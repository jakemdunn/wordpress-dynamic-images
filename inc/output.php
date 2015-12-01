<?php
/**
 *  Methods for output, and asset embeds
 * 
 *  @author Jacob Dunn (http://phenomblue.com)
 */

namespace WordPress\Plugins\DynamicImages;


class Output{

	// ------------- Static Methods

	private static $instance;
	public static function instance()
	{
		if(null === static::$instance)
			static::$instance = new Output();

		return static::$instance;
	}

	public function embedAssets()
	{
		extract(Options::getOptions());

		if($embedAssets){
	  		wp_enqueue_script('dynamic-images',plugins_url('dynamic-images/assets/dynamic-images.js',DI_ROOT), false, null, true);
	  		wp_enqueue_style('dynamic-images',plugins_url('dynamic-images/assets/dynamic-images.css',DI_ROOT), false, null, true);
		}
	}
	public function outputOptions()
	{
		printf('<script>
			this.dynamicImageOptions = %s;
		</script>',json_encode(Options::getOptions()));
	}

	public function filterContent($content)
	{
		return preg_replace('!<img(.*?)src=(.*?)>!', '<img$1data-dynamic-image=$2>', $content);
	}

	protected function __construct()
	{
		extract(Options::getOptions());

		add_action('wp_enqueue_scripts', array($this,'embedAssets'));
		add_action('wp_footer', array($this,'outputOptions'));

		if($filterContent){
			add_filter('the_content', array($this,'filterContent'), 100);
			add_filter('acf/load_value/type=wysiwyg', array($this,'filterContent'));
			add_filter('acf/load_value/type=textarea', array($this,'filterContent'));
		}
	}

}

Output::instance();