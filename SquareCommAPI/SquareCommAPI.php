<?php

/*
Copyright 2015  Tom Mills (http://www.squarestatesoftware.com)

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

//----------------------------------------------------------------------------
// SquareCommAPI.PHP - SquareComm api
//----------------------------------------------------------------------------

//
// required scripts 
//
require_once ('file.php');
//require_once (dirname(__FILE__) . '/../SquareStateAPI/sqss_serverapi.php');

//----------------------------------------------------------------------------

abstract class squarecommapi_error {
        // error codes

        const errNoAttribute            = 90;
}

class squarecommapi extends sqss_serverapi {
	private $parms;
	private $db			= "";
	private $plugin			= "";

	public $endpoint		= "";
	public $req			= "";
	public $payload			= "";
	public $reqstring		= "";
	public $dbname			= "";
	public $dbusername		= "";
	public $dbhost			= "";
	public $dbprefix		= "";
	public $dbpath			= "";
	public $msg			= "";
	public $stat			= "";
	public $prefix			= "";
	public $version			= "1.1.0";
	public $upc			= "";
	public $attribute		= "";
	public $debug			= "";
	public $uploaddir		= "";

	public $errCode			= 0;
	public $errbuff			= array();
	public $phpbuff			= array();

	public $posts			= "";
	public $postmeta		= "";

	protected $User;

	protected $fileapi;

	public function __construct($request, $origin, $vars) {
		global $wpdb;

		parent::__construct($request, $origin, $vars);

		$this->plugin = $vars['myplugin'];
		$this->debug = $vars['debug'];
		$this->dbname = $vars['dbname'];
		$this->dbusername = $vars['dbusername'];
		$this->dbpath = $vars['dbpath'];
		$this->dbprefix = $vars['dbprefix'];
		$this->siteURL = $vars['siteURL'];

		$this->uploaddir = wp_upload_dir();

		$this->fileapi = new squarecommfile();

		$old_error_handler = set_error_handler(array($this, 'php_error_handler'));

		if ($this->debug > 0) {
			if ($this->debug > 1) {
				$this->error_log(__METHOD__."; _SERVER: ".print_r($_SERVER,true));
			}
			$this->error_log(__METHOD__."; request: ".$request);
			$this->error_log(__METHOD__."; origin: ".$origin);
			$this->error_log(__METHOD__."; vars: ".print_r($vars,true));
		}

		$this->verb = $_SERVER['REQUEST_METHOD'];
		$this->url_elements = explode('/', $_SERVER['REQUEST_URI']);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; url elements: ".print_r($this->url_elements,true));
		}

		$this->squarecommapi_parse_args();

		if ($this->debug > 2) {
			$this->error_log(__METHOD__."; parms: ".print_r($this->parms,true));
		}

		if ($vars['body'] == 'YES') {
			//
			// override vars with request body parms
			//
			$this->dbname = $this->parms['dbname'];
			$this->dbusername = $this->parms['dbusername'];
			$this->dbpath = $this->parms['dbpath'];
			$this->dbhost = $this->parms['dbhost'];
			$this->dbprefix = $this->parms['dbprefix'];
			$this->httpmethod = $this->parms['httpmethod'];
			$this->action = $this->parms['action'];
			$this->prodID = $this->parms['prodID'];
			$this->upc = $this->parms['upc'];
			$this->attribute = $this->parms['attribute'];
			$this->siteURL = $this->parms['siteURL'];
			
			$payload = $this->parms['payload'];
			$this->payloadIn = json_decode(json_encode($payload), true);
		}

		$this->posts = $this->dbprefix."posts";
		$this->postmeta = $this->dbprefix."postmeta";

		
		if ($this->debug > 0) {
			$this->error_log("this->uploaddir: ".print_r($this->uploaddir,true));
			$this->error_log("this->dbprefix: ".$this->dbprefix);
			$this->error_log("this->dbpath: ".$this->dbpath);
			$this->error_log("this->posts: ".$this->posts);
			$this->error_log("this->postmeta: ".$this->postmeta);
			$this->error_log("wpdb->dbname: ".$wpdb->dbname);
			$this->error_log("wpdb->dbhost: ".$wpdb->dbhost);
			$this->error_log("wpdb->prefix: ".$wpdb->prefix);
		}
	}

	// error handler function
	public function php_error_handler($errno, $errstr, $errfile, $errline) {
		if ($errfile == __FILE__) {
			$error = $errstr."; Line Number: ".$errline."; File: ".$errfile;
			$this->phpbuff[] = $error;
		}

		/* Don't execute PHP internal error handler */
		return true;
	}

	public function squarecommapi_parse_args() {
		$parms = array();

		if (isset($_SERVER['QUERY_STRING'])) {
			parse_str($_SERVER['QUERY_STRING'], $parms);
		}
 
		// now how about PUT/POST bodies? These override what we got from GET

		$content_type = "";
		if (isset($_SERVER['CONTENT_TYPE'])) {
			$content_type = $_SERVER['CONTENT_TYPE'];
		}

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; content_type: ".$content_type);
		}

		$body = $this->file;	// parent class reads from input

		switch($content_type) {
			case "application/json":
				$body_params = json_decode($body);
				if ($body_params) {
					foreach($body_params as $param_name => $param_value) {
						$parms[$param_name] = $param_value;
					}
				}
				$this->format = "json";
				break;

			case "application/x-www-form-urlencoded":
				parse_str($body, $postvars);
				foreach($postvars as $field => $value) {
					$parms[$field] = $value;
				}
				$this->format = "html";
				break;

			default:
				// we could parse other supported formats here
				break;

		}
		$this->parms = $parms;
	}

	//----------------------------------------------------------------------------
	// REST endpoints 
	//----------------------------------------------------------------------------

	//----------------------------------------------------------------------------
	// error 
	//----------------------------------------------------------------------------
	protected function error($args) {
		$this->endpoint = $args[1];
		$this->reqstring = "sqss_req_".$this->endpoint;
		$this->errCode = $this->sqss_do_request(sqss_request_type::sqss_req_error, $args);

		//if ($this->debug > 0) {
			error_log(__METHOD__."; args: ".print_r($args,true));
			error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		//}

		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// handshake 
	//----------------------------------------------------------------------------
	protected function handshake($args) {
		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		$this->reqstring = "sqss_req_handshake";
		$this->endpoint = sqss_request_type::sqss_req_handshake;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// reset_demo
	//----------------------------------------------------------------------------
	protected function reset_demo($args) {
		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		$this->reqstring = "sqss_req_reset_demo";
		$this->endpoint = sqss_request_type::sqss_req_reset_demo;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}
/*
	//----------------------------------------------------------------------------
	// dbstate 
	//----------------------------------------------------------------------------
	protected function dbstate($args) {
		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		$this->reqstring = "sqss_req_dbstate";
		$this->endpoint = sqss_request_type::sqss_req_dbstate;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}
*/
	//----------------------------------------------------------------------------
	// customtypes 
	//----------------------------------------------------------------------------
	protected function customtypes($args) {
		$this->reqstring = "sqss_req_customtypes";
		$this->endpoint= sqss_request_type::sqss_req_customtypes;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 0) {
			error_log(__METHOD__."; args: ".print_r($args,true));
			error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//-----------------------------------------------------------------------------
	// products
	//----------------------------------------------------------------------------
	protected function products($args) {
		$this->reqstring = "sqss_req_product";
		$this->endpoint = sqss_request_type::sqss_req_product;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// variations 
	//----------------------------------------------------------------------------
	protected function variations($args) {
		$this->reqstring = "sqss_req_variation";
		$this->endpoint = sqss_request_type::sqss_req_variation;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// attributes 
	//----------------------------------------------------------------------------
	protected function attributes($args) {
		$this->reqstring = "sqss_req_attributes";
		$this->endpoint= sqss_request_type::sqss_req_attributes;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// metakeys 
	//----------------------------------------------------------------------------
	protected function metakeys($args) {
		$this->reqstring = "sqss_req_metakeys";
		$this->endpoint = sqss_request_type::sqss_req_metakeys;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// upcs 
	//----------------------------------------------------------------------------
	protected function upcs($args) {
		$this->reqstring = "sqss_req_upcs";
		$this->endpoint= sqss_request_type::sqss_req_upc;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// media 
	//----------------------------------------------------------------------------
	protected function media($args) {
		$this->reqstring = "sqss_req_media";
		$this->endpoint= sqss_request_type::sqss_req_media;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// image 
	//----------------------------------------------------------------------------
	protected function image($args) {

		$this->reqstring = "sqss_req_image";
		$this->endpoint = sqss_request_type::sqss_req_image;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// feedback 
	//----------------------------------------------------------------------------
	protected function feedback($args) {
		$this->reqstring = "sqss_req_feedback";
		$this->endpoint = sqss_request_type::sqss_req_feedback;
		$this->errCode = $this->sqss_do_request($this->endpoint, $args);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; payload: ".print_r($this->payload,true));
		}
		return $this->payload;
	}

	//----------------------------------------------------------------------------
	// do request 
	//----------------------------------------------------------------------------
	public function sqss_do_request($request, $args) {
		$this->stat = "Success";

		$this->payload	= array();

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; request: ".$request."; args: ".print_r($args,true));
		}

		switch ($request) {
		    case sqss_request_type::sqss_req_error:

			$this->errCode = array_shift($args);
			$req = array_shift($args);
			$errMsg = ". ".array_shift($args);

			goto err;

		    case sqss_request_type::sqss_req_handshake:

			$this->errCode = $this->handshake_request();
			if ($this->errCode != sqss_error::errSuccess)
				goto err;

			break;

		    case sqss_request_type::sqss_req_reset_demo:

			//$this->reqstring = "sqss_req_reset_demo";
			$this->errCode = $this->reset_demo_request();
			if ($this->errCode != sqss_error::errSuccess)
				goto err;

			break;

		    case sqss_request_type::sqss_req_customtypes:

			$this->errCode = $this->customtype_query();
			if ($this->errCode != sqss_error::errSuccess)
				goto err;

			break;

		    case sqss_request_type::sqss_req_product:

			if ($this->action == 'RECV') {
				$this->errCode = $this->product_query($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			} else if ($this->action == 'SEND') {
				if (array_key_exists('product', $args)) {
					$product = stripslashes($args['product']);
	
					if ($this->debug > 1) {
						$this->error_log(__METHOD__."; product id: ".$product['id']);
					}
					$this->req = json_decode($product, true);

					if (!$this->req || $this->req == "" || !isset($this->req)) {
						$this->error_log(__METHOD__."; json error: ".sqss_json_error(json_last_error()));
						goto err;
					}
				}

				$this->errCode = $this->product_update($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			} else if ($this->action == 'DELETE') {
				if (array_key_exists('product', $args)) {
					$product = stripslashes($args['product']);
	
					if ($this->debug > 1) {
						$this->error_log(__METHOD__."; product id: ".$product['id']);
					}
					$this->req = json_decode($product, true);

					if (!$this->req || $this->req == "" || !isset($this->req)) {
						$this->error_log(__METHOD__."; json error: ".sqss_json_error(json_last_error()));
						goto err;
					}
				}

				$this->errCode = $this->product_delete($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			}
			
			break;

		    case sqss_request_type::sqss_req_variation:

			if ($this->action == 'RECV') {
				$this->errCode = $this->product_variation_query($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			} else if ($this->action == 'SEND') {
				if (array_key_exists('product', $args)) {
					$product = stripslashes($args['product']);
	
					if ($this->debug > 1) {
						$this->error_log(__METHOD__."; product id: ".$product['id']);
					}
					$this->req = json_decode($product, true);

					if (!$this->req || $this->req == "" || !isset($this->req)) {
						$this->error_log(__METHOD__."; json error: ".sqss_json_error(json_last_error()));
						$this->error_log(__METHOD__."; errant json: ".$product);
						goto err;
					}
				}

				$this->errCode = $this->product_variation_update($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			} else if ($this->action == 'DELETE') {
				if (array_key_exists('product', $args)) {
					$product = stripslashes($args['product']);
	
					if ($this->debug > 1) {
						$this->error_log(__METHOD__."; product id: ".$product['id']);
					}
					$this->req = json_decode($product, true);

					if (!$this->req || $this->req == "" || !isset($this->req)) {
						$this->error_log(__METHOD__."; json error: ".sqss_json_error(json_last_error()));
						goto err;
					}
				}

				$this->errCode = $this->variation_delete($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			}

			break;

		    case sqss_request_type::sqss_req_attributes:

			if ($this->action == 'RECV') {
				$this->errCode = $this->attribute_query();
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			}

			break;

		    case sqss_request_type::sqss_req_metakeys:

			if ($this->action == 'RECV') {
				$this->errCode = $this->metakey_query();
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			}

			break;

		    case sqss_request_type::sqss_req_upc:

			if ($this->action == 'RECV') {
				$this->errCode = $this->upc_query();
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			}

			break;

		    case sqss_request_type::sqss_req_media:

			if ($this->action == 'RECV') {
				$this->errCode = $this->media_query($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			} else if ($this->action == 'SEND') {
				$this->errCode = $this->media_update($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			} else if ($this->action == 'DELETE') {
				$this->errCode = $this->media_delete($args);
				if ($this->errCode != sqss_error::errSuccess)
					goto err;
			}

			break;

		    case sqss_request_type::sqss_req_feedback:

			$this->reqstring = "sqss_req_feedback";
			//$logdata = stripslashes($_POST['feedback']);

			if (array_key_exists('feedback', $args)) {
				$feedback = stripslashes($args['feedback']);
	
				if ($this->debug > 0) {
					$this->error_log(__METHOD__."; feedback: ".print_r($feedback,true));
				}
				$this->req = json_decode($feedback, true);

				if (!$this->req || $this->req == "" || !isset($this->req)) {
					$this->error_log(__METHOD__."; json error: ".sqss_json_error(json_last_error()));
					$this->error_log(__METHOD__."; errant json: ".$feedback);
					goto err;
				}
			}

			$this->errCode = $this->send_feedback($args);
			if ($this->errCode != sqss_error::errSuccess)
				goto err;

			break;

		    case sqss_request_type::sqss_req_dbstate:

			$this->reqstring = "sqss_req_dbstate";
			$this->errCode = $this->get_dbstate($args);
			if ($this->errCode != sqss_error::errSuccess)
				goto err;

			break;

		    default:

			$this->reqstring = "sqss_req_unknown_request_type";
			$this->msg = __METHOD__."; error: unknown query type: $this->endpoint.";

			$this->errCode = array_shift($args);

			goto err;
		}

out:
		$rc = $this->errCode;

		$this->errText = $this->translate_errCode($rc);
		if (!empty($errReq)) {
			$this->errText = $this->errText.$errReq;
		}
		if (!empty($errMsg)) {
			$this->errText = $this->errText.$errMsg;
		}

		// status 
		$status = array(
				'httpmethod'	=> "$this->httpmethod",
				'action'	=> "$this->action",
				'stat'		=> "$this->stat",
				'version'	=> "$this->version",
				'web-service'	=> "$this->plugin",
				'endpoint'	=> "$this->endpoint",
				'dbname'	=> "$this->dbname",
				'errorcode'	=> "$this->errCode",
				'errortext'	=> "$this->errText",
				'errordetail'	=> "$this->msg"
		);

		$this->payload['sqss_status'] = $status;
		$this->payload['sqss_log'] = $this->errbuff;

		return $rc;

err:
		// error
		$this->error_log($this->msg);

		$this->stat = "fail";

		$rc = $this->errCode;
		$this->errText = $this->translate_errCode($this->errCode);

		goto out;
	}

	//----------------------------------------------------------------------------
	// handshake - return the default dbname, user, passwd 
	//----------------------------------------------------------------------------
	private function handshake_request() {
		$payload = array(
			'dbhost'	=> $this->dbhost,
			'dbprefix'	=> $this->dbprefix,
			'dbname'	=> $this->dbname,
			'dbpath'	=> $this->dbpath,
			'dbusername'	=> $this->dbusername,
			'siteURL'	=> $this->siteURL
		);

		$this->payload['sqss_payload'] = $payload;
		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// reset_demo - return the default dbname, user, passwd 
	//----------------------------------------------------------------------------
	private function reset_demo_request() {
		$payload = array(
			'dbhost'	=> $this->dbhost,
			'dbname'	=> $this->dbname,
			'dbpath'	=> $this->dbpath
		);

		$this->payload['sqss_payload'] = $payload;
		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// customtypes - custom field data types 
	//----------------------------------------------------------------------------
	private function customtype_query() {
		global $wpdb;

		// get data types

		$sql = "DESCRIBE $this->postmeta";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errProductQueryFail;
 		}
		$datatypes = json_decode(json_encode($res), true);
 
		$types = array();

		foreach ($datatypes as $type) {
			$typearray = array(
				'field'		=> $type['Field'],
				'type'		=> $type['Type']
			);

			$types[] = $typearray;
		}
		$this->payload['sqss_payload'] = $types;

		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// product query
	//----------------------------------------------------------------------------
	private function product_query($args) {
		global $wpdb;

		$current_site = get_current_site();

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}
/*
		if (count($args) == 0) {
			$offset = 0;
			$count = -1;
		} else if (count($args) == 1) {
			$offset = 0;
			$count = 0;
			$prodID = $args[0];
		} else
 		if (count($args) == 2) {
			if ($args[1] == "variations") {
				$this->reqstring = "sqss_req_variation";
				$this->endpoint = sqss_request_type::sqss_req_variation;

				return $this->product_variation_query($args);
			} else {
				$offset = $args[0];
				$count = $args[1];
			}
		} else if (count($args) > 2) {
			if ($args[1] == "variations") {
				return $this->product_variation_query($args);
			}
		}
		if ($this->debug > 0) {
			//$this->error_log(__METHOD__."; offset: ".$offset."; count: ".$count);
			$this->error_log(__METHOD__."; prodID: ".$prodID);
		}
*/

		//
		// all products
		//
		$sql = "SELECT ID, post_title, post_type, post_name, post_excerpt, post_modified "
			. "FROM $this->posts "
			. "WHERE post_type = 'product' "
			.	"AND post_status = 'publish' "
			. "ORDER BY post_title";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errProductQueryFail;
 		}
		$prods = json_decode(json_encode($res), true);
		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; sql(0): ".$sql);
			$this->error_log(__METHOD__."; result count: ".count($prods));
		}

		//
		// all product thumbnail attachment lists 
		//
		$sql = "SELECT post_id, meta_value "
			. "FROM $this->postmeta "
			. "WHERE meta_key = '_thumbnail_id'";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errProductQueryFail;
 		}
		$thumbids = json_decode(json_encode($res), true);
		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; sql(1): ".$sql);
			$this->error_log(__METHOD__."; result count: ".count($thumbids));
		}

		//
		// all product attachment lists
		//
		$sql = "SELECT post_id, meta_value "
			. "FROM $this->postmeta "
			. " WHERE meta_key = '_wp_attached_file'";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errGetMetaFail;
 		}
		$attachments = json_decode(json_encode($res), true);
		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; sql(2): ".$sql);
			$this->error_log(__METHOD__."; result count: ".count($attachments));
		}

		//
		// all product gallery lists 
		//
		$sql = "SELECT post_id, meta_value "
			. "FROM $this->postmeta "
			. "WHERE meta_key = '_product_image_gallery'";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errGetMetaFail;
 		}
		$galleries = json_decode(json_encode($res), true);
		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; sql(3): ".$sql);
			$this->error_log(__METHOD__."; result count: ".count($galleries));
		}

		$thumbs = array();

		foreach ($thumbids as $thumbid) {
			foreach ($attachments as $attachment) {
				if ($attachment['post_id'] == $thumbid['meta_value']) {
					// found it
					$thumb = array (
						'id' => $thumbid['post_id'],
						'thumbid' => $thumbid['meta_value'],
						'url' => $this->uploaddir['baseurl']."/".$attachment['meta_value']
					);
					$thumbs[] = $thumb;
					break;
				}
			}
		}

		for ($i = 0; $i < count($prods); $i++) {
			$prod = $prods[$i];
			$pID = $prod['ID'];

			if (!empty($prodID) && $prodID != $pID) {
				continue;
			}

			$featuredURLid = -1;
			$featuredURL = "";
			$featuredURLorig = "";

			//
			// get product thumbnail (featured) image
			//
			foreach ($thumbs as $thumb) {
				if ($pID == $thumb['id']) {
					$featuredURLid = $thumb['thumbid'];
					$featuredURL = $thumb['url'];
					$featuredURLorig = $this->fileapi->get_original_fileurl($featuredURL);
					break;
				}
			}

			if ($this->debug > 0) {
				$this->error_log(__METHOD__."; product featured URL: ".$featuredURLid);
			}

			$prodImages = array();

			if ($featuredURLid != -1) {
				if ($this->debug > 0) {
					$this->error_log(__METHOD__."; product featured URL: ".$featuredURL->meta_value);
				}

				$featuredImage = array(
					'id'		=> $featuredURLid,
					'url'		=> $featuredURL,
					'urlorig'	=> $featuredURLorig,
					'type'		=> "featured",
					'action'	=> 'no-op'		// default action
				);

				if ($this->debug > 0) {
					$this->error_log(__METHOD__."; featuredImage: ".print_r($featuredImage,true));
				}

				$prodImages[] = $featuredImage;
			}

			//
			// product gallery
			//
			$gallery_found = FALSE;

			foreach ($galleries as $g) {
				if ($pID == $g['post_id']) {
					$gallery = $g['meta_value'];
					$gallery_found = TRUE;
					break;
				}
			}

			//
			// get product gallery images
			//
			$urlid = -1;
			$url = "";
			$urlorig = "";

			$gallery_ids = array();

			if ($gallery_found) {
				$gallery_ids = explode(",", $gallery);

				if ($this->debug > 0) {
					$this->error_log(__METHOD__."; gallery_ids: ".print_r($gallery_ids,true));
				}

				foreach ($gallery_ids as $gid) {
					//
					// find gid in attachments 
					//
					$gurl = "";

					foreach ($attachments as $attachment) {
						if ($attachment['post_id'] == $gid) {
							// found gallery attachment 
							$gurl = $attachment['meta_value'];
							break;
						}
					}

					if ($gurl) {
						$url = $this->uploaddir['baseurl']."/$gurl";
						$urlorig = $this->fileapi->get_original_fileurl($url);
	
						if ($this->debug > 0) {
							$this->error_log(__METHOD__.": product attachment URL: ".$url."; prod ID: ".$pID);
						}

						$galleryImage = array(
							'id'		=> $gid,
							'url'		=> $url,
							'urlorig'	=> $urlorig,
							'type'		=> "gallery",
							'action'	=> 'no-op'		// default action
						);

						$prodImages[] = $galleryImage;
					}
				}
			}

			// get product variation count
			$sql = "SELECT ID "
				. "FROM $this->posts "
				. " WHERE post_parent = '$pID' "
				. 	"AND post_type = 'product_variation'";
			$res = $wpdb->get_results($sql);

			// product payload
			$product = array(
				'id'			=> $pID,
				'type'			=> $prod['post_type'],
				'name'			=> $prod['post_name'],
				'itemName'		=> $prod['post_title'],
				'description'		=> $prod['post_excerpt'],
				'modified'		=> $prod['post_modified'],
				'featuredUrl'		=> $featuredURL,
				'productImages'		=> $prodImages,
				'variationCount'	=> count($res)
				//'prod_meta_visible'	=> $prod_meta_visible,
			);
			$products[] = $product;
		}

		if (count($products)) {
			$this->payload['sqss_payload'] = $products;
			$this->payload['sqss_uploadurl'] = $this->uploaddir['path'];
			return sqss_error::errSuccess;
		}

		return sqss_error::errNoProducts;
	}

	//----------------------------------------------------------------------------
	// product variation query
	//----------------------------------------------------------------------------
	private function product_variation_query($args) {
		global $wpdb;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		$prodID = $this->prodID;
		$varID = $this->varID;

		if (!empty($prodID)) {
			$sql = "SELECT a.ID, a.post_parent, a.post_title, a.post_type, a.post_name, a.post_excerpt, a.post_modified, "
				. "b.meta_id, b.meta_key, b.meta_value "
				. "FROM $this->posts a, $this->postmeta b "
				. "WHERE a.post_parent = '$prodID' "
				. 	"AND a.ID = b.post_id "
				. 	"AND a.post_type = 'product_variation' "
				.	"AND a.post_status = 'publish' "
				. "ORDER BY post_parent, ID";
		} else {
			$sql = "SELECT a.ID, a.post_parent, a.post_title, a.post_type, a.post_name, a.post_excerpt, a.post_modified, "
				. "b.meta_id, b.meta_key, b.meta_value "
				. "FROM $this->posts a, $this->postmeta b "
				. "WHERE a.ID = b.post_id "
				. 	"AND a.post_type = 'product_variation' "
				.	"AND a.post_status = 'publish' "
				. "ORDER BY post_parent, ID";
		}

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; sql: ".$sql);
		}

		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errVariationQueryFail;
 		}
		$vars = json_decode(json_encode($res), true);

		$var_meta_visible = array();
		$var_meta_hidden = array();

		$variations = array();

		$lastvar = $vars[0];
		$lastID = $lastvar['ID'];

		foreach ($vars as $var) {
			$vID = $var['ID'];

			if (!empty($varID) && $varID != $vID) {
				continue;
			}
			
			if ($var['ID'] != $lastID) {
				// create last variation
				$errCode = $this->create_variation($lastvar, $var_meta_visible, $var_meta_hidden, $variation);
				if ($errCode == squarecommapi_error::errNoAttribute) {
					// variation does not contain the given attribute target
				} else if ($errCode != sqss_error::errSuccess) {
					$this->error_log(__METHOD__."; script error: ".$errCode);
					return sqss_error::errVariationQueryFail;
				} else {
					$variations[] = $variation;
				}
				$lastID = $var['ID'];
			}

			//
			// custom fields that are not 'visible' and must be 'added' to the product
			// via the web interface (ie. wp-admin) in order to make them editable
			//
			if ($var['meta_key'][0] == '_') {
				$nkey = ltrim($var['meta_key'],"_");
				$var_meta_hidden["$nkey"] = ltrim($var['meta_value'], "_");
			} else {
				$nkey = $var['meta_key'];
				$var_meta_visible["$nkey"] = $var['meta_value'];
			}

			$lastvar = $var;
		}

		if (!empty($lastvar)) {
			// create last variation
			$errCode = $this->create_variation($lastvar, $var_meta_visible, $var_meta_hidden, $variation);
			if ($errCode == squarecommapi_error::errNoAttribute) {
				// variation does not contain the given attribute target
			} else if ($errCode != sqss_error::errSuccess) {
				$this->error_log(__METHOD__."; script error: ".$errCode);
				return sqss_error::errVariationQueryFail;
			} else {
				$variations[] = $variation;
			}
		}
		$this->payload['sqss_payload'] = $variations;

		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// create variation from compiled meta data 
	//----------------------------------------------------------------------------
	private function create_variation($var, &$var_meta_visible, &$var_meta_hidden, &$variation) {
		global $wpdb;

		$rc = sqss_error::errSuccess;

		if (empty($var_meta_hidden['upc'])) {
			$var_meta_hidden['upc'] = "";
		}

		// variation 
		$variation = array(
			'id'			=> $var['ID'],
			'title'			=> $var['post_title'],
			'modified'		=> $var['post_modified'],
			'upc'			=> $var_meta_hidden['upc'],
			'var_meta_visible'	=> $var_meta_visible,
			'var_meta_hidden'	=> $var_meta_hidden
		);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; varID: ".$varID);
			$this->error_log(__METHOD__."; attribute: ".print_r($this->attribute,true));
		}

		$terms = $this->dbprefix."terms";
		$term_taxonomy = $this->dbprefix."term_taxonomy";
		$term_relationships = $this->dbprefix."term_relationships";

		$parent = $var['post_parent'];

		$sql = "SELECT a.term_id, a.name, a.slug, b.taxonomy "
			. "FROM $terms a, $term_taxonomy b, $term_relationships c "
			. "WHERE a.term_id = b.term_id "
			. 	"AND c.object_id = '$parent' "
			. 	"AND b.term_taxonomy_id = c.term_taxonomy_id "
			. 	"AND LOWER(b.taxonomy) LIKE 'pa_%' "
			. "ORDER BY term_id";

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; sql: ".$sql);
		}

		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			$rc = sqss_error::errTaxonomyQueryFail;
			goto out;
 		}
		$taxonomies = json_decode(json_encode($res), true);

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; taxonomies: ".print_r($taxonomies,true));
		}

		$attributes = array();

		for ($i = 0; $i < count($taxonomies); $i++) {

			if (strstr($taxonomies[$i]['taxonomy'],"pa_")) {
				$taxkey = "attribute_".$taxonomies[$i]['taxonomy'];
			} else {
				$taxkey = "attribute_pa_".$taxonomies[$i]['taxonomy'];
			}

			$taxvalue = $taxonomies[$i]['name'];

			//
			// does this variation contain this attribute ?
			//
			foreach ($var_meta_visible as $key => $value) {

				if (strtolower($key) == strtolower($taxkey)) {

					if (strtolower($value) == strtolower($taxvalue)) {
						$attribute = array(
							'id'		=> $taxonomies[$i]['term_id'],
							'name'		=> ucwords($taxvalue)
						);
						$attrkey = substr($taxkey,strlen("attribute_"));
						if (strstr($attrkey,"pa_")) {
							$attrkey = substr($attrkey,strlen("pa_"));
						}
						$attributes["$attrkey"] = $attribute;
					}	
				}	
			}
		}

		if (empty($this->attribute)) {
			goto out;
		}

		//
		// filter out attributes not contained in the target attribute set
		//
		$targetattribute = json_decode(json_encode($this->attribute), true);

		foreach ($attributes as $key => $value) {

			if (strtolower($key) == strtolower($targetattribute['key'])) {
				// key found. now find value
				foreach ($targetattribute['values'] as $targetattributevalue) {
					if ($targetattributevalue['selected'] == '1'
						&& strtolower($targetattributevalue['val']) == strtolower($value['name'])) {
						// value found
						goto out;
					}
				}
			}
		}

		return squarecommapi_error::errNoAttribute;

out:

		$variation['attributes'] = $attributes;

		unset($var_meta_visible);
		$var_meta_visible = array();
		unset($var_meta_hidden);
		$var_meta_hidden = array();

		return $rc;
	}

	//----------------------------------------------------------------------------
	// upc query
	//----------------------------------------------------------------------------
	private function upc_query() {
		global $wpdb;

		$sql = "SELECT a.ID, a.post_parent, a.post_title, a.post_type, a.post_name, a.post_excerpt, a.post_modified, "
			. "b.meta_id, b.meta_key, b.meta_value "
			. "FROM $this->posts a, $this->postmeta b "
			. "WHERE a.ID = b.post_id "
			. 	"AND a.post_type = 'product_variation' "
			.	"AND a.post_status = 'publish' "
			.	"AND b.meta_key = '_upc' "
			.	"AND b.meta_value != '' "
			. "ORDER BY post_parent, ID";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errVariationQueryFail;
 		}
		$upcrecs = json_decode(json_encode($res), true);

		$sql = "SELECT post_id, meta_key, meta_value "
			. "FROM $this->postmeta "
			. "WHERE meta_key LIKE 'attribute_%' "
			. "ORDER BY post_id";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errGetMetaFail;
 		}
		$attrrecs = json_decode(json_encode($res), true);

		$sql = "SELECT ID, post_title "
			. "FROM $this->posts "
			. "WHERE post_type = 'product' "
			. 	"AND post_status = 'publish'";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errGetMetaFail;
 		}
		$prods = json_decode(json_encode($res), true);

		unset($this->payload);

		$upcs = array();

		foreach ($upcrecs as $upcrec) {
			$upcid = $upcrec['meta_value'];

			$attributes = array();

			foreach ($attrrecs as $attrrec) {
				if ($upcrec['ID'] == $attrrec['post_id']) {
					$keyStr = substr($attrrec['meta_key'], strlen("attribute_pa_"));
					//$keyStr = $attrrec['meta_key'];

					$attribute = array(
						'key'		=> $keyStr,
						'value'		=> $attrrec['meta_value']
					);
					$attributes[] = $attribute;
				}
			}

			foreach ($prods as $prod) {
				if ($upcrec['post_parent'] == $prod['ID']) {
					$name = $prod['post_title'];
					break;
				}
			}

			// upc 
			$upc = array(
				'name'		=> $name,
				'id'		=> $upcrec['ID'],
				'upc'		=> "$upcid",
				'attributes'	=> $attributes
			);
			$upcs[] = $upc;
		}
		$this->payload['sqss_payload'] = $upcs;

		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// attribute query - 
	//----------------------------------------------------------------------------
	private function attribute_query() {
		global $wpdb;

		$term_relationships = $this->dbprefix."term_relationships";
		$sql = "SELECT DISTINCT a.meta_key, a.meta_value "
			. "FROM $this->postmeta a, $this->posts b, $this->posts c "
			. "WHERE a.meta_key LIKE 'attribute_%' "
			. "	AND a.post_id = b.ID "
			. "	AND b.post_parent = c.ID "
			. "ORDER BY a.meta_key";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errAttributesQueryFail;
 		}
		$keys = json_decode(json_encode($res), true);
 
		$lastKey = $keys[0]['meta_key'];
		$lastVal = $keys[0]['meta_value'];

		$values = array();
		$attrs = array();

		for ($i = 0; $i < count($keys); $i++) {

			$key = $keys[$i];

			if ($key['meta_key'] != $lastKey) {
				$value = array(
					'val'		=> $lastVal,
					'selected'	=> '0' 
				);
				$values[] = $value;

				$keyStr = substr($lastKey, strlen("attribute_pa_"));
				$attr = array(
					'key'		=> $keyStr,
					'values'	=> $values,
					'selected'	=> '0' 
				);
				$attrs[] = $attr;
				
				unset($values);
				$values = array();

				$lastKey = $key['meta_key'];
				$lastVal = $key['meta_value'];
			} else {
				if ($i > 0) {
					$value = array(
						'val'		=> $lastVal,
						'selected'	=> '0' 
					);
					$values[] = $value;
				}

				$lastKey = $key['meta_key'];
				$lastVal = $key['meta_value'];
			}
		}

		if (!empty($lastKey)) {
			$keyStr = substr($lastKey, strlen("attribute_pa_"));
			$attr = array(
				'key'		=> $keyStr,
				'values'	=> $values,
				'selected'	=> '0' 
			);
			$attrs[] = $attr;
		}

		$this->payload['sqss_payload'] = $attrs;

		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// attribute delete - 
	//----------------------------------------------------------------------------
	private function attribute_delete_term($attribute) {
		global $wpdb;

		$id = $attribute['id'];

		$term_taxonomy = $this->dbprefix."term_taxonomy";
		$sql = "SELECT term_id, term_taxonomy_id "
			. "FROM $term_taxonomy "
			. "WHERE term_id = $term_id ";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errAttributesQueryFail;
 		}
		$tt = json_decode(json_encode($res), true);
 
		$attrs = array();
		$lastKey = $keys[0]['meta_key'];
		$lastVal = $keys[0]['meta_value'];

		$values = array();

		foreach ($keys as $key) {
			if ($key['meta_key'] != $lastKey) {
				$value = array(
					'val'		=> $lastVal,
					'selected'	=> '0' 
				);
				$values[] = $value;

				$keyStr = substr($lastKey, strlen("attribute_pa_"));
				$attr = array(
					'key'		=> $keyStr,
					'values'	=> $values,
					'selected'	=> '0' 
				);
				$attrs[] = $attr;
				
				unset($values);
				$values = array();

				$lastKey = $key['meta_key'];
				$lastVal = $key['meta_value'];
			} else {
				$value = array(
					'val'		=> $lastVal,
					'selected'	=> '0' 
				);
				$values[] = $value;

				$lastKey = $key['meta_key'];
				$lastVal = $key['meta_value'];
			}
		}

		if (!empty($lastKey)) {
			$keyStr = substr($lastKey, strlen("attribute_pa_"));
			$attr = array(
				'key'		=> $keyStr,
				'values'	=> $values,
				'selected'	=> '0' 
			);
			$attrs[] = $attr;

		}

		$this->payload['sqss_payload'] = $attrs;

		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// metakeys query - 
	//----------------------------------------------------------------------------
	private function metakey_query() {
		global $wpdb;

		$sql = "SELECT DISTINCT b.meta_key "
			. "FROM $this->posts a, $this->postmeta b "
			. "WHERE a.ID = b.post_id "
			. 	"AND a.post_type = 'product_variation' "
			.	"AND a.post_status = 'publish' "
			. 	"AND b.meta_key NOT LIKE '\_%' "
			.	"AND b.meta_key NOT LIKE 'attribute_%' "
			. "ORDER BY b.meta_key";

		$keys = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errVisibleQueryFail;
 		}
 
		$metakeys = array();

		foreach ($keys as $key) {
			$nkey = ltrim($key->meta_key,"_");
			$metakey = array(
				'key'		=> $nkey,
				'selected'	=> '0' 
			);
			$metakeys[] = $metakey;
		}
		$this->payload['sqss_payload'] = $metakeys;

		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// media query
	//----------------------------------------------------------------------------
	private function media_query() {
		global $wpdb;

		$sql = "SELECT ID, post_date, post_date_gmt, post_modified, post_modified_gmt "
			. "FROM $this->posts "
			. "WHERE post_type = 'attachment' "
			.	"AND post_mime_type LIKE 'image%' "
			.	"AND post_status = 'inherit'";

		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errMediaQueryFail;
 		}
		$imgs = json_decode(json_encode($res), true);

		$images = array();

		foreach ($imgs as $img) {
			$sql = "SELECT meta_value "
				. "FROM $this->postmeta "
				. "WHERE post_id = '$img[ID]' "
				. 	"AND meta_key = '_wp_attached_file'";
			$res = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
				return sqss_error::errVisibleQueryFail;
 			}
			$path = json_decode(json_encode($res[0]),true);

			$url = $this->uploaddir['baseurl']."/".$path['meta_value'];

			$urlarray = array(
				'id'			=> $img['ID'],
				'date_created'		=> $img['post_date'],
				'date_created_gmt'	=> $img['post_date_gmt'],
				'date_modified'		=> $img['post_modified'],
				'date_modified_gmt'	=> $img['post_modified_gmt'],
				'url'			=> $url
			);
			$images[] = $urlarray;

			if ($this->debug > 0) {
				$this->error_log(__METHOD__."; url: ".$url);
			}
		}
		$this->payload['sqss_uploadurl'] = $this->uploaddir['url'];
		$this->payload['sqss_payload'] = $images;

		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// media update 
	//----------------------------------------------------------------------------
	private function media_update($args) {

		$image = json_decode(json_encode($this->parms['payload']), true);

		$url = $image['url'];

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; url: ".$url);
		}

		$rc = sqss_error::errSuccess;

		$this->errCode = $this->fileapi->media_upload($image);
		if ($this->errCode != sqss_error::errSuccess) {
			$rc = $this->errCode;
			goto out;
		}

		// return the url
		$this->payload['sqss_payload'] = $image;

out:
		return $rc;
	}

	//----------------------------------------------------------------------------
	// dbstate 
	//----------------------------------------------------------------------------
	private function get_dbstate($args) {
		global $wpdb;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
			$this->error_log(__METHOD__."; dbusername: ".$dbusername);
		}

		$payload = array(
			'uploadurl'	=> $this->uploaddir['path']
		);

		$this->payload['sqss_payload'] = $payload;
		return sqss_error::errSuccess;

