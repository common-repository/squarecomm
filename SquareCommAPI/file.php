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
// file.php - file functions 
//----------------------------------------------------------------------------

//
// Add the Square State Software API
//
require_once (dirname(__FILE__) . '/../SquareStateAPI/sqss_serverapi.php');
require_once (ABSPATH . 'wp-admin' . '/includes/image.php');

class squarecommfile extends sqss_serverapi {

 	public function __construct() {
		if ($this->debug > 0) {
			$this->error_log(__METHOD__);
		}
	}

	//----------------------------------------------------------------------------
	// add the image and all related db information
	//----------------------------------------------------------------------------
	public function add_file_and_attach($url, $prodID, $imagedata, &$attach_id) {
		$rc = sqss_error::errSuccess;
	
		$rc = $this->add_file($url, $imagedata, $path);
		if ($rc != sqss_error::errSuccess) {
			$msg = sprintf(__METHOD__."; add file failed;");
			return 999;
		}

		$filetype = mime_content_type ($path);

		// Prepare an array of post data for the attachment.
		$args = array(
			'guid'			=> $url,
			'post_mime_type'	=> $filetype,
			'post_title'		=> basename($url),
			'post_content'		=> "",
			'post_status'		=> 'inherit'
		);

		//if ($this->debug > 0)
		{
			$this->error_log(__METHOD__."; attachment args: ".print_r($args,true));
		}

		$id = wp_insert_attachment ($args, $path, $prodID);
		if ($id == "") {
			$msg = sprintf(__METHOD__."; product attachment insert failed;");
			$this->error_log(__METHOD__."; ".$msg);
			$rc = sqss_error::errImageAttachFail;
			goto out;
		}

		// Generate the metadata for the attachment, and update the database record.
		$data = wp_generate_attachment_metadata ($id, $path);

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; filepath : ".$path);
			$this->error_log(__METHOD__."; attach id: ".$id);
		}

		$result = update_post_meta($id, '_wp_attachment_metadata', $data);

		if ($result == 0) {
			$msg = sprintf(__METHOD__."; post_meta update failed;");
			return 999;
		}

		$attach_id = $id;

out:

		$this->error_log(__METHOD__."; attach id: ".$attach_id);

		return $rc;
	}

	//----------------------------------------------------------------------------
	// delete the image and all related db information
	//----------------------------------------------------------------------------
	public function delete_file_and_detach($url, $mediaID) {

		$rc = sqss_error::errSuccess;
/*	
		$rc = $this->delete_file($url, $path);
		if ($rc != sqss_error::errSuccess) {
			$msg = sprintf(__METHOD__."; add file failed;");
			return 999;
		}

		$filetype = mime_content_type ($path);

		// Prepare an array of post data for the attachment.
		$args = array(
			'guid'			=> $url,
			'post_mime_type'	=> $filetype,
			'post_title'		=> basename($url),
			'post_content'		=> "",
			'post_status'		=> 'inherit'
		);
*/

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; mediaID: ".$mediaID."; url: ".$url);
		}

		$rc = wp_delete_attachment( $mediaID, TRUE );
		if (!$rc) {
			$msg = sprintf(__METHOD__."; attachment delete failed;");
			$this->error_log(__METHOD__."; ".$msg);
			$rc = sqss_error::errImageDetachFail;
			goto out;
		}

out:

		return $rc;
	}

	//----------------------------------------------------------------------------
	// add the image 
	//----------------------------------------------------------------------------
	public function add_file($url, $imagedata, &$path) {

		$rc = sqss_error::errSuccess;
	
		$this->uploaddir = wp_upload_dir();

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; uploaddir: ".print_r($this->uploaddir,true));
			$this->error_log(__METHOD__."; url: ".$url);
		}

		$urlpath = parse_url($url, PHP_URL_PATH);
		$filename = basename($urlpath);
		$path = $this->uploaddir['path']."/".$filename;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; image file path: ".$path);
		}

		//
		// convert image string to a jpeg file
		//
		$this->base64_to_jpeg($imagedata, $path);

