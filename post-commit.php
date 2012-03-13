<?php

# configuration files definitions
define('__MAIN_CONFIG__', __DIR__.'/config.ini');
define('__REPO_CONFIG__', __DIR__.'/repositories.ini');
define('__HOST_CONFIG__', __DIR__.'/hosts.ini');


/**
 * STEP 0: Preconfiguration
 * - check IP which request this script, 
 * - load main config file
 * - create temporary dir for repo caching
 */
$conf = parse_ini_file(__MAIN_CONFIG__, true);
$conf['main']['cache_dir'] = str_replace('./', __DIR__.'/', $conf['main']['cache_dir']);

$sync_task = array();
$error = $warning = '';



/**
 * STEP 1: Get params from POST request and check if this repos 
 * is allowed for this instance of github-webhook
 */

if (!empty($_REQUEST['payload'])) {
	$payload = json_decode($_REQUEST['payload'], true);

	# check if specific variable is exists in loaded payload
	if (!empty($payload)) {
		if (strpos($payload['ref'], 'master') !== false) {
			$repo_conf = parse_ini_file(__REPO_CONFIG__,true);
			if (!empty($repo_conf)) {
				# check if we have a config file for current repository
				# from submited data
				# need to be like $repo_conf [ repo_url ]; 
				if (isset($repo_conf[$payload['repository']['url']])) {
					$sync_conf = &$repo_conf[$payload['repository']['url']];
				}
				else $error = 'Script '.__REPO_CONFIG__.' not configured for sync "'.$payload['repository']['url'].'" repository';
			}
			else $error = 'Config file '.__REPO_CONFIG__.' can\'t found';
		}
		else $error = 'Commit not to master branch. Ignore';
	}
	else $error = 'Can\'t decode payload variable';
}
else $error = 'Playload variable not exists or empty';



/**
 * STEP 2: Check configuration of repository sync, 
 * commit list of task for syncing
 */
if (empty($error) && !empty($sync_conf)) {
	# load servers config and check if all servers 
	# is described in config or not
	$host_conf = parse_ini_file(__HOST_CONFIG__, true);
	if (!empty($host_conf)) {

		# update cache dir for this repository
		$cache_dir = $conf['main']['cache_dir'].
			(substr($conf['main']['cache_dir'],-1) != '/' ? '/' : '').
			urlencode($payload['repository']['url']);


		# build task list for each of this servers
		$hosts = explode(',', $sync_conf['hosts']);
		foreach ($hosts as &$host) {
			$host = trim($host);
			if (!empty($host_conf[$host])) {
				$c_host = &$host_conf[$host];

				# check if sync proto for this server is allowed for script
				if (isset($c_host['proto']) && !empty($conf[$c_host['proto']]['exec'])) {
					$replace = array(
						'$from'		=> $cache_dir.(!empty($sync_conf['repo_path']) ? (substr($sync_conf['repo_path'], 1) != '/' && substr($cache_dir,-1) != '/' ? '/' : '').$sync_conf['repo_path'] : ''),
						'$user'		=> $c_host['user'],
						'$host'		=> $c_host['host'],
						'$password'	=> $c_host['password'],
						'$path'		=> $c_host['path'],
						'$repo_path'	=> $sync_conf['server_path'],
					);

					$sync_task[] = str_replace(array_keys($replace), array_values($replace), $conf[$c_host['proto']]['exec']);
				}
				else $warning = 'Protocol "'.$c_host['proto'].'" not described in '.__MAIN_CONFIG__.'. Ignore sync to host "'.$host.'"';
			}
			else $warning = 'Host "'.$host.'" not found in '.__HOST_CONFIG__.' file. Ignore sync for it';
		}
	}
	else $error = 'Can\'t load config file '.__HOST_CONFIG__.' with server configurations';
}


/**
 * STEP 3: 
 * checkout (cache) commited version of master branch 
 */
if (empty($error) && !empty($sync_task)) {

	# prepare for clone repo or update it
	$updated = false;
	$git = array (
		'$repo_url'	=> str_replace('http://', 'git://', $payload['repository']['url']).'.git',
		'$cache_dir'	=> $cache_dir,
	);

	# cache project (clone if not exists or sync)
	# if dir is already created, try to sync it
	if (is_dir($git['$cache_dir'])) {
		echo exec(str_replace(array_keys($git), array_values($git), $conf['main']['sync']));
		$updated = true;
	}

	# if repository not updated
	if (!$updated) {
		echo exec(str_replace(array_keys($git), array_values($git), $conf['main']['clone']));
	}

	# check if dir is not empty. If not - do sync command
	$files = @scandir($git['$cache_dir']);
	if (count($files) > 2) {
		foreach ($sync_task as $task) {
			echo $task."\n";
			echo exec($task);
		}
	}
}

echo 'Warnings: '.$warning."\n";
echo 'Errors: '.$error."\n";