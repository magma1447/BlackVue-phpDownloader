<?php

define('EXIT_SUCCESS', 0);
define('EXIT_PARTIAL', 1);
define('EXIT_ALREADYRUNNING', 2);
define('EXIT_NOCONNECT', 3);
define('EXIT_FILELISTPARSEERROR', 10);

$config = array(
	'pidFile' => '/tmp/BlackVue-phpDownloader.pid',
	'hostname' => 'blackvue',
	'destination' => '/data/blackvue/',
	'printSummary' => TRUE,
	);


function echoerr($string) {
	fwrite(STDERR, $string);
}



if(file_exists($config['pidFile'])) {
	$oldPid = file_get_contents($config['pidFile']);
	if(file_exists("/proc/{$oldPid}")) {
		$cmd = file_get_contents("/proc/{$oldPid}/cmdline");
		if(strpos($cmd, $argv[0]) !== FALSE) {
			echo "Already running\n";
			exit(EXIT_ALREADYRUNNING);
		}
	}
}
$pid = getmypid();
file_put_contents($config['pidFile'], $pid);



// Make sure destination has a trailing slash, it's assumed later on in the code
$destination = rtrim($config['destination'], '/') . '/';
$report = array();


$ch = curl_init();
curl_setopt_array(
	$ch,
	array(
		CURLOPT_URL => "http://{$config['hostname']}/blackvue_vod.cgi",
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_LOW_SPEED_LIMIT => 1024*100,	// 100 kB/s
		CURLOPT_LOW_SPEED_TIME => 10,
		CURLOPT_TIMEOUT => 60,
		)
	);
$rsp = curl_exec($ch);
$errno = curl_errno($ch);
if(empty($rsp) || $errno !== 0) {
	echo "Error when fetching list, errno: {$errno}\n";
	exit(EXIT_NOCONNECT);
}
$filenames = explode("\r\n", $rsp);

// Remove "v:2.00"
$filenames = array_filter($filenames, function($line) {
	return (strpos($line, 'n:/') === 0) ? TRUE : FALSE;
});

// Each entry now looks like: "n:/Record/20170719_172930_NF.mp4,s:1000000"
$filenames = array_map(function($line) {
	preg_match('/n:\/.*Record\/(.*\.mp4).*/', $line, $matches);
	if(!empty($matches[1])) {
		return $matches[1];
	}

	var_dump($line);
	echoerr("Error: Failed to parse line\n");
	exit(EXIT_FILELISTPARSEERROR);
}, $filenames);


$cnt = count($filenames);
echo "Found {$cnt} filenames, will remove those that already exists\n";
$report[] = "Found {$cnt} filenames in the camera";

$filenames = array_filter($filenames, function($filename) use ($destination) {
	return (file_exists($destination . $filename)) ? FALSE : TRUE;
});
$filesToFetch = count($filenames);
$estimatedSeconds = $filesToFetch*25;
echo "{$filesToFetch} files left to fetch, estimated time to fetch all is {$estimatedSeconds} seconds\n";
$report[] = "{$filesToFetch} filenames left to fetch";


if($filesToFetch == 0) {
	exit(EXIT_SUCCESS);
}


// Sort the array to fetch newest first
rsort($filenames);


echo PHP_EOL;
$numFetched = 0;
$success = TRUE;
foreach($filenames as $filename) {
	echo "Fetching file {$filename}\n";

	$t1 = microtime(TRUE);
	curl_setopt($ch, CURLOPT_URL, "http://{$config['hostname']}/Record/{$filename}");
	$rsp = curl_exec($ch);
	$errno = curl_errno($ch);
	if(empty($rsp) || $errno !== 0) {
		echoerr("Error when fetching file {$filename}, errno: {$errno}\n");
		$success = FALSE;
		break;
	}
	$t2 = microtime(TRUE);

	$kB = round(strlen($rsp)/1024);
	$seconds = round($t2 - $t1, 1);
	$kBps = round($kB/$seconds);
	echo "Fetched {$kB} kB in {$seconds} seconds ({$kBps} kB/s)\n";

	echo "Saving file {$filename}\n";
	file_put_contents($destination . $filename, $rsp);

	$numFetched++;
	echo PHP_EOL;
}
curl_close($ch);


echo "All files fetched\n";
if($success) {
	$report[] = "All {$numFetched} files fetched";
} else {
	$report[] = "Fetched {$numFetched} of {$filesToFetch} files";
}

if($config['printSummary']) {
	$report = implode(PHP_EOL, $report) . PHP_EOL;
	echoerr($report);
}

if($success) {
	exit(EXIT_SUCCESS);
} else {
	exit(EXIT_PARTIAL);
}
