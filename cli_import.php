<?php

require_once dirname(__FILE__) . '/LogFilterBase.php';
require_once dirname(__FILE__) . '/LogEvents.php';
require_once dirname(__FILE__) . '/LogStore.php';
require_once dirname(__FILE__) . '/LogFileHandler.php';
require_once dirname(__FILE__) . '/LogExaminer.php';

$start_time = microtime(true);

//print_r($argv);

if (!$argv[1]) { 
	exit_usage();
}


$dataset = $argv[1];
$logexam = new LogExaminer($dataset);
// TODO: Set filters and other listeners here

if ($argv[2]) {
	echo "Reading list of files\n";
	$filenames = array_slice($argv, 2);
	$logexam->import($filenames);
}
else {
	echo "Reading from standard input\n";
	// Streaming in from STDIN
	$logexam->importFile(STDIN);
}

$end_time = microtime(true);
echo "Time taken: ", ($end_time - $start_time), "\n";

// TODO: post-processing
$logexam->postProcessing();


function _exit_usage() {
	echo <<<USAGE
	usage:	cli_import 'dataset' [file]
	or	cli_import 'dataset' < zcat [file.gz] 
USAGE;
	exit;
}


?>