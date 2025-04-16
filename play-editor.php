<?php
/**
 * Plugin Name: Play Editor
 * Plugin URI: https://github.com/zaerl/play-editor
 * Description: A WordPress Playground WYPIWYG editor. The P stands for "Play".
 * Version: 0.1.0
 * Requires at least: 6.0
 * Author: Francesco Bigiarini
 * Author URI: https://zaerl.com
 *
 * @package Play_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Set the blueprint for the Play Editor.
 *
 * @param array|null $blueprint The blueprint to set.
 * @return array The blueprint.
 */
function zape_set_blueprint( $blueprint = null ) {
	if ( null === $blueprint ) {
		$blueprint = array(
			'_za_pe' => true,
			'steps'  => array( array( 'step' => 'login' ) ),
		);
	}

	update_option( 'zape_blueprint', $blueprint );

	return $blueprint;
}

/**
 * Get the blueprint for the Play Editor.
 *
 * @return array The blueprint.
 */
function zape_get_blueprint() {
	return get_option( 'zape_blueprint' );
}

/**
 * Ensure the networking feature is enabled.
 *
 * @param array $blueprint The blueprint.
 * @return array The blueprint.
 */
function zape_ensure_networking( $blueprint ) {
	if ( ! array_key_exists( 'features', $blueprint ) ) {
		$blueprint['features'] = array();
	}

	$blueprint['features']['networking'] = true;

	return $blueprint;
}

/**
 * Check if the current environment is the WordPress Playground.
 *
 * TODO: This is a temporary solution.
 *
 * @return bool
 */
function zape_is_playground() {
	// Main site or local environment.
	return function_exists( 'post_message_to_js' );
}

/**
 * Get the Playground URL.
 *
 * @param array $blueprint_obj The blueprint object.
 * @param bool  $builder Whether to open the builder.
 *
 * @return string The Playground URL.
 */
function zape_get_playground_url( $blueprint_obj, $builder = false ) {
	$url = 'https://playground.wordpress.net';

	if ( $builder ) {
		$url = $url . '/builder/builder.html';
	}

	if ( $blueprint_obj ) {
		$url = $url . '#' . wp_json_encode( $blueprint_obj );
	}

	return $url;
}

/**
 * Clean the blueprint object.
 *
 * @param array $blueprint_obj The blueprint object.
 * @return array The cleaned blueprint object.
 */
function zape_clean_blueprint( $blueprint_obj ) {
	if ( array_key_exists( 'steps', $blueprint_obj ) && is_array( $blueprint_obj['steps'] ) ) {
		foreach ( $blueprint_obj['steps'] as $i => $step ) {
			if ( array_key_exists( '_za_pe', $step ) ) {
				unset( $blueprint_obj['steps'][ $i ]['_za_pe'] );
			}
		}
	}

	return $blueprint_obj;
}

/**
 * Render the options page for the Play Editor.
 */
