# Google OAuth Login (OREMIS)

A WordPress plugin that allows users to log in using their Google accounts via OAuth. Designed by the **OREMIS** association to simplify secure access to WordPress websites.

---

## 🛠️ Features

- Secure authentication via Google OAuth.
- Optional automatic WordPress user creation for valid emails.
- Admin page to configure **Client ID** and **Client Secret**.
- Modern and user-friendly interface.

---

## 📦 Installation

1. **Download the plugin**:
   - Clone or download this GitHub repository.

2. **Install via WordPress**:
   - Log in to your WordPress admin dashboard.
   - Go to `Plugins > Add New`.
   - Click **Upload Plugin** and upload the plugin's ZIP file.
   - Activate the plugin.

3. **Manual Installation**:
   - Download the plugin folder.
   - Move it to the `/wp-content/plugins/` directory in your WordPress installation.
   - Activate the plugin via `Plugins > Installed Plugins` in the WordPress dashboard.

---

## ⚙️ Configuration

1. **Obtain Google OAuth credentials**:
   - Go to the [Google Cloud Console](https://console.cloud.google.com/).
   - Create or select an existing project.
   - Navigate to `APIs & Services > Credentials`.
   - Click **Create Credentials** and select **OAuth 2.0 Client IDs**.
   - Configure the OAuth consent screen if required.
   - Add the redirect URI: `https://your-site.com/wp-admin/admin-ajax.php?action=google_oauth_callback`.

2. **Configure the plugin**:
   - Go to `Settings > Google OAuth`.
   - Enter the **Client ID** and **Client Secret** obtained from the Google Cloud Console.
   - Click **Save Changes**.

---

## 🚀 Usage

1. **Login page**:
   - The **Sign in with Google** button will automatically appear on the WordPress login page.

2. **User Login**:
   - Users can click the button to authenticate via Google.
   - If their email matches an existing WordPress user, they will be logged in.
   - If the email is unknown, the login will fail (no new user will be created automatically).

---

## 🎨 Customization

1. **Change styles**:
   - Modify the CSS file: `assets/css/style.css`.

2. **Customize the login button**:
   - The CSS file contains styles to personalize the appearance of the button on the login page.

---

## 🐞 Troubleshooting

### Common Issues:
- **"Client ID or Client Secret missing"**:
  - Verify that the Google credentials are correctly configured in `Settings > Google OAuth`.

- **"Invalid redirect URI"**:
  - Ensure the redirect URI configured in the Google Cloud Console matches exactly with your WordPress site.

- **"Missing validation code"**:
  - Check that the user has authorized the application via the Google consent screen.

### Enable WordPress Debug Mode:
Add the following lines to your `wp-config.php` file:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check the ```wp-content/debug.log``` file for additional details.

### ❤️ Credits

This plugin was designed and developed by the OREMIS association.
To learn more about our projects, visit our website: https://oremis.fr.

Contact us at: contact@oremis.fr