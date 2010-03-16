<?php

// LOGINS
require 'config/login.php';

// IMPORT CLASSES
require_once 'scripts/gapi.class.php';
require_once 'scripts/gdx.class.php';

// DATA
$data['profile'] = array("Newbeat", 21673320);
$data['format'] = "table";
$data['dbTable'] = null;
$data['dates']= array('2010-02-15', '2010-02-24');
$data['dimensions'] = array('pagePath');
$data['metrics'] = array('entrances', 'uniquePageViews', 'pageViews');
$data['sort'] = '-pageViews';
$data['max'] = 100;
$data['filters'] = null;
$data['segment'] = null;

// RUN
$myGdx = new gdx($data);
$myGdx->report();

?>