<?php
/**
 * Appearance, branding, login and front-end bar settings.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_branding_upload')) {
    function sp_branding_upload($field, $prefix = 'brand')
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK || $_FILES[$field]['size'] > 2 * 1024 * 1024) {
            return false;
        }
        $info = @getimagesize($_FILES[$field]['tmp_name']);
        if (!$info) {
            return false;
        }
        $map = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $mime = strtolower($info['mime'] ?? '');
        if (!isset($map[$mime])) {
            return false;
        }
        $dir = GSDATAUPLOADPATH . 'simplepress';
        sp_public_dir($dir);
        $name = $prefix . '-' . date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $map[$mime];
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            return false;
        }
        @chmod($target, 0644);
        sp_log('branding_upload', $name);
        return $name;
    }
}

if (!function_exists('wp_simple_handle_appearance_actions')) {
    function wp_simple_handle_appearance_actions()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (isset($_POST['sp_save_theme'])) {
            sp_require_post('save_appearance_theme');
            $scheme = in_array(($_POST['scheme'] ?? 'classic'), ['classic', 'modern', 'dark'], true) ? $_POST['scheme'] : 'classic';
            wp_simple_save_setting('admin_scheme', $scheme);
            wp_simple_save_setting('grid_admin', !empty($_POST['grid_admin']) ? '1' : '0');
            wp_simple_save_setting('grid_frontend', !empty($_POST['grid_frontend']) ? '1' : '0');
            sp_redirect(sp_admin_url('appearance', 'saved=1'));
        }
        if (isset($_POST['sp_save_branding'])) {
            sp_require_post('save_branding');
            $fields = [
                'branding_enabled' => !empty($_POST['branding_enabled']) ? '1' : '0',
                'branding_footer_name' => substr(strip_tags((string) ($_POST['footer_name'] ?? '')), 0, 200),
                'branding_footer_link' => (string) (sp_url($_POST['footer_link'] ?? '', false) ?: ''),
                'branding_colors' => !empty($_POST['colors_enabled']) ? '1' : '0',
                'branding_accent' => preg_match('/^#[0-9a-f]{3,6}$/i', $_POST['accent'] ?? '') ? $_POST['accent'] : '#2271b1',
                'branding_bg' => preg_match('/^#[0-9a-f]{3,6}$/i', $_POST['bg'] ?? '') ? $_POST['bg'] : '#f0f0f1',
                'branding_header_code' => wp_simple_get_setting('developer_code', '1') === '1' ? substr((string) ($_POST['header_code'] ?? ''), 0, 100000) : '',
                'branding_footer_code' => wp_simple_get_setting('developer_code', '1') === '1' ? substr((string) ($_POST['footer_code'] ?? ''), 0, 100000) : '',
            ];
            if (!empty($_POST['remove_icon'])) {
                $fields['branding_icon'] = '';
            }
            $icon = sp_branding_upload('branding_icon', 'brand');
            if ($icon === false) {
                sp_redirect(sp_admin_url('appearance', 'error=upload'));
            }
            if ($icon !== '') {
                $fields['branding_icon'] = $icon;
            }
            foreach ($fields as $key => $value) {
                wp_simple_save_setting($key, $value);
            }
            sp_log('branding_save', 'appearance updated');
            sp_redirect(sp_admin_url('appearance', 'saved=1'));
        }
        if (isset($_POST['sp_save_login'])) {
            sp_require_post('save_login_options');
            wp_simple_save_setting('login_show_password', !empty($_POST['login_show_password']) ? '1' : '0');
            wp_simple_save_setting('login_hide_forgot', !empty($_POST['login_hide_forgot']) ? '1' : '0');
            $logo = sp_branding_upload('login_logo', 'login');
            if ($logo === false) {
                sp_redirect(sp_admin_url('appearance', 'error=upload'));
            }
            if ($logo !== '') {
                wp_simple_save_setting('login_logo', $logo);
            }
            if (!empty($_POST['remove_login_logo'])) {
                wp_simple_save_setting('login_logo', '');
            }
            sp_redirect(sp_admin_url('appearance', 'saved=1'));
        }
        if (isset($_POST['sp_save_frontend'])) {
            sp_require_post('save_frontend_bar');
            wp_simple_save_setting('frontend_bar', !empty($_POST['frontend_bar']) ? '1' : '0');
            sp_redirect(sp_admin_url('appearance', 'saved=1'));
        }
    }
}

if (!function_exists('sp_hex_shade')) {
    /**
     * Lighten (positive percent) or darken (negative percent) a hex color.
     */
    function sp_hex_shade($hex, $percent)
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#2271b1';
        }
        $out = '#';
        foreach ([0, 2, 4] as $offset) {
            $channel = hexdec(substr($hex, $offset, 2));
            $channel += ($percent / 100) * 255;
            $channel = max(0, min(255, (int) round($channel)));
            $out .= str_pad(dechex($channel), 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }
}

