<?php
/**
 *  Sets up the options for this plugin
 * 
 *  @author Jacob Dunn (http://phenomblue.com)
 */

namespace WordPress\Plugins\DynamicImages;

add_action('plugins_loaded',__NAMESPACE__.'\Options::setup');
add_filter('acf/settings/load_json', __NAMESPACE__.'\Options::addLoadPoint');

class Options{
	public static function getOptions()
	{
		$defaults = array(
			'maxWidth' => 	 	 2000,
			'maxHeight' => 	 	 2000,
			'disableCropping' => true,
			'sizeStep' => 	 	 200,
			'offsetStep' => 	 50,
			'embedAssets' =>	 true,
			'filterContent' =>	 true,
			'baseUrl' =>	 	 'di'
			);

		$options = array();
		if( function_exists('get_field') ) {

			$options['maxWidth'] 		= intval(get_field('max_width' ,'di_options'));
			$options['maxHeight'] 		= intval(get_field('max_height','di_options'));
			$options['disableCropping'] = (bool) get_field('disable_cropping','di_options');
			$options['sizeStep'] 		= intval(get_field('size_step','di_options'));
			$options['offsetStep'] 		= intval(get_field('offset_step','di_options'));

			$options['embedAssets'] 	= (bool) get_field('embed_assets','di_options');
			$options['filterContent'] 	= (bool) get_field('filter_content','di_options');
			$options['baseUrl'] 		= get_field('base_url','di_options');

			// Can't not have a url set
			if(empty($options['baseUrl']) && function_exists('wp_generate_password')){
				$options['baseUrl'] = 'di-'.\wp_generate_password(5,false);
				update_field('field_561ea193f9d8e',$options['baseUrl'],'di_options');
			}
		}

		return apply_filters('DI.options',wp_parse_args( $options, $defaults ));
	}

	public static function setup()
	{
		if( function_exists('acf_add_options_page') ) {
			acf_add_options_page(array(
				'page_title' => 'Dynamic Images',
				'menu_title' => 'Dynamic Images',
				'post_id' => 'di_options',
				'parent_slug' => 'options-general.php'
				));
		}
	}

	public static function addLoadPoint($paths)
	{
		$paths []= DI_ROOT . '/fields';
		return $paths;
	}
}