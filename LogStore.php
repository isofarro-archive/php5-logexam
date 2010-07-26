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


	public function getAllEntries() {
		return $this->_getAllRows('entry');
	}
	
	public function add($entry) {
		$this->_initDbConnection();

		
		$ipAddressId = $this->getIpAddressId($entry->ipAddress);
		//echo "DEBUG: IP Address Id: $ipAddressId\n";
		$urlId 			 = $this->getUrlId($entry->url);
		//echo "DEBUG: URL id: $urlId\n";
		$userAgentId = $this->getUserAgentId($entry->userAgent);
		//echo "DEBUG: URL id: $urlId\n";

		$sessionId   = 0;

		$stm = $this->_prepareStatement('entry', 'insert');
		$stm->execute(array(
			':ip_id'	    		=> $ipAddressId,
			':date'						=> date('Y-m-d H:i:s', $entry->date),
			':method'					=> $entry->method,
			':url_id' 				=> $urlId,
			':http'			 			=> $entry->http,
			':status' 				=> $entry->status,
			':length' 				=> $entry->length,
			':referrer'				=> $entry->referrer,
			':userAgent_id' 	=> $userAgentId,
			':session_id'			=> $sessionId
		));

		return !$this->_isPdoError($stm) && ($stm->rowCount());
	}
	
	public function updateEntrySession($entry) {
		$stm = $this->_prepareStatement('entry', 'updateSessionId');
		$stm->execute(array(
			':id'         => $entry->id,
			':session_id' => $entry->session_id
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

	public function getUrlId($url) {
		$params = array(':url' => $url);
		$row = $this->_getOneRow('urls', 'getByUrl', $params);
		
		if ($row) {
			return $row->id;
		}
		else {
			$stm = $this->_prepareStatement('urls', 'insert');
			$stm->execute($params);
			return $this->_db->lastInsertId();
		}
	}


	public function getUserAgentId($userAgent) {
		$params = array(':user_agent' => $userAgent);
		$row = $this->_getOneRow('user_agent', 'getByUserAgent', $params);
		
		if ($row) {
			return $row->id;
		}
		else {
			$stm = $this->_prepareStatement('user_agent', 'insert');
			$stm->execute($params);
			return $this->_db->lastInsertId();
		}
	}


	public function getSessionByEntry($entry) {
		$entry_time = date('Y-m-d H:i:s', $entry->date);
		$params = array(
			':ip_id'	=> $entry->ip_id,
			':date'		=> $entry_time
		);
		$row = $this->_getOneRow('session', 'getByEntry', $params);
		
		if ($row) {
			return $row;
		}
		else {
			# Create a new Session
			return $this->createNewSession($entry);
		}
	}
	
	public function createNewSession($entry) {
		$stm = $this->_prepareStatement('session', 'insert');
		$stm->execute(array(
			'ip_id'       => $entry->ip_id,
			':start_time' => $entry->date,
			':end_time'		=> $entry->date
		));
		$session_id = $this->_db->lastInsertId();
		return $this->getSessionById($session_id);
	}
	
	public function getSessionById($id) {
		$params = array(':id' => $id);
		$row = $this->_getOneRow('session', 'getById', $params);

		if ($row) {
			return $row;
		}
		return NULL;
	}
	
	##
	## Private methods - orm helper methods
	##
	
	protected function _getOneRow($tableKey, $queryKey, $params, $hydrate=false) {
		// TODO: Check in cache first
		//echo "DEBUG: $tableKey,$queryKey,"; print_r($params);
		$cachedRow = $this->_getCachedRow($tableKey, $params);
		if (!empty($cachedRow)) {
			return $cachedRow;
		}
		
		$stm = $this->_prepareStatement($tableKey, $queryKey);
		$stm->execute($params);

		if (!$this->_checkPdoError($stm) && ($row = $stm->fetchObject())) {
			$this->_cacheRow($tableKey, $row);
			return $row;
		}
		return NULL;
	}
	
	protected function _getAllRows($tableKey, $queryKey='getAll', $params=array(), $hydrate=false) {
		
		$stm = $this->_prepareStatement($tableKey, $queryKey);
		$stm->execute($params);
		
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
	protected function _cacheRow($tableKey, $row) {
		$keys = $this->_schema[$tableKey]['cache_by'];
		if (!empty($keys)) {
			foreach($keys as $key) {
				$this->_rowCache[$tableKey][$key][$row->{$key}] = $row;
			}
		}
	}
	
	protected function _getCachedRow($tableKey, $rowKey) {
		list($key, $value) = each($rowKey);
		$key = substr($key, 1);
		$cacheKeys = $this->_schema[$tableKey]['cache_by'];
		if (
				!empty($cacheKeys) && in_array($key, $cacheKeys)
				&& !empty($this->_rowCache[$tableKey][$key][$value])
		) {
			//echo '@';
			return $this->_rowCache[$tableKey][$key][$value];
		}
		return NULL;
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
	id							INTEGER PRIMARY KEY,
	ip_id						INTEGER NOT NULL,
	date						DATETIME NOT NULL,
	method					VARCHAR(8) NOT NULL,
	url_id					INTEGER NOT NULL,
	http						VARCHAR(8),
	status					INTEGER,
	length					INTEGER,
	referrer				VARCHAR(255),
	userAgent_id		INTEGER NOT NULL,
	session_id			INTEGER,
	
	UNIQUE (ip_id, date, url_id),
	
	FOREIGN KEY (ip_id) REFERENCES `ip_address` (id)
		ON DELETE CASCADE
	FOREIGN KEY (url_id) REFERENCES `urls` (id)
		ON DELETE CASCADE
	FOREIGN KEY (userAgent_id) REFERENCES `user_agent` (id)
		ON DELETE CASCADE
);
SQL;

		$schema['entry']['insert'] = <<<SQL
INSERT OR IGNORE INTO `log_entry`
(id, ip_id, date, method, url_id, http, status, length, referrer, userAgent_id, session_id)
VALUES
(NULL, :ip_id, :date, :method, :url_id, :http, :status, :length, :referrer, :userAgent_id, :session_id)
SQL;

		$schema['entry']['updateSessionId'] = <<<SQL
UPDATE `log_entry`
SET session_id = :session_id
WHERE id = :id
SQL;

		$schema['entry']['getAll'] = <<<SQL
SELECT
id, ip_id, date, method, url_id, http, status, length, referrer, userAgent_id, session_id
FROM `log_entry`
ORDER BY date ASC;
SQL;

		/******************************************************************
		*
		* IP Address table
		*
		******************************************************************/
		$schema['ip_address']['cache_by'] = array('address', 'id');
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

	/******************************************************************
	*
	* IP Address table
	*
	******************************************************************/
		$schema['urls']['cache_by'] = array('id', 'url');
		$schema['urls']['create']   = <<<SQL
CREATE TABLE IF NOT EXISTS `urls` (
	id			INTEGER PRIMARY KEY,
	url			VARCHAR(255) NOT NULL
);
CREATE INDEX IF NOT EXISTS `urls_index1`
	ON `urls` (url);
SQL;


		$schema['urls']['insert'] = <<<SQL
INSERT OR IGNORE INTO `urls`
(id, url)
VALUES
(NULL, :url)
SQL;

		$schema['urls']['getByUrl'] = <<<SQL
SELECT id, url
FROM `urls`
WHERE url = :url ;
SQL;


	/******************************************************************
	*
	* User Agent table
	*
	******************************************************************/
		$schema['user_agent']['cache_by'] = array();
		$schema['user_agent']['create']   = <<<SQL
CREATE TABLE IF NOT EXISTS `user_agent` (
	id					INTEGER PRIMARY KEY,
	user_agent 	TEXT NOT NULL
);
SQL;


		$schema['user_agent']['insert'] = <<<SQL
INSERT OR IGNORE INTO `user_agent`
(id, user_agent)
VALUES
(NULL, :user_agent)
SQL;

		$schema['user_agent']['getByUserAgent'] = <<<SQL
SELECT id, user_agent
FROM `user_agent`
WHERE user_agent = :user_agent ;
SQL;


	/******************************************************************
	*
	* Session table
	*
	******************************************************************/
		$schema['session']['cache_by'] = array();
		$schema['session']['create']   = <<<SQL
CREATE TABLE IF NOT EXISTS `session` (
	id					INTEGER PRIMARY KEY,
	ip_id				INTEGER NOT NULL,
	start_time	DATETIME NOT NULL,
	end_time		DATETIME NOT NULL,

	FOREIGN KEY (ip_id) REFERENCES `ip_address` (id)
		ON DELETE CASCADE
);
SQL;


		$schema['session']['insert'] = <<<SQL
INSERT OR IGNORE INTO `session`
(id, ip_id, start_time, end_time)
VALUES
(NULL, :ip_id, :start_time, :end_time)
SQL;

		$schema['session']['update'] = <<<SQL
UPDATE `session`
start_time 	= :start_time
end_time 		= :end_time
WHERE
id = :id
SQL;

		$schema['session']['getByEntry'] = <<<SQL
SELECT id, ip_id, start_time, end_time
FROM `session`
WHERE 
		ip_id = :ip_id
AND start_time > :date
ORDER BY end_time DESC
LIMIT 0, 1;
SQL;

		$schema['session']['getById'] = <<<SQL
SELECT id, ip_id, start_time, end_time
FROM `session`
WHERE id = :id;
SQL;


		return $schema;
	}

}


?>