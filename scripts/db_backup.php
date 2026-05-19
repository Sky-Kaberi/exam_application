#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

$isCli = PHP_SAPI === 'cli';

function outputMessage(string $message, bool $isCli, int $httpCode = 200): void
{
    if ($isCli) {
        echo $message . PHP_EOL;
        return;
    }

    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['message' => $message], JSON_UNESCAPED_SLASHES) ?: '{"message":"Unable to encode response."}';
}

function writeLog(string $logFile, string $message): void
{
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

$options = $isCli
    ? getopt('', ['backup-dir::', 'retention-days::'])
    : [
        'backup-dir' => isset($_REQUEST['backup_dir']) ? (string) $_REQUEST['backup_dir'] : null,
        'retention-days' => isset($_REQUEST['retention_days']) ? (string) $_REQUEST['retention_days'] : null,
    ];

if (!$isCli) {
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

$logFile = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backup.log';
$timestamp = date('Y-m-d_H-i-s');
$sqlFile = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . DB_NAME . '_' . $timestamp . '.sql';
$gzFile = $sqlFile . '.gz';

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    writeLog($logFile, 'ERROR: DB connection failed: ' . $mysqli->connect_error);
    outputMessage('ERROR: DB connection failed: ' . $mysqli->connect_error, $isCli, 500);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$fh = fopen($sqlFile, 'wb');
if (!$fh) {
    writeLog($logFile, 'ERROR: Could not create SQL backup file.');
    outputMessage('ERROR: Could not create SQL backup file.', $isCli, 500);
    exit(1);
}

fwrite($fh, "-- Database Backup\n");
fwrite($fh, "-- Database: `" . DB_NAME . "`\n");
fwrite($fh, '-- Generated: ' . date('Y-m-d H:i:s') . "\n\n");
fwrite($fh, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
fwrite($fh, "SET AUTOCOMMIT = 0;\n");
fwrite($fh, "START TRANSACTION;\n");
fwrite($fh, "SET time_zone = \"+00:00\";\n\n");
fwrite($fh, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
fwrite($fh, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
fwrite($fh, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
fwrite($fh, "/*!40101 SET NAMES utf8mb4 */;\n\n");

$tablesRes = $mysqli->query('SHOW TABLES');
if (!$tablesRes) {
    fclose($fh);
    @unlink($sqlFile);
    writeLog($logFile, 'ERROR: Could not list tables: ' . $mysqli->error);
    outputMessage('ERROR: Could not list tables: ' . $mysqli->error, $isCli, 500);
    exit(1);
}

while ($tableRow = $tablesRes->fetch_array(MYSQLI_NUM)) {
    $table = (string) $tableRow[0];

    $createRes = $mysqli->query('SHOW CREATE TABLE `' . $mysqli->real_escape_string($table) . '`');
    if ($createRes) {
        $createRow = $createRes->fetch_assoc();
        fwrite($fh, "-- Table structure for table `{$table}`\n");
        fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($fh, $createRow['Create Table'] . ";\n\n");
        $createRes->free();
    }

    $dataRes = $mysqli->query('SELECT * FROM `' . $mysqli->real_escape_string($table) . '`');
    if ($dataRes) {
        fwrite($fh, "-- Dumping data for table `{$table}`\n");
        while ($row = $dataRes->fetch_assoc()) {
            $columns = array_map(static function ($col): string {
                return '`' . str_replace('`', '``', (string) $col) . '`';
            }, array_keys($row));
            $values = array_map(static function ($val) use ($mysqli): string {
                return $val === null ? 'NULL' : "'" . $mysqli->real_escape_string((string) $val) . "'";
            }, array_values($row));

            fwrite($fh, 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
        }
        fwrite($fh, "\n");
        $dataRes->free();
    }
}
$tablesRes->free();

fwrite($fh, "COMMIT;\n");
fwrite($fh, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
fwrite($fh, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
fwrite($fh, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");

fclose($fh);
$mysqli->close();

$in = fopen($sqlFile, 'rb');
$out = gzopen($gzFile, 'wb9');
if (!$in || !$out) {
    if (is_resource($in)) {
        fclose($in);
    }
    if (is_resource($out)) {
        gzclose($out);
    }
    @unlink($gzFile);
    writeLog($logFile, 'ERROR: Could not create gzip file.');
    outputMessage('ERROR: Could not create gzip file.', $isCli, 500);
    exit(1);
}

while (!feof($in)) {
    $chunk = fread($in, 8192);
    if ($chunk === false) {
        break;
    }
    gzwrite($out, $chunk);
}

fclose($in);
gzclose($out);
@unlink($sqlFile);

if ($retentionDays > 0) {
    $cutoffTimestamp = strtotime('-' . $retentionDays . ' days');
    foreach (glob(rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . DB_NAME . '_*.sql.gz') ?: [] as $filePath) {
        if (is_file($filePath) && filemtime($filePath) < $cutoffTimestamp) {
            @unlink($filePath);
        }
    }
}

writeLog($logFile, 'Backup created successfully: ' . $gzFile);
outputMessage('Backup created successfully: ' . $gzFile, $isCli);
exit(0);
