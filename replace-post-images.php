<?php
defined( 'ABSPATH' ) or die( 'You are so handsome!' );
/**
 * Plugin Name: Replace post images
 * Plugin URI: http://mypluginuri.com/
 * Description: Inside a post, download images from other domain, save its this domain and replace old image with new image.
 * Version: 1.0
 * Author: thontc82@gmail.com
 * Author URI: http://admicro.vn
 * License: A "replace-post-images" license name e.g. GPL12
 * Text Domain: replace-post-images
 * Domain Path: /
 */
if(!function_exists('base_url')){
	function base_url($path = ''){
		$str = substr($path, 0, 1)=='/' ? '' : '/';
		$ssl      = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' );
		$sp       = strtolower( $_SERVER['SERVER_PROTOCOL'] );
		$protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
		$port     = $_SERVER['SERVER_PORT'];
		$port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
		$host     = $_SERVER['SERVER_NAME'] . $port;
		if($path==''){
			$str = $path = '';
		}
		return $protocol . '://' . $host . $str . $path;
	}
}
define('DOMAIN_REPLACE', base_url(''));
class Content{
	private $post_id;
	private $content;
	function __construct($p_id, $cont=''){
		$this->post_id = $p_id;
		$this->content = $cont;
	}
	public function replace(){
		$start = '<img';
		$end = '>';

		$total_images = substr_count($this->content, $start);
		$count = 0;
		$append = 0;
		while($count<$total_images){
			$img_tag = $this->getBlock($this->content, $start, $end, $append, true);
			$append = $img_tag['end_offset'];
			$img_link = $this->getBlock($img_tag['block'], 'src="', '" ', 0, false);
			if($img_link){
				$check_link = strpos($img_link['block'], DOMAIN_REPLACE, 0);
				if($check_link===false){
					$this->logE('[post_id:'.$this->post_id.'][old_link]=' . $img_link['block']);
					$new_link = $this->clone_image($img_link['block'], $this->post_id);
					$this->logE('[post_id:'.$this->post_id.'][new_link]=' . $new_link);
					$this->content = str_replace($img_link['block'], $new_link, $this->content);
				}
			}
			$count++;
		}
		return $this->content;
	}
	private function clone_image( $image_url, $post_id  ){
		$upload_dir = wp_upload_dir();
		$image_data = file_get_contents($image_url);
		$filename = strtolower(basename($image_url));
		$filename = urldecode($filename);
		$filename = sanitize_file_name($filename);
		if(wp_mkdir_p($upload_dir['path'])){
			$file = $upload_dir['path'] . '/' . $filename;
		}else{
			$file = $upload_dir['basedir'] . '/' . $filename;
		}
		file_put_contents($file, $image_data);

		$wp_filetype = wp_check_filetype( $filename, null );
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => sanitize_file_name($filename),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		$res1 = wp_update_attachment_metadata( $attach_id, $attach_data );
		//$res2 = set_post_thumbnail( $post_id, $attach_id );
		//$this->logE($res1);
		//$this->logE($res2);
		if(!$res1){
			return '';
		}
		$url = $this->base_url('wp-content/uploads/'.$attach_data['file']);
		return $url;
	}
	private function getBlock($content, $start, $end, $append=0, $has_wrap=false){
		$start_offset = strpos($content, $start, $append);
		if($start_offset<=0) return '';
		$end_offset = strpos($content, $end, $start_offset);
		if($end_offset<=0) return '';
		if($end_offset<=$start_offset) return '';
		if($has_wrap){
			$block = substr($content, $start_offset, $end_offset-$start_offset+strlen($end));
		}else{
			$start_offset = $start_offset+strlen($start);
			$block = substr($content, $start_offset, $end_offset-$start_offset);
		}
		return array('start_offset'=>$start_offset, 'end_offset'=>$end_offset, 'block'=>$block);
	}

	private function base_url($path = ''){
		$str = substr($path, 0, 1)=='/' ? '' : '/';
		$ssl      = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' );
		$sp       = strtolower( $_SERVER['SERVER_PROTOCOL'] );
		$protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
		$port     = $_SERVER['SERVER_PORT'];
		$port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
		$host     = $_SERVER['SERVER_NAME'] . $port;
		if($path==''){
			$str = $path = '';
		}
		return $protocol . '://' . $host . $str . $path;
	}
	private function logE($msg){
		//error_log(date('[Y:m:d H:i:s] ') . print_r($msg, true) . "\n", 3, plugin_dir_path(__FILE__).'system-'.date('Y-m-d').'.log' );
	}
}

class ReplacePostImages{
	function __construct(){
		add_action( 'wp_ajax_rpi_replace_images', array($this, 'rpi_replace_images') );
		add_action( 'wp_ajax_nopriv_rpi_replace_images', array($this, 'rpi_replace_images') );

		add_action('admin_enqueue_scripts', array($this, 'thematic_enqueue_scripts'));
		add_action('media_buttons', array($this, 'add_replace_media_button'));
	}
	public function thematic_enqueue_scripts() {
		wp_register_script('replace_media_script', plugins_url("script.js", __FILE__), array('jquery'), '1.0', true);
		wp_enqueue_script('replace_media_script');
	}
	static public function init(){
		$obj = new self();
	}
	function add_replace_media_button(){
		global $post;
		echo '<button type="button" id="btn-replace-image" class="btn-replace-image button button-default">Replace images</button><img id="loading-replace-image" style="display:none;" src="/wp-includes/images/spinner.gif">';
	}
	function rpi_replace_images(){
		global $wpdb;
		$post_id = isset($_POST['post_id']) ? stripslashes($_POST['post_id']) : 0;
		$content = isset($_REQUEST['content']) ? stripslashes($_REQUEST['content']) : '';
		$result = array('success'=>true, 'msg'=>'', 'content'=>'');
		try{
			if(!defined('DOMAIN_REPLACE')) throw new Exception('You have not config DOMAIN_REPLACE');
			if(!$post_id) throw new Exception('Not found post_id');
			if(!$content) throw new Exception('Not found content');
			
			$contentObject = new Content($post_id, $content);
			$result['content'] = $contentObject->replace();
			
		}catch(Exception $e){
			$result['msg'] = $e->getMessage();
		}
		echo json_encode($result);exit();
	}
}
add_action( 'plugins_loaded', array( 'ReplacePostImages', 'init' ), 10 );