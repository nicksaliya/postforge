<?php
/**
 * Plugin Name:       PostForge – Frontend Post Builder
 * Description:       Easily create dynamic frontend post submission forms and customizable post grids with shortcode generators. PostForge lets you visually build forms for any post type, control field visibility, set required fields, enable AJAX submissions, redirect after submit, and much more. Display your content using flexible grid or list layouts with filters, video indicators, and advanced query options – all without writing code.
 * Version:           1.0.0
 * Author:            Rahul Dungarani / Volcone Web Solutions
 * Text Domain:       volcone.com
 * Domain Path:       /languages
 * Author URI: http://volcone.com/
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function activate_postforge() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-postforge-activator.php';
    Postforge_Activator::activate();
}

function deactivate_postforge() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-postforge-deactivator.php';
    Postforge_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_postforge' );
register_deactivation_hook( __FILE__, 'deactivate_postforge' );

require plugin_dir_path( __FILE__ ) . 'includes/class-postforge.php';

function run_postforge() {
    $plugin = new Postforge();
    $plugin->run();
}
run_postforge();
