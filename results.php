<?php
chdir(__DIR__ . '/../../../../../../../');

// we need access handling
include_once 'Services/Context/classes/class.ilContext.php';
ilContext::init(ilContext::CONTEXT_RSS);

require_once("Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

//require_once ('./include/inc.debug.php');
//$request = $DIC->http()->request();
//$content = $request->getBody()->getContents();
//log_var($content, 'content');

require_once (__DIR__ . '/classes/class.ilExAutoScoreConnector.php');
$connector = new ilExAutoScoreConnector();
$connector->receiveResult();
