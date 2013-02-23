<?php
/*
** Datenbankklasse von Gerrit Paepcke
** Stand: 12.12.2011
** Version 0.0.3
**
*/
class Database 
{ 
	private $db;  
	private $query_count=0;
	private $query_time=0;
	private $prefix;
	private $db_infos=array();
	private $linebreak;
	
	public function __construct($server, $user, $pw, $database, $prefix) {
		$this->db_infos['server'] = $server;
		$this->db_infos['user'] = $user;
		$this->db_infos['pw'] = $pw;
		$this->db_infos['database'] = $database;
		$this->connect($server, $user, $pw, $database);
		$this->prefix = $prefix;
		$this->linebreak = "\n";
		$this->query("SET NAMES 'utf8'");
	}
	/*
	** Connect to Database
	*/
	public function connect($server, $user, $pw, $database) {
		$this->db = new mysqli($server, $user, $pw, $database);
		if (mysqli_connect_errno()) {
			if (DEBUG) echo sprintf("[%d] %s".$this->linebreak, mysqli_connect_errno(), mysqli_connect_error())."\n";
			throw new DatabaseException(sprintf("[%d] %s".$this->linebreak, mysqli_connect_errno(), mysqli_connect_error()));
		}
	}
	
	/*
	** Setzt ein beliebiges SQL Query ab
	*/
	public function query($string, $hide_errors=0) {
		$start = microtime(true);
		if (DEBUG) echo $string.$this->linebreak;
		if (!$this->db->ping()) {
			$this->connect($this->db_infos['server'], $this->db_infos['user'], $this->db_infos['pw'], $this->db_infos['database']);
		}
		
		$query = $this->db->query($string);
		if ($this->db->errno && !$hide_errors) {
			throw new DatabaseException(sprintf("[%d] %s\n", $this->db->errno, $this->db->error)." - QUERY: ".$string);
		}
		
		$end = microtime(true);
		$this->query_time += $end-$start;
		$this->query_count++;
		
		return $query; 
	}
	
	/*
	** Liefert einen Query als Assoziatives Array
	*/
	public function fetch_array($query) {
		return $query->fetch_assoc();
	}
	
	/*
	** Z�hlt alle Zeilen in einem Query
	*/
	public function num_rows($query) {
		return $query->num_rows;
	}
	
	/*
	** Gibt die ID des letzten Inserts zur�ck
	*/
	public function insert_id() {
		return $this->db->insert_id;
	}
	
	/*
	** Gibt die Anzahl der betroffenen Reihen zur�ck
	*/
	public function affected_rows() {
		return $this->db->affected_rows;
	}
	
	/*
	** Einfache SELECT Abfrage
	*/
	public function simple_select($table, $fields="*", $conditions="", $options=array(), $escaping = true) {
		if ($fields != "*" && $escaping) $fields = "`".implode("`,`", explode(",", $fields))."`";
		$query = "SELECT ".$fields." FROM ".$this->getPrefix().$table;
		
		if($conditions != "") {
			$query .= " WHERE ".$conditions;
		}
		
		if(isset($options['group_by'])) {
			$query .= " GROUP BY ".$options['group_by'];
		}
		
		if(isset($options['having'])) {
			$query .= " HAVING ".$options['having'];
		}
		
		if(isset($options['order_by'])) {
			$query .= " ORDER BY ".$options['order_by'];
			if(isset($options['order_dir'])) {
				$query .= " ".my_strtoupper($options['order_dir']);
			}
		}
		
		if(isset($options['limit_start']) && isset($options['limit'])) {
			$query .= " LIMIT ".$options['limit_start'].", ".$options['limit'];
		}
		else if(isset($options['limit'])) {
			$query .= " LIMIT ".$options['limit'];
		}
		return $this->query($query);
	}
	
