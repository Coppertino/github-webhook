<?php

# configuration files definitions
define('__MAIN_CONFIG__', __DIR__.'config.ini');
define('__REPO_CONFIG__', __DIR__.'repositories.ini');
define('__HOST_CONFIG__', __DIR__.'hosts.ini');

/**
 * STEP 1: Get params from POST request and check if this repos 
 * is allowed for this instance of github-webhook
 */


if (!empty($_REQUEST['payload'])) {
	echo "Payload sent:".$_REQUEST['payload']."\r\n";
	$payload = json_decode($_REQUEST['payload'], true);

	# check if specific variable is exists in loaded payload
	if (!empty($payload) && strpos('master', $payload['ref']) !== false) {
		$repositories = parse_ini_file()
		
		
	}
	else $error = 'Can\'t decode payload variable';
}
else $error = 'Playload variable not exists or empty';

