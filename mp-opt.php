<?php
/**
 * Maintenance Page options
 *
 * @see: http://wpsettingsapi.jeroensormani.com/
 * @see: http://ottopress.com/2009/wordpress-settings-api-tutorial/
 */


if (!defined('TSMP_PLUGIN_SLUG'))
	define( 'TSMP_PLUGIN_SLUG', basename( str_replace( ' ', '%20', plugins_url( '', __FILE__ ) ) ) );

// @include_once 'inc/lib-ts/opt-common.php';

/**
 */
function tsmp_settings_init() {
	register_setting(
		'tsmp_plugin_options',
		'tsmp_settings',
		'tsmp_settings_validate' );

	add_settings_section(
		'main',
		'Maintenance mode',
		'tsmp_section_main_text',
		'tsmp_settings' );

	add_settings_field(
		'maint_mode',
		'Maintenance mode',
		'maint_mode_render',
		'tsmp_settings',
		'main' );

	add_settings_field(
		'maint_allow',
		'Allowed users',
		'maint_allow_render',
		'tsmp_settings',
		'main' );

	add_settings_field(
		'maint_retry',
		'Expected return date',
		'maint_retry_render',
		'tsmp_settings',
		'main' );
}

add_action( 'admin_init', 'tsmp_settings_init' );

/**
 */
function tsmp_admin_notices() {
	$options = get_option( 'tsmp_settings' );
	if (isset($options['maint_mode']) && $options['maint_mode'] == 1) {
		add_settings_error(
			TSMP_PLUGIN_SLUG,
			'maint_mode',
			'<span class="dashicons dashicons-hidden"></span> ' .
			'Your site is in <a style="text-transform:uppercase"' .
			' href="' . admin_url('admin.php?page='.TSMP_PLUGIN_SLUG) . '"' .
			'>maintenance mode</a>!');
	}
}

add_action( 'admin_notices', 'tsmp_admin_notices' );

/**
 */
function tsmp_admin_bar_menu( $wp_admin_bar ) {
	if (!current_user_can('manage_options')) return;

	$options = get_option( 'tsmp_settings' );
	if ( isset($options['maint_mode']) && ($options['maint_mode'] == 1) ) {

		$wp_admin_bar->add_node( array(
			'id'     => 'maint-mode',
			'parent' => null,
			'group'  => null,
			'title'  => '<span class="ab-icon dashicons dashicons-hidden"></span>' .
				'<span class="ab-label" style="text-transform:uppercase">Maintenance mode</span>',
			'href'   => admin_url('admin.php?page='.TSMP_PLUGIN_SLUG),
			'meta'   => array(
				'target' => '_self',
				'title'  => 'Your site is in Maintenance Mode!',
				) ) );
	}
}

add_action( 'admin_bar_menu', 'tsmp_admin_bar_menu', 999 );

/**
 */
function tsmp_add_admin_menu() {
	if ( function_exists('add_tecsmith_page') )
		add_tecsmith_page(
			'TS Maintenance Page',
			'Maintenance Mode',
			'manage_options',
			TSMP_PLUGIN_SLUG,
			'tsmp_options_page',
			'dashicons-admin-generic',
			999 );
	else
		add_management_page(
			'TS Maintenance Page',
			'Maintenance Mode',
			'manage_options',
			TSMP_PLUGIN_SLUG,
			'tsmp_options_page',
			'dashicons-admin-generic',
			999 );
}

add_action( 'admin_menu', 'tsmp_add_admin_menu' );

/**
 */
function tsmp_section_main_text() {
	?><p>Activating maintenance mode will place your site into a
	<i>"back soon"</i> or <i>"under construction"</i> mode.<br>
	The site will render a <b>503</b> page and return code.</p><?php
}

function maint_mode_render() {
	$options = get_option( 'tsmp_settings' );
	?><label for="tsmp-maint-mode">
	<input type="checkbox" name="tsmp_settings[maint_mode]" <?php checked( $options['maint_mode'], 1 ); ?> value="1" id="tsmp-maint-mode">
	Check here to turn maintenance mode on.
	</label>
	<?php
}

