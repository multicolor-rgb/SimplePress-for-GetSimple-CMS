<?php
if (!defined('IN_GS')) { die('You cannot load this file directly!'); }

define('WP_SIMPLE_SNIPPETS_DIR', GSDATAOTHERPATH . 'snippetMassive/');
define('WP_SIMPLE_SNIPPETS_FILE', WP_SIMPLE_SNIPPETS_DIR . 'snippet.json');

# ==============================================================================
# GLOBALNE METODY POBIERANIA SNIPPETÓW: get_snippet('nazwa') / return_snippet('nazwa')
# ==============================================================================
if (!function_exists('get_snippet')) {
    function get_snippet($name) {
        echo return_snippet($name);
    }
}

if (!function_exists('return_snippet')) {
    function return_snippet($name) {
        if (file_exists(WP_SIMPLE_SNIPPETS_FILE)) {
            $data = json_decode(@file_get_contents(WP_SIMPLE_SNIPPETS_FILE), true);
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (isset($item['title']) && $item['title'] === $name) {
                        return $item['content'] ?? '';
                    }
                }
            }
        }
        return '';
    }
}

# ==============================================================================
# OBSŁUGA ZAPISU FORMULARZA
# ==============================================================================
function wp_simple_handle_snippets_actions() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp_save_snippets'])) {
        sp_require_post('save_snippets');
        $titles = $_POST['snippetTitle'] ?? [];
        $contents = $_POST['snippetContent'] ?? [];

        $saved_snippets = [];

        if (!empty($titles) && is_array($titles)) {
            for ($i = 0; $i < count($titles); $i++) {
                $raw_title = trim($titles[$i]);
                $clean_title = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '-', $raw_title));
                $content = $contents[$i] ?? '';

                if (!empty($clean_title)) {
                    $saved_snippets[] = [
                        'title' => $clean_title,
                        'content' => $content
                    ];
                }
            }
        }

        if (!is_dir(WP_SIMPLE_SNIPPETS_DIR)) {
            @mkdir(WP_SIMPLE_SNIPPETS_DIR, 0755, true);
        }

        $json_data = json_encode($saved_snippets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (@file_put_contents(WP_SIMPLE_SNIPPETS_FILE, $json_data) !== false) {
            redirect('load.php?id=simplepress&sub=snippets&saved=1');
        } else {
            redirect('load.php?id=simplepress&sub=snippets&error=save');
        }
        exit;
    }
}
add_action('load-common', 'wp_simple_handle_snippets_actions');

