<?php

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
		'profile' => array(null, null), 
		'format' =>"table", 'dbTable' => null, 
		'dates' => array("2010-01-01", "2010-01-31"), 
		'dimensions' => null, 'metrics' => "visits", 
		'sort' => null, 'max' => 10, 'filters' => null, 'segment' => null
	);
	
	private $locked = array();
	public $validParams = array();	
	public $data = array();
	
	/**
	* constructor
	*
	*/
	public function __construct($defaults = null, $locked = null) {
		// override internal defaults if supplied
		if ($defaults !== null) {
			foreach ($defaults as $name => $value) {
				if ($this->defaultValues[$name])
					$this->defaultValues[$name] = $value;
			}
		}	
		// set locks
		if ($locked !== null) {
			$this->locked = $locked;
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
	* is a parameter able to be modified?
	*
	* @param $name String
	* @return Boolean
	*/
	private function is_unlocked($name) {
		if (in_array($name, $this->locked))
			return false;
		else
			return true;
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
				if ($get && $this->is_unlocked($name)) {	
					// prepare and validate multiple values
					if (stristr($get, "|")) {
						foreach (explode("|", $get) as $value) {
							if ($this->is_valid($name, $value)) {
								$params[$name][] = $value;
							} else {
								$this->error_message("value", array($name, $value));
							}	
						}
					// validate single values	
					} elseif ($this->is_valid($name, $get)) {
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
	private function is_valid($name, $value) {
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