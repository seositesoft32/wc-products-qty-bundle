<?php
/**
 * Plugin initialisation class.
 *
 * Entry point that instantiates the admin and frontend controllers and
 * registers their hooks. A single instance is stored in
 * `$GLOBALS['wpqb_plugin_init']` so the plugin can be accessed externally
 * if needed.
 *
 * @package   WC_Products_Qty_Bundle
 * @subpackage Inc
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPQB_Plugin_Init
 *
 * Bootstraps the plugin by creating one instance each of WPQB_Plugin_Admin
 * and WPQB_Plugin_Frontend and calling their `hooks()` methods. Also
 * registers itself on the WordPress `init` action so subclasses can hook in
 * after WordPress is fully loaded.
 *
 * @since 1.0.0
 */
class WPQB_Plugin_Init {

    /**
     * The admin controller instance.
     *
     * @since 1.0.0
     *
     * @var WPQB_Plugin_Admin
     */
    private $admin;

    /**
     * The frontend controller instance.
     *
     * @since 1.0.0
     *
     * @var WPQB_Plugin_Frontend
     */
    private $frontend;

    /**
     * Constructor.
     *
     * Instantiates both sub-controllers and immediately registers their hooks
     * so no further setup step is required by the caller.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->admin    = new WPQB_Plugin_Admin();
        $this->frontend = new WPQB_Plugin_Frontend();

        $this->admin->hooks();
        $this->frontend->hooks();
    }

    /**
     * WordPress `init` action callback.
     *
     * Reserved for any logic that must run after WordPress has finished loading
     * (e.g. custom post type or taxonomy registration). Currently empty.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function init() {
    }
}

// Instantiate the plugin once, guarded against duplicate instantiation.
if ( ! isset( $GLOBALS['wpqb_plugin_init'] ) && class_exists( 'WPQB_Plugin_Init' ) ) {
    $GLOBALS['wpqb_plugin_init'] = new WPQB_Plugin_Init();
    add_action( 'init', [ $GLOBALS['wpqb_plugin_init'], 'init' ] );
}
