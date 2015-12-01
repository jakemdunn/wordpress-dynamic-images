<?php
/**
 *  Resizes an image and returns an array containing the resized URL, width, height and file type. Uses native Wordpress functionality.
 *  Loosely based on the script at // https://github.com/MatthewRuddy/Wordpress-Timthumb-alternative
 *	Images are saved to the cache directory in this plugin for easy removal
 * 
 *  @author Jacob Dunn (http://phenomblue.com)
 *  @return array A WordPress image editor properly sized and saved
 */

namespace WordPress\Plugins\DynamicImages;

add_action( 'delete_attachment', __NAMESPACE__.'Resize::deleteResized' );

class Resize{
	public static function getImage($url,$params)
	{
		// Set up our arguments
		extract( wp_parse_args( $params, array(
			'width'=>-1,
			'height'=>-1,
			'cropped'=>false,
			'offsetX'=>50,
			'offsetY'=>50
			)));

		// Options for this plugin
		extract(Options::getOptions());


		// Get the image file path
		global $blog_id;
		
		$path = parse_url( $url );
		$path = !is_multisite()
			? sprintf('/%s/%s',
				trim(ABSPATH,'/'),
				trim($path['path'],'/')
				)
			: sprintf('/%1$s/wp-content/blogs.dir/%2$s/files/%3$s',
				trim(ABSPATH,'/'),
				$blog_id,
				trim($path['path'],'/')
				);

		// Make sure it exists
		if(!file_exists($path))
			return new \WP_Error( 'invalid_path', __( 'No file found at this location.','dimages' ), $path );

		// Get information, set up for our possible resize
		$pathInfo = (object)pathinfo( $path );
		$editor = wp_get_image_editor( $path );

		// Something's afoot
		if(is_wp_error($editor))
			return $editor;

		// Do the sizing up front so we aren't creating multiple versions for smaller image sizes
		// need to finalize sizing for the benefit of the filename up front

		$origSize = (object)$editor->get_size();
		$destSize = (object)array('width'=>$width,'height'=>$height);

		if($destSize->width  === -1) $destSize->width  = $origSize->width;
		if($destSize->height === -1) $destSize->height = $origSize->height;

		$offsetX = max(0,min(100,$offsetX));
		$offsetY = max(0,min(100,$offsetY));

		// Cropping is disabled by default in the admin
		if($disableCropping)
			$cropped = false;

		if($cropped){
			$destSize->width  = min($destSize->width ,$maxWidth );
			$destSize->height = min($destSize->height,$maxHeight);
		}else{
			$constrained = wp_constrain_dimensions(
				$origSize->width,$origSize->height,
				$destSize->width,$destSize->height
				);

			$destSize = (object)array(
				'width'  => $constrained[0],
				'height' => $constrained[1]
				);
		}

		// Snap dimensions and offsets to specified intervals, to prevent far too many duplicates from being created

		// Snaps size up to steps
		$destSize->width  = ceil($destSize->width  / $sizeStep) * $sizeStep;
		$destSize->height = ceil($destSize->height / $sizeStep) * $sizeStep;

		// Snap offsets to nearest steps
		$offsetX = round($offsetX / $offsetStep) * $offsetStep;
		$offsetY = round($offsetY / $offsetStep) * $offsetStep;

		// Different sizing occurs for cropping vs straight resize
		if($cropped){

			// Find the ratio for the smallest dimensional difference
			$ratio = min(
				$origSize->width  / $destSize->width,
				$origSize->height / $destSize->height
				);

			// Scale down the destination size if we're too large
			if($ratio < 1){
				$destSize->width  = round($destSize->width  * $ratio);
				$destSize->height = round($destSize->height * $ratio);
				$ratio = 1;
			}

			$suffix = sprintf('cropped-%sx%s-%sx%s',
				$destSize->width,
				$destSize->height,
				$offsetX,
				$offsetY
				);

			// Set our offset to usable numbers
			$offsetXPixels = ($origSize->width  - ($destSize->width  * $ratio)) * ($offsetX/100);
			$offsetYPixels = ($origSize->height - ($destSize->height * $ratio)) * ($offsetY/100);

		}else{
			$constrained = wp_constrain_dimensions(
				$origSize->width,$origSize->height,
				$destSize->width,$destSize->height
				);

			$destSize = (object)array(
				'width'  => $constrained[0],
				'height' => $constrained[1]
				);

			$suffix = sprintf('%sx%s',$destSize->width,$destSize->height);
		}

		// If dimensions match, return the original
		if($destSize->width == $origSize->width && $destSize->height == $origSize->height)
			return $editor;

		// This is the destination file
		$uploads = wp_upload_dir();

		$cacheDir = preg_replace('!.*/(uploads|themes|plugins|wp_content)!', '', $pathInfo->dirname);

		$resizedDir = sprintf('%s/dynamic-images-cache%s',
			$uploads['basedir'],
			$cacheDir
			);

		$resizedPath = sprintf('%s/%s-%s.%s',
			$resizedDir,
			$pathInfo->filename,
			$suffix,
			$pathInfo->extension
			);

		// It doesn't exist, create it
		if(!file_exists($resizedPath)){

			if(!file_exists($resizedDir) && !@mkdir($resizedDir , 0755, true)){
				return new \WP_Error( 'permissions error', __( 'Unable to create cache directory.','dimages' ), $path );
			}

			if($cropped){
				$editor->crop(
					$offsetXPixels, $offsetYPixels,
					$destSize->width * $ratio, $destSize->height * $ratio,
					$destSize->width, $destSize->height
					);
			}else{
				$editor->resize( $destSize->width, $destSize->height );
			}

			$editor->save( $resizedPath );

			// Save a reference of this to our parent image if it's in the DB
			self::storeReference($url,$resizedPath);

			return $editor;
		}

		return wp_get_image_editor($resizedPath) ;

	}

	// Store references to our paths for future deletion

	private static function storeReference($url,$path)
	{
		global $wpdb;

		$attachment = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $wpdb->posts WHERE guid LIKE '%%%s'",
			$url 
			));

		if ( !$attachment ) return;

		$metadata = wp_get_attachment_metadata( $attachment[0]->ID );

		if (!isset( $metadata['image_meta'])) return;
		if(!isset($metadata['image_meta']['dynamic_images']))
			$metadata['image_meta']['dynamic_images'] = array();

		$metadata['image_meta']['dynamic_images'][] = str_replace(ABSPATH, '', $path);

		wp_update_attachment_metadata( $attachment[0]->ID, $metadata );

	}

	public static function deleteResized($post_id ) {

		// Get attachment image metadata
		$metadata = wp_get_attachment_metadata( $post_id );
		if (   empty($metadata)
			|| empty( $metadata['image_meta'] )
			|| empty( $metadata['image_meta']['dynamic_images'] ) )
			return;

		$images = $metadata['image_meta']['dynamic_images'];

		// Delete the resized images
		foreach ( $images as $image ) {
			// Delete the resized image
			@unlink( ABSPATH . $image );
		}
	}
}