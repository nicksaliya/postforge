<?php

class Postforge {

    protected $loader;
    protected $plugin_name = 'postforge';
    protected $version = '1.0.0';

    public function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'class-postforge-loader.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-postforge-i18n.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '/admin/class-postforge-admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '/public/class-postforge-public.php';

        $this->loader = new Postforge_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new Postforge_i18n();
        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
    }

    private function define_admin_hooks() {
        $plugin_admin = new Postforge_Admin( $this->plugin_name, $this->version );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
    }

    private function define_public_hooks() {
        $plugin_public = new Postforge_Public( $this->plugin_name, $this->version );
        $this->loader->add_action( 'init', $plugin_public, 'register_shortcode' );
    }

    public function run() {
        $this->loader->run();
    }
}
