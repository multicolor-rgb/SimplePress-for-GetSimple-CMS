<?php
if (!defined('IN_GS')) { die('You cannot load this file directly!'); }

# ==============================================================================
# OBSŁUGA BAZY SQLITE3 DLA RÓL UŻYTKOWNIKÓW
# ==============================================================================
function wp_simple_init_users_table() {
    $db = wp_simple_get_db();
    if ($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS user_meta (
            username TEXT PRIMARY KEY,
            role TEXT DEFAULT 'administrator',
            display_name TEXT DEFAULT ''
        )");
    }
}

# ==============================================================================
# LICZNIK STRON AUTORA
# ==============================================================================
function wp_simple_count_author_pages($username) {
    $count = 0;
    $pages_xml = GSDATAOTHERPATH . 'pages.xml';
    if (file_exists($pages_xml)) {
        $xml = @getXML($pages_xml);
        if ($xml && isset($xml->item)) {
            foreach ($xml->item as $p) {
                if ((string)$p->author === $username) {
                    $count++;
                }
            }
        }
    }
    return $count;
}

# ==============================================================================
# PRZETWARZANIE AKCJI POST / GET
# ==============================================================================
function wp_simple_handle_users_actions() {
    wp_simple_init_users_table();
    if (!sp_is_administrator()) {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp_delete_user'])) {
        sp_require_post('delete_user');
        $target = sp_user_id($_POST['user'] ?? '');
        if ($target === false) {
            sp_redirect(sp_admin_url('users', 'error=not_found'));
        }
        if (strcasecmp($target, sp_user_id(sp_current_user())) === 0) {
            sp_redirect(sp_admin_url('users', 'error=self_delete'));
        }
        $users = glob(GSUSERSPATH . '*.xml');
        if (count($users) <= 1) {
            sp_redirect(sp_admin_url('users', 'error=last_user'));
        }
        $targetRole = wp_simple_get_user_role($target);
        if ($targetRole === 'administrator') {
            $admins = 0;
            foreach ($users as $uFile) {
                if (wp_simple_get_user_role(basename($uFile, '.xml')) === 'administrator') { $admins++; }
            }
            if ($admins <= 1) {
                sp_redirect(sp_admin_url('users', 'error=last_user'));
            }
        }
        $user_file = GSUSERSPATH . $target . '.xml';
        if (sp_path_within($user_file, GSUSERSPATH, true) && is_file($user_file)) {
            @unlink($user_file);
            sp_delete_user_meta($target);
            $hiddenFile = sp_storage_dir() . '/hidden/' . $target . '.json';
            if (is_file($hiddenFile)) { @unlink($hiddenFile); }
            sp_log('user_delete', $target);
            sp_redirect(sp_admin_url('users', 'deleted=1'));
        }
        sp_redirect(sp_admin_url('users', 'error=not_found'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp_save_user'])) {
        sp_require_post('save_user');
        $mode = (($_POST['user_mode'] ?? 'add') === 'edit') ? 'edit' : 'add';
        $username = sp_user_id($_POST['user_login'] ?? '');
        $email = trim((string) ($_POST['user_email'] ?? ''));
        $password = (string) ($_POST['user_pass'] ?? '');
        $role = in_array(($_POST['user_role'] ?? 'administrator'), ['administrator', 'editor'], true) ? $_POST['user_role'] : 'administrator';
        $bio = trim(strip_tags((string) ($_POST['user_bio'] ?? '')));
        $display_name = trim(strip_tags((string) ($_POST['user_display_name'] ?? '')));
        $lang = preg_replace('/[^A-Za-z_\-]/', '', (string) ($_POST['user_lang'] ?? ($GLOBALS['LANG'] ?? 'en_US')));

        if ($username === false || $email === '') {
            sp_redirect(sp_admin_url('users', 'error=empty_fields'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sp_redirect(sp_admin_url('users', 'error=email_invalid'));
        }
        if (!is_file(GSLANGPATH . $lang . '.php')) {
            $lang = 'en_US';
        }
        $user_file = GSUSERSPATH . $username . '.xml';

        if ($mode === 'add') {
            if (is_file($user_file)) {
                sp_redirect(sp_admin_url('users', 'error=user_exists'));
            }
            if (strlen($password) < 8) {
                sp_redirect(sp_admin_url('users', 'error=empty_pass'));
            }
            $xml = new SimpleXMLExtended('<?xml version="1.0" encoding="UTF-8"?><item></item>');
            $xml->addChild('USR', $username);
            $xml->addChild('NAME', $display_name);
            $xml->addChild('PWD', passhash($password));
            $xml->addChild('EMAIL', $email);
            $xml->addChild('HTMLEDITOR', '1');
            $xml->addChild('TIMEZONE', 'Europe/Warsaw');
            $xml->addChild('LANG', $lang);
            $xml->addChild('ROLE', $role === 'editor' ? 'EDITOR' : 'ADMIN');
            $xml->addChild('BIO', $bio);
            if (XMLsave($xml, $user_file)) {
                @chmod($user_file, 0640);
                wp_simple_set_user_meta($username, $role, $display_name);
                sp_log('user_create', $username);
                sp_redirect(sp_admin_url('users', 'added=1'));
            }
            sp_redirect(sp_admin_url('users', 'error=write'));
        }

        if (!is_file($user_file)) {
            sp_redirect(sp_admin_url('users', 'error=not_found'));
        }
        $xml = getXML($user_file);
        $xml->EMAIL = $email;
        $xml->LANG = $lang;
        if ($display_name !== '') {
            $xml->NAME = $display_name;
        }
        if ($password !== '') {
            if (strlen($password) < 8) {
                sp_redirect(sp_admin_url('users', 'error=empty_pass'));
            }
            $xml->PWD = passhash($password);
        }
        if (isset($xml->BIO)) {
            $xml->BIO = null;
        }
        $xml->addChild('BIO', $bio);
        if (isset($xml->ROLE)) {
            $xml->ROLE = $role === 'editor' ? 'EDITOR' : 'ADMIN';
        } else {
            $xml->addChild('ROLE', $role === 'editor' ? 'EDITOR' : 'ADMIN');
        }
        if (XMLsave($xml, $user_file)) {
            @chmod($user_file, 0640);
            wp_simple_set_user_meta($username, $role, $display_name);
            sp_log('user_update', $username);
            sp_redirect(sp_admin_url('users', 'updated=1'));
        }
        sp_redirect(sp_admin_url('users', 'error=write'));
    }
}
add_action('load-common', 'wp_simple_handle_users_actions');

# ==============================================================================
# WIDOK GŁÓWNY ZARZĄDZANIA UŻYTKOWNIKAMI
# ==============================================================================
function wp_simple_users_view() {
    sp_require_admin();
    wp_simple_handle_users_actions();

    $action = $_GET['action'] ?? 'list';
    $current_user = sp_current_user();

    ?>
    <style>
        .wp-btn-danger {
            background-color: var(--wp-danger-strong) !important;
            border-color: var(--wp-danger-strong) !important;
            color: var(--wp-on-accent) !important;
            text-decoration: none !important;
            display: inline-block;
            transition: background 0.15s ease-in-out, border-color 0.15s ease-in-out;
        }
        .wp-btn-danger:hover {
            background-color: var(--wp-danger-strong) !important;
            border-color: var(--wp-danger-strong) !important;
            color: var(--wp-on-accent) !important;
        }
        .wp-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--wp-blue);
            color: var(--wp-on-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .user-table-row:hover {
            background-color: var(--wp-surface-alt);
        }
    </style>

    <div id="wpbody-content">
        <div class="wrap">
            <?php if (function_exists('wp_simple_render_subtabs')) { wp_simple_render_subtabs(); } ?>

            <?php if (isset($_GET['added'])): ?>
                <div class="updated"><p>Nowy użytkownik został pomyślnie utworzony.</p></div>
            <?php elseif (isset($_GET['updated'])): ?>
                <div class="updated"><p>Dane użytkownika zostały zaktualizowane.</p></div>
            <?php elseif (isset($_GET['deleted'])): ?>
                <div class="updated"><p>Użytkownik został pomyślnie usunięty z systemu.</p></div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="error"><p>
                    <?php 
                        switch ($_GET['error']) {
                            case 'self_delete': echo 'Nie możesz usunąć konta, na którym jesteś aktualnie zalogowany!'; break;
                            case 'user_exists': echo 'Użytkownik o takim loginie już istnieje!'; break;
                            case 'empty_fields': echo 'Login oraz adres e-mail są wymagane.'; break;
                            case 'empty_pass': echo 'Hasło musi mieć co najmniej 8 znaków.'; break;
                            case 'email_invalid': echo 'Podaj poprawny adres e-mail.'; break;
                            case 'last_user': echo 'Nie można usunąć ostatniego użytkownika.'; break;
                            default: echo 'Wystąpił błąd podczas operacji.'; break;
                        }
                    ?>
                </p></div>
            <?php endif; ?>

            <?php if ($action === 'new' || $action === 'edit'): 
                $is_edit = ($action === 'edit');
                $edit_user = $is_edit ? clean_url(basename($_GET['user'] ?? '')) : '';
                $user_data = ['login' => '', 'email' => '', 'role' => 'administrator', 'bio' => '', 'name' => '', 'lang' => 'en_US'];

                if ($is_edit && file_exists(GSUSERSPATH . $edit_user . '.xml')) {
                    $xml = getXML(GSUSERSPATH . $edit_user . '.xml');
                    $user_data['login'] = (string)$xml->USR;
                    $user_data['email'] = (string)$xml->EMAIL;
                    $user_data['bio'] = (string)($xml->BIO ?? '');
                    $user_data['name'] = (string)($xml->NAME ?? '');
                    $user_data['lang'] = (string)($xml->LANG ?? 'en_US');
                    $user_data['role'] = wp_simple_get_user_role($edit_user);
                }
            ?>
                <!-- FORMULARZ DODAWANIA / EDYCJI -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                    <h2 style="margin:0 !important;"><?php echo $is_edit ? 'Edytuj profil: ' . htmlspecialchars($user_data['login']) : 'Dodaj nowego użytkownika'; ?></h2>
                    <a href="load.php?id=simplepress&sub=users" class="button">Powrót do listy</a>
                </div>

                <form method="post" action="load.php?id=simplepress&amp;sub=users">
                    <?php sp_nonce_field('save_user'); ?>
                    <input type="hidden" name="user_mode" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
                    
                    <table class="form-table" style="max-width: 850px; background: var(--wp-card-bg); border: 1px solid var(--wp-border); padding: 20px; border-radius: 3px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
                        <tbody>
                            <tr>
                                <th scope="row" style="width: 220px; padding: 15px 10px; font-weight: 600;">Nazwa użytkownika (login)</th>
                                <td style="padding: 15px 10px;">
                                    <input type="text" name="user_login" value="<?php echo htmlspecialchars($user_data['login']); ?>" <?php echo $is_edit ? 'readonly style="background:var(--wp-content-bg); cursor:not-allowed;"' : 'required'; ?> class="regular-text" style="width: 100%; max-width: 350px; padding: 6px 10px;">
                                    <?php if ($is_edit): ?><p class="description" style="font-size:12px; color:var(--wp-text-muted); margin-top:4px;">Loginu w GetSimple CMS nie można edytować.</p><?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding: 15px 10px; font-weight: 600;">Adres e-mail</th>
                                <td style="padding: 15px 10px;">
                                    <input type="email" name="user_email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required class="regular-text" style="width: 100%; max-width: 350px; padding: 6px 10px;">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding: 15px 10px; font-weight: 600;">Nazwa wyświetlana</th>
                                <td style="padding: 15px 10px;"><input type="text" name="user_display_name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" style="width:100%;max-width:350px;padding:6px 10px;"></td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding: 15px 10px; font-weight: 600;">Język</th>
                                <td style="padding: 15px 10px;"><select name="user_lang"><?php foreach (glob(GSLANGPATH . '*.php') as $lf): $lc = basename($lf, '.php'); ?><option value="<?php echo sp_e($lc); ?>" <?php echo (($user_data['lang'] ?? 'en_US') === $lc ? 'selected' : ''); ?>><?php echo sp_e($lc); ?></option><?php endforeach; ?></select></td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding: 15px 10px; font-weight: 600;">Hasło</th>
                                <td style="padding: 15px 10px;">
                                    <div style="display: flex; gap: 8px; max-width: 450px;">
                                        <input type="password" name="user_pass" id="user_pass" autocomplete="new-password" placeholder="<?php echo $is_edit ? 'Pozostaw puste, aby nie zmieniać' : 'Wpisz silne hasło'; ?>" <?php echo $is_edit ? '' : 'required'; ?> class="regular-text" style="flex:1; padding: 6px 10px;">
                                        <button type="button" class="button" id="btn-gen-pass">Generuj hasło</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding: 15px 10px; font-weight: 600;">Rola</th>
                                <td style="padding: 15px 10px;">
                                    <select name="user_role" style="padding: 6px 10px; border-radius: 4px;">
                                        <option value="administrator" <?php echo ($user_data['role'] === 'administrator') ? 'selected' : ''; ?>>Administrator (Pełny dostęp)</option>
                                        <option value="editor" <?php echo ($user_data['role'] === 'editor') ? 'selected' : ''; ?>>Redaktor (Zarządzanie stronami)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding: 15px 10px; font-weight: 600;">Biogram (Bio)</th>
                                <td style="padding: 15px 10px;">
                                    <textarea name="user_bio" rows="4" style="width: 100%; max-width: 450px; padding: 8px; border-radius: 4px; border: 1px solid var(--wp-input-border);"><?php echo htmlspecialchars($user_data['bio']); ?></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit" style="margin-top: 20px;">
                        <input type="submit" name="wp_save_user" class="button button-primary" value="<?php echo $is_edit ? 'Zaktualizuj profil' : 'Utwórz użytkownika'; ?>">
                    </p>
                </form>

                <script>
                document.getElementById('btn-gen-pass').addEventListener('click', function() {
                    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
                    let pass = '';
                    for (let i = 0; i < 16; i++) {
                        pass += chars.charAt(Math.floor(Math.random() * chars.length));
                    }
                    document.getElementById('user_pass').value = pass;
                });
                </script>

            <?php else: ?>
                <!-- POPRAWIONA TABELA LISTY UŻYTKOWNIKÓW -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                    <div style="display:flex; align-items:center; gap: 12px;">
                        <h2 style="margin:0 !important;">Użytkownicy</h2>
                        <a href="load.php?id=simplepress&sub=users&action=new" class="button button-primary">Dodaj nowego</a>
                    </div>
                    <div>
                        <input type="search" id="user-search-input" placeholder="Szukaj użytkownika..." style="width: 220px; padding: 6px 10px; border: 1px solid var(--wp-input-border); border-radius: 4px; font-size: 13px;">
                    </div>
                </div>

                <div style="background: var(--wp-card-bg); border:1px solid var(--wp-border); border-radius:3px; overflow:hidden; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
                    <table class="highlight" id="users-table" style="width: 100%; margin: 0; border: none; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--wp-surface-alt); border-bottom: 1px solid var(--wp-border); text-align: left;">
                                <th style="width: 32%; padding: 12px 16px;">Użytkownik</th>
                                <th style="width: 28%; padding: 12px 16px;">Adres e-mail</th>
                                <th style="width: 15%; padding: 12px 16px;">Rola</th>
                                <th style="width: 10%; padding: 12px 16px; text-align: center;">Strony</th>
                                <th style="width: 15%; padding: 12px 16px; text-align: right;">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $user_files = glob(GSUSERSPATH . '*.xml');
                            if (empty($user_files)): ?>
                                <tr><td colspan="5" style="text-align:center; padding: 30px; color:var(--wp-text-muted);">Brak zarejestrowanych kont użytkowników.</td></tr>
                            <?php else:
                                foreach ($user_files as $ufile):
                                    $uname = basename($ufile, '.xml');
                                    $xml = getXML($ufile);
                                    $email = (string)($xml->EMAIL ?? '');
                                    $role = wp_simple_get_user_role($uname);
                                    $page_count = wp_simple_count_author_pages($uname);
                                    $is_me = ($uname === $current_user);
                                    $initial = strtoupper(substr($uname, 0, 1));
                            ?>
                                <tr class="user-table-row" style="border-bottom: 1px solid var(--wp-border-light); vertical-align: middle;">
                                    <td style="padding: 12px 16px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div class="wp-user-avatar"><?php echo $initial; ?></div>
                                            <div>
                                                <strong style="font-size: 14px; color: var(--wp-text-strong);"><?php echo htmlspecialchars($uname); ?></strong>
                                                <?php if ($is_me): ?>
                                                    <span style="background: #e7f5ea; color: #1e4620; font-size: 11px; padding: 2px 8px; border-radius: 12px; margin-left: 6px; font-weight: 600; display: inline-block;">To Ty</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <a href="mailto:<?php echo htmlspecialchars($email); ?>" style="color: var(--wp-blue); text-decoration: none;">
                                            <?php echo htmlspecialchars($email); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <?php if ($role === 'administrator'): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; font-weight: 500; color: var(--wp-text-strong);">
                                                <span class="dashicons dashicons-shield" style="font-size: 16px; color: var(--wp-blue);"></span> Administrator
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--wp-text-muted);">Redaktor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: center;">
                                        <span style="background: var(--wp-content-bg); color: var(--wp-text-strong); padding: 3px 8px; border-radius: 10px; font-size: 12px; font-weight: 600;">
                                            <?php echo $page_count; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right; white-space: nowrap;">
                                        <a href="load.php?id=simplepress&sub=users&action=edit&user=<?php echo urlencode($uname); ?>" class="button" style="margin-right: 4px;">Edytuj</a>
                                        <?php if (!$is_me): ?>
                                            <form method="post" action="load.php?id=simplepress&amp;sub=users" style="display:inline" onsubmit="return confirm('Czy na pewno chcesz bezpowrotnie usunąć użytkownika &quot;<?php echo sp_e($uname); ?>&quot;?');">
                                                <?php sp_nonce_field('delete_user'); ?>
                                                <input type="hidden" name="wp_delete_user" value="1">
                                                <input type="hidden" name="user" value="<?php echo sp_e($uname); ?>">
                                                <button type="submit" class="button wp-btn-danger">Usuń</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>

                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const searchInput = document.getElementById("user-search-input");
                    if (searchInput) {
                        searchInput.addEventListener("keyup", function() {
                            const val = this.value.toLowerCase();
                            document.querySelectorAll("#users-table tbody tr.user-table-row").forEach(function(row) {
                                row.style.display = row.textContent.toLowerCase().indexOf(val) > -1 ? "" : "none";
                            });
                        });
                    }
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
    <?php
}