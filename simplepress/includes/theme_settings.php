<?php
/**
 * Theme settings registry, storage and backend creator.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_active_theme')) {
    function sp_active_theme()
    {
        $file = GSDATAOTHERPATH . 'website.xml';
        if (!is_file($file)) {
            return false;
        }
        $xml = @simplexml_load_file($file);
        if (!$xml) {
            return false;
        }
        $template = basename((string) $xml->TEMPLATE);
        if ($template === '') {
            return false;
        }
        $path = GSTHEMESPATH . $template;
        return is_dir($path) && sp_path_within($path, GSTHEMESPATH, true) ? $template : false;
    }
}

if (!function_exists('sp_theme_registry')) {
    function &sp_theme_registry()
    {
        if (!isset($GLOBALS['sp_customizer_registry']) || !is_array($GLOBALS['sp_customizer_registry'])) {
            $GLOBALS['sp_customizer_registry'] = ['sections' => [], 'settings' => []];
        }
        return $GLOBALS['sp_customizer_registry'];
    }
}

if (!function_exists('sp_customizer_add_section')) {
    function sp_customizer_add_section($id, $label, $args = [])
    {
        $id = preg_replace('/[^a-z0-9_\-]/i', '-', (string) $id);
        if ($id === '') {
            return;
        }
        $registry = &sp_theme_registry();
        $registry['sections'][$id] = [
            'id' => $id,
            'label' => (string) $label,
            'priority' => isset($args['priority']) ? (int) $args['priority'] : 10,
        ];
    }
}

if (!function_exists('sp_customizer_add_setting')) {
    function sp_customizer_add_setting($key, $args = [])
    {
        $key = preg_replace('/[^a-z0-9_\-]/i', '_', (string) $key);
        if ($key === '') {
            return;
        }
        $extra = is_array($args) ? $args : [];
        foreach (['label', 'type', 'default', 'options', 'section', 'priority', 'description'] as $known) {
            unset($extra[$known]);
        }
        $registry = &sp_theme_registry();
        $registry['settings'][$key] = [
            'key' => $key,
            'label' => (string) ($args['label'] ?? $key),
            'type' => (string) ($args['type'] ?? 'text'),
            'default' => $args['default'] ?? '',
            'options' => $args['options'] ?? [],
            'section' => (string) ($args['section'] ?? 'general'),
            'priority' => isset($args['priority']) ? (int) $args['priority'] : 10,
            'description' => (string) ($args['description'] ?? ''),
            'source' => 'theme',
            'args' => $extra,
        ];
    }
}

if (!function_exists('sp_def_arg')) {
    function sp_def_arg($def, $key, $default = null)
    {
        if (is_array($def) && isset($def['args']) && is_array($def['args']) && array_key_exists($key, $def['args'])) {
            return $def['args'][$key];
        }
        if (is_array($def) && array_key_exists($key, $def) && $key !== 'args') {
            return $def[$key];
        }
        return $default;
    }
}

if (!function_exists('sp_theme_decode_options')) {
    function sp_theme_decode_options($options)
    {
        if (is_array($options)) {
            return $options;
        }
        $options = trim((string) $options);
        if ($options === '') {
            return [];
        }
        $decoded = json_decode($options, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $out = [];
        foreach (preg_split('/\r\n|\r|\n|\|\|/', $options) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $out[] = ['value' => $parts[0], 'label' => $parts[1] ?? $parts[0]];
        }
        return $out;
    }
}

if (!function_exists('sp_theme_option_values')) {
    function sp_theme_option_values($def)
    {
        $options = sp_theme_decode_options($def['options'] ?? []);
        $values = [];
        $assoc = $options && array_keys($options) !== range(0, count($options) - 1);
        if ($assoc) {
            foreach ($options as $key => $label) {
                $values[] = (string) $key;
            }
            return $values;
        }
        foreach ($options as $option) {
            if (is_array($option)) {
                $values[] = (string) ($option['value'] ?? '');
            } else {
                $values[] = (string) $option;
            }
        }
        return $values;
    }
}

if (!function_exists('sp_customizer_collect')) {
    function sp_customizer_collect($theme = null)
    {
        $theme = $theme ?: (function_exists('sp_active_theme') ? sp_active_theme() : false);
        if (!$theme) {
            return ['sections' => [], 'settings' => []];
        }
        static $loading = false;
        if ($loading) {
            return sp_theme_registry();
        }
        $file = GSTHEMESPATH . $theme . '/functions.php';
        if (is_file($file)) {
            $loading = true;
            include_once $file;
            $loading = false;
        }
        return sp_theme_registry();
    }
}

if (!function_exists('sp_theme_db_rows')) {
    function sp_theme_db_rows($theme)
    {
        $out = [];
        $db = function_exists('wp_simple_get_db') ? wp_simple_get_db() : null;
        if (!$db) {
            return $out;
        }
        $stmt = $db->prepare('SELECT * FROM theme_settings_defs WHERE theme = :t ORDER BY priority ASC, setting_key ASC');
        if (!$stmt) {
            return $out;
        }
        $stmt->bindValue(':t', $theme, SQLITE3_TEXT);
        $res = $stmt->execute();
        if ($res) {
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $options = sp_theme_decode_options($row['options'] ?? '');
                $extra = [];
                if (!empty($row['args'])) {
                    $decodedArgs = json_decode($row['args'], true);
                    if (is_array($decodedArgs)) {
                        $extra = $decodedArgs;
                    }
                }
                $out[$row['setting_key']] = [
                    'key' => $row['setting_key'],
                    'label' => $row['label'] ?: $row['setting_key'],
                    'type' => $row['type'] ?: 'text',
                    'default' => $row['default_value'],
                    'options' => $options,
                    'section' => $row['section'] ?: 'general',
                    'priority' => (int) $row['priority'],
                    'description' => (string) ($extra['description'] ?? ''),
                    'source' => $row['source'] ?: 'db',
                    'args' => $extra,
                ];
            }
        }
        return $out;
    }
}

if (!function_exists('sp_theme_migrate_json')) {
    function sp_theme_migrate_json($theme)
    {
        $flag = 'theme_migrated_' . $theme;
        if (function_exists('wp_simple_get_setting') && wp_simple_get_setting($flag, '') === '1') {
            return;
        }
        $file = GSTHEMESPATH . $theme . '/settings.json';
        if (!is_file($file)) {
            if (function_exists('wp_simple_save_setting')) {
                wp_simple_save_setting($flag, '1');
            }
            return;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        if (is_array($data) && isset($data['settings']) && is_array($data['settings'])) {
            $db = function_exists('wp_simple_get_db') ? wp_simple_get_db() : null;
            foreach ($data['settings'] as $key => $def) {
                if (!is_array($def)) {
                    continue;
                }
                $key = preg_replace('/[^a-z0-9_\-]/i', '_', (string) $key);
                if ($key === '') {
                    continue;
                }
                $type = strtolower((string) ($def['type'] ?? 'text'));
                if ($type === 'dropdown') {
                    $type = 'select';
                }
                if ($type === 'wysywig') {
                    $type = 'wysiwyg';
                }
                $optionsList = sp_theme_decode_options($def['options'] ?? '');
                $options = $optionsList ? json_encode($optionsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
                $extra = [];
                foreach (['min', 'max', 'step', 'accept', 'source', 'multiple', 'height', 'toolbar', 'rows', 'placeholder', 'include_private', 'description'] as $extraKey) {
                    if (array_key_exists($extraKey, $def)) {
                        $extra[$extraKey] = $def[$extraKey];
                    }
                }
                $args = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
                if ($db) {
                    $stmt = $db->prepare('INSERT OR IGNORE INTO theme_settings_defs
                        (theme, setting_key, label, type, default_value, options, section, priority, source, args)
                        VALUES (:t, :k, :l, :ty, :d, :o, :s, :p, :src, :a)');
                    if ($stmt) {
                        $stmt->bindValue(':t', $theme, SQLITE3_TEXT);
                        $stmt->bindValue(':k', $key, SQLITE3_TEXT);
                        $stmt->bindValue(':l', (string) ($def['title'] ?? $key), SQLITE3_TEXT);
                        $stmt->bindValue(':ty', $type, SQLITE3_TEXT);
                        $stmt->bindValue(':d', (string) ($def['value'] ?? ''), SQLITE3_TEXT);
                        $stmt->bindValue(':o', $options, SQLITE3_TEXT);
                        $stmt->bindValue(':s', (string) ($def['section'] ?? 'general'), SQLITE3_TEXT);
                        $stmt->bindValue(':p', 10, SQLITE3_INTEGER);
                        $stmt->bindValue(':src', 'import', SQLITE3_TEXT);
                        $stmt->bindValue(':a', $args, SQLITE3_TEXT);
                        $stmt->execute();
                    }
                    $pref = $db->prepare('INSERT OR REPLACE INTO theme_options (theme, setting_key, value) VALUES (:t, :k, :v)');
                    if ($pref) {
                        $pref->bindValue(':t', $theme, SQLITE3_TEXT);
                        $pref->bindValue(':k', $key, SQLITE3_TEXT);
                        $pref->bindValue(':v', (string) ($def['value'] ?? ''), SQLITE3_TEXT);
                        $pref->execute();
                    }
                }
            }
        }
        @rename($file, $file . '.migrated');
        if (function_exists('wp_simple_save_setting')) {
            wp_simple_save_setting($flag, '1');
        }
    }
}

if (!function_exists('sp_theme_setting_defs')) {
    function sp_theme_setting_defs($theme = null)
    {
        static $cache = [];
        $theme = $theme ?: (function_exists('sp_active_theme') ? sp_active_theme() : false);
        if (!$theme) {
            return [];
        }
        if (isset($cache[$theme])) {
            return $cache[$theme];
        }
        sp_theme_migrate_json($theme);
        $registry = sp_customizer_collect($theme);
        $defs = $registry['settings'];
        foreach (sp_theme_db_rows($theme) as $key => $def) {
            if (!isset($defs[$key])) {
                $defs[$key] = $def;
            }
        }
        foreach ($defs as $key => $def) {
            if (!isset($defs[$key]['args']) || !is_array($defs[$key]['args'])) {
                $defs[$key]['args'] = [];
            }
            $defs[$key]['options'] = sp_theme_decode_options($defs[$key]['options'] ?? []);
        }
        uasort($defs, function ($a, $b) {
            $section = strcmp((string) ($a['section'] ?? ''), (string) ($b['section'] ?? ''));
            if ($section !== 0) {
                return $section;
            }
            return ((int) ($a['priority'] ?? 10)) <=> ((int) ($b['priority'] ?? 10));
        });
        $cache[$theme] = $defs;
        return $defs;
    }
}

if (!function_exists('sp_theme_sections')) {
    function sp_theme_sections($theme = null)
    {
        $theme = $theme ?: (function_exists('sp_active_theme') ? sp_active_theme() : false);
        if (!$theme) {
            return [];
        }
        $registry = sp_customizer_collect($theme);
        $sections = $registry['sections'];
        foreach (sp_theme_setting_defs($theme) as $def) {
            $id = $def['section'] ?: 'general';
            if (!isset($sections[$id])) {
                $sections[$id] = ['id' => $id, 'label' => ucfirst(str_replace(['-', '_'], ' ', $id)), 'priority' => 50];
            }
        }
        uasort($sections, function ($a, $b) {
            return ((int) ($a['priority'] ?? 10)) <=> ((int) ($b['priority'] ?? 10));
        });
        return $sections;
    }
}

if (!function_exists('sp_theme_option_raw')) {
    function sp_theme_option_raw($theme, $key)
    {
        $db = function_exists('wp_simple_get_db') ? wp_simple_get_db() : null;
        if (!$db) {
            return null;
        }
        $stmt = $db->prepare('SELECT value FROM theme_options WHERE theme = :t AND setting_key = :k LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bindValue(':t', $theme, SQLITE3_TEXT);
        $stmt->bindValue(':k', $key, SQLITE3_TEXT);
        $res = $stmt->execute();
        if ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            return (string) $row['value'];
        }
        return null;
    }
}

if (!function_exists('sp_theme_preview_file')) {
    function sp_theme_preview_file($theme)
    {
        $dir = function_exists('sp_storage_dir') ? sp_storage_dir() . '/preview' : GSDATAOTHERPATH . 'simplepress/preview';
        if (function_exists('sp_private_dir')) {
            sp_private_dir($dir);
        }
        $user = function_exists('sp_current_user') ? sp_current_user() : 'guest';
        return $dir . '/' . md5($user . '|' . $theme) . '.json';
    }
}

if (!function_exists('sp_theme_preview_values')) {
    function sp_theme_preview_values($theme)
    {
        if (($_GET['sp_customizer_preview'] ?? '') !== '1' || !function_exists('sp_is_administrator') || !sp_is_administrator()) {
            return [];
        }
        $file = sp_theme_preview_file($theme);
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('sp_theme_option')) {
    function sp_theme_option($key, $default = null)
    {
        $theme = function_exists('sp_active_theme') ? sp_active_theme() : false;
        if (!$theme) {
            return $default === null ? '' : $default;
        }
        $defs = sp_theme_setting_defs($theme);
        $preview = sp_theme_preview_values($theme);
        if (array_key_exists($key, $preview)) {
            return $preview[$key];
        }
        $value = sp_theme_option_raw($theme, $key);
        if ($value === null) {
            $value = isset($defs[$key]['default']) ? $defs[$key]['default'] : null;
        }
        if ($value === null) {
            $value = $default;
        }
        return $value === null ? '' : $value;
    }
}

if (!function_exists('sp_theme_option_set')) {
    function sp_theme_option_set($key, $value, $theme = null)
    {
        $theme = $theme ?: (function_exists('sp_active_theme') ? sp_active_theme() : false);
        if (!$theme) {
            return false;
        }
        $db = function_exists('wp_simple_get_db') ? wp_simple_get_db() : null;
        if (!$db) {
            return false;
        }
        $stmt = $db->prepare('INSERT OR REPLACE INTO theme_options (theme, setting_key, value) VALUES (:t, :k, :v)');
        if (!$stmt) {
            return false;
        }
        $stmt->bindValue(':t', $theme, SQLITE3_TEXT);
        $stmt->bindValue(':k', (string) $key, SQLITE3_TEXT);
        $stmt->bindValue(':v', (string) $value, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }
}

if (!function_exists('sp_theme_clean_html')) {
    function sp_theme_clean_html($html)
    {
        if (function_exists('xss_clean')) {
            return xss_clean((string) $html);
        }
        return strip_tags((string) $html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h1><h2><h3><h4><blockquote><code><pre><img><figure><figcaption><div><span><table><thead><tbody><tr><th><td><hr>');
    }
}

if (!function_exists('sp_theme_sanitize_url_value')) {
    function sp_theme_sanitize_url_value($value)
    {
        $value = trim(str_replace(["\r", "\n", "\0"], '', (string) $value));
        if ($value === '') {
            return '';
        }
        if (stripos($value, 'javascript:') === 0 || stripos($value, 'data:') === 0) {
            return '';
        }
        if (function_exists('sp_url')) {
            $url = sp_url($value, true);
            if ($url) {
                return $url;
            }
        }
        if (function_exists('sp_relative_path')) {
            $rel = sp_relative_path($value);
            if ($rel !== false) {
                return $rel;
            }
        }
        if (preg_match('#^[A-Za-z0-9._\-/?=&%\#]+$#', $value) && strpos($value, '..') === false) {
            return $value;
        }
        return '';
    }
}

if (!function_exists('sp_theme_sanitize_value')) {
    function sp_theme_sanitize_value($type, $value, $def = [])
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $type = strtolower((string) $type);
        $value = (string) $value;
        if ($type === 'dropdown') {
            $type = 'select';
        }
        if ($type === 'wysywig') {
            $type = 'wysiwyg';
        }
        if ($type === 'media') {
            $type = 'image';
        }
        if ($type === 'wysiwyg') {
            return sp_theme_clean_html($value);
        }
        if ($type === 'color' || $type === 'colour') {
            return preg_match('/^#[0-9a-f]{3,6}$/i', $value) ? $value : '#000000';
        }
        if ($type === 'number' || $type === 'range') {
            $number = is_numeric($value) ? $value + 0 : 0;
            $min = sp_def_arg($def, 'min');
            $max = sp_def_arg($def, 'max');
            if ($min !== null && is_numeric($min)) {
                $number = max($number + 0, $min + 0);
            }
            if ($max !== null && is_numeric($max)) {
                $number = min($number + 0, $max + 0);
            }
            return $number;
        }
        if ($type === 'toggle' || $type === 'checkbox') {
            return in_array($value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
        }
        if (in_array($type, ['select', 'radio', 'button_group'], true)) {
            $allowed = sp_theme_option_values($def);
            if ($allowed && !in_array($value, $allowed, true)) {
                return isset($def['default']) ? (string) $def['default'] : '';
            }
            return $value;
        }
        if (in_array($type, ['checkbox_group', 'multiselect'], true)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                $decoded = $value === '' ? [] : preg_split('/\s*,\s*/', $value);
            }
            $allowed = sp_theme_option_values($def);
            $clean = [];
            foreach ($decoded as $item) {
                $item = (string) $item;
                if (!$allowed || in_array($item, $allowed, true)) {
                    $clean[] = $item;
                }
            }
            return json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($type === 'gallery') {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                $decoded = $value === '' ? [] : preg_split('/\s*,\s*/', $value);
            }
            $clean = [];
            foreach (array_slice($decoded, 0, 100) as $item) {
                $item = sp_theme_sanitize_url_value($item);
                if ($item !== '') {
                    $clean[] = $item;
                }
            }
            return json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (in_array($type, ['image', 'file', 'link', 'url'], true)) {
            return sp_theme_sanitize_url_value($value);
        }
        if ($type === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : (string) ($def['default'] ?? '');
        }
        if ($type === 'tel') {
            return substr(preg_replace('/[^0-9+()\-\s]/', '', $value), 0, 60);
        }
        if ($type === 'date') {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
        }
        if ($type === 'time') {
            return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? $value : '';
        }
        if ($type === 'datetime-local' || $type === 'datetime') {
            return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value) ? $value : '';
        }
        if ($type === 'icon') {
            $value = preg_replace('/[^a-z0-9\-]/i', '', $value);
            return $value === '' ? '' : $value;
        }
        if ($type === 'page') {
            return substr(preg_replace('#[^a-z0-9_\-/]#i', '', $value), 0, 200);
        }
        if ($type === 'code') {
            return substr($value, 0, 200000);
        }
        if ($type === 'heading' || $type === 'divider') {
            return '';
        }
        return substr(strip_tags($value), 0, 20000);
    }
}

