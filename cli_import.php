<?php

require_once dirname(__FILE__) . '/LogExaminer.php';

print_r($argv);

if (!$argv[1]) { 
	exit_usage();
}


$dataset = $argv[1];
$logexam = new LogExaminer($dataset);

if ($argv[2]) {
	echo "INFO: Importing file: {$filename}\n";
	$logexam->import($filename);
}
else {
	echo "Reading from standard input\n";
	// Streaming in from STDIN
	$logexam->import(STDIN);
}


function _exit_usage() {
	echo <<<USAGE
	usage:	cli_import 'dataset' [file]
	or	cli_import 'dataset' < zcat [file.gz] 
USAGE;
	exit;
}


?>