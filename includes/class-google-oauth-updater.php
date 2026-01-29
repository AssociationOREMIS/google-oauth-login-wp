<?php

if (!defined('ABSPATH')) {
    exit;
}

class GoogleOAuthUpdater
{
    private $plugin_slug;
    private $plugin_file;
    private $repository;
    private $github_api_url;
    private $transient_key;
    private $cache_expiration = 12 * HOUR_IN_SECONDS;

    public function __construct()
    {
        $this->plugin_slug = 'google-oauth-login-wp';
        $this->plugin_file = plugin_basename(GOOGLE_OAUTH_LOGIN_PLUGIN_FILE);
        $this->repository = 'AssociationOREMIS/google-oauth-login-wp';
        $this->github_api_url = "https://api.github.com/repos/{$this->repository}";
        $this->transient_key = 'google_oauth_github_api_cache';

        // Hooks for update mechanism
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('plugin_action_links_' . plugin_basename(GOOGLE_OAUTH_LOGIN_PLUGIN_FILE), function ($links) {
            // URL de la page des paramètres
            $settings_link = '<a href="' . admin_url('options-general.php?page=google-oauth-settings') . '">Paramètres</a>';

            // Ajoute le lien "Paramètres" en haut de la liste des actions
            array_unshift($links, $settings_link);

            return $links;
        });

        add_action('upgrader_process_complete', [$this, 'purge_update_cache'], 10, 2);
    }

    private function get_plugin_data()
    {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return get_plugin_data(GOOGLE_OAUTH_LOGIN_PLUGIN_FILE);
    }

    private function get_github_data()
    {
        $cached_data = get_transient($this->transient_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $args = [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            ],
            'timeout' => 15,
        ];

        $response = wp_remote_get($this->github_api_url . '/releases/latest', $args);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data)) {
            return false;
        }

        set_transient($this->transient_key, $data, $this->cache_expiration);
        return $data;
    }

    public function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $plugin_data = $this->get_plugin_data();
        $current_version = $plugin_data['Version'];
        $github_data = $this->get_github_data();

        if ($github_data && isset($github_data['tag_name'])) {
            $new_version = ltrim($github_data['tag_name'], 'v');

            if (version_compare($new_version, $current_version, '>')) {
                $plugin_details = (object) [
                    'slug' => $this->plugin_slug,
                    'new_version' => $new_version,
                    'url' => $github_data['html_url'],
                    'package' => $github_data['zipball_url'],
                    'tested' => $plugin_data['Tested up to'] ?? '',
                    'requires' => $plugin_data['Requires at least'] ?? '',
                    'requires_php' => $plugin_data['Requires PHP'] ?? '',
                ];

                $transient->response[$this->plugin_file] = $plugin_details;
            }
        }

        return $transient;
    }

    public function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== $this->plugin_slug) {
            return $result;
        }

        $github_data = $this->get_github_data();
        $plugin_data = $this->get_plugin_data();

        if (!$github_data) {
            return $result;
        }

        return (object) [
            'name' => $plugin_data['Name'],
            'slug' => $this->plugin_slug,
            'version' => ltrim($github_data['tag_name'], 'v'),
            'author' => $plugin_data['Author'],
            'author_profile' => $plugin_data['AuthorURI'],
            'homepage' => $plugin_data['PluginURI'],
            'requires' => $plugin_data['RequiresWP'] ?? '5.0',
            'tested' => $plugin_data['Tested up to'] ?? '',
            'requires_php' => $plugin_data['RequiresPHP'] ?? '7.4',
            'downloaded' => 0,
            'last_updated' => $github_data['published_at'],
            'sections' => [
                'description' => $plugin_data['Description'],
                'changelog' => $this->get_changelog($github_data),
            ],
            'download_link' => $github_data['zipball_url'],
        ];
    }

    private function get_changelog($github_data)
    {
        $changelog = "= {$github_data['tag_name']} =\n";
        $changelog .= "Released: " . date('Y-m-d', strtotime($github_data['published_at'])) . "\n\n";
        $changelog .= $github_data['body'] ?? 'No changelog provided.';
        return $changelog;
    }

    public function purge_update_cache($upgrader_object, $options)
    {
        if (
            $options['action'] === 'update' &&
            $options['type'] === 'plugin' &&
            isset($options['plugins'])
        ) {
            if (in_array($this->plugin_file, $options['plugins'])) {
                delete_transient($this->transient_key);
            }
        }
    }
}
