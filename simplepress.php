<?php
/*
 * Plugin Name: SimplePress
 * Description: WordPress-inspired administration theme, dashboard and secure admin toolkit for GetSimple CMS.
 * Version: 6.4.0
 * Author: SimplePress
 * Author URI: https://getsimple-ce.ovh
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly!');
}

$sp_plugin_file = basename(__FILE__, '.php');
$sp_plugin_file = 'simplepress';

define('SP_VERSION', '6.4.0');
define('SP_PLUGIN', $sp_plugin_file);
define('SP_PATH', GSPLUGINPATH . 'simplepress/');
define('SP_INC', SP_PATH . 'includes/');
define('SP_URL', (defined('SITEURL') ? rtrim(SITEURL, '/') : '') . '/plugins/simplepress/');
define('WP_SIMPLE_INC', SP_INC);
define('WP_SIMPLE_MENU_DIR', GSDATAOTHERPATH . 'simplepress/');
define('WP_SIMPLE_MENU_FILE', WP_SIMPLE_MENU_DIR . 'menu_ext.json');

require_once SP_INC . 'security.php';
require_once SP_INC . 'storage.php';

$sp_modules = [
    'dashboard.php',
    'users.php',
    'settings.php',
    'snippets.php',
    'menu_ext.php',
    'media.php',
    'code_editor.php',
    'migrate.php',
    'repo.php',
    'maintenance.php',
    'backups.php',
    'hidden.php',
    'branding.php',
    'theme_settings.php',
    'customizer.php',
    'account.php',
    'gsconfig.php',
    'helpdesk.php',
    'sqlite.php',
    'search.php',
];
foreach ($sp_modules as $sp_module) {
    if (file_exists(SP_INC . $sp_module)) {
        require_once SP_INC . $sp_module;
    }
}

/* -------------------------------------------------------------------------
 * Language helpers
 * ---------------------------------------------------------------------- */
global $LANG, $i18n, $sp_custom_lang, $sp_lang_code;
$sp_custom_lang = [];
$sp_lang_code = 'en_US';
$sp_lang_loader = function ($file) {
    $i18n = [];
    include $file;
    return is_array($i18n) ? $i18n : [];
};
$sp_default_lang = $sp_lang_loader(SP_PATH . 'lang/en_US.php');
$sp_lang = !empty($LANG) ? (string) $LANG : 'en_US';
$sp_lang = preg_replace('/[^A-Za-z0-9_\-]/', '', $sp_lang);
if ($sp_lang === '') {
    $sp_lang = 'en_US';
}
$sp_lang_file = SP_PATH . 'lang/' . $sp_lang . '.php';
if (!is_file($sp_lang_file)) {
    $sp_lang_short = strtolower(substr($sp_lang, 0, 2));
    $sp_lang_candidates = glob(SP_PATH . 'lang/' . $sp_lang_short . '_*.php');
    if (is_array($sp_lang_candidates) && !empty($sp_lang_candidates)) {
        sort($sp_lang_candidates);
        $sp_lang_file = $sp_lang_candidates[0];
    }
}
if (!is_file($sp_lang_file)) {
    $sp_lang_file = SP_PATH . 'lang/en_US.php';
}
$sp_lang_code = basename($sp_lang_file, '.php');
$sp_current_lang = $sp_lang_loader($sp_lang_file);
$sp_custom_lang = array_merge($sp_default_lang, $sp_current_lang);
if (is_array($i18n)) {
    foreach ($sp_custom_lang as $sp_k => $sp_v) {
        if (!isset($i18n[$sp_k])) {
            $i18n[$sp_k] = $sp_v;
        }
    }
}

if (!function_exists('sp_lang_code')) {
    function sp_lang_code()
    {
        global $sp_lang_code;
        return $sp_lang_code !== '' ? $sp_lang_code : 'en_US';
    }
}

if (!function_exists('wp_i18n')) {
    function wp_i18n($key, $fallback = '')
    {
        global $sp_custom_lang;
        if (isset($sp_custom_lang[$key]) && $sp_custom_lang[$key] !== '') {
            return $sp_custom_lang[$key];
        }
        if (function_exists('i18n_r')) {
            $translated = i18n_r($key);
            if ($translated && $translated !== $key && strpos($translated, '{') !== 0) {
                return $translated;
            }
        }
        return $fallback !== '' ? $fallback : $key;
    }
}

/* -------------------------------------------------------------------------
 * URL helpers
 * ---------------------------------------------------------------------- */
