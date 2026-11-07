<?php
/**
 * SQLite storage overview.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('wp_simple_sqlite_view')) {
    function wp_simple_sqlite_view()
    {
        sp_require_admin();
        $db = wp_simple_get_db();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_vacuum'])) {
            sp_require_post('sqlite_vacuum');
            if ($db) {
                @$db->exec('VACUUM');
                sp_log('sqlite_vacuum', 'database optimized');
            }
            sp_redirect(sp_admin_url('sqlite', 'saved=1'));
        }
        $dbFile = GSDATAOTHERPATH . 'simplepress.db';
        $settings = [];
        $roles = [];
        if ($db) {
            $res = $db->query('SELECT key, value FROM settings ORDER BY key');
            if ($res) {
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    $settings[$row['key']] = $row['value'];
                }
            }
            $res = $db->query('SELECT username, role, display_name FROM user_meta ORDER BY username');
            if ($res) {
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    $roles[] = $row;
                }
            }
        }
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('TAB_SQLITE', 'Magazyn SQLite3')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Baza została zoptymalizowana.</p></div><?php endif; ?>
            <?php if (!$db): ?>
                <div class="notice notice-warning"><p>Rozszerzenie SQLite3 jest niedostępne. SimplePress używa awaryjnego magazynu JSON, więc podstawowe funkcje nadal działają.</p></div>
            <?php else: ?>
                <div class="postbox"><div class="inside">
                    <p><strong>Plik bazy:</strong> <code><?php echo sp_e($dbFile); ?></code></p>
                    <p><strong>Rozmiar:</strong> <?php echo sp_e(is_file($dbFile) ? (function_exists('fSize') ? fSize(filesize($dbFile)) : filesize($dbFile) . ' B') : '—'); ?></p>
                    <p><strong>Wersja SQLite:</strong> <?php echo sp_e(SQLite3::version()['versionString'] ?? '—'); ?></p>
                    <form method="post" action="<?php echo sp_e(sp_admin_url('sqlite')); ?>">
                        <?php sp_nonce_field('sqlite_vacuum'); ?>
                        <input type="hidden" name="sp_vacuum" value="1">
                        <button type="submit" class="button">Optymalizuj bazę (VACUUM)</button>
                    </form>
                </div></div>
                <h2>Ustawienia w bazie</h2>
                <table class="widefat striped" style="max-width:1000px"><thead><tr><th>Klucz</th><th>Wartość</th></tr></thead><tbody>
                <?php foreach ($settings as $key => $value): ?>
                    <tr><td><code><?php echo sp_e($key); ?></code></td><td><?php echo sp_e($value); ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($settings)): ?><tr><td colspan="2">Brak zapisanych ustawień.</td></tr><?php endif; ?>
                </tbody></table>
                <h2>Role użytkowników</h2>
                <table class="widefat striped" style="max-width:1000px"><thead><tr><th>Użytkownik</th><th>Rola</th><th>Nazwa</th></tr></thead><tbody>
                <?php foreach ($roles as $row): ?>
                    <tr><td><?php echo sp_e($row['username']); ?></td><td><?php echo sp_e($row['role']); ?></td><td><?php echo sp_e($row['display_name']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($roles)): ?><tr><td colspan="3">Brak wpisów.</td></tr><?php endif; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
        <?php
    }
}
