<?php
if (!defined('IN_GS')) { die('You cannot load this file directly!'); }

# ==============================================================================
# LOGIKA MIGRACJI DOMEN I WYMUSZANIA SSL
# ==============================================================================

/**
 * Zastępuje stary URL nowym we wszystkich plikach podstron XML GetSimple CMS
 */
function wp_simple_migrate_domain($old_url, $new_url) {
    $old_url = sp_url(trim($old_url), false);
    $new_url = sp_url(trim($new_url), false);

    if (!$old_url || !$new_url || $old_url === $new_url) {
        return false;
    }
    if (function_exists('wp_simple_create_backup')) {
        $backup = wp_simple_create_backup('data');
        if (empty($backup[0])) {
            return false;
        }
    }

    $modified = 0;
    $skip = str_replace('\\', '/', sp_storage_dir() . '/backups');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(GSDATAPATH, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($iterator as $file) {
        if ($file->isLink() || !$file->isFile()) { continue; }
        $path = str_replace('\\', '/', $file->getPathname());
        if (strpos($path, $skip) === 0) { continue; }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['xml', 'json', 'txt', 'html', 'htm', 'css', 'js'], true)) { continue; }
        if ($file->getSize() > 5 * 1024 * 1024) { continue; }
        $content = @file_get_contents($path);
        if ($content === false || strpos($content, $old_url) === false) { continue; }
        $newContent = str_replace($old_url, $new_url, $content);
        if ($newContent !== $content && sp_write_file($path, $newContent, 0640)) {
            $modified++;
        }
    }

    if (function_exists('create_pagesxml')) {
        create_pagesxml(true);
    }
    sp_log('domain_migrate', $old_url . ' -> ' . $new_url . ' (' . $modified . ' files)');
    return $modified;
}

/**
 * Wymusza reguły HTTPS w pliku .htaccess
 */
function wp_simple_set_force_ssl($enable = true) {
    $htaccess_path = GSROOTPATH . '.htaccess';
    $marker_start  = "# BEGIN SimplePress SSL";
    $marker_end    = "# END SimplePress SSL";

    $ssl_rules = $marker_start . PHP_EOL .
                 "<IfModule mod_rewrite.c>" . PHP_EOL .
                 "RewriteEngine On" . PHP_EOL .
                 "RewriteCond %{HTTPS} off" . PHP_EOL .
                 "RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]" . PHP_EOL .
                 "</IfModule>" . PHP_EOL .
                 $marker_end . PHP_EOL;

    $content = file_exists($htaccess_path) ? @file_get_contents($htaccess_path) : '';
    if ($content === false) {
        $content = '';
    }
    if (file_exists($htaccess_path)) {
        $backupDir = sp_storage_dir() . '/backups';
        sp_private_dir($backupDir);
        @copy($htaccess_path, $backupDir . '/htaccess-' . date('Ymd-His') . '.txt');
    }

    // Usunięcie starych reguł, jeśli istnieją
    $pattern = '/' . preg_quote($marker_start, '/') . '.*?' . preg_quote($marker_end, '/') . '\s*/s';
    $content = preg_replace($pattern, '', $content);
    $oldPattern = '/# BEGIN WP-Simple SSL.*?# END WP-Simple SSL\s*/s';
    $content = preg_replace($oldPattern, '', $content);

    if ($enable) {
        $content = $ssl_rules . PHP_EOL . ltrim($content);
    }

    $saved = @file_put_contents($htaccess_path, trim($content) . PHP_EOL);

    // Zapis stanu w pliku konfiguracyjnym wtyczki
    $status_dir = GSDATAOTHERPATH . 'simplepress/';
    if (!is_dir($status_dir)) {
        @mkdir($status_dir, 0755, true);
    }
    @file_put_contents($status_dir . 'ssl_status.txt', $enable ? 'on' : 'off');

    return ($saved !== false);
}

/**
 * Sprawdza czy wymuszanie SSL jest włączone
 */
function wp_simple_is_ssl_forced() {
    $status_file = GSDATAOTHERPATH . 'simplepress/ssl_status.txt';
    if (!file_exists($status_file)) {
        $old = GSDATAOTHERPATH . 'wp_simple_admin/ssl_status.txt';
        if (file_exists($old)) { sp_private_dir(dirname($status_file)); @copy($old, $status_file); }
    }
    if (file_exists($status_file)) {
        return trim(@file_get_contents($status_file)) === 'on';
    }
    return false;
}

# ==============================================================================
# PRZETWARZANIE FORMULARZY
# ==============================================================================
function wp_simple_handle_migrate_actions() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Migracja URL
        if (isset($_POST['wp_migrate_domain_submit'])) {
            sp_require_post('migrate_domain');
            $old_url = $_POST['old_url'] ?? '';
            $new_url = $_POST['new_url'] ?? '';

            $result = wp_simple_migrate_domain($old_url, $new_url);
            if ($result !== false) {
                redirect('load.php?id=simplepress&sub=migrate&migrated=' . (int)$result);
            } else {
                redirect('load.php?id=simplepress&sub=migrate&error=empty_urls');
            }
            exit;
        }

        // Wymuszenie SSL
        if (isset($_POST['wp_ssl_submit'])) {
            sp_require_post('save_ssl');
            $turnon = isset($_POST['turnon_ssl']) && $_POST['turnon_ssl'] === 'on';
            if (wp_simple_set_force_ssl($turnon)) {
                redirect('load.php?id=simplepress&sub=migrate&ssl_saved=1');
            } else {
                redirect('load.php?id=simplepress&sub=migrate&error=htaccess_write');
            }
            exit;
        }
    }
}
add_action('load-common', 'wp_simple_handle_migrate_actions');

