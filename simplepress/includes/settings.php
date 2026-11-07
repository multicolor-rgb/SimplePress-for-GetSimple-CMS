<?php
if (!defined('IN_GS')) { die('You cannot load this file directly!'); }

# ==============================================================================
# 1. FUNKCJE POMOCNICZE BAZY SQLITE3 DLA USTAWIEŃ
# ==============================================================================

if (!function_exists('wp_simple_get_setting')) {
    function wp_simple_get_setting($key, $default = '') {
        if (!function_exists('wp_simple_get_db')) {
            return $default;
        }
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare("SELECT value FROM settings WHERE key = :key LIMIT 1");
            if ($stmt) {
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $res = $stmt->execute();
                if ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                    return $row['value'];
                }
            }
        }
        return $default;
    }
}

if (!function_exists('wp_simple_save_setting')) {
    function wp_simple_save_setting($key, $val) {
        if (!function_exists('wp_simple_get_db')) {
            return false;
        }
        $db = wp_simple_get_db();
        if ($db) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :val)");
            if ($stmt) {
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $stmt->bindValue(':val', $val, SQLITE3_TEXT);
                $result = $stmt->execute();
                return ($result !== false);
            }
        }
        return false;
    }
}

# ==============================================================================
# 2. PRZETWARZANIE FORMULARZA I WIDOK USTAWIEŃ
# ==============================================================================

if (!function_exists('wp_simple_process_settings')) {
    function wp_simple_process_settings() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp_save_settings'])) {
            sp_require_post('save_editor_settings');
            $editor = isset($_POST['active_editor']) ? trim($_POST['active_editor']) : 'ckeditor';
            $toolbar = isset($_POST['tinymce_toolbar']) ? trim($_POST['tinymce_toolbar']) : '';
            if (!in_array($editor, ['ckeditor', 'tinymce'], true)) {
                $editor = 'ckeditor';
            }
            $toolbar = preg_replace('/[^a-zA-Z0-9_|\- ]/', '', $toolbar);
            $toolbar = substr($toolbar, 0, 1000);

            if (in_array($editor, ['ckeditor', 'tinymce'])) {
                wp_simple_save_setting('active_editor', $editor);
                wp_simple_save_setting('tinymce_toolbar', $toolbar);

                if (function_exists('redirect')) {
                    redirect('load.php?id=simplepress&sub=settings&updated=1');
                } else {
                    header('Location: load.php?id=simplepress&sub=settings&updated=1');
                }
                exit;
            }
        }
    }
}