if (!function_exists('sp_admin_scheme_palette')) {
    /**
     * Complete admin color palette for a scheme.
     */
    function sp_admin_scheme_palette($scheme)
    {
        $arrow_light = 'url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%206l5%205%205-5%202%201-7%207-7-7%202-1z%22%20fill%3D%22%23555%22%2F%3E%3C%2Fsvg%3E")';
        $arrow_dark = 'url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%206l5%205%205-5%202%201-7%207-7-7%202-1z%22%20fill%3D%22%23a7aaad%22%2F%3E%3C%2Fsvg%3E")';
        $palettes = [
            'classic' => [
                '--wp-bar-bg' => '#1d2327',
                '--wp-bar-text' => '#f0f0f1',
                '--wp-bar-hover' => '#2c3338',
                '--wp-bar-icon' => '#a7aaad',
                '--wp-sidebar-bg' => '#1d2327',
                '--wp-sidebar-hover' => '#135e96',
                '--wp-sidebar-active' => '#2271b1',
                '--wp-sidebar-text' => '#f0f0f1',
                '--wp-sidebar-icon' => '#a7aaad',
                '--wp-sidebar-icon-hover' => '#72aee6',
                '--wp-sidebar-submenu-bg' => '#13171a',
                '--wp-nav-hover-bg' => '#13171a',
                '--wp-content-bg' => '#f0f0f1',
                '--wp-card-bg' => '#ffffff',
                '--wp-surface-alt' => '#f6f7f7',
                '--wp-hover-bg' => '#f0f0f1',
                '--wp-blue' => '#2271b1',
                '--wp-blue-hover' => '#135e96',
                '--wp-blue-soft' => '#f0f6fc',
                '--wp-text' => '#2c3338',
                '--wp-text-strong' => '#1d2327',
                '--wp-text-muted' => '#646970',
                '--wp-text-faint' => '#8c8f94',
                '--wp-border' => '#c3c4c7',
                '--wp-border-light' => '#f0f0f1',
                '--wp-input-bg' => '#ffffff',
                '--wp-input-border' => '#8c8f94',
                '--wp-on-accent' => '#ffffff',
                '--wp-danger' => '#d63638',
                '--wp-danger-strong' => '#b32d2e',
                '--wp-success' => '#00a32a',
                '--wp-warning' => '#dba617',
                '--wp-warning-strong' => '#d89e00',
                '--wp-warning-alt' => '#e27730',
                '--wp-select-arrow' => $arrow_light,
            ],
            'modern' => [
                '--wp-bar-bg' => '#1e1e1e',
                '--wp-bar-text' => '#f0f0f1',
                '--wp-bar-hover' => '#2c2c2c',
                '--wp-bar-icon' => '#a7aaad',
                '--wp-sidebar-bg' => '#1e1e1e',
                '--wp-sidebar-hover' => '#2c2c2c',
                '--wp-sidebar-active' => '#3858e9',
                '--wp-sidebar-text' => '#f0f0f1',
                '--wp-sidebar-icon' => '#a7aaad',
                '--wp-sidebar-icon-hover' => '#8ab4f8',
                '--wp-sidebar-submenu-bg' => '#15191c',
                '--wp-nav-hover-bg' => '#2c2c2c',
                '--wp-content-bg' => '#f0f0f1',
                '--wp-card-bg' => '#ffffff',
                '--wp-surface-alt' => '#f6f7f7',
                '--wp-hover-bg' => '#eef1ff',
                '--wp-blue' => '#3858e9',
                '--wp-blue-hover' => '#2b46c9',
                '--wp-blue-soft' => '#eef1ff',
                '--wp-text' => '#1e1e1e',
                '--wp-text-strong' => '#1e1e1e',
                '--wp-text-muted' => '#646970',
                '--wp-text-faint' => '#8c8f94',
                '--wp-border' => '#c3c4c7',
                '--wp-border-light' => '#f0f0f1',
                '--wp-input-bg' => '#ffffff',
                '--wp-input-border' => '#8c8f94',
                '--wp-on-accent' => '#ffffff',
                '--wp-danger' => '#d63638',
                '--wp-danger-strong' => '#b32d2e',
                '--wp-success' => '#00a32a',
                '--wp-warning' => '#dba617',
                '--wp-warning-strong' => '#d89e00',
                '--wp-warning-alt' => '#e27730',
                '--wp-select-arrow' => $arrow_light,
            ],
            'dark' => [
                '--wp-bar-bg' => '#101517',
                '--wp-bar-text' => '#f0f0f1',
                '--wp-bar-hover' => '#1e1e1e',
                '--wp-bar-icon' => '#a7aaad',
                '--wp-sidebar-bg' => '#101517',
                '--wp-sidebar-hover' => '#1e1e1e',
                '--wp-sidebar-active' => '#72aee6',
                '--wp-sidebar-text' => '#f0f0f1',
                '--wp-sidebar-icon' => '#a7aaad',
                '--wp-sidebar-icon-hover' => '#9ec2e6',
                '--wp-sidebar-submenu-bg' => '#0b0e10',
                '--wp-nav-hover-bg' => '#2c3338',
                '--wp-content-bg' => '#1e1e1e',
                '--wp-card-bg' => '#2c2c2c',
                '--wp-surface-alt' => '#3c434a',
                '--wp-hover-bg' => '#3c434a',
                '--wp-blue' => '#72aee6',
                '--wp-blue-hover' => '#9ec2e6',
                '--wp-blue-soft' => '#1e2b38',
                '--wp-text' => '#f0f0f1',
                '--wp-text-strong' => '#ffffff',
                '--wp-text-muted' => '#a7aaad',
                '--wp-text-faint' => '#8c8f94',
                '--wp-border' => '#3c434a',
                '--wp-border-light' => '#3c434a',
                '--wp-input-bg' => '#1e1e1e',
                '--wp-input-border' => '#3c434a',
                '--wp-on-accent' => '#101517',
                '--wp-danger' => '#f86368',
                '--wp-danger-strong' => '#ff8a8f',
                '--wp-success' => '#68de7c',
                '--wp-warning' => '#f0c33c',
                '--wp-warning-strong' => '#ffd566',
                '--wp-warning-alt' => '#f0a85c',
                '--wp-select-arrow' => $arrow_dark,
            ],
        ];
        return $palettes[$scheme] ?? $palettes['classic'];
    }
}