out:

		return $rc;
	}

	//----------------------------------------------------------------------------
	// replace the image file
	//----------------------------------------------------------------------------
	public function replace_file($fileurl, $filename, &$newurl, &$attach_id)
	{
		//
		// move the uploaded file from $uploaddir/tmpdir into $uploaddir/sqss/[uuid]/
		//

		$prodID = $this->req['id'];

		//$filetype = wp_check_filetype (basename($fileurl), null);
		$filetype = mime_content_type (basename($fileurl));
		$urlcomp = parse_url($fileurl);
		$filepath = $_SERVER['DOCUMENT_ROOT'].$urlcomp['path'];

		if ($this->debug > 0)
		{
			$this->error_log(__METHOD__."; filepath: ".basename($filepath));
		}
 
		$tmpdir = $this->uploaddir['path']."/tmpdir";
		$srcfile = $tmpdir.'/'.$filename;
		$parts = pathinfo($filepath);

		$dstdir = $parts['dirname'];

		$pos = strpos($dstdir,'/sqss/');
		if (!$pos)
		{
			$dstdir = $dstdir.'/sqss';
			if ($this->uuid != "")
				$dstdir = $dstdir.'/'.$this->uuid;
		}
		$dstfile = $dstdir.'/'.$filename;

		$this->error_log(__METHOD__."; moving file from: ".$srcfile." to: ".$dstfile);

		if (!file_exists($dstdir))
			mkdir($dstdir, 0777, true);

		if (rename($srcfile, $dstfile) != true)
		{
			$this->msg = sprintf(__METHOD__."; %s move failed;",$dstfile);
			return 0;
		}

		$relpath = _wp_relative_upload_path($dstfile);

		$dsturl = $this->uploaddir['baseurl'].'/'.$relpath;

		$sizes = array();
		$this->get_thumbnail_sizes($sizes);
	
		foreach ($sizes as $key => $size)
		{	
			if ($key == 'thumbnail' || $key == 'medium' || $key == 'large')
				$this->resize_image($dstfile, $size[0], $size[1]);
			else
				$this->resize_image($dstfile, $size[0], 0);
		}

		// get the product attachments

		//$result = $this->get_product_image_attachment( $prodID, $attach_ID, $dstfile );
		$result = get_image_attachment( $dstfile, $attach_ID );
		if ($result == 0)
		{
			return 0;
		}

		$newurl = $dsturl;

		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// revert the image file to its original
	//----------------------------------------------------------------------------
	public function revert_file($fileurl)
	{
		//
		// update the file attachment to reflect image file reversion to original
		//

		$prodID = $this->req['id'];

		//$filetype = wp_check_filetype (basename($fileurl), null);
		$filetype = mime_content_type (basename($fileurl));
		$urlcomp = parse_url($fileurl);
		$filepath = $_SERVER['DOCUMENT_ROOT'].$urlcomp['path'];

		if ($this->debug > 0)
		{
			$this->error_log(__METHOD__."; filepath: ".basename($filepath));
		}

		$parts = pathinfo($filepath);
		$dstdir = $parts['dirname'];
		$origfile = $dstdir.'/'.basename($filepath);

		$dstdir = $dstdir.'/sqss';
		if ($this->uuid != "")
			$dstdir = $dstdir.'/'.$this->uuid;
		$dstfile = $dstdir.'/'.basename($filepath);

		// get the product attachments

		$result = $this->get_product_image_attachment( $prodID, $attach_ID, $dstfile );
		if ($result != 0)
		{
			return sqss_error::errSuccess;
		}
/*
		$args = array(
			'posts_per_page'	=> -1,
			'post_mime_type'	=> 'image',
			'post_type'		=> 'attachment',
			'post_parent'		=> $prodID
		);

		$attachments = array();
		$attachments = get_posts($args);

		foreach ($attachments as $attachment)
		{
			// we only care about the attached image file 
			//if ($attachment->post_parent == $prodID)
			{
				$sql = "SELECT meta_value "
					. "FROM $wpdb->postmeta "
					. "WHERE post_id = '$attachment->ID' "
					. 	"AND meta_key = '_wp_attached_file'";
				$val = $wpdb->get_results($sql);
				if ($wpdb->last_error)
				{
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					return sqss_error::errGetMetaFail;
 				}
				$result = json_decode(json_encode($val[0]), true);
				if ($result == "")
				{
					$msg = sprintf(__METHOD__."; post_meta get failed; status: (empty value)");
					return 0;
				}

				// if attachment value matches new file name then update
				if ($result != _wp_relative_upload_path($dstfile))
					continue;

				//$result = update_post_meta( $attachment->ID, '_wp_attached_file', _wp_relative_upload_path($origfile) );
				$uploadpath = _wp_relative_upload_path($origfile);

				$sql = "UPDATE $wpdb->postmeta "
					. "SET meta_value='$uploadpath' "
					. "WHERE post_id = '$attachment->ID' "
					. "	AND meta_key = '_wp_attachment_file'";

				$result = $wpdb->get_results($sql);
				if ($wpdb->last_error)
				{
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					return sqss_error::errVariationUpdateFail;
 				}
				if ($resul) == 0)
				{
					$msg = sprintf(__METHOD__."; post_meta update failed; status: ".$result);
					return 0;
				}

				// Generate the metadata for the attachment, and update the database record.
				$data = wp_generate_attachment_metadata ($attachment->ID, $origfile);

				//$result = get_post_meta( $attachment->ID, '_wp_attachment_metadata', true );
				$sql = "SELECT meta_value "
					. "FROM $wpdb->postmeta "
					. "WHERE post_id = '$attachment->ID' "
					. 	"AND meta_key = '_wp_attached_metadata'";
				$val = $wpdb->get_results($sql);
				if ($wpdb->last_error)
				{
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					return sqss_error::errGetMetaFail;
 				}
				$result = json_decode(json_encode($val[0]), true);
				if ($result == "")
				{
					$msg = sprintf(__METHOD__."; post_meta get failed; status: (empty value)");
					return 0;
				}

				// if attachment value matches new data then skip update
				if ($result != $data)
				{
					//$result = update_post_meta( $attachment->ID, '_wp_attachment_metadata', $data );
					$sql = "UPDATE $wpdb->postmeta "
						. "SET meta_value='$data' "
						. "WHERE post_id = '$attachment->ID' "
						. "	AND meta_key = '_wp_attachment_metadata'";

					$result = $wpdb->get_results($sql);
					if ($wpdb->last_error)
					{
						$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
						return sqss_error::errVariationUpdateFail;
 					}
					if ($result == 0)
					{
						$msg = sprintf(__METHOD__."; post_meta update failed;");
						return 0;
					}
				}

				$attach_id = $attachment->ID;

				return sqss_error::errSuccess;
			}
		}
*/
		$msg = sprintf("revert_file: attachment for image: ".$fileurl." not found");

		return 0;
	}

	//----------------------------------------------------------------------------
	// delete the image file
	//----------------------------------------------------------------------------
	public function delete_file($fileurl)
	{
		//
		// delete the file attachment to reflect image file delete
		//

		$prodID = $this->req['id'];

		$filetype = mime_file_type (basename($fileurl));
		$urlcomp = parse_url($fileurl);
		$filepath = $_SERVER['DOCUMENT_ROOT'].$urlcomp['path'];

		if ($this->debug > 0)
		{
			$this->error_log(__METHOD__."; filepath: ".basename($filepath));
		}

		$parts = pathinfo($filepath);
		$dstdir = $parts['dirname'];
		$origfile = $dstdir.'/'.basename($filepath);

		$dstdir = $dstdir.'/sqss';
		if ($this->uuid != "")
			$dstdir = $dstdir.'/'.$this->uuid;
		$dstfile = $dstdir.'/'.basename($filepath);

		// get the product attachments

		$result = $this->get_product_image_attachment( $prodID, $attach_ID, $dstfile );
		if ($result != 0)
		{
			return sqss_error::errSuccess;
		}
/*
		$args = array(
			'posts_per_page'	=> -1,
			'post_mime_type'	=> 'image',
			'post_type'		=> 'attachment',
			'post_parent'		=> $prodID
		);

		$attachments = array();
		$attachments = get_posts($args);

		foreach ($attachments as $attachment)
		{
			// we only care about the attached image file 
			if ($attachment->post_parent == $prodID)
			{
				//$result = get_post_meta( $attachment->ID, '_wp_attached_file', true );
				$sql = "SELECT meta_value "
					. "FROM $wpdb->postmeta "
					. "WHERE post_id = '$attachment->ID' "
					. 	"AND meta_key = '_wp_attached_file'";
				$val = $wpdb->get_results($sql);
				if ($wpdb->last_error)
				{
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					return sqss_error::errGetMetaFail;
 				}
				$result = json_decode(json_encode($val[0]), true);
				if ($result == "")
				{
					$msg = sprintf(__METHOD__."; post_meta get failed; status: (empty value)");
					return 0;
				}

				// if attachment value matches new file name then update
				if ($result != _wp_relative_upload_path($dstfile))
					continue;

				$uploadpath = _wp_relative_upload_path($origfile);
				$sql = "UPDATE $wpdb->postmeta "
					. "SET meta_value='$uploadpath' "
					. "WHERE post_id = '$attachment->ID' "
					. "	AND meta_key = '_wp_attachment_file'";

				$result = $wpdb->get_results($sql);
				if ($wpdb->last_error)
				{
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					return sqss_error::errVariationUpdateFail;
 				}
				if ($resul) == 0)
				{
					$msg = sprintf(__METHOD__."; post_meta update failed; status: ".$result);
					return 0;
				}

				// Generate the metadata for the attachment, and update the database record.
				$data = wp_generate_attachment_metadata ($attachment->ID, $origfile);

				//$result = get_post_meta( $attachment->ID, '_wp_attachment_metadata', true );
				if ($result == "")
				{
					$msg = sprintf(__METHOD__."; post_meta get failed; status: (empty value)");
					return 0;
				}

				// if attachment value matches new data then skip update
				if ($result != $data)
				{
					$sql = "UPDATE $wpdb->postmeta "
						. "SET meta_value='$data' "
						. "WHERE post_id = '$attachment->ID' "
						. "	AND meta_key = '_wp_attachment_metadata'";

					$result = $wpdb->get_results($sql);
					if ($wpdb->last_error)
					{
						$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
						return sqss_error::errVariationUpdateFail;
 					}
					if ($result == 0)
					{
						$msg = sprintf(__METHOD__."; post_meta update failed;");
						return 0;
					}
				}

				$attach_id = $attachment->ID;

				return sqss_error::errSuccess;
			}
		}
*/
		$msg = sprintf(__METHOD__."; attachment for image: ".$fileurl." not found");

		return 0;
	}


	//----------------------------------------------------------------------------
	// make_image_featured - make the given image the product featured image
	//----------------------------------------------------------------------------
	public function make_image_featured ($prodID, $attachID) {
		global $wpdb;

		//
		// check that this is the featured image and if not, make it
		//

		$sql = "SELECT meta_id, meta_key, meta_value "
			. "FROM $wpdb->postmeta "
			. "WHERE post_id = '$prodID' "
			.       "AND meta_key = '_thumbnail_id'";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errGetMetaFail;
		}
		$thumbrec = json_decode(json_encode($res), true);

error_log("make_image_featured; prodID: ".$prodID."; attachID = ".$attachID);
error_log("make_image_featured; thumbrec = ".print_r($thumbrec,true));
		if (!empty($thumbrec)) {
			$thumbnail_id = $thumbrec['meta_value'];
error_log("make_image_featured; thumbnail_id = ".$thumbnail_id);

			if ($thumbnail_id != $attachID) {
				//
				// not the thumbnail. so, make it the thumbnail
				//
				if ($this->debug > 0) {
					$this->error_log(__METHOD__."; new featured image file; id: ".$attachID."; file: ".basename($url));
				}

				$sql = "UPDATE $wpdb->postmeta "
					. "SET meta_value = '$attachID' "
					. "WHERE post_id = '$prodID' "
					. "	AND meta_key = '_thumbnail_id'";

				$result = $wpdb->get_results($sql);
				if ($wpdb->last_error) {
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					return sqss_error::errProductUpdateFail;
				}

				if ($result == 0) {
					$this->error_log(__METHOD__."; post_meta update failed; thumbnail_id: ".$attachID);
					return sqss_error::errProductUpdateFail;
				}
			}
		} else {
                        $sql = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) "
                                . "VALUES ('$prodID', '_thumbnail_id', '$attachID' )";

			$result = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; insert error: ".$wpdb->last_error);
				return sqss_error::errProductUpdateFail;
			}

			if ($result == 0) {
				$this->error_log(__METHOD__."; post_meta insert failed; thumbnail_id: ".$attachID);
				return sqss_error::errProductUpdateFail;
			}
		}
	}

	//----------------------------------------------------------------------------
	// image url - get the original image url
	//----------------------------------------------------------------------------
	public function get_original_fileurl($fileurl) {
		$retUrl = "";

		$pos = strpos($fileurl,'/sqss/');
		if ($pos) {
			$parts = pathinfo($fileurl);
			$retUrl = substr($fileurl, 0, $pos).'/'.$parts['filename'].'.'.$parts['extension'];	
		} else {
			$retUrl = $fileurl;
		}

		return $retUrl;
	}

	//----------------------------------------------------------------------------
	// image sizes 
	//----------------------------------------------------------------------------
	public function get_thumbnail_sizes(&$sizes) {
		global $_wp_additional_image_sizes;

		foreach (get_intermediate_image_sizes() as $s) {
 			$sizes[$s] = array(0, 0);
 			if (in_array ($s, array('thumbnail', 'medium', 'large'))) {
 				$sizes[$s][0] = get_option($s . '_size_w');
 				$sizes[$s][1] = get_option($s . '_size_h');
 			} else {
 				if (isset ($_wp_additional_image_sizes)
					&& isset ($_wp_additional_image_sizes[$s]))
 					$sizes[$s] = array ($_wp_additional_image_sizes[$s]['width'], $_wp_additional_image_sizes[$s]['height'],);
 			}
 		}
	}

	//----------------------------------------------------------------------------
	// image resize 
	//----------------------------------------------------------------------------
	public function resize_image($file, $width, $height) {
		$image_properties = getimagesize($file);
		$image_width = $image_properties[0];
		$image_height = $image_properties[1];
		$image_ratio = $image_width / $image_height;
		$type = $image_properties["mime"];

		if (!$width && !$height) {
			$width = $image_width;
			$height = $image_height;
		}

		if (!$width) {
			$width = intval($height * $image_ratio);
		}
		if (!$height) {
			$height = intval($width / $image_ratio);
		}

		if ($type == "image/jpeg") {
			$thumb = imagecreatefromjpeg($file);
		} else if ($type == "image/png") {
			$thumb = imagecreatefrompng($file);
		} else {
			return false;
		}

		$parts = pathinfo($file);
		$target = $parts['dirname'].'/'.$parts['filename'].'-'.$width.'x'.$height.'.'.$parts['extension'];

		$temp_image = imagecreatetruecolor($width, $height);
		imagecopyresampled($temp_image, $thumb, 0, 0, 0, 0, $width, $height, $image_width, $image_height);
		$thumbnail = imagecreatetruecolor($width, $height);
		imagecopyresampled($thumbnail, $temp_image, 0, 0, 0, 0, $width, $height, $width, $height);

		if ($type == "image/jpeg") {
			imagejpeg($thumbnail, $target);
		} else {
			imagepng($thumbnail, $target);
		}

		imagedestroy($temp_image);
		imagedestroy($thumbnail);
	}

	//----------------------------------------------------------------------------
	// media upload 
	//----------------------------------------------------------------------------
	public function media_upload(&$image) {

		$action = $image['action'];

		if (array_key_exists('type', $image)) {
			$type = $image['type'];
		}
		if (array_key_exists('url', $image)) {
			$url = $image['url'];
		}
		if (array_key_exists('imagedata', $image)) {
			$imagedata = $image['imagedata'];
			$image['imagedata'] = "";
		}

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; url: ".$url."; action: ".$action);
		}

		// add image to the database and attach to the product
		$this->errCode = $this->add_file_and_attach($url, "0", $imagedata, $attach_id);
		if ($this->errCode != sqss_error::errSuccess) {
			$rc = $this->errCode;
			goto out;
		}

		$image['id'] = $attach_id;

		if ($this->debug > 0) {
			$this->error_log(__METHOD__."; updated upload directory contents: ");
			$extensions = array('jpg','png');
			$files = $this->getDirectoryTree($this->uploaddir['path'],$extensions); 
			foreach ($files as $file) {	
				$this->error_log(__METHOD__."; image file: ".$file);
			}
		}

