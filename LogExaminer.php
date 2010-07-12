<?php

class LogExaminer {
	var $dataset;
	var $filters;
	
	public function __construct($dataset) {
		$this->_setDataset($dataset);
	}
	
	public function import($file, $filter=NULL) {
		$filename = NULL;
		if ($filter) {
			$this->setFilter($filter);
		}
		
		if (is_string($file) && is_file($file)) {
			$filename = $file;
			$file = fopen($filename, 'r');
		}
		
		if ($file && is_resource($file)) {
			echo "INFO: Processing input stream\n";
			
			$count = 0;
			
			while ($line = fgets($file, 4096)) {
				$count++;
				printf("%04d: ", $count);
				echo $line;
				
				$components = $this->parse($line);
				print_r($components);
				
				if ($count>1) {
					break;
				}
			}
		}
		

		if ($filename && is_resouce($file)) {
			fclose($file);
		}
	}
	
	
	public function parse($line) {
		$components = (object)NULL;
		if (preg_match("/^(\d+\.\d+\.\d+\.\d+) - - \[(\d{2}\/\w{3}\/\d{4}:\d{2}:\d{2}:\d{2}) ([^\]]+)\] \"(\w+) ([^ ]+) (HTTP\/1\.\d)\" (\d+) (\d+) \"([^\"]+)\" \"([^\"]+)\"/", $line, $matches)) {
			//print_r($matches);
			$components->ipAddress = $matches[1];
			$components->date      = $matches[2];
			$components->timezone  = $matches[3];
			$components->method    = $matches[4];
			$components->url       = $matches[5];
			$components->version   = $matches[6];
			$components->status    = $matches[7];
			$components->length    = $matches[8];
			$components->referrer  = $matches[9];
			$components->userAgent = $matches[10];
		}
		return $components;
	}
	
	public function setFilter($filter) {
		$this->filters = $filter;
	}
	
	protected function _setDataset($dataset) {
		$this->dataset = $dataset;
	}
	
}


?>