if (!function_exists('sp_branding_head')) {
    function sp_branding_head()
    {
        if (is_frontend()) {
            return;
        }
        $scheme = sp_user_pref('admin_scheme', wp_simple_get_setting('admin_scheme', 'classic'));
        if (!in_array($scheme, ['classic', 'modern', 'dark'], true)) {
            $scheme = 'classic';
        }
        $vars = sp_admin_scheme_palette($scheme);
        if (wp_simple_get_setting('branding_colors', '0') === '1') {
            $accent = wp_simple_get_setting('branding_accent', '#2271b1');
            $bg = wp_simple_get_setting('branding_bg', '#f0f0f1');
            if (preg_match('/^#[0-9a-f]{3,6}$/i', $accent)) {
                $vars['--wp-blue'] = $accent;
                $vars['--wp-blue-hover'] = sp_hex_shade($accent, $scheme === 'dark' ? 18 : -12);
            }
            if (preg_match('/^#[0-9a-f]{3,6}$/i', $bg)) {
                $vars['--wp-content-bg'] = $bg;
            }
        }
        $style = ':root{';
        foreach ($vars as $key => $value) {
            $style .= $key . ':' . $value . ';';
        }
        $style .= 'color-scheme:' . ($scheme === 'dark' ? 'dark' : 'light') . ';}';
        echo '<style id="simplepress-vars">' . $style . '</style>';
        if (wp_simple_get_setting('branding_enabled', '0') === '1' && wp_simple_get_setting('developer_code', '1') === '1') {
            $code = wp_simple_get_setting('branding_header_code', '');
            if ($code !== '') { echo "\n" . $code . "\n"; }
        }
        if (wp_simple_get_setting('grid_admin', '0') === '1') {
            echo '<link rel="stylesheet" href="' . sp_e(sp_plugin_url('css/grid.css')) . '">';
        }
    }
}

