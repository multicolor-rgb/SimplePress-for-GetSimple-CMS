<?php
/**
 * SimplePress security helpers.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_e')) {
    function sp_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sp_nonce')) {
    function sp_nonce($action)
    {
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string) $action);
        if (function_exists('get_nonce')) {
            return get_nonce($action, 'simplepress', false);
        }
        $user = isset($GLOBALS['USR']) ? $GLOBALS['USR'] : 'guest';
        $salt = defined('GS_SALT') ? GS_SALT : (defined('GSUSECUSTOMSALT') ? GSUSECUSTOMSALT : __FILE__);
        return hash_hmac('sha256', $action . '|' . $user . '|' . floor(time() / 3600), $salt);
    }
}

if (!function_exists('sp_verify_nonce')) {
    function sp_verify_nonce($action, $value = null)
    {
        if ($value === null) {
            $value = $_POST['sp_nonce'] ?? ($_GET['sp_nonce'] ?? ($_SERVER['HTTP_X_SIMPLEPRESS_NONCE'] ?? ''));
        }
        if (!is_string($value) || $value === '') {
            return false;
        }
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string) $action);
        if (function_exists('check_nonce')) {
            return check_nonce($value, $action, 'simplepress');
        }
        return hash_equals(sp_nonce($action), $value);
    }
}

if (!function_exists('sp_nonce_field')) {
    function sp_nonce_field($action)
    {
        echo '<input type="hidden" name="sp_nonce" value="' . sp_e(sp_nonce($action)) . '">';
    }
}

if (!function_exists('sp_require_post')) {
    function sp_require_post($action)
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !sp_verify_nonce($action)) {
            sp_die('CSRF validation failed. Please reload the page and try again.', 403);
        }
    }
}

if (!function_exists('sp_require_get_nonce')) {
    function sp_require_get_nonce($action)
    {
        if (!sp_verify_nonce($action)) {
            sp_die('Security validation failed.', 403);
        }
    }
}

if (!function_exists('sp_current_user')) {
    function sp_current_user()
    {
        return isset($GLOBALS['USR']) ? (string) $GLOBALS['USR'] : '';
    }
}

if (!function_exists('sp_user_id')) {
    function sp_user_id($username)
    {
        $username = strtolower(trim((string) $username));
        if (!preg_match('/^[a-z0-9][a-z0-9._\-]{0,63}$/', $username)) {
            return false;
        }
        return function_exists('_id') ? _id($username) : $username;
    }
}

if (!function_exists('sp_user_role')) {
    function sp_user_role($username)
    {
        $username = sp_user_id($username);
        if ($username === false) {
            return 'administrator';
        }
        $role = function_exists('wp_simple_get_user_role') ? wp_simple_get_user_role($username) : '';
        if (in_array($role, ['administrator', 'editor'], true)) {
            return $role;
        }
        $file = GSUSERSPATH . $username . '.xml';
        if (is_file($file)) {
            $xml = @simplexml_load_file($file);
            if ($xml && (string) $xml->ROLE !== '') {
                $xmlRole = strtolower((string) $xml->ROLE);
                if ($xmlRole === 'admin') {
                    return 'administrator';
                }
                if ($xmlRole === 'editor') {
                    return 'editor';
                }
            }
        }
        return 'administrator';
    }
}

if (!function_exists('sp_is_authenticated')) {
    function sp_is_authenticated()
    {
        return function_exists('cookie_check') && cookie_check() && sp_current_user() !== '';
    }
}

if (!function_exists('sp_is_administrator')) {
    function sp_is_administrator()
    {
        return sp_is_authenticated() && sp_user_role(sp_current_user()) === 'administrator';
    }
}

if (!function_exists('sp_require_authenticated')) {
    function sp_require_authenticated($json = false)
    {
        if (sp_is_authenticated()) {
            return;
        }
        if ($json) {
            sp_json(['status' => 'error', 'message' => 'Authentication required'], 403);
        }
        sp_die('Authentication required.', 403);
    }
}

if (!function_exists('sp_require_admin')) {
    function sp_require_admin($json = false)
    {
        if (!sp_is_authenticated()) {
            if ($json) {
                sp_json(['status' => 'error', 'message' => 'Authentication required'], 403);
            }
            sp_die('Authentication required.', 403);
        }
        if (!sp_is_administrator()) {
            if ($json) {
                sp_json(['status' => 'error', 'message' => 'Administrator role required'], 403);
            }
            sp_die('Administrator role required.', 403);
        }
    }
}

if (!function_exists('sp_die')) {
    function sp_die($message, $status = 400)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><html><head><meta charset="utf-8"><title>SimplePress</title></head><body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f0f0f1;color:#3c434a;padding:40px"><h1 style="font-size:23px;font-weight:400">' . sp_e($message) . '</h1></body></html>';
        exit;
    }
}

if (!function_exists('sp_json')) {
    function sp_json($data, $status = 200)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

if (!function_exists('sp_redirect')) {
    function sp_redirect($url)
    {
        if (function_exists('redirect')) {
            redirect($url);
        }
        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
            exit;
        }
        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        exit;
    }
}

if (!function_exists('sp_url')) {
    function sp_url($url, $allowRelative = false)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        if ($allowRelative && $url[0] === '/' && strpos($url, '//') !== 0) {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }
        if (preg_match('/[\x00-\x20<>"\']/', $url)) {
            return false;
        }
        return $url;
    }
}

if (!function_exists('sp_relative_path')) {
    function sp_relative_path($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        $path = preg_replace('/[\x00-\x1F\x7F]/', '', $path);
        $path = ltrim($path, '/');
        if ($path === '' || preg_match('#(^|/)\.\.(/|$)#', $path) || preg_match('#^[a-zA-Z]:#', $path)) {
            return false;
        }
        return $path;
    }
}

if (!function_exists('sp_path_within')) {
    function sp_path_within($path, $base, $mustExist = true)
    {
        $baseReal = realpath($base);
        if ($baseReal === false) {
            return false;
        }
        if ($mustExist) {
            $real = realpath($path);
            if ($real === false) {
                return false;
            }
        } else {
            $parent = realpath(dirname($path));
            if ($parent === false) {
                return false;
            }
            $real = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
        }
        $prefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return $real === $baseReal || strpos($real, $prefix) === 0;
    }
}

if (!function_exists('sp_safe_basename')) {
    function sp_safe_basename($name, $pattern = '/^[A-Za-z0-9._\- ]+$/')
    {
        $name = basename(str_replace(['\\', "\0"], '/', (string) $name));
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..' || !preg_match($pattern, $name)) {
            return false;
        }
        return $name;
    }
}

if (!function_exists('sp_private_dir')) {
    function sp_private_dir($dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $htaccess = rtrim($dir, '/\\') . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        $index = rtrim($dir, '/\\') . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
        return is_dir($dir);
    }
}

if (!function_exists('sp_public_dir')) {
    function sp_public_dir($dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $htaccess = rtrim($dir, '/\\') . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\nRequire all granted\n</IfModule>\n<IfModule !mod_authz_core.c>\nAllow from all\n</IfModule>\n<FilesMatch \"\\.(php[0-9]?|ph[0-9]?|phtml|phtm|phar|pl|py|jsp|asp|htm|shtml|sh|cgi)$\">\nRequire all denied\nDeny from all\n</FilesMatch>\n");
        }
        $index = rtrim($dir, '/\\') . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
        return is_dir($dir);
    }
}

if (!function_exists('sp_read_json')) {
    function sp_read_json($file, $default = [])
    {
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : $default;
    }
}

if (!function_exists('sp_write_file')) {
    function sp_write_file($file, $contents, $mode = 0640)
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        $tmp = $file . '.tmp.' . bin2hex(random_bytes(6));
        $fp = @fopen($tmp, 'wb');
        if (!$fp) {
            return false;
        }
        $ok = @flock($fp, LOCK_EX);
        if ($ok) {
            $ok = @fwrite($fp, $contents) !== false;
            @fflush($fp);
            @flock($fp, LOCK_UN);
        }
        @fclose($fp);
        if (!$ok) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, $mode);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        @chmod($file, $mode);
        return true;
    }
}

if (!function_exists('sp_write_json')) {
    function sp_write_json($file, $data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return false;
        }
        return sp_write_file($file, $json, 0640);
    }
}

if (!function_exists('sp_log')) {
    function sp_log($action, $details = '')
    {
        $dir = GSDATAOTHERPATH . 'simplepress';
        sp_private_dir($dir);
        $user = sp_current_user() !== '' ? sp_current_user() : 'guest';
        $line = '[' . date('Y-m-d H:i:s') . '] [' . preg_replace('/[^A-Za-z0-9._\-]/', '', $user) . '] [' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '] [' . $action . '] ' . str_replace(["\r", "\n"], ' ', $details) . PHP_EOL;
        @file_put_contents($dir . '/security.log', $line, FILE_APPEND | LOCK_EX);
        @chmod($dir . '/security.log', 0640);
    }
}

if (!function_exists('sp_storage_dir')) {
    function sp_storage_dir()
    {
        $dir = GSDATAOTHERPATH . 'simplepress';
        sp_private_dir($dir);
        return $dir;
    }
}

if (!function_exists('sp_settings_path')) {
    function sp_settings_path()
    {
        return sp_storage_dir() . '/settings.json';
    }
}
