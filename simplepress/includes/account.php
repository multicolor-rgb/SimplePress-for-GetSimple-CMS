<?php
/**
 * Per-user preferences: admin color scheme and editor.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('wp_simple_handle_account_actions')) {
    function wp_simple_handle_account_actions()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['wp_save_account'])) {
            return;
        }
        sp_require_post('save_account');
        if (!sp_is_authenticated()) {
            sp_die('Authentication required.', 403);
        }

        $scheme = (string) ($_POST['admin_scheme'] ?? '');
        if (!in_array($scheme, ['', 'classic', 'modern', 'dark'], true)) {
            $scheme = '';
        }
        $editor = (string) ($_POST['editor'] ?? '');
        if (!in_array($editor, ['', 'ckeditor', 'tinymce'], true)) {
            $editor = '';
        }
        $frontend_bar = (string) ($_POST['frontend_bar'] ?? '');
        if (!in_array($frontend_bar, ['', '0', '1'], true)) {
            $frontend_bar = '';
        }

        sp_save_user_pref('admin_scheme', $scheme);
        sp_save_user_pref('editor', $editor);
        sp_save_user_pref('frontend_bar', $frontend_bar);
        sp_log('account_prefs', sp_current_user());
        sp_redirect(sp_admin_url('account', 'saved=1'));
    }
}

if (!function_exists('wp_simple_account_view')) {
    function wp_simple_account_view()
    {
        sp_require_authenticated();
        $user = sp_current_user();
        $global_scheme = wp_simple_get_setting('admin_scheme', 'classic');
        if (!in_array($global_scheme, ['classic', 'modern', 'dark'], true)) {
            $global_scheme = 'classic';
        }
        $user_scheme = sp_user_pref('admin_scheme', '');
        $global_editor = wp_simple_get_setting('active_editor', 'ckeditor');
        $user_editor = sp_user_pref('editor', '');
        $global_bar = wp_simple_get_setting('frontend_bar', '1') === '1' ? '1' : '0';
        $user_bar = sp_user_pref('frontend_bar', '');
        $role = function_exists('wp_simple_get_user_role') ? wp_simple_get_user_role($user) : 'administrator';
        $schemes = [
            'classic' => 'SimplePress Classic',
            'modern' => 'SimplePress Modern',
            'dark' => 'SimplePress Dark',
        ];
        $editors = [
            'ckeditor' => 'CKEditor',
            'tinymce' => 'TinyMCE',
        ];
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('TAB_ACCOUNT', 'Moje konto')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p><?php echo sp_e(wp_i18n('ACCOUNT_SAVED', 'Ustawienia konta zostały zapisane.')); ?></p></div><?php endif; ?>

            <div class="postbox">
                <div class="postbox-header"><h2><?php echo sp_e(wp_i18n('ACCOUNT_PROFILE', 'Profil')); ?></h2></div>
                <div class="inside">
                    <p><strong><?php echo sp_e(wp_i18n('ACCOUNT_USER', 'Użytkownik')); ?>:</strong> <?php echo sp_e($user); ?></p>
                    <p><strong><?php echo sp_e(wp_i18n('ACCOUNT_ROLE', 'Rola')); ?>:</strong> <?php echo sp_e($role === 'editor' ? wp_i18n('ROLE_EDITOR', 'Redaktor') : wp_i18n('ROLE_ADMIN', 'Administrator')); ?></p>
                    <p><a class="button" href="settings.php#profile"><?php echo sp_e(wp_i18n('ACCOUNT_GS_PROFILE', 'Edytuj profil GetSimple')); ?></a></p>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header"><h2><?php echo sp_e(wp_i18n('ACCOUNT_PREFERENCES', 'Preferencje panelu')); ?></h2></div>
                <div class="inside">
                    <form method="post" action="<?php echo sp_e(sp_admin_url('account')); ?>">
                        <?php sp_nonce_field('save_account'); ?>
                        <input type="hidden" name="wp_save_account" value="1">
                        <p>
                            <label for="sp-account-scheme"><strong><?php echo sp_e(wp_i18n('ACCOUNT_SCHEME', 'Schemat kolorów')); ?></strong></label><br>
                            <select name="admin_scheme" id="sp-account-scheme">
                                <option value="" <?php echo $user_scheme === '' ? 'selected' : ''; ?>><?php echo sp_e(sprintf(wp_i18n('ACCOUNT_DEFAULT', 'Domyślny (globalny: %s)'), $schemes[$global_scheme] ?? $global_scheme)); ?></option>
                                <?php foreach ($schemes as $key => $label): ?>
                                <option value="<?php echo sp_e($key); ?>" <?php echo $user_scheme === $key ? 'selected' : ''; ?>><?php echo sp_e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="sp-account-editor"><strong><?php echo sp_e(wp_i18n('ACCOUNT_EDITOR', 'Edytor treści')); ?></strong></label><br>
                            <select name="editor" id="sp-account-editor">
                                <option value="" <?php echo $user_editor === '' ? 'selected' : ''; ?>><?php echo sp_e(sprintf(wp_i18n('ACCOUNT_DEFAULT', 'Domyślny (globalny: %s)'), $editors[$global_editor] ?? $global_editor)); ?></option>
                                <?php foreach ($editors as $key => $label): ?>
                                <option value="<?php echo sp_e($key); ?>" <?php echo $user_editor === $key ? 'selected' : ''; ?>><?php echo sp_e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label for="sp-account-frontend-bar"><strong><?php echo sp_e(wp_i18n('ACCOUNT_FRONTEND_BAR', 'Pasek na stronie publicznej')); ?></strong></label><br>
                            <select name="frontend_bar" id="sp-account-frontend-bar">
                                <option value="" <?php echo $user_bar === '' ? 'selected' : ''; ?>><?php echo sp_e(sprintf(wp_i18n('ACCOUNT_DEFAULT', 'Domyślny (globalny: %s)'), $global_bar === '1' ? wp_i18n('YES', 'tak') : wp_i18n('NO', 'nie'))); ?></option>
                                <option value="1" <?php echo $user_bar === '1' ? 'selected' : ''; ?>><?php echo sp_e(wp_i18n('SHOW', 'Pokaż')); ?></option>
                                <option value="0" <?php echo $user_bar === '0' ? 'selected' : ''; ?>><?php echo sp_e(wp_i18n('HIDE', 'Ukryj')); ?></option>
                            </select>
                        </p>
                        <p class="description"><?php echo sp_e(wp_i18n('ACCOUNT_HINT', 'Te ustawienia dotyczą tylko Twojego konta i nadpisują ustawienia globalne.')); ?></p>
                        <p><button type="submit" class="button button-primary"><?php echo sp_e(wp_i18n('ACCOUNT_SAVE', 'Zapisz preferencje')); ?></button></p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
