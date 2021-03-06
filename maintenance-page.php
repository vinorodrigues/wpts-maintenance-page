<?php
/**
 * Plugin Name: TS Maintenance Page
 * Plugin URI: http://tecsmith.com.au
 * Description: Maintenance Page plugin
 * Author: Vino Rodrigues
 * Version: 1.0.0
 * Author URI: http://vinorodrigues.com
**/

function __tsmp_fill_with($path, &$list) {
	$path = trailingslashit($path);
	$list[] = $path . '503.php';
	$list[] = $path . '503.html';
	// $list[] = $path . '503.htm';
}

/**
 */
function tsmp_find_maintenance_file($all = false) {
	$filelist = array(false);

	// Wordpress default
	$filelist[] = trailingslashit(WP_CONTENT_DIR) . 'maintenance.php';

	// Root of website
	__tsmp_fill_with( ABSPATH, $filelist);

	// Root of uploads folder
	__tsmp_fill_with( wp_upload_dir(null, false)['basedir'], $filelist );

	// Child theme folder
	if (is_child_theme())
		__tsmp_fill_with( get_stylesheet_directory(), $filelist );

	// Theme folder (parent theme)
	__tsmp_fill_with( get_template_directory(), $filelist );

	// This plugin folder
	__tsmp_fill_with( plugin_dir_path(__FILE__), $filelist );

	// List is done, now find the first file
	for ($i=1; $i < count($filelist); $i++) {
		if (file_exists($filelist[$i])) {
			$filelist[0] = $filelist[$i];
			break;
		}
	}

	return $all ? $filelist : $filelist[0];
}

include_once 'mp-opt.php';

if (!function_exists('today')) {
	function today() {
		return mktime(0, 0, 0);
	}
}

function tsmp_template_redirect() {
	if ( isset($_GET['503']) ) goto nocheck;  // test function
	// Goto??? Wow... since when?  Yup, bad practice, but this is the quickest way.

	$options = get_option( 'tsmp_settings' );
	if ( !isset($options['maint_mode']) || ($options['maint_mode'] != 1) ) return;

	$user = wp_get_current_user();
	if (is_object($user) && ($user->ID != 0)) {
		if (!isset($options['maint_allow']) || empty($options['maint_allow']))
			$options['maint_allow'] = false;
		switch ($options['maint_allow']) {
			case 'editor':
				$allowed_roles = array('administrator', 'editor');
				break;
			case 'author':
				$allowed_roles = array('administrator', 'editor', 'author');
				break;
			case 'subscriber':
				$allowed_roles = array('administrator', 'editor', 'author', 'subscriber');
				break;
			default:
				$allowed_roles = array('administrator');
		}

		//** find if user is permited to view
		if ( array_intersect($allowed_roles, $user->roles) ) return;
	}

	nocheck:;

	global $upgrading;

	//** fix retry time
	$upgrading = @strtotime($options['maint_retry']);
	if (($upgrading === false) || ($upgrading <= today()))
	 	$upgrading = mktime( date("H"), date("i")+10 );  // 10 min from now

	//** find the redirection file
	if (isset($options['maint_file']) && file_exists($options['maint_file'])) {
		$fn = $options['maint_file'];
	} else {
		$fn = tsmp_find_maintenance_file();
		if ($fn) {
			$options['maint_file'] = $fn;
			update_option( 'tsmp_settings', $options );
		}
	}

	//** if file is php then assume it will generate the 503.  Just include it.
	if ($fn && (pathinfo($fn, PATHINFO_EXTENSION) == 'php')) {
		include($fn);
		exit();
	}

	//** file was not a php, so set up the 503 return code header
	$protocol = $_SERVER["SERVER_PROTOCOL"];
	if ( 'HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol ) $protocol = 'HTTP/1.0';
	header( $protocol . ' 503 Service Temporarily Unavailable', true, 503 );
	header( 'Status: 503 Service Temporarily Unavailable' );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Retry-After: ' . date('r', $upgrading) );
	//** set up cache
	header( 'Cache-Control: public' );
	header( 'Expires: ' . date('r', $upgrading) );
	header( 'vary: User-Agent');
	header( 'ETag: "' . date('YmdHis', $upgrading) . '-tsmp"' );

	//** if file is htm or html then output it's contents.
	if ($fn) {
		readfile( $fn );
		exit();
	}

	//** if no file found then generate a generic output.
?><!DOCTYPE html>
<html<?php if ( is_rtl() ) echo ' dir="rtl"'; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php _e( 'Maintenance' ); ?></title>
</head>
<body>
	<h1><?php _e( 'Unavailable for scheduled maintenance.' ); ?></h1>
	<p><?php echo sprintf(__('Retry after %s at %s UTC'),
		date(get_option( 'date_format' ), $upgrading),
		date(get_option( 'time_format' ), $upgrading) ); ?></p>
</body>
</html>
<?php
	exit();
}

add_action( 'template_redirect', 'tsmp_template_redirect', 2 );
