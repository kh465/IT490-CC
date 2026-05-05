#!/usr/bin/php
<?php
require_once __DIR__ . '/rmqhelper.php';

$baseDir = '/var/www/sample';
$repoDir = "$baseDir/repo";
$webDir	 = "$repoDir/IT490-CC-Omar-hassan";
$releaseDate = date('Ymd_Hi');
$qaVM = '100.103.112.3';
$qaUser = 'omar-hassan';
$gitURL = 'https://github.com/kh465/IT490-CC.git';
$gitBranch = 'in_dev';

echo "releaseDate: $releaseDate\n";

#clone if repo is missing, reset if it does (ensures repo is up to date)
if (!is_dir("$repoDir/.git")) {
    exec("git clone -b " . escapeshellarg($gitBranch) . " " . escapeshellarg($gitURL) . " " . escapeshellarg($repoDir) . " 2>&1", $out, $ret);
} else {
    exec("cd " . escapeshellarg($repoDir) . " && git fetch origin " . escapeshellarg($gitBranch) . " && git reset --hard origin/" . escapeshellarg($gitBranch) . " 2>&1", $out, $ret);
}

if ($ret !== 0) {
    publishDeployEvent('log_deploy', [
        'env' => 'in_dev',
        'release' => $releaseDate,
        'commit' => '',
        'status' => 'FAIL',
        'decided_by' => null,
        'notes' => implode("\n", $out),
    ]);
    fwrite(STDERR, "git refresh failed!\n");
    fwrite(STDERR, implode("\n", $out) . "\n");
    exit(1);
}

#point live at the repo so apache serves real-time
exec("ln -sfn " . escapeshellarg($webDir) . " " . escapeshellarg("$baseDir/live"));

$commit = trim(shell_exec("cd " . escapeshellarg($repoDir) . " && git rev-parse HEAD"));

publishDeployEvent('log_deploy', [
    'env' => 'in_dev',
    'release' => $releaseDate,
    'commit' => $commit,
    'status' => 'approved',
    'decided_by' => 'autoapprove',
    'notes' => 'auto approved to dev',
]);
echo "dev live, commit $commit ($releaseDate)\n";

$tarball = "/tmp/{$releaseDate}.tar.gz";
exec("tar -czf " . escapeshellarg($tarball) .
    " -C " . escapeshellarg($webDir) . " . 2>&1", $tOut, $tRet);
if ($tRet !== 0) {
    echo "failed to tarball! qa unchanged\n";
    echo "tar output: " . implode("\n", $tOut) . "\n";
    exit(0);
}

exec("rsync -az " . escapeshellarg($tarball) .
    " {$qaUser}@{$qaVM}:/tmp/ 2>&1", $rOut, $rRet);
if ($rRet !== 0) {
    echo "rsync to qa failed!\n";
    echo "rsync output: " . implode("\n", $rOut) . "\n";
    @unlink($tarball);
    exit(0);
}

exec("ssh {$qaUser}@{$qaVM} 'php /var/www/sample/scripts/receive.php " .
    escapeshellarg($releaseDate) . " " . escapeshellarg($commit) . " qa' 2>&1", $sOut);
echo "qa staged: " . implode("\n", $sOut) . "\n";
@unlink($tarball);
