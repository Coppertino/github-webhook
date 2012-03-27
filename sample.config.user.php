<?php

# Separated with comma. Empty string - all ips allowed.
# Look in admin panel for GitHub public IP's
# Example: '127.0.0.1, 192.168.1.1'
define('__ALLOWED_IPS__',	'94.45.140.46, 207.97.227.253, 50.57.128.197');

# Emails to send sync results
define('MAIL_TO',		'johndoe@example.com');
# Mail everything, including logs.
define('MAIL_LOGS',		true);
# Mail only if error occured
define('MAIL_ERRORS',		true);


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
	'dev' => array(
		'proto'		=> 'rsync+ssh',
		'host'		=> '127.0.0.1',
		'user'		=> 'www-data',
		'password'	=> '',
		'path'		=> '/var/www/'
	),
	# Shared Hosting with FTP access
	'prod' => array(
		'proto'		=> 'ftp',
		'host'		=> '127.0.0.1',
		'user'		=> 'hosting',
		'password'	=> 'hosting',
		'path'		=> '/usr/sites/'
	)
);

#
# REPOSITORIES configuration
#
$repo_conf = array(
	'http://github.com/Coppertino/github-webhook' => array(
		'branch'	=> 'master',
		'hosts'		=> 'dev',
		'repo_path'	=> '',
		'server_path'	=> 'github-webhook',
		'config_folder'	=> 'webhook'
	)
);