if (!function_exists('sp_plugin_url')) {
    function sp_plugin_url($path = '')
    {
        global $SITEURL;
        return rtrim($SITEURL, '/') . '/plugins/simplepress/' . ltrim($path, '/');
    }
}

if (!function_exists('sp_admin_url')) {
    function sp_admin_url($sub = '', $extra = '')
    {
        $url = 'load.php?id=simplepress';
        if ($sub !== '') {
            $url .= '&sub=' . rawurlencode($sub);
        }
        if ($extra !== '') {
            $url .= '&' . ltrim($extra, '&');
        }
        return $url;
    }
}

/* -------------------------------------------------------------------------
 * Plugin registration and tabs
 * ---------------------------------------------------------------------- */
register_plugin(
    SP_PLUGIN,
    'SimplePress',
    SP_VERSION,
    'SimplePress',
    'https://getsimple-ce.ovh',
    'WordPress-inspired admin interface, dashboard and secure admin toolkit.',
    'dashboard',
    'wp_simple_router_view'
);

add_action('nav-tab', 'wp_simple_nav_tabs');
if (!function_exists('wp_simple_nav_tabs')) {
    function wp_simple_nav_tabs()
    {
        $id = $_GET['id'] ?? '';
        $sub = $_GET['sub'] ?? '';
        $current = ($id === 'simplepress' && $sub === 'dashboard') ? 'current' : '';
        echo '<li class="wp-menu-simplepress"><a href="load.php?id=simplepress&amp;sub=dashboard" class="simplepress ' . $current . '"><span class="wp-nav-label">SimplePress</span></a></li>';
    }
}

if (!function_exists('wp_simple_admin_tabs')) {
    function wp_simple_admin_tabs()
    {
        return [
            'dashboard' => [wp_i18n('TAB_DASHBOARD', 'Dashboard'), 'dashicons-dashboard'],
            'plugin-repo' => [wp_i18n('TAB_PLUGINS', 'Plugins'), 'dashicons-admin-plugins'],
            'settings' => [wp_i18n('TAB_EDITOR', 'Editor'), 'dashicons-edit-page'],
            'users' => [wp_i18n('TAB_USERS', 'Users'), 'dashicons-admin-users'],
            'snippets' => [wp_i18n('TAB_SNIPPETS', 'Snippets'), 'dashicons-shortcode'],
            'menu' => [wp_i18n('TAB_MENU', 'Menu'), 'dashicons-menu'],
            'appearance' => [wp_i18n('TAB_APPEARANCE', 'Appearance'), 'dashicons-admin-appearance'],
            'maintenance' => [wp_i18n('TAB_MAINTENANCE', 'Maintenance'), 'dashicons-admin-tools'],
            'backups' => [wp_i18n('TAB_BACKUPS', 'Backups'), 'dashicons-backup'],
            'hidden' => [wp_i18n('TAB_HIDDEN', 'Hidden sections'), 'dashicons-hidden'],
            'theme' => [wp_i18n('TAB_THEME', 'Theme'), 'dashicons-admin-customizer'],
            'account' => [wp_i18n('TAB_ACCOUNT', 'My account'), 'dashicons-admin-users'],
            'migrate' => [wp_i18n('TAB_MIGRATE', 'Migration & SSL'), 'dashicons-migrate'],
            'tools' => [wp_i18n('TAB_TOOLS', 'Tools'), 'dashicons-admin-generic'],
            'helpdesk' => [wp_i18n('TAB_HELP', 'Help'), 'dashicons-editor-help'],
            'sqlite' => [wp_i18n('TAB_SQLITE', 'SQLite3'), 'dashicons-database'],
        ];
    }
}