# ==============================================================================
# WIDOK ZARZĄDZANIA SNIPPETAMI Z WYBRANYM EDYTOREM
# ==============================================================================
function wp_simple_snippets_view() {
    sp_require_admin();
    global $SITEURL, $TEMPLATE, $EDTOOL, $toolbar, $options, $EDOPTIONS;

    wp_simple_handle_snippets_actions();

    // Pobieramy aktywny edytor (CKEditor / TinyMCE), z preferencją użytkownika.
    $active_editor = function_exists('sp_active_editor') ? sp_active_editor() : 'ckeditor';
    if ($active_editor !== 'tinymce') {
        $active_editor = 'ckeditor';
    }

    $default_toolbar = 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image media | code fullscreen';
    $tinymce_toolbar = function_exists('wp_simple_get_setting') ? wp_simple_get_setting('tinymce_toolbar', $default_toolbar) : $default_toolbar;

    $snippets = [];
    if (file_exists(WP_SIMPLE_SNIPPETS_FILE)) {
        $decoded = json_decode(@file_get_contents(WP_SIMPLE_SNIPPETS_FILE), true);
        if (is_array($decoded)) {
            $snippets = $decoded;
        }
    }
    ?>
    <style>
        .snippet-card {
            background: var(--wp-card-bg);
            border: 1px solid var(--wp-border);
            border-radius: 4px;
            margin-bottom: 25px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            position: relative;
        }
        .snippet-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: var(--wp-surface-alt);
            border-bottom: 1px solid var(--wp-border);
            border-radius: 3px 3px 0 0;
            gap: 10px;
            flex-wrap: wrap;
        }
        .snippet-code-badge {
            background: var(--wp-blue);
            color: var(--wp-on-accent);
            font-family: monospace;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            user-select: all;
        }
        .copy-snippet-btn {
            cursor: pointer;
            padding: 3px 8px;
            font-size: 11px;
            border-radius: 3px;
            border: 1px solid var(--wp-border);
            background: var(--wp-card-bg);
            color: var(--wp-blue);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .copy-snippet-btn:hover { background: var(--wp-content-bg); }
        .snippet-body { padding: 15px; }
        .btn-remove-snippet {
            background: var(--wp-danger-strong) !important;
            border-color: var(--wp-danger-strong) !important;
            color: var(--wp-on-accent) !important;
            padding: 2px 10px !important;
            font-size: 12px !important;
            line-height: 2 !important;
            height: auto !important;
            cursor: pointer;
        }
        .btn-remove-snippet:hover { background: var(--wp-danger-strong) !important; }
        .tox-tinymce { border: 1px solid var(--wp-border) !important; border-radius: 3px !important; }
    </style>

    <?php if ($active_editor === 'tinymce'): ?>
        <script src="<?php echo sp_e(sp_plugin_url('lib/tinymce/tinymce.min.js')); ?>"></script>
    <?php else: ?>
        <script type="text/javascript" src="template/js/ckeditor/ckeditor.js?t=3.3.18.1"></script>
    <?php endif; ?>

    <div id="wpbody-content">
        <div class="wrap">
            <?php if (function_exists('wp_simple_render_subtabs')) { wp_simple_render_subtabs(); } ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <div>
                    <h2 style="margin:0 !important;"><?php echo sp_e(wp_i18n('SNIPPETS_TITLE', 'Content snippets')); ?></h2>
                    <p style="color:var(--wp-text-muted); font-size:13px; margin:4px 0 0 0;">
                        <?php echo sp_e(wp_i18n('SNIPPETS_ACTIVE_EDITOR', 'Active editor:')); ?> <strong><?php echo ($active_editor === 'tinymce') ? 'TinyMCE' : 'CKEditor'; ?></strong>.
                        <?php echo wp_i18n('SNIPPETS_USAGE', 'Use snippets in templates with <code>&lt;?php get_snippet("name"); ?&gt;</code>.'); ?>
                    </p>
                </div>
                <button type="button" class="button button-primary" id="btn-add-snippet">
                    <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle; margin-top:-2px;"></span> <?php echo sp_e(wp_i18n('SNIPPETS_ADD', 'Add snippet')); ?>
                </button>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="updated"><p><?php echo sp_e(wp_i18n('SNIPPETS_SAVED', 'All snippets saved successfully.')); ?></p></div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="error"><p><?php echo sp_e(sprintf(wp_i18n('SNIPPETS_SAVE_ERROR', 'Error saving the snippets file. Check permissions for directory %s.'), 'data/other/')); ?></p></div>
            <?php endif; ?>

            <form method="post" action="load.php?id=simplepress&amp;sub=snippets" id="snippets-form">
                <?php sp_nonce_field('save_snippets'); ?>
                <div id="snippets-container">
                    <?php if (empty($snippets)): ?>
                        <div id="no-snippets-notice" style="background: var(--wp-card-bg); border:1px dashed var(--wp-border); padding:30px; text-align:center; color:var(--wp-text-muted); border-radius:4px; margin-bottom:20px;">
                            <?php echo sp_e(wp_i18n('SNIPPETS_EMPTY', 'No snippets yet. Add your first one below.')); ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($snippets as $index => $item): 
                            $stitle = htmlspecialchars($item['title'] ?? '');
                            $scontent = htmlspecialchars($item['content'] ?? '');
                            $unique_id = 'snippet_editor_' . $index;
                        ?>
                            <div class="snippet-card">
                                <div class="snippet-header">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span class="dashicons dashicons-shortcode" style="color:var(--wp-blue);"></span>
                                        <span class="snippet-code-badge">&lt;?php get_snippet("<?php echo $stitle; ?>"); ?&gt;</span>
                                        <button type="button" class="copy-snippet-btn" data-code='<?php echo '<?php get_snippet("' . $stitle . '"); ?>'; ?>'>
                                            <?php echo sp_e(wp_i18n('SNIPPETS_COPY_CALL', 'Copy call')); ?>
                                        </button>
                                    </div>
                                    <button type="button" class="button btn-remove-snippet" onclick="removeSnippetCard(this, '<?php echo $unique_id; ?>');">
                                        <?php echo sp_e(wp_i18n('SNIPPETS_DELETE', 'Delete')); ?>
                                    </button>
                                </div>
                                <div class="snippet-body">
                                    <p style="margin:0 0 10px 0;">
                                        <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;"><?php echo sp_e(wp_i18n('SNIPPETS_ID', 'Identifier (title):')); ?></label>
                                        <input type="text" name="snippetTitle[]" value="<?php echo $stitle; ?>" required pattern="[a-zA-Z0-9_-]+" placeholder="<?php echo sp_e(wp_i18n('SNIPPETS_PLACEHOLDER', 'e.g. footer_contact')); ?>" onkeyup="updateBadge(this);" style="width:100%; max-width:400px; padding:6px 10px; border: 1px solid var(--wp-input-border); border-radius:4px; font-family:monospace;">
                                    </p>
                                    <p style="margin:0;">
                                        <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;"><?php echo sp_e(wp_i18n('SNIPPETS_CONTENT', 'Snippet content:')); ?></label>
                                        <textarea name="snippetContent[]" id="<?php echo $unique_id; ?>" class="snippet-editor-target" style="width:100%; height:260px;"><?php echo $scontent; ?></textarea>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <p class="submit" style="margin-top:20px;">
                    <input type="submit" name="wp_save_snippets" class="button button-primary button-hero" value="<?php echo sp_e(wp_i18n('SNIPPETS_SAVE_ALL', 'Save all snippets')); ?>">
                </p>
            </form>
        </div>
    </div>

    <script>
    var snippetI18n = <?php echo json_encode([
        'delete' => wp_i18n('SNIPPETS_DELETE', 'Delete'),
        'id' => wp_i18n('SNIPPETS_ID', 'Identifier (title):'),
        'content' => wp_i18n('SNIPPETS_CONTENT', 'Snippet content:'),
        'placeholder' => wp_i18n('SNIPPETS_PLACEHOLDER_NEW', 'e.g. sidebar_banner'),
        'defaultId' => wp_i18n('SNIPPETS_DEFAULT_ID', 'identifier'),
        'copied' => wp_i18n('SNIPPETS_COPIED', 'Copied!'),
        'deleteConfirm' => wp_i18n('SNIPPETS_DELETE_CONFIRM', 'Are you sure you want to delete this snippet?'),
        'serverError' => wp_i18n('SNIPPETS_SERVER_ERROR', 'Server error:'),
        'badJson' => wp_i18n('SNIPPETS_BAD_JSON', 'Invalid JSON response.'),
        'parseError' => wp_i18n('SNIPPETS_PARSE_ERROR', 'Error parsing the server response.'),
        'networkError' => wp_i18n('SNIPPETS_NETWORK_ERROR', 'Network error while sending.'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var currentActiveEditor = "<?php echo $active_editor; ?>";
    var tinymceToolbar = <?php echo json_encode($tinymce_toolbar); ?>;
    var snippetCounter = <?php echo count($snippets); ?>;

    // Uniwersalna funkcja uploadu zdjęć dla TinyMCE w snippetach
    function tinymceUploadHandler(blobInfo, progress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'load.php?id=simplepress&action=wp_simple_editor_upload');

            xhr.upload.onprogress = function (e) {
                progress(e.loaded / e.total * 100);
            };

            xhr.onload = function () {
                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(snippetI18n.serverError + ' ' + xhr.status);
                    return;
                }
                try {
                    var json = JSON.parse(xhr.responseText.trim());
                    if (!json || (!json.location && !json.url)) {
                        reject(json.error || snippetI18n.badJson);
                        return;
                    }
                    resolve(json.location || json.url);
                } catch (err) {
                    reject(snippetI18n.parseError);
                }
            };

            xhr.onerror = function () { reject(snippetI18n.networkError); };

            var formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());

            formData.append('sp_nonce', '<?php echo sp_e(sp_nonce('editor_upload')); ?>');
            xhr.send(formData);
        });
    }

    // Inicjalizacja instancji edytora na pojedynczym elemencie
    function initEditorInstance(textareaId) {
        if (currentActiveEditor === 'tinymce' && typeof tinymce !== 'undefined') {
            tinymce.init({
                selector: '#' + textareaId,
                height: 280,
                menubar: false,
                plugins: 'advlist autolink lists link image charmap preview searchreplace code fullscreen table help',
                toolbar: tinymceToolbar,
                branding: false,
                promotion: false,
                base_url: '<?php echo sp_e(sp_plugin_url('lib/tinymce')); ?>',
                suffix: '.min',
                skin_url: '<?php echo sp_e(sp_plugin_url('lib/tinymce/skins/ui/oxide')); ?>',
                content_css: '<?php echo sp_e(sp_plugin_url('lib/tinymce/skins/content/default/content.min.css')); ?>',
                skin: 'oxide',
                automatic_uploads: true,
                images_upload_handler: tinymceUploadHandler,
                setup: function (editor) {
                    editor.on('change keyup NodeChange blur', function () {
                        editor.save();
                    });
                }
            });
        } else if (currentActiveEditor === 'ckeditor' && typeof CKEDITOR !== 'undefined') {
            CKEDITOR.replace(textareaId, {
                skin: 'getsimple',
                forcePasteAsPlainText: true,
                entities: false,
                height: '240px',
                tabSpaces: 10,
                filebrowserBrowseUrl: 'filebrowser.php?type=all',
                filebrowserImageBrowseUrl: 'filebrowser.php?type=images',
                filebrowserWindowWidth: '730',
                filebrowserWindowHeight: '500'
            });
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById("snippets-container");
        const addBtn = document.getElementById("btn-add-snippet");
        const emptyNotice = document.getElementById("no-snippets-notice");

        // Inicjalizacja edytorów dla istniejących snippetów
        document.querySelectorAll('.snippet-editor-target').forEach(function(textarea) {
            initEditorInstance(textarea.id);
        });

        // Dynamiczne dodawanie nowego snippetu
        addBtn.addEventListener("click", function() {
            if (emptyNotice) emptyNotice.style.display = "none";

            snippetCounter++;
            const newId = 'snippet_editor_new_' + snippetCounter;

            const newCard = document.createElement("div");
            newCard.className = "snippet-card";
            newCard.innerHTML = `
                <div class="snippet-header">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-shortcode" style="color:var(--wp-blue);"></span>
                        <span class="snippet-code-badge">&lt;?php get_snippet("nowy_snippet"); ?&gt;</span>
                    </div>
                    <button type="button" class="button btn-remove-snippet" onclick="removeSnippetCard(this, '${newId}');">${snippetI18n.delete}</button>
                </div>
                <div class="snippet-body">
                    <p style="margin:0 0 10px 0;">
                        <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">${snippetI18n.id}</label>
                        <input type="text" name="snippetTitle[]" value="" required pattern="[a-zA-Z0-9_-]+" placeholder="${snippetI18n.placeholder}" onkeyup="updateBadge(this);" style="width:100%; max-width:400px; padding:6px 10px; border: 1px solid var(--wp-input-border); border-radius:4px; font-family:monospace;">
                    </p>
                    <p style="margin:0;">
                        <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">${snippetI18n.content}</label>
                        <textarea name="snippetContent[]" id="${newId}" style="width:100%; height:260px;"></textarea>
                    </p>
                </div>
            `;
            container.prepend(newCard);

            // Odpalenie wybranego edytora na nowym polu
            setTimeout(function() {
                initEditorInstance(newId);
            }, 50);

            newCard.querySelector('input').focus();
        });

        // Przechwycenie formularza i synchronizacja danych przed POST
        const snippetsForm = document.getElementById("snippets-form");
        if (snippetsForm) {
            snippetsForm.addEventListener("submit", function() {
                if (currentActiveEditor === 'tinymce' && typeof tinymce !== 'undefined') {
                    tinymce.triggerSave();
                } else if (currentActiveEditor === 'ckeditor' && typeof CKEDITOR !== 'undefined') {
                    for (var instance in CKEDITOR.instances) {
                        CKEDITOR.instances[instance].updateElement();
                    }
                }
            });
        }

        // Kopiowanie wywołania PHP do schowka
        document.addEventListener("click", function(e) {
            const copyBtn = e.target.closest(".copy-snippet-btn");
            if (copyBtn) {
                const code = copyBtn.getAttribute("data-code");
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(code).then(function() {
                        const originalText = copyBtn.textContent;
                        copyBtn.textContent = snippetI18n.copied;
                        setTimeout(function() {
                            copyBtn.textContent = originalText;
                        }, 1800);
                    });
                }
            }
        });
    });

    function updateBadge(input) {
        const val = input.value.trim() || snippetI18n.defaultId;
        const badge = input.closest('.snippet-card').querySelector('.snippet-code-badge');
        if (badge) {
            badge.textContent = '<?php get_snippet("' + val + '"); ?>';
        }
    }

    function removeSnippetCard(btn, editorId) {
        if (confirm(snippetI18n.deleteConfirm)) {
            if (currentActiveEditor === 'tinymce' && typeof tinymce !== 'undefined') {
                var inst = tinymce.get(editorId);
                if (inst) inst.remove();
            } else if (currentActiveEditor === 'ckeditor' && typeof CKEDITOR !== 'undefined') {
                if (CKEDITOR.instances[editorId]) {
                    CKEDITOR.instances[editorId].destroy();
                }
            }
            btn.closest(".snippet-card").remove();
        }
    }
    </script>
    <?php
}