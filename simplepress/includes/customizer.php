<?php
/**
 * WordPress-like front-end theme customizer.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_customizer_can_use')) {
    function sp_customizer_can_use()
    {
        return function_exists('sp_is_administrator') && sp_is_administrator();
    }
}

if (!function_exists('sp_customizer_preview_url')) {
    function sp_customizer_preview_url()
    {
        global $SITEURL;
        return rtrim((string) $SITEURL, '/') . '/?sp_customizer_preview=1';
    }
}

if (!function_exists('sp_customizer_pages')) {
    function sp_customizer_pages()
    {
        global $pagesArray, $SITEURL;
        if (function_exists('getPagesXmlValues')) {
            getPagesXmlValues();
        }
        if (empty($pagesArray) && is_file(GSDATAOTHERPATH . 'pages.xml')) {
            $xml = @simplexml_load_file(GSDATAOTHERPATH . 'pages.xml');
            if ($xml && isset($xml->item)) {
                $pagesArray = [];
                foreach ($xml->item as $item) {
                    $slug = (string) $item->url;
                    if ($slug === '') {
                        continue;
                    }
                    $pagesArray[$slug] = [];
                    foreach ($item->children() as $opt => $val) {
                        $pagesArray[$slug][(string) $opt] = (string) $val;
                    }
                }
            }
        }

        $nodes = [];
        foreach ((array) $pagesArray as $slug => $page) {
            if (!is_array($page)) {
                continue;
            }
            $slug = (string) ($page['url'] ?? $slug);
            if ($slug === '') {
                continue;
            }
            $parent = (string) ($page['parent'] ?? '');
            $nodes[$slug] = [
                'slug' => $slug,
                'title' => (string) ($page['title'] ?? $slug),
                'menu' => (string) ($page['menu'] ?? ''),
                'parent' => $parent,
                'order' => (int) ($page['menuOrder'] ?? 0),
                'private' => in_array((string) ($page['private'] ?? ''), ['Y', '1', 'true'], true),
            ];
        }

        $children = [];
        foreach ($nodes as $slug => $node) {
            $parent = $node['parent'];
            if ($parent === '' || !isset($nodes[$parent])) {
                $parent = '';
            }
            $children[$parent][] = $slug;
        }

        $sitePath = (string) parse_url((string) $SITEURL, PHP_URL_PATH);
        $sitePath = rtrim($sitePath, '/');
        $makePath = function ($full) use ($SITEURL, $sitePath) {
            $full = (string) $full;
            if ($SITEURL !== '' && strpos($full, $SITEURL) === 0) {
                $relative = ltrim(substr($full, strlen($SITEURL)), '/');
                return ($sitePath !== '' ? $sitePath : '') . '/' . $relative;
            }
            $parts = parse_url($full);
            $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
            if (!empty($parts['query'])) {
                $path .= '?' . $parts['query'];
            }
            return $path;
        };

        $out = [];
        $seen = [];
        $walk = function ($parent, $level) use (&$walk, &$children, &$nodes, &$out, &$seen, $SITEURL, $makePath) {
            if (empty($children[$parent])) {
                return;
            }
            usort($children[$parent], function ($a, $b) use ($nodes) {
                $order = $nodes[$a]['order'] <=> $nodes[$b]['order'];
                return $order !== 0 ? $order : strcasecmp($nodes[$a]['title'], $nodes[$b]['title']);
            });
            foreach ($children[$parent] as $slug) {
                $node = $nodes[$slug];
                $full = function_exists('find_url') ? find_url($slug, $node['parent']) : rtrim((string) $SITEURL, '/') . '/' . $slug;
                $out[] = [
                    'slug' => $slug,
                    'title' => $node['title'] !== '' ? $node['title'] : ($node['menu'] !== '' ? $node['menu'] : $slug),
                    'path' => $makePath($full),
                    'url' => (string) $full,
                    'level' => $level,
                    'private' => $node['private'],
                ];
                $seen[$slug] = true;
                $walk($slug, $level + 1);
            }
        };
        $walk('', 0);

        // Dołącz strony bez istniejącego rodzica, żeby nigdy nie zniknęły z listy.
        foreach ($nodes as $slug => $node) {
            if (isset($seen[$slug])) {
                continue;
            }
            $full = function_exists('find_url') ? find_url($slug, $node['parent']) : rtrim((string) $SITEURL, '/') . '/' . $slug;
            $out[] = [
                'slug' => $slug,
                'title' => $node['title'] !== '' ? $node['title'] : ($node['menu'] !== '' ? $node['menu'] : $slug),
                'path' => $makePath($full),
                'url' => (string) $full,
                'level' => 0,
                'private' => $node['private'],
            ];
        }
        return $out;
    }
}

if (!function_exists('sp_customizer_menu_links')) {
    function sp_customizer_menu_links()
    {
        if (!function_exists('wp_simple_get_menu_links')) {
            return [];
        }
        $out = [];
        foreach ((array) wp_simple_get_menu_links() as $link) {
            if (!is_array($link) || empty($link['url'])) {
                continue;
            }
            $out[] = [
                'name' => (string) ($link['name'] ?? $link['url']),
                'url' => (string) $link['url'],
            ];
        }
        return $out;
    }
}

if (!function_exists('sp_customizer_ajax')) {
    function sp_customizer_ajax()
    {
        if (!isset($_GET['sp_customizer_ajax'])) {
            return;
        }
        if (!sp_customizer_can_use()) {
            sp_json(['status' => 'error', 'message' => wp_i18n('ERROR_ADMIN', 'Administrator role required.')], 403);
        }
        if (!sp_verify_nonce('customizer', $_POST['sp_nonce'] ?? ($_GET['sp_nonce'] ?? ''))) {
            sp_json(['status' => 'error', 'message' => wp_i18n('ERROR_SECURITY', 'Security validation failed.')], 403);
        }
        $action = preg_replace('/[^a-z0-9_]/i', '', (string) $_GET['sp_customizer_ajax']);
        $theme = function_exists('sp_active_theme') ? sp_active_theme() : false;
        if (!$theme) {
            sp_json(['status' => 'error', 'message' => wp_i18n('CUSTOMIZER_NO_ACTIVE_THEME', 'No active theme')], 400);
        }

        if ($action === 'load') {
            $defs = [];
            foreach (sp_theme_setting_defs($theme) as $key => $def) {
                $def['value'] = function_exists('sp_theme_option') ? sp_theme_option($key, $def['default'] ?? '') : ($def['default'] ?? '');
                $defs[] = $def;
            }
            sp_json([
                'status' => 'success',
                'theme' => $theme,
                'sections' => array_values(sp_theme_sections($theme)),
                'defs' => $defs,
                'pages' => sp_customizer_pages(),
                'menu_links' => sp_customizer_menu_links(),
                'preview' => sp_customizer_preview_url(),
            ]);
        }

        if ($action === 'preview') {
            $values = json_decode((string) ($_POST['values'] ?? '{}'), true);
            if (!is_array($values)) {
                $values = [];
            }
            $defs = sp_theme_setting_defs($theme);
            $clean = [];
            foreach ($defs as $key => $def) {
                if (!array_key_exists($key, $values)) {
                    continue;
                }
                $type = (string) ($def['type'] ?? 'text');
                $clean[$key] = sp_theme_sanitize_value($type, $values[$key], $def);
            }
            sp_theme_preview_save($clean, $theme);
            sp_json(['status' => 'success']);
        }

        if ($action === 'media_list') {
            if (!function_exists('sp_media_library_files')) {
                sp_json(['status' => 'error', 'message' => wp_i18n('CUSTOMIZER_MEDIA_UNAVAILABLE', 'Media library unavailable')], 500);
            }
            $kind = (string) ($_POST['kind'] ?? ($_GET['kind'] ?? 'all'));
            sp_json(['status' => 'success', 'files' => sp_media_library_files($kind)]);
        }

        if ($action === 'media_upload') {
            if (!function_exists('sp_media_library_upload')) {
                sp_json(['status' => 'error', 'message' => wp_i18n('CUSTOMIZER_MEDIA_UNAVAILABLE', 'Media library unavailable')], 500);
            }
            if (!isset($_FILES['file'])) {
                sp_json(['status' => 'error', 'message' => wp_i18n('CUSTOMIZER_NO_FILE', 'No file')], 400);
            }
            $kind = (string) ($_POST['kind'] ?? 'all');
            $result = sp_media_library_upload($_FILES['file'], $kind);
            if (isset($result['error'])) {
                sp_json(['status' => 'error', 'message' => $result['error']], 400);
            }
            sp_json(['status' => 'success', 'file' => $result['file']]);
        }

        if ($action === 'preview_clear') {
            sp_theme_preview_clear($theme);
            sp_json(['status' => 'success']);
        }

        if ($action === 'save') {
            $values = json_decode((string) ($_POST['values'] ?? '{}'), true);
            if (!is_array($values)) {
                sp_json(['status' => 'error', 'message' => wp_i18n('CUSTOMIZER_INVALID_VALUES', 'Invalid values')], 400);
            }
            sp_theme_settings_save_values($values, $theme);
            sp_theme_preview_clear($theme);
            sp_json(['status' => 'success', 'message' => wp_i18n('CUSTOMIZER_SETTINGS_SAVED', 'Settings saved')]);
        }

        sp_json(['status' => 'error', 'message' => wp_i18n('CUSTOMIZER_UNKNOWN_ACTION', 'Unknown action')], 400);
    }
    add_action('common', 'sp_customizer_ajax');
}

if (!function_exists('sp_customizer_render')) {
    function sp_customizer_render()
    {
        if (($_GET['sp_customizer_preview'] ?? '') === '1' || !sp_customizer_can_use()) {
            return;
        }
        $forced = ($_GET['sp_customizer'] ?? '') === '1';
        if (!$forced && (!function_exists('sp_frontend_bar_enabled') || !sp_frontend_bar_enabled())) {
            return;
        }
        global $SITEURL;
        $dashiconsCss = SP_PATH . 'css/dashicons.css';
        echo '<link rel="stylesheet" href="' . sp_e(sp_plugin_url('css/dashicons.css?v=' . (file_exists($dashiconsCss) ? filemtime($dashiconsCss) : SP_VERSION))) . '" media="all">' . PHP_EOL;
        $labels = [
            'title' => wp_i18n('CUSTOMIZER_TITLE', 'Customize'),
            'publish' => wp_i18n('CUSTOMIZER_PUBLISH', 'Publish'),
            'publishing' => wp_i18n('CUSTOMIZER_PUBLISHING', 'Publishing...'),
            'published' => wp_i18n('CUSTOMIZER_PUBLISHED', 'Published.'),
            'saving' => wp_i18n('CUSTOMIZER_SAVING', 'Saving preview...'),
            'close' => wp_i18n('CUSTOMIZER_CLOSE', 'Close'),
            'desktop' => wp_i18n('CUSTOMIZER_DESKTOP', 'Desktop'),
            'tablet' => wp_i18n('CUSTOMIZER_TABLET', 'Tablet'),
            'mobile' => wp_i18n('CUSTOMIZER_MOBILE', 'Mobile'),
            'empty' => wp_i18n('CUSTOMIZER_EMPTY', 'This theme has no settings.'),
            'error' => wp_i18n('CUSTOMIZER_ERROR', 'Could not save.'),
            'unsaved' => wp_i18n('CUSTOMIZER_UNSAVED', 'You have unsaved changes. Close the customizer?'),
            'reset' => wp_i18n('CUSTOMIZER_RESET', 'Restore defaults'),
            'search' => wp_i18n('CUSTOMIZER_SEARCH', 'Search settings...'),
            'choose' => wp_i18n('CUSTOMIZER_CHOOSE', 'Choose file'),
            'file_url' => wp_i18n('CUSTOMIZER_FILE_URL', 'File URL'),
            'remove' => wp_i18n('CUSTOMIZER_REMOVE', 'Remove'),
            'clear' => wp_i18n('CUSTOMIZER_CLEAR', 'Clear'),
            'upload' => wp_i18n('CUSTOMIZER_UPLOAD', 'Upload file'),
            'library' => wp_i18n('CUSTOMIZER_LIBRARY', 'Media library'),
            'insert' => wp_i18n('CUSTOMIZER_INSERT', 'Insert'),
            'cancel' => wp_i18n('CUSTOMIZER_CANCEL', 'Cancel'),
            'uploading' => wp_i18n('CUSTOMIZER_UPLOADING', 'Uploading...'),
            'loading' => wp_i18n('FE_LOADING', 'Loading...'),
            'upload_hint' => wp_i18n('CUSTOMIZER_UPLOAD_HINT', 'Drop a file here or click to choose from disk.'),
            'no_media' => wp_i18n('CUSTOMIZER_NO_MEDIA', 'No files in the library.'),
            'media_title' => wp_i18n('CUSTOMIZER_MEDIA_TITLE', 'Choose or upload media'),
            'media_error' => wp_i18n('CUSTOMIZER_MEDIA_ERROR', 'Could not load the library.'),
            'custom_url' => wp_i18n('CUSTOMIZER_CUSTOM_URL', 'Custom URL…'),
            'pages_group' => wp_i18n('CUSTOMIZER_PAGES_GROUP', 'Pages'),
            'menu_group' => wp_i18n('CUSTOMIZER_MENU_GROUP', 'Menu links'),
            'gallery_empty' => wp_i18n('CUSTOMIZER_GALLERY_EMPTY', 'No images yet. Add your first one.'),
            'add_images' => wp_i18n('CUSTOMIZER_ADD_IMAGES', 'Add images'),
            'choose_icon' => wp_i18n('CUSTOMIZER_CHOOSE_ICON', 'Choose icon'),
            'search_icons' => wp_i18n('CUSTOMIZER_SEARCH_ICONS', 'Search icons...'),
            'no_results' => wp_i18n('CUSTOMIZER_NO_RESULTS', 'No results.'),
            'link_empty' => wp_i18n('CUSTOMIZER_LINK_EMPTY', '— none —'),
        ];
        $config = [
            'endpoint' => rtrim((string) $SITEURL, '/') . '/?sp_customizer_ajax=',
            'nonce' => sp_nonce('customizer'),
            'preview' => sp_customizer_preview_url(),
            'tinymce' => sp_plugin_url('lib/tinymce/tinymce.min.js'),
            'labels' => $labels,
        ];
        ?>
        <div id="sp-customizer" aria-hidden="true">
            <div class="sp-cz-sidebar">
                <div class="sp-cz-header">
                    <h2><?php echo sp_e($labels['title']); ?></h2>
                    <button type="button" class="sp-cz-close" aria-label="<?php echo sp_e($labels['close']); ?>">&times;</button>
                </div>
                <div class="sp-cz-search">
                    <input type="search" id="sp-cz-search" placeholder="<?php echo sp_e($labels['search']); ?>" aria-label="<?php echo sp_e($labels['search']); ?>">
                </div>
                <div class="sp-cz-scroll" id="sp-cz-sections"></div>
                <div class="sp-cz-footer">
                    <span class="sp-cz-status" id="sp-cz-status"></span>
                    <button type="button" class="sp-cz-button sp-cz-button-secondary" id="sp-cz-reset"><?php echo sp_e($labels['reset']); ?></button>
                    <button type="button" class="sp-cz-button sp-cz-button-primary" id="sp-cz-publish"><?php echo sp_e($labels['publish']); ?></button>
                </div>
            </div>
            <div class="sp-cz-preview">
                <div class="sp-cz-devices">
                    <button type="button" class="sp-cz-device sp-cz-device-active" data-width="100%"><?php echo sp_e($labels['desktop']); ?></button>
                    <button type="button" class="sp-cz-device" data-width="768px"><?php echo sp_e($labels['tablet']); ?></button>
                    <button type="button" class="sp-cz-device" data-width="375px"><?php echo sp_e($labels['mobile']); ?></button>
                </div>
                <div class="sp-cz-frame-wrap">
                    <iframe id="sp-cz-iframe" src="about:blank" title="<?php echo sp_e($labels['title']); ?>"></iframe>
                </div>
            </div>
        </div>
        <script>window.SP_CUSTOMIZER = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
        <?php
        $js = SP_PATH . 'js/customizer.js';
        echo '<script src="' . sp_e(sp_plugin_url('js/customizer.js?v=' . (file_exists($js) ? filemtime($js) : SP_VERSION))) . '"></script>' . PHP_EOL;
    }
    add_action('theme-footer', 'sp_customizer_render', 20);
}