out:
		return $rc;
	}

	//----------------------------------------------------------------------------
	// product update 
	//----------------------------------------------------------------------------
	private function product_update($args) {
		global $wpdb;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		$prodID = $this->prodID;
		$product = json_decode(json_encode($this->parms['payload']), true);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; offset: ".$offset."; count: ".$count);
		}

		$itemName = $product['itemName'];
		$desc = $product['description'];

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; prodID: ".$prodID);
			$this->error_log(__METHOD__."; description: ".$description);
		}

		$rc = sqss_error::errSuccess;

		$now = $this->_now('UTC');
		$images = $product['productImages'];

		if (!empty($prodID)) {
			$sql = "UPDATE $this->posts "
				. "SET post_title='$itemName', post_excerpt='$desc', post_modified='$now', post_modified_gmt='$now' "
				. "WHERE ID = '$prodID'";

			$result = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
				return sqss_error::errProductUpdateFail;
 			}

			if ($result == 0) {
				$this->msg = __METHOD__."; update product failed; product ID = '".$prodID."'";
				$rc = sqss_error::errProductUpdateFail;
				goto out;
			}
		} else {
			$sql = "INSERT INTO $this->posts (post_title, post_name, post_excerpt, post_type, post_modified, post_modified_gmt, post_date, post_date_gmt) "
				. "VALUES ('$itemName', '$slug', '$desc', 'product', '$now', '$now', '$now', '$now')";

			$result = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
				return sqss_error::errProductUpdateFail;
 			}

			if ($result == 0) {
				$this->msg = __METHOD__."; update product failed; product ID = '".$prodID."'";
				$rc = sqss_error::errProductUpdateFail;
				goto out;
			}

			$prodID = $wpdb->insert_id;

			$guid = "https://".$_SERVER['HTTP_HOST']."/?post_type=product&p=".$prodID;

			$sql = "UPDATE $this->posts "
				. "SET guid='$guid' "
				. "WHERE ID = '$prodID'";

			$result = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
				return sqss_error::errProductUpdateFail;
 			}

			if ($result == 0) {
				$this->msg = __METHOD__."; update product failed; product ID = '".$prodID."'";
				$rc = sqss_error::errProductUpdateFail;
				goto out;
			}
		}

		$this->payload['sqss_product_id'] = $prodID;

		//----------------------------------------------------------------------------
		// product images
		//----------------------------------------------------------------------------
		$images = $product['productImages'];

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; product images: ".print_r($images,true));
		}

		if (!empty($images)) {
			$new_gallery_ids = array();

			for ($i = 0, $cnt = count($images); $i < $cnt; ++$i) {
				if ($this->debug > 0) {
					$this->error_log(__METHOD__."; image url: ".$images[$i]['url']);
				}

				$action = $images[$i]['action'];

				if (array_key_exists('type', $images[$i]))
					$type = $images[$i]['type'];
				if (array_key_exists('id', $images[$i]))
					$id = $images[$i]['id'];
				if (array_key_exists('url', $images[$i]))
					$url = $images[$i]['url'];
				if (array_key_exists('image', $images[$i])) {
					$image = $images[$i]['image'];
					$images[$i]['image'] = "";
				}

				$attach_id = "";

				if ($this->debug > 0) {
					$this->error_log(__METHOD__."; url: ".$url."; type: ".$type."; id: ".$id."; action: ".$action);
				}

				if ($action == "no-op") {
					// no op
					$attach_id = $id;
				} else if ($action == "attach") {
					//
					// existing image. add image to the product gallery
					//
					$this->errCode = $this->fileapi->get_image_attachment($url, $attach_id);
					if ($this->errCode != sqss_error::errSuccess) {
						$rc = $this->errCode;
						goto out;
					}
					$id = $attach_id;
/*
					if (empty($attach_id)) {
						// new image
						$this->errCode = $this->fileapi->put_product_image_attachment($prodID, $attach_ID, $url);
						if ($this->errCode != sqss_error::errSuccess) {
							$rc = $this->errCode;
							goto out;
						}
					}
*/

				} else if ($action == "add") {
					//
					// new image. add image to the database and attach to the product
					//
					$this->errCode = $this->add_file($url, $prodID, $image, $attach_id);
					if ($this->errCode != sqss_error::errSuccess) {
						$rc = $this->errCode;
						goto out;
					}

					$images[$i]['id'] = $attach_id;

					if ($type == "featured") {
						$this->errCode = $this->fileapi->make_image_featured($prodID, $attach_id);

						if ($this->errCode != sqss_error::errSuccess) {
							$rc = $this->errCode;
							goto out;
						}
					}
				} else if ($action == "replace") {
					//
					// replace physical image file 
					//
					$name = $images[$i]['localName'];

					$newurl = "";
					$urlorig = "";

					if (array_key_exists('urlorig', $images[$i])) {
						$urlorig = $images[$i]['urlorig'];
					}
					
					$this->errCode = $this->fileapi->add_file($url, $images[$i]['imagedata']);
					if ($this->errCode != sqss_error::errSuccess) {
						$rc = $this->errCode;
						goto out;
					}
				} else if ($action == "revert") {
					$this->errCode = $this->revert_file($url);
					if ($this->errCode != sqss_error::errSuccess) {
						$rc = $this->errCode;
						goto out;
					}
					$attach_id = $id;
				} else if ($action == "delete") {
					$this->errCode = $this->fileapi->delete_file($url);
					if ($this->errCode != sqss_error::errSuccess) {
						$rc = $this->errCode;
						goto out;
					}
				} else {
					$this->error_log(__METHOD__."; invalid action received from client;\naction: '".$action."';\nurl: ".$url);
					$rc = sqss_error::errProductUpdateFail;
					goto out;
				}

				if ($type == "featured") {
					$this->fileapi->make_image_featured($prodID, $id);
					$attach_id = $id;
				}

				$images[$i]['action'] = "no-op";

				// add to gallery ?
				if ($attach_id != "") {
					if ($type == "gallery") {
						// add attachment id gallery
						$key = array_search($attach_id, $new_gallery_ids);
						if ($key == "") {
							// id not in gallery, add it to the gallery
							$new_gallery_ids[] = $attach_id;
						}
					}
				}
			}      

			//
			// updated product gallery
			//
			//$id_string = "[gallery ids=".implode(",",$new_gallery_ids)."]";
			$id_string = implode(",",$new_gallery_ids);

			$sql = "SELECT meta_value FROM $this->postmeta "
				. "WHERE meta_key = '_product_image_gallery' "
				. "	AND post_id = '$prodID'";

			$curr_ids = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
				$rc = sqss_error::errProductUpdateFail;
				goto out;
	 		}

			if ($id_string != "") {
				if ($this->debug > 0) {
					$this->error_log(__METHOD__."; updated gallery ids: ".$id_string);
				}
				// XXX
				// XXX jigoshop post_content ????
				// XXX $format = '[gallery ids="%s"]';
				// XXX
				if (empty($curr_ids)) {
					$sql = "INSERT INTO $this->postmeta (post_id, meta_key, meta_value) "
						. "VALUES ('$prodID', '_product_image_gallery', '$id_string')";
				} else {
					$sql = "UPDATE $this->postmeta "
						. "SET meta_value='$id_string' "
						. "WHERE meta_key = '_product_image_gallery' "
						. "	AND post_id = '$prodID'";
				}

				$result = $wpdb->get_results($sql);
				if ($wpdb->last_error) {
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					$rc = sqss_error::errProductUpdateFail;
					goto out;
		 		}
				if ($result == 0) {
					$this->error_log(__METHOD__."; error: meta update failed; key = _product_image_gallery; value = ".$id_string);
					$rc = sqss_error::errProductUpdateFail;
					goto out;
				}
			}
		}

