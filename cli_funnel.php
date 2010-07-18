<?php

require_once dirname(__FILE__) . '/LogExaminer.php';

interface InputHandle {
	function open($name, $mode='r');
	function close(); 
}

class FileHandle implements InputHandle {
	var $handle;
	
	public function open($name, $mode='r') {
		$this->handle = fopen($name, $mode);
	}
	
	public function getHandle() {
		return $this->handle;
	}
	
	public function close() {
		fclose($this->handle);
	}
}

class GzipPipeHandle implements InputHandle {
	var $handle;
	var $filename;
	var $pipename;

	public function open($name, $mode='r') {
		$this->filename = $name;
		$this->pipename = '/tmp/' . basename($name) . '-PIPE';
		$cmd    = "gzip -cd $this->filename";
		//echo "\nRunning command: $cmd\n";
		$this->handle = popen($cmd, $mode);
	}
	
	public function getHandle() {
		return $this->handle;
	}

	public function close() {
		pclose($this->handle);
	}
}



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
		echo "Processing $filename:";
		
		if (preg_match('/\.gz$/', $filename)) {
			echo 'Z';
			$h = new GzipPipeHandle();
		}
		else {
			echo 'F';
			$h = new FileHandle();
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