if (!function_exists('sp_theme_option_array')) {
    function sp_theme_option_array($key, $default = [])
    {
        $value = function_exists('sp_theme_option') ? sp_theme_option($key, '') : '';
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (is_string($value) && trim($value) !== '') {
            return preg_split('/\s*,\s*/', $value);
        }
        return $default;
    }
}

if (!function_exists('sp_theme_settings_save_values')) {
    function sp_theme_settings_save_values($values, $theme = null)
    {
        $theme = $theme ?: (function_exists('sp_active_theme') ? sp_active_theme() : false);
        if (!$theme || !is_array($values)) {
            return false;
        }
        $defs = sp_theme_setting_defs($theme);
        $ok = true;
        foreach ($defs as $key => $def) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $type = strtolower((string) ($def['type'] ?? 'text'));
            if (in_array($type, ['heading', 'divider'], true)) {
                continue;
            }
            $value = sp_theme_sanitize_value($type, $values[$key], $def);
            $ok = sp_theme_option_set($key, $value, $theme) && $ok;
        }
        return $ok;
    }
}

if (!function_exists('sp_theme_preview_save')) {
    function sp_theme_preview_save($values, $theme = null)
    {
        $theme = $theme ?: (function_exists('sp_active_theme') ? sp_active_theme() : false);
        if (!$theme || !is_array($values)) {
            return false;
        }
        return function_exists('sp_write_json') ? sp_write_json(sp_theme_preview_file($theme), $values) : false;
    }
}