function zape_options_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$blueprint_obj      = zape_clean_blueprint( zape_get_blueprint() );
	$blueprint_file_obj = zape_clean_blueprint( zape_load_blueprint() );

	$blueprint      = wp_json_encode( $blueprint_obj, JSON_PRETTY_PRINT );
	$blueprint_file = null;

	if ( false === $blueprint ) {
		$blueprint = '{}';
	}

	if ( null !== $blueprint_file_obj ) {
		$blueprint_file = wp_json_encode( $blueprint_file_obj, JSON_PRETTY_PRINT );

		if ( 0 === strcmp( $blueprint, $blueprint_file ) ) {
			// The blueprint is the same as the initial one.
			$blueprint_file = false;
		}
	}

	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<style>
		#za-pe-editor {
			background: #f8f9fa;
			border: 1px solid #ddd;
			border-radius: 4px;
			padding: 15px;
			font-family: monospace;
			font-size: 13px;
			line-height: 1.4;
			overflow: auto;
			max-height: 500px;
		}
		</style>
		<script>
		function zape_get_blueprint(type) {
			const is_playground = <?php echo zape_is_playground() ? 'true' : 'false'; ?>;
			const url = new URL('<?php echo admin_url('admin-ajax.php'); ?>');
			url.searchParams.append('action', 'zape_get_blueprint');
			url.searchParams.append('type', type);
			url.searchParams.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'zape_get_blueprint' ) ); ?>');

			fetch(url);
		}
		</script>
		<h2><?php esc_html_e( 'Playground Blueprint', 'play-editor' ); ?></h2>
		<pre id="za-pe-editor"><?php echo esc_html( $blueprint ); ?></pre>
		<p>
			<button type="button" class="za-pe-open-blueprint button" onclick="zape_get_blueprint('open')">
				<?php esc_html_e( 'Open on Playground', 'play-editor' ); ?>
			</button>&nbsp;
			<button type="button" class="za-pe-open-blueprint button" onclick="zape_get_blueprint('builder-open')">
				<?php esc_html_e( 'Open on Playground Blueprint Builder', 'play-editor' ); ?>
			</button>&nbsp;
			<button type="button" class="za-pe-open-blueprint button" onclick="zape_get_blueprint('copy')">
				<?php esc_html_e( 'Copy blueprint to clipboard', 'play-editor' ); ?>
			</button>&nbsp;
			<button type="button" class="za-pe-open-blueprint button" onclick="zape_get_blueprint('download')">
				<?php esc_html_e( 'Download blueprint', 'play-editor' ); ?>
			</button>
		</p>
		<?php if ( $blueprint_file ) : ?>
			<h2><?php esc_html_e( 'Initial Playground Blueprint', 'play-editor' ); ?></h2>
			<pre id="za-pe-editor"><?php echo esc_html( $blueprint_file ); ?></pre>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Intercept 'activate_plugin' action for activatePlugin step.
 *
 * @param string $plugin The plugin path.
 * @param bool   $network_wide Whether the plugin is being activated network-wide.
 */
function zape_step_activate_plugin( $plugin, $network_wide ) {
	$blueprint = zape_get_blueprint();
	$installed = null;
	$slug      = null;

	if ( false !== strpos( $plugin, '/' ) ) {
		$slug = dirname( $plugin );
	} else {
		$slug = basename( $plugin, '.php' );
	}

	// Hello Dolly is a default plugin, so we don't need to install it.
	if ( 'hello.php' === $plugin ) {
		$blueprint['steps'][] = array(
			'_za_pe'     => true,
			'step'       => 'activatePlugin',
			'pluginPath' => $plugin,
		);

		return zape_set_blueprint( $blueprint );
	}

	foreach ( $blueprint['steps'] as $i => $step ) {
		if (
			'installPlugin' === $step['step'] &&
			$slug === $step['pluginData']['slug']
		) {
			$installed = $i;
			break;
		}
	}

	if ( null !== $installed ) {
		// Already installed using a blueprint, just activate it.
		if ( ! array_key_exists( 'options', $blueprint['steps'][ $installed ] ) ) {
			$blueprint['steps'][ $installed ]['options'] = array(
				'activate' => true,
			);
		}
	} else {
		// Not installed, install and activate it.
		$blueprint['steps'][] = array(
			'_za_pe'     => true,
			'step'       => 'installPlugin',
			'pluginData' => array(
				'resource' => 'wordpress.org/plugins',
				'slug'     => $slug,
			),
			'options'    => array( 'activate' => true ),
		);
	}

	zape_set_blueprint( zape_ensure_networking( $blueprint ) );
}

/**
 * Intercept 'switch_theme' action for activateTheme step.
 *
 * @param string   $new_name The new theme name.
 * @param WP_Theme $new_theme The new theme folder name.
 * @param WP_Theme $old_theme The old theme folder name.
 */
