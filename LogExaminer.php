<?php

class LogExaminer {
	var $dataset;
	var $datasource;
	var $filters;
	
	public function __construct($dataset) {
		$this->_setDataset($dataset);
		$this->_createEvents();
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
			
			if (preg_match('/\.gz$/', $filename)) {
				$handle = new GzipLogFileHandle();
			}
			else {
				$handle = new LogFileHandle();
			}

			$handle->open($filename, 'r');
			$file = $handle->getHandle();
		}
		
		if ($file && is_resource($file)) {
			echo "Importing {$filename}\n";
			
			$lineno  = 0;
			$entries = 0;
			$count   = 0;
			
			while ($line = fgets($file, 8192)) {
				$lineno++;
				$count++;
				//echo $line;
				
				$entry = $this->parse($line);
				//print_r($entry);
				if (empty($entry->url)) {
					printf("\nERROR line %08d: %s", $lineno, $line);
				}
				elseif($this->isAcceptable($entry) && $this->add($entry)) {
					$entries++;
				}
				
				//if ($count>10) { break; }
				
				if ($count>1000) {
					echo '.'; $count=0;
				}
			}
		}
		else {
			echo "Not a valid file handle";
		}
		
		echo "\nAdded $entries entries from $lineno lines\n";

		if ($handle && is_resource($file)) {
			$handle->close();
		}
	}
	
	public function isAcceptable($entry) {
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
		
		if ($this->filter) {
			$isAcceptable = $this->filter->isAcceptable($entry);
			if ($isAcceptable===true || $isAcceptable===false) {
				return $isAcceptable;
			}
			return false;
		}
		return true;
	}
	
	public function add($entry) {
		return $this->datasource->add($entry);
	}
	
	public function parse($line) {
		$components = (object)NULL;
		if (preg_match("/^(\d+\.\d+\.\d+\.\d+) (\S+) (\S+) \[(\d{2}\/\w{3}\/\d{4}:\d{2}:\d{2}:\d{2} [^\]]+)\] \"(\w+) (.+) (HTTP\/1\.\d+)\" (\d+) (\d+|-) \"([^\"]*)\" \"([^\"]*)\"/", $line, $matches)) {
			## Apache combined log format
			//print_r($matches);
			$components->ipAddress = $matches[1];
			$components->date      = strtotime($matches[4]);
			$components->method    = $matches[5];
			$components->url       = $matches[6];
			$components->http      = $matches[7];
			$components->status    = $matches[8];
			$components->length    = $matches[9];
			$components->referrer  = $matches[10];
			$components->userAgent = $matches[11];
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
		
		$datasetFile = dirname(__FILE__) . '/datasets/' . $dataset . '.php';
		if (file_exists($datasetFile)) {
			require_once($datasetFile);
			if ($filter) {
				//echo "INFO: Filter defined!\n";
				$this->filter = $filter;
			}
		}
	}
	
	protected function _createEvents() {
		LogEvents::addEvents(array(
			'log_import:add',
			'log_import:new_file'
		));
	}
	
}


?>
