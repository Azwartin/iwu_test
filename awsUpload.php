<?php

function upload_file_to_s3( $post_id, $data = null, $file_path = null, $force_new_s3_client = false, $remove_local_files = true ) {
	$return_metadata = get_return_metadata( $data );
	$data = get_data( $post_id, $data );
	if ( is_wp_error( $data ) ) {
		return $data;
    }

    $err = apply_pre_upload_attachment_filters( $post_id, $data, $return_metadata );
    if ( ! is_null( $err ) ) {
        return $err;
    }

    list( $file_path, $type, $err ) = get_file_path( $post_id, $file_path, $return_metadata );
    if ( ! is_null( $err ) ) {
        return $err;
    }

	$file_name = basename( $file_path );
	list( $s3object, $err ) = put_to_s3([
		'post_id' => $post_id,
		'data' => $data,
		'file_path' => $file_path,
		'file_name' => $file_name,
		'type' => $type,
	], $force_new_s3_client, $return_metadata);

	if ( ! is_null( $err ) ) {
        return $err;
	}
	
	replace_post_meta_s3( $post_id, $s3object );
	$filesize_total = remove_post_local_files( $post_id, $data, $file_path, $return_metadata );
	update_post_meta_total_filesize( $post_id, $data, $filesize_total, $return_metadata );

	if ( ! is_null( $return_metadata ) ) {
		// If the attachment metadata is supplied, return it
		return $return_metadata;
	}
	return $s3object;
}


/**
 * @return mixed data from params or wp if not set
 */
function get_data($post_id, $data) {
	if ( is_null( $data ) ) {
		$data = wp_get_attachment_metadata( $post_id, true );
	}
    
    return $data;
}

/**
 * @return mixed metadata from params
 */
function get_return_metadata($data) {
    return is_null( $data ) ? null : $data;
}

/**
 * apply pre upload filters
 * if something returned - cancel upload, return result
 * @return mixed result
 */
function apply_pre_upload_attachment_filters($post_id, $data, $return_metadata) {
    // Allow S3 upload to be hijacked / cancelled for any reason
    $pre = apply_filters( 'as3cf_pre_upload_attachment', false, $post_id, $data );
    if ( false !== $pre ) {
        if ( ! is_null( $return_metadata ) ) {
            // If the attachment metadata is supplied, return it
            return $data;
        }
        $error_msg = is_string( $pre ) ? $pre : __( 'Upload aborted by filter \'as3cf_pre_upload_attachment\'', 'amazon-s3-and-cloudfront' );
        return $this->return_upload_error( $error_msg );
    }

    return null;
}
/** 
 * check file_path for post
 * @return array of [file_path, file_type, error]
*/
function get_file_path($post_id, $file_path, $return_metadata) {
	if ( is_null( $file_path ) ) {
		$file_path = get_attached_file( $post_id, true );
	}
	// Check file exists locally before attempting upload
	if ( ! file_exists( $file_path ) ) {
		$error_msg = sprintf( __( 'File %s does not exist', 'amazon-s3-and-cloudfront' ), $file_path );
		return [null, $this->return_upload_error( $error_msg, $return_metadata )];
	}
	$type          = get_post_mime_type( $post_id );
	$allowed_types = $this->get_allowed_mime_types();
	// check mime type of file is in allowed S3 mime types
	if ( ! in_array( $type, $allowed_types ) ) {
		$error_msg = sprintf( __( 'Mime type %s is not allowed', 'amazon-s3-and-cloudfront' ), $type );
		return [null, $this->return_upload_error( $error_msg, $return_metadata )];
    }
    
    return [$file_path, $type, null];
}

/**
 * get time for prefix based on post data
 * @return mixed
*/
function get_folder_time($post_id, $data) {
    // derive prefix from various settings
    if ( isset( $data['file'] ) ) {
        return $this->get_folder_time_from_url( $data['file'] );
    } else {
        $time = $this->get_attachment_folder_time( $post_id );
        return date( 'Y/m', $time );
    }
}

/**
 * @return array of [$acl, $region, $bucket, $prefix] acl data from old s3object
*/
function get_acl_with_s3object_params_from_old_object($old_s3object) {
    // use existing non default ACL if attachment already exists
    if ( isset( $old_s3object['acl'] ) ) {
        $acl = $old_s3object['acl'];
    }
    // use existing prefix
    $prefix = dirname( $old_s3object['key'] );
    $prefix = ( '.' === $prefix ) ? '' : $prefix . '/';
    // use existing bucket
    $bucket = $old_s3object['bucket'];
    // get existing region
    if ( isset( $old_s3object['region'] ) ) {
        $region = $old_s3object['region'];
    };
    
    return [$acl, $region, $bucket, $prefix];
}

/**
 * @return array of [$acl, $region, $bucket, $prefix] acl data for new object
*/
function get_acl_with_s3object_params_for_new_object($post_id, $data) {
    $acl = self::DEFAULT_ACL;
    $time = get_folder_time($post_id, $data);
    $prefix = $this->get_file_prefix( $time );
    // use bucket from settings
    $bucket = $this->get_setting( 'bucket' );
    $region = $this->get_setting( 'region' );
    if ( is_wp_error( $region ) ) {
        $region = '';
    }

    return [$acl, $region, $bucket, $prefix];
}

/**
 * @return array of [$acl, $region, $bucket, $prefix] acl data from post
*/
function get_acl_with_s3object_params($post_id, $data) {
	// check the attachment already exists in S3, eg. edit or restore image
	if ( ( $old_s3object = $this->get_attachment_s3_info( $post_id ) ) ) {
		list($acl, $region, $bucket, $prefix) = get_acl_with_s3object_params_from_old_object($old_s3object);
	} else {
		list($acl, $region, $bucket, $prefix) = get_acl_with_s3object_params_for_new_object($post_id, $data);
    }
    
    $acl = apply_filters( 'as3cf_upload_acl', $acl, $data, $post_id );
    return [$acl, $region, $bucket, $prefix];
}

