<?php

if (!defined('ABSPATH')) {
    exit;
}

class GoogleOAuthLoginStandalone
{
    private const ALLOWED_DOMAIN = 'oremis.fr';

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
        $state = $this->generate_state();
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
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
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
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
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new Exception('Erreur lors de la récupération des informations utilisateur');
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function enqueue_styles()
    {
        wp_enqueue_style(
            'google-oauth-style',
            GOOGLE_OAUTH_LOGIN_PLUGIN_URL . 'assets/css/style.css',
            [],
            google_oauth_asset_version('assets/css/style.css')
        );
    }

    public function add_google_login_button()
    {
        if (empty($this->client_id) || empty($this->client_secret)) {
            return;
        }
        $auth_url = $this->create_auth_url();
        ?>
        <div class="google-login-container">
            <a href="<?php echo esc_url($auth_url); ?>" class="google-login-button">
                <img src="<?php echo esc_url(GOOGLE_OAUTH_LOGIN_PLUGIN_URL . 'assets/images/google-icon.svg'); ?>" alt="Google Icon">
                Se connecter avec Google
            </a>
        </div>
        <?php
    }

    public function handle_oauth_callback()
    {
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';

        if (!empty($error) || empty($code) || empty($state) || !$this->validate_state($state)) {
            wp_safe_redirect(wp_login_url() . '?login=failed');
            exit;
        }

        try {
            $access_token = $this->get_token($code);
            $user_info = $this->get_user_info($access_token);

            if (!isset($user_info['email'])) {
                throw new Exception('Email non trouvé');
            }

            if (isset($user_info['email_verified']) && $user_info['email_verified'] !== true && $user_info['email_verified'] !== 'true' && $user_info['email_verified'] !== 1) {
                throw new Exception('Email non vérifié');
            }

            $email = sanitize_email($user_info['email']);
            if ($email === '') {
                throw new Exception('Email invalide');
            }
            $email = strtolower($email);
            $name = $user_info['name'] ?? '';
            $given_name = $user_info['given_name'] ?? '';
            $family_name = $user_info['family_name'] ?? '';

            if (!$this->is_email_allowed($email)) {
                throw new Exception('Domaine email non autorisé');
            }

            // Vérifier si l'utilisateur existe
            $user = get_user_by('email', $email);

            if (!$user) {
                if (!$this->is_auto_create_enabled()) {
                    throw new Exception('Création automatique désactivée');
                }
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
                    'last_name' => $family_name,
                ]);

                $user = get_user_by('ID', $user_id);
            }

            // Connecter l'utilisateur
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);

            wp_safe_redirect(admin_url());
            exit;
        } catch (Exception $e) {
            wp_safe_redirect(wp_login_url() . '?login=failed');
            exit;
        }
    }

    private function generate_username($email)
    {
        $username = substr($email, 0, strpos($email, '@'));
        $base_username = sanitize_user($username, true);
        if ($base_username === '') {
            $base_username = 'user';
        }
        $username = $base_username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    private function generate_state()
    {
        $state = wp_generate_password(32, false, false);
        set_transient('google_oauth_state_' . $state, 1, 15 * MINUTE_IN_SECONDS);
        return $state;
    }

    private function validate_state($state)
    {
        $key = 'google_oauth_state_' . $state;
        $valid = get_transient($key);
        if ($valid !== false) {
            delete_transient($key);
            return true;
        }
        return false;
    }

    private function is_auto_create_enabled()
    {
        return get_option('google_oauth_allow_create', '0') === '1';
    }

    private function is_email_allowed($email)
    {
        $domain = substr(strrchr($email, '@'), 1);
        if ($domain === false || $domain === '') {
            return false;
        }
        return strtolower($domain) === self::ALLOWED_DOMAIN;
    }
}
