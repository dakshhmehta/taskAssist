<?php

/**
 * Plugin Name: WPManage by Timepro
 * Plugin URI: https://beta.timepro.in
 * Description: A WordPress management plugin connecting to the Timepro WP Dashboard
 * Version: 0.0.3
 * Author: Timepro
 * Author URI: https://yourdomain.com
 * License: GPL v2 or later
 * Text Domain: wpmanage-by-timepro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin version
define('TIMEPRO_URL', 'https://beta.timepro.in');
define('WPMANAGE_VERSION', '0.0.3');
define('WPMANAGE_UPDATE_URL', TIMEPRO_URL . '/wp/wp-plugin-info.json');
define('LAST_SENT_AT', 'managewp_last_sent_at');

// Include the updater
require_once plugin_dir_path(__FILE__) . 'updater.php';

require_once plugin_dir_path(__FILE__) . 'wp_profiler.php';

// Initialize the profiler
function wpmanage_init_wp_profiler()
{
    WPProfiler::collect_data();


    if ($_GET['_pull_profile_data'] == 'yes') {
        exit(json_encode(WPProfiler::get_data()));
    }

    // Send the data every 12 hours
    if (!get_transient(LAST_SENT_AT)) {
        WPProfiler::send_data();
    }

    // Example usage
    if ($_GET['_profile_data'] == 1) {
        delete_transient(LAST_SENT_AT);
        exit('<pre>' . var_dump(WPProfiler::get_data()) . '</pre>');
    }
}
add_action('init', 'wpmanage_init_wp_profiler');
