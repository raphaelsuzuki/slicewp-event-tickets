<?php
/**
 * Plugin Name: SliceWP – Event Tickets Integration
 * Plugin URI:  https://github.com/your-org/slicewp-event-tickets
 * Description: Tracks affiliate sales from The Events Calendar / Event Tickets (Tickets Commerce) inside SliceWP.
 * Version:     0.9.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * Text Domain: slicewp-event-tickets
 * License:     GPL2
 *
 * Requires Plugins: slicewp, the-events-calendar, event-tickets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SLICEWP_ET_VERSION',  '0.9.0' );
define( 'SLICEWP_ET_FILE',     __FILE__ );
define( 'SLICEWP_ET_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SLICEWP_ET_DIR_URL',  plugin_dir_url( __FILE__ ) );


/**
 * Boot: load integration files after SliceWP and Event Tickets are fully loaded.
 */
function slicewp_et_load() {

	// SliceWP must be present to register an integration.
	if ( ! function_exists( 'slicewp' ) ) {
		return;
	}

	require_once SLICEWP_ET_DIR_PATH . 'includes/class-integration-event-tickets.php';
	require_once SLICEWP_ET_DIR_PATH . 'includes/functions.php';

	// Register the integration so SliceWP lists it in settings.
	add_filter( 'slicewp_register_integration', 'slicewp_et_register_integration' );

	// Tell SliceWP that the Event Tickets plugin is active.
	add_filter( 'slicewp_is_integration_plugin_active_event_tickets', 'slicewp_et_is_plugin_active' );

	// Load hooks only when the integration is enabled in SliceWP settings.
	add_action( 'slicewp_include_files_late', 'slicewp_et_maybe_load_hooks' );

}
// Must run before SliceWP loads integrations on plugins_loaded priority 15.
add_action( 'plugins_loaded', 'slicewp_et_load', 9 );


/**
 * Register the integration class with SliceWP.
 *
 * @param array $integrations
 * @return array
 */
function slicewp_et_register_integration( $integrations ) {
	$integrations['event_tickets'] = 'SliceWP_Integration_Event_Tickets';
	return $integrations;
}


/**
 * Let SliceWP know the plugin is active.
 *
 * @param bool $is_active
 * @return bool
 */
function slicewp_et_is_plugin_active( $is_active = false ) {
	return class_exists( 'Tribe__Tickets__Main' );
}


/**
 * Load the hooks file only when the integration is active in SliceWP settings.
 */
function slicewp_et_maybe_load_hooks() {

	if ( slicewp_is_integration_active( 'event_tickets' ) && slicewp_is_integration_plugin_active( 'event_tickets' ) ) {
		require_once SLICEWP_ET_DIR_PATH . 'includes/functions-hooks.php';
	}

}
