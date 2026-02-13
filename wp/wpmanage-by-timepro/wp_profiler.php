<?php

class WPProfiler {
    protected static $data = [];

    public static function collect_data() {
        global $wpdb;

        self::$data = [
            'url' => get_site_url(),
            'active_theme' => static::get_theme_name(),
            'wp_version'    => get_bloginfo('version'),
            'php_version'   => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'admin_username' => self::get_admin_username(),
            'admin_email' => self::get_admin_email(),
            'site_email'   => get_option('admin_email'),
            'plugin_version' => WPMANAGE_VERSION,
            'last_backup'     => self::get_updraft_last_backup_time(),
        ];
    }
    
    private static function get_updraft_last_backup_time() {
        $backup_history = get_option('updraft_backup_history');

        if (!empty($backup_history) && is_array($backup_history)) {
            $last_backup = max(array_keys($backup_history)); // Get the latest backup timestamp
            return date('Y-m-d H:i:s', $last_backup); // Convert to readable format
        }

        return null;
    }

    private static function get_admin_username() {
        $admin = get_user_by('ID', 1);
        return ($admin) ? $admin->user_login : 'Unknown';
    }

    private static function get_admin_email() {
        $admin = get_user_by('ID', 1);
        return ($admin) ? $admin->user_email : 'Unknown';
    }

    private static function get_theme_name(){
        $theme = wp_get_theme();

        return $theme->get('Name');
    }

    public static function get_data() {
        return self::$data;
    }

    public static function send_data() {
        if (get_transient(LAST_SENT_AT)) {
            return; // Exit if data was sent recently (within 12 hours)
        }

        // echo 'Sending data';

        $data = self::$data;
        $json_data = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, TIMEPRO_URL.'/api/wp-data?passwd=ThisIsGood');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        // exit($response);

        // Set transient to prevent sending data again for 12 hours
        if ($response) {
            set_transient(LAST_SENT_AT, true, 12 * HOUR_IN_SECONDS);
        }
    }
}