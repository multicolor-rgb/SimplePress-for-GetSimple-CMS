<?php
/**
 * Backup creator and authenticated download endpoint.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_backup_dir')) {
    function sp_backup_dir()
    {
        $dir = sp_storage_dir() . '/backups';
        sp_private_dir($dir);
        return $dir;
    }
}

if (!function_exists('sp_backup_bases')) {
    function sp_backup_bases()
    {
        return [
            'plugins' => ['label' => 'Plugins', 'path' => GSPLUGINPATH],
            'admin' => ['label' => 'Admin', 'path' => GSADMINPATH],
            'data' => ['label' => 'Data', 'path' => GSDATAPATH],
            'themes' => ['label' => 'Themes', 'path' => GSTHEMESPATH],
            'uploads' => ['label' => 'Uploads', 'path' => GSDATAUPLOADPATH],
        ];
    }
}

if (!function_exists('wp_simple_create_backup')) {
    function wp_simple_create_backup($key)
    {
        $bases = sp_backup_bases();
        if (!isset($bases[$key]) || !class_exists('ZipArchive')) {
            return [false, 'Nieprawidłowy folder lub brak rozszerzenia ZIP.'];
        }
        $base = realpath($bases[$key]['path']);
        if ($base === false || !is_dir($base)) {
            return [false, 'Folder nie istnieje.'];
        }
        $name = 'simplepress-' . $key . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
        $target = sp_backup_dir() . '/' . $name;
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [false, 'Nie można otworzyć archiwum ZIP.'];
        }
        $skip = rtrim(sp_backup_dir(), '/\\');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        $count = 0;
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                continue;
            }
            $path = str_replace('\\', '/', $item->getPathname());
            if (strpos($path, str_replace('\\', '/', $skip)) === 0) {
                continue;
            }
            if (strpos($path, str_replace('\\', '/', $base) . '/') !== 0) {
                continue;
            }
            $relative = substr($path, strlen(str_replace('\\', '/', $base)) + 1);
            if ($relative === '' || strpos($relative, '..') !== false) {
                continue;
            }
            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($path, $relative);
                $count++;
            }
        }
        $zip->close();
        @chmod($target, 0640);
        sp_log('backup_create', $name . ' (' . $count . ' files)');
        return [true, $name];
    }
}

if (!function_exists('sp_backup_list')) {
    function sp_backup_list()
    {
        $items = [];
        foreach (glob(sp_backup_dir() . '/simplepress-*.zip') as $file) {
            if (!preg_match('/^simplepress-[a-z]+-\d{8}-\d{6}-[a-f0-9]+\.zip$/', basename($file))) {
                continue;
            }
            $items[] = ['name' => basename($file), 'size' => filesize($file), 'time' => filemtime($file)];
        }
        usort($items, function ($a, $b) { return $b['time'] <=> $a['time']; });
        return $items;
    }
}

if (!function_exists('sp_backup_valid')) {
    function sp_backup_valid($name)
    {
        $name = basename((string) $name);
        if (!preg_match('/^simplepress-[a-z]+-\d{8}-\d{6}-[a-f0-9]+\.zip$/', $name)) {
            return false;
        }
        $file = sp_backup_dir() . '/' . $name;
        return is_file($file) && sp_path_within($file, sp_backup_dir(), true);
    }
}

if (!function_exists('wp_simple_handle_backup_download')) {
    function wp_simple_handle_backup_download()
    {
        if (($_GET['id'] ?? '') !== 'simplepress' || ($_GET['sub'] ?? '') !== 'backups' || !isset($_GET['download'])) {
            return;
        }
        sp_require_admin();
        sp_require_get_nonce('backup_download');
        $name = basename((string) $_GET['download']);
        if (!sp_backup_valid($name)) {
            sp_die('Nie znaleziono kopii zapasowej.', 404);
        }
        $file = sp_backup_dir() . '/' . $name;
        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . filesize($file));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }
        readfile($file);
        exit;
    }
}
add_action('admin-pre-header', 'wp_simple_handle_backup_download');

if (!function_exists('wp_simple_backups_view')) {
    function wp_simple_backups_view()
    {
        sp_require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_create_backup'])) {
            sp_require_post('create_backup');
            $result = wp_simple_create_backup((string) ($_POST['folder'] ?? ''));
            sp_redirect(sp_admin_url('backups', $result[0] ? 'saved=1' : 'error=create'));
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_delete_backup'])) {
            sp_require_post('delete_backup');
            $name = (string) ($_POST['name'] ?? '');
            if (sp_backup_valid($name)) {
                @unlink(sp_backup_dir() . '/' . basename($name));
                sp_log('backup_delete', $name);
                sp_redirect(sp_admin_url('backups', 'deleted=1'));
            }
            sp_redirect(sp_admin_url('backups', 'error=not_found'));
        }
        $items = sp_backup_list();
        $nonce = sp_nonce('backup_download');
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('TAB_BACKUPS', 'Kopie zapasowe')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Kopia została utworzona.</p></div><?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?><div class="notice notice-success"><p>Kopia została usunięta.</p></div><?php endif; ?>
            <?php if (isset($_GET['error'])): ?><div class="notice notice-error"><p>Operacja nie powiodła się.</p></div><?php endif; ?>
            <div class="postbox" style="max-width:760px">
                <div class="inside">
                    <form method="post" action="<?php echo sp_e(sp_admin_url('backups')); ?>" class="sp-inline">
                        <?php sp_nonce_field('create_backup'); ?>
                        <select name="folder">
                            <?php foreach (sp_backup_bases() as $key => $base): ?>
                            <option value="<?php echo sp_e($key); ?>"><?php echo sp_e($base['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="sp_create_backup" class="button button-primary">Utwórz kopię</button>
                    </form>
                </div>
            </div>
            <table class="widefat striped" style="max-width:1000px">
                <thead><tr><th>Nazwa</th><th>Data</th><th>Rozmiar</th><th>Akcje</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4">Brak kopii zapasowych.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><code><?php echo sp_e($item['name']); ?></code></td>
                        <td><?php echo sp_e(date('Y-m-d H:i', $item['time'])); ?></td>
                        <td><?php echo sp_e(function_exists('fSize') ? fSize($item['size']) : round($item['size'] / 1024, 1) . ' KB'); ?></td>
                        <td>
                            <a class="button" href="<?php echo sp_e(sp_admin_url('backups', 'download=' . rawurlencode($item['name']) . '&sp_nonce=' . rawurlencode($nonce))); ?>">Pobierz</a>
                            <form method="post" action="<?php echo sp_e(sp_admin_url('backups')); ?>" style="display:inline" onsubmit="return confirm('Usunąć kopię?');">
                                <?php sp_nonce_field('delete_backup'); ?>
                                <input type="hidden" name="sp_delete_backup" value="1">
                                <input type="hidden" name="name" value="<?php echo sp_e($item['name']); ?>">
                                <button type="submit" class="button button-link-delete">Usuń</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