if (!function_exists('wp_simple_settings_view')) {
    function wp_simple_settings_view() {
        sp_require_admin();
        wp_simple_process_settings();

        $active_editor = wp_simple_get_setting('active_editor', 'ckeditor');
        $default_toolbar = 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code fullscreen';
        $tinymce_toolbar = wp_simple_get_setting('tinymce_toolbar', $default_toolbar);
        ?>
        <div id="wpbody-content">
            <div class="wrap">
                <?php if (function_exists('wp_simple_render_subtabs')) { wp_simple_render_subtabs(); } ?>
                
                <h2 style="margin-bottom: 20px;">Ustawienia edytora treści</h2>

                <?php if (isset($_GET['updated'])): ?>
                    <div class="updated"><p>Ustawienia zostały pomyślnie zapisane.</p></div>
                <?php endif; ?>

                <form method="post" action="load.php?id=simplepress&amp;sub=settings">
                    <?php sp_nonce_field('save_editor_settings'); ?>
                    <table class="form-table" style="max-width: 850px; background: var(--wp-card-bg); border: 1px solid var(--wp-border); padding: 20px; border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                        <tbody>
                            <tr>
                                <th scope="row" style="width: 220px; padding: 15px 10px; vertical-align: top; font-weight: 600; color: var(--wp-text-strong);">
                                    Domyślny edytor treści
                                </th>
                                <td style="padding: 15px 10px;">
                                    <fieldset>
                                        <label style="display: block; margin-bottom: 12px; cursor: pointer;">
                                            <input type="radio" name="active_editor" value="ckeditor" <?php echo ($active_editor === 'ckeditor') ? 'checked' : ''; ?> style="margin-right: 8px;">
                                            <strong>CKEditor</strong> <span style="color: var(--wp-text-muted);">(Domyślny edytor GetSimple CMS)</span>
                                        </label>
                                        <label style="display: block; margin-bottom: 12px; cursor: pointer;">
                                            <input type="radio" name="active_editor" value="tinymce" <?php echo ($active_editor === 'tinymce') ? 'checked' : ''; ?> style="margin-right: 8px;">
                                            <strong>TinyMCE</strong> <span style="color: var(--wp-text-muted);">(Standard znany z klasycznego WordPressa)</span>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr id="tinymce-options" style="<?php echo ($active_editor === 'tinymce') ? '' : 'display:none;'; ?>">
                                <th scope="row" style="width: 220px; padding: 15px 10px; vertical-align: top; font-weight: 600; color: var(--wp-text-strong);">
                                    Pasek narzędzi TinyMCE
                                </th>
                                <td style="padding: 15px 10px;">
                                    <textarea name="tinymce_toolbar" rows="3" style="width: 100%; font-family: monospace; font-size: 12px; padding: 8px; border: 1px solid var(--wp-input-border); border-radius: 4px; box-sizing: border-box;"><?php echo htmlspecialchars($tinymce_toolbar); ?></textarea>
                                    <p class="description" style="color: var(--wp-text-muted); font-size: 12px; margin: 6px 0 0 0;">
                                        Wpisz przyciski oddzielone spacjami. Znak <code>|</code> tworzy pionowy separator grup.
                                    </p>
                                    <p style="margin-top: 8px; font-size: 12px;">
                                        <strong>Presety paska:</strong><br>
                                        <a href="#" class="wp-preset-link" data-preset="undo redo | bold italic underline | bullist numlist | link unlink | removeformat">Minimalistyczny</a> | 
                                        <a href="#" class="wp-preset-link" data-preset="undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code fullscreen">Pełny (Domyślny)</a>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit" style="margin-top: 20px;">
                        <input type="submit" name="wp_save_settings" class="button button-primary" value="Zapisz zmiany">
                    </p>
                </form>
            </div>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const radios = document.querySelectorAll("input[name='active_editor']");
            const optionsRow = document.getElementById("tinymce-options");
            radios.forEach(function(r) {
                r.addEventListener("change", function() {
                    if (optionsRow) {
                        optionsRow.style.display = (this.value === "tinymce") ? "" : "none";
                    }
                });
            });

            document.querySelectorAll(".wp-preset-link").forEach(function(btn) {
                btn.addEventListener("click", function(e) {
                    e.preventDefault();
                    const textarea = document.querySelector("textarea[name='tinymce_toolbar']");
                    if (textarea) {
                        textarea.value = this.dataset.preset;
                    }
                });
            });
        });
        </script>
        <?php
    }
}

# ==============================================================================
# 3. BEZPIECZNY ENDPOINT AJAX DLA UPLOADU GRAFIK (TINYMCE)
# ==============================================================================

if (!function_exists('wp_simple_tinymce_upload_handler')) {
    function wp_simple_tinymce_upload_handler() {
        if (isset($_GET['action']) && in_array($_GET['action'], ['wp_simple_tinymce_upload', 'wp_simple_editor_upload'], true)) {
            @ini_set('display_errors', '0');
            while (ob_get_level()) {
                @ob_end_clean();
            }

            header('Content-Type: application/json; charset=utf-8');

            if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Brak pliku do wgrania.']);
                exit;
            }

            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                echo json_encode(['error' => 'Kod błędu PHP uploadu: ' . $file['error']]);
                exit;
            }

            if (!sp_is_authenticated()) {
                http_response_code(403);
                echo json_encode(['error' => 'Brak uprawnień.']);
                exit;
            }
            if (!sp_verify_nonce('editor_upload', $_POST['sp_nonce'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Nieprawidłowy token bezpieczeństwa.']);
                exit;
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => 'Plik jest zbyt duży (limit 5 MB).']);
                exit;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowed, true) || !@getimagesize($file['tmp_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Dozwolone są tylko obrazy JPG, PNG, GIF i WebP.']);
                exit;
            }

            $base_name = pathinfo($file['name'], PATHINFO_FILENAME);
            $clean_base = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($base_name));
            if (empty($clean_base)) {
                $clean_base = 'img_' . date('Ymd_His');
            }

            $upload_dir = defined('GSDATAPATH') ? GSDATAPATH . 'uploads/' : dirname(dirname(dirname(__DIR__))) . '/data/uploads/';
            
            if (!is_dir($upload_dir)) {
                if (!@mkdir($upload_dir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Katalog data/uploads/ nie istnieje i nie można go utworzyć (brak uprawnień).']);
                    exit;
                }
            }

            $final_name = $clean_base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_file = $upload_dir . $final_name;
            if (!sp_path_within(dirname($target_file), $upload_dir, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Nieprawidłowa nazwa pliku.']);
                exit;
            }

            if (@move_uploaded_file($file['tmp_name'], $target_file)) {
                global $SITEURL;
                $site_url = !empty($SITEURL) ? rtrim($SITEURL, '/') : '';
                if (empty($site_url)) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $site_url = $protocol . $host;
                }

                $public_url = $site_url . '/data/uploads/' . $final_name;
                echo json_encode(['location' => $public_url]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Błąd zapisu pliku na serwerze. Sprawdź prawa do folderu data/uploads.']);
            }
            exit;
        }
    }
}

