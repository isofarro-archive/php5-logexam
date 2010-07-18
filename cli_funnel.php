<?php

require_once dirname(__FILE__) . '/LogFileHandler.php';
require_once dirname(__FILE__) . '/LogExaminer.php';

//print_r($argv);

if (!$argv[1]) { 
	_exit_usage();
}

$filelen = count($argv);
$fileHandles = array();

for($i=1; $i<$filelen; $i++) {
	$filename = $argv[$i];
	if (is_file($filename)) {
		$logfile = '';
		echo "Processing $filename: ";
		
		if (preg_match('/\.gz$/', $filename)) {
			echo 'Z';
			$h = new GzipLogFileHandle();
		}
		else {
			echo 'F';
			$h = new LogFileHandle();
		}


		$h->open($filename, 'r');
		$count = 0;
		
		$fh = $h->getHandle();
		while ($line = fgets($fh, 4096)) {
			$count++;
			//printf("%04d: ", $count);
			//echo $line;	

			if ($count>1000) {
				echo '.';
				$count=0;
				//break;
			}
		}
		echo "\n";
		$h->close();
	}
	else {
		echo "Skipping $filename - not a file.";
	}
}



function _exit_usage() {
	echo <<<USAGE
Usage: cli_funnel.php [files]

USAGE;
	exit;
}


?>