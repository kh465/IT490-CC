#!/usr/bin/php
<?php
require_once __DIR__ . '/rmqhelper.php';

$target = $argv[1] ?? null;
$env = $argv[2] ?? 'prod';
$by = $argv[3] ?? trim(shell_exec('whoami'));
$reason = $argv[4] ?? 'manual rollback via CLI';

$baseDir = '/var/www/sample';

if (!$target) {
    $lastApprovedFile = "$baseDir/last_approved.txt";
    if (!file_exists($lastApprovedFile)) {
        fwrite(STDERR, "No last_approved.txt found. Please give a specific release name.\n");
        fwrite(STDERR, "How to use: sudo ./rollback.php <release> [env] [by] [reason]\n");
        exit(1);
    }
    $target = trim(file_get_contents($lastApprovedFile));
}

$path = "$baseDir/releases/$target";
if (!is_dir($path)) {
    fwrite(STDERR, "Release folder not found: $path\n");
    exit(1);
}

#update current and live so apache reflects the rollback
exec("ln -sfn " . escapeshellarg($path) . " " . escapeshellarg("$baseDir/current"));
exec("ln -sfn " . escapeshellarg($path) . " " . escapeshellarg("$baseDir/live"));
file_put_contents("$baseDir/last_approved.txt", $target);

try {
    publishDeployEvent('log_rollback', [
        'env' => $env,
        'release' => $target,
        'decided_by' => $by,
        'notes' => $reason,
    ]);
    echo "Rolled back to $target on $env.\n";
} catch (Exception $e) {
    fwrite(STDERR, "Rolled back to $target on $env, but event publish failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Log manually or check /var/log/deploy_mq_errors.log\n");
    exit(2);
}
