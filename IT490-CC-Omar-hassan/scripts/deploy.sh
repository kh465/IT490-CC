#!/usr/bin/php
<?php
require_once __DIR__ . '/rmqhelper.php';

$baseDir = '/var/www/sample';
$releaseDate = date('Ymd_Hi');
echo "releaseDate: $releaseDate\n";
$newRelease = trim("$baseDir/releases/$releaseDate");
echo "newRelease: $newRelease\n";
$qaVM = '100.103.112.3';
$qaUser = 'omar-hassan';

#get the dev branch from github
exec("git clone --no-checkout -b in_dev https://github.com/kh465/IT490-CC.git " . 
	escapeshellarg($newRelease) . " 2>&1", $out, $ret);
if ($ret !== 0) {
	publishDeployEvent('log_deploy', [
		'env' => 'in_dev',
		'release' => $releaseDate,
		'commit' => '',
		'status' => 'FAIL',
		'decided_by' => null,
		'notes' => implode("\n", $out),]);
	fwrite(STDERR, "git clone failed!\n");
	exit(1);
}

exec("git -C " . escapeshellarg($newRelease) . " sparse-checkout set IT490-CC-Omar-hassan 2>&1");
exec("git -C " . escapeshellarg($newRelease) . " checkout 2&>1");

$commit = trim(shell_exec("cd " . escapeshellarg($newRelease) . " && git rev-parse HEAD"));

exec("ln -sfn " . escapeshellarg($newRelease) . "/IT490-CC-Omar-hassan " .
	escapeshellarg("$baseDir/current"));

publishDeployEvent('log_deploy', [
	'env' => 'in_dev',
	'release' => $releaseDate,
	'commit' => $commit,
	'status' => 'approved',
	'decided_by' => 'autoapprove',
	'notes' => 'auto approved to dev',]);
echo "pushed dev, live on $releaseDate\n";

#package and send over to qa vm
$tarball = "/tmp/{$releaseDate}.tar.gz";
$subfolderPath = "$newRelease/IT490-CC-Omar-hassan";
exec("tar -czf " . escapeshellarg($tarball) . " -C " . escapeshellarg($subfolderPath) ." . 2>&1", $tOut, $tRet);
if ($tRet !== 0) {
	echo "failed to tarball! qa unchanged\n";
	exit(0);
}

exec("rsync -az " . escapeshellarg($tarball) . " {$qaUser}@{$qaVM}:/tmp/ 2>&1", $rOut, $rRet);
if ($rRet !== 0) {
	echo "rsync to qa failed!\n";
	exit(0);
}

exec("ssh {$qaUser}@{$qaVM} 'php /var/www/sample/scripts/receive.php " .
	escapeshellarg($releaseDate) . " " . escapeshellarg($commit) . "' 2>&1", $sOut);
echo "qa staged: " . implode("\n", $sOut) . "\n";
unlink($tarball);
