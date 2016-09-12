<?php
/**
 * Plugin Name: TS Maintenance Page
 * Plugin URI: http://tecsmith.com.au
 * Description: Maintenance Page plugin
 * Author: Vino Rodrigues
 * Version: 0.9.0
 * Author URI: http://vinorodrigues.com
**/

/**
 */
function tsmp_find_maintenance_file($all = false) {
	$filelist = array(false);

	function __fill_with($path, &$list) {
		$list[] = $path . '503.php';
		$list[] = $path . '503.html';
		// $list[] = $path . '503.htm';
	}

	// Wordpress default
	$filelist[] = trailingslashit(WP_CONTENT_DIR) . 'maintenance.php';

	// Root of website
	__fill_with( ABSPATH, $filelist);

	// Child theme folder
	if (is_child_theme())
		__fill_with( trailingslashit(get_stylesheet_directory()), $filelist );

	// Theme folder (parent theme)
	__fill_with( trailingslashit(get_template_directory()), $filelist );

	// This plugin folder
	__fill_with( trailingslashit(plugin_dir_path(__FILE__)), $filelist );

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

function tsmp_template_redirect() {
	$options = get_option( 'tsmp_settings' );
	if ( !isset($options['maint_mode']) || ($options['maint_mode'] != 1) ) return;

	if (!isset($options['maint_allow']) || empty($options['maint_allow']))
		$options['maint_allow'] = 'administrator';

	//** find if user is permited to view
	if (current_user_can( $options['maint_allow'] )) return;

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
	header( 'Retry-After: ' . date('r', $options['maint_return']) );
	//** set up cache
	header( 'Cache-Control: public' );
	header( 'Expires: ' . date('r', $options['maint_return']) );
	// header( 'vary: User-Agent');
	header( 'ETag: "' . date('YmdHis', $options['maint_return']) . '-tsmp"' );

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
		date(get_option( 'date_format' ), $options['maint_return']),
		date(get_option( 'time_format' ), $options['maint_return']) ); ?></p>
</body>
</html>
<?php
	exit();
}

add_action( 'template_redirect', 'tsmp_template_redirect', 2 );