if (!function_exists('wp_simple_render_subtabs')) {
    function wp_simple_render_subtabs()
    {
        $sub = $_GET['sub'] ?? 'dashboard';
        $tabs = wp_simple_admin_tabs();
        if (function_exists('exec_filter')) {
            $filtered = exec_filter('simplepress_admin_tabs', $tabs);
            if (is_array($filtered)) {
                $tabs = $filtered;
            }
        }
        $primary_keys = ['dashboard', 'plugin-repo', 'settings', 'users', 'snippets', 'menu', 'appearance', 'theme'];
        $primary = [];
        $secondary = [];
        foreach ($tabs as $key => $tab) {
            if (in_array($key, $primary_keys, true)) {
                $primary[$key] = $tab;
            } else {
                $secondary[$key] = $tab;
            }
        }

        $tab_parts = function ($key, $tab) {
            $label = is_array($tab) ? $tab[0] : $tab;
            $icon = is_array($tab) && !empty($tab[1]) ? $tab[1] : 'dashicons-admin-generic';
            $url = (is_array($tab) && !empty($tab[2])) ? $tab[2] : sp_admin_url($key);
            return [$label, $icon, $url];
        };

        $secondary_active = isset($secondary[$sub]);

        echo '<nav class="nav-tab-wrapper sp-nav-tabs" aria-label="SimplePress">';
        foreach ($primary as $key => $tab) {
            [$label, $icon, $url] = $tab_parts($key, $tab);
            $active = ($sub === $key) ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . $active . '" href="' . sp_e($url) . '"' . ($sub === $key ? ' aria-current="page"' : '') . '>';
            echo '<span class="dashicons ' . sp_e($icon) . '" aria-hidden="true"></span> ' . sp_e($label);
            echo '</a>';
        }

        if ($secondary) {
            echo '<span class="sp-nav-more-wrap' . ($secondary_active ? ' sp-nav-more-wrap-active' : '') . '">';
            echo '<span class="dashicons dashicons-ellipsis sp-nav-more-icon" aria-hidden="true"></span>';
            echo '<label class="screen-reader-text" for="sp-nav-more">' . sp_e(wp_i18n('MORE', 'More')) . '</label>';
            echo '<select id="sp-nav-more" class="sp-nav-more' . ($secondary_active ? ' sp-nav-more-active' : '') . '" aria-label="' . sp_e(wp_i18n('MORE', 'More')) . '" onchange="if(this.value){window.location.href=this.value;}">';
            echo '<option value=""' . ($secondary_active ? '' : ' selected') . '>' . sp_e(wp_i18n('MORE', 'More')) . '…</option>';
            foreach ($secondary as $key => $tab) {
                [$label, , $url] = $tab_parts($key, $tab);
                echo '<option value="' . sp_e($url) . '"' . ($sub === $key ? ' selected' : '') . '>' . sp_e($label) . '</option>';
            }
            echo '</select>';
            echo '</span>';
        }

        echo '<label class="screen-reader-text" for="sp-nav-all">' . sp_e(wp_i18n('TAB_NAVIGATION', 'SimplePress navigation')) . '</label>';
        echo '<select id="sp-nav-all" class="sp-nav-all" aria-label="' . sp_e(wp_i18n('TAB_NAVIGATION', 'SimplePress navigation')) . '" onchange="if(this.value){window.location.href=this.value;}">';
        foreach ($tabs as $key => $tab) {
            [$label, , $url] = $tab_parts($key, $tab);
            echo '<option value="' . sp_e($url) . '"' . ($sub === $key ? ' selected' : '') . '>' . sp_e($label) . '</option>';
        }
        echo '</select>';
        echo '</nav>';
    }
}

if (!function_exists('wp_simple_router_view')) {
    function wp_simple_router_view()
    {
        $sub = $_GET['sub'] ?? 'dashboard';
        $map = [
            'dashboard' => 'wp_simple_dashboard_view',
            'plugin-repo' => 'wp_simple_plugin_repo_view',
            'settings' => 'wp_simple_settings_view',
            'users' => 'wp_simple_users_view',
            'snippets' => 'wp_simple_snippets_view',
            'menu' => 'wp_simple_menu_ext_view',
            'appearance' => 'wp_simple_appearance_view',
            'maintenance' => 'wp_simple_maintenance_view',
            'backups' => 'wp_simple_backups_view',
            'hidden' => 'wp_simple_hidden_view',
            'theme' => 'wp_simple_theme_settings_view',
            'account' => 'wp_simple_account_view',
            'migrate' => 'wp_simple_migrate_view',
            'tools' => 'wp_simple_tools_view',
            'helpdesk' => 'wp_simple_helpdesk_view',
            'sqlite' => 'wp_simple_sqlite_view',
        ];
        if (function_exists('exec_filter')) {
            $filtered = exec_filter('simplepress_admin_views', $map);
            if (is_array($filtered)) { $map = $filtered; }
        }
        $callback = $map[$sub] ?? 'wp_simple_dashboard_view';
        if (function_exists($callback)) {
            call_user_func($callback);
        } else {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . sp_e(wp_i18n('VIEW_MISSING', 'This view is not available.')) . '</p></div></div>';
        }
    }
}

/* -------------------------------------------------------------------------
 * Admin/sidebar menu integration
 * ---------------------------------------------------------------------- */