if (isset($_GET['action']) && in_array($_GET['action'], ['wp_simple_tinymce_upload', 'wp_simple_editor_upload'], true)) {
    wp_simple_tinymce_upload_handler();
}
# ==============================================================================
# 4. BLOKADA CODEMIRROR / CKEDITOR ORAZ MONTAŻ TINYMCE
# ==============================================================================

if (!function_exists('wp_simple_handle_editor_swap')) {
    function wp_simple_handle_editor_swap() {
        global $HTMLEDITOR, $EDTOOL;
        if (sp_active_editor() === 'tinymce') {
            $HTMLEDITOR = '';
            $EDTOOL = '';
        }
    }
    add_action('edit-extras', 'wp_simple_handle_editor_swap');
}

if (!function_exists('wp_simple_tinymce_head_styles')) {
    function wp_simple_tinymce_head_styles() {
        $current_script = basename($_SERVER['PHP_SELF'] ?? '');
        if ($current_script === 'edit.php' && sp_active_editor() === 'tinymce') {
            echo '<script src="' . sp_e(sp_plugin_url('lib/tinymce/tinymce.min.js')) . '"></script>' . PHP_EOL;
            echo '<style>
                .CodeMirror, div.CodeMirror { display: none !important; }
                /* Textarea musi pozostać w pełni dostępna dla formularza POST */
                #post-content { 
                    display: block !important; 
                    position: absolute !important; 
                    left: -9999px !important; 
                    top: -9999px !important;
                    width: 1px !important; 
                    height: 1px !important; 
                    opacity: 0 !important;
                    pointer-events: none !important;
                }
                .tox-tinymce { 
                    border: 1px solid var(--wp-border, var(--wp-border)) !important; 
                    border-radius: 3px !important; 
                    box-shadow: 0 1px 1px rgba(0,0,0,0.04) !important;
                }
            </style>' . PHP_EOL;
        }
    }
    add_action('header', 'wp_simple_tinymce_head_styles');
}