function maint_allow_render() {
	$options = get_option( 'tsmp_settings' );

	// var_dump_pre($options); die();

	if (!isset($options['maint_allow']) || empty($options['maint_allow']))
		$options['maint_allow'] = 'administrator';
	?>
	<select name="tsmp_settings[maint_allow]">
		<option value="administrator" <?php selected( $options['maint_allow'], 'administrator' ); ?>>Administrator</option>
		<option value="editor" <?php selected( $options['maint_allow'], 'editor' ); ?>>Editor</option>
		<option value="author" <?php selected( $options['maint_allow'], 'author' ); ?>>Author</option>
		<option value="subscriber" <?php selected( $options['maint_allow'], 'subscriber' ); ?>>Subscriber</option>
	</select>
	<p class="description">These users will be granted access and will not see the maintenance page.</p>
	<?php
}

function maint_retry_render() {
	$options = get_option( 'tsmp_settings' );
	if (!isset($options['maint_retry']) || empty($options['maint_retry']))
		$options['maint_retry'] = date('Y-m-d');

	?>
	<input type="date" name="tsmp_settings[maint_retry]" value="<?php
		echo $options['maint_retry']; ?>" min="<?php echo date('Y-m-d'); ?>">
	<p class="description">Seting the time to today (or before) will recomend a retry in 10min's. </p>
	<?php
}

function tsmp_settings_validate($input) {
	$options = get_option( 'tsmp_settings' );

	$input['maint_mode'] = isset($input['maint_mode']) && $input['maint_mode'] ? 1 : 0;
	$changed = ($options['maint_mode'] !== $input['maint_mode']);
	$options['maint_mode'] = $input['maint_mode'];

	$options['maint_allow'] = $input['maint_allow'];

	$t = @strtotime($input['maint_retry']);  // hopefully it comes as '2016-09-15' format
	if ($t === false) $t = time();
	$options['maint_retry'] = date('Y-m-d', $t);

	if ($changed && ($options['maint_mode'] == 1)) {
		$options['maint_file'] = tsmp_find_maintenance_file();
	}

	return $options;
}


function tsmp_options_page() {
	global $title;

	if ( ! current_user_can( 'manage_options' ) )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );

?>
<div class="warp">
	<?php screen_icon(); ?>
	<h2><?php echo $title ?></h2>
	<div style="margin-bottom: 1rem;">
	<?php settings_errors(); ?>
	</div>

	<form action='options.php' method='post'>
		<?php
			settings_fields( 'tsmp_plugin_options' );
			?><div style="border: 1px solid rgba(255,0,0,0.2);
				box-shadow: rgba(127,0,0,0.1) 0 0 5px 5px inset;
				border-radius: 5px;
				padding: 0 1rem;
				margin-right: 1rem;
				background: rgba(127,0,0,0.025);
				display: block;"><?php
			do_settings_sections( 'tsmp_settings' );
			?></div><?php
			submit_button();
		?>
	</form>

	<p>
	It is recomended that you provide a 503 page.  The following are sought:
	<pre><?php
	$filelist = tsmp_find_maintenance_file(true);
	$fnd = $filelist[0];
	unset($filelist[0]);
	foreach ($filelist as $fn) {
		if (file_exists($fn))
			echo '<span class="dashicons dashicons-yes" style="color:#070;opacity:0.8"></span>';
		else
			echo '<span class="dashicons dashicons-no-alt" style="color:#F90;opacity:0.8"></span>';
		echo ' ';
		if ($fnd && ($fnd == $fn))
			echo sprintf('<b>%s</b> <span class="dashicons dashicons-visibility" style="color:#19F;opacity:0.8"></span>', $fn);
		else
			echo $fn;
		echo PHP_EOL;
	}
	?></pre></p>
	<p class="description">(Sorted in order of search, with file first found highlighted.)</p>
</div>
<?php
}

/**
* Plugin page settings link
 */
function tsmp_plugin_action_links( $links ) {
	$link = 'admin.php?page=' . TSMP_PLUGIN_SLUG;
	$link = '<a href="'.esc_url( get_admin_url(null, $link) ).'">'.__('Settings').'</a>';
	array_unshift($links, $link);
	return $links;
}

add_filter( 'plugin_action_links_'.dirname(plugin_basename(__FILE__)).'/maintenance-page.php', 'tsmp_plugin_action_links' );

// eof
