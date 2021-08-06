<?php
chdir(__DIR__ . '/../../../../../../../');

//require_once ('./include/inc.debug.php');
//log_request();


// we need access handling
include_once 'Services/Context/classes/class.ilContext.php';
ilContext::init(ilContext::CONTEXT_RSS);

require_once("Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

require_once (__DIR__ . '/classes/class.ilExAutoScoreConnector.php');
$connector = new ilExAutoScoreConnector();
$connector->receiveResult();
