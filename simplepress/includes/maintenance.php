<?php
/**
 * Maintenance mode.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_maybe_maintenance')) {
    function sp_maybe_maintenance()
    {
        if (!is_frontend()) {
            return;
        }
        if (wp_simple_get_setting('maintenance_mode', '0') !== '1') {
            return;
        }
        if (sp_is_authenticated()) {
            return;
        }
        $content = (string) wp_simple_get_setting('maintenance_content', '');
        if (function_exists('xss_clean')) {
            $content = xss_clean($content);
        }
        if (trim($content) === '') {
            $content = '<h1>' . sp_e(wp_i18n('MAINTENANCE_TITLE', 'Maintenance')) . '</h1><p>' . sp_e(wp_i18n('MAINTENANCE_TEXT', 'We are performing scheduled maintenance. Please check back soon.')) . '</p>';
        }
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><html lang="' . sp_e(str_replace('_', '-', sp_lang_code())) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . sp_e(wp_i18n('MAINTENANCE_TITLE', 'Maintenance')) . '</title><style>html,body{height:100%;margin:0}body{display:flex;align-items:center;justify-content:center;background:#f0f0f1;color:#3c434a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:16px;line-height:1.6}.sp-maintenance{max-width:680px;margin:24px;padding:40px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.04)}.sp-maintenance h1{font-size:28px;font-weight:400;margin-top:0;color:#1d2327}.sp-maintenance a{color:#2271b1}</style></head><body><div class="sp-maintenance">' . $content . '</div></body></html>';
        exit;
    }
}

if (!function_exists('wp_simple_maintenance_view')) {
    function wp_simple_maintenance_view()
    {
        sp_require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_save_maintenance'])) {
            sp_require_post('save_maintenance');
            $content = (string) ($_POST['maintenance_content'] ?? '');
            if (function_exists('xss_clean')) {
                $content = xss_clean($content);
            }
            wp_simple_save_setting('maintenance_mode', !empty($_POST['maintenance_mode']) ? '1' : '0');
            wp_simple_save_setting('maintenance_content', substr($content, 0, 200000));
            sp_log('maintenance_save', !empty($_POST['maintenance_mode']) ? 'enabled' : 'disabled');
            sp_redirect(sp_admin_url('maintenance', 'saved=1'));
        }
        $enabled = wp_simple_get_setting('maintenance_mode', '0') === '1';
        $content = (string) wp_simple_get_setting('maintenance_content', '');
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('MAINTENANCE_TITLE', 'Maintenance')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p><?php echo sp_e(wp_i18n('SAVED', 'Changes saved.')); ?></p></div><?php endif; ?>
            <form method="post" action="<?php echo sp_e(sp_admin_url('maintenance')); ?>">
                <?php sp_nonce_field('save_maintenance'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo sp_e(wp_i18n('MAINTENANCE_ENABLE', 'Enable maintenance mode')); ?></th>
                        <td><label><input type="checkbox" name="maintenance_mode" value="1" <?php echo $enabled ? 'checked' : ''; ?>> <?php echo sp_e(wp_i18n('MAINTENANCE_ENABLE_HINT', 'Visitors will see a 503 page; administrators keep access.')); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="maintenance_content"><?php echo sp_e(wp_i18n('MAINTENANCE_CONTENT', 'Page content')); ?></label></th>
                        <td><textarea id="maintenance_content" name="maintenance_content" rows="12" class="large-text code"><?php echo sp_e($content); ?></textarea></td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" name="sp_save_maintenance" class="button button-primary"><?php echo sp_e(wp_i18n('SAVE', 'Save changes')); ?></button></p>
            </form>
        </div>
        <?php
    }
}
