<?php

if (!defined('ABSPATH')) {
    exit;
}

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
        GOOGLE_OAUTH_LOGIN_PLUGIN_URL . 'assets/js/admin.js',
        [],
        google_oauth_asset_version('assets/js/admin.js'),
        true
    );
}

function google_oauth_enqueue_admin_styles($hook)
{
    if ($hook === 'settings_page_google-oauth-settings') {
        wp_enqueue_style(
            'google-oauth-admin-style',
            GOOGLE_OAUTH_LOGIN_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            google_oauth_asset_version('assets/css/admin-style.css')
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
        wp_verify_nonce($_POST['google_oauth_settings_nonce'], 'google_oauth_settings') &&
        current_user_can('manage_options')
    ) {
        update_option('google_oauth_client_id', sanitize_text_field(wp_unslash($_POST['client_id'] ?? '')));
        update_option('google_oauth_client_secret', sanitize_text_field(wp_unslash($_POST['client_secret'] ?? '')));
        update_option('google_oauth_allow_create', isset($_POST['allow_create']) ? '1' : '0');

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
                <tr>
                    <th><label for="allow_create">Création automatique</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="allow_create" name="allow_create" value="1" <?php checked('1', get_option('google_oauth_allow_create', '0')); ?>>
                            Autoriser la création automatique d'utilisateurs WordPress
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Domaine autorisé</th>
                    <td>
                        <p class="description">Seuls les comptes Google <strong>@oremis.fr</strong> sont autorisés.</p>
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
