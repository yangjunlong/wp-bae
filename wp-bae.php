<?php
/**
 * 百度BAE插件 云存储 和 邮件队列
 * 
 * @license 	General Public License
 * @author		Yang,Junlong at 2013-8-22 PM3:55:02 build.
 * @link		http://www.crossyou.cn/
 *//**
 * @package		WordPress
 * @subpackage  wp-bae
 * @version		$Id$
 */
/**
 * Plugin Name: 百度-BAE
 * Plugin URI: http://crossyou.cn/wp-bae.html
 * Description: Baidu BAE Plugin for wordpress
 * Version: 1.0.0
 * Author: 煎饼
 * Author URI: http://crossyou.cn/
 */
$appid = getenv('HTTP_BAE_ENV_APPID');
if($appid){
	define('IS_BAE', true);
}else{
	define('IS_BAE', false);
}
define('BAE_BASENAME', plugin_basename(__FILE__));
define('BAE_BASEFOLDER', plugin_basename(dirname(__FILE__)));
define('BAE_FILENAME', str_replace(DFM_BASEFOLDER.'/', '', plugin_basename(__FILE__)));
define('BCS_HOST', 'http://bcs.duapp.com');

// 初始化选项
register_activation_hook(__FILE__, 'bae_set_options');
function bae_set_options() {
	$options = array(
			'bucket' => '',
			'bcms'	 => '',
			'ak'     => '',
			'sk'     => '',
			'upload_path' => '.',
			'upload_url_path' => BCS_HOST
	);

	add_option('bae_options', $options, '', 'yes');
}

global $baidu_bcs,$bae_bucket,$bae_isimg;
$baidu_bcs = bae_get_baidu_bcs();
$bae_isimg = false;


//提示信息
function bae_admin_warnings() {
	$bcs_options = get_option('bcs_options', TRUE);

	$bcs_bucket = attribute_escape($bcs_options['bucket']);
	if ( !$bcs_options['bucket'] && !isset($_POST['submit']) ) {
		function bcs_warning() {
			echo "<div id='bcs-warning' class='updated fade'><p><strong>".__('Bcs is almost ready.')."</strong> ".sprintf(__('You must <a href="%1$s">enter your BCS Bucket </a> for it to work.'), "options-general.php?page=" . BCS_BASEFOLDER . "/bcs-support.php")."</p></div>";
		}
		add_action('admin_notices', 'bcs_warning');
		return;
	}
}

//获取BaiduBCS对象
function bae_get_baidu_bcs(){
	require_once('bcs.class.php');
	$bae_options = get_option('bae_options', TRUE);
	
	$bae_bucket  = attribute_escape($bae_options['bucket']);
	$bae_bcms    = attribute_escape($bae_options['bcms']);
	$bae_ak 	 = attribute_escape($bae_options['ak']);
	$bae_sk  	 = attribute_escape($bae_options['sk']);
	
	if(IS_BAE){
		$bae_ak = getenv ( 'HTTP_BAE_ENV_AK' );
		$bae_sk = getenv ( 'HTTP_BAE_ENV_SK' );
	}
	
	return new BaiduBCS($bae_ak, $bae_sk);
}

//上传文件到bcs远程服务器
function bae_file_upload_to_bcs($object , $file , $opt = array()){
	global $baidu_bcs,$bae_bucket;
	$default = array(
		'acl' => 'public-read',
		'headers' => array(
			'Expires' => 'access plus 1 years'
		)
	);
	$opt = wp_parse_args($opt,$default);
	
	$baidu_bcs->create_object ($bae_bucket, $object, $file, $opt );
	
	return true;
}

//上传函数（直接上传数据）
function bae_file_upload_by_contents( $object , $bits , $opt = array()){
	global $baidu_bcs,$bae_bucket;
	
	$default = array(
			'acl' => 'public-read',
			'headers' => array(
					'Expires' => 'access plus 1 years'
			)
	);
	$opt = wp_parse_args($opt,$default);
	
	$baidu_bcs->create_object_by_content ( $bae_bucket, $object, $bits, $opt );

	return true;
}


function bae_upload_attachments_to_bcs($data){
	global $baidu_bcs,$bae_bucket, $bae_isimg;
	
	$type = $data['type'];
	//如果是图片类型的文件，则不通过此方法上传
	if( substr_count($type,"image/")>0 ){
		$bae_isimg = true;
		return $data;
	}
	$bae_isimg = false;
	
	//获取上传路径
	$wp_upload_dir = wp_upload_dir();
	$upload_path = get_option('upload_path');
	$bcs_options = get_option('bcs_options', TRUE);
	if($upload_path == '.' ){
		$upload_path='/';
	} else {
		$upload_path = '/' . trim($upload_path,'/');
	}
	
	//上传原始文件
	$object = $upload_path.$wp_upload_dir['subdir'].'/'.basename($data['file']);
	$file = $data['file'];
	
	$opt =array(
		'headers' => array('Content-Type' => $type)
	);
	bae_file_upload_to_bcs($object, $file, $opt);
	
	$url = BCS_HOST.'/'.$bae_bucket.$object;
	
	return $data;
}
add_filter('wp_handle_upload', 'bae_upload_attachments_to_bcs');

