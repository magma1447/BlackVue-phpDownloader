<?php

$hostname = 'blackvue';
$destination = '/data/blackvue/';


function echoerr($string) {
	fwrite(STDERR, $string);
}


// Make sure destination has a trailing slash, it's assumed later on in the code
$destination = rtrim($destination, '/') . '/';


$ch = curl_init();
curl_setopt_array(
	$ch,
	array(
		CURLOPT_URL => "http://{$hostname}/blackvue_vod.cgi",
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
	echoerr("Error when fetching list, errno: {$errno}\n");
	exit(1);
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
	exit(1);
}, $filenames);


$cnt = count($filenames);
echo "Found {$cnt} filenames, will remove those that already exists\n";

$filenames = array_filter($filenames, function($filename) use ($destination) {
	return (file_exists($destination . $filename)) ? FALSE : TRUE;
});
$cnt = count($filenames);
$estimatedSeconds = $cnt*25;
echo "{$cnt} files left to fetch, estimated time to fetch all is {$estimatedSeconds} seconds\n";


// Sort the array to fetch newest first
rsort($filenames);


foreach($filenames as $filename) {
	echo "Fetching file {$filename}\n";

	$t1 = microtime(TRUE);
	curl_setopt($ch, CURLOPT_URL, "http://{$hostname}/Record/{$filename}");
	$rsp = curl_exec($ch);
	$errno = curl_errno($ch);
	if(empty($rsp) || $errno !== 0) {
		echoerr("Error when fetching file {$filename}, errno: {$errno}\n");
		exit(1);
	}
	$t2 = microtime(TRUE);

	$kB = round(strlen($rsp)/1024);
	$seconds = round($t2 - $t1, 1);
	$kBps = round($kB/$seconds);
	echo "Fetched {$kB} kB in {$seconds} seconds ({$kBps} kB/s)\n";

	echo "Saving file {$filename}\n";
	file_put_contents($destination . $filename, $rsp);

	echo PHP_EOL;
}


curl_close($ch);