if (!function_exists('sp_side_menu')) {
    function sp_side_menu($text, $sub, $id = '')
    {
        $sub = preg_replace('/[^a-z0-9_\-]/i', '', (string) $sub);
        $current = (($_GET['id'] ?? '') === 'simplepress' && ($_GET['sub'] ?? '') === $sub);
        $id = $id !== '' ? $id : $sub;
        echo '<li id="sb_sp_' . sp_e($id) . '" class="sp-side-item"><a href="' . sp_e(sp_admin_url($sub)) . '"' . ($current ? ' class="current"' : '') . '>' . $text . '</a></li>';
    }
}

if (!function_exists('sp_register_side_menus')) {
    function sp_register_side_menus()
    {
        if (is_frontend() || !sp_is_administrator()) {
            return;
        }
        $menus = [
            ['settings-sidebar', 'SimplePress', 'dashboard', 'simplepress'],
            ['settings-sidebar', wp_i18n('TAB_APPEARANCE', 'Appearance'), 'appearance', 'appearance'],
            ['settings-sidebar', wp_i18n('TAB_USERS', 'Users'), 'users', 'users'],
            ['settings-sidebar', wp_i18n('TAB_HIDDEN', 'Hidden sections'), 'hidden', 'hidden'],
            ['settings-sidebar', wp_i18n('TAB_MAINTENANCE', 'Maintenance'), 'maintenance', 'maintenance'],
            ['settings-sidebar', wp_i18n('TAB_TOOLS', 'Tools'), 'tools', 'tools'],
            ['settings-sidebar', wp_i18n('TAB_HELP', 'Help'), 'helpdesk', 'helpdesk'],
            ['pages-sidebar', wp_i18n('TAB_SNIPPETS', 'Snippets'), 'snippets', 'snippets'],
            ['plugins-sidebar', wp_i18n('TAB_PLUGINS', 'Plugins'), 'plugin-repo', 'repo'],
            ['backups-sidebar', wp_i18n('TAB_BACKUPS', 'Backups'), 'backups', 'backups'],
            ['theme-sidebar', wp_i18n('TAB_THEME', 'Theme'), 'theme', 'theme'],
        ];
        if (function_exists('exec_filter')) {
            $filtered = exec_filter('simplepress_sidebar_links', $menus);
            if (is_array($filtered)) { $menus = $filtered; }
        }
        foreach ($menus as $menu) {
            if (!isset($menu[3])) { $menu[3] = $menu[2]; }
            add_action($menu[0], 'sp_side_menu', [$menu[1], $menu[2], $menu[3]]);
        }
    }
}
sp_register_side_menus();

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */
if (!function_exists('wp_simple_admin_assets')) {
    function wp_simple_admin_assets()
    {
        $css = SP_PATH . 'css/wp-admin.css';
        $dash = SP_PATH . 'css/dashicons.css';
        echo '<link rel="stylesheet" href="' . sp_e(sp_plugin_url('css/dashicons.css?v=' . (file_exists($dash) ? filemtime($dash) : SP_VERSION))) . '" media="all">' . PHP_EOL;
        echo '<link rel="stylesheet" href="' . sp_e(sp_plugin_url('css/wp-admin.css?v=' . (file_exists($css) ? filemtime($css) : SP_VERSION))) . '" media="all">' . PHP_EOL;
        global $sp_custom_lang;
        $sp_js_i18n = is_array($sp_custom_lang) ? $sp_custom_lang : [];
        echo '<script>window.SP_PLUGIN_URL=' . json_encode(sp_plugin_url('')) . ';window.SP_USER=' . json_encode(sp_current_user()) . ';window.SP_LANG=' . json_encode(sp_lang_code()) . ';window.WP_I18N=' . json_encode($sp_js_i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' . PHP_EOL;
    }
}

if (!function_exists('wp_simple_admin_js')) {
    function wp_simple_admin_js()
    {
        $js = SP_PATH . 'js/wp-admin.js';
        echo '<script src="' . sp_e(sp_plugin_url('js/wp-admin.js?v=' . (file_exists($js) ? filemtime($js) : SP_VERSION))) . '"></script>' . PHP_EOL;
    }
}

add_action('header', 'wp_simple_admin_assets');
add_action('header', 'sp_branding_head');
add_action('footer', 'wp_simple_admin_js');
add_action('index-login', 'wp_simple_login_styles');
add_action('index-login', 'wp_simple_login_customization');
add_action('theme-header', 'wp_simple_frontend_bar_assets');
add_action('theme-footer', 'wp_simple_frontend_bar_render');
if (!function_exists('sp_dispatch_early_actions')) {
    function sp_dispatch_early_actions()
    {
        if (($_GET['id'] ?? '') !== 'simplepress' || strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $adminActions = ['wp_save_settings', 'wp_delete_user', 'wp_save_user', 'wp_save_snippets', 'wp_add_menu_link', 'wp_delete_menu_link', 'wp_migrate_domain_submit', 'wp_ssl_submit', 'sp_save_theme', 'sp_save_branding', 'sp_save_login', 'sp_save_frontend'];
        $needsAdmin = false;
        foreach ($adminActions as $key) {
            if (isset($_POST[$key])) { $needsAdmin = true; break; }
        }
        if ($needsAdmin && !sp_is_administrator()) {
            sp_die(wp_i18n('ERROR_ADMIN', 'Administrator role required.'), 403);
        }
        if (function_exists('wp_simple_process_settings')) { wp_simple_process_settings(); }
        if (function_exists('wp_simple_handle_users_actions')) { wp_simple_handle_users_actions(); }
        if (function_exists('wp_simple_handle_snippets_actions')) { wp_simple_handle_snippets_actions(); }
        if (function_exists('wp_simple_handle_menu_actions')) { wp_simple_handle_menu_actions(); }
        if (function_exists('wp_simple_handle_migrate_actions')) { wp_simple_handle_migrate_actions(); }
        if (function_exists('wp_simple_handle_appearance_actions')) { wp_simple_handle_appearance_actions(); }
        if (function_exists('wp_simple_handle_account_actions')) { wp_simple_handle_account_actions(); }
    }
}
add_action('admin-pre-header', 'sp_dispatch_early_actions', []);

add_action('admin-pre-header', 'sp_enforce_hidden_sections', []);
add_action('admin-pre-header', 'wp_simple_process_quick_draft', []);
add_action('index-pretemplate', 'wp_simple_track_views', []);
add_action('admin-pre-header', 'sp_restrict_editor_role', []);
add_action('common', 'sp_maybe_maintenance', []);
add_action('footer', 'sp_plugin_search_assets', []);

if (!function_exists('wp_simple_login_styles')) {
    function wp_simple_login_styles()
    {
        wp_simple_admin_assets();
    }
}

if (!function_exists('wp_simple_login_customization')) {
    function wp_simple_login_customization()
    {
        global $SITEURL, $SITENAME;
        $logo = sp_plugin_url('img/sp.svg');
        $brand = get_option_sp('login_logo', '');
        if ($brand !== '' && is_file(GSDATAUPLOADPATH . 'simplepress/' . basename($brand))) {
            $logo = rtrim($SITEURL, '/') . '/data/uploads/simplepress/' . rawurlencode(basename($brand));
        }
        echo '<div class="sp-login-brand">';
        echo '<a href="' . sp_e($SITEURL) . '" class="wp-login-logo"><img src="' . sp_e($logo) . '" alt="SimplePress" width="84" height="84"></a>';
        echo '<span class="sp-login-site">' . sp_e($SITENAME ?? 'SimplePress') . '</span>';
        echo '</div>';
        if (wp_simple_get_setting('login_show_password', '0') === '1') {
            echo '<script>(function(){function add(){var p=document.getElementById("pwd");var f=document.querySelector("form.login");if(!p||!f||document.querySelector(".sp-show-password"))return;var b=document.createElement("button");b.type="button";b.className="button sp-show-password";b.textContent=' . sp_js(wp_i18n('SHOW_PASSWORD', 'Show password')) . ';b.addEventListener("click",function(){p.type=p.type==="password"?"text":"password";b.textContent=p.type==="text"?' . sp_js(wp_i18n('HIDE_PASSWORD', 'Hide password')) . ':' . sp_js(wp_i18n('SHOW_PASSWORD', 'Show password')) . ';});var s=f.querySelector("p:last-child");if(s){s.appendChild(b);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",add);else add();})();</script>';
        }
        if (wp_simple_get_setting('login_hide_forgot', '0') === '1') {
            echo '<style>body#index p.cta a[href="resetpassword.php"]{display:none!important}</style>';
        }
    }
}

if (!function_exists('get_option_sp')) {
    function get_option_sp($key, $default = '')
    {
        return wp_simple_get_setting($key, $default);
    }
}

/* -------------------------------------------------------------------------
 * Front-end admin bar
 * ---------------------------------------------------------------------- */
if (!function_exists('sp_frontend_bar_enabled')) {
    function sp_frontend_bar_enabled()
    {
        if (!sp_is_authenticated()) {
            return false;
        }
        $global = wp_simple_get_setting('frontend_bar', '1');
        return sp_user_pref('frontend_bar', $global !== '' ? $global : '1') !== '0';
    }
}

if (!function_exists('sp_frontend_page_slug')) {
    function sp_frontend_page_slug()
    {
        global $url;
        $page = !empty($url) ? $url : ($_GET['id'] ?? 'index');
        if (is_array($page)) {
            $page = reset($page);
        }
        if (is_object($page) && method_exists($page, '__toString')) {
            $page = (string) $page;
        }
        return (string) $page;
    }
}

if (!function_exists('wp_simple_frontend_bar_assets')) {
    function wp_simple_frontend_bar_assets()
    {
        if (wp_simple_get_setting('grid_frontend', '0') === '1') {
            echo '<link rel="stylesheet" href="' . sp_e(sp_plugin_url('css/grid.css')) . '" media="all">' . PHP_EOL;
        }
        if (!sp_is_authenticated()) {
            return;
        }
        echo '<link rel="stylesheet" href="' . sp_e(sp_plugin_url('css/frontend.css')) . '" media="all">' . PHP_EOL;
        $customizerCss = SP_PATH . 'css/customizer.css';
        echo '<link rel="stylesheet" href="' . sp_e(sp_plugin_url('css/customizer.css?v=' . (file_exists($customizerCss) ? filemtime($customizerCss) : SP_VERSION))) . '" media="all">' . PHP_EOL;
    }
}

if (!function_exists('wp_simple_frontend_bar_render')) {
    function wp_simple_frontend_bar_render()
    {
        global $SITEURL, $SITENAME, $url;
        if (!sp_frontend_bar_enabled()) {
            return;
        }
        $adminDir = defined('GSADMIN') ? GSADMIN : 'admin';
        $adminUrl = rtrim($SITEURL, '/') . '/' . $adminDir . '/';
        $page = sp_frontend_page_slug();
        echo '<script>document.documentElement.classList.add("sp-has-frontend-bar");document.body.classList.add("sp-has-frontend-bar");</script>';
        echo '<div id="wp-frontend-adminbar">';
        echo '<div class="bar-left">';
        echo '<a href="' . sp_e($adminUrl . 'load.php?id=simplepress&sub=dashboard') . '" class="sp-front-logo" title="SimplePress"><img src="' . sp_e(sp_plugin_url('img/sp.svg')) . '" alt="SP"></a>';
        echo '<a href="' . sp_e($adminUrl . 'pages.php') . '">' . sp_e(wp_i18n('NAV_PAGES', 'Pages')) . '</a>';
        echo '<a href="' . sp_e($adminUrl . 'edit.php?id=' . urlencode($page)) . '">' . sp_e(wp_i18n('EDIT_PAGE', 'Edit page')) . '</a>';
        echo '<a href="' . sp_e($adminUrl . 'edit.php') . '">' . sp_e(wp_i18n('ADD_PAGE', 'Add page')) . '</a>';
        if (sp_is_administrator()) {
            echo '<a href="' . sp_e($adminUrl . 'load.php?id=simplepress&sub=theme') . '" data-sp-customizer="1">' . sp_e(wp_i18n('TAB_THEME', 'Theme')) . '</a>';
        }
        if (function_exists('wp_simple_front_bar_links')) {
            wp_simple_front_bar_links();
        }
        echo '</div><div class="bar-right">';
        echo '<a href="' . sp_e($adminUrl . 'load.php?id=simplepress&sub=account') . '">' . sp_e(wp_i18n('WELCOME', 'Howdy')) . ', <strong>' . sp_e(sp_current_user()) . '</strong></a>';
        echo '<a href="' . sp_e($adminUrl . 'logout.php') . '">' . sp_e(wp_i18n('LOGOUT', 'Log out')) . '</a>';
        echo '</div></div>';
    }
}

/* -------------------------------------------------------------------------
 * Sidebar active-state compatibility for old CSS/JS
 * ---------------------------------------------------------------------- */
if (!function_exists('wp_simple_sidebar_active_fix_js')) {
    function wp_simple_sidebar_active_fix_js()
    {
        // Modern navigation is handled by js/wp-admin.js.
    }
}
