<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Mysqli.php 7056 2012-09-25 07:14:03Z EZdesign $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * mysqli wrapper
 *
 * @package Piwik
 * @subpackage Piwik_Tracker
 */
class Piwik_Tracker_Db_Mysqli extends Piwik_Tracker_Db
{
	protected $connection = null;
	protected $host;
	protected $port;
	protected $socket;
	protected $dbname;
	protected $username;
	protected $password;
	protected $charset;

	/**
	 * Builds the DB object
	 *
	 * @param array   $dbInfo
	 * @param string  $driverName
	 */
	public function __construct( $dbInfo, $driverName = 'mysql') 
	{
		if(isset($dbInfo['unix_socket']) && $dbInfo['unix_socket'][0] == '/')
		{
			$this->host = null;
			$this->port = null;
			$this->socket = $dbInfo['unix_socket'];
		}
		else if ($dbInfo['port'][0] == '/')
		{
			$this->host = null;
			$this->port = null;
			$this->socket = $dbInfo['port'];
		}
		else
		{
			$this->host = $dbInfo['host'];
			$this->port = $dbInfo['port'];
			$this->socket = null;
		}
		$this->dbname = $dbInfo['dbname'];
		$this->username = $dbInfo['username'];
		$this->password = $dbInfo['password'];
		$this->charset = isset($dbInfo['charset']) ? $dbInfo['charset'] : null;
	}

	/**
	 * Destructor
	 */
	public function __destruct() 
	{
		$this->connection = null;
	}

	/**
	 * Connects to the DB
	 * 
	 * @throws Exception|Piwik_Tracker_Db_Exception  if there was an error connecting the DB
	 */
	public function connect() 
	{
		if(self::$profiling)
		{
			$timer = $this->initProfiler();
		}
		
		$this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->dbname, $this->port, $this->socket);
		if(!$this->connection || mysqli_connect_errno())
		{
			throw new Piwik_Tracker_Db_Exception("Connect failed: " . mysqli_connect_error());
		}

		if($this->charset && !mysqli_set_charset($this->connection, $this->charset))
		{
			throw new Piwik_Tracker_Db_Exception("Set Charset failed: " . mysqli_error($this->connection));
		}

		$this->password = '';
		
		if(self::$profiling)
		{
			$this->recordQueryProfile('connect', $timer);
		}
	}
	
	/**
	 * Disconnects from the server
	 */
	public function disconnect()
	{
		mysqli_close($this->connection);
		$this->connection = null;
	}
	
	/**
	 * Returns an array containing all the rows of a query result, using optional bound parameters.
	 *
	 * @see query()
	 *
	 * @param string  $query       Query
	 * @param array   $parameters  Parameters to bind
	 * @return array
	 * @throws Exception|Piwik_Tracker_Db_Exception if an exception occured
	 */
	public function fetchAll( $query, $parameters = array() )
	{
		try {
			if(self::$profiling)
			{
				$timer = $this->initProfiler();
			}

			$rows = array();
			$query = $this->prepare( $query, $parameters );
			$rs = mysqli_query($this->connection, $query);
			if(is_bool($rs))
			{
				throw new Piwik_Tracker_Db_Exception('fetchAll() failed: ' . mysqli_error($this->connection) . ' : ' . $query);
			}

			while($row = mysqli_fetch_array($rs, MYSQLI_ASSOC)) 
			{
				$rows[] = $row;
			}
			mysqli_free_result($rs);

			if(self::$profiling)
			{
				$this->recordQueryProfile($query, $timer);
			}
			return $rows;
		} catch (Exception $e) {
			throw new Piwik_Tracker_Db_Exception("Error query: ".$e->getMessage());
		}
	}
	
	/**
	 * Returns the first row of a query result, using optional bound parameters.
	 *
	 * @see query()
	 *
	 * @param string  $query       Query
	 * @param array   $parameters  Parameters to bind
	 * @throws Piwik_Tracker_Db_Exception if an exception occurred
	 */
	public function fetch( $query, $parameters = array() )
	{
		try {
			if(self::$profiling)
			{
				$timer = $this->initProfiler();
			}

			$query = $this->prepare( $query, $parameters );
			$rs = mysqli_query($this->connection, $query);
			if(is_bool($rs))
			{
				throw new Piwik_Tracker_Db_Exception('fetch() failed: ' . mysqli_error($this->connection) . ' : ' . $query);
			}

			$row = mysqli_fetch_array($rs, MYSQLI_ASSOC);
			mysqli_free_result($rs);

			if(self::$profiling)
			{
				$this->recordQueryProfile($query, $timer);
			}
			return $row;
		} catch (Exception $e) {
			throw new Piwik_Tracker_Db_Exception("Error query: ".$e->getMessage());
		}
	}
	
	/**
	 * Executes a query, using optional bound parameters.
	 * 
	 * @param string        $query       Query
	 * @param array|string  $parameters  Parameters to bind array('idsite'=> 1)
	 * 
	 * @return bool|resource  false if failed
	 * @throws Piwik_Tracker_Db_Exception  if an exception occurred
	 */
	public function query($query, $parameters = array()) 
	{
		if(is_null($this->connection))
		{
			return false;
		}

		try {
			if(self::$profiling)
			{
				$timer = $this->initProfiler();
			}
			
			$query = $this->prepare( $query, $parameters );
			$result = mysqli_query($this->connection, $query);
			if(!is_bool($result))
			{
				mysqli_free_result($result);
			}
			
			if(self::$profiling)
			{
				$this->recordQueryProfile($query, $timer);
			}
			return $result;
		} catch (Exception $e) {
			throw new Piwik_Tracker_Db_Exception("Error query: ".$e->getMessage() . "
								In query: $query
								Parameters: ".var_export($parameters, true));
		}
	}

	/**
	 * Returns the last inserted ID in the DB
	 * 
	 * @param  String $sequenceCol Column on which the sequence is created.
	 *         Pertinent for DBMS that use sequences instead of auto_increment.
	 * @return int
	 */
	public function lastInsertId($sequenceCol=null)
	{
		return mysqli_insert_id($this->connection);
	}

	/**
	 * Input is a prepared SQL statement and parameters
	 * Returns the SQL statement
	 *
	 * @param string  $query
	 * @param array   $parameters
	 * @return string
	 */
	private function prepare($query, $parameters) {
		if(!$parameters)
		{
			$parameters = array();
		}
		else if(!is_array($parameters))
		{
			$parameters = array( $parameters );
		}

		$this->paramNb = 0;
		$this->params = &$parameters;
		$query = preg_replace_callback('/\?/', array($this, 'replaceParam'), $query);
		
		return $query;
	}
	
	public function replaceParam($match) {
		$param = &$this->params[$this->paramNb];
		$this->paramNb++;
		
		if ($param === null) {
			return 'NULL';
		} else {
			return "'".addslashes($param)."'";
		}
	}

	/**
	 * Test error number
	 *
	 * @param Exception  $e
	 * @param string     $errno
	 * @return bool
	 */
	public function isErrNo($e, $errno)
	{
		return mysqli_errno($this->_connection) == $errno;
	}

	/**
	 * Return number of affected rows in last query
	 *
	 * @param mixed  $queryResult  Result from query()
	 * @return int
	 */
	public function rowCount($queryResult)
	{
		return mysqli_affected_rows($this->connection);
	}
}