out:
		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// media delete 
	//----------------------------------------------------------------------------
	public function media_delete($media) {

		if (array_key_exists('id', $media)) {
			$id = $media['id'];
		}
		if (array_key_exists('url', $media)) {
			$url = $media['url'];
		}

		//if ($this->debug > 0) {
			$this->error_log(__METHOD__."; media: ".print_r($media,true));
		//}

		// delete image from the database
		$this->errCode = $this->delete_file_and_detach($url, $id);
		if ($this->errCode != sqss_error::errSuccess) {
			$rc = $this->errCode;
			goto out;
		}

		if ($this->debug > 1) {
			$this->error_log(__METHOD__."; updated upload directory contents: ");
			$extensions = array('jpg','png');
			$files = $this->getDirectoryTree($this->uploaddir['path'],$extensions); 
			foreach ($files as $file) {	
				$this->error_log(__METHOD__."; image file: ".$file);
			}
		}

out:
		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// upload_archive_and_unzip 
	//----------------------------------------------------------------------------
	public function upload_archive_and_unzip($args) {
		//
		// upload the file archive and extract contents into $uploaddir/tmpdir
		//
		if ($this->debug > 0) {
			$fp = fopen("/tmp/spimage_trace.txt", "a"); //creates a file to trace your data
			fwrite($fp,"upload_dir \n");
			fwrite($fp, print_r($this->uploaddir, true));
			fwrite($fp,"GET \n");
			fwrite($fp, print_r($_GET, true));
			fwrite($fp,"POST \n");
			fwrite($fp, print_r($_POST, true));//displays the POST
			fwrite($fp,"FILES \n");
			fwrite($fp,print_r($_FILES,true));//display the FILES
			fclose($fp);
		}

		$path = $_POST['path'];
		$file = $_FILES['file']['name'];
		$tmpname = $_FILES['file']['tmp_name'];
		$filetype = $_FILES['file']['type'];
		$target = $this->uploaddir['path']."/".$file;

		$rc = 1;

		//----------------------------------------------------------------------------
		// remove existing/older version of the image file
		//----------------------------------------------------------------------------
		if (file_exists($target)) {
			unlink($target);
		}

		//----------------------------------------------------------------------------
		// copy file to target location
		//----------------------------------------------------------------------------
		$result = 0;	
		if ($result == 0) {
			$this->msg = __METHOD__."; Image file: $file; upload to: $target failed: ".$_FILES['file']['error'];
			$this->stat = "fail";
			$rc = 0;
			goto out;
		}

		//----------------------------------------------------------------------------
		// change file permissions
		//----------------------------------------------------------------------------
		chmod($target, 0766);

		$this->error_log(__METHOD__."; The file: ". $file. " of type: ". $filetype. " has been uploaded to: ". $target);

		//----------------------------------------------------------------------------
		// unzip archive 
		//----------------------------------------------------------------------------
		$zip = new ZipArchive;
		$tmpdir = $this->uploaddir['path']."/tmpdir";

		if ($zip->open($target) === TRUE) {
			$zip->extractTo($tmpdir);
			$zip->close();
		} else {
			$this->msg = __METHOD__."; Zip archive open failed: $target";
			$stat = "fail";
			$rc = 0;
			goto out;
		}
out:
		return sqss_error::errSuccess;
	}

	//----------------------------------------------------------------------------
	// get image directory contents 
	//----------------------------------------------------------------------------
	public function getDirectoryTree ($outerDir , $x) {
		$dirs = array_diff (scandir($outerDir), Array( ".", ".." ));
		$filenames = Array();

		foreach ($dirs as $d) {
			if (is_dir($outerDir."/".$d) ) {
				$filenames[$d] = getDirectoryTree ($outerDir."/".$d , $x);
			} else {
				foreach ($x as $y) {
					if (($y)?ereg($y.'$',$d):1)
						$filenames[$d] = $d;
				}
			}
		}
		return $filenames;
	}

	//----------------------------------------------------------------------------
	// get product attachment 
	//----------------------------------------------------------------------------
	public function get_product_image_attachment ($prodID, &$attach_id, $name) {
		global $wpdb;

		$rc = sqss_error::errImageAttachFail; 

error_log("gpia: prodID: ".$prodID);
		$attachments = array();

		$sql = "SELECT ID, post_title, post_type, post_name, post_excerpt, post_modified "
			. "FROM $wpdb->posts "
			. "WHERE post_parent = '$prodID' "
			. 	"AND post_type = 'attachment' "
			.	"AND post_mime_type = 'image'";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errProductQueryFail;
 		}
		$attachments = json_decode(json_encode($res[0]), true);

error_log("gpia: name: ".$name);
		foreach ($attachments as $attachment) {
			// we only care about the attached image file 

error_log("gpia: attachment->id: ".$attachment->ID);
			if ($attachment->post_parent == $prodID) {
				$sql = "SELECT meta_value "
					. "FROM $wpdb->postmeta "
					. "WHERE post_id = '$attachment->ID' "
					. 	"AND meta_key = '_wp_attached_file'";
				$val = $wpdb->get_results($sql);
				if ($wpdb->last_error) {
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					return sqss_error::errGetMetaFail;
 				}
				$result = json_decode(json_encode($val[0]), true);
				if ($result == "") {
					$msg = sprintf(__METHOD__.": post_meta get failed; status: (empty value)");
					return $rc;
				}

error_log("gpia: attachment: ".$result);
//error_log("gpia: relative name: "._wp_relative_upload_path($name));
				// if attachment value matches new file name then skip update
				if ($result != _wp_relative_upload_path($name)) {
					//$result = update_post_meta( $attachment->ID, '_wp_attached_file', _wp_relative_upload_path($name) );
					$uploadpath = _wp_relative_upload_path($name);
					$sql = "UPDATE $wpdb->postmeta "
						. "SET meta_value='$uploadpath' "
						. "WHERE post_id = '$id' "
						. "	AND meta_key = '_wp_attachment_file'";

					$result = $wpdb->get_results($sql);
					if ($wpdb->last_error) {
						$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
						return sqss_error::errVariationUpdateFail;
			 		}
					if ($result == 0) {
						$msg = sprintf(__METHOD__."; post_meta update failed; status: ".$result);
						return $rc;
					}
				}

				// Generate the metadata for the attachment, and update the database record.
				$data = wp_generate_attachment_metadata ($attachment->ID, $name);

				$sql = "SELECT meta_value "
					. "FROM $wpdb->postmeta "
					. "WHERE post_id = '$attachment->ID' "
					. 	"AND meta_key = '_wp_attached_metadata'";
				$val = $wpdb->get_results($sql);
				if ($wpdb->last_error) {
					$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
					return sqss_error::errGetMetaFail;
 				}
				$result = json_decode(json_encode($val[0]), true);
				if ($result == "") {
					$msg = sprintf(__METHOD__."; post_meta get failed; status: (empty value)");
					return $rc;
				}

				// if attachment value matches new data then skip update
				if ($result != $data) {
					//$result = update_post_meta( $attachment->ID, '_wp_attachment_metadata', $data );
					$sql = "UPDATE $wpdb->postmeta "
						. "SET meta_value='$data' "
						. "WHERE post_id = '$id' "
						. "	AND meta_key = '_wp_attachment_metadata'";

					$result = $wpdb->get_results($sql);
					if ($wpdb->last_error) {
						$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
						return sqss_error::errVariationUpdateFail;
			 		}
					if ($result == 0) {
						$msg = sprintf(__METHOD__."; post_meta update failed;");
						return $rc;
					}
				}

				$attach_id = $attachment->ID;
error_log("gpia: attach_id: ".$attach_id);

				return sqss_error::errSuccess;
			}
		}
	}

	//----------------------------------------------------------------------------
	// get attachment 
	//----------------------------------------------------------------------------
	public function get_image_attachment ($url, &$attach_id) {
		global $wpdb;

		$rc = sqss_error::errGetAttachmentFail; 

		$attachments = array();

		$sql = "SELECT ID, post_title, post_type, post_name, post_excerpt, post_modified "
			. "FROM $wpdb->posts "
			. "WHERE post_type = 'attachment' "
			.	"AND post_mime_type = 'image/jpeg' "
			.		"OR post_mime_type = 'image/png' "
			.		"OR post_mime_type = 'image/gif'";
		$res = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			return sqss_error::errProductQueryFail;
 		}
		$attachments = json_decode(json_encode($res, true));

		foreach ($attachments as $attachment) {
			$sql = "SELECT meta_value "
				. "FROM $wpdb->postmeta "
				. "WHERE post_id = '$attachment->ID' "
				. 	"AND meta_key = '_wp_attached_file'";
			$val = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
				return sqss_error::errGetMetaFail;
 			}
			$result = json_decode(json_encode($val[0]), true);
			if ($result == "") {
				$msg = sprintf(__METHOD__."; post_meta get failed; status: (empty value)");
				return $rc;
			}

			$attachUrl = wp_get_attachment_url($attachment->ID);
