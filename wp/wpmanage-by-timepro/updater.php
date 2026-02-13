<?php

class WPManage_Updater {
    private $update_url;

    public function __construct($update_url) {
        $this->update_url = $update_url;
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_details'), 10, 3);
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $response = wp_remote_get($this->update_url);
        if (is_wp_error($response)) {
            return $transient;
        }

        $update_data = json_decode(wp_remote_retrieve_body($response));
        if (!$update_data || !isset($update_data->new_version)) {
            return $transient;
        }

        if (version_compare(WPMANAGE_VERSION, $update_data->new_version, '<')) {
            $transient->response[plugin_basename(__FILE__)] = (object) array(
                'slug'        => 'wpmanage-by-timepro',
                'plugin'      => plugin_basename(__FILE__),
                'new_version' => $update_data->new_version,
                'url'         => $update_data->homepage,
                'package'     => $update_data->download_url,
            );
        }

        return $transient;
    }

    public function plugin_details($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== 'wpmanage-by-timepro') {
            return $result;
        }

        $response = wp_remote_get($this->update_url);
        if (is_wp_error($response)) {
            return $result;
        }

        $update_data = json_decode(wp_remote_retrieve_body($response));
        if (!$update_data) {
            return $result;
        }

        return (object) array(
            'name'        => 'WPManage by Timepro',
            'slug'        => 'wpmanage-by-timepro',
            'version'     => $update_data->new_version,
            'author'      => '<a href="'.TIMEPRO_URL.'">Timepro</a>',
            'homepage'    => $update_data->homepage,
            'download_link' => $update_data->download_url,
            'sections'    => array(
                'description' => $update_data->description,
                'changelog'   => $update_data->changelog,
            ),
        );
    }
}

// Initialize updater
new WPManage_Updater(WPMANAGE_UPDATE_URL);