if (!function_exists('sp_branding_footer')) {
    function sp_branding_footer()
    {
        if (is_frontend() || wp_simple_get_setting('branding_enabled', '0') !== '1') {
            return;
        }
        $name = strip_tags((string) wp_simple_get_setting('branding_footer_name', ''));
        $link = sp_url(wp_simple_get_setting('branding_footer_link', ''), false) ?: '';
        $icon = basename((string) wp_simple_get_setting('branding_icon', ''));
        $iconUrl = ($icon !== '' && is_file(GSDATAUPLOADPATH . 'simplepress/' . $icon)) ? rtrim($GLOBALS['SITEURL'] ?? '', '/') . '/data/uploads/simplepress/' . rawurlencode($icon) : '';
        $payload = ['name' => $name, 'link' => $link, 'icon' => $iconUrl, 'year' => date('Y')];
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        echo '<script>(function(){var d=' . json_encode($payload, $flags) . ';';
        if ($iconUrl !== '') {
            echo 'var s=document.querySelector(".wp-site-name .site-name-text");if(s){var i=document.createElement("img");i.src=d.icon;i.alt=d.name;i.style.cssText="height:18px;width:auto;vertical-align:middle;margin-right:6px";s.parentNode.insertBefore(i,s);}';
        }
        if ($name !== '') {
            echo 'var f=document.querySelector("#footer .footer-left");if(f){f.textContent=d.year+" © "+d.name;}';
        }
        if ($link !== '') {
            echo 'var g=document.querySelector(".gslogo a");if(g){g.href=d.link;}';
        }
        echo '})();</script>';
        if (wp_simple_get_setting('developer_code', '1') === '1') {
            $code = wp_simple_get_setting('branding_footer_code', '');
            if ($code !== '') { echo "\n" . $code . "\n"; }
        }
    }
}
add_action('footer', 'sp_branding_footer');