out:
		$this->payload['sqss_payload'] = $images;

		return $rc;
	}

	//----------------------------------------------------------------------------
	// variation update 
	//----------------------------------------------------------------------------
	private function product_variation_update($args) {
		global $wpdb;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		$prodID	= $args[0];

		$variation = json_decode(json_encode($this->parms), true);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; prodID: ".$prodID);
			$this->error_log(__METHOD__."; product variation arg: ".print_r($variation,true));
		}

		$payload	= $variation['payload'];
		
		if (!empty($payload['parent_id'])) {
			$parentID	= $payload['parent_id'];
		}
		if (!empty($payload['upc'])) {
			$number		= $payload['upc'];
		}
		if (!empty($payload['id'])) {
			$id		= $payload['id'];
		}
		if (!empty($payload['updates'])) {
			$metaUpdates	= $payload['updates'];
		}
		if (!empty($payload['var_meta_visible'])) {
			$metaVisible	= $payload['var_meta_visible'];
		}
		if (!empty($payload['var_meta_hidden'])) {
			$metaHidden	= $payload['var_meta_hidden'];
		}

		$rc = sqss_error::errSuccess;

		//----------------------------------------------------------------------------
		// variation meta values
		//----------------------------------------------------------------------------

		if (isset($number)) {
			//
			// 'SKU' only appears in a request when it is being added to the database
			//

			if ($this->debug > 0) {
				$this->error_log(__METHOD__."; variation['upc']: ".$number);
			}

			if (!empty($number)) {
				$sql = "SELECT ID, post_title "
					. "FROM $this->posts a, $this->postmeta b "
					. "WHERE a.ID = b.post_id "
					. 	"AND a.post_type = 'product_variation' "
					.		"AND a.post_status = 'publish' "
					.		"AND b.meta_key = '_upc' "
					.		"AND b.meta_value = '$number' "
					. "ORDER BY post_title";

				$prods = $wpdb->get_results($sql);
				if ($wpdb->last_error) {
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					$rc = sqss_error::errProductQueryFail;
					goto out;
		 		}

			}
	
			if (count($prods)) {
				foreach ($prods as $prod) {
					if ($id != $prod->ID) {
						//
						// number exists in a different product variation
						// and the request is trying to add it again. 
						//
						$this->msg = __METHOD__."; error: SKU: ".$number." already used; existing product variation with this SKU: ".$prod->post_title;
						$rc = sqss_error::errVariationUpdateFail;
						goto out;
					}
				}
			} else if ($number != "") {
				//
				// number doesn't exist in database or it's empty.
				//
				$sql = "UPDATE $this->postmeta "
					. "SET _upc= '$number' "
					. "WHERE ID = '$id'";

				$result = $wpdb->get_results($sql);
				if ($wpdb->last_error) {
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					$rc = sqss_error::errVariationUpdateFail;
					goto out;
		 		}

				if ($result == 0) {
					$this->msg = sprintf(__METHOD__."; error: meta update failed; key = upc; value = ".$number);
					$rc = sqss_error::errVariationUpdateFail;
					goto out;
				}
			}
		}

		//
		// meta keys
		//

		foreach ($metaUpdates as $key => $value) {
			if (isset($value)) {
				$args = array(
					'id'	=> $id,
					'key'	=> "_".$key,
					'value'	=> $value
				);
				$this->update_meta($args);
			}
		}


