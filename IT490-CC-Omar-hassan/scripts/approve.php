#!/usr/bin/php
<?php 

require_once __DIR__ . '/rmqhelper.php';

$release = $argv[1] ?? null;
$env = $argv[2] ??  null;
$by = $argv[3] ?? trim(shell_exec('whoami'));
$notes = $argv[4] ?? null;

if (!$release || !$env) {
    fwrite(STDERR, "Usage: php approve.php <release> <env> [by] [notes]\n");
    exit(1);
}

$baseDir = '/var/www/sample';
$path = "$baseDir/releases/$release";

if (!is_dir($path)) {
      fwrite(STDERR, "Release not found: $release\n");
      exit(1); }

//swap to current
exec("ln -sfn " . escapeshellarg($path) . " " . escapeshellarg("$baseDir/current"));

//track approvals
file_put_contents("$baseDir/last_approved.txt", $release);

//remove pending pointer
$pending = @readlink("$baseDir/pending");
if ($pending === $path) {
     @unlink("$baseDir/pending");
}

//publish to rmq and others
publishDeployEvent('set_status', [
    'release' => $release,
    'environment' => $env,
    'status' => 'approved',
    'decided_by' => $by,
    'notes' => $notes,
]);

echo "$release approved and live.\n";

//promote to prod (if on qa)
if ($env === 'qa') {
$prodHost = '100.70.7.44';
$prodUser = 'omar-hassan';
$tarball = "/tmp/{$release}.tar.gz";

//tarball so we can send it over 
exec("tar -czf " . escapeshellarg($tarball) .
" -C " . escapeshellarg("$baseDir/releases") .
" " . escapeshellarg($release) . " 2>&1", $tOut, $tRet);

if ($tRet !== 0) {
  echo "WARNING: tarball failed — prod not updated\n"; exit(0);
  echo "tar output: " . implode("\n", $tOut) . "\n";
  exit(0);
}

exec("rsync -az " . escapeshellarg($tarball) .
" {$prodUser}@{$prodHost}:/tmp/ 2>&1", $rOut, $rRet);

if ($rRet !== 0) { echo "WARNING: rsync to prod failed\n"; exit(0); }
$commit = trim(shell_exec("cd " . escapeshellarg($path) . " && git rev-parse HEAD 2>/dev/null")) ?: '';
exec("ssh {$prodUser}@{$prodHost} 'php /var/www/sample/scripts/receive.php " .
escapeshellarg($release) . " " . escapeshellarg($commit) . "' 2>&1", $sOut);

echo "Prod staging: " . implode("\n", $sOut) . "\n";

unlink($tarball);
}