if (!function_exists('wp_simple_appearance_view')) {
    function wp_simple_appearance_view()
    {
        sp_require_admin();
        wp_simple_handle_appearance_actions();
        $scheme = wp_simple_get_setting('admin_scheme', 'classic');
        $branding = [
            'enabled' => wp_simple_get_setting('branding_enabled', '0') === '1',
            'footer_name' => wp_simple_get_setting('branding_footer_name', ''),
            'footer_link' => wp_simple_get_setting('branding_footer_link', ''),
            'icon' => wp_simple_get_setting('branding_icon', ''),
            'colors' => wp_simple_get_setting('branding_colors', '0') === '1',
            'accent' => wp_simple_get_setting('branding_accent', '#2271b1'),
            'bg' => wp_simple_get_setting('branding_bg', '#f0f0f1'),
            'header_code' => wp_simple_get_setting('branding_header_code', ''),
            'footer_code' => wp_simple_get_setting('branding_footer_code', ''),
        ];
        $iconUrl = ($branding['icon'] !== '' && is_file(GSDATAUPLOADPATH . 'simplepress/' . basename($branding['icon']))) ? rtrim($GLOBALS['SITEURL'] ?? '', '/') . '/data/uploads/simplepress/' . rawurlencode(basename($branding['icon'])) : '';
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('TAB_APPEARANCE', 'Wygląd')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Ustawienia zostały zapisane.</p></div><?php endif; ?>
            <?php if (isset($_GET['error'])): ?><div class="notice notice-error"><p>Nie udało się zapisać ustawień lub przesłać obrazu.</p></div><?php endif; ?>

            <div class="postbox"><div class="postbox-header"><h2>Schemat kolorów</h2></div><div class="inside">
                <form method="post" action="<?php echo sp_e(sp_admin_url('appearance')); ?>">
                    <?php sp_nonce_field('save_appearance_theme'); ?>
                    <input type="hidden" name="sp_save_theme" value="1">
                    <?php foreach (['classic' => 'SimplePress Classic', 'modern' => 'SimplePress Modern', 'dark' => 'SimplePress Dark'] as $key => $label): ?>
                    <label style="display:block;margin:6px 0"><input type="radio" name="scheme" value="<?php echo sp_e($key); ?>" <?php echo $scheme === $key ? 'checked' : ''; ?>> <?php echo sp_e($label); ?></label>
                    <?php endforeach; ?>
                    <p><label><input type="checkbox" name="grid_admin" value="1" <?php echo wp_simple_get_setting('grid_admin', '0') === '1' ? 'checked' : ''; ?>> Bootstrap grid w panelu</label></p>
                    <p><label><input type="checkbox" name="grid_frontend" value="1" <?php echo wp_simple_get_setting('grid_frontend', '0') === '1' ? 'checked' : ''; ?>> Bootstrap grid na froncie</label></p>
                    <p><button type="submit" class="button button-primary">Zapisz schemat</button></p>
                </form>
            </div></div>

            <div class="postbox"><div class="postbox-header"><h2>Branding</h2></div><div class="inside">
                <form method="post" enctype="multipart/form-data" action="<?php echo sp_e(sp_admin_url('appearance')); ?>">
                    <?php sp_nonce_field('save_branding'); ?>
                    <input type="hidden" name="sp_save_branding" value="1">
                    <p><label><input type="checkbox" name="branding_enabled" value="1" <?php echo $branding['enabled'] ? 'checked' : ''; ?>> Włącz branding</label></p>
                    <p><label>Tekst stopki<br><input type="text" class="regular-text" name="footer_name" value="<?php echo sp_e($branding['footer_name']); ?>"></label></p>
                    <p><label>Link stopki<br><input type="url" class="regular-text" name="footer_link" value="<?php echo sp_e($branding['footer_link']); ?>" placeholder="https://"></label></p>
                    <p><label>Logo / ikona<br><input type="file" name="branding_icon" accept="image/png,image/jpeg,image/gif,image/webp"></label></p>
                    <?php if ($iconUrl !== ''): ?><p><img src="<?php echo sp_e($iconUrl); ?>" alt="" style="max-height:80px;max-width:200px"><br><label><input type="checkbox" name="remove_icon" value="1"> usuń ikonę</label></p><?php endif; ?>
                    <p><label><input type="checkbox" name="colors_enabled" value="1" <?php echo $branding['colors'] ? 'checked' : ''; ?>> Nadpisz kolory</label></p>
                    <p><label>Akcent <input type="color" name="accent" value="<?php echo sp_e($branding['accent']); ?>"></label> <label>Tło <input type="color" name="bg" value="<?php echo sp_e($branding['bg']); ?>"></label></p>
                    <?php if (wp_simple_get_setting('developer_code', '1') === '1'): ?>
                    <p><label>Kod w nagłówku<br><textarea name="header_code" rows="6" class="large-text code"><?php echo sp_e($branding['header_code']); ?></textarea></label></p>
                    <p><label>Kod w stopce<br><textarea name="footer_code" rows="6" class="large-text code"><?php echo sp_e($branding['footer_code']); ?></textarea></label></p>
                    <?php endif; ?>
                    <p><button type="submit" class="button button-primary">Zapisz branding</button></p>
                </form>
            </div></div>

            <div class="postbox"><div class="postbox-header"><h2>Logowanie i pasek front-endu</h2></div><div class="inside">
                <form method="post" enctype="multipart/form-data" action="<?php echo sp_e(sp_admin_url('appearance')); ?>" style="margin-bottom:20px">
                    <?php sp_nonce_field('save_login_options'); ?>
                    <input type="hidden" name="sp_save_login" value="1">
                    <p><label><input type="checkbox" name="login_show_password" value="1" <?php echo wp_simple_get_setting('login_show_password', '0') === '1' ? 'checked' : ''; ?>> Pokaż przycisk „Pokaż hasło”</label></p>
                    <p><label><input type="checkbox" name="login_hide_forgot" value="1" <?php echo wp_simple_get_setting('login_hide_forgot', '0') === '1' ? 'checked' : ''; ?>> Ukryj link resetowania hasła</label></p>
                    <p><label>Logo logowania<br><input type="file" name="login_logo" accept="image/png,image/jpeg,image/gif,image/webp"></label></p>
                    <?php if (wp_simple_get_setting('login_logo', '') !== ''): ?><p><label><input type="checkbox" name="remove_login_logo" value="1"> usuń logo logowania</label></p><?php endif; ?>
                    <p><button type="submit" class="button button-primary">Zapisz opcje logowania</button></p>
                </form>
                <form method="post" action="<?php echo sp_e(sp_admin_url('appearance')); ?>">
                    <?php sp_nonce_field('save_frontend_bar'); ?>
                    <input type="hidden" name="sp_save_frontend" value="1">
                    <p><label><input type="checkbox" name="frontend_bar" value="1" <?php echo wp_simple_get_setting('frontend_bar', '1') === '1' ? 'checked' : ''; ?>> Pokaż pasek administratora na stronie publicznej</label></p>
                    <p><button type="submit" class="button button-primary">Zapisz pasek</button></p>
                </form>
            </div></div>
        </div>
        <?php
    }
}
