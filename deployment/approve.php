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

// Swap live symlink (atomic)
// point current to the new release folder
exec("ln -sfn " . escapeshellarg($path) . " " . escapeshellarg("$baseDir/current"));

// keep track of what was last approved
file_put_contents("$baseDir/last_approved.txt", $release);

// Clear pending pointer
// if this release is pending, remove the pointer since it is live now
$pending = @readlink("$baseDir/pending");
if ($pending === $path) {
     @unlink("$baseDir/pending");
}

// Publish approval event
// send a message to the queue so other scripts know this got approved
publishDeployEvent('set_status', [
    'release' => $release,
    'environment' => $env,
    'status' => 'approved',
    'decided_by' => $by,
    'notes' => $notes,
]);

echo "$release approved and live.\n";

// If on QA, promote to prod
// only runs if we're approving on qa, pushes it to prod server
if ($env === 'qa') {
$prodHost = '100.80.61.121';
$prodUser = 'xaviersylvers';
$tarball = "/tmp/{$release}.tar.gz";

// zip up the release folder so we can send it over 
exec("tar -czf " . escapeshellarg($tarball) .
" -C " . escapeshellarg("$baseDir/releases") .
" " . escapeshellarg($release) . " 2>&1", $tOut, $tRet);

if ($tRet !== 0) {
  echo "WARNING: tarball failed — prod not updated\n"; exit(0);
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
