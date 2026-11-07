<?php
/**
 * gsconfig.php editor and developer switches.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('wp_simple_tools_view')) {
    function wp_simple_tools_view()
    {
        sp_require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_save_gsconfig'])) {
            sp_require_post('save_gsconfig');
            $content = (string) ($_POST['content'] ?? '');
            if (strlen($content) > 500000 || trim($content) === '') {
                sp_redirect(sp_admin_url('tools', 'error=invalid'));
            }
            $file = GSROOTPATH . 'gsconfig.php';
            $backupDir = sp_storage_dir() . '/backups/gsconfig';
            sp_private_dir($backupDir);
            if (is_file($file)) {
                @copy($file, $backupDir . '/gsconfig-' . date('Ymd-His') . '.php');
            }
            if (sp_write_file($file, $content, 0644)) {
                sp_log('gsconfig_save', 'gsconfig.php');
                sp_redirect(sp_admin_url('tools', 'saved=config'));
            }
            sp_redirect(sp_admin_url('tools', 'error=write'));
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_save_developer'])) {
            sp_require_post('save_developer');
            wp_simple_save_setting('developer_code', !empty($_POST['developer_code']) ? '1' : '0');
            sp_redirect(sp_admin_url('tools', 'saved=developer'));
        }
        $config = is_file(GSROOTPATH . 'gsconfig.php') ? (string) @file_get_contents(GSROOTPATH . 'gsconfig.php') : '';
        $codeAllowed = wp_simple_get_setting('developer_code', '1') === '1';
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('TAB_TOOLS', 'Narzędzia')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Ustawienia zostały zapisane.</p></div><?php endif; ?>
            <?php if (isset($_GET['error'])): ?><div class="notice notice-error"><p>Operacja nie powiodła się.</p></div><?php endif; ?>
            <div class="postbox"><div class="postbox-header"><h2>Bezpieczeństwo funkcji deweloperskich</h2></div><div class="inside">
                <form method="post" action="<?php echo sp_e(sp_admin_url('tools')); ?>">
                    <?php sp_nonce_field('save_developer'); ?>
                    <input type="hidden" name="sp_save_developer" value="1">
                    <label><input type="checkbox" name="developer_code" value="1" <?php echo $codeAllowed ? 'checked' : ''; ?>> Zezwalaj na własny kod header/footer oraz edycję gsconfig.php</label>
                    <p><button type="submit" class="button button-primary">Zapisz</button></p>
                </form>
            </div></div>
            <?php if ($codeAllowed): ?>
            <div class="postbox"><div class="postbox-header"><h2>gsconfig.php</h2></div><div class="inside">
                <p class="description">Przed każdym zapisem tworzona jest kopia w chronionym katalogu.</p>
                <form method="post" action="<?php echo sp_e(sp_admin_url('tools')); ?>">
                    <?php sp_nonce_field('save_gsconfig'); ?>
                    <input type="hidden" name="sp_save_gsconfig" value="1">
                    <textarea name="content" rows="22" class="large-text code" style="font-family:Consolas,Monaco,monospace;font-size:12px"><?php echo sp_e($config); ?></textarea>
                    <p><button type="submit" class="button button-primary">Zapisz gsconfig.php</button></p>
                </form>
            </div></div>
            <?php endif; ?>
        </div>
        <?php
    }
}
