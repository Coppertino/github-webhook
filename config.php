<?php

#
# MAIN configuration file
#
define('__CACHE_DIR__',		__DIR__.'/cache');
define('__SYNC_CONFIGS_DIR__',	__DIR__.'/sync-configs');
define('__ALLOWED_IPS__',	'127.0.0.1'); # Separated with comma. Empty string - all ips allowed. Example: '127.0.0.1, 192.168.1.1'

define('__CMD_CLONE__',		'git clone $repo_url $cache_dir');
define('__CMD_SYNC__',		'cd $cache_dir && git pull');


define('LOG_FILENAME',		'git-sync.log');
define('MAIL_TO',		'atomic@iig-global.com'); # lucky@iig-global.com, atomic@iig-global.com, acrist@iig-global.com
define('MAIL_LOGS',		true);
define('MAIL_ERRORS',		true);


# Protocol-specific configurations
$proto_conf = array(
	'rsync+ssh' => array(
		'exec' => 'rsync -avz --exclude \'.git*\' --delete $from $user@$host:$path$repo_path/ 2>&1',
	),
	'ftp' => array(
		'exec' => 'ncftpput -F -D -R -u $user -p $password $host $srv_path/ $from/* 2>&1',
	)
);

#
# HOST configuration
#
# Config file for storing information about production servers
# in which web site must be synced (allowed configuration when 
# one repository sync to multiple web nodes)
#
# [server short name]
# proto = ''
#

$hosts_conf = array(
	# Development server with SSH server and rsync
	'devbox' => array(
		'proto'		=> 'rsync+ssh',
		'host'		=> '127.0.0.1',
		'user'		=> 'www-data',
		'password'	=> '',
		'path'		=> '/var/www/'
	),
	# Shared Hosting with FTP access
	'hosting' => array(
		'proto'		=> 'ftp',
		'host'		=> '127.0.0.1',
		'user'		=> 'hosting',
		'password'	=> 'hosting',
		'path'		=> 'public_html'
	)
);

#
# REPOSITORIES configuration
#
$repo_conf = array(
	'http://github.com/Coppertino/github-webhook' => array(
		'branch'	=> 'master',
		'hosts'		=> 'devbox',
		'repo_path'	=> '',
		'server_path'	=> 'git-webhook',
		'config_folder'	=> 'webhook'
	)
);