out:
		return $rc;
	}

	//----------------------------------------------------------------------------
	// product delete 
	//----------------------------------------------------------------------------
	private function product_delete($args) {
		global $wpdb;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		if (count($args) == 0) {
			$offset = 0;
			$count = -1;
		} else if (count($args) == 1) {
			$offset = 0;
			$count = 0;
			$prodID = $args[0];
		} else if (count($args) == 2) {
			if ($args[1] == "variations") {
				$this->reqstring = "sqss_req_variation";
				$this->endpoint = sqss_request_type::sqss_req_variation;

				return $this->product_variation_update($args);
			} else {
				$offset = $args[0];
				$count = $args[1];
			}
		} else if (count($args) > 2) {
			if ($args[1] == "variations") {
				return $this->product_variation_update($args);
			}
		}

		$prodID = $this->prodID;
		$product = $this->payload;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; parms: ".print_r($this->parms,true));
			$this->error_log(__METHOD__."; offset: ".$offset."; count: ".$count);
			$this->error_log(__METHOD__."; product: ".$product);
		}

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; prodID: ".$prodID);
		}

		$rc = sqss_error::errSuccess;

		$sql = "DELETE "
			. "FROM $this->posts "
			. "WHERE ID = '$prodID'";

		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; delete product failed; product ID: ".$prodID."; error: ".$wpdb->last_error);
			$rc = sqss_error::errProductDeleteFail;
			goto out;
 		}

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args = ".print_r($args, true));
		}
 
		$now = $this->_now('UTC');

		//
		// delete product meta records too
		//
		$sql = "DELETE "
			. "FROM $this->postmeta "
			. "WHERE post_id = '$prodID'";

		$result = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			$rc = sqss_error::errProductDeleteFail;
			goto out;
 		}

		if ($result == 0) {
			$this->error_log(__METHOD__."; delete product meta keys failed; product ID: ".$prodID."; error: ".$wpdb->last_error);
			$rc = sqss_error::errProductDeleteFail;
			goto out;
		}

