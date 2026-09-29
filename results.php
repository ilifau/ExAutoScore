<?php
declare(strict_types=1);

chdir(__DIR__ . '/../../../../../../../');

//require_once ('./include/inc.debug.php');
//log_request();

require_once '../vendor/composer/vendor/autoload.php';

// we need access handling
ilContext::init(ilContext::CONTEXT_RSS);
ilInitialisation::initILIAS();

require_once (__DIR__ . '/classes/class.ilExAutoScoreConnector.php');
$connector = new ilExAutoScoreConnector();
$connector->receiveResult();
