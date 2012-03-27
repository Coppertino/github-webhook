<?php

# include configuration file
require_once(__DIR__.'/config.core.php');
require_once(__DIR__.'/config.user.php');
ignore_user_abort();

# Copy dir contents
function copyContents($source, $dest) {
	$sourceHandle = opendir($source);
	if (!is_dir($dest)) mkdir($dest, 0777, true);
	
	while($res = readdir($sourceHandle)) {
		if ($res == '.' || $res == '..') continue;
		
		if(is_dir($source . '/' . $res)) {
			copyContents($source . '/' . $res, $dest . '/' . $res);
		}
		else copy($source . '/' . $res, $dest . '/' . $res);
	}
}


/**
 * STEP 0: Preconfiguration
 * - check IP which request this script, 
 * - create temporary dir for repo caching
 */
$sync_task = array();
$error = '';
$log = '------------------------------------------------------------------------------------------'.PHP_EOL.date('Y-m-d H:i:s').PHP_EOL.'Sync started'.PHP_EOL.PHP_EOL;

$log .= 'REQUEST:'.PHP_EOL.print_r($_REQUEST, 1).PHP_EOL.PHP_EOL.PHP_EOL;

# Check requests by ip address
$log .= '------------------------------ IP check'.PHP_EOL;
if (!empty($_SERVER['REMOTE_ADDR'])) {
	if (strlen(__ALLOWED_IPS__) > 0) {
		if (stripos(__ALLOWED_IPS__, $_SERVER['REMOTE_ADDR']) !== false) $log .= 'IP "'.$_SERVER['REMOTE_ADDR'].'": passed'.PHP_EOL;
		else $error = 'This ip "'.$_SERVER['REMOTE_ADDR'].'" is not allowed';
	}
	else $log .= '**WARNING** List of allowed ips is empty. All requests are allowed.'.PHP_EOL;
}
else $error = '$_SERVER[\'REMOTE_ADDR\'] is not set. Operations from console are not allowed.';

/**
 * STEP 1: Get params from POST request and check if this repos 
 * is allowed for this instance of github-webhook
 */
if (empty($error)) $log .= PHP_EOL.'------------------------------ Repo check'.PHP_EOL;
if (empty($error) && !empty($_REQUEST['payload'])) {
	$log .= 'Payload encoded:'.PHP_EOL.print_r($_REQUEST['payload'],1).PHP_EOL;
	$payload = json_decode($_REQUEST['payload'], true);
	$log .= PHP_EOL.'Payload decoded:'.PHP_EOL.print_r($payload,1).PHP_EOL;

	# check if specific variable is exists in loaded payload
	if (!empty($payload)) {
		# check if we have a config file for current repository
		# from submited data
		# need to be like $repo_conf [ repo_url ]; 
		if (isset($repo_conf[@$payload['repository']['url']])) {
			if (strpos($payload['ref'], $repo_conf[$payload['repository']['url']]['branch']) !== false) {
				$log .= 'SYNC CONFIG FOUND'.PHP_EOL.PHP_EOL;
				$sync_conf = &$repo_conf[$payload['repository']['url']];
			}
			else $error = 'Commit not to "'.$repo_conf[$repo_conf[$payload['repository']['url']]].'" branch. Ignore';
		}
		else $error = 'Post-commit webhook not configured for sync "'.$payload['repository']['url'].'" repository';
	}
	else $error = 'Can\'t decode payload variable';
}
else if (empty($error)) $error = 'Payload variable does not exist or empty';


/**
 * STEP 2: Check configuration of repository sync, 
 * commit list of task for syncing
 */