out:
		return $rc;
	}

	//----------------------------------------------------------------------------
	// variation delete 
	//----------------------------------------------------------------------------
	private function variation_delete($args) {
		global $wpdb;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		if (count($args) == 0) {
			$offset = 0;
			$count = -1;
		} else if (count($args) == 1) {
			$offset = 0;
			$count = 0;
			$prodID = $args[0];
		}

		$prodID = $this->prodID;
		$product = $this->payload;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; parms: ".print_r($this->parms,true));
			$this->error_log(__METHOD__."; offset: ".$offset."; count: ".$count);
			$this->error_log(__METHOD__."; product: ".$product);
		}

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; prodID: ".$prodID);
		}

		$rc = sqss_error::errSuccess;

		$sql = "DELETE "
			. "FROM $this->posts "
			. "WHERE ID = '$prodID'";

		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; delete product failed; product ID: ".$prodID."; error: ".$wpdb->last_error);
			$rc = sqss_error::errProductDeleteFail;
			goto out;
 		}

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args = ".print_r($args, true));
		}
 
		$now = $this->_now('UTC');

		//
		// delete product meta records too
		//
		$sql = "DELETE "
			. "FROM $this->postmeta "
			. "WHERE post_id = '$prodID'";

		$result = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			$rc = sqss_error::errProductDeleteFail;
			goto out;
 		}

		if ($result == 0) {
			$this->error_log(__METHOD__."; delete product meta keys failed; product ID: ".$prodID."; error: ".$wpdb->last_error);
			$rc = sqss_error::errProductDeleteFail;
			goto out;
		}

