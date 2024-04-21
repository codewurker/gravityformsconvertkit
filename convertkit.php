<?php
/*
Plugin Name: Gravity Forms ConvertKit Add-On
Plugin URI: https://gravityforms.com
Description: Integrates Gravity Forms with ConvertKit, allowing users to automatically be subscribed to ConvertKit on form submission.
Version: 1.0.0
Author: Gravity Forms
Author URI: https://gravityforms.com
License: GPL-3.0+
Text Domain: gravityformsconvertkit
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2023 Rocketgenius Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses.

*/

defined( 'ABSPATH' ) || die();

// Defines the current version of the Gravity Forms ConvertKit Add-On.
define( 'GF_CONVERTKIT_VERSION', '1.0.0' );

// Defines the minimum version of Gravity Forms required to run Gravity Forms ConvertKit Add-On.
define( 'GF_CONVERTKIT_MIN_GF_VERSION', '2.0' );

// After Gravity Forms is loaded, load the Add-On.
add_action( 'gform_loaded', array( 'GF_ConvertKit_Bootstrap', 'load_addon' ), 5 );

/**
 * Loads the Gravity Forms ConvertKit Add-On.
 *
 * Includes the main class and registers it with GFAddOn.
 *
 * @since 1.0
 */
class GF_ConvertKit_Bootstrap {

	/**
	 * Loads the required files.
	 *
	 * @since  1.0
	 */
	public static function load_addon() {

		// Requires the class file.
		require_once plugin_dir_path( __FILE__ ) . '/class-gf-convertkit.php';

		// Registers the class name with GFAddOn.
		GFAddOn::register( 'GF_ConvertKit' );
	}

}

register_activation_hook( __FILE__, 'deactivate_legacy_convertkit' );

/**
 * Deactivate the legacy plugin when ours is activated.
 *
 * @since 1.0
 *
 * @return void
 */
function deactivate_legacy_convertkit() {
	if ( ! defined( 'CKGF_PLUGIN_BASENAME' ) ) {
		return;
	}

	$basename = CKGF_PLUGIN_BASENAME;

	if ( ! is_plugin_active( $basename ) ) {
		return;
	}

	deactivate_plugins( $basename, true );

	$message_name = 'gf_convertkit_disable_message';

	\GFCommon::add_dismissible_message( esc_html__( 'In order to prevent conflicts, we disabled the existing ConvertKit for Gravity Forms plugin.', 'gravityformsconvertkit'), $message_name, 'warning', false, true, 'site-wide' );
}

/**
 * Returns an instance of the GF_ConvertKit class
 *
 * @since  1.0
 *
 * @return GF_ConvertKit|bool An instance of the GF_ConvertKit class
 */
function gf_convertkit() {
	return class_exists( 'GF_ConvertKit' ) ? GF_ConvertKit::get_instance() : false;
}
