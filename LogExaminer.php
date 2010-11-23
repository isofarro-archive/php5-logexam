<?php

class LogExaminer {
	var $dataset;
	var $datasource;
	var $filters;
	
	const SESSION_EXPIRY = 1800; // 30 minutes
	
	public function __construct($dataset) {
		$this->_setDataset($dataset);
		$this->_createEvents();
	}
	
	public function import($filenames, $filter=NULL) {
		//print_r($filenames); 
		foreach($filenames as $filename) {
			$filedata = $this->importFile($filename, $filter);
			$this->addFile($filedata);
		}
		return;
	}
		
	public function importFile($file, $filter=NULL) {
		$filedata = (object)array(
			'filepath' => '',
			'domain'   => '',
			'minDate'  => 0,
			'maxDate'  => 0,
			'entries'  => 0
		);
		
		$filename = 'STDIN';
		if ($filter) {
			$this->setFilter($filter);
		}
		
		if (is_string($file) && is_file($file)) {
			$filename = $file;
			$filedata->filepath = realpath($filename);
			
			if (preg_match('/\.gz$/', $filename)) {
				$handle = new GzipLogFileHandle();
			}
			else {
				$handle = new LogFileHandle();
			}

			$handle->open($filename, 'r');
			$file = $handle->getHandle();
		}
		else {
			$filedata->filepath = $filename . '-' . time();
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
				else {
					if ($filedata->minDate > $entry->date || !$filedata->minDate) {
						$filedata->minDate = $entry->date;
					}
					if ($filedata->maxDate < $entry->date || !$filedata->maxDate) {
						$filedata->maxDate = $entry->date;
					}

					// TODO: Replace this with a modular filter
					if($this->isAcceptable($entry) && $this->add($entry)) {
						$entries++;
					}
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
		
		$filedata->entries = $lineno;
		return $filedata;
	}
	
	public function postProcessing() {
		echo "Post Processing:\n";
		// Collate sessions
		$entries = $this->getEntries();
		//$entries = array_slice($entries, 0, 5);
		
		$count = 0;
		$lines = 0;
		foreach($entries as $entry) {
			if (!$entry->session_id) {
				$count++; $lines++;
				//echo "Entry: "; print_r($entry);
				$session = $this->getSession($entry);
				$entry->session_id = $session->id;
				//echo "Session id: $session->id\n";
				$this->updateEntrySession($entry);
				
				if ($count>1000) {
					echo '#';
					$count=0;
				}
			}			
		}
		echo "\n$lines rows processed.\n";
	}
	
	public function isAcceptable($entry) {
		// TODO: Convert into an event listener based approach
		if (!empty($this->filter)) {
			$isAcceptable = $this->filter->filter($entry);
			if ($isAcceptable===true || $isAcceptable===false) {
				return $isAcceptable;
			}
			return false;
		}
		else {
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
	}
	
	public function addFile($filedata) {
		return $this->datasource->addFile($filedata);
	}
	
	public function add($entry) {
		return $this->datasource->add($entry);
	}
	
	public function getEntries() {
		return $this->datasource->getAllEntries();
	}
	
	public function updateEntrySession($entry) {
		return $this->datasource->updateEntrySession($entry);
	}
 	
	public function getSession($entry) {
		$session = $this->datasource->getSessionByEntry($entry);

		$entryTs = strtotime($entry->date);
		$lastTs  = strtotime($session->end_time);
		$diff    = $entryTs - $lastTs;
		if ($diff > self::SESSION_EXPIRY) {
			//echo "\t[$entry->date|$session->end_time|$diff]\n";
			$session = $this->datasource->createNewSession($entry);
		}
		
		return $session;
	}
	
	public function parse($line) {
		$components = (object)NULL;
		if (preg_match("/^(\d+\.\d+\.\d+\.\d+) (\S+) (\S+) \[(\d{2}\/\w{3}\/\d{4}:\d{2}:\d{2}:\d{2} [^\]]+)\] \"(\w+) (.+) (HTTP\/1\.\d+)\" (\d+) (\d+|-) (\d+ \S )?\"([^\"]*)\" \"([^\"]*)\"/", $line, $matches)) {
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
				echo "INFO: Filter defined!\n";
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