if (!function_exists('wp_simple_tinymce_footer_init')) {
    function wp_simple_tinymce_footer_init() {
        $current_script = basename($_SERVER['PHP_SELF'] ?? '');
        if ($current_script === 'edit.php' && sp_active_editor() === 'tinymce') {
            $default_toolbar = 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code fullscreen';
            $toolbar = wp_simple_get_setting('tinymce_toolbar', $default_toolbar);
            ?>
            <script type="text/javascript">
            (function() {
                function initTinyMCEBridge() {
                    var textarea = document.getElementById("post-content");
                    var form = document.getElementById("editform");
                    if (!textarea || typeof tinymce === "undefined") return;

                    // 1. Usunięcie pozostałości po CodeMirror
                    if (typeof CodeMirror !== "undefined") {
                        document.querySelectorAll('.CodeMirror').forEach(function(cm) {
                            if (cm.CodeMirror) {
                                cm.CodeMirror.toTextArea();
                            }
                            cm.remove();
                        });
                    }

                    // 2. Usunięcie ewentualnej wcześniejszej instancji TinyMCE
                    if (tinymce.get('post-content')) {
                        tinymce.get('post-content').remove();
                    }

                    // Funkcja twardo przepisująca dane do textarea
                    function commitContent() {
                        var ed = tinymce.get('post-content');
                        if (ed) {
                            var content = ed.getContent({ format: 'raw' });
                            textarea.value = content;
                            textarea.innerHTML = content;
                            textarea.defaultValue = content;
                            if (window.jQuery) {
                                window.jQuery('#post-content').val(content);
                            }
                        }
                    }

                    // 3. Inicjalizacja TinyMCE
                    tinymce.init({
                        selector: '#post-content',
                        height: 520,
                        menubar: true,
                        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
                        toolbar: <?php echo json_encode($toolbar); ?>,
                        branding: false,
                        promotion: false,
                        base_url: '<?php echo sp_e(sp_plugin_url("lib/tinymce")); ?>',
                        suffix: '.min',
                        skin_url: '<?php echo sp_e(sp_plugin_url("lib/tinymce/skins/ui/oxide")); ?>',
                        content_css: '<?php echo sp_e(sp_plugin_url("lib/tinymce/skins/content/default/content.min.css")); ?>',
                        skin: 'oxide',
                        
                        // Zapis czystego kodu HTML
                        entity_encoding: 'raw',
                        entities: '160,nbsp',
                        verify_html: false,
                        cleanup: false,
                        cleanup_on_startup: false,
                        forced_root_block: 'p',
                        
                        image_title: true,
                        automatic_uploads: true,
                        file_picker_types: 'image',

                        images_upload_handler: function (blobInfo, progress) {
                            return new Promise(function (resolve, reject) {
                                var xhr = new XMLHttpRequest();
                                xhr.withCredentials = false;
                                xhr.open('POST', 'load.php?id=simplepress&action=wp_simple_tinymce_upload');

                                xhr.upload.onprogress = function (e) {
                                    progress(e.loaded / e.total * 100);
                                };

                                xhr.onload = function () {
                                    if (xhr.status < 200 || xhr.status >= 300) {
                                        reject('Błąd serwera: ' + xhr.status);
                                        return;
                                    }
                                    try {
                                        var json = JSON.parse(xhr.responseText.trim());
                                        if (!json || typeof json.location !== 'string') {
                                            reject(json.error || 'Nieprawidłowa odpowiedź JSON.');
                                            return;
                                        }
                                        resolve(json.location);
                                    } catch (err) {
                                        reject('Błąd parsowania JSON.');
                                    }
                                };

                                xhr.onerror = function () {
                                    reject('Błąd sieciowy podczas uploadu.');
                                };

                                var formData = new FormData();
                                formData.append('file', blobInfo.blob(), blobInfo.filename());

                                formData.append('sp_nonce', '<?php echo sp_e(sp_nonce('editor_upload')); ?>');
                                xhr.send(formData);
                            });
                        },

                        setup: function (editor) {
                            editor.on('change keyup NodeChange undo redo SetContent', commitContent);
                            editor.on('init', function() {
                                commitContent();
                            });
                        }
                    });

                    // 4. Podstawienie fałszywej instancji CKEDITOR, aby skrypty GetSimple nie wywalały błędu
                    window.CKEDITOR = window.CKEDITOR || {};
                    window.CKEDITOR.instances = window.CKEDITOR.instances || {};
                    window.CKEDITOR.instances['post-content'] = {
                        getData: function() {
                            var ed = tinymce.get('post-content');
                            return ed ? ed.getContent({ format: 'raw' }) : textarea.value;
                        },
                        setData: function(val) {
                            var ed = tinymce.get('post-content');
                            if (ed) ed.setContent(val);
                        },
                        updateElement: function() {
                            commitContent();
                        }
                    };

                    // 5. Przechwycenie formularza i przycisków zapisu przed procedurami GetSimple
                    if (form) {
                        // Obsługa wczesnego zdarzenia mousedown i click na przycisku zapisu
                        var subBtn = document.getElementById("submitted");
                        if (subBtn) {
                            subBtn.addEventListener("mousedown", commitContent, true);
                            subBtn.addEventListener("click", commitContent, true);
                        }

                        var allSubmits = form.querySelectorAll('input[type="submit"], button[type="submit"]');
                        allSubmits.forEach(function(b) {
                            b.addEventListener("mousedown", commitContent, true);
                            b.addEventListener("click", commitContent, true);
                        });

                        form.addEventListener("submit", commitContent, true);

                        if (window.jQuery) {
                            window.jQuery(form).on('submit', function() {
                                commitContent();
                            });
                        }
                    }
                }

                // Bezpieczny start
                if (window.jQuery) {
                    jQuery(document).ready(function() {
                        setTimeout(initTinyMCEBridge, 100);
                    });
                } else {
                    window.addEventListener("load", function() {
                        setTimeout(initTinyMCEBridge, 100);
                    });
                }
            })();
            </script>
            <?php
        }
    }
    add_action('footer', 'wp_simple_tinymce_footer_init');
}