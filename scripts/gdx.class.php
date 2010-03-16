<?php

/**
 * Class gdx
 * 
 * GDX (Google Data Exporter)
 * Access GA API and write to database
 * Controller for gapi and mdb2 classes
 *	Adam Marshall 2009-10
 */
class gdx {
	
	private $params = array();
	private $meta = array();
	private $data = array();
	private $gapi;
	private $db_config;
	
	/**
	* constructor
	*/	
	public function __construct($params) {
			$this->params = $params;
			$this->gapi = new gapi(ga_user, ga_pass);
			$this->db_config = db_type . "://" . db_user . ":" .db_pass . "@" . db_server . "/" . db_name;
	}
	
	/**
	* core reporting method
	*/	
	public function report() {			
			$data = $this->getData();
			$this->prepare($data->getResults());
			echo $this->output();
	}	
	
	/**
	* request for data
	*
	* @param $ga Object
	* @return Object 
	*/	
	private function getData() {	
			$this->gapi->requestReportData(	
					$this->params['profile'][1], 
					$this->params['dimensions'], 
					$this->params['metrics'],
					$this->params['sort'], 
					$this->params['filters'], 
					$this->params['dates'][0], 
					$this->params['dates'][1], 
					1, 
					$this->params['max'], 
					$this->params['segment']		
			);
			return $this->gapi;
	}
	
	/**
	* prepare - seperate metadata and values into seperate arrays
	*
	* @param $table Array
	*/	
	private function prepare($table) {
			$x = 0;			
			foreach ($table as $entry) {
			
				$this->meta[0][0] = 'startdate';
				$this->meta[1][0] = 'enddate';
				$this->meta[0][1] = $this->meta[1][1] = 'date';
				$this->data[$x][0] = $this->params['dates'][0];
				$this->data[$x][1] = $this->params['dates'][1];
				$y = 2;
				
				foreach( $entry->getDimensions() as $key => $value) {
					if ($x==0) {
						$this->meta[$y][$x] = $key;
						$this->meta[$y][$x+1] = $this->data_type($value);
					}	
					$this->data[$x][$y] = $value;
					$y++;
				}				
				
				foreach( $entry->getMetrics() as $key => $value) {
					if ($x==0) {
						$this->meta[$y][$x] = $key;
						$this->meta[$y][$x+1] = $this->data_type($value);
					}	
					$this->data[$x][$y] = $value;
					$y++;
				}	
						
				$x++;			
			}
	}
	
	/**
	* output - converts values to appropiate output format
	*
	* @return String
	*/	
	private function output() {
		
		$meta = $this->meta;
		$data = $this->data;
		$format = $this->params['format'];
		
		switch ($format) {
			
			case "table":
				$out = "<h2>" . $this->params['profile'][0] . "</h2>";
				$out .= "<h3>" . $this->params['dates'][0] . " &#45; " . $this->params['dates'][1] ."</h3>";
				$out .= "<table><tr>";
				foreach ($meta as $m) {
					$out .= "<th>" . $m[0] . "</th>";
				} $out .= "</tr>";
				foreach ($data as $row) {
					$out .= "<tr>";
					foreach ($row as $d) {
					$out .= "<td>" . htmlspecialchars($d) . "</td>";
					} $out .= "</tr>";
				} $out .= "</table>";	
			break;
			
			case "csv":	
				$out = $this->params['profile'][0] . ",\n";
				$out .= $this->params['dates'][0] . "," . $this->params['dates'][1] .",\n";
				foreach ($meta as $m) {
					$headerString .= $m[0].",";
				} $out .= substr($headerString, 0, -1) . "\n";
				foreach ($data as $row) {
					$rowString = "";
					foreach ($row as $d) {
						$rowString .= $d.",";
					} $out .= substr($rowString, 0, -1) . "\n";
				}
			break;	
			
			case "write":			
				$out = "<h3>Writing Query To Database &#58;</h3>";
				$q = 'INSERT INTO '  . $this->params['dbTable'];
				foreach ($meta as $row) {
					$m[] = $row[0];
				}	
				$q .= ' (' . implode(",", $m) . ') VALUES';
				$q .= ' (' . substr(str_repeat(", ?",count($meta)), 2). ')';
				$out .= $q;
				if ($this->db_count() < 0)
					$this->db_create();
				$this->db_write($q, $data);	
			break;	
			
			default:
				$out = "<b>Error:</b> Format not recognised. Try 'table', or 'csv'.";
			break;	
			
		//end switch
		}
		
		return $out;	
	}	
	
	/**
	* superior database writing
	*
	* @param $queryString String
	* @param $allData Array
	*/	
	private function db_write($queryString, $allData) {			
			require_once 'MDB2.php';
			$mdb2 =& MDB2::factory($this->db_config);
			
			if (PEAR::isError($mdb2)) 
				$this->db_error($mdb2);
			
			$mdb2->loadModule('Extended', null, false);
			$sth = $mdb2->prepare($queryString);
			$statement = $mdb2->extended->executeMultiple($sth, $allData);

			if (PEAR::isError($result)) {
				$this->db_error($result);
				exit();
			}
	}	
	
	/**
	* table creation
	*
	*/	
	private function db_create() {			
			require_once 'MDB2.php';
			$mdb2 =& MDB2::factory($this->db_config);
			
			if (PEAR::isError($mdb2)) 
				$this->db_error($mdb2);
			
			$mdb2->loadModule('Manager');
			
			foreach ($this->meta as $meta) {
				$defs[$meta[0]] = array( 'type' => $meta[1] );
			}	
			
			$result = $mdb2->createTable($this->params['dbTable'], $defs);

			if (PEAR::isError($result)) {
				$this->db_error($result);
				exit();
			}
	}	
	
	/**
	* database - count rows in table
	*
	* @return Integer (-1 on failure)
	*/	
	private function db_count() {			
			require_once 'MDB2.php';
			$mdb2 =& MDB2::factory($this->db_config);
			
			if (PEAR::isError($mdb2)) 
				$this->db_error($mdb2);
			
			$q = "SELECT * FROM " . $this->params['dbTable'];
			$result = $mdb2->query($q);
			if (PEAR::isError($result)) {
				return -1;
			} else {
				$output = $result->numRows();
				$result->free();
				return $output;
			}	
	}	
		
	/**
	* database error messaging
	*
	* @param $error Object
	*/	
	private function db_error($error) {	
			$message = $error->getMessage();
			$user = $error->getUserinfo();
			echo '<div class="error"><h4>Error!</h4>';
			echo "<p>" . $message . "</p>";
			echo "<h4>Detail</h4>";
			echo "<p>" . $user . "</p></div>";
	}
	
	/**
	* data typing
	*
	* @param $value ( String, Date, Integer )
	*/	
	public function data_type($value) {	
		if (is_int($value))
			$type = 'integer';
		elseif (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value))
			$type = 'date';
		elseif (is_string($value))
			$type = 'text';
		else
			$type = false;
		return $type;
	}
	
// end class	
}	


?>