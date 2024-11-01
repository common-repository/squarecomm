<?php

/**
 * @package SquareComm 
 * @author Square State Software 
 * @version 2.2.0
 */

/*
Plugin Name: SquareComm 
Plugin URI: https://www.squarestatesoftware.com/SquareComm-plugin
Description: Wordpress REST API. Created for the SquareComm mobile app but may support any client app conforming to its API.
Author: Square State Software
Version: 2.2.0
Author URI: https://www.squarestatesoftware.com
License: GPLv2 or later
Text Domain: squarecomm 
*/

/*
Copyright 2015  Tom Mills (https://www.squarestatesoftware.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//if( !defined('$debug') )
//      	define( '$debug', 0);
//$debug = 0;

//
// SquareComm API
//
if (!class_exists('squarecommapi')) {
	require_once (dirname(__FILE__) . '/SquareCommAPI/SquareCommAPI.php');
}

//----------------------------------------------------------------------------
// query vars wordpress hook
//----------------------------------------------------------------------------
function sqss_squarecomm_plugin_query_vars($vars) {

	$vars[] = 'myplugin';
	$vars[] = 'dbname';
	$vars[] = 'dbusername';
	$vars[] = 'dbpath';
	$vars[] = 'debug';
	$vars[] = 'request';
	$vars[] = 'prodID';
	$vars[] = 'upc';
	$vars[] = 'debug';

	//error_log(__METHOD__."; vars: ".print_r($vars,true));

	return $vars;
}

//----------------------------------------------------------------------------
// parse request wordpress hook
//----------------------------------------------------------------------------
function sqss_squarecomm_plugin_parse_request($wp) {
	global $wpdb;
        global $db;
        global $debug;
        global $plugin;
        global $req;
        //global $debug;

        global $errCode;

	//error_log(__METHOD__."; wp->query_vars: ".print_r($wp->query_vars,true));

	if (array_key_exists('debug', $wp->query_vars)) {
        	$debug = $wp->query_vars['debug'];
	} else {
		$debug = 0;
	}

	if ($debug > 0) {
		error_log(__METHOD__."; _SERVER: ".print_r($_SERVER,true));
		error_log(__METHOD__."; wp->query_vars: ".print_r($wp->query_vars,true));
	}

	if (!(array_key_exists('myplugin', $wp->query_vars) && $wp->query_vars['myplugin'] == 'SquareComm-plugin')) {
		goto noop;
	}

	if (class_exists('squarecommapi')) {
                if ($debug > 0) {
			error_log(__METHOD__."; query_vars: ".print_r($wp->query_vars,true));
                }
	} else {
		// return valid http error code
		return 1;
	}

        $wp->query_vars['uploddir'] = wp_upload_dir();

	if (array_key_exists('request', $wp->query_vars)) {
        	$request = $wp->query_vars['request'];
	}

	if (array_key_exists('dbusername', $wp->query_vars)) {
        	$dbusername = $wp->query_vars['dbusername'];
	}

	if (array_key_exists('dbpath', $wp->query_vars)) {
        	$dbpath = $wp->query_vars['dbpath'];
	}

        $vars = array();

	if ($request == sqss_request_type::sqss_req_handshake) {
		goto out;
	}

        $vars['body'] = 'YES';

out:

	if (!empty($wp->query_vars['myplugin'])) {
        	$vars['myplugin'] = $wp->query_vars['myplugin'];
	}
	if (!empty($wp->query_vars['debug'])) {
        	$vars['debug'] = $wp->query_vars['debug'];
	}
	if (!empty($wp->query_vars['dbname'])) {
        	$vars['dbname'] = $wp->query_vars['dbname'];
	}
	if (!empty($wp->query_vars['dbusername'])) {
       		$vars['dbusername'] = $wp->query_vars['dbusername'];
	}
	if (!empty($wp->query_vars['dbpath'])) {
       		$vars['dbpath'] = $wp->query_vars['dbpath'];
	}
       	$vars['uploaddir'] = wp_upload_dir();

	$client = "";
	if (!empty($_SERVER['HTTP_ORIGIN'])) {
        	$client = $_SERVER['HTTP_ORIGIN'];
	}	

        $squarecomm = new squarecommapi($request, $client, $vars);
	echo $squarecomm->processAPI("0");

	exit();

err:

	$request = "error/".$errCode."/".$request;

	if ($debug > 1) {
		error_log(__METHOD__."; error request: ".$request);
	}

	goto out;

noop:

	if ($debug > 1) {
		error_log(__METHOD__."; no-op request: ".$request);
	}

	$request = "sqss_req_ignore";

	return;
}

// Add support for custom vars
add_filter('query_vars', 'sqss_squarecomm_plugin_query_vars');
 
// Process the custom URL
add_action('parse_request', 'sqss_squarecomm_plugin_parse_request');

?>