# ==============================================================================
# WIDOK KOKPITU MIGRACJI
# ==============================================================================
function wp_simple_migrate_view() {
    sp_require_admin();
    global $SITEURL;
    wp_simple_handle_migrate_actions();

    $ssl_active = wp_simple_is_ssl_forced();
    ?>
    <div id="wpbody-content">
        <div class="wrap">
            <?php if (function_exists('wp_simple_render_subtabs')) { wp_simple_render_subtabs(); } ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h2 style="margin:0 !important;">Narzędzia migracji i SSL</h2>
            </div>

            <?php if (isset($_GET['migrated'])): ?>
                <div class="updated"><p>Pomyślnie zaktualizowano linki w <strong><?php echo (int)$_GET['migrated']; ?></strong> plikach stron!</p></div>
            <?php elseif (isset($_GET['ssl_saved'])): ?>
                <div class="updated"><p>Ustawienia przekierowania SSL zostały pomyślnie zapisane w pliku <code>.htaccess</code>.</p></div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="error"><p>
                    <?php 
                        if ($_GET['error'] === 'empty_urls') {
                            echo 'Błąd: Adres źródłowy i docelowy muszą być różne i nie mogą być puste.';
                        } elseif ($_GET['error'] === 'htaccess_write') {
                            echo 'Błąd: Nie udało się zmodyfikować pliku .htaccess. Sprawdź uprawnienia do zapisu w katalogu głównym.';
                        } else {
                            echo 'Wystąpił nieoczekiwany błąd.';
                        }
                    ?>
                </p></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px;">
                
                <!-- KARTA 1: MIGRACJA ADRESU DOMENY -->
                <div class="postbox" style="background: var(--wp-card-bg); border: 1px solid var(--wp-border); border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,0.04); margin-bottom: 0;">
                    <div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid var(--wp-border-light); background: var(--wp-surface-alt);">
                        <h3 style="margin: 0; font-size: 14px; font-weight: 600; display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-admin-site-alt3" style="color: var(--wp-blue);"></span> Migracja domeny i linków
                        </h3>
                    </div>
                    <div class="inside" style="padding: 16px;">
                        <p style="color: var(--wp-text-muted); font-size: 13px; margin-top: 0;">
                            Wyszukaj i podmień wszystkie wystąpienia starej domeny na nowy adres URL w treściach i załącznikach wszystkich podstron XML.
                        </p>
                        
                        <form method="post" action="load.php?id=simplepress&amp;sub=migrate">
                            <?php sp_nonce_field('migrate_domain'); ?>
                            <p>
                                <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Stary adres URL:</label>
                                <input type="text" name="old_url" value="<?php echo htmlspecialchars(rtrim($SITEURL, '/')); ?>" required style="width: 100%; padding: 7px 10px; border: 1px solid var(--wp-input-border); border-radius: 4px; box-sizing: border-box; font-family: monospace;">
                            </p>
                            <p>
                                <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Nowy adres URL:</label>
                                <input type="text" name="new_url" value="" placeholder="https://nowadomena.pl" required style="width: 100%; padding: 7px 10px; border: 1px solid var(--wp-input-border); border-radius: 4px; box-sizing: border-box; font-family: monospace;">
                            </p>
                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--wp-border-light);">
                                <input type="submit" name="wp_migrate_domain_submit" class="button button-primary" value="Podmień domeny w stronach" onclick="return confirm('Czy na pewno chcesz zamienić adresy URL we wszystkich plikach stron? Ta operacja jest nieodwracalna.');">
                            </div>
                        </form>
                    </div>
                </div>

                <!-- KARTA 2: WYMUSZENIE POŁĄCZENIA SSL -->
                <div class="postbox" style="background: var(--wp-card-bg); border: 1px solid var(--wp-border); border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,0.04); margin-bottom: 0;">
                    <div class="postbox-header" style="padding: 12px 16px; border-bottom: 1px solid var(--wp-border-light); background: var(--wp-surface-alt);">
                        <h3 style="margin: 0; font-size: 14px; font-weight: 600; display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-lock" style="color: var(--wp-success);"></span> Bezpieczeństwo i wymuszenie HTTPS
                        </h3>
                    </div>
                    <div class="inside" style="padding: 16px;">
                        <p style="color: var(--wp-text-muted); font-size: 13px; margin-top: 0;">
                            Wymusza automatyczne przekierowanie każdego połączenia HTTP na protokół HTTPS (kod 301) za pośrednictwem pliku <code>.htaccess</code>.
                        </p>

                        <form method="post" action="load.php?id=simplepress&amp;sub=migrate">
                            <?php sp_nonce_field('save_ssl'); ?>
                            <div style="background: var(--wp-surface-alt); border: 1px solid var(--wp-border); padding: 14px; border-radius: 4px; margin-bottom: 16px;">
                                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;">
                                    <input type="checkbox" name="turnon_ssl" value="on" <?php echo $ssl_active ? 'checked' : ''; ?> style="margin: 0;">
                                    Włącz wymuszanie certyfikatu SSL (HTTPS)
                                </label>
                                <p class="description" style="margin: 6px 0 0 24px; font-size: 12px; color: var(--wp-text-muted);">
                                    Upewnij się, że Twoja domena posiada aktywny i poprawny certyfikat SSL przed zaznaczeniem tej opcji.
                                </p>
                            </div>

                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--wp-border-light);">
                                <input type="submit" name="wp_ssl_submit" class="button button-primary" value="Zapisz ustawienia SSL">
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php
}