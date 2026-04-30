<?php
require_once __DIR__ . '/mq.php';

$target = $argv[1] ?? null;
$by = $argv[2] ?? trim(shell_exec('whoami'));
$reason = $argv[3] ?? 'manual rollback via CLI';

$baseDir = '/var/www/app';
$env = 'production';


if (!$target) {
    $lastApprovedFile = "$baseDir/last_approved.txt";
    if (!file_exists($lastApprovedFile)) {
        fwrite(STDERR, "No last_approved.txt found. Please give a  specific release name.\n");
        fwrite(STDERR, "How to use: php rollback.php <release> [by] [reason]\n");
        exit(1);
    }
    $target = trim(file_get_contents($lastApprovedFile));
}

$path = "$baseDir/releases/$target";
if (!is_dir($path)) {
    fwrite(STDERR, "Release folder not found: $path\n");
    exit(1);
}

exec("ln -sfn " . escapeshellarg($path) . " " . escapeshellarg("$baseDir/current"));


try {
    publishDeployEvent('log_rollback', [
        'env' => $env,
        'release' => $target,
        'decided_by' => $by,
        'notes' => $reason,
    ]);
    echo "Rolled back to $target on $env.\n";
} catch (Exception $e) {

    fwrite(STDERR, "Rolled back to the $target on $env, but event publish failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Log manually or check /var/log/deploy_mq_errors.log\n");
    exit(2);
}

