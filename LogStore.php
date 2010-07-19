<?php

class LogStore {
	var $dataset;
	var $_db;
	var $_schema;

	var $_stmCache = array();
	var $_rowCache = array();

	// Configuration
	var $_config = array(
		'database_dir' => '/tmp/',
		'datasource'   => 'sqlite:/tmp/tmp-dataset.db'
	);

	
	public function __construct($dataset) {
		$this->dataset = $dataset;
		$this->setConfig(array(
			'datasource' => "sqlite:{$this->_config['database_dir']}logdataset-{$dataset}.db"
		));
	}

	public function setConfig($config) {
		if (is_array($config)) {
			$this->_config = array_merge($this->_config, $config);
		}
	}


	
	public function add($entry) {
		$this->_initDbConnection();

		
		$ipAddressId = $this->getIpAddressId($entry->ipAddress);
		//echo "DEBUG: IP Address Id: $ipAddressId\n";


		$stm = $this->_prepareStatement('entry', 'insert');
		$stm->execute(array(
			':ip_id'	    => $ipAddressId,
			':date'				=> $entry->date,
			':timezone'		=> $entry->timezone,
			':method'			=> $entry->method,
			':url'				=> $entry->url,
			':http' 			=> $entry->http,
			':status' 		=> $entry->status,
			':length' 		=> $entry->length,
			':referrer'		=> $entry->referrer,
			':user_agent' => $entry->userAgent
		));

		return !$this->_isPdoError($stm) && ($stm->rowCount());
	}
	
	public function getIpAddressId($ipAddress) {
		$params = array(':address' => $ipAddress);
		$ip = $this->_getOneRow('ip_address', 'getByAddress', $params);
		
		if ($ip) {
			return $ip->id;
		}
		else {
			$stm = $this->_prepareStatement('ip_address', 'insert');
			$stm->execute($params);
			return $this->_db->lastInsertId();
		}
	}
	
	##
	## Private methods - orm helper methods
	##
	
	protected function _getOneRow($tableKey, $queryKey, $params, $hydrate=false) {
		// TODO: Check in cache first
		//echo "DEBUG: $tableKey,$queryKey,"; print_r($params);
		
		$stm = $this->_prepareStatement($tableKey, $queryKey);
		$stm->execute($params);

		if (!$this->_checkPdoError($stm) && ($row = $stm->fetchObject())) {
			// TODO: Cache row
			return $row;
		}
		return NULL;
	}
	
	protected function _getAllRows($stm, $hydrate=false) {
		// TODO: use query key instead of passing the statement
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}
		
