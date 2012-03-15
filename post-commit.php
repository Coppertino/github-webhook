<?php

# include configuration file
require_once(__DIR__.'/config.php');
ignore_user_abort();


/**
 * STEP 0: Preconfiguration
 * - check IP which request this script, 
 * - create temporary dir for repo caching
 */
$sync_task = array();
$error = $warning = '';


// TODO: add checking IP of remote addr


/**
 * STEP 1: Get params from POST request and check if this repos 
 * is allowed for this instance of github-webhook
 */
if (!empty($_REQUEST['payload'])) {
	$payload = json_decode($_REQUEST['payload'], true);

	# check if specific variable is exists in loaded payload
	if (!empty($payload)) {
		# check if we have a config file for current repository
		# from submited data
		# need to be like $repo_conf [ repo_url ]; 
		if (isset($repo_conf[@$payload['repository']['url']])) {
			if (strpos($payload['ref'], $repo_conf[$payload['repository']['url']]['branch']) !== false) {
				$sync_conf = &$repo_conf[$payload['repository']['url']];
			}
			else $error = 'Commit not to "'.$repo_conf[$repo_conf[$payload['repository']['url']]].'" branch. Ignore';
		}
		else $error = 'Post-commit webhook not configured for sync "'.$payload['repository']['url'].'" repository';
	}
	else $error = 'Can\'t decode payload variable';
}
else $error = 'Playload variable not exists or empty';


/**
 * STEP 2: Check configuration of repository sync, 
 * commit list of task for syncing
 */
if (empty($error) && !empty($sync_conf)) {

	# update cache dir for this repository
	$cache_dir = __CACHE_DIR__.(substr(__CACHE_DIR__,-1) != '/' ? '/' : '').urlencode($payload['repository']['url']);

	# build task list for each of this servers
	$hosts = explode(',', $sync_conf['hosts']);
	foreach ($hosts as &$host) {
		$host = trim($host);
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

				$sync_task[] = str_replace(array_keys($replace), array_values($replace), $proto_conf[$c_host['proto']]['exec']);
			}
			else $warning = 'Protocol "'.$c_host['proto'].'" not described in post-commit configuration. Ignore sync to host "'.$host.'"';
		}
		else $warning = 'Host "'.$host.'" not found in post-commit configuration. Ignore sync';
	}
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

	# check if cache dir is exists. if not - create it
	if (!is_dir(__CACHE_DIR__)) {
		@mkdir(__CACHE_DIR__, 0777, true);
	}


	# cache project (clone if not exists or sync)
	# if dir is already created, try to sync it
	if (is_dir($git['$cache_dir'])) {
		$command = str_replace(array_keys($git), array_values($git), __CMD_SYNC__);
		echo '<hr/>EXECUTE COMMAND: '.$command.'<br/>';
		exec($command, $result).'<hr/>';
		echo '* '.implode('<br/>* ', $result);
		$updated = true;
	}

	# if repository not updated
	if (!$updated) {
		$command = str_replace(array_keys($git), array_values($git), __CMD_CLONE__);
		echo '<hr/>EXECUTE COMMAND: '.$command.'<br/>';
		exec($command, $result);
		echo '* '.implode('<br/>* ', $result);
	}

	# check if dir is not empty. If not - do sync command
	$files = @scandir($git['$cache_dir']);
	if (count($files) > 2) {
		foreach ($sync_task as $task) {
			echo '<hr/>'.$task.'<br/>';
			exec($task, $result);
			echo '* '.implode('<br/>* ', $result);
		}
	}
}

// TODO: adding correct processing error

echo 'Warnings: '.$warning."\n";
echo 'Errors: '.$error."\n";