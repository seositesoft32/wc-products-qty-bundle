<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPQB_Plugin_Init
{
    /**
     * @var WPQB_Plugin_Admin
     */
    private $admin;

    /**
     * @var WPQB_Plugin_Frontend
     */
    private $frontend;

    public function __construct()
    {
        $this->admin = new WPQB_Plugin_Admin();
        $this->frontend = new WPQB_Plugin_Frontend();

        $this->admin->hooks();
        $this->frontend->hooks();
    }

    public function init()
    {
    }
}

if (!isset($GLOBALS['wpqb_plugin_init']) && class_exists('WPQB_Plugin_Init')) {
    $GLOBALS['wpqb_plugin_init'] = new WPQB_Plugin_Init();
    add_action('init', [$GLOBALS['wpqb_plugin_init'], 'init']);
}
