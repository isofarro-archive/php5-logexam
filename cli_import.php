<?php

require_once dirname(__FILE__) . '/LogStore.php';
require_once dirname(__FILE__) . '/LogFileHandler.php';
require_once dirname(__FILE__) . '/LogExaminer.php';

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


function _exit_usage() {
	echo <<<USAGE
	usage:	cli_import 'dataset' [file]
	or	cli_import 'dataset' < zcat [file.gz] 
USAGE;
	exit;
}


?>