error_log("gia: attachment: ".$result);
error_log("gia: attachUrl: ".$attachUrl);
			if ($url == $attachUrl) {
				// found it
				$attach_id = $attachment->ID;
				return sqss_error::errSuccess;
			}
		}

		return $rc;
	}

	//----------------------------------------------------------------------------
	// attach image to product
	//----------------------------------------------------------------------------
	public function put_product_image_attachment ($prodID, &$attach_id, $url) {
		global $wpdb;

		$rc = sqss_error::errSuccess; 

error_log("ppia: prodID: ".$prodID);
		$urlpath = parse_url($url, PHP_URL_PATH);
		$filename = basename($urlpath);
		$file = $this->uploaddir['path']."/".$filename;

error_log("ppia: url: ".$url);
error_log("ppia: urlpath: ".$urlpath);
error_log("ppia: file: ".$file);

		$sql = "SELECT meta_value "
			. "FROM $wpdb->postmeta "
			. "WHERE post_id = '$prodID' "
			. 	"AND meta_key = '_wp_attached_file'";
		$val = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			$rc = sqss_error::errGetAttachmentFail; 
			goto out;
 		}
		$result = json_decode(json_encode($val[0]), true);
		if ($result == "") {
			$msg = sprintf(__METHOD__."; post_meta get failed; status: (empty value)");
			$rc = sqss_error::errGetAttachmentFail; 
			goto out;
		}

		// if attachment value matches new file name then update
		if ($result != _wp_relative_upload_path($file)) {
			goto out;
		}

		//$result = update_post_meta( $attachment->ID, '_wp_attached_file', _wp_relative_upload_path($origfile) );
		$uploadpath = _wp_relative_upload_path($file);

		$sql = "UPDATE $wpdb->postmeta "
			. "SET meta_value='$uploadpath' "
			. "WHERE post_id = '$prodID' "
			. "	AND meta_key = '_wp_attachment_file'";

		$result = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			$rc = sqss_error::errPutAttachmentFail; 
			goto out;
 		}
		if ($result == 0) {
			$msg = sprintf(__METHOD__."; post_meta update failed; status: ".$result);
			$rc = sqss_error::errPutAttachmentFail; 
			goto out;
		}

		// Generate the metadata for the attachment, and update the database record.
		$data = wp_generate_attachment_metadata ($prodID, $file);

		//$result = get_post_meta( $attachment->ID, '_wp_attachment_metadata', true );
		$sql = "SELECT meta_value "
			. "FROM $wpdb->postmeta "
			. "WHERE post_id = '$prodID' "
			. 	"AND meta_key = '_wp_attached_metadata'";
		$val = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
			$rc = sqss_error::errPutAttachmentFail; 
			goto out;
 		}
		$result = json_decode(json_encode($val[0]), true);
		if ($result == "") {
			$msg = sprintf(__METHOD__."; post_meta get failed; status: (empty value)");
			$rc = sqss_error::errPutAttachmentFail; 
			goto out;
		}

		// if attachment value matches new data then skip update
		if ($result != $data) {
			//$result = update_post_meta( $attachment->ID, '_wp_attachment_metadata', $data );
			$sql = "UPDATE $wpdb->postmeta "
				. "SET meta_value='$data' "
				. "WHERE post_id = '$prodID' "
				. "	AND meta_key = '_wp_attachment_metadata'";

			$result = $wpdb->get_results($sql);
			if ($wpdb->last_error) {
				$this->error_log(__METHOD__."; query error: ".$wpdb->last_error);
				$rc = sqss_error::errPutAttachmentFail; 
				goto out;
 			}
			if ($result == 0) {
				$msg = sprintf(__METHOD__."; post_meta update failed;");
				$rc = sqss_error::errPutAttachmentFail; 
				goto out;
			}
		}

out:

		return $rc;
	}

	//----------------------------------------------------------------------------
	// recursive remove directory 
	//----------------------------------------------------------------------------
	public function rrmdir($dir) {
		foreach (glob($dir . '/*') as $file) {
			if (is_dir($file))
				rrmdir($file);
			else
				unlink($file);
		}
		rmdir($dir);
	}

}

?>
