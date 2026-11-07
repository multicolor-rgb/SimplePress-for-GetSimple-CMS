<?php
/**
 * SimplePress storage layer: SQLite with a JSON fallback.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('wp_simple_get_db')) {
    function wp_simple_get_db()
    {
        static $db = null;
        if ($db !== null) {
            return $db instanceof SQLite3 ? $db : null;
        }
        if (!class_exists('SQLite3')) {
            $db = false;
            return null;
        }

        $newFile = GSDATAOTHERPATH . 'simplepress.db';
        $oldFile = GSDATAOTHERPATH . 'wp_simple_admin.db';
        if (!file_exists($newFile) && file_exists($oldFile)) {
            @copy($oldFile, $newFile);
            @chmod($newFile, 0640);
        }
        if (!file_exists($newFile)) {
            sp_private_dir(GSDATAOTHERPATH . 'simplepress');
        }

        try {
            $db = new SQLite3($newFile);
            $db->busyTimeout(5000);
            $db->exec('PRAGMA journal_mode = WAL');
            $db->exec("CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS user_meta (
                username TEXT PRIMARY KEY,
                role TEXT DEFAULT 'administrator',
                display_name TEXT DEFAULT ''
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS user_prefs (
                username TEXT NOT NULL,
                pref_key TEXT NOT NULL,
                pref_value TEXT DEFAULT '',
                PRIMARY KEY (username, pref_key)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS theme_settings_defs (
                theme TEXT NOT NULL,
                setting_key TEXT NOT NULL,
                label TEXT DEFAULT '',
                type TEXT DEFAULT 'text',
                default_value TEXT DEFAULT '',
                options TEXT DEFAULT '',
                section TEXT DEFAULT 'general',
                priority INTEGER DEFAULT 0,
                source TEXT DEFAULT 'db',
                args TEXT DEFAULT '',
                PRIMARY KEY (theme, setting_key)
            )");
            $defColumns = [];
            $defInfo = $db->query('PRAGMA table_info(theme_settings_defs)');
            if ($defInfo) {
                while ($defColumn = $defInfo->fetchArray(SQLITE3_ASSOC)) {
                    if (!empty($defColumn['name'])) {
                        $defColumns[] = (string) $defColumn['name'];
                    }
                }
            }
            if ($defColumns && !in_array('args', $defColumns, true)) {
                $db->exec("ALTER TABLE theme_settings_defs ADD COLUMN args TEXT DEFAULT ''");
            }
            $db->exec("CREATE TABLE IF NOT EXISTS theme_options (
                theme TEXT NOT NULL,
                setting_key TEXT NOT NULL,
                value TEXT DEFAULT '',
                PRIMARY KEY (theme, setting_key)
            )");
            @chmod($newFile, 0640);
            return $db;
        } catch (Exception $e) {
            $db = false;
            return null;
        }
    }
}

if (!function_exists('wp_simple_get_setting')) {
    function wp_simple_get_setting($key, $default = '')
    {
        $key = (string) $key;
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key LIMIT 1');
            if ($stmt) {
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $res = $stmt->execute();
                if ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                    return $row['value'];
                }
            }
        }
        $json = sp_read_json(sp_settings_path(), []);
        return array_key_exists($key, $json) ? $json[$key] : $default;
    }
}

if (!function_exists('wp_simple_save_setting')) {
    function wp_simple_save_setting($key, $val)
    {
        $key = (string) $key;
        $val = (string) $val;
        $ok = false;
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :val)');
            if ($stmt) {
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $stmt->bindValue(':val', $val, SQLITE3_TEXT);
                $ok = $stmt->execute() !== false;
            }
        }
        $json = sp_read_json(sp_settings_path(), []);
        $json[$key] = $val;
        $jsonOk = sp_write_json(sp_settings_path(), $json);
        return $ok || $jsonOk;
    }
}

if (!function_exists('wp_simple_get_user_role')) {
    function wp_simple_get_user_role($username)
    {
        $username = function_exists('sp_user_id') ? sp_user_id($username) : $username;
        if ($username === false) {
            return 'administrator';
        }
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare('SELECT role FROM user_meta WHERE username = :u LIMIT 1');
            if ($stmt) {
                $stmt->bindValue(':u', $username, SQLITE3_TEXT);
                $res = $stmt->execute();
                if ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                    return $row['role'];
                }
            }
        }
        $roles = sp_read_json(sp_storage_dir() . '/roles.json', []);
        return $roles[$username] ?? 'administrator';
    }
}

if (!function_exists('wp_simple_set_user_meta')) {
    function wp_simple_set_user_meta($username, $role, $display_name = '')
    {
        $username = function_exists('sp_user_id') ? sp_user_id($username) : $username;
        if ($username === false) {
            return false;
        }
        $role = in_array($role, ['administrator', 'editor'], true) ? $role : 'administrator';
        $ok = false;
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO user_meta (username, role, display_name) VALUES (:u, :r, :d)');
            if ($stmt) {
                $stmt->bindValue(':u', $username, SQLITE3_TEXT);
                $stmt->bindValue(':r', $role, SQLITE3_TEXT);
                $stmt->bindValue(':d', $display_name, SQLITE3_TEXT);
                $ok = $stmt->execute() !== false;
            }
        }
        $roles = sp_read_json(sp_storage_dir() . '/roles.json', []);
        $roles[$username] = $role;
        $jsonOk = sp_write_json(sp_storage_dir() . '/roles.json', $roles);
        return $ok || $jsonOk;
    }
}

if (!function_exists('wp_simple_get_user_pref')) {
    function wp_simple_get_user_pref($username, $key, $default = '')
    {
        $username = function_exists('sp_user_id') ? sp_user_id($username) : $username;
        $key = preg_replace('/[^a-z0-9_\-]/i', '', (string) $key);
        if ($username === false || $key === '') {
            return $default;
        }
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare('SELECT pref_value FROM user_prefs WHERE username = :u AND pref_key = :k LIMIT 1');
            if ($stmt) {
                $stmt->bindValue(':u', $username, SQLITE3_TEXT);
                $stmt->bindValue(':k', $key, SQLITE3_TEXT);
                $res = $stmt->execute();
                if ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                    return (string) $row['pref_value'];
                }
            }
        }
        $prefs = sp_read_json(sp_storage_dir() . '/user_prefs.json', []);
        return isset($prefs[$username][$key]) ? (string) $prefs[$username][$key] : $default;
    }
}

if (!function_exists('wp_simple_set_user_pref')) {
    function wp_simple_set_user_pref($username, $key, $value)
    {
        $username = function_exists('sp_user_id') ? sp_user_id($username) : $username;
        $key = preg_replace('/[^a-z0-9_\-]/i', '', (string) $key);
        if ($username === false || $key === '') {
            return false;
        }
        $value = (string) $value;
        $ok = false;
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO user_prefs (username, pref_key, pref_value) VALUES (:u, :k, :v)');
            if ($stmt) {
                $stmt->bindValue(':u', $username, SQLITE3_TEXT);
                $stmt->bindValue(':k', $key, SQLITE3_TEXT);
                $stmt->bindValue(':v', $value, SQLITE3_TEXT);
                $ok = $stmt->execute() !== false;
            }
        }
        $prefs = sp_read_json(sp_storage_dir() . '/user_prefs.json', []);
        $prefs[$username][$key] = $value;
        $jsonOk = sp_write_json(sp_storage_dir() . '/user_prefs.json', $prefs);
        return $ok || $jsonOk;
    }
}

if (!function_exists('sp_user_pref')) {
    function sp_user_pref($key, $default = '')
    {
        $user = function_exists('sp_current_user') ? sp_current_user() : '';
        if ($user === '') {
            return $default;
        }
        $value = wp_simple_get_user_pref($user, $key, '');
        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('sp_save_user_pref')) {
    function sp_save_user_pref($key, $value)
    {
        $user = function_exists('sp_current_user') ? sp_current_user() : '';
        if ($user === '') {
            return false;
        }
        return wp_simple_set_user_pref($user, $key, $value);
    }
}

if (!function_exists('sp_active_editor')) {
    function sp_active_editor()
    {
        $pref = sp_user_pref('editor', '');
        if (in_array($pref, ['ckeditor', 'tinymce'], true)) {
            return $pref;
        }
        return function_exists('wp_simple_get_setting') ? wp_simple_get_setting('active_editor', 'ckeditor') : 'ckeditor';
    }
}

if (!function_exists('sp_delete_user_meta')) {
    function sp_delete_user_meta($username)
    {
        $username = sp_user_id($username);
        if ($username === false) {
            return;
        }
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare('DELETE FROM user_meta WHERE username = :u');
            if ($stmt) {
                $stmt->bindValue(':u', $username, SQLITE3_TEXT);
                $stmt->execute();
            }
            $stmt = $db->prepare('DELETE FROM user_prefs WHERE username = :u');
            if ($stmt) {
                $stmt->bindValue(':u', $username, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
        $roles = sp_read_json(sp_storage_dir() . '/roles.json', []);
        unset($roles[$username]);
        sp_write_json(sp_storage_dir() . '/roles.json', $roles);
        $prefs = sp_read_json(sp_storage_dir() . '/user_prefs.json', []);
        unset($prefs[$username]);
        sp_write_json(sp_storage_dir() . '/user_prefs.json', $prefs);
    }
}

if (!function_exists('sp_all_settings')) {
    function sp_all_settings()
    {
        $all = sp_read_json(sp_settings_path(), []);
        $db = wp_simple_get_db();
        if ($db) {
            $res = $db->query('SELECT key, value FROM settings');
            if ($res) {
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    $all[$row['key']] = $row['value'];
                }
            }
        }
        return $all;
    }
}
