<?php
/**
 * includes/database.php
 *
 * @package default
 */


require_once LIB_PATH_INC.DS."config.php";

class MySqli_DB {

	private $con;
	public $query_id;


	/**
	 *
	 */
	function __construct() {
		$this->db_connect();
	}


	/*--------------------------------------------------------------*/
	/* Function for Open database connection
	/*--------------------------------------------------------------*/

	/**
	 *
	 */
	public function db_connect() {
		try {
			$this->con = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);
		} catch (\mysqli_sql_exception $e) {
			$this->con = false;
		}
		if (!$this->con) {
			error_log("Database connection failed: " . mysqli_connect_error());
			die(" Database connection failed. Please try again later.");
		} else {
			$select_db = $this->con->select_db(DB_NAME);
			if (!$select_db) {
				error_log("Failed to select database " . DB_NAME . ": " . $this->con->error);
				die("Failed to select database. Please try again later.");
			}
		}
	}


	/*--------------------------------------------------------------*/
	/* Function for Close database connection
	/*--------------------------------------------------------------*/

	/**
	 *
	 */
	public function db_disconnect() {
		if (isset($this->con)) {
			mysqli_close($this->con);
			unset($this->con);
		}
	}


	/*--------------------------------------------------------------*/
	/* Function for mysqli query
	/*--------------------------------------------------------------*/

	/**
	 *
	 * @param unknown $sql
	 * @return unknown
	 */
	public function query($sql) {

		if (trim($sql != "")) {
			$this->query_id = $this->con->query($sql);
		}
		if (!$this->query_id) {
			error_log("SQL Error: " . $this->con->error . " | Query: " . $sql);
			die("A database error occurred. Please try again later.");
		}

		return $this->query_id;

	}


	/*--------------------------------------------------------------*/
	/* Prepared statement — returns mysqli_stmt or false on failure
	/*--------------------------------------------------------------*/

	/**
	 * Execute a prepared statement query (INSERT/UPDATE/DELETE).
	 *
	 * @param string $sql    SQL with ? placeholders
	 * @param string $types  Bind types (e.g. "s" for string, "i" for int, "d" for double)
	 * @param mixed  ...$params Values to bind
	 * @return mysqli_stmt
	 */
	public function prepare_query($sql, $types, ...$params) {
		$stmt = $this->con->prepare($sql);
		if (!$stmt) {
			error_log("Prepare failed: " . $this->con->error . " | SQL: " . $sql);
			die("A database error occurred. Please try again later.");
		}
		$stmt->bind_param($types, ...$params);
		if (!$stmt->execute()) {
			error_log("Execute failed: " . $stmt->error . " | SQL: " . $sql);
			die("A database error occurred. Please try again later.");
		}
		return $stmt;
	}

	/**
	 * Execute a prepared SELECT and return all rows as an associative array.
	 *
	 * @param string $sql    SQL with ? placeholders
	 * @param string $types  Bind types
	 * @param mixed  ...$params Values to bind
	 * @return array
	 */
	public function prepare_select($sql, $types, ...$params) {
		$stmt = $this->prepare_query($sql, $types, ...$params);
		$result = $stmt->get_result();
		$rows = [];
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		$stmt->close();
		return $rows;
	}

	/**
	 * Execute a prepared SELECT and return a single row, or null.
	 *
	 * @param string $sql
	 * @param string $types
	 * @param mixed  ...$params
	 * @return array|null
	 */
	public function prepare_select_one($sql, $types, ...$params) {
		$rows = $this->prepare_select($sql, $types, ...$params);
		return $rows ? $rows[0] : null;
	}


	/*--------------------------------------------------------------*/
	/* Function for Query Helper
	/*--------------------------------------------------------------*/

	/**
	 *
	 * @param unknown $statement
	 * @return unknown
	 */
	public function fetch_array($statement) {
		return mysqli_fetch_array($statement);
	}


	/**
	 *
	 * @param unknown $statement
	 * @return unknown
	 */
	public function fetch_object($statement) {
		return mysqli_fetch_object($statement);
	}


	/**
	 *
	 * @param unknown $statement
	 * @return unknown
	 */
	public function fetch_assoc($statement) {
		return mysqli_fetch_assoc($statement);
	}


	/**
	 *
	 * @param unknown $statement
	 * @return unknown
	 */
	public function num_rows($statement) {
		return mysqli_num_rows($statement);
	}


	/**
	 *
	 * @return unknown
	 */
	public function insert_id() {
		return mysqli_insert_id($this->con);
	}


	/**
	 *
	 * @return unknown
	 */
	public function affected_rows() {
		return mysqli_affected_rows($this->con);
	}


	/*--------------------------------------------------------------*/
	/* Function for Remove escapes special
	 /* characters in a string for use in an SQL statement
	 /*--------------------------------------------------------------*/

	/**
	 *
	 * @param unknown $str
	 * @return unknown
	 */
	public function escape($str) {
		return $this->con->real_escape_string($str);
	}


	/**
	 * Raw mysqli connection. Lets callers run queries that need to handle
	 * their own errors instead of going through query()/prepare_query(),
	 * which die() on failure. Used by Settings::load() so a missing
	 * `settings` table during a migration window falls back to defaults
	 * instead of taking the whole site down.
	 *
	 * @return mysqli
	 */
	public function connection() {
		return $this->con;
	}


	/*--------------------------------------------------------------*/
	/* Function for while loop
	/*--------------------------------------------------------------*/

	/**
	 *
	 * @param unknown $loop
	 * @return unknown
	 */
	public function while_loop($loop) {
		global $db;
		$results = array();
		while ($result = $this->fetch_array($loop)) {
			$results[] = $result;
		}
		return $results;
	}


}


$db = new MySqli_DB();