function zape_step_activate_theme( $new_name, $new_theme, $old_theme ) {
	$blueprint = zape_get_blueprint();
	$installed = null;
	$slug      = $new_theme->get_stylesheet();

	foreach ( $blueprint['steps'] as $i => $step ) {
		if (
			'installTheme' === $step['step'] &&
			$slug === $step['themeData']['slug']
		) {
			$installed = $i;
			break;
		}
	}

	if ( null !== $installed ) {
		// Already installed using a blueprint, just activate it.
		if ( ! array_key_exists( 'options', $blueprint['steps'][ $installed ] ) ) {
			$blueprint['steps'][ $installed ]['options'] = array(
				'activate' => true,
			);
		}
	} else {
		// Not installed, install and activate it.
		$blueprint['steps'][] = array(
			'_za_pe'    => true,
			'step'      => 'installTheme',
			'themeData' => array(
				'resource' => 'wordpress.org/themes',
				'slug'     => $slug,
			),
			'options'   => array( 'activate' => true ),
		);
	}

	zape_set_blueprint( zape_ensure_networking( $blueprint ) );
}

/**
 * Intercept 'added_option' action for setSiteOptions step.
 *
 * @param string $option_name The name of the option.
 * @param mixed  $value The value of the option.
 */
function zape_added_option( $option_name, $value ) {
	return zape_step_set_site_options( $option_name, null, $value );
}

/**
 * Intercept 'add_site_option' action for setSiteOptions step.
 *
 * @param string $option_name The name of the option.
 * @param mixed  $value The value of the option.
 * @param int    $network_id The network ID.
 */
function zape_add_site_option( $option_name, $value, $network_id ) {
	return zape_step_set_site_options( $option_name, null, $value, $network_id );
}

/**
 * Intercept 'updated_option' action for setSiteOptions step.
 *
 * @param string $option_name The name of the option.
 * @param mixed  $old_value The old value of the option.
 * @param mixed  $value The new value of the option.
 * @param int    $network_id The network ID.
 */
function zape_step_set_site_options( $option_name, $old_value, $value, $network_id = null ) {
	global $pagenow;

	// Special case for Play Editor option.
	if ( 'zape_blueprint' === $option_name ) {
		return;
	}

	$value  = maybe_serialize( $value );
	$unused = array(
		'active_plugins',
		'adminhash',
		'bp-emails-unsubscribe-salt',
		'can_compress_scripts',
		'cron',
		'finished_updating_comment_type',
		'fresh_site',
		'recently_activated',
		'recovery_keys',
		'rewrite_rules',
		'sidebars_widgets',
		'theme_switched',
	);

	// During activation of a new theme or plugin, multiple options are set.
	if (
		( 'themes.php' === $pagenow || 'plugins.php' === $pagenow ) &&
		isset( $_GET['action'] ) &&
		'activate' === $_GET['action']
	) {
		return;
	}

	if (
		str_starts_with( $option_name, '_' ) || // _transient_doing_cron and others.
		str_starts_with( $option_name, 'theme_mods_' ) || // theme_mods_{theme} and others.
		in_array( $option_name, $unused, true ) ||
		$old_value === $value ||
		// Prevent empties.
		( '0' === $old_value && null === $value ) ||
		( 0 === $old_value && null === $value ) ||
		( '' === $old_value && null === $value ) ||
		( null === $old_value && '' === $value ) ||
		// Prevent numeric string values and digits.
		( is_numeric( $value ) && $old_value === (string) $value )
	) {
		return;
	}

	// Special case for admin email.
	if ( 'new_admin_email' === $option_name ) {
		$option_name = 'admin_email';
	}

	$blueprint = zape_get_blueprint();

	// Find if there's already a setSiteOptions step.
	$found = false;

	foreach ( $blueprint['steps'] as $i => $step ) {
		if ( 'setSiteOptions' === $step['step'] ) {
			$found = $i;
			break;
		}
	}

	if ( false === $found ) {
		$blueprint['steps'][] = array(
			'_za_pe'  => true,
			'step'    => 'setSiteOptions',
			'options' => array(
				$option_name => $value,
			),
		);
	} else {
		$blueprint['steps'][ $found ]['options'][ $option_name ] = $value;
	}

	zape_set_blueprint( $blueprint );
}

