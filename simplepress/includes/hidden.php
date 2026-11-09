<?php
/**
 * Per-user hidden admin sections and role restrictions.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_hidden_defaults')) {
    function sp_hidden_defaults()
    {
        return [
            'hidepages' => false,
            'hidefiles' => false,
            'hidethemes' => false,
            'hidebackup' => false,
            'hideplugin' => false,
            'hidesupport' => false,
            'hidesettings' => false,
            'hidegssettings' => false,
            'hidei18n' => false,
        ];
    }
}

if (!function_exists('sp_hidden_file')) {
    function sp_hidden_file($username)
    {
        $id = sp_user_id($username);
        if ($id === false) {
            return false;
        }
        $dir = sp_storage_dir() . '/hidden';
        sp_private_dir($dir);
        return $dir . '/' . $id . '.json';
    }
}

if (!function_exists('sp_hidden_for')) {
    function sp_hidden_for($username)
    {
        $file = sp_hidden_file($username);
        if ($file === false) {
            return sp_hidden_defaults();
        }
        $data = sp_read_json($file, []);
        return array_merge(sp_hidden_defaults(), is_array($data) ? $data : []);
    }
}

if (!function_exists('sp_enforce_hidden_sections')) {
    function sp_enforce_hidden_sections()
    {
        if (is_frontend() || !sp_is_authenticated()) {
            return;
        }
        $user = sp_current_user();
        $hidden = sp_hidden_for($user);
        if (!array_filter($hidden)) {
            return;
        }
        $file = function_exists('get_filename_id') ? get_filename_id() : '';
        $id = $_GET['id'] ?? '';
        $sub = $_GET['sub'] ?? '';
        $deny = false;
        if ($hidden['hidepages'] && in_array($file, ['pages', 'edit', 'menu-manager'], true)) { $deny = true; }
        if ($hidden['hidefiles'] && in_array($file, ['upload', 'image', 'filebrowser'], true)) { $deny = true; }
        if ($hidden['hidethemes'] && in_array($file, ['theme', 'theme-edit', 'components', 'sitemap', 'navigation'], true)) { $deny = true; }
        if ($hidden['hidebackup'] && in_array($file, ['backups', 'backup-edit', 'archive'], true)) { $deny = true; }
        if ($hidden['hideplugin'] && $file === 'plugins') { $deny = true; }
        if ($hidden['hidei18n'] && $id === 'i18n_gallery') { $deny = true; }
        if ($hidden['hidesupport'] && in_array($file, ['support', 'log', 'log-error', 'health-check', 'phpinfo', 'salt-shaker'], true)) { $deny = true; }
        if ($hidden['hidesettings'] && $id === 'simplepress' && $sub !== 'helpdesk') { $deny = true; }
        if ($hidden['hidegssettings'] && $file === 'settings') { $deny = true; }
        if ($deny) {
            sp_log('hidden_block', $file . ' ' . $id . ' ' . $sub);
            sp_die(wp_i18n('HIDDEN_DISABLED', 'This section has been disabled by the administrator.'), 403);
        }
    }
}

if (!function_exists('sp_restrict_editor_role')) {
    function sp_restrict_editor_role()
    {
        if (is_frontend() || !sp_is_authenticated()) {
            return;
        }
        if (($_GET['id'] ?? '') !== 'simplepress') {
            return;
        }
        if (sp_is_administrator()) {
            return;
        }
        $sub = $_GET['sub'] ?? 'dashboard';
        if (!in_array($sub, ['dashboard', 'helpdesk'], true)) {
            sp_die(wp_i18n('HIDDEN_ADMIN_REQUIRED', 'This section requires administrator role.'), 403);
        }
    }
}

if (!function_exists('wp_simple_hidden_view')) {
    function wp_simple_hidden_view()
    {
        sp_require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sp_save_hidden'])) {
            sp_require_post('save_hidden');
            $user = sp_user_id($_POST['user'] ?? '');
            $file = sp_hidden_file($user);
            if ($user === false || $file === false || !is_file(GSUSERSPATH . $user . '.xml')) {
                sp_redirect(sp_admin_url('hidden', 'error=invalid'));
            }
            $values = [];
            foreach (sp_hidden_defaults() as $key => $unused) {
                $values[$key] = !empty($_POST[$key]);
            }
            sp_write_json($file, $values);
            sp_log('hidden_save', $user);
            sp_redirect(sp_admin_url('hidden', 'saved=1'));
        }
        $users = glob(GSUSERSPATH . '*.xml');
        ?>
        <div class="wrap">
            <?php wp_simple_render_subtabs(); ?>
            <h1><?php echo sp_e(wp_i18n('TAB_HIDDEN', 'Hidden sections')); ?></h1>
            <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p><?php echo sp_e(wp_i18n('HIDDEN_SAVED', 'Settings saved.')); ?></p></div><?php endif; ?>
            <p class="description"><?php echo sp_e(wp_i18n('HIDDEN_HINT', 'Hiding sections works server-side. Users cannot access a hidden page via a direct URL.')); ?></p>
            <?php foreach ($users as $file): $u = basename($file, '.xml'); $hidden = sp_hidden_for($u); ?>
            <div class="postbox" style="max-width:900px;margin-bottom:18px">
                <div class="postbox-header"><h2><?php echo sp_e($u); ?></h2></div>
                <div class="inside">
                    <form method="post" action="<?php echo sp_e(sp_admin_url('hidden')); ?>">
                        <?php sp_nonce_field('save_hidden'); ?>
                        <input type="hidden" name="sp_save_hidden" value="1">
                        <input type="hidden" name="user" value="<?php echo sp_e($u); ?>">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:4px 20px">
                            <?php
                            $labels = [
                                'hidepages' => wp_i18n('HIDDEN_PAGES', 'Pages'), 'hidefiles' => wp_i18n('HIDDEN_FILES', 'Files'), 'hidethemes' => wp_i18n('HIDDEN_THEMES', 'Themes'),
                                'hidebackup' => wp_i18n('HIDDEN_BACKUPS', 'Backups'), 'hideplugin' => wp_i18n('HIDDEN_PLUGINS', 'Plugins'), 'hidesupport' => wp_i18n('HIDDEN_HELP', 'Help'),
                                'hidesettings' => 'SimplePress', 'hidegssettings' => wp_i18n('HIDDEN_GS_SETTINGS', 'GetSimple settings'), 'hidei18n' => 'i18n Gallery',
                            ];
                            foreach ($labels as $key => $label):
                            ?>
                            <label><input type="checkbox" name="<?php echo sp_e($key); ?>" value="1" <?php echo !empty($hidden[$key]) ? 'checked' : ''; ?>> <?php echo sp_e($label); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <p><button type="submit" class="button button-primary"><?php echo sp_e(wp_i18n('SAVE', 'Save changes')); ?></button></p>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
