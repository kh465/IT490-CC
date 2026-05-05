#!/usr/bin/php
<?php
require_once __DIR__ . '/rmqhelper.php';

$release = $argv[1] ?? null;
$commit  = $argv[2] ?? '';
$env     = $argv[3] ?? 'qa';

if (!$release) {
    fwrite(STDERR, "error! no release found!\n");
    exit(1);
}

$siteDir = '/var/www/sample';
$tarball = "/tmp/{$release}.tar.gz";
$relPath = "$siteDir/releases/$release";

if (!is_dir($relPath))
    mkdir($relPath, 0755, true);

# tarballs are now content-only (fix in push.php / approve.php),
# so extract straight into relPath. no more nested folder.
exec("tar -xzf " . escapeshellarg($tarball) . " -C " .
    escapeshellarg($relPath) . " 2>&1", $out, $ret);
if ($ret !== 0) {
    fwrite(STDERR, "failed to unpack!\n");
    fwrite(STDERR, implode("\n", $out) . "\n");
    exit(1);
}
@unlink($tarball);

#pending pointer for tracking
exec("ln -sfn " . escapeshellarg($relPath) . " " . escapeshellarg("$siteDir/pending"));

#point apache at this release so it can be previewed before approval
exec("ln -sfn " . escapeshellarg($relPath) . " " . escapeshellarg("$siteDir/live"));

publishDeployEvent('log_deploy', [
    'env' => $env,
    'release' => $release,
    'commit' => $commit,
    'status' => 'pending',
    'decided_by' => null,
    'notes' => null,
]);

echo "$release pending on $env, awaiting decision... (use approve/reject)\n";
