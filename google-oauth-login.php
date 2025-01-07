<?php

/**
 * Plugin Name: Google OAuth Login
 * Plugin URI: https://oremis.fr
 * Description: Permet aux utilisateurs de se connecter avec leur compte Google
 * Version: 1.0.0
 * Author: Lucas VOLET
 * Author URI: https://oremis.fr
 * License: GPL-2.0+
 */

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

// Chargement de l'autoloader de Composer
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use Google\Client;
use Google\Service\Oauth2;

class GoogleOAuthLogin
{
    private $client;

    public function __construct()
    {
        add_action('init', [$this, 'init_google_client']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('login_form', [$this, 'add_google_login_button']);
        add_action('wp_ajax_nopriv_google_oauth_callback', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_google_oauth_callback', [$this, 'handle_oauth_callback']);
    }

    public function init_google_client()
    {
        $this->client = new Client();
        $this->client->setClientId(get_option('google_oauth_client_id'));
        $this->client->setClientSecret(get_option('google_oauth_client_secret'));
        // Utiliser l'URL AJAX de WordPress
        $this->client->setRedirectUri(admin_url('admin-ajax.php?action=google_oauth_callback'));
        $this->client->addScope('email');
        $this->client->addScope('profile');
    }

    public function enqueue_styles()
    {
        wp_enqueue_style(
            'google-oauth-style',
            plugin_dir_url(__FILE__) . 'assets/css/style.css',
            [],
            time() // Utilisation d'un timestamp pour éviter le cache
        );
    }

    public function add_google_login_button()
    {
        $auth_url = $this->client->createAuthUrl();
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
            $token = $this->client->fetchAccessTokenWithAuthCode($_GET['code']);
            $this->client->setAccessToken($token);

            $google_oauth = new Oauth2($this->client);
            $google_user_info = $google_oauth->userinfo->get();

            $email = $google_user_info->getEmail();
            $name = $google_user_info->getName();

            // Vérifier si l'utilisateur existe déjà
            $user = get_user_by('email', $email);

            if (!$user) {
                // Créer un nouvel utilisateur
                $username = $this->generate_username($email);
                $random_password = wp_generate_password();

                $user_id = wp_create_user($username, $random_password, $email);

                if (is_wp_error($user_id)) {
                    wp_redirect(wp_login_url() . '?login=failed');
                    exit;
                }

                // Mettre à jour les informations de l'utilisateur
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $name,
                    'first_name' => $google_user_info->getGivenName(),
                    'last_name' => $google_user_info->getFamilyName()
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
    new GoogleOAuthLogin();
});

// Ajout du menu d'administration
add_action('admin_menu', function () {
    add_options_page(
        'Paramètres Google OAuth',
        'Google OAuth (OREMIS)',
        'manage_options',
        'google-oauth-settings',
        'render_settings_page'
    );
});

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