if (empty($error)) $log .= PHP_EOL.'------------------------------ Config check'.PHP_EOL;
if (empty($error) && !empty($sync_conf)) {

	# update cache dir for this repository
	$cache_dir = __CACHE_DIR__.(substr(__CACHE_DIR__,-1) != '/' ? '/' : '').urlencode($payload['repository']['url']);
	$log .= 'Cache dir: '.$cache_dir.PHP_EOL;
	
	$log .= 'Hosts to sync: '.$hosts.PHP_EOL.'=========='.PHP_EOL;
	# build task list for each of this servers
	$hosts = explode(',', $sync_conf['hosts']);
	foreach ($hosts as &$host) {
		$host = trim($host);
		$log .= '----- '.$host.PHP_EOL;
		if (!empty($host) && !empty($hosts_conf[$host])) {
			$c_host = &$hosts_conf[$host];

			# check if sync proto for this server is allowed for script
			if (!empty($proto_conf[$c_host['proto']]['exec'])) {
				$replace = array(
					'$from'		=> $cache_dir.(!empty($sync_conf['repo_path']) ? (substr($sync_conf['repo_path'], 1) != '/' && substr($cache_dir,-1) != '/' ? '/' : '').$sync_conf['repo_path'] : '').'/',
					'$user'		=> $c_host['user'],
					'$host'		=> $c_host['host'],
					'$password'	=> $c_host['password'],
					'$path'		=> $c_host['path'],
					'$repo_path'	=> $sync_conf['server_path'],
				);
				
				$tmp_task = str_replace(array_keys($replace), array_values($replace), $proto_conf[$c_host['proto']]['exec']);
				$log .= 'Task:'.PHP_EOL.$tmp_task.PHP_EOL;
				$sync_task[] = $tmp_task;
			}
			else $log .= '**WARNING** Protocol "'.$c_host['proto'].'" not described in post-commit configuration. Ignore sync to host "'.$host.'"'.PHP_EOL;
		}
		else $log .= '**WARNING** Host "'.$host.'" not found in post-commit configuration. Ignore sync'.PHP_EOL;
	}
	$log .= '=========='.PHP_EOL;
}


/**
 * STEP 3: 
 * checkout (cache) commited version of master branch 
 */
if (empty($error)) $log .= PHP_EOL.'------------------------------ Checkout & sync'.PHP_EOL;
if (empty($error) && !empty($sync_task)) {

	# prepare for clone repo or update it
	$updated = false;
	$git = array (
		'$repo_url'	=> str_replace('http://', 'git://', $payload['repository']['url']).'.git',
		'$cache_dir'	=> $cache_dir,
	);

	# check if cache dir is exists. if not - create it
	if (!is_dir(__CACHE_DIR__)) {
		@mkdir(__CACHE_DIR__, 0777, true);
	}


	# cache project (clone if not exists or sync)
	# if dir is already created, try to sync it
	if (is_dir($git['$cache_dir'])) {
		$log .= '---------- SYNC'.PHP_EOL;
		$command = str_replace(array_keys($git), array_values($git), __CMD_SYNC__);
		echo '<hr/>EXECUTE COMMAND: '.$command.'<br/>';
		$log .= 'Executing: '.$command.PHP_EOL;
		exec($command, $result).'<hr/>';
		echo '* '.implode('<br/>* ', $result);
		$log .= 'Result: '.PHP_EOL.'* '.implode(PHP_EOL.'* ', $result).PHP_EOL.PHP_EOL;
		$updated = true;
	}

	# if repository not updated
	if (!$updated) {
		$log .= '---------- CLONE'.PHP_EOL;
		$command = str_replace(array_keys($git), array_values($git), __CMD_CLONE__);
		echo '<hr/>EXECUTE COMMAND: '.$command.'<br/>';
		$log .= 'Executing: '.$command.PHP_EOL;
		exec($command, $result);
		echo '* '.implode('<br/>* ', $result);
		$log .= 'Result: '.PHP_EOL.'* '.implode(PHP_EOL.'* ', $result).PHP_EOL.PHP_EOL;
	}
	
	if (!empty($sync_conf['config_folder'])) {
		$files = @scandir(__SYNC_CONFIGS_DIR__ . '/' . $sync_conf['config_folder']);
		
		# Copy configs
		if (count($files) > 2) {
			$log .= 'Found config folder: "'.$sync_conf['config_folder'].'"'.PHP_EOL.PHP_EOL;
			copyContents(__SYNC_CONFIGS_DIR__ . '/' . $sync_conf['config_folder'], $git['$cache_dir']);
		}
	}

	# check if dir is not empty. If not - do sync command
	$files = @scandir($git['$cache_dir']);
	if (count($files) > 2) {
		foreach ($sync_task as $task) {
			$log .= '---------- UPLOAD'.PHP_EOL;
			echo '<hr/>'.$task.'<br/>';
			$log .= 'Executing: '.$command.PHP_EOL;
			exec($task, $result);
			echo '* '.implode('<br/>* ', $result);
			$log .= 'Result: '.PHP_EOL.'* '.implode(PHP_EOL.'* ', $result).PHP_EOL.PHP_EOL;
		}
	}
}

# Log and report
if (!empty($error)) $log .= '**ERROR** '.$error.PHP_EOL;
else $log .= '** SYNC FINISHED **'.PHP_EOL;

file_put_contents(LOG_FILENAME, $log.PHP_EOL, FILE_APPEND);

if (MAIL_LOGS || !empty($error) && MAIL_ERRORS) {
	mail(MAIL_TO, 'git-webhook '.(!empty($error) ? 'error' : 'successfully synced').' at '.date('Y-m-d H:i:s'), $log);
}