/**
 * Intercept 'saved_term' action for "save term" step.
 *
 * @param int    $term_id The term ID.
 * @param int    $tt_id The term taxonomy ID.
 * @param string $taxonomy The taxonomy.
 * @param bool   $update Whether the term is being updated.
 * @param array  $args The arguments.
 */
function zape_step_save_term( $term_id, $tt_id, $taxonomy, $update, $args ) {
	global $pagenow;

	if ( str_starts_with( $taxonomy, '_' ) ) {
		return;
	}

	// During BuddyPress activation, multiple terms are set.
	if ( 'plugins.php' === $pagenow && str_starts_with( $taxonomy, 'bp-' ) ) {
		return;
	}

	if ( $update ) {
		// wp term update category N --name=Test.
		$cli_command = sprintf(
			'wp term update %s %d --name="%s" --slug="%s" --description="%s" --parent=%s',
			$taxonomy,
			$term_id,
			$args['name'],
			$args['slug'],
			$args['description'],
			$args['parent']
		);
	} else {
		// wp term create category "Test" --slug="test".
		$cli_command = sprintf(
			'wp term create %s "%s"',
			$taxonomy,
			$args['name']
		);

		if ( is_string( $args['slug'] ) && '' !== $args['slug'] ) {
			$cli_command .= sprintf( ' --slug="%s"', $args['slug'] );
		}

		if ( is_string( $args['description'] ) && '' !== $args['description'] ) {
			$cli_command .= sprintf( ' --description="%s"', $args['description'] );
		}

		if ( is_string( $args['parent'] ) && '' !== $args['parent'] ) {
			$cli_command .= sprintf( ' --parent=%s', $args['parent'] );
		}
	}

	$blueprint = zape_get_blueprint();

	$blueprint['steps'][] = array(
		'_za_pe'  => true,
		'step'    => 'wp-cli',
		'command' => $cli_command,
	);

	zape_set_blueprint( $blueprint );
}

/**
 * Intercept 'wp_insert_post_data' filter for "save post" step.
 *
 * @param array $data The post data.
 * @param array $postarr The post array.
 * @param array $unsanitized_postarr The unsanitized post array.
 * @param bool  $update Whether the post is being updated.
 */
function zape_insert_post_data( $data, $postarr, $unsanitized_postarr, $update ) {
	if ( $update ) {
		update_option( 'zape_post_data', get_post( $postarr['ID'], ARRAY_A ) );
	}

	return $data;
}

/**
 * Intercept 'save_post' action for "save post" step.
 *
 * @param int     $post_id The post ID.
 * @param WP_Post $post The post object.
 * @param bool    $update Whether the post is being updated.
 */
function zape_step_save_post( $post_id, $post, $update ) {
	$blueprint = zape_get_blueprint();

	if ( 'wp_global_styles' === $post->post_type ) {
		return;
	}

	if ( $update ) {
		$original_post = get_option( 'zape_post_data' );

		if ( is_null( $original_post ) ) {
			return;
		}

		$changed_fields = array();

		foreach ( $post->to_array() as $field => $value ) {
			if ( isset( $original_post[ $field ] ) && $original_post[ $field ] !== $value ) {
				$changed_fields[ $field ] = $value;
			}
		}

		unset( $changed_fields['post_modified'] );
		unset( $changed_fields['post_modified_gmt'] );

		$cli_command = sprintf( 'wp post update %d', $post_id );

		foreach ( $changed_fields as $field => $value ) {
			$cli_command .= sprintf( ' --%s="%s"', $field, is_array( $value ) ? implode( ',', $value ) : $value );
		}
	} else {
		$cli_command = 'wp post create';
		$new_post    = $post->to_array();

		unset( $new_post['comment_count'] );
		unset( $new_post['guid'] );
		unset( $new_post['ID'] );
		unset( $new_post['post_modified'] );
		unset( $new_post['post_modified_gmt'] );

		foreach ( $new_post as $field => $value ) {
			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}

			$cli_command .= sprintf( ' --%s="%s"', $field, is_array( $value ) ? implode( ',', $value ) : $value );
		}
	}

	$blueprint['steps'][] = array(
		'_za_pe'  => $post_id,
		'step'    => 'wp-cli',
		'command' => $cli_command,
	);

	delete_option( 'zape_post_data' );
	zape_set_blueprint( $blueprint );
}

