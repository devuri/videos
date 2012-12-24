<?

/**
 * Dukt Videos
 *
 * @package		Dukt Videos
 * @version		Version 1.0b1
 * @author		Benjamin David
 * @copyright	Copyright (c) 2012 - DUKT
 * @link		http://dukt.net/videos/
 *
 */
 
namespace DuktVideos;

require_once(DUKT_VIDEOS_UNIVERSAL_PATH.'libraries/ajax.php');
require_once(DUKT_VIDEOS_PATH.'libraries/app.php');
 
class Ajax_blocks extends Ajax {

	public function __construct()
	{
		parent::__construct();
	}
	
	// --------------------------------------------------------------------
	
	public function field_preview()
	{
		$services = \DuktVideos\App::get_services();;
		
		$video_page = $this->lib->input_post('video_page');
		
		$video_opts = array(
			'url' => $video_page,
		);
		
		$embed_opts = array(
			'width' => 500,
			'height' => 282,
			'autohide' => true
		);
		
		$vars['video'] = \DuktVideos\App::get_video($video_opts, $embed_opts);

		echo $this->lib->load_view('field/preview', $vars, true, 'expressionengine');
		
		exit;
	}
}