function create_s3object($params) {
    $s3object = $params;
	// store acl if not default
	if ( $params['acl'] == self::DEFAULT_ACL ) {
		unset($s3object['acl']);
    }
    
    return $s3object;
}

function get_s3_upload_args($post_id, $args) {
	$args = $args + [
		'CacheControl' => 'max-age=31536000',
		'Expires'      => date( 'D, d M Y H:i:s O', time() + 31536000 ),
	];
	$args = apply_filters( 'as3cf_object_meta', $args, $post_id );
}

function replace_post_meta_s3($post_id, $s3object) {
	delete_post_meta( $post_id, 'amazonS3_info' );
    add_post_meta( $post_id, 'amazonS3_info', $s3object );
}

/**
 * Updates meta filesize data
 * */
function update_post_meta_filesize($file_path, $bytes) {
	if ( false !== $bytes ) {
		// Store in the attachment meta data for use by WP
		$data['filesize'] = $bytes;
		// Update metadata with filesize
		update_post_meta( $post_id, '_wp_attachment_metadata', $data );
		return bytes;
	}
}

function update_post_meta_total_filesize($post_id, $data, $filesize_total, $return_metadata) {
	// Store the file size in the attachment meta if we are removing local file
	if ( $filesize_total > 0 ) {
		// Add the total file size for all image sizes
		update_post_meta( $post_id, 'wpos3_filesize_total', $filesize_total );
	} else {
		if ( isset( $data['filesize'] ) ) {
			// Make sure we don't have a cached file sizes in the meta
			unset( $data['filesize'] );
			if ( is_null( $return_metadata ) ) {
				// Remove the filesize from the metadata
				update_post_meta( $post_id, '_wp_attachment_metadata', $data );
			}
			delete_post_meta( $post_id, 'wpos3_filesize_total' );
		}
	}
}

/**
 * @return int - total of deleted file sizes
*/
function remove_post_local_files($post_id, $data, $file_path, $return_metadata) {
	$files_to_remove = array();
	if ( file_exists( $file_path ) ) {
		$files_to_remove[$file_path] = 1;
	}

	$filesize_total             = 0;
	$remove_local_files_setting = $this->get_setting( 'remove-local-file' );
	if ( $remove_local_files_setting && is_null( $return_metadata )) {
		$bytes = filesize( $file_path );
		update_post_meta_filesize( $file_path, $bytes );
		// Add to the file size total
		$filesize_total += (int) $bytes;
	}
	
    $file_paths = $this->get_attachment_file_paths( $post_id, true, $data );
	foreach ( $file_paths as $file_path ) {
		if ( ! isset( $files_to_remove[$file_path] ) ) {
			$files_to_remove[$file_path] = 1;
			if ( $remove_local_files_setting ) {
				// Record the file size for the additional image
				$bytes = filesize( $file_path );
				$filesize_total += (int) $bytes;
			}
		}
    }
	
	if ( $remove_local_files && $remove_local_files_setting ) {
		filter_and_remove_local_files($post_id, $file_path, $files_to_remove);
	}

	return $remove_local_files_setting ? $filesize_total : 0;
}

function filter_and_remove_local_files($post_id, $file_path, $files_to_remove ) {
	$files_to_remove = array_keys($files_to_remove);
	// Allow other functions to remove files after they have processed
	$files_to_remove = apply_filters( 'as3cf_upload_attachment_local_files_to_remove', $files_to_remove, $post_id, $file_path );
	// Remove duplicates
	$files_to_remove = array_unique( $files_to_remove );
	// Delete the files
	$this->remove_local_files( $files_to_remove );
}

/**
 * Puts file to s3
 * @param $data - assoc array of [
 * 		file_path => ...,
 * 		file_name => ...,
 * 		type => ...,
 * 		post_id => ...,
 * 		data => ...,
 * ]
 * @return array [s3object, err]
*/
function put_to_s3($data, $force_new_s3_client, $return_metadata) {
	try {
		$file_path = $data['file_path'];
		$file_name = $data['file_name'];
		$type = $data['type'];
		$post_id = $data['post_id'];
		$data = $data['data'];
	} catch (\Exception $e) {
		$error_msg = sprintf( __( 'Invalid arguments: %s', 'amazon-s3-and-cloudfront' ), $e->getMessage() );
		return [null, $this->return_upload_error( $e->getMessage(), $return_metadata )];
	}

	list( $acl, $region, $bucket, $prefix ) = get_acl_with_s3object_params( $post_id, $data );

    $s3object = create_s3object( array(
            'acl'    => $acl,
            'bucket' => $bucket,
            'key'    => $prefix . $file_name,
            'region' => $region,
        ) 
    );

	$s3client = $this->get_s3client( $region, $force_new_s3_client );
	$args = get_s3_upload_args(
		array(
			'Bucket'       => $bucket,
			'Key'          => $prefix . $file_name,
			'SourceFile'   => $file_path,
			'ACL'          => $acl,
			'ContentType'  => $type,
		)
	);
	
	if ( file_exists( $file_path ) ) {
		try {
			$s3client->putObject( $args );
			return [$s3object, null];
		} catch ( Exception $e ) {
			$error_msg = sprintf( __( 'Error uploading %s to S3: %s', 'amazon-s3-and-cloudfront' ), $file_path, $e->getMessage() );
			return [null, $this->return_upload_error( $error_msg, $return_metadata )];
		}
	}
}
