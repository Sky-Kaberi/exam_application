#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from CLI.\n");
    exit(1);
}

$options = getopt('', ['backup-dir::', 'retention-days::']);
$backupDir = isset($options['backup-dir']) && $options['backup-dir'] !== false
    ? (string) $options['backup-dir']
    : __DIR__ . '/../backups';
$retentionDays = isset($options['retention-days']) && $options['retention-days'] !== false
    ? (int) $options['retention-days']
    : 7;

if ($retentionDays < 0) {
    fwrite(STDERR, "Invalid retention-days value. Use 0 or a positive integer.\n");
    exit(1);
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Unable to create backup directory: {$backupDir}\n");
    exit(1);
}

if (!is_writable($backupDir)) {
    fwrite(STDERR, "Backup directory is not writable: {$backupDir}\n");
    exit(1);
}

$timestamp = date('Ymd_His');
$filename = sprintf('%s_%s.sql.gz', DB_NAME, $timestamp);
$backupFile = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

$command = sprintf(
    'mysqldump --host=%s --user=%s --password=%s --single-transaction --quick --routines --triggers %s | gzip > %s',
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    escapeshellarg($backupFile)
);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptors, $pipes);

if (!is_resource($process)) {
    fwrite(STDERR, "Unable to start mysqldump process.\n");
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

    fwrite(STDERR, "Database backup failed.\n");
    if ($stderr !== false && trim($stderr) !== '') {
        fwrite(STDERR, trim($stderr) . "\n");
    }
    if ($stdout !== false && trim($stdout) !== '') {
        fwrite(STDERR, trim($stdout) . "\n");
    }

    exit($exitCode);
}

if ($retentionDays > 0) {
    $cutoffTimestamp = strtotime('-' . $retentionDays . ' days');

    foreach (glob(rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . DB_NAME . '_*.sql.gz') ?: [] as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }

        if (filemtime($filePath) < $cutoffTimestamp) {
            @unlink($filePath);
        }
    }
}

echo "Backup created successfully: {$backupFile}\n";
exit(0);
