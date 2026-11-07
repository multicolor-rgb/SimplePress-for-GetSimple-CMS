<?php
/**
 * Help desk.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_helpdesk_nav')) {
    function sp_helpdesk_nav()
    {
        if (wp_simple_get_setting('helpdesk_enabled', '0') !== '1') {
            return;
        }
        $current = (($_GET['id'] ?? '') === 'simplepress' && ($_GET['sub'] ?? '') === 'helpdesk') ? 'current' : '';
        echo '<li class="wp-menu-helpdesk wp-custom-nav-item"><a href="load.php?id=simplepress&amp;sub=helpdesk" class="' . $current . '"><span class="dashicons dashicons-editor-help"></span><span class="wp-nav-label">Pomoc</span></a></li>';
    }
}
add_action('nav-tab', 'sp_helpdesk_nav');

if (!function_exists('wp_simple_helpdesk_view')) {
    function wp_simple_helpdesk_view()
    {
        sp_require_authenticated();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_save_helpdesk']) && sp_is_administrator()) {
            sp_require_post('save_helpdesk');
            $content = (string) ($_POST['content'] ?? '');
            if (function_exists('xss_clean')) {
                $content = xss_clean($content);
            }
            wp_simple_save_setting('helpdesk_enabled', !empty($_POST['enabled']) ? '1' : '0');
            wp_simple_save_setting('helpdesk_content', substr($content, 0, 200000));
            sp_redirect(sp_admin_url('helpdesk', 'saved=1'));
        }
        $enabled = wp_simple_get_setting('helpdesk_enabled', '0') === '1';
        $content = (string) wp_simple_get_setting('helpdesk_content', '');
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('TAB_HELP', 'Pomoc')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Treść pomocy została zapisana.</p></div><?php endif; ?>
            <?php if ($content !== ''): ?><div class="postbox"><div class="inside sp-help-content"><?php echo $content; ?></div></div><?php endif; ?>
            <?php if (sp_is_administrator()): ?>
            <form method="post" action="<?php echo sp_e(sp_admin_url('helpdesk')); ?>">
                <?php sp_nonce_field('save_helpdesk'); ?>
                <input type="hidden" name="sp_save_helpdesk" value="1">
                <p><label><input type="checkbox" name="enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>> Pokaż link „Pomoc” w nawigacji</label></p>
                <textarea name="content" rows="16" class="large-text code"><?php echo sp_e($content); ?></textarea>
                <p><button type="submit" class="button button-primary">Zapisz pomoc</button></p>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
