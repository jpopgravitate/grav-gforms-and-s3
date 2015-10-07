<?php

/*
	* Plugin Name: Gravitatey Forms Uploads to S3
    * Version: 1.0
    * Author: Jpop @ Gravitate
	* Todo:
		- Pull constants from s3 offload plugin (bucket name)
		- Multiple uploads
		- Multiple forms
		- Settings page (maybe embed in Gforms settings)

*/

define( 'awsAccessKey', AWS_ACCESS_KEY_ID );  // shouldn't need to set this if s3 has already been configured
define( 'awsSecretKey', AWS_SECRET_ACCESS_KEY ); // ditto

define( 'BUCKET_NAME', 'uploads.maletis.com' ); // set this to your bucket name
define( 'GFORM_FORM_ID', '2' ); // set this to the ID of the form you want to hook up to s3
define( 'GFORM_UPLOAD_FIELD_ID', '80' ); // set this to the ID of the upload field


// workaround for duplicate filenames (for now)
// will only break if two people upload the same filename at the exact same millisecond
define( 'UPLOAD_PATH', 'wp-content/uploads/applications/' . md5(time()) . '/' );

// s3 class by Donovan Schönknecht
include_once('inc/S3.php');

// filters upload path so that entries in the admin panel show the correct URL
add_filter( 'gform_upload_path', 'grav_change_upload_path', 10, 3 );

// sends the uploaded file to s3
add_action( 'gform_after_submission_' . GFORM_FORM_ID, 'grav_submit_to_s3', 10, 2 );


function grav_submit_to_s3( $entry, $form ) {

	// no file?  no problem.
	if ( empty( $entry[GFORM_UPLOAD_FIELD_ID] ) ) return;

	$gfs3 = new S3( awsAccessKey, awsSecretKey );

	// url of uploaded file
	$file_url = $entry[GFORM_UPLOAD_FIELD_ID];

	// filename of uploaded file
	$file_name = $_FILES['input_' . GFORM_UPLOAD_FIELD_ID]['name'];

	// ensure bucket is there
	$gfs3->putBucket( BUCKET_NAME, S3::ACL_AUTHENTICATED_READ );

	// clean up filename, split into parts
	$url_parts = parse_url( $file_url );
	$full_path = $_SERVER['DOCUMENT_ROOT'] . substr($url_parts['path'], 1);
	if(is_dir($file_name)){ $file_name = basename($file_name); }

	// this is the full path to the file on S3
	$filename_to_s3 = UPLOAD_PATH . $file_name;

	if ( $gfs3->putObjectFile( $full_path, BUCKET_NAME, $filename_to_s3, S3::ACL_PUBLIC_READ ) ) {
	    return true; // upload success
	} else {
	    wp_die( 'It looks like something went wrong while uploading your file. Please try again in a few moments.' );
	}

}

function grav_change_upload_path( $path_info, $GFORM_form_id ) {

   $path_info['path'] = $_SERVER['DOCUMENT_ROOT'] . UPLOAD_PATH;
   $path_info['url'] = 'http://'. BUCKET_NAME . '/' . UPLOAD_PATH;
   return $path_info;

}