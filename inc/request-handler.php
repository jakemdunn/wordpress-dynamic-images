<?php
/**
 *  Handles all requests for our resized images, and streams them to output.
 *
 *	Example request: http://dev.yoursite.com/di/c1800x1200-xjpg/wp-content/uploads/2015/05/153
 *
 *	Parts:
 *		Base: http://dev.yoursite.com/di/
 *		Query: c1800x1200-xjpg (crop to 1800x1200, file extension jpg)
 *		Path: /wp-content/uploads/2015/05/153 - note the file extension is specified in the query, to allow for use on NGINX servers
 *
 *	Available Query Variables:
 *		c - crop      | c{WIDTH}x{HEIGHT}    | eg. c100x50 (crops to 100x50 image)
 *		o - offset    | o{offsetX}x{offsetY} | eg. o100x50 (offsets to right center)
 *		w - width     | w{WIDTH}			 | eg. w100 (sizes image to max width of 100)
 *		h - height    | h{HEIGHT}			 | eg. h100 (sizes image to max height of 100)
 *		x - extension | x{EXTENSION}		 | eg. xjpg (passes extension of target image to work around NGINX file handling)
 * 
 *  @author Jacob Dunn (http://phenomblue.com)
 */

namespace WordPress\Plugins\DynamicImages;

class RequestHandler{

	public function init()
	{
		$this->setupRewrites();
	}

	public function parse_query($query)
	{
		$this->outputImage($query);
	}

	public function __construct()
	{
		add_action('init',array(&$this,'init'));
		add_action('parse_query',array(&$this,'parse_query'));
	}

	private function setupRewrites()
	{
		extract(Options::getOptions());

		$rules = get_option( 'rewrite_rules' );
		$rule = sprintf('^%s/([^/]*)/(.*)?',$baseUrl);
		$queryParam = sprintf('%s-query',$baseUrl);
		$pathParam = sprintf('%s-path',$baseUrl);

		if ( ! isset( $rules[$rule] ) ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}

		add_rewrite_tag('%'.$queryParam.'%', '([^&]+)');
		add_rewrite_tag('%'.$pathParam.'%', '([^&]+)');
		add_rewrite_rule($rule,'index.php?'.$queryParam.'=$matches[1]&'.$pathParam.'=$matches[2]','top');
	}


	protected static $operations = array(
		'c' => 'crop',
		'f' => 'crop',
		'w' => 'width',
		'h' => 'height',
		'o' => 'offset',
		'x' => 'extension'
	);

	private function outputImage($query)
	{
		extract(Options::getOptions());

		$queryParam = sprintf('%s-query',$baseUrl);
		$pathParam = sprintf('%s-path',$baseUrl);

		if(empty($query->query[$queryParam]) || empty($query->query[$pathParam])) return;

		$params = $query->query[$queryParam];
		$path = preg_replace('/\.\.\//', '', $query->query[$pathParam]);

		$params = explode('-', $params);
		$parsed = array();

		foreach ($params as $param) {
			if (preg_match("/(?P<operation>[a-z]{1})(?P<value>[\S]*)/", $param, $matches) &&
				isset(static::$operations[$matches['operation']])) {
				$parsed[static::$operations[$matches['operation']]] = $matches['value'];
			}
		}

		foreach ($parsed as $key => $value) {
			switch ($key) {
				case 'crop':
					$dimensions = explode('x', $value);
					$parsed['width'] = intval($dimensions[0]);
					$parsed['height'] = intval($dimensions[1]);
					$parsed['cropped'] = true;
					unset($parsed[$key]);
					break;
				case 'extension':
					$path .= '.'.preg_replace('/[^0-9a-zA-Z]*/', '', $value);
					unset($parsed[$key]);
					break;
				case 'offset':
					$dimensions = explode('x', $value);
					$parsed['offsetX'] = intval($dimensions[0]);
					$parsed['offsetY'] = intval($dimensions[1]);
					unset($parsed[$key]);
					break;
				case 'width':
				case 'height':
					$parsed[$key] = intval($value);
					break;
			}
		}

		$image = Resize::getImage($path,$parsed);
		if(is_wp_error($image))
			wp_die($image);

		// Set caching headers, and stream
		header('Pragma: public');
		header('Cache-Control: max-age=86400');
		header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
		$image->stream();

		exit();
	}
}

$handler = new RequestHandler();