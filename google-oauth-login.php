<?php

/**
 * Plugin Name: Google OAuth Login Standalone (OREMIS)
 * Plugin URI: https://oremis.fr
 * Description: Permet aux bénévoles de l'association OREMIS de se connecter avec leur compte Google
 * Version: 1.1.0
 * Author: Lucas VOLET
 * Author URI: https://oremis.fr
 * License: GPL-2.0+
 * Requires at least: 5.5
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GOOGLE_OAUTH_LOGIN_PLUGIN_FILE', __FILE__);
define('GOOGLE_OAUTH_LOGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GOOGLE_OAUTH_LOGIN_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GOOGLE_OAUTH_LOGIN_PLUGIN_DIR . 'includes/helpers.php';
require_once GOOGLE_OAUTH_LOGIN_PLUGIN_DIR . 'includes/class-google-oauth-login.php';
require_once GOOGLE_OAUTH_LOGIN_PLUGIN_DIR . 'includes/admin-settings.php';
require_once GOOGLE_OAUTH_LOGIN_PLUGIN_DIR . 'includes/class-google-oauth-updater.php';

add_action('plugins_loaded', function () {
    new GoogleOAuthLoginStandalone();
    $GLOBALS['google_oauth_updater'] = new GoogleOAuthUpdater();
});
