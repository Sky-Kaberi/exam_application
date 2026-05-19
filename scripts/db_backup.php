#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

$isCli = PHP_SAPI === 'cli';

function outputMessage(string $message, bool $isCli, int $httpCode = 200): void
{
    if ($isCli) {
        echo $message;
        return;
    }

    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['message' => $message], JSON_UNESCAPED_SLASHES) ?: '{"message":"Unable to encode response."}';
}

function findMySqlDumpBinary(): ?string
{
    $candidates = [
        'mysqldump',
        '/usr/bin/mysqldump',
        '/usr/local/mysql/bin/mysqldump',
        '/opt/plesk/mysql/8.0/bin/mysqldump',
        '/opt/plesk/mysql/5.7/bin/mysqldump',
        '/opt/plesk/mysql/5.6/bin/mysqldump',
        '/usr/bin/mariadb-dump',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === 'mysqldump') {
            $resolved = trim((string) shell_exec('command -v mysqldump 2>/dev/null'));
            if ($resolved !== '') {
                return $resolved;
            }
            continue;
        }

        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

if ($isCli) {
    $options = getopt('', ['backup-dir::', 'retention-days::']);
} else {
    $options = [
        'backup-dir' => isset($_REQUEST['backup_dir']) ? (string) $_REQUEST['backup_dir'] : null,
        'retention-days' => isset($_REQUEST['retention_days']) ? (string) $_REQUEST['retention_days'] : null,
    ];

    $webToken = defined('BACKUP_WEB_TOKEN') ? (string) BACKUP_WEB_TOKEN : '';
    $requestToken = isset($_REQUEST['token']) ? (string) $_REQUEST['token'] : '';

    if ($webToken === '' || !hash_equals($webToken, $requestToken)) {
        outputMessage('Unauthorized request. Configure BACKUP_WEB_TOKEN and pass token query parameter.', false, 403);
        exit(1);
    }
}

$backupDir = isset($options['backup-dir']) && $options['backup-dir'] !== null && $options['backup-dir'] !== false && $options['backup-dir'] !== ''
    ? (string) $options['backup-dir']
    : __DIR__ . '/../backups';
$retentionDays = isset($options['retention-days']) && $options['retention-days'] !== null && $options['retention-days'] !== false && $options['retention-days'] !== ''
    ? (int) $options['retention-days']
    : 7;

if ($retentionDays < 0) {
    outputMessage('Invalid retention value. Use 0 or a positive integer.', $isCli, 422);
    exit(1);
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    outputMessage("Unable to create backup directory: {$backupDir}", $isCli, 500);
    exit(1);
}

if (!is_writable($backupDir)) {
    outputMessage("Backup directory is not writable: {$backupDir}", $isCli, 500);
    exit(1);
}

$dumpBinary = findMySqlDumpBinary();
if ($dumpBinary === null) {
    outputMessage('mysqldump not found. Install MySQL client tools or use mariadb-dump.', $isCli, 500);
    exit(1);
}

$timestamp = date('Ymd_His');
$filename = sprintf('%s_%s.sql.gz', DB_NAME, $timestamp);
$backupFile = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

$command = sprintf(
    '%s --host=%s --user=%s --password=%s --single-transaction --quick --routines --triggers %s | gzip > %s',
    escapeshellarg($dumpBinary),
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    escapeshellarg($backupFile)
);

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open($command, $descriptors, $pipes);

if (!is_resource($process)) {
    outputMessage('Unable to start database dump process.', $isCli, 500);
    exit(1);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0) {
    if (is_file($backupFile)) {
        @unlink($backupFile);
    }

    $details = [];
    if (is_string($stderr) && trim($stderr) !== '') {
        $details[] = trim($stderr);
    }
    if (is_string($stdout) && trim($stdout) !== '') {
        $details[] = trim($stdout);
    }

    $message = 'Database backup failed.' . (count($details) > 0 ? ' ' . implode(' ', $details) : '');
    outputMessage($message, $isCli, 500);
    exit($exitCode);
}

if ($retentionDays > 0) {
    $cutoffTimestamp = strtotime('-' . $retentionDays . ' days');
    foreach (glob(rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . DB_NAME . '_*.sql.gz') ?: [] as $filePath) {
        if (is_file($filePath) && filemtime($filePath) < $cutoffTimestamp) {
            @unlink($filePath);
        }
    }
}

outputMessage("Backup created successfully: {$backupFile}", $isCli);
exit(0);