out:
		return $rc;
	}

	//----------------------------------------------------------------------------
	// media delete 
	//----------------------------------------------------------------------------
	private function media_delete($args) {
		$image = json_decode(json_encode($this->parms['payload']), true);

		$id = $image['id'];
		$url = $image['url'];

		//if ($this->debug > 0) {
			$this->error_log(__METHOD__."; id: ".$id);
			$this->error_log(__METHOD__."; url: ".$url);
		//}

		$rc = sqss_error::errSuccess;

		$this->errCode = $this->fileapi->media_delete($image);
		if ($this->errCode != sqss_error::errSuccess) {
			$rc = $this->errCode;
			goto out;
		}

		// return the url
		$this->payload['sqss_payload'] = $image;

out:
		return $rc;

/*
		global $wpdb;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args: ".print_r($args,true));
		}

		$mediaID = $this->mediaID;
		$media = $this->payload;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; parms: ".print_r($this->parms,true));
			$this->error_log(__METHOD__."; media: ".print_r($media,true));
		}

		$rc = sqss_error::errSuccess;

		$sql = "DELETE "
			. "FROM $this->posts "
			. "WHERE ID = '$prodID'";

		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; delete product failed; product ID: ".$prodID."; error: ".$wpdb->last_error);
			$rc = sqss_error::errMediaDeleteFail;
			goto out;
 		}

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; args = ".print_r($args, true));
		}
 
		$now = $this->_now('UTC');

		//
		// delete media meta records too
		//
		$sql = "DELETE "
			. "FROM $this->postmeta "
			. "WHERE post_id = '$prodID'";

		$result = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			$rc = sqss_error::errMediaDeleteFail;
			goto out;
 		}

		if ($result == 0) {
			$this->error_log(__METHOD__."; delete meta keys failed; ID: ".$mediaID."; error: ".$wpdb->last_error);
			$rc = sqss_error::errMediaDeleteFail;
			goto out;
		}

out:
		return $rc;
*/
	}

	//----------------------------------------------------------------------------
	// product meta: generic meta update
	//----------------------------------------------------------------------------
	private function update_meta($args) {
		global $wpdb;

		$id = $args['id'];
		$key = $args['key'];
		$value = $args['value'];

		$sql = "SELECT meta_key, meta_value "
			. "FROM $this->postmeta "
			. "WHERE post_id = '$id' "
			. 	"AND meta_key = '$key'";

		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errProductQueryFail;
 		}
		$val = json_decode(json_encode($res[0]), true);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; key: ".$key."; db value: ".$val['meta_value']."; new value: ".$value);
		}

		if ($val['meta_value'] != $value) {
			$sql = "UPDATE $this->postmeta "
				. "SET meta_value='$value' "
				. "WHERE post_id = '$id' "
				. "	AND meta_key = '$key'";

			$result = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
				return sqss_error::errVariationUpdateFail;
	 		}

			if ($result == 0) {
				$this->msg = __METHOD__.": postmeta update failed; key = ".$key."; value = ".$value;
				return sqss_error::errVariationUpdateFail;
			}
		}
	}

	//----------------------------------------------------------------------------
	// send_feedback 
	//----------------------------------------------------------------------------
	private function send_feedback($args) {
		$json = json_decode($logdata, true);

		if (!array_key_exists('log', $json)) {
			$log = array('log'	=> "No Log");
		} else {
			$log	= $json['log'];
		}
		if (!array_key_exists('status', $json)) {
			$status = array('status'=> "No Status");
		} else {
			$status	= $json['status'];
		}

		$email	= "admin@squarestatesoftware.com";

		if ($this->debug > 0) {

			$this->error_log(__METHOD__."; log: ".print_r($log,true));
			$this->error_log(__METHOD__."; status: ".print_r($status,true));
			$this->error_log(__METHOD__."; ack: ".print_r($ack,true));
			$this->error_log(__METHOD__."; email: ".$email);
		}

		$message = sprintf("\n\nPlugin Log:\n\n%s\nPlugin Return Status:\n\n%s\nPlugin Ack:\n\n%s",
				print_r($log,true),print_r($status,true),print_r($ack,true));

		$subject = sprintf("squarecomm debug; user: %s",$this->dbprefix);

		$rc = mail($email, $subject, '$message');

		if ($rc == 0) {
			$this->error_log(__METHOD__."; mail failed; error: ".print_r(error_get_last(),true));
		}
out:
		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// gallery file search 
	//----------------------------------------------------------------------------
	private function get_gallery_attachment($post, $appurl) {

		// get the post gallery
		$gallery = get_post_gallery($post, false);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; app file name : ".basename($appurl));
			$this->error_log(__METHOD__."; gallery       : ".print_r($gallery,true));
		}

		if ($gallery) {
			$gallery_urls = $gallery['src'];
			$gallery_ids = explode(",",$gallery['ids']);
		}

		for ($i = 0; $i < count($gallery_urls); ++$i) {
			$galleryurl = $gallery_urls[$i];

			if ($this->debug > 0) {
				$this->error_log(__METHOD__."; gallery name   : ".basename($galleryurl));
			}

			if (!strcmp(basename($galleryurl), basename($appurl))) {
				$attach_id = $gallery_ids[$i];
				if ($attach_id != "") {
					$this->error_log(__METHOD__."; found attachment for: ".basename($appurl)."; id: ".$attach_id);
					return $attach_id;
				}
			}
		}

		return "";
	}

	//----------------------------------------------------------------------------
	// gallery - attach an image to the product
	//----------------------------------------------------------------------------
	private function attach_image($url, &$attach_id) {
		$prodID = $this->req['parent_id'];

		$rc = sqss_error::errSuccess;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; url: ".$url);
			$this->error_log(__METHOD__."; dstfile: ".$dstfile);
		}
		
		$rc = $this->fileapi->put_product_image_attachment($prodID, $attach_ID, $url);

out:
		return $rc;
	}

}

?>
