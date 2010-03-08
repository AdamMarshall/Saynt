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
				$out = "<h2>".$this->params['profile'][0]."</h2>";
				$out .= "<table><tr>";
				foreach ($meta as $m) {
					$out .= "<th>".$m[0]."</th>";
				} $out .= "</tr>";
				foreach ($data as $row) {
					$out .= "<tr>";
					foreach ($row as $d) {
					$out .= "<td>".$d."</td>";
					} $out .= "</tr>";
				} $out .= "</table>";	
			break;
			
			case "csv":	
				$out = $this->params['profile'][0] . ",\n";
				$out .= $this->params['dates'][0] . "," . $this->params['dates'][1] .",\n";
				foreach ($meta as $m) {
					$out .= $m[0].",";
				} $out .= "\n";
				foreach ($data as $row) {
					foreach ($row as $d) {
					$out .= $d.",";
					} $out .= "\n";
				}
			break;	
			
			case "hard_write":			
				$out = "<h3>Database Query</h3>";
				$k = 0;
				$q = 'INSERT INTO ' . $this->params['dbTable'] . ' VALUES ';		
				foreach ($data as $row) {
					foreach ($row as $cell) {
						$cells[$k][] = "'" . $cell . "'";
					}
					$vals[] = "(" . implode(", ", $cells[$k]) . ")";
					$k++;
				}
				$q .= implode(", ", $vals);
				$out .= $q;
				$this->db_hard_write($q);	
			break;	
			
			case "write":			
				$out = "<h3>Advanced Database Query</h3>";
				$q = 'INSERT INTO '  . $this->params['dbTable'];
				foreach ($meta as $row) {
					$m[] = $row[0];
				}	
				$q .= ' (' . implode(",", $m) . ') VALUES';
				$q .= ' ('. substr(str_repeat(", ?",count($meta)), 2). ')';
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
	* database writing
	*
	* @param $queryString String
	*/	
	private function db_hard_write($queryString) {			
			require_once 'MDB2.php';
			$mdb2 =& MDB2::factory($this->db_config);
			
			if (PEAR::isError($mdb2)) 
				$this->db_error($mdb2);
			
			$result = $mdb2->exec($queryString);

			if (PEAR::isError($result)) {
				$this->db_error($result);
				exit();
			}
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
			echo "<h4>Error!</h4>";
			echo "<p>" . $message . "</p>";
			echo "<h4>Detail</h4>";
			echo "<p>" . $user . "</p>";
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
		return $type;
	}
	
// end class	
}	


/**
 * Class gdx_helper
 * 
 * GDX Helper
 * User friendly access to a gdx object
 *	Adam Marshall 2009-10
 */
class gdx_helper {
	
	private $allowedNames = array(
		'profile' , 'format' , 'dbTable', 'dates', 
		'dimensions', 'metrics', 'sort', 'max', 'filters', 'segment'
	);
	
	private $defaultValues = array( 
		'profile' => array("Newbeat", 21673320), 
		'format' =>"table", 'dbTable' => null, 
		'dates' => array("2010-01-01", "2010-01-31"), 
		'dimensions' => null, 'metrics' => "visits", 
		'sort' => null, 'max' => 10, 'filters' => null, 'segment' => null
	);
	
	public $validParams = array();	
	public $data = array();
	
	/**
	* constructor
	*
	*/
	public function __construct($defaults) {
		// override internal defaults if supplied
		foreach ($defaults as $name => $value) {
			$this->defaultValues[$name] = $value;
		}
		// access current rules
		$this->validParams = $this->get_valid();
		// process get requests
		$data = $this->process_get();		
		// fix default sort
		$m = $data['metrics'];
		if ($data['sort'] == null && $m !== null)	
			$data['sort'] = "-" . (is_array($m) ? $m[0] : $m);
		$this->data = $data;			
	}
	
	/**
	* process a GET request to an API page
	* exclude all non-valid query names
	*
	* @return $params Array
	*/
	private function process_get() {		
			foreach ($this->allowedNames as $name) {
				$get = $_GET[$name];
				if ($get) {	
					// prepare and validate multiple values
					if (stristr($get, "|")) {
						foreach (explode("|", $get) as $value) {
							if ($this->isValid($name, $value)) {
								$params[$name][] = $value;
							} else {
								$this->error_message("value", array($name, $value));
							}	
						}
					// validate single values	
					} elseif ($this->isValid($name, $get)) {
						$params[$name] = $get;						
					} else {
						$this->error_message("value", array($name, $get));
					}
				// if not specified, use defaults	
				} else {
					$params[$name] = $this->defaultValues[$name];
				}	
			}
			return $params;
	}
	
	/**
	* test to see if query value is valid
	*
	* @param $name String
	* @param $value String
	* @return Boolean
	*/	
	private function isValid($name, $value) {
		if (in_array("%%INT%%", $this->validParams[$name]) 
			&& preg_match('/[0-9].+/', $value))
			return true;
		if (in_array("%%ANY%%", $this->validParams[$name]))
			return true;
		else	
			return in_array($value, $this->validParams[$name]);
	}
	
	/**
	* open valid parameters file
	*
	* @return $rules Array
	*/	
	private function get_valid() {
		if (($handle = fopen("../config/validParams.csv", "r")) !== FALSE) {		
			while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
				$rules[$data[0]] = array_slice($data, 1);
			}
			fclose($handle);
		}
		
		return $rules;
	}
	
	/**
	* helper error messages
	*
	* @param $type String
	* @param $info Array
	* @return echo
	*/	
	private function error_message($type, $info) {
		echo '<div class="error"><h4>Warning!</h4><p>';
		switch ($type) {
			case "value":
				echo "We didn't recognise the value <b>'". $info[1] ."'</b> assigned to the parameter <b>'". $info[0] . "'</b>.";
			break;
			default:
				echo "An unidentified error occured. Yeah, I don't know either."; 
			Break;	
		}
		echo '</p></div>';
	}
	
	
	
// end class	
}	

?>