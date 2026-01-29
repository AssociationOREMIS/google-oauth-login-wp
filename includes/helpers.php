<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('google_oauth_asset_version')) {
    function google_oauth_asset_version($relative_path)
    {
        $path = GOOGLE_OAUTH_LOGIN_PLUGIN_DIR . ltrim($relative_path, '/');
        if (file_exists($path)) {
            return (string) filemtime($path);
        }
        return '1.0.0';
    }
}
