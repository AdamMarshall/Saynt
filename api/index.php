<?php

// VITAL STATS
require '../config/login.php';

// IMPORT CLASSES
require_once '../scripts/gapi.class.php';
require_once '../scripts/gdx.class.php';

// example of adding defaults
$defaults = array('dates' => array("2010-02-01", "2010-02-22"));

// PROCESS REQUEST
$myHelper = new gdx_helper($defaults);
$myGdx = new gdx($myHelper->data);
$myGdx->report();




?>
