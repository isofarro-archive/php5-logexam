<?php

class LogExaminer {
	var $dataset;
	var $datasource;
	var $filters;
	
	public function __construct($dataset) {
		$this->_setDataset($dataset);
	}
	
	public function import($filenames, $filter=NULL) {
		//print_r($filenames); 
		foreach($filenames as $filename) {
			$this->importFile($filename, $filter);
		}
		return;
	}
		
	public function importFile($file, $filter=NULL) {
		$filename = 'STDIN';
		if ($filter) {
			$this->setFilter($filter);
		}
		
		if (is_string($file) && is_file($file)) {
			$filename = $file;
			// TODO: Call a LogFileHandler Factory method to get a resource object
			$file = fopen($filename, 'r');
		}
		
		if ($file && is_resource($file)) {
			echo "Importing {$filename}\n";
			
			$lineno  = 0;
			$entries = 0;
			$count   = 0;
			
			while ($line = fgets($file, 4096)) {
				$lineno++;
				$count++;
				//echo $line;
				
				$entry = $this->parse($line);
				if (empty($entry->url)) {
					printf("\nERROR line %08d: %s", $lineno, $line);
				}
				elseif($this->is_acceptable($entry)) {
					$this->add($entry);
					$entries++;
				}
				//print_r($entry);
				
				//if ($count>2) {
				//	break;
				//}
				
				if ($count>1000) {
					echo '.'; $count=0;
				}
			}
		}
		else {
			echo "Not a valid file handle";
		}
		
		echo "\nAdded $entries entries from $lineno lines\n";

		if ($filename && is_resource($file)) {
			fclose($file);
		}
	}
	
	public function is_acceptable($entry) {
		// TODO: Convert into an event listener based approach
		if (preg_match('/\.(\w+)$/', $entry->url, $matches)) {
			$extension = strtolower($matches[1]);
			switch($extension) {
				case 'css':
				case 'js':
				case 'gif':
				case 'jpg':
				case 'jpeg':
				case 'png':
				case 'ico':
				case 'bmp':
				case 'swf':
					return false; break;
				case 'rdf':
				case 'necho':
				case 'atom':
				case 'rss':
					return false; break;
			}
		}
		return true;
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
			//echo "\n{$line}\n";
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