function bae_upload_attachment($metadata){
	global $baidu_bcs,$bae_bucket, $bae_isimg;
	if(!$bae_isimg){
		return $metadata;
	}
	$bae_isimg = false;
	
	$wp_upload_dir = wp_upload_dir();
	$upload_path = get_option('upload_path');
	$bae_options = get_option('bae_options', true);
	if($upload_path == '.' ){
		$upload_path='/';
	} else {
		$upload_path = '/' . trim($upload_path,'/').'/';
	}
	
	$object = $upload_path.$metadata['file'];
	$file = $wp_upload_dir['basedir'].'/'.$metadata['file'];
	//上传原始文件
	bae_file_upload_to_bcs($object, $file);
	//上传小尺寸文件
	if (isset($metadata['sizes']) && count($metadata['sizes']) > 0){
		foreach ($metadata['sizes'] as $val){
			$object = $upload_path.$wp_upload_dir['subdir'].'/'.$val['file'];
			$file = $wp_upload_dir['path'].'/'.$val['file'];
			$opt =array(
				'headers' => array('Content-Type' => $val['mime-type'])
			);
			bae_file_upload_to_bcs ( $object, $file, $opt );
		}
	}
	
	return $metadata;
}
//生成缩略图后立即上传
add_filter('wp_generate_attachment_metadata', 'bae_upload_attachment', 999);

function bae_format_url($url) {
	if(strpos($url, BCS_HOST) !== false) {
		$arr = explode(BCS_HOST, $url);
		$url = BCS_HOST . $arr[1];
	}
	return $url;
}
add_filter('wp_get_attachment_url', 'bae_format_url');


//删除本地文件
function bae_delete_local_file($file){
	try{
		//文件不存在
		if(!@file_exists($file))
			return TRUE;
		//删除文件
		if(!@unlink($file))
			return FALSE;
		return TRUE;
	}
	catch(Exception $ex){
		return FALSE;
	}
}

//删除BCS上的附件 Thanks Loveyuki（loveyuki@gmail.com）
function bae_del_attachments_from_bcs($file) {
	require_once('bcs.class.php');

	$bae_options = get_option('bae_options', TRUE);

	$bae_bucket  = attribute_escape($bae_options['bucket']);
	$bae_bcms    = attribute_escape($bae_options['bcms']);
	$bae_ak 	 = attribute_escape($bae_options['ak']);
	$bae_sk  	 = attribute_escape($bae_options['sk']);

	if(IS_BAE){
		$bae_ak = getenv ( 'HTTP_BAE_ENV_AK' );
		$bae_sk = getenv ( 'HTTP_BAE_ENV_SK' );
	}

	if(!is_object($baidu_bcs)){
		$baidu_bcs = new BaiduBCS($bae_ak, $bae_sk);
	}	

	$bucket = $bae_bucket;

	$upload_dir = wp_upload_dir();

	$object = str_replace($upload_dir['basedir'],'',$file);
	$object = ltrim( $object , '/' );

	$object = str_replace('http://bcs.duapp.com/'.$bucket,'',$object);
	try {
		$baidu_bcs->delete_object($bucket, $object);
	}catch (Exception $e){
		
	}
	
	return $file;
}

add_action('wp_delete_file', 'bae_del_attachments_from_bcs');


function bae_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/wp-bae.php' ) ) {
		$links[] = '<a href="options-general.php?page=' . BAE_BASEFOLDER . '/wp-bae.php">'.__('Settings').'</a>';
	}

	return $links;
}
add_filter( 'plugin_action_links', 'bae_plugin_action_links', 10, 2 );

function bae_add_setting_page() {
	add_options_page('BCS Setting', '百度BAE', 8, __FILE__, 'bae_setting_page');
}

add_action('admin_menu', 'bae_add_setting_page');

function bae_setting_page() {
	$options = array();
	$settings_updated = false;
	
	if($_POST['bucket']) {
		$options['bucket'] = trim(stripslashes($_POST['bucket']));
	}
	if($_POST['bcms']) {
		$options['bcms'] = trim(stripslashes($_POST['bcms']));
	}
	if($_POST['ak'] && false === getenv ( 'HTTP_BAE_ENV_AK' )) {
		$options['ak'] = trim(stripslashes($_POST['ak']));
	}
	if($_POST['sk'] && false === getenv ( 'HTTP_BAE_ENV_SK' )) {
		$options['sk'] = trim(stripslashes($_POST['sk']));
	}
	if($_POST['upload_path']) {
		$options['upload_path'] = trim(stripslashes($_POST['upload_path']));
	}
	if($_POST['upload_url_path']) {
		$options['upload_url_path'] = trim(stripslashes($_POST['upload_url_path']));
	}
	if($_POST['uploads_use_yearmonth_folders']) {
		$options['uploads_use_yearmonth_folders'] = trim(stripslashes($_POST['uploads_use_yearmonth_folders']));
	}
	if($options !== array() ){
		update_option('bae_options', $options);
		
		$settings_updated = true;
    }

    $bae_options = get_option('bae_options', TRUE);

    $bae_bucket  = attribute_escape($bae_options['bucket']);
    $bae_bcms    = attribute_escape($bae_options['bcms']);
    $bae_ak 	 = attribute_escape($bae_options['ak']);
    $bae_sk  	 = attribute_escape($bae_options['sk']);
    
    $upload_path = attribute_escape($bae_options['upload_path']);
    $upload_url_path = attribute_escape($bae_options['upload_url_path']);
    $uploads_use_yearmonth_folders = attribute_escape($bae_options['uploads_use_yearmonth_folders']);
    
    if(IS_BAE){
    	$bae_ak = getenv ( 'HTTP_BAE_ENV_AK' );
    	$bae_sk = getenv ( 'HTTP_BAE_ENV_SK' );
    }
?>

<div class="wrap">
	<div id="icon-options-general" class="icon32"><br></div>
    <h2>百度BAE设置</h2>
    <?php if ($settings_updated):?>
    <div id="setting-error-settings_updated" class="updated settings-error"> 
	<p><strong>设置已保存。</strong></p></div>
	<?php endif;?>
    <form name="form1" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . BAE_BASEFOLDER . '/wp-bae.php'); ?>">
        <table class="form-table">
		<tbody>
		<?php if (IS_BAE) :?>
		<tr valign="top">
		<th scope="row"><label for="appid">应用ID</label></th>
		<td>
			<input name="appid" disabled="disabled" type="text" id="appid" value="" class="regular-text" value="<?php echo getenv('HTTP_BAE_ENV_APPID');?>">
			<p class="description">这是你的应用ID，不可修改</p>
		</td>
		</tr>
		<?php endif; ?>
		
		<tr valign="top">
		<th scope="row"><label for="bucket">Bucket设置</label></th>
		<td>
			<input name="bucket" type="text" id="bucket" value="<?php echo $bae_bucket;?>" class="regular-text" placeholder="请输入云存储使用的 Bucket">
			<p class="description">访问 <a href="http://developer.baidu.com/bae/bcs/bucket/">百度云存储</a> 创建 Bucket 后，填写以上内容。</p>
		</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="bcms">消息队列名称</label></th>
			<td><input name="bcms" type="text" id="bcms" value="<?php echo $bae_bcms;?>" class="regular-text">
			<p class="description">访问 <a href="http://developer.baidu.com/bae/bms/list/">消息服务</a> 创建 消息队列 后，填写以上内容。</p></td>
		</tr>
		
		<tr valign="top">
			<th scope="row"><label for="ak">Access Key / API key(AK)</label></th>
			<td><input name="ak"<?php if (IS_BAE) :?> disabled="disabled"<?php endif;?> type="text" id="ak" value="<?php echo $bae_ak;?>" class="regular-text">
			<p class="description">访问 <a href="http://developer.baidu.com/bae/ref/key/" target="_blank">我的密钥</a>，获取 AK和SK。</p></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="sk">Secret Key (SK)</label></th>
			<td><input name="sk"<?php if (IS_BAE) :?> disabled="disabled"<?php endif;?> type="text" id="sk" value="<?php echo $bae_sk;?>" class="regular-text">
		</tr>
		</tbody>
		</table>
		<h3 class="title">文件上传</h3>
		<table class="form-table">
			<tbody><tr valign="top">
			<th scope="row"><label for="upload_path">默认上传路径</label></th>
			<td><input name="upload_path" type="text" id="upload_path" value="<?php echo $upload_path;?>" class="regular-text code">
			<p class="description">默认为<code>.</code></p>
			</td>
			</tr>
			
			<tr valign="top">
			<th scope="row"><label for="upload_url_path">文件的完整URL地址</label></th>
			<td><input name="upload_url_path" disabled="disabled" type="text" id="upload_url_path" value="<?php echo $upload_url_path;?>" class="regular-text code">
			<p class="description">可选配置，默认留空。</p>
			</td>
			</tr>
			<tr>
			<th scope="row" colspan="2" class="th-full">
			<label for="uploads_use_yearmonth_folders">
			<input name="uploads_use_yearmonth_folders" type="checkbox" id="uploads_use_yearmonth_folders" value="1"<?php if($uploads_use_yearmonth_folders):?> checked="checked"<?php endif;?> />
			以年—月目录形式组织上传内容</label>
			</th>
			</tr>
			</tbody>
		</table>
        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="保存更改"></p>
    </form>
</div>
<?php }
/* End of file: index.php */
/* Location: ./index.php */