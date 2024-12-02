<?php
/*
Plugin Name: TaskAssist by Romin Interactive
Description: Exposes PHP version, WordPress version, and plugin list via a custom REST API endpoint.
Version: 1.0
Author: Romin Interactive
*/

function custom_api_register_routes() {
    register_rest_route('taskassist/v1', '/site-info', [
        'methods' => 'GET',
        'callback' => 'custom_api_get_site_info',
        'permission_callback' => '__return_true',
    ]);
}

function custom_api_get_site_info() {
    // Get PHP version and WordPress version
    $php_version = phpversion();
    $wp_version = get_bloginfo('version');

    if($_GET['secret'] !== 'Daxdaxdax2910'){
        exit();
    }

    // Get list of installed plugins
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();
    $plugin_list = [];

    foreach ($all_plugins as $plugin_file => $plugin_data) {
        $plugin_list[] = [
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'active' => is_plugin_active($plugin_file),
        ];
    }

    // Prepare response data
    $data = [
        'php_version' => $php_version,
        'wp_version' => $wp_version,
        'plugins' => $plugin_list,
    ];

    return new WP_REST_Response($data, 200);
}

add_action('rest_api_init', 'custom_api_register_routes');