/**
 * Intercept 'delete_post' action for "delete post" step.
 *
 * @param int     $post_id The post ID.
 * @param WP_Post $post The post object.
 */
function zape_step_delete_post( $post_id, $post ) {
	$blueprint = zape_get_blueprint();
	$found     = false;

	foreach ( $blueprint['steps'] as $i => $step ) {
		if (
			array_key_exists( '_za_pe', $step ) &&
			str_starts_with( $step['step'], 'wp post' ) &&
			(string) $post_id === (string) $step['_za_pe']
		) {
			unset( $blueprint['steps'][ $i ] );
			$found = true;
		}
	}

	if ( ! $found ) {
		$blueprint['steps'][] = array(
			'_za_pe'  => true,
			'step'    => 'wp-cli',
			'command' => sprintf( 'wp post delete %d', $post_id ),
		);
	}

	zape_set_blueprint( $blueprint );
}

/**
 * Intercept 'updated_user_meta' action for "update user meta" step.
 *
 * @param int    $meta_id The meta ID.
 * @param int    $user_id The user ID.
 * @param string $meta_key The meta key.
 * @param mixed  $meta_value The meta value.
 */
function zape_step_update_user_meta( $meta_id, $user_id, $meta_key, $meta_value ) {
	$unused = array(
		'community-events-location',
		'session_tokens',
		'wp_dashboard_quick_press_last_post_id',
		'wp_persisted_preferences',
		'wp_user-settings',
		'wp_user-settings-time',
	);

	if ( in_array( $meta_key, $unused, true ) ) {
		return;
	}

	$blueprint = zape_get_blueprint();

	$blueprint['steps'][] = array(
		'_za_pe' => true,
		'step'   => 'updateUserMeta',
		'meta'   => array(
			$meta_key => $meta_value,
		),
		'userId' => $user_id,
	);

	zape_set_blueprint( $blueprint );
}

/**
 * Load the blueprint from the file.
 *
 * @return array|null The blueprint.
 */
function zape_load_blueprint() {
	$blueprint_path = plugin_dir_path( __FILE__ ) . 'blueprint.json';

	if ( file_exists( $blueprint_path ) ) {
		$blueprint_content = file_get_contents( $blueprint_path );
		$blueprint_content = json_decode( $blueprint_content, true );

		if ( $blueprint_content ) {
			return $blueprint_content;
		}
	}

	return null;
}

/**
 * Bind the core steps.
 */