	/*
	** Komplexe SELECT Abfrage f�r Joins
	*/
	public function select($tables, $fields="*", $conditions="", $options=array(), $escaping=true) {
		if (!is_array($tables)) {
			return $this->simple_select($tables , $fields, $conditions, $options, $escaping);
		}
		
		$join = ((isset($options['join']))?$options['join']:"NATURAL JOIN");
		if ($fields != "*" && $escaping) $fields = "`".implode("`,`", explode(",", $fields))."`";
		$query = "SELECT ".$fields." FROM ".$this->getPrefix().implode(" ".$join." ".$this->getPrefix(), $tables);
		
		if($conditions != "") {
			$query .= " WHERE ".$conditions;
		}
		
		if(isset($options['group_by'])) {
			$query .= " GROUP BY ".$options['group_by'];
		}
		
		if(isset($options['having'])) {
			$query .= " HAVING ".$options['having'];
		}
		
		if(isset($options['order_by'])) {
			$query .= " ORDER BY ".$options['order_by'];
			if(isset($options['order_dir'])) {
				$query .= " ".my_strtoupper($options['order_dir']);
			}
		}
		
		if(isset($options['limit_start']) && isset($options['limit'])) {
			$query .= " LIMIT ".$options['limit_start'].", ".$options['limit'];
		}
		else if(isset($options['limit'])) {
			$query .= " LIMIT ".$options['limit'];
		}
		return $this->query($query);
	}
	
	/*
	** Setzt ein Insert Statement zusammen und schickt es ab
	*/
	public function insert($table, array $line) {
		if(!is_array($line)) {
			return false;
		}
		$line = array_map(array($this, "mysql_esc"), $line);
		$felder = "`".implode("`,`", array_keys($line))."`"; 
		$werte = implode("','", $line);
		
		$query = "INSERT INTO ".$this->getPrefix().$table." (".$felder.")  
					VALUES ('".$werte."');";
		
		$this->query($query);
		return $this->insert_id();
	}
	
	/*
	** Generiert ein Insert Statement mit mehreren value eintr�gen
	*/
	public function insert_multi($table, array $lines) {
		if(!sizeof($lines) > 0) {
			return false;
		}
		
		$felder = "`".implode("`,`", array_keys($lines[0]))."`"; 
		
		foreach ($lines as $row) {
			$row = array_map(array($this, "mysql_esc"), $row);
			$werte[] = "('".implode("','",$row)."')";
		}
		
		$query = "INSERT INTO ".$this->getPrefix().$table." (".$felder.")  
					VALUES ".implode(", ",$werte).";"; 
		
		return $this->query($query);
	}
	
	/*
	** Generiert ein L�sch Statement und schickt es ab
	*/
	public function delete($table, $where="", $limit="") {
		$query = "";
		if(!empty($where)) {
			$query .= " WHERE $where";
		}
		
		if(!empty($limit)) {
			$query .= " LIMIT $limit";
		}
		
		$query = "DELETE FROM ".$this->getPrefix().$table.$query.";";  
		
		return $this->query($query);
	}
	
	/*
	** Generiert ein Update Statement und schickt es ab
	*/
	public function update($table, array $array, $where="", $limit="", $no_quote=false) {
		if(!is_array($array)) {
			return false;
		}
		
		$comma = "";
		$query = "";
		$quote = "'";
		
		if($no_quote == true) {
			$quote = "";
		}
		
		foreach($array as $feld => $wert) {
			$query .= $comma."`".$feld."`= ".$quote.$this->mysql_esc($wert).$quote; 
			$comma = ', ';
		}
		
		if(!empty($where)) {
			$query .= " WHERE $where";
		}
		
		if(!empty($limit)) {
			$query .= " LIMIT $limit";
		}
		
		$query = "UPDATE ".$this->getPrefix().$table." 
					SET ".$query.";";
		
		return $this->query($query);
	}	
	
	/*
	** Liefert den Tabellenprefix zur�ck
	*/
	public function getPrefix() {
		return $this->prefix;
	}
	
	/*
	** Liefert den Query Count zur�ck
	*/
	public function getQueryCount() {
		return $this->query_count;
	}
	
	/*
	** Liefert den Query Time zur�ck
	*/
	public function getQueryTime() {
		return $this->query_time;
	}
	
	
	/*
	** Escaped Strings
	*/
	public function mysql_esc($string) {
		return $this->db->real_escape_string($string);
	}
	
	/*
	** Gibt Objekt Attribute zur�ck
	*/
	public function __get($name) {
		return $this->$name;
	}
}
?>