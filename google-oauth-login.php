<?php

/**
 * Plugin Name: Google OAuth Login Standalone (OREMIS)
 * Plugin URI: https://oremis.fr
 * Description: Permet aux bénévoles de l'association OREMIS de se connecter avec leur compte Google
 * Version: 1.0.4
 * Author: Lucas VOLET
 * Author URI: https://oremis.fr
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoogleOAuthLoginStandalone
{
    private $client_id;
    private $client_secret;
    private $redirect_uri;

    public function __construct()
    {
        add_action('init', [$this, 'init_oauth']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('login_form', [$this, 'add_google_login_button']);
        add_action('wp_ajax_nopriv_google_oauth_callback', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_google_oauth_callback', [$this, 'handle_oauth_callback']);
    }

    public function init_oauth()
    {
        $this->client_id = get_option('google_oauth_client_id');
        $this->client_secret = get_option('google_oauth_client_secret');
        $this->redirect_uri = admin_url('admin-ajax.php?action=google_oauth_callback');
    }

    public function create_auth_url()
    {
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    private function get_token($code)
    {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Erreur lors de la récupération du token');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['access_token'])) {
            throw new Exception('Token invalide');
        }

        return $body['access_token'];
    }

    private function get_user_info($access_token)
    {
        $response = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Erreur lors de la récupération des informations utilisateur');
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function enqueue_styles()
    {
        wp_enqueue_style(
            'google-oauth-style',
            plugin_dir_url(__FILE__) . 'assets/css/style.css',
            [],
            time()
        );
    }

    public function add_google_login_button()
    {
        $auth_url = $this->create_auth_url();
?>
        <div class="google-login-container">
            <a href="<?php echo esc_url($auth_url); ?>" class="google-login-button">
                <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/google-icon.svg'; ?>" alt="Google Icon">
                Se connecter avec Google
            </a>
        </div>
    <?php
    }

    public function handle_oauth_callback()
    {
        if (!isset($_GET['code'])) {
            wp_redirect(wp_login_url());
            exit;
        }

        try {
            $access_token = $this->get_token($_GET['code']);
            $user_info = $this->get_user_info($access_token);

            if (!isset($user_info['email'])) {
                throw new Exception('Email non trouvé');
            }

            $email = $user_info['email'];
            $name = $user_info['name'] ?? '';
            $given_name = $user_info['given_name'] ?? '';
            $family_name = $user_info['family_name'] ?? '';

            // Vérifier si l'utilisateur existe
            $user = get_user_by('email', $email);

            if (!$user) {
                // Créer un nouvel utilisateur
                $username = $this->generate_username($email);
                $random_password = wp_generate_password();

                $user_id = wp_create_user($username, $random_password, $email);

                if (is_wp_error($user_id)) {
                    throw new Exception('Erreur lors de la création de l\'utilisateur');
                }

                // Mettre à jour les informations
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $name,
                    'first_name' => $given_name,
                    'last_name' => $family_name
                ]);

                $user = get_user_by('ID', $user_id);
            }

            // Connecter l'utilisateur
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);

            wp_redirect(admin_url());
            exit;
        } catch (Exception $e) {
            wp_redirect(wp_login_url() . '?login=failed');
            exit;
        }
    }

    private function generate_username($email)
    {
        $username = substr($email, 0, strpos($email, '@'));
        $base_username = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }
}

// Initialisation du plugin
add_action('plugins_loaded', function () {
    new GoogleOAuthLoginStandalone();
});

// Partie administration (identique à votre version)
add_action('admin_menu', function () {
    add_options_page(
        'Paramètres Google OAuth',
        'Google OAuth (OREMIS)',
        'manage_options',
        'google-oauth-settings',
        'render_settings_page'
    );
});

// Les fonctions render_settings_page() et autres restent identiques...

function google_oauth_enqueue_admin_scripts($hook)
{
    // Vérifiez que vous êtes sur la bonne page
    if ($hook !== 'settings_page_google-oauth-settings') {
        return;
    }
    wp_enqueue_script(
        'google-oauth-admin-js',
        plugin_dir_url(__FILE__) . 'assets/js/admin.js',
        [],
        '1.0.0',
        true
    );
}

function google_oauth_enqueue_admin_styles($hook)
{
    if ($hook === 'settings_page_google-oauth-settings') {
        wp_enqueue_style(
            'google-oauth-admin-style',
            plugin_dir_url(__FILE__) . 'assets/css/admin-style.css',
            [],
            '1.0.0'
        );
    }
}

add_action('admin_enqueue_scripts', 'google_oauth_enqueue_admin_styles');
add_action('admin_enqueue_scripts', 'google_oauth_enqueue_admin_scripts');

function render_settings_page()
{
    // Sauvegarder les paramètres
    if (
        isset($_POST['google_oauth_settings_nonce']) &&
        wp_verify_nonce($_POST['google_oauth_settings_nonce'], 'google_oauth_settings')
    ) {

        update_option('google_oauth_client_id', sanitize_text_field($_POST['client_id']));
        update_option('google_oauth_client_secret', sanitize_text_field($_POST['client_secret']));

        echo '<div class="notice notice-success"><p>Paramètres sauvegardés.</p></div>';
    }

    ?>
    <div class="wrap">
        <h2>Paramètres Google OAuth (OREMIS)</h2>
        <form method="post" action="">
            <?php wp_nonce_field('google_oauth_settings', 'google_oauth_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="client_id">Client ID</label></th>
                    <td>
                        <input type="text" id="client_id" name="client_id"
                            value="<?php echo esc_attr(get_option('google_oauth_client_id')); ?>"
                            class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="client_secret">Client Secret</label></th>
                    <td>
                        <input type="password" id="client_secret" name="client_secret"
                            value="<?php echo esc_attr(get_option('google_oauth_client_secret')); ?>"
                            class="regular-text">
                    </td>
                    <td>
                        <button type="button" id="toggle-secret">Afficher</button>
                    </td>
                </tr>




            </table>
            <?php submit_button(); ?>
        </form>
        <div class="credits-card">
            <h2>Association OREMIS</h2>
            <p>
                Ce plugin a été conçu par l'association <strong>OREMIS</strong>.
                Notre mission est de promouvoir l'inclusion scolaire et la lutte contre le harcèlement.
            </p>
            <p>
                Pour plus d'informations, visitez notre site web :
                <a href="https://oremis.fr" target="_blank">www.oremis.fr</a>
            </p>
            <p>Contactez-nous à : <a href="mailto:contact@oremis.fr">contact@oremis.fr</a></p>
        </div>

    </div>

<?php
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
        $this->plugin_file = plugin_basename(__FILE__);
        $this->repository = 'AssociationOREMIS/google-oauth-login-wp';
        $this->github_api_url = "https://api.github.com/repos/{$this->repository}";
        $this->transient_key = 'google_oauth_github_api_cache';

        // Hooks for update mechanism
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
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
        return get_plugin_data(__FILE__);
    }

    private function get_github_data()
    {
        $cached_data = get_transient($this->transient_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $github_token = defined('GITHUB_OAUTH_TOKEN') ? GITHUB_OAUTH_TOKEN : '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';

        $args = [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'timeout' => 15,
        ];

        if (!empty($github_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $github_token;
        }

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

add_action('plugins_loaded', function () {
    global $updater;
    $updater = new GoogleOAuthUpdater();
});
