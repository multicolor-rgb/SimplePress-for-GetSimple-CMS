<?php
/**
 * Secure plugin catalog, installer and uninstaller.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_plugin_hosts')) {
    function sp_plugin_hosts()
    {
        return ['github.com', 'codeload.github.com', 'api.github.com', 'raw.githubusercontent.com', 'objects.githubusercontent.com', 'github-releases.githubusercontent.com', 'getsimple-ce.ovh', 'www.getsimple-ce.ovh'];
    }
}

if (!function_exists('sp_plugin_normalize_url')) {
    function sp_plugin_normalize_url($url)
    {
        $url = trim((string) $url);
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }
        $host = strtolower((string) $parts['host']);
        if ($host !== 'github.com') {
            return $url;
        }
        $segments = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/')), 'strlen'));
        if (count($segments) < 2) {
            return $url;
        }
        $owner = $segments[0];
        $repo = preg_replace('/\.git$/i', '', $segments[1]);
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $owner) || !preg_match('/^[A-Za-z0-9_.\-]+$/', $repo)) {
            return $url;
        }
        if (count($segments) > 2) {
            if ($segments[2] === 'tree' && !empty($segments[3])) {
                $branch = implode('/', array_slice($segments, 3));
                return 'https://github.com/' . $owner . '/' . $repo . '/archive/refs/heads/' . $branch . '.zip';
            }
            return $url;
        }
        return 'https://github.com/' . $owner . '/' . $repo . '/archive/refs/heads/main.zip';
    }
}

if (!function_exists('sp_plugin_detect_main')) {
    function sp_plugin_detect_main($dir)
    {
        $fallback = null;
        foreach ((array) @scandir($dir) as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = rtrim($dir, '/\\') . '/' . $item;
            if (!is_file($path) || is_link($path) || strtolower(pathinfo($item, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }
            $head = (string) @file_get_contents($path, false, null, 0, 16384);
            if (preg_match('/register_plugin\s*\(/', $head) || preg_match('/\*\s*Plugin Name\s*:/i', $head)) {
                return $path;
            }
            if ($fallback === null && strtolower($item) !== 'index.php') {
                $fallback = $path;
            }
        }
        return $fallback;
    }
}

if (!function_exists('sp_plugin_should_skip')) {
    function sp_plugin_should_skip($item)
    {
        $item = strtolower((string) $item);
        if (in_array($item, [
            '.git', '.github', '.gitignore', '.gitattributes', '.editorconfig',
            '.idea', '.vscode', '__macosx', '.ds_store', 'thumbs.db',
            'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
            'yarn.lock', 'phpunit.xml', 'phpunit.xml.dist', '.travis.yml', '.gitlab-ci.yml',
        ], true)) {
            return true;
        }
        return (bool) preg_match('/^(readme|license|licence|changelog|copying|contributing|code_of_conduct)(\..*)?$/i', $item);
    }
}

if (!function_exists('sp_plugin_catalog')) {
    function sp_plugin_catalog()
    {
        $cache = GSCACHEPATH . 'simplepress_plugin_catalog.json';
        if (is_file($cache) && filemtime($cache) > time() - 21600) {
            $data = sp_read_json($cache, []);
            if (is_array($data) && !empty($data)) {
                return $data;
            }
        }
        $url = 'https://getsimplecms-ce-plugins.github.io/db.json';
        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SimplePress/' . SP_VERSION);
            curl_setopt($ch, CURLOPT_MAXFILESIZE, 5 * 1024 * 1024);
            $raw = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => 0, 'user_agent' => 'SimplePress/' . SP_VERSION], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $raw = @file_get_contents($url, false, $ctx, 0, 5 * 1024 * 1024);
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data)) {
            sp_write_json($cache, $data);
            return $data;
        }
        return [];
    }
}

if (!function_exists('sp_plugin_fetch')) {
    function sp_plugin_fetch($url, $target)
    {
        if (function_exists('curl_init')) {
            $fp = @fopen($target, 'wb');
            if (!$fp) {
                return [false, 'Nie można zapisać pliku tymczasowego.'];
            }
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_MAXFILESIZE, 50 * 1024 * 1024);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SimplePress/' . SP_VERSION);
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            fclose($fp);
            if (!$ok || $status < 200 || $status >= 300) {
                @unlink($target);
                return [false, 'Nie udało się pobrać archiwum.'];
            }
            $host = strtolower((string) parse_url($effective, PHP_URL_HOST));
            if (!in_array($host, sp_plugin_hosts(), true)) {
                @unlink($target);
                return [false, 'Przekierowanie na niedozwolony host.'];
            }
            if (filesize($target) > 50 * 1024 * 1024) {
                @unlink($target);
                return [false, 'Archiwum jest zbyt duże.'];
            }
            return [true, ''];
        }
        $ctx = stream_context_create(['http' => ['timeout' => 120, 'follow_location' => 0, 'user_agent' => 'SimplePress/' . SP_VERSION], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $data = @file_get_contents($url, false, $ctx, 0, 50 * 1024 * 1024 + 1);
        if ($data === false || strlen($data) > 50 * 1024 * 1024) {
            return [false, 'Nie udało się pobrać archiwum.'];
        }
        return sp_write_file($target, $data, 0640) ? [true, ''] : [false, 'Nie można zapisać archiwum.'];
    }
}

if (!function_exists('sp_plugin_contains_php')) {
    function sp_plugin_contains_php($dir)
    {
        foreach ((array) @scandir($dir) as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = $dir . '/' . $item;
            if (is_link($path)) { continue; }
            if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') { return true; }
            if (is_dir($path) && sp_plugin_contains_php($path)) { return true; }
        }
        return false;
    }
}

if (!function_exists('sp_plugin_copy')) {
    function sp_plugin_copy($source, $destination, $skipTop = false)
    {
        $base = realpath($destination);
        if ($base === false) { return false; }
        $ok = true;
        foreach ((array) @scandir($source) as $item) {
            if ($item === '.' || $item === '..') { continue; }
            if ($skipTop && sp_plugin_should_skip($item)) { continue; }
            $src = $source . '/' . $item;
            $dst = rtrim($destination, '/\\') . '/' . $item;
            if (is_link($src)) { continue; }
            if (is_dir($src)) {
                if (!is_dir($dst) && !@mkdir($dst, 0755, true)) { $ok = false; continue; }
                if (!sp_path_within($dst, $base, true) || !sp_plugin_copy($src, $dst)) { $ok = false; }
            } elseif (is_file($src)) {
                if (!sp_path_within($dst, $base, false) || !@copy($src, $dst)) { $ok = false; }
                else { @chmod($dst, 0644); }
            }
        }
        return $ok;
    }
}

if (!function_exists('sp_install_plugin_zip')) {
    function sp_install_plugin_zip($url)
    {
        $url = sp_url($url, false);
        if (!$url || stripos($url, 'https://') !== 0) {
            return [false, 'Podaj poprawny adres HTTPS.'];
        }
        $normalized = sp_plugin_normalize_url($url);
        $candidates = [$normalized];
        if ($normalized !== $url && preg_match('#/archive/refs/heads/main\.zip$#', $normalized)) {
            $candidates[] = preg_replace('#/main\.zip$#', '/master.zip', $normalized);
        }
        foreach ($candidates as $candidate) {
            $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
            if (!in_array($host, sp_plugin_hosts(), true)) {
                return [false, 'Ten host nie jest dozwolony.'];
            }
        }
        if (!class_exists('ZipArchive')) {
            return [false, 'Rozszerzenie ZIP nie jest dostępne.'];
        }
        $tmpRoot = sp_storage_dir() . '/tmp';
        sp_private_dir($tmpRoot);
        $zipFile = $tmpRoot . '/download-' . bin2hex(random_bytes(8)) . '.zip';
        $fetched = [false, 'Nie udało się pobrać archiwum.'];
        foreach ($candidates as $candidate) {
            $fetched = sp_plugin_fetch($candidate, $zipFile);
            if ($fetched[0]) {
                $url = $candidate;
                break;
            }
            @unlink($zipFile);
        }
        if (!$fetched[0]) {
            @unlink($zipFile);
            return $fetched;
        }
        $extract = $tmpRoot . '/extract-' . bin2hex(random_bytes(8));
        @mkdir($extract, 0755, true);
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            @unlink($zipFile);
            sp_security_delete_tree($extract, $tmpRoot);
            return [false, 'Nie można otworzyć archiwum ZIP.'];
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            $mode = (int) (($stat['external_attr'] ?? 0) >> 16) & 0170000;
            if ($name === '' || $name[0] === '/' || strpos($name, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $name) || $mode === 0120000) {
                $zip->close();
                @unlink($zipFile);
                sp_security_delete_tree($extract, $tmpRoot);
                return [false, 'Archiwum zawiera niebezpieczne wpisy.'];
            }
            $total += (int) ($stat['size'] ?? 0);
            if ($total > 100 * 1024 * 1024 || $zip->numFiles > 3000) {
                $zip->close();
                @unlink($zipFile);
                sp_security_delete_tree($extract, $tmpRoot);
                return [false, 'Archiwum jest zbyt duże.'];
            }
        }
        if (!$zip->extractTo($extract)) {
            $zip->close();
            @unlink($zipFile);
            sp_security_delete_tree($extract, $tmpRoot);
            return [false, 'Nie udało się rozpakować archiwum.'];
        }
        $zip->close();
        @unlink($zipFile);
        $items = array_values(array_filter((array) @scandir($extract), function ($v) { return $v !== '.' && $v !== '..'; }));
        $source = $extract;
        if (count($items) === 1 && is_dir($extract . '/' . $items[0])) {
            $source = $extract . '/' . $items[0];
        }
        $mainFile = sp_plugin_detect_main($source);
        if ($mainFile === null) {
            foreach ((array) @scandir($source) as $item) {
                if ($item === '.' || $item === '..') { continue; }
                $candidate = rtrim($source, '/\\') . '/' . $item;
                if (!is_dir($candidate) || is_link($candidate)) { continue; }
                $nestedMain = sp_plugin_detect_main($candidate);
                if ($nestedMain !== null) {
                    $source = $candidate;
                    $mainFile = $nestedMain;
                    break;
                }
            }
        }
        if ($mainFile === null) {
            sp_security_delete_tree($extract, $tmpRoot);
            return [false, 'Archiwum nie zawiera głównego pliku pluginu.'];
        }
        $pluginId = basename($mainFile, '.php');
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', (string) $pluginId) || strtolower($pluginId) === 'simplepress') {
            sp_security_delete_tree($extract, $tmpRoot);
            return [false, 'Archiwum nie zawiera poprawnego pluginu.'];
        }
        foreach ((array) @scandir($source) as $item) {
            if ($item === '.' || $item === '..') { continue; }
            if (strtolower($item) === 'simplepress' || strtolower($item) === 'simplepress.php') {
                sp_security_delete_tree($extract, $tmpRoot);
                return [false, 'Archiwum próbuje nadpisać SimplePress.'];
            }
        }
        $ok = sp_plugin_copy($source, GSPLUGINPATH, true);
        sp_security_delete_tree($extract, $tmpRoot);
        if (!$ok) {
            return [false, 'Nie udało się skopiować plików pluginu.'];
        }
        sp_log('plugin_install', $pluginId . ' ' . $url);
        return [true, 'Plugin ' . $pluginId . ' został zainstalowany.'];
    }
}

if (!function_exists('sp_security_delete_tree')) {
    function sp_security_delete_tree($path, $base)
    {
        $path = rtrim($path, '/\\');
        $base = rtrim($base, '/\\');
        if ($path === '' || $base === '' || realpath($path) === realpath($base) || !sp_path_within($path, $base, true)) {
            return false;
        }
        if (is_link($path) || is_file($path)) { return @unlink($path); }
        if (is_dir($path)) {
            foreach ((array) @scandir($path) as $item) {
                if ($item === '.' || $item === '..') { continue; }
                sp_security_delete_tree($path . '/' . $item, $base);
            }
            return @rmdir($path);
        }
        return false;
    }
}

if (!function_exists('sp_uninstall_plugin')) {
    function sp_uninstall_plugin($id)
    {
        $id = (string) $id;
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $id) || $id === 'simplepress') {
            return [false, 'Nieprawidłowa nazwa pluginu.'];
        }
        $root = realpath(GSPLUGINPATH);
        if ($root === false) { return [false, 'Nie można ustalić katalogu pluginów.']; }
        $did = false;
        $dir = GSPLUGINPATH . $id;
        if (is_dir($dir) && sp_path_within($dir, $root, true)) {
            $did = sp_security_delete_tree($dir, $root) || $did;
        }
        $file = GSPLUGINPATH . $id . '.php';
        if (is_file($file) && sp_path_within($file, $root, true)) {
            $did = @unlink($file) || $did;
        }
        if (!$did) { return [false, 'Nie znaleziono pluginu.']; }
        sp_log('plugin_uninstall', $id);
        return [true, 'Plugin ' . $id . ' został usunięty.'];
    }
}

if (!function_exists('wp_simple_plugin_repo_view')) {
    function wp_simple_plugin_repo_view()
    {
        sp_require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_install_plugin'])) {
            sp_require_post('install_plugin');
            $result = sp_install_plugin_zip((string) ($_POST['url'] ?? ''));
            sp_redirect(sp_admin_url('plugin-repo', $result[0] ? 'saved=install' : 'error=' . rawurlencode($result[1])));
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_uninstall_plugin'])) {
            sp_require_post('uninstall_plugin');
            $result = sp_uninstall_plugin((string) ($_POST['plugin'] ?? ''));
            sp_redirect(sp_admin_url('plugin-repo', $result[0] ? 'saved=uninstall' : 'error=' . rawurlencode($result[1])));
        }
        $catalog = sp_plugin_catalog();
        $installed = [];
        foreach (glob(GSPLUGINPATH . '*.php') as $file) {
            $id = basename($file, '.php');
            if ($id === 'index' || $id === 'simplepress') { continue; }
            $head = (string) @file_get_contents($file, false, null, 0, 8192);
            if (strpos($head, 'register_plugin') === false) { continue; }
            $installed[$id] = isset($GLOBALS['plugin_info'][$id]['name']) ? $GLOBALS['plugin_info'][$id]['name'] : $id;
        }
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('TAB_PLUGINS', 'Wtyczki')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Operacja zakończona pomyślnie.</p></div><?php endif; ?>
            <?php if (isset($_GET['error'])): ?><div class="notice notice-error"><p><?php echo sp_e((string) $_GET['error']); ?></p></div><?php endif; ?>
            <div class="postbox"><div class="postbox-header"><h2>Zainstaluj z archiwum ZIP</h2></div><div class="inside">
                <form method="post" action="<?php echo sp_e(sp_admin_url('plugin-repo')); ?>">
                    <?php sp_nonce_field('install_plugin'); ?>
                    <input type="hidden" name="sp_install_plugin" value="1">
                    <input type="url" class="large-text" name="url" placeholder="https://github.com/..." required>
                    <p class="description">Dozwolone hosty: GitHub i getsimple-ce.ovh (tylko HTTPS).</p>
                    <p><button type="submit" class="button button-primary">Zainstaluj</button></p>
                </form>
            </div></div>
            <div class="postbox"><div class="postbox-header"><h2>Zainstalowane wtyczki</h2></div><div class="inside">
                <table class="widefat striped">
                    <thead><tr><th>Wtyczka</th><th>Akcje</th></tr></thead><tbody>
                    <?php foreach ($installed as $id => $name): ?>
                    <tr><td><strong><?php echo sp_e($name); ?></strong> <code><?php echo sp_e($id); ?></code></td><td>
                        <form method="post" action="<?php echo sp_e(sp_admin_url('plugin-repo')); ?>" style="display:inline" onsubmit="return confirm('Usunąć ten plugin?');">
                            <?php sp_nonce_field('uninstall_plugin'); ?>
                            <input type="hidden" name="sp_uninstall_plugin" value="1">
                            <input type="hidden" name="plugin" value="<?php echo sp_e($id); ?>">
                            <button type="submit" class="button button-link-delete">Odinstaluj</button>
                        </form>
                    </td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($installed)): ?><tr><td colspan="2">Brak wtyczek.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div></div>
            <?php if (!empty($catalog)): ?>
            <div class="postbox"><div class="postbox-header"><h2>Katalog GetSimple CE</h2></div><div class="inside">
                <input type="search" id="sp-catalog-search" class="regular-text" placeholder="Szukaj wtyczki...">
                <div class="sp-catalog" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:12px">
                    <?php foreach ($catalog as $item): if (!is_array($item)) { continue; } ?>
                    <div class="postbox sp-catalog-item" style="margin:0;padding:12px" data-search="<?php echo sp_e(strtolower(($item['name'] ?? '') . ' ' . ($item['info'] ?? '') . ' ' . ($item['tags'] ?? ''))); ?>">
                        <strong><?php echo sp_e($item['name'] ?? ''); ?></strong>
                        <p style="font-size:12px"><?php echo sp_e($item['info'] ?? ''); ?></p>
                        <form method="post" action="<?php echo sp_e(sp_admin_url('plugin-repo')); ?>">
                            <?php sp_nonce_field('install_plugin'); ?>
                            <input type="hidden" name="sp_install_plugin" value="1">
                            <input type="hidden" name="url" value="<?php echo sp_e($item['url'] ?? ''); ?>">
                            <button type="submit" class="button">Zainstaluj</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div></div>
            <script>(function(){var i=document.getElementById("sp-catalog-search");if(!i)return;i.addEventListener("input",function(){var q=this.value.toLowerCase();document.querySelectorAll(".sp-catalog-item").forEach(function(el){el.style.display=el.dataset.search.indexOf(q)!==-1?"":"none";});});})();</script>
            <?php endif; ?>
        </div>
        <?php
    }
}