if (!function_exists('sp_theme_preview_clear')) {
    function sp_theme_preview_clear($theme = null)
    {
        $theme = $theme ?: (function_exists('sp_active_theme') ? sp_active_theme() : false);
        if (!$theme) {
            return;
        }
        $file = sp_theme_preview_file($theme);
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

if (!function_exists('sp_theme_value')) {
    function sp_theme_value($field)
    {
        return sp_theme_option($field, '');
    }
}

if (!function_exists('mats')) {
    function mats($field)
    {
        echo sp_theme_value($field);
    }
}

if (!function_exists('r_mats')) {
    function r_mats($field)
    {
        return sp_theme_value($field);
    }
}

if (!function_exists('sp_theme_db_save_def')) {
    function sp_theme_db_save_def($theme, $key, $data)
    {
        $db = function_exists('wp_simple_get_db') ? wp_simple_get_db() : null;
        if (!$db) {
            return false;
        }
        $key = preg_replace('/[^a-z0-9_\-]/i', '_', (string) $key);
        if ($key === '') {
            return false;
        }
        $stmt = $db->prepare('INSERT OR REPLACE INTO theme_settings_defs
            (theme, setting_key, label, type, default_value, options, section, priority, source, args)
            VALUES (:t, :k, :l, :ty, :d, :o, :s, :p, :src, :a)');
        if (!$stmt) {
            return false;
        }
        $args = $data['args'] ?? '';
        if (is_array($args)) {
            $args = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $stmt->bindValue(':t', $theme, SQLITE3_TEXT);
        $stmt->bindValue(':k', $key, SQLITE3_TEXT);
        $stmt->bindValue(':l', (string) ($data['label'] ?? $key), SQLITE3_TEXT);
        $stmt->bindValue(':ty', (string) ($data['type'] ?? 'text'), SQLITE3_TEXT);
        $stmt->bindValue(':d', (string) ($data['default'] ?? ''), SQLITE3_TEXT);
        $stmt->bindValue(':o', (string) ($data['options'] ?? ''), SQLITE3_TEXT);
        $stmt->bindValue(':s', (string) ($data['section'] ?? 'general'), SQLITE3_TEXT);
        $stmt->bindValue(':p', (int) ($data['priority'] ?? 10), SQLITE3_INTEGER);
        $stmt->bindValue(':src', 'db', SQLITE3_TEXT);
        $stmt->bindValue(':a', (string) $args, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }
}

if (!function_exists('sp_theme_db_delete_def')) {
    function sp_theme_db_delete_def($theme, $key)
    {
        $db = function_exists('wp_simple_get_db') ? wp_simple_get_db() : null;
        if (!$db) {
            return false;
        }
        $stmt = $db->prepare('DELETE FROM theme_settings_defs WHERE theme = :t AND setting_key = :k AND source != :src');
        if (!$stmt) {
            return false;
        }
        $stmt->bindValue(':t', $theme, SQLITE3_TEXT);
        $stmt->bindValue(':k', $key, SQLITE3_TEXT);
        $stmt->bindValue(':src', 'theme', SQLITE3_TEXT);
        $ok = $stmt->execute() !== false;
        $cleanup = $db->prepare('DELETE FROM theme_options WHERE theme = :t AND setting_key = :k');
        if ($cleanup) {
            $cleanup->bindValue(':t', $theme, SQLITE3_TEXT);
            $cleanup->bindValue(':k', $key, SQLITE3_TEXT);
            $cleanup->execute();
        }
        return $ok;
    }
}

if (!function_exists('wp_simple_theme_settings_view')) {
    function wp_simple_theme_settings_view()
    {
        sp_require_admin();
        $theme = sp_active_theme();
        if (!$theme) {
            echo '<div class="wrap"><div class="notice notice-warning"><p>' . sp_e(wp_i18n('THEME_NO_ACTIVE', 'No active theme.')) . '</p></div></div>';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_save_theme_def'])) {
            sp_require_post('save_theme_def');
            $key = preg_replace('/[^a-z0-9_\-]/i', '_', (string) ($_POST['setting_key'] ?? ''));
            $codeDefs = sp_customizer_collect($theme)['settings'];
            if ($key === '' || isset($codeDefs[$key])) {
                sp_redirect(sp_admin_url('theme', 'error=code_setting'));
            }
            $optionsRaw = trim((string) ($_POST['options'] ?? ''));
            $options = '';
            if ($optionsRaw !== '') {
                $decoded = sp_theme_decode_options($optionsRaw);
                $options = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $argsRaw = trim((string) ($_POST['args'] ?? ''));
            $args = '';
            if ($argsRaw !== '') {
                $decodedArgs = json_decode($argsRaw, true);
                if (is_array($decodedArgs)) {
                    $args = json_encode($decodedArgs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            sp_theme_db_save_def($theme, $key, [
                'label' => substr(strip_tags((string) ($_POST['label'] ?? $key)), 0, 200),
                'type' => preg_replace('/[^a-z0-9_\-]/i', '', (string) ($_POST['type'] ?? 'text')),
                'default' => (string) ($_POST['default_value'] ?? ''),
                'options' => $options,
                'section' => preg_replace('/[^a-z0-9_\-]/i', '-', (string) ($_POST['section'] ?? 'general')),
                'priority' => (int) ($_POST['priority'] ?? 10),
                'args' => $args,
            ]);
            sp_redirect(sp_admin_url('theme', 'saved=1'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_delete_theme_def'])) {
            sp_require_post('delete_theme_def');
            sp_theme_db_delete_def($theme, (string) ($_POST['setting_key'] ?? ''));
            sp_redirect(sp_admin_url('theme', 'deleted=1'));
        }

        $defs = sp_theme_setting_defs($theme);
        $sections = sp_theme_sections($theme);
        $dbRows = sp_theme_db_rows($theme);
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('THEME_TITLE', 'Theme settings')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p><?php echo sp_e(wp_i18n('THEME_SAVED', 'Setting saved.')); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?><div class="notice notice-success"><p><?php echo sp_e(wp_i18n('THEME_DELETED', 'Setting deleted.')); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['error'])): ?><div class="notice notice-error"><p><?php echo sp_e(wp_i18n('THEME_DUPLICATE_KEY', 'This key is already registered in the theme code or is invalid.')); ?></p></div><?php endif; ?>
            <p><?php echo sp_e(wp_i18n('THEME_ACTIVE', 'Active theme:')); ?> <strong><?php echo sp_e($theme); ?></strong></p>
            <p>
                <a class="button button-primary" href="<?php echo sp_e(rtrim((string) ($GLOBALS['SITEURL'] ?? ''), '/') . '/?sp_customizer=1'); ?>" target="_blank" rel="noopener"><?php echo sp_e(wp_i18n('FE_CUSTOMIZE', 'Customize on site')); ?></a>
            </p>

            <div class="postbox">
                <div class="postbox-header"><h2><?php echo sp_e(wp_i18n('THEME_TITLE', 'Theme settings')); ?></h2></div>
                <div class="inside">
                    <?php if (!$defs): ?>
                    <p><?php echo sp_e(wp_i18n('THEME_EMPTY', 'This theme has no settings yet.')); ?></p>
                    <?php else: ?>
                    <table class="widefat">
                        <thead><tr><th><?php echo sp_e(wp_i18n('THEME_SECTION', 'Section')); ?></th><th><?php echo sp_e(wp_i18n('THEME_LABEL', 'Label')); ?></th><th><?php echo sp_e(wp_i18n('THEME_KEY', 'Key')); ?></th><th><?php echo sp_e(wp_i18n('THEME_TYPE', 'Type')); ?></th><th><?php echo sp_e(wp_i18n('THEME_SOURCE', 'Source')); ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($defs as $key => $def): $source = $def['source'] ?? 'db'; ?>
                        <tr>
                            <td><?php echo sp_e($sections[$def['section']]['label'] ?? $def['section']); ?></td>
                            <td><strong><?php echo sp_e($def['label']); ?></strong></td>
                            <td><code><?php echo sp_e($key); ?></code></td>
                            <td><?php echo sp_e($def['type']); ?></td>
                            <td><?php echo $source === 'theme' ? sp_e(wp_i18n('THEME_SOURCE_THEME', 'theme (code)')) : sp_e($source); ?></td>
                            <td>
                                <?php if ($source !== 'theme'): ?>
                                <form method="post" action="<?php echo sp_e(sp_admin_url('theme')); ?>" onsubmit="return confirm(<?php echo sp_e(sp_js(wp_i18n('THEME_DELETE_CONFIRM', 'Delete this setting?'))); ?>);" style="margin:0">
                                    <?php sp_nonce_field('delete_theme_def'); ?>
                                    <input type="hidden" name="sp_delete_theme_def" value="1">
                                    <input type="hidden" name="setting_key" value="<?php echo sp_e($key); ?>">
                                    <button type="submit" class="button"><?php echo sp_e(wp_i18n('THEME_DELETE', 'Delete')); ?></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header"><h2><?php echo sp_e(wp_i18n('THEME_CREATOR', 'Settings creator')); ?></h2></div>
                <div class="inside">
                    <p class="description"><?php echo sp_e(wp_i18n('THEME_CREATOR_HINT', 'Add a field that will be available in the customizer. Fields from the theme code are read-only.')); ?></p>
                    <form method="post" action="<?php echo sp_e(sp_admin_url('theme')); ?>">
                        <?php sp_nonce_field('save_theme_def'); ?>
                        <input type="hidden" name="sp_save_theme_def" value="1">
                        <table class="form-table">
                            <tr><th><label for="sp-def-key"><?php echo sp_e(wp_i18n('THEME_KEY', 'Key')); ?></label></th><td><input class="regular-text" id="sp-def-key" name="setting_key" required pattern="[a-zA-Z0-9._-]+"></td></tr>
                            <tr><th><label for="sp-def-label"><?php echo sp_e(wp_i18n('THEME_LABEL', 'Label')); ?></label></th><td><input class="regular-text" id="sp-def-label" name="label" required></td></tr>
                            <tr><th><label for="sp-def-type"><?php echo sp_e(wp_i18n('THEME_TYPE', 'Type')); ?></label></th><td>
                                <select id="sp-def-type" name="type">
                                    <option value="text">text</option>
                                    <option value="textarea">textarea</option>
                                    <option value="wysiwyg">wysiwyg</option>
                                    <option value="select">select</option>
                                    <option value="dropdown">dropdown (<?php echo sp_e(wp_i18n('THEME_ALIAS_SELECT', 'alias select')); ?>)</option>
                                    <option value="radio">radio</option>
                                    <option value="button_group">button_group</option>
                                    <option value="checkbox_group">checkbox_group</option>
                                    <option value="multiselect">multiselect</option>
                                    <option value="toggle">toggle</option>
                                    <option value="color">color</option>
                                    <option value="image">image</option>
                                    <option value="media">media (<?php echo sp_e(wp_i18n('THEME_ALIAS_IMAGE', 'alias image')); ?>)</option>
                                    <option value="file">file</option>
                                    <option value="link">link (<?php echo sp_e(wp_i18n('THEME_PAGE_OR_URL', 'page / URL')); ?>)</option>
                                    <option value="page">page (<?php echo sp_e(wp_i18n('THEME_PAGE_SLUG', 'page slug')); ?>)</option>
                                    <option value="gallery">gallery</option>
                                    <option value="icon">icon (<?php echo sp_e(wp_i18n('THEME_DASHICONS', 'Dashicons')); ?>)</option>
                                    <option value="number">number</option>
                                    <option value="range">range</option>
                                    <option value="date">date</option>
                                    <option value="time">time</option>
                                    <option value="datetime-local">datetime-local</option>
                                    <option value="email">email</option>
                                    <option value="url">url</option>
                                    <option value="tel">tel</option>
                                    <option value="code">code</option>
                                    <option value="heading">heading (<?php echo sp_e(wp_i18n('THEME_UI_ONLY', 'UI only')); ?>)</option>
                                    <option value="divider">divider (<?php echo sp_e(wp_i18n('THEME_UI_ONLY', 'UI only')); ?>)</option>
                                </select>
                            </td></tr>
                            <tr><th><label for="sp-def-default"><?php echo sp_e(wp_i18n('THEME_DEFAULT_VALUE', 'Default value')); ?></label></th><td><input class="regular-text" id="sp-def-default" name="default_value"></td></tr>
                            <tr><th><label for="sp-def-section"><?php echo sp_e(wp_i18n('THEME_SECTION', 'Section')); ?></label></th><td><input class="regular-text" id="sp-def-section" name="section" value="general"></td></tr>
                            <tr><th><label for="sp-def-priority"><?php echo sp_e(wp_i18n('THEME_PRIORITY', 'Priority')); ?></label></th><td><input type="number" id="sp-def-priority" name="priority" value="10"></td></tr>
                            <tr><th><label for="sp-def-options"><?php echo sp_e(wp_i18n('THEME_OPTIONS', 'Options')); ?></label></th><td><textarea id="sp-def-options" name="options" rows="4" class="large-text code" placeholder='<?php echo sp_e(wp_i18n('THEME_OPTIONS_PLACEHOLDER', '[{"value":"light","label":"Light"}] or lines: light|Light. Separate options with || or a new line.')); ?>'></textarea></td></tr>
                            <tr><th><label for="sp-def-args"><?php echo sp_e(wp_i18n('THEME_ARGS', 'Additional arguments (JSON)')); ?></label></th><td><textarea id="sp-def-args" name="args" rows="4" class="large-text code" placeholder='{"accept":"image/*","source":"pages","multiple":true,"min":0,"max":100,"step":1,"include_private":false}'></textarea><p class="description"><?php echo wp_i18n('THEME_ARGS_HINT', 'Optional. E.g. <code>source</code> for a link: <code>pages</code>, <code>menu</code> or <code>all</code>.'); ?></p></td></tr>
                        </table>
                        <p><button type="submit" class="button button-primary"><?php echo sp_e(wp_i18n('THEME_ADD', 'Add setting')); ?></button></p>
                    </form>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header"><h2><?php echo sp_e(wp_i18n('THEME_PHP_CODE', 'Theme PHP code')); ?></h2></div>
                <div class="inside">
                    <p class="description"><?php echo wp_i18n('THEME_PHP_HINT', 'Paste into the theme <code>functions.php</code> to register the settings in code.'); ?></p>
                    <textarea class="large-text code" rows="12" readonly><?php
                    $snippet = "if (function_exists('sp_customizer_add_setting')) {\n";
                    foreach ($defs as $key => $def) {
                        $codeArgs = [
                            'label' => $def['label'],
                            'type' => $def['type'],
                            'default' => $def['default'],
                            'section' => $def['section'],
                            'priority' => $def['priority'] ?? 10,
                            'options' => $def['options'] ?? [],
                        ];
                        if (!empty($def['args']) && is_array($def['args'])) {
                            $codeArgs = array_merge($codeArgs, $def['args']);
                        }
                        if (!empty($def['description'])) {
                            $codeArgs['description'] = $def['description'];
                        }
                        $snippet .= "    sp_customizer_add_setting(" . var_export($key, true) . ", " . var_export($codeArgs, true) . ");\n";
                    }
                    $snippet .= "}\n";
                    echo sp_e($snippet);
                    ?></textarea>
                </div>
            </div>
        </div>
        <?php
    }
}
