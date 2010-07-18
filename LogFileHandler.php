<?php

interface LogInputHandle {
	function open($name, $mode='r');
	function close(); 
}

class LogFileHandle implements LogInputHandle {
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

class GzipLogFileHandle implements LogInputHandle {
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

?>