		$rows = array();
		while ($row = $stm->fetchObject()) {
			$rows[] = $row;
		}
		return $rows;
	}

	/**
		_prepareStatement: lazy cache of prepared statements, so the prepare
			statement is only done once in the current instantiation, and 
			reused wherever possible.
		@param table name (from the schema)
		@param query name (from the schema)
		@returns a prepared PDO Statement
	**/
	protected function _prepareStatement($table, $queryKey) {
		$cacheKey = "{$table}:{$queryKey}";
		//echo "DEBUG: SQL: ", $this->_schema[$table][$queryKey] . "\n";
		if (empty($this->_stmCache[$cacheKey])) {
			$stm = $this->_db->prepare($this->_schema[$table][$queryKey]);	
			$this->_stmCache[$cacheKey] = $stm;
		} else {
			// Cache hit!
			//echo '±';
		}
		return $this->_stmCache[$cacheKey];
	}

	/******************************************************************
	*
	* Row Caches
	*
	******************************************************************/
	protected function _cacheRow($tableKey, $queryKey, $rowKey, $row) {
		
	}
	
	protected function _getCachedRow($tableKey, $queryKey, $rowKey) {
		
	}
	
	protected function _delCacheRow($tableKey, $queryKey=false, $rowKey=false) {
		
	}


	/**
		_initDbConnection: lazy initialisation of connection and database. 
			initialises connection in $this->conn, or exits. Checks that 
			all the database tables exists, creating them along the way.
	**/
	protected function _initDbConnection() {
		if (!empty($this->_db)) {
			return;
		}
		
		// Create a new PDO connection
		if (empty($this->_config['datasource'])) {
			throw new Exception("datasource not configured");
		}
		
		// Initialise a new database connection and associated schema.
		$db = new PDO($this->_config['datasource']);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->_schema = $this->_initDbSchema();
		
		// Check the database tables exist - create if necessary
		$this->_initDbTables($db);
		
		// Database successfully initialised, make it ready to use
		$this->_db = $db;
	}

	/**
		_initDbTables: lazy creates missing db tables based on those
			specified in the schame
		
		@param $db - connection to a db
	**/
	protected function _initDbTables($db) {
		$buffer = array();
		foreach($this->_schema as $sql) {
			$buffer[] = $sql['create'];
		}
		$db->exec(implode("\n", $buffer));

		// Check for fatal failures?
		if ($db->errorCode() !== '00000') {
			$info = $db->errorInfo();
			die('GraphDbPdoStorage->_initDbTables: PDO Error: ' . 
				implode(', ', $info) . "\n");
		}
	}

	/**
		_isPdoError: quiet check for a PDO error. Returns true/false whether an 
			error occurred or not.
		@param a PDO statement
		@returns boolean whether an error occurred or not
	**/
	protected function _isPdoError($stm) {
		// Check for errors
		if ($stm->errorCode() !== '00000') {
			return true;
		}
		return false;
	}
	
	/**
		_checkPdoError: checks the PDO statement for an error, and displays
			a message before returning true/false whether an error occurred.
		@param a PDO statement
		@returns boolean whether an error occurred or not
	**/
	protected function _checkPdoError($stm) {
		// Check for fatal failures?
		if ($stm->errorCode() !== '00000') {
			$info = $stm->errorInfo();
			echo 'PDO Error: ' . implode(', ', $info) . "\n";
			return true;
		}
		return false;
	}
	
	protected function _initDbSchema() {
			$schema = array();

			/******************************************************************
			*
			* Log Entries table
			*
			******************************************************************/

			$schema['entry']['create'] = <<<SQL
CREATE TABLE IF NOT EXISTS `log_entry` (
	ip_id				INTEGER,
	date				DATETIME NOT NULL,
	timezone		VARCHAR(5),
	method			VARCHAR(8) NOT NULL,
	url					VARCHAR(255) NOT NULL,
	http				VARCHAR(8),
	status			INTEGER,
	length			INTEGER,
	referrer		VARCHAR(255),
	user_agent	VARCHAR(255) NOT NULL,
	
	FOREIGN KEY (ip_id) REFERENCES `ip_address` (id)
		ON DELETE CASCADE
);
SQL;

		$schema['entry']['insert'] = <<<SQL
INSERT INTO `log_entry`
(ip_id, date, timezone, method, url, http, status, length, referrer, user_agent)
VALUES
(:ip_id, :date, :timezone, :method, :url, :http, :status, :length, :referrer, :user_agent)
SQL;

		/******************************************************************
		*
		* IP Address table
		*
		******************************************************************/
		$scheme['ip_address']['cache_by'] = array('ip_address');
		$schema['ip_address']['create']   = <<<SQL
CREATE TABLE IF NOT EXISTS `ip_address` (
	id			INTEGER PRIMARY KEY,
	address	VARCHAR(15) NOT NULL
);
CREATE INDEX IF NOT EXISTS `ip_address_index1`
	ON `ip_address` (address);
SQL;


		$schema['ip_address']['insert'] = <<<SQL
INSERT OR IGNORE INTO `ip_address`
(id, address)
VALUES
(NULL, :address)
SQL;

		$schema['ip_address']['getByAddress'] = <<<SQL
SELECT id, address
FROM `ip_address`
WHERE address = :address ;
SQL;


		return $schema;
	}

}


?>