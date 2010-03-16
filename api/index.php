<?php

// VITAL STATS
require '../config/login.php';

// IMPORT CLASSES
require_once '../scripts/gapi.class.php';
require_once '../scripts/gdx.class.php';
require_once '../scripts/gdx_helper.class.php';

// example of adding defaults and locking parameters

$defaults = array(	
	'dates' => array("2010-02-01", "2010-02-22"), 
	'profile' => array("Newbeat", 21673320) 
);

$locked = array(
	'profile',
	'segment',
	'filters'
);	

// PROCESS REQUEST
$myHelper = new gdx_helper($defaults, $locked);
$myGdx = new gdx($myHelper->data);
$myGdx->report();




?>