function zape_bind_core_steps() {
	// The 'activatePlugin' step.
	add_action( 'activate_plugin', 'zape_step_activate_plugin', 10, 2 );

	// The 'activateTheme' step.
	add_action( 'switch_theme', 'zape_step_activate_theme', 10, 3 );

	// NOT SUPPORTED.
	// The wp-config.php steps.
	// The 'defineSiteUrl' step.
	// The 'defineWpConfigConsts' step.
	// The 'enableMultisite' step.
	// The 'importThemeStarterContent' step.
	// The 'importWordPressFiles' step.
	// The 'importWxr' step.

	// The 'installPlugin' step.
	// See zape_step_activate_plugin().

	// The 'installTheme' step.
	// See zape_step_activate_theme().

	// The 'login' step.
	// Added by default.

	// NOT SUPPORTED.
	// The 'resetData' step.

	// NOT SUPPORTED.
	// The 'runPHP' step.
	// The 'runPHPWithOptions' step.

	// NOT SUPPORTED.
	// The 'runSql' step.

	// The 'setSiteLanguage' step.

	// 'setSiteOptions' step.
	add_action( 'updated_option', 'zape_step_set_site_options', 10, 3 );
	add_action( 'added_option', 'zape_added_option', 10, 2 );
	add_action( 'update_site_option', 'zape_step_set_site_options', 10, 4 );
	add_action( 'add_site_option', 'zape_add_site_option', 10, 3 );

	// The 'unzip' step.

	// The 'updateUserMeta' step.
	add_action( 'updated_user_meta', 'zape_step_update_user_meta', 10, 4 );
	add_action( 'added_user_meta', 'zape_step_update_user_meta', 10, 4 );

	// The 'wp-cli' step.

	// Filesystem steps are not supported.
	// 'cp' step.
	// 'mkdir' step.
	// 'mv' step.
	// 'rm' step.
	// 'rmdir' step.
	// 'writeFile' step.
	// 'writeFiles' step.
}

/**
 * Bind the additional steps.
 */
function zape_bind_additional_steps() {
	// Save or update a term for "save term" step.
	add_action( 'saved_term', 'zape_step_save_term', 10, 5 );

	// Delete a post for "delete post" step.
	add_action( 'delete_post', 'zape_step_delete_post', 10, 2 );

	// Save or update a post for "save post" step.
	add_action( 'save_post', 'zape_step_save_post', 10, 3 );
	add_filter( 'wp_insert_post_data', 'zape_insert_post_data', 10, 4 );
}

/**
 * Initialize the Play Editor plugin.
 */
function zape_init() {
	$blueprint = zape_get_blueprint();

	if ( false === $blueprint ) {
		$blueprint_content = zape_load_blueprint();

		if ( $blueprint_content ) {
			zape_set_blueprint( $blueprint_content );
		} else {
			zape_set_blueprint();
		}
	}

	zape_bind_core_steps();
	zape_bind_additional_steps();
}

add_action( 'init', 'zape_init', 0 );

/**
 * Add the submenu page for the Play Editor.
 */
function zape_options_page() {
	$blueprint = zape_get_blueprint();
	$count     = 0;

	foreach ( $blueprint['steps'] as $step ) {
		if ( array_key_exists( '_za_pe', $step ) ) {
			++$count;
		}
	}

	$menu_title = 0 === $count ?
		esc_html__( 'Play Editor', 'play-editor' ) :
		sprintf( esc_html__( 'Play Editor %s', 'play-editor' ), '<span class="menu-counter">' . $count . '</span>' );

	add_menu_page(
		__( 'Play Editor', 'play-editor' ),
		$menu_title,
		'manage_options',
		'za_pe',
		'zape_options_page_html',
		'dashicons-coffee',
	);
}

// Add the submenu page for the Play Editor.
add_action( 'admin_menu', 'zape_options_page' );

/**
 * Blueprint AJAX handler.
 */
function zape_ajax_get_blueprint() {
	if ( ! check_ajax_referer( 'zape_get_blueprint', '_wpnonce', false ) ) {
		wp_die( 'Invalid nonce' );
	}

	$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';

	$blueprint_obj = zape_clean_blueprint( zape_get_blueprint() );
	$message       = array(
		'blueprint' => wp_json_encode( $blueprint_obj, JSON_PRETTY_PRINT ) ?? '{}',
		'type'      => $type,
		'url'       => zape_get_playground_url( $blueprint_obj, 'builder-open' === $type ),
	);

	post_message_to_js( wp_json_encode( $message ) );

	// Nothing to return.
	die();
}

add_action( 'wp_ajax_zape_get_blueprint', 'zape_ajax_get_blueprint' );
