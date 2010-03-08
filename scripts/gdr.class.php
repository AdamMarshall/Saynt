<?php

/**
 * Class gdr
 * 
 * GDX (Google Data Reader)
 * Access data stored by GDX
 * Controller for mdb2 class
 *	Adam Marshall 2009-10
 */
class gdr {
	
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
	
}

?>	