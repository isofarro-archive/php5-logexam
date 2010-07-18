<?php

class LogExaminer {
	var $dataset;
	var $datasource;
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
			$pages = 0;
			
			while ($line = fgets($file, 4096)) {
				$count++;
				//printf("%04d: ", $count);
				//echo $line;
				
				$entry = $this->parse($line);
				//print_r($entry);
				$this->add($entry);
				
				if ($count>1000) {
					echo '.'; $count=0; $pages++;
					//break;
				}
			}
		}
		
		echo "\nAdded ", ($pages * 1000) + $count, " entries\n";
		

		if ($filename && is_resource($file)) {
			fclose($file);
		}
	}
	
	public function add($entry) {
		$this->datasource->add($entry);
	}
	
	public function parse($line) {
		$components = (object)NULL;
		if (preg_match("/^(\d+\.\d+\.\d+\.\d+) ([^\s]+) ([^\s]+) \[(\d{2}\/\w{3}\/\d{4}:\d{2}:\d{2}:\d{2}) ([^\]]+)\] \"(\w+) ([^ ]+) (HTTP\/1\.\d)\" (\d+) (\d+|-) \"([^\"]*)\" \"([^\"]+)\"/", $line, $matches)) {
			## Apache combined log format
			//print_r($matches);
			$components->ipAddress = $matches[1];
			$components->date      = $matches[4];
			$components->timezone  = $matches[5];
			$components->method    = $matches[6];
			$components->url       = $matches[7];
			$components->http      = $matches[8];
			$components->status    = $matches[9];
			$components->length    = $matches[10];
			$components->referrer  = $matches[11];
			$components->userAgent = $matches[12];
		}
		else {
			echo "\n{$line}\n";
		}
		return $components;
	}
	
	public function setFilter($filter) {
		$this->filters = $filter;
	}
	
	protected function _setDataset($dataset) {
		$this->dataset    = $dataset;
		$this->datasource = new LogStore($dataset);
	}
	
}


?>