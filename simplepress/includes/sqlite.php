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
            <h1><?php echo sp_e(wp_i18n('SQLITE_TITLE', 'SQLite3 storage')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p><?php echo sp_e(wp_i18n('SQLITE_OPTIMIZED', 'Database optimized.')); ?></p></div><?php endif; ?>
            <?php if (!$db): ?>
                <div class="notice notice-warning"><p><?php echo sp_e(wp_i18n('SQLITE_UNAVAILABLE', 'SQLite3 extension is unavailable. SimplePress uses a JSON fallback store, so basic features still work.')); ?></p></div>
            <?php else: ?>
                <div class="postbox"><div class="inside">
                    <p><strong><?php echo sp_e(wp_i18n('SQLITE_DB_FILE', 'Database file:')); ?></strong> <code><?php echo sp_e($dbFile); ?></code></p>
                    <p><strong><?php echo sp_e(wp_i18n('SIZE', 'Size')); ?>:</strong> <?php echo sp_e(is_file($dbFile) ? (function_exists('fSize') ? fSize(filesize($dbFile)) : filesize($dbFile) . ' B') : '—'); ?></p>
                    <p><strong><?php echo sp_e(wp_i18n('SQLITE_VERSION', 'SQLite version:')); ?></strong> <?php echo sp_e(SQLite3::version()['versionString'] ?? '—'); ?></p>
                    <form method="post" action="<?php echo sp_e(sp_admin_url('sqlite')); ?>">
                        <?php sp_nonce_field('sqlite_vacuum'); ?>
                        <input type="hidden" name="sp_vacuum" value="1">
                        <button type="submit" class="button"><?php echo sp_e(wp_i18n('SQLITE_OPTIMIZE', 'Optimize database (VACUUM)')); ?></button>
                    </form>
                </div></div>
                <h2><?php echo sp_e(wp_i18n('SQLITE_SETTINGS', 'Settings in the database')); ?></h2>
                <table class="widefat striped" style="max-width:1000px"><thead><tr><th><?php echo sp_e(wp_i18n('THEME_KEY', 'Key')); ?></th><th><?php echo sp_e(wp_i18n('SQLITE_VALUE', 'Value')); ?></th></tr></thead><tbody>
                <?php foreach ($settings as $key => $value): ?>
                    <tr><td><code><?php echo sp_e($key); ?></code></td><td><?php echo sp_e($value); ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($settings)): ?><tr><td colspan="2"><?php echo sp_e(wp_i18n('SQLITE_NO_SETTINGS', 'No saved settings.')); ?></td></tr><?php endif; ?>
                </tbody></table>
                <h2><?php echo sp_e(wp_i18n('SQLITE_USER_ROLES', 'User roles')); ?></h2>
                <table class="widefat striped" style="max-width:1000px"><thead><tr><th><?php echo sp_e(wp_i18n('SQLITE_USER', 'User')); ?></th><th><?php echo sp_e(wp_i18n('ROLE', 'Role')); ?></th><th><?php echo sp_e(wp_i18n('NAME', 'Name')); ?></th></tr></thead><tbody>
                <?php foreach ($roles as $row): ?>
                    <tr><td><?php echo sp_e($row['username']); ?></td><td><?php echo sp_e($row['role']); ?></td><td><?php echo sp_e($row['display_name']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($roles)): ?><tr><td colspan="3"><?php echo sp_e(wp_i18n('SQLITE_NO_ENTRIES', 'No entries.')); ?></td></tr><?php endif; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
        <?php
    }
}
