<?php
if (!defined('IN_GS')) { die('You cannot load this file directly!'); }

# ==============================================================================
# 1. FUNKCJA GENEROWANIA MINIATUR (HD 400PX + WYOSTRZANIE + WYMUSZANIE)
# ==============================================================================
if (!function_exists('wp_simple_generate_thumbnail')) {
    function wp_simple_generate_thumbnail($filename, $force = false) {
        $filename = basename((string) $filename);
        $upload_dir = defined('GSDATAUPLOADPATH') ? GSDATAUPLOADPATH : GSROOTPATH . 'data/uploads/';
        $thumb_dir  = defined('GSTHUMBNAILPATH') ? GSTHUMBNAILPATH : GSROOTPATH . 'data/thumbs/';

        if (!is_dir($thumb_dir)) {
            @mkdir($thumb_dir, 0755, true);
        }

        $src_file = $upload_dir . $filename;
        $dst_file = $thumb_dir . 'thumbsm.' . $filename;

        if (!file_exists($src_file)) return false;

        // Jeśli miniatura istnieje i nie wymuszamy odświeżenia, a oryginał nie jest nowszy - pomiń
        if (!$force && file_exists($dst_file) && filemtime($dst_file) >= filemtime($src_file)) {
            return true;
        }

        $info = @getimagesize($src_file);
        if (!$info) return false;

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($src_file); break;
            case 'image/png':  $img = @imagecreatefrompng($src_file); break;
            case 'image/gif':  $img = @imagecreatefromgif($src_file); break;
            case 'image/webp': $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src_file) : false; break;
            default: return false;
        }

        if (!$img) return false;

        $width  = $info[0];
        $height = $info[1];

        // Rozdzielczość bazowa HD 400px (idealna ostrość na ekranach Retina / HiDPI)
        $target_w = 400;
        $target_h = 400;

        $ratio = min($target_w / $width, $target_h / $height);
        $new_w = max(1, (int)($width * $ratio));
        $new_h = max(1, (int)($height * $ratio));

        $thumb = imagecreatetruecolor($new_w, $new_h);

        // Pełna przezroczystość dla PNG/WebP/GIF
        if ($mime === 'image/png' || $mime === 'image/webp' || $mime === 'image/gif') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $new_w, $new_h, $transparent);
        }

        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $new_w, $new_h, $width, $height);

        // Lekkie wyostrzenie po zmniejszeniu (unsharp mask)
        if ($mime === 'image/jpeg' || $mime === 'image/webp') {
            $sharpenMatrix = [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1]
            ];
            $divisor = 8;
            $offset = 0;
            @imageconvolution($thumb, $sharpenMatrix, $divisor, $offset);
        }

        // Zapis z maksymalnymi parametrami jakości
        switch ($mime) {
            case 'image/jpeg': 
                @imagejpeg($thumb, $dst_file, 93);
                break;
            case 'image/png':  
                @imagepng($thumb, $dst_file, 2);
                break;
            case 'image/gif':  
                @imagegif($thumb, $dst_file); 
                break;
            case 'image/webp': 
                @imagewebp($thumb, $dst_file, 92); 
                break;
        }

        imagedestroy($img);
        imagedestroy($thumb);
        return true;
    }
}

# ==============================================================================
# 1B. WSPÓLNE API BIBLIOTEKI MEDIÓW (ADMIN + FRONT CUSTOMIZER)
# ==============================================================================
if (!function_exists('sp_media_library_image_extensions')) {
    function sp_media_library_image_extensions()
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    }
}

if (!function_exists('sp_media_library_upload_extensions')) {
    function sp_media_library_upload_extensions()
    {
        return [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'odt', 'ods', 'odp', 'txt', 'csv', 'rtf',
            'zip', 'gz', 'tar', '7z',
            'mp3', 'wav', 'ogg', 'mp4', 'webm', 'mov', 'avi', 'ico',
        ];
    }
}

if (!function_exists('sp_media_library_public_url')) {
    function sp_media_library_public_url($file)
    {
        global $SITEURL;
        $file = rawurlencode((string) $file);
        return rtrim((string) $SITEURL, '/') . '/data/uploads/' . $file;
    }
}

if (!function_exists('sp_media_library_files')) {
    function sp_media_library_files($kind = 'all')
    {
        $kind = in_array($kind, ['all', 'image', 'file'], true) ? $kind : 'all';
        $upload_path = defined('GSDATAUPLOADPATH') ? GSDATAUPLOADPATH : GSROOTPATH . 'data/uploads/';
        $thumb_path = defined('GSTHUMBNAILPATH') ? GSTHUMBNAILPATH : GSROOTPATH . 'data/thumbs/';
        $image_ext = sp_media_library_image_extensions();
        $skip_ext = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phtm', 'phar', 'pl', 'py', 'jsp', 'asp', 'aspx', 'htm', 'html', 'shtml', 'sh', 'cgi', 'htaccess'];
        $files = [];

        if (!is_dir($upload_path)) {
            return $files;
        }

        $scan = @scandir($upload_path);
        if (!$scan) {
            return $files;
        }
        foreach ($scan as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.php' || is_dir($upload_path . $file)) {
                continue;
            }
            if (preg_match('/[<>"\']/', $file)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $skip_ext, true)) {
                continue;
            }
            $is_img = in_array($ext, $image_ext, true);
            if ($kind === 'image' && !$is_img) {
                continue;
            }
            $mtime = @filemtime($upload_path . $file);
            $thumb_url = sp_media_library_public_url($file);
            if ($is_img) {
                $thumb_file = $thumb_path . 'thumbsm.' . $file;
                if (!file_exists($thumb_file) || (int) @filemtime($thumb_file) < (int) $mtime) {
                    wp_simple_generate_thumbnail($file, true);
                }
                if (file_exists($thumb_file)) {
                    $thumb_url = rtrim((string) ($GLOBALS['SITEURL'] ?? ''), '/') . '/data/thumbs/' . rawurlencode('thumbsm.' . $file) . '?v=' . (int) @filemtime($thumb_file);
                }
            }
            $fsize = @filesize($upload_path . $file);
            $files[] = [
                'name' => $file,
                'url' => sp_media_library_public_url($file),
                'thumb' => $thumb_url,
                'type' => $is_img ? 'image' : 'file',
                'kind' => $is_img ? 'image' : 'file',
                'ext' => $ext,
                'size' => $fsize ? round($fsize / 1024, 1) . ' KB' : '0 KB',
            ];
        }
        usort($files, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $files;
    }
}

if (!function_exists('sp_media_library_upload')) {
    function sp_media_library_upload($file, $kind = 'all')
    {
        $kind = in_array($kind, ['all', 'image', 'file'], true) ? $kind : 'all';
        $upload_path = defined('GSDATAUPLOADPATH') ? GSDATAUPLOADPATH : GSROOTPATH . 'data/uploads/';
        $thumb_path = defined('GSTHUMBNAILPATH') ? GSTHUMBNAILPATH : GSROOTPATH . 'data/thumbs/';

        if (!is_array($file) || !isset($file['error'])) {
            return ['error' => 'Brak pliku.'];
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Nie udało się wgrać pliku.'];
        }
        $upload_image_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $upload_ext = sp_media_library_upload_extensions();
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $is_img = in_array($ext, $upload_image_ext, true);

        if ($kind === 'image' && !$is_img) {
            return ['error' => 'Dozwolone są tylko obrazy JPG, PNG, GIF i WebP.'];
        }
        if (!$is_img && !in_array($ext, $upload_ext, true)) {
            return ['error' => 'Niedozwolony typ pliku.'];
        }
        $max_size = $is_img ? 8 * 1024 * 1024 : 20 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $max_size) {
            return ['error' => 'Plik jest zbyt duży.'];
        }
        if ($is_img && function_exists('getimagesize') && !@getimagesize($file['tmp_name'])) {
            return ['error' => 'Plik nie jest prawidłowym obrazem.'];
        }
        if (function_exists('validate_safe_file') && !validate_safe_file($file['tmp_name'], $file['name'])) {
            return ['error' => 'Niedozwolony typ pliku.'];
        }

        $base = pathinfo((string) $file['name'], PATHINFO_FILENAME);
        if (function_exists('clean_img_name')) {
            if (function_exists('to7bit')) {
                $base = to7bit($base);
            }
            $base = clean_img_name($base);
        } else {
            $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $base);
        }
        $base = trim((string) $base, '-_');
        if ($base === '') {
            $base = 'file';
        }
        $final_name = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $upload_path . $final_name;
        if (!is_dir($upload_path)) {
            @mkdir($upload_path, 0755, true);
        }
        if (function_exists('sp_path_within') && !sp_path_within(dirname($target), $upload_path, true)) {
            return ['error' => 'Nieprawidłowa nazwa pliku.'];
        }
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['error' => 'Nie udało się zapisać pliku.'];
        }
        @chmod($target, 0644);
        if (function_exists('sp_log')) {
            sp_log('media_upload', $final_name);
        }

        $thumb_url = sp_media_library_public_url($final_name);
        if ($is_img && $ext !== 'svg') {
            wp_simple_generate_thumbnail($final_name, true);
            if (file_exists($thumb_path . 'thumbsm.' . $final_name)) {
                $thumb_url = rtrim((string) ($GLOBALS['SITEURL'] ?? ''), '/') . '/data/thumbs/' . rawurlencode('thumbsm.' . $final_name) . '?v=' . time();
            }
        }
        return [
            'file' => [
                'name' => $final_name,
                'url' => sp_media_library_public_url($final_name),
                'thumb' => $thumb_url,
                'type' => $is_img ? 'image' : 'file',
                'kind' => $is_img ? 'image' : 'file',
                'ext' => $ext,
                'size' => round((int) ($file['size'] ?? 0) / 1024, 1) . ' KB',
            ],
        ];
    }
}

# ==============================================================================
# 2. AJAX ENDPOINTS: LISTA, ASYNCHRONICZNY UPLOAD, ZAPIS JAKO EDYTOWANY PLIK
# ==============================================================================
if (!function_exists('wp_simple_media_ajax_handlers')) {
    function wp_simple_media_ajax_handlers() {
        if (is_frontend()) {
            if (isset($_GET['action']) && strpos((string) $_GET['action'], 'wp_simple_') === 0) {
                sp_json(['status' => 'error', 'message' => 'Authentication required'], 403);
            }
            return;
        }
        // A. Pobieranie listy plików JSON
        if (isset($_GET['action']) && $_GET['action'] === 'wp_simple_media_list') {
            sp_require_authenticated(true);
            @ini_set('display_errors', '0');
            while (ob_get_level()) { @ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');

            global $SITEURL;
            $site_url = !empty($SITEURL) ? rtrim($SITEURL, '/') : '';
            if (empty($site_url)) {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $site_url = $proto . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }

            $upload_path = defined('GSDATAUPLOADPATH') ? GSDATAUPLOADPATH : GSROOTPATH . 'data/uploads/';
            $thumb_path  = defined('GSTHUMBNAILPATH') ? GSTHUMBNAILPATH : GSROOTPATH . 'data/thumbs/';
            $force_all   = isset($_GET['regenerate_thumbs']);
            $files_list  = [];

            if (is_dir($upload_path)) {
                $scan = scandir($upload_path);
                foreach ($scan as $file) {
                    if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.php' || is_dir($upload_path . $file) || preg_match('/[<>"\']/', $file)) continue;

                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                    $public_url = $site_url . '/data/uploads/' . $file;
                    $mtime = @filemtime($upload_path . $file);
                    
                    $thumb_url = $public_url;
                    if ($is_img) {
                        $thumb_file = $thumb_path . 'thumbsm.' . $file;
                        // Wymuś odświeżenie jeśli miniatura nie istnieje, jest starsza niż oryginał lub podano flagę
                        if ($force_all || !file_exists($thumb_file) || filemtime($thumb_file) < $mtime) {
                            wp_simple_generate_thumbnail($file, true);
                        }
                        if (file_exists($thumb_file)) {
                            $thumb_url = $site_url . '/data/thumbs/thumbsm.' . $file . '?v=' . filemtime($thumb_file);
                        }
                    }

                    $fsize = @filesize($upload_path . $file);
                    $files_list[] = [
                        'name'  => $file,
                        'url'   => $public_url . '?v=' . $mtime,
                        'thumb' => $thumb_url,
                        'type'  => $is_img ? 'image' : 'file',
                        'ext'   => $ext,
                        'size'  => $fsize ? round($fsize / 1024, 1) . ' KB' : '0 KB'
                    ];
                }
            }
            echo json_encode(['files' => $files_list]);
            exit;
        }

        // B. Asynchroniczny upload
        if (isset($_GET['action']) && $_GET['action'] === 'wp_simple_async_upload') {
            @ini_set('display_errors', '0');
            while (ob_get_level()) { @ob_end_clean(); }
            sp_require_authenticated(true);
            if (!sp_verify_nonce('media_upload', $_POST['sp_nonce'] ?? '')) {
                sp_json(['status' => 'error', 'message' => 'Nieprawidłowy token bezpieczeństwa.'], 403);
            }
            if (!isset($_FILES['file'])) {
                sp_json(['status' => 'error', 'message' => 'Brak pliku'], 400);
            }

            $upload_path = defined('GSDATAUPLOADPATH') ? GSDATAUPLOADPATH : GSROOTPATH . 'data/uploads/';
            $thumb_path  = defined('GSTHUMBNAILPATH') ? GSTHUMBNAILPATH : GSROOTPATH . 'data/thumbs/';

            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024 || !in_array($ext, $allowed, true) || !@getimagesize($file['tmp_name'])) {
                sp_json(['status' => 'error', 'message' => 'Dozwolone są tylko obrazy JPG, PNG, GIF i WebP (do 8 MB).'], 400);
            }
            if (function_exists('validate_safe_file') && !validate_safe_file($file['tmp_name'], $file['name'])) {
                sp_json(['status' => 'error', 'message' => 'Niedozwolony typ pliku.'], 400);
            }
            $clean_base = function_exists('clean_img_name') ? clean_img_name(to7bit(pathinfo($file['name'], PATHINFO_FILENAME))) : preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
            $clean_base = $clean_base !== '' ? $clean_base : 'image';
            $final_name = $clean_base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target = $upload_path . $final_name;
            if (!sp_path_within(dirname($target), $upload_path, true)) {
                sp_json(['status' => 'error', 'message' => 'Nieprawidłowa nazwa pliku.'], 400);
            }

            if (move_uploaded_file($file['tmp_name'], $target)) {
                global $SITEURL;
                $site_url = !empty($SITEURL) ? rtrim($SITEURL, '/') : '';
                $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

                $thumb_url = $site_url . '/data/uploads/' . $final_name;
                if ($is_img) {
                    wp_simple_generate_thumbnail($final_name, true);
                    if (file_exists($thumb_path . 'thumbsm.' . $final_name)) {
                        $thumb_url = $site_url . '/data/thumbs/thumbsm.' . $final_name . '?v=' . time();
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'file' => [
                        'name'  => $final_name,
                        'url'   => $site_url . '/data/uploads/' . $final_name . '?v=' . time(),
                        'thumb' => $thumb_url,
                        'type'  => $is_img ? 'image' : 'file',
                        'ext'   => $ext
                    ]
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Błąd zapisu pliku']);
            }
            exit;
        }

        // C. Zapis zedytowanej grafiki jako NOWY EDYTOWANY PLIK
        if (isset($_GET['action']) && $_GET['action'] === 'wp_simple_save_crop') {
            @ini_set('display_errors', '0');
            while (ob_get_level()) { @ob_end_clean(); }
            sp_require_authenticated(true);
            if (!sp_verify_nonce('media_crop', $_SERVER['HTTP_X_SIMPLEPRESS_NONCE'] ?? ($_POST['sp_nonce'] ?? ''))) {
                sp_json(['status' => 'error', 'message' => 'Nieprawidłowy token bezpieczeństwa.'], 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['image']) || empty($data['filename'])) {
                sp_json(['status' => 'error', 'message' => 'Błędne dane'], 400);
            }

            $upload_path = defined('GSDATAUPLOADPATH') ? GSDATAUPLOADPATH : GSROOTPATH . 'data/uploads/';
            $original_name = sp_safe_basename($data['filename']);
            $ext = strtolower(pathinfo((string) $original_name, PATHINFO_EXTENSION));
            if (!$original_name || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                sp_json(['status' => 'error', 'message' => 'Nieprawidłowy plik.'], 400);
            }
            $source = $upload_path . $original_name;
            if (!is_file($source) || !sp_path_within($source, $upload_path, true)) {
                sp_json(['status' => 'error', 'message' => 'Plik źródłowy nie istnieje.'], 404);
            }
            $img_parts = explode(';base64,', $data['image']);
            if (count($img_parts) < 2) {
                sp_json(['status' => 'error', 'message' => 'Błędny format danych'], 400);
            }
            $binary = base64_decode($img_parts[1], true);
            $image = $binary !== false ? @imagecreatefromstring($binary) : false;
            if (!$image) {
                sp_json(['status' => 'error', 'message' => 'Błędne dane obrazu.'], 400);
            }
            $base_name = pathinfo($original_name, PATHINFO_FILENAME);
            $new_name = $base_name . '-edited-' . bin2hex(random_bytes(4)) . '.jpg';
            $target = $upload_path . $new_name;
            $saved = @imagejpeg($image, $target, 92);
            imagedestroy($image);
            if ($saved && sp_path_within($target, $upload_path, true)) {
                @chmod($target, 0644);
                wp_simple_generate_thumbnail($new_name, true);
                sp_log('media_crop', $new_name);
                sp_json(['status' => 'success', 'new_filename' => $new_name]);
            }
            sp_json(['status' => 'error', 'message' => 'Nie udało się zapisać nowego pliku'], 500);
        }
    }
    add_action('common', 'wp_simple_media_ajax_handlers');
    add_action('load-common', 'wp_simple_media_ajax_handlers');
}

# ==============================================================================
# 3. STYLE CSS DLA INTERFEJSU
# ==============================================================================
if (!function_exists('wp_simple_media_universal_styles')) {
    function wp_simple_media_universal_styles() {
        ?>
        <link rel="stylesheet" href="<?php echo sp_e(sp_plugin_url("lib/cropper/cropper.min.css")); ?>"/>
        <script src="<?php echo sp_e(sp_plugin_url("lib/cropper/cropper.min.js")); ?>"></script>
        <style>
            body#upload.wp-media-ready #imageTable,
            body#upload.wp-media-ready .edit-nav,
            body#upload.wp-media-ready .h5.clearfix,
            body#upload.wp-media-ready #file_load,
            body#upload.wp-media-ready .main > h3.floated,
            body#upload.wp-media-ready #sb_filesize { display: none !important; }

            .wp-media-card.wp-hidden {
                display: none !important;
            }

            .wp-sidebar-card {
                background: var(--wp-card-bg);
                border: 1px solid var(--wp-border, var(--wp-border));
                border-radius: 6px;
                padding: 16px;
                margin-bottom: 18px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }
            .wp-sidebar-dropzone {
                border: 2px dashed var(--wp-blue);
                border-radius: 6px;
                padding: 24px 12px;
                text-align: center;
                background: var(--wp-blue-soft);
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .wp-sidebar-dropzone:hover, .wp-sidebar-dropzone.dragover {
                border-color: var(--wp-blue-hover);
                background: var(--wp-blue-soft);
            }
            .wp-sidebar-dropzone .icon-wrap {
                display: flex;
                justify-content: center;
                margin-bottom: 8px;
                color: var(--wp-blue);
            }
            .wp-sidebar-dropzone p {
                margin: 0;
                font-size: 13px;
                font-weight: 600;
                color: var(--wp-text-strong);
            }
            .wp-sidebar-dropzone span {
                font-size: 11px;
                color: var(--wp-text-muted);
            }
            .wp-side-progress-wrap {
                height: 6px;
                background: var(--wp-border);
                border-radius: 3px;
                margin-top: 12px;
                overflow: hidden;
                display: none;
            }
            .wp-side-progress-bar {
                height: 100%;
                width: 0%;
                background: var(--wp-blue);
                transition: width 0.15s;
            }

            .wp-filter-group {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-top: 10px;
            }
            .wp-side-filter-btn {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 9px 12px;
                border-radius: 5px;
                color: var(--wp-text);
                text-decoration: none !important;
                font-size: 13px;
                font-weight: 500;
                background: var(--wp-surface-alt);
                border: 1px solid var(--wp-border);
                transition: all 0.15s ease;
                cursor: pointer;
            }
            .wp-side-filter-btn .btn-inner {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .wp-side-filter-btn svg {
                fill: currentColor;
                flex-shrink: 0;
            }
            .wp-side-filter-btn:hover {
                background: var(--wp-content-bg);
                border-color: var(--wp-border);
            }
            .wp-side-filter-btn.active {
                background: var(--wp-blue);
                border-color: var(--wp-blue);
                color: var(--wp-on-accent) !important;
                font-weight: 600;
            }
            .wp-side-filter-btn .count-badge {
                background: var(--wp-card-bg);
                color: var(--wp-text-muted);
                font-size: 11px;
                font-weight: 600;
                padding: 2px 7px;
                border-radius: 10px;
                border: 1px solid var(--wp-border);
            }
            .wp-side-filter-btn.active .count-badge {
                background: rgba(255,255,255,0.25);
                color: var(--wp-on-accent);
                border: none;
            }

            .wp-media-toolbar {
                display: flex !important;
                justify-content: space-between;
                align-items: center;
                background: var(--wp-card-bg) !important;
                padding: 12px 16px !important;
                border: 1px solid var(--wp-border, var(--wp-border)) !important;
                border-radius: 6px !important;
                margin-bottom: 18px !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
            }
            .wp-media-search-input {
                padding: 7px 12px !important;
                border: 1px solid var(--wp-input-border) !important;
                border-radius: 4px !important;
                width: 250px !important;
                font-size: 13px !important;
            }

            .wp-media-grid {
                display: grid !important;
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
                gap: 15px !important;
                margin-bottom: 25px !important;
            }
            .wp-media-card {
                background: var(--wp-card-bg);
                border: 1px solid var(--wp-border, var(--wp-border));
                border-radius: 6px;
                padding: 8px;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                box-shadow: 0 1px 2px rgba(0,0,0,0.03);
                transition: border-color 0.15s, box-shadow 0.15s;
                position: relative;
            }
            .wp-media-card:hover {
                border-color: var(--wp-blue);
                box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            }
            .wp-media-thumb-link {
                display: block;
                width: 100%;
                cursor: zoom-in;
                text-decoration: none;
            }
            .wp-media-thumb {
                width: 100% !important;
                height: 105px !important;
                object-fit: cover !important;
                border-radius: 4px !important;
                background: var(--wp-content-bg) !important;
                display: block;
                transition: opacity 0.15s;
            }
            .wp-media-thumb:hover {
                opacity: 0.92;
            }
            .wp-media-icon {
                width: 100% !important;
                height: 105px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: var(--wp-surface-alt) !important;
                font-weight: 700 !important;
                color: var(--wp-text-muted) !important;
                font-size: 14px !important;
                text-transform: uppercase !important;
                border-radius: 4px !important;
            }
            .wp-media-title {
                font-size: 11px !important;
                margin-top: 8px !important;
                word-break: break-all !important;
                line-height: 1.3 !important;
                color: var(--wp-text-strong) !important;
                max-height: 28px !important;
                overflow: hidden !important;
                width: 100%;
            }
            
            .wp-media-actions {
                margin-top: 8px !important;
                display: flex !important;
                justify-content: center;
                align-items: center;
                gap: 6px !important;
                width: 100%;
                padding-top: 6px;
                border-top: 1px solid var(--wp-border-light);
            }
            .wp-action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 26px;
                height: 26px;
                border-radius: 4px;
                text-decoration: none !important;
                color: var(--wp-text-muted) !important;
                background: var(--wp-surface-alt);
                border: 1px solid var(--wp-border);
                transition: all 0.15s ease;
                cursor: pointer;
            }
            .wp-action-btn svg {
                width: 14px;
                height: 14px;
                fill: currentColor;
            }
            .wp-action-btn:hover {
                background: var(--wp-blue);
                border-color: var(--wp-blue);
                color: var(--wp-on-accent) !important;
            }
            .wp-action-btn.delete-btn:hover {
                background: var(--wp-danger);
                border-color: var(--wp-danger);
                color: var(--wp-on-accent) !important;
            }

            #wp-crop-modal {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.85);
                z-index: 10000000;
                align-items: center;
                justify-content: center;
            }
            .wp-crop-box {
                background: var(--wp-card-bg);
                width: 900px;
                max-width: 95vw;
                height: 640px;
                max-height: 90vh;
                border-radius: 6px;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .wp-crop-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: var(--wp-surface-alt);
                padding: 10px 18px;
                border-bottom: 1px solid var(--wp-border);
            }
            .wp-crop-body {
                flex: 1;
                background: #111;
                overflow: hidden;
                position: relative;
                width: 100%;
                height: 480px;
                max-height: 70vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .wp-modal-tabs {
                display: flex;
                border-bottom: 1px solid var(--wp-border);
                background: var(--wp-surface-alt);
                padding: 0 15px;
            }
            .wp-modal-tab {
                padding: 12px 18px;
                border: none;
                background: none;
                cursor: pointer;
                font-size: 13px;
                font-weight: 600;
                color: var(--wp-text-muted);
                border-bottom: 3px solid transparent;
            }
            .wp-modal-tab.active {
                color: var(--wp-blue);
                border-bottom-color: var(--wp-blue);
                background: var(--wp-card-bg);
            }
            .wp-modal-dropzone {
                border: 2px dashed var(--wp-blue);
                background: var(--wp-blue-soft);
                padding: 60px 20px;
                text-align: center;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s;
            }
            .wp-modal-dropzone:hover {
                background: var(--wp-blue-soft);
            }
            .wp-modal-item {
                border: 2px solid transparent !important;
                border-radius: 4px !important;
                cursor: pointer !important;
                padding: 6px !important;
                text-align: center !important;
                background: var(--wp-card-bg) !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
            }
            .wp-modal-item:hover { border-color: var(--wp-blue) !important; }
            .wp-modal-item.selected { border-color: var(--wp-blue) !important; background: var(--wp-blue-soft) !important; }
            .wp-modal-item img { width: 100% !important; height: 80px !important; object-fit: cover !important; border-radius: 3px !important; }

            /* ==============================================================
               BIBLIOTEKA MEDIÓW — WIDOK W STYLU WORDPRESSA
               ============================================================== */
            #wp-media-library {
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .wp-media-library-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 16px !important;
                flex-wrap: wrap !important;
                margin: 0 0 16px 0 !important;
            }
            .wp-media-library-header h1 {
                font-size: 23px !important;
                font-weight: 400 !important;
                line-height: 1.3 !important;
                margin: 0 !important;
                padding: 0 !important;
                color: var(--wp-text) !important;
            }
            .wp-media-library-header .button {
                flex-shrink: 0;
            }

            .wp-media-toolbar {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 12px !important;
                flex-wrap: wrap !important;
                background: var(--wp-card-bg) !important;
                border: 1px solid var(--wp-border) !important;
                border-radius: 3px !important;
                box-shadow: none !important;
                padding: 8px 12px !important;
                margin: 0 0 16px 0 !important;
            }
            .wp-media-search-wrap {
                position: relative !important;
                display: flex !important;
                align-items: center !important;
                flex: 1 1 260px !important;
                max-width: 420px !important;
                min-width: 200px !important;
            }
            .wp-media-search-wrap .dashicons {
                position: absolute !important;
                left: 9px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                width: 18px !important;
                height: 18px !important;
                font-size: 18px !important;
                color: var(--wp-text-faint) !important;
                pointer-events: none !important;
            }
            .wp-media-search-input {
                width: 100% !important;
                height: 32px !important;
                padding: 4px 10px 4px 34px !important;
                border: 1px solid var(--wp-input-border) !important;
                border-radius: 4px !important;
                background: var(--wp-input-bg) !important;
                color: var(--wp-text) !important;
                font-size: 13px !important;
                line-height: 1.4 !important;
                box-sizing: border-box !important;
                -webkit-appearance: none !important;
                appearance: none !important;
            }
            .wp-media-search-input:focus {
                border-color: var(--wp-blue) !important;
                box-shadow: 0 0 0 1px var(--wp-blue) !important;
                outline: 2px solid transparent !important;
            }
            .wp-media-search-input::placeholder {
                color: var(--wp-text-faint) !important;
            }
            .wp-media-toolbar-right {
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
                flex-wrap: wrap !important;
            }
            .wp-media-filter {
                min-width: 160px !important;
                height: 32px !important;
                min-height: 32px !important;
                padding: 4px 28px 4px 8px !important;
                font-size: 13px !important;
            }
            .wp-media-count {
                color: var(--wp-text-muted) !important;
                font-size: 12px !important;
                white-space: nowrap !important;
            }
            .wp-media-count strong {
                color: var(--wp-text) !important;
            }

            #wp-media-library .wp-media-grid {
                display: grid !important;
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)) !important;
                gap: 16px !important;
                margin: 0 0 24px 0 !important;
            }
            #wp-media-library .wp-media-card {
                background: var(--wp-card-bg) !important;
                border: 1px solid var(--wp-border) !important;
                border-radius: 3px !important;
                padding: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                text-align: left !important;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04) !important;
                transition: border-color 0.15s, box-shadow 0.15s !important;
                position: relative !important;
                overflow: hidden !important;
            }
            #wp-media-library .wp-media-card:hover {
                border-color: var(--wp-blue) !important;
                box-shadow: 0 2px 6px rgba(0,0,0,0.1) !important;
            }
            #wp-media-library .wp-media-thumb-link {
                display: block !important;
                width: 100% !important;
                text-decoration: none !important;
                background: var(--wp-surface-alt) !important;
            }
            #wp-media-library .wp-media-thumb {
                width: 100% !important;
                height: 150px !important;
                object-fit: cover !important;
                border-radius: 0 !important;
                background: var(--wp-surface-alt) !important;
                display: block !important;
            }
            #wp-media-library .wp-media-icon {
                width: 100% !important;
                height: 150px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: var(--wp-surface-alt) !important;
                font-weight: 700 !important;
                color: var(--wp-text-muted) !important;
                font-size: 14px !important;
                text-transform: uppercase !important;
                border-radius: 0 !important;
            }
            #wp-media-library .wp-media-title {
                font-size: 12px !important;
                margin: 0 !important;
                padding: 8px 10px !important;
                word-break: break-word !important;
                line-height: 1.35 !important;
                color: var(--wp-text) !important;
                max-height: 42px !important;
                overflow: hidden !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            #wp-media-library .wp-media-actions {
                margin: 0 !important;
                padding: 6px 8px !important;
                display: flex !important;
                justify-content: flex-start !important;
                align-items: center !important;
                gap: 6px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                border-top: 1px solid var(--wp-border-light) !important;
            }
            #wp-media-library .wp-action-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 28px !important;
                height: 28px !important;
                border-radius: 3px !important;
                text-decoration: none !important;
                color: var(--wp-text-muted) !important;
                background: transparent !important;
                border: 1px solid transparent !important;
                cursor: pointer !important;
            }
            #wp-media-library .wp-action-btn svg {
                width: 15px !important;
                height: 15px !important;
                fill: currentColor !important;
            }
            #wp-media-library .wp-action-btn:hover {
                background: var(--wp-surface-alt) !important;
                border-color: var(--wp-border) !important;
                color: var(--wp-blue) !important;
            }
            #wp-media-library .wp-action-btn.delete-btn:hover {
                background: var(--wp-danger) !important;
                border-color: var(--wp-danger) !important;
                color: var(--wp-on-accent) !important;
            }
            .wp-media-empty {
                background: var(--wp-card-bg) !important;
                border: 1px dashed var(--wp-border) !important;
                border-radius: 3px !important;
                padding: 32px 20px !important;
                text-align: center !important;
                color: var(--wp-text-muted) !important;
                font-size: 13px !important;
                margin-bottom: 24px !important;
            }

            @media screen and (max-width: 782px) {
                .wp-media-search-wrap {
                    max-width: none !important;
                    flex-basis: 100% !important;
                }
                .wp-media-toolbar-right {
                    width: 100% !important;
                    justify-content: space-between !important;
                }
                #wp-media-library .wp-media-grid {
                    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)) !important;
                    gap: 12px !important;
                }
            }
        </style>
        <?php
    }
    add_action('header', 'wp_simple_media_universal_styles');
}

# ==============================================================================
# 4. WIDOK UPLOAD.PHP: SIDEBAR, SIATKA Z IKONAMI SVG I CROPPER
# ==============================================================================
if (!function_exists('wp_simple_render_upload_page_script')) {
    function wp_simple_render_upload_page_script() {
        if (function_exists('get_filename_id') && get_filename_id() !== 'upload') { return; }
        $media_labels = [
            'library' => wp_i18n('MEDIA_LIBRARY', 'Biblioteka mediów'),
            'add' => wp_i18n('MEDIA_ADD', 'Dodaj media'),
            'search' => wp_i18n('MEDIA_SEARCH', 'Szukaj mediów...'),
            'filter' => wp_i18n('MEDIA_FILTER', 'Filtruj widok'),
            'all' => wp_i18n('MEDIA_ALL', 'Wszystkie media'),
            'images' => wp_i18n('MEDIA_IMAGES', 'Zdjęcia i grafiki'),
            'files' => wp_i18n('MEDIA_FILES', 'Dokumenty i inne'),
            'visible' => wp_i18n('MEDIA_VISIBLE', 'Widoczne:'),
            'empty' => wp_i18n('MEDIA_EMPTY', 'Nie znaleziono mediów.'),
            'drop' => wp_i18n('MEDIA_DROP', 'Upuść pliki tutaj'),
            'or_click' => wp_i18n('MEDIA_OR_CLICK', 'lub kliknij, aby wybrać'),
        ];
        ?>
        <div id="wp-crop-modal">
            <div class="wp-crop-box">
                <div class="wp-crop-toolbar">
                    <strong style="font-size:14px;">Edycja kadr: <span id="wp-crop-filename" style="font-weight:normal; color:var(--wp-text-muted);"></span></strong>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="button" id="crop-rotate-left" title="Obróć o 90° w lewo">↺ 90°</button>
                        <button type="button" class="button" id="crop-rotate-right" title="Obróć o 90° w prawo">↻ 90°</button>
                        <button type="button" class="button" id="crop-flip-x" title="Odbicie lustrzane">↔ Odbij</button>
                        <select id="crop-aspect-ratio" class="button" style="height:30px;">
                            <option value="NaN">Proporcja: Dowolna</option>
                            <option value="1">1:1 (Kwadrat)</option>
                            <option value="1.77777777778">16:9 (Panorama)</option>
                            <option value="1.33333333333">4:3 (Standard)</option>
                        </select>
                    </div>
                </div>
                <div class="wp-crop-body" id="wp-crop-body-container"></div>
                <div style="padding: 12px 18px; background: var(--wp-card-bg); border-top: 1px solid var(--wp-border); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="button" id="wp-crop-cancel">Anuluj</button>
                    <button type="button" class="button button-primary" id="wp-crop-save">Zapisz jako nowy plik</button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var icons = {
                cloud: '<svg viewBox="0 0 24 24" width="36" height="36"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>',
                folder: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>',
                image: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>',
                file: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>',
                view: '<svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>',
                crop: '<svg viewBox="0 0 24 24"><path d="M17 15h2V7c0-1.1-.9-2-2-2H9v2h8v8zM7 17V1H5v4H1v2h4v10c0 1.1.9 2 2 2h10v4h2v-4h4v-2H7z"/></svg>',
                download: '<svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>',
                trash: '<svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>',
                uploadModal: '<svg viewBox="0 0 24 24" width="46" height="46" fill="var(--wp-blue)"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>'
            };
            var mLabels = <?php echo json_encode($media_labels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            function buildUploadUI() {
                var table = document.getElementById("imageTable");
                var sidebar = document.getElementById("sidebar");
                if (!table || !sidebar) return;
                if (table.dataset.wpBuilt === "true") return;

                table.dataset.wpBuilt = "true";

                var rows = table.querySelectorAll("tr");
                var files = [];
                var countImages = 0;
                var countDocs = 0;

                rows.forEach(function(r) {
                    var link = r.querySelector("a.primarylink");
                    if (!link) return;

                    var name = link.textContent.trim();
                    if (!name || (link.getAttribute("href") && link.getAttribute("href").indexOf("deletefile") !== -1)) return;

                    var ext = name.split(".").pop().toLowerCase();
                    var isImg = ["jpg", "jpeg", "png", "gif", "webp", "svg"].indexOf(ext) !== -1;
                    
                    if (isImg) countImages++; else countDocs++;

                    var directFileUrl = "../data/uploads/" + encodeURIComponent(name);
                    // Cache-busting dla miniatury
                    var thumbSrc = isImg ? ("../data/thumbs/thumbsm." + encodeURIComponent(name) + "?v=" + Date.now()) : directFileUrl;

                    var delLink = r.querySelector("a.delconfirm, a.delete, a[href*='deletefile']");
                    var delHref = delLink ? delLink.getAttribute("href") : "";

                    files.push({ 
                        name: name, 
                        url: directFileUrl, 
                        thumb: thumbSrc, 
                        ext: ext, 
                        isImg: isImg, 
                        delHref: delHref 
                    });
                });

                // Panel boczny (zachowaj istniejące podmenu .snav)
                var preservedSnav = sidebar.querySelector("ul.snav");
                sidebar.innerHTML = `
                    <div class="wp-sidebar-card">
                        <h4 style="margin:0 0 10px 0; font-size:14px; font-weight:600; color:var(--wp-text-strong);">${mLabels.add}</h4>
                        <div class="wp-sidebar-dropzone" id="wp-side-dropzone">
                            <input type="file" id="wp-side-file-input" multiple style="display:none;">
                            <div class="icon-wrap">${icons.cloud}</div>
                            <p>${mLabels.drop}</p>
                            <span>${mLabels.or_click}</span>
                            <div class="wp-side-progress-wrap" id="wp-side-pwrap">
                                <div class="wp-side-progress-bar" id="wp-side-pbar"></div>
                            </div>
                        </div>
                    </div>

                    <div class="wp-sidebar-card">
                        <h4 style="margin:0 0 8px 0; font-size:13px; font-weight:600; color:var(--wp-text-strong); text-transform:uppercase; letter-spacing:0.5px;">${mLabels.filter}</h4>
                        <div class="wp-filter-group">
                            <a class="wp-side-filter-btn active" data-type="all">
                                <span class="btn-inner">${icons.folder} ${mLabels.all}</span>
                                <span class="count-badge">${files.length}</span>
                            </a>
                            <a class="wp-side-filter-btn" data-type="image">
                                <span class="btn-inner">${icons.image} ${mLabels.images}</span>
                                <span class="count-badge">${countImages}</span>
                            </a>
                            <a class="wp-side-filter-btn" data-type="file">
                                <span class="btn-inner">${icons.file} ${mLabels.files}</span>
                                <span class="count-badge">${countDocs}</span>
                            </a>
                        </div>
                    </div>
                `;
                if (preservedSnav) {
                    sidebar.insertBefore(preservedSnav, sidebar.firstChild);
                }

                var dropzone = document.getElementById("wp-side-dropzone");
                var fileInput = document.getElementById("wp-side-file-input");

                dropzone.addEventListener("click", function() { fileInput.click(); });
                dropzone.addEventListener("dragover", function(e) { e.preventDefault(); dropzone.classList.add("dragover"); });
                dropzone.addEventListener("dragleave", function() { dropzone.classList.remove("dragover"); });
                dropzone.addEventListener("drop", function(e) {
                    e.preventDefault();
                    dropzone.classList.remove("dragover");
                    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                        uploadSidebarFiles(e.dataTransfer.files);
                    }
                });

                fileInput.addEventListener("change", function() {
                    if (this.files && this.files.length > 0) uploadSidebarFiles(this.files);
                });

                function uploadSidebarFiles(filesToUp) {
                    var pWrap = document.getElementById("wp-side-pwrap");
                    var pBar = document.getElementById("wp-side-pbar");
                    pWrap.style.display = "block";
                    pBar.style.width = "0%";

                    var formData = new FormData();
                    for (var i = 0; i < filesToUp.length; i++) {
                        formData.append("file", filesToUp[i]);
                    }
                    formData.append("sp_nonce", "<?php echo sp_e(sp_nonce('media_upload')); ?>");

                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "upload.php?action=wp_simple_async_upload");

                    xhr.upload.onprogress = function(e) {
                        if (e.lengthComputable) {
                            pBar.style.width = ((e.loaded / e.total) * 100) + "%";
                        }
                    };

                    xhr.onload = function() {
                        pBar.style.width = "100%";
                        setTimeout(function() { window.location.reload(); }, 300);
                    };

                    xhr.onerror = function() {
                        alert("Błąd podczas wgrywania pliku.");
                        pWrap.style.display = "none";
                    };

                    xhr.send(formData);
                }

                // Nagłówek, toolbar i siatka w stylu WordPressa
                var library = document.createElement("div");
                library.id = "wp-media-library";
                library.innerHTML = `
                    <div class="wp-media-library-header">
                        <h1>${mLabels.library}</h1>
                        <button type="button" class="button button-primary" id="wp-media-add-btn">${mLabels.add}</button>
                    </div>
                    <div class="wp-media-toolbar">
                        <div class="wp-media-search-wrap">
                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                            <input type="search" id="wp-media-search" class="wp-media-search-input" placeholder="${mLabels.search}" aria-label="${mLabels.search}">
                        </div>
                        <div class="wp-media-toolbar-right">
                            <select id="wp-media-filter" class="wp-media-filter" aria-label="${mLabels.filter}">
                                <option value="all">${mLabels.all}</option>
                                <option value="image">${mLabels.images}</option>
                                <option value="file">${mLabels.files}</option>
                            </select>
                            <span class="wp-media-count">${mLabels.visible} <strong id="wp-filter-count">${files.length}</strong></span>
                        </div>
                    </div>
                    <div class="wp-media-empty" id="wp-media-empty" style="display:none">${mLabels.empty}</div>
                `;

                var grid = document.createElement("div");
                grid.className = "wp-media-grid";

                files.forEach(function(f) {
                    var card = document.createElement("div");
                    card.className = "wp-media-card";
                    card.dataset.name = String(f.name).toLowerCase();
                    card.dataset.type = f.isImg ? "image" : "file";

                    var previewHtml = f.isImg
                        ? `<a href="${f.url}" target="_blank" class="wp-media-thumb-link" title="Powiększ / podgląd pełnego zdjęcia">
                             <img src="${f.thumb}" class="wp-media-thumb" loading="lazy" alt="${f.name}" onerror="this.src='${f.url}'">
                           </a>`
                        : `<a href="${f.url}" target="_blank" class="wp-media-thumb-link" title="Otwórz plik">
                             <div class="wp-media-icon">${f.ext}</div>
                           </a>`;

                    var viewBtn = `<a href="${f.url}" target="_blank" class="wp-action-btn" title="Podgląd">${icons.view}</a>`;
                    var editBtn = f.isImg ? `<a class="wp-action-btn wp-crop-btn" data-url="${f.url}" data-name="${f.name}" title="Przytnij / Edytuj">${icons.crop}</a>` : '';
                    var dlBtn = `<a href="${f.url}" download="${f.name}" class="wp-action-btn" title="Pobierz">${icons.download}</a>`;
                    var delBtn = f.delHref ? `<a href="${f.delHref}" class="wp-action-btn delete-btn" title="Usuń plik" onclick="return confirm('Czy na pewno usunąć plik ${f.name}?');">${icons.trash}</a>` : '';

                    card.innerHTML = `
                        ${previewHtml}
                        <div class="wp-media-title" title="${f.name}">${f.name}</div>
                        <div class="wp-media-actions">
                            ${viewBtn}
                            ${editBtn}
                            ${dlBtn}
                            ${delBtn}
                        </div>
                    `;
                    grid.appendChild(card);
                });

                library.appendChild(grid);
                var libraryHost = document.querySelector("#maincontent .main") || document.querySelector("#maincontent");
                if (libraryHost) {
                    libraryHost.insertBefore(library, libraryHost.firstChild);
                }
                document.body.classList.add("wp-media-ready");

                var addMediaBtn = document.getElementById("wp-media-add-btn");
                if (addMediaBtn && fileInput) {
                    addMediaBtn.addEventListener("click", function() { fileInput.click(); });
                }

                var activeFilterType = "all";

                function filterMedia() {
                    var search = document.getElementById("wp-media-search");
                    var empty = document.getElementById("wp-media-empty");
                    var q = search ? search.value.toLowerCase().trim() : "";
                    var visibleCount = 0;

                    grid.querySelectorAll(".wp-media-card").forEach(function(card) {
                        var matchesName = !q || card.dataset.name.indexOf(q) !== -1;
                        var matchesType = (activeFilterType === "all") || (card.dataset.type === activeFilterType);

                        if (matchesName && matchesType) {
                            card.classList.remove("wp-hidden");
                            visibleCount++;
                        } else {
                            card.classList.add("wp-hidden");
                        }
                    });

                    var count = document.getElementById("wp-filter-count");
                    if (count) count.textContent = visibleCount;
                    if (empty) empty.style.display = visibleCount === 0 ? "block" : "none";
                }

                function setFilter(type) {
                    activeFilterType = type;
                    document.querySelectorAll(".wp-side-filter-btn").forEach(function(b) {
                        b.classList.toggle("active", b.dataset.type === type);
                    });
                    var filterSelect = document.getElementById("wp-media-filter");
                    if (filterSelect) filterSelect.value = type;
                    filterMedia();
                }

                var searchInput = document.getElementById("wp-media-search");
                if (searchInput) searchInput.addEventListener("input", filterMedia);
                var filterSelect = document.getElementById("wp-media-filter");
                if (filterSelect) filterSelect.addEventListener("change", function() { setFilter(this.value); });

                document.querySelectorAll(".wp-side-filter-btn").forEach(function(btn) {
                    btn.addEventListener("click", function(e) {
                        e.preventDefault();
                        setFilter(this.dataset.type || "all");
                    });
                });

                filterMedia();

                // Obsługa Cropper.js
                var cropper = null;
                var currentEditingFile = "";
                var cropModal = document.getElementById("wp-crop-modal");
                var cropContainer = document.getElementById("wp-crop-body-container");

                document.querySelectorAll(".wp-crop-btn").forEach(function(btn) {
                    btn.addEventListener("click", function() {
                        currentEditingFile = this.dataset.name;
                        document.getElementById("wp-crop-filename").textContent = currentEditingFile;
                        
                        var targetUrl = "../data/uploads/" + encodeURIComponent(currentEditingFile);

                        if (cropper) {
                            cropper.destroy();
                            cropper = null;
                        }

                        cropContainer.innerHTML = '';
                        var newImg = document.createElement("img");
                        newImg.style.display = "block";
                        newImg.style.maxWidth = "100%";
                        newImg.style.maxHeight = "460px";
                        cropContainer.appendChild(newImg);

                        cropModal.style.display = "flex";

                        newImg.onload = function() {
                            cropper = new Cropper(newImg, {
                                viewMode: 1,
                                dragMode: 'crop',
                                autoCropArea: 0.85,
                                restore: false,
                                guides: true,
                                center: true,
                                highlight: false,
                                cropBoxMovable: true,
                                cropBoxResizable: true,
                                toggleDragModeOnDblclick: false,
                                responsive: true
                            });
                        };

                        newImg.src = targetUrl + "?t=" + Date.now();
                    });
                });

                document.getElementById("wp-crop-cancel").addEventListener("click", function() {
                    cropModal.style.display = "none";
                    if (cropper) { cropper.destroy(); cropper = null; }
                    cropContainer.innerHTML = '';
                });

                document.getElementById("crop-rotate-left").addEventListener("click", function() { if (cropper) cropper.rotate(-90); });
                document.getElementById("crop-rotate-right").addEventListener("click", function() { if (cropper) cropper.rotate(90); });

                var flipX = false;
                document.getElementById("crop-flip-x").addEventListener("click", function() {
                    if (cropper) { flipX = !flipX; cropper.scaleX(flipX ? -1 : 1); }
                });

                document.getElementById("crop-aspect-ratio").addEventListener("change", function() {
                    if (cropper) cropper.setAspectRatio(parseFloat(this.value));
                });

                document.getElementById("wp-crop-save").addEventListener("click", function() {
                    if (!cropper) return;
                    var saveBtn = this;
                    saveBtn.disabled = true;
                    saveBtn.textContent = "Zapisywanie nowej wersji...";

                    try {
                        var canvas = cropper.getCroppedCanvas();
                        if (!canvas) {
                            alert("Nie udało się wygenerować wycinka obrazu.");
                            saveBtn.disabled = false;
                            saveBtn.textContent = "Zapisz jako nowy plik";
                            return;
                        }
                        var base64Data = canvas.toDataURL("image/jpeg", 0.93);

                        fetch("upload.php?action=wp_simple_save_crop", {
                            method: "POST",
                            headers: { "Content-Type": "application/json", "X-SimplePress-Nonce": "<?php echo sp_e(sp_nonce('media_crop')); ?>" },
                            body: JSON.stringify({ filename: currentEditingFile, image: base64Data })
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === "success") { 
                                window.location.reload(); 
                            } else { 
                                alert("Błąd: " + (res.message || "Niepowodzenie")); 
                                saveBtn.disabled = false; 
                                saveBtn.textContent = "Zapisz jako nowy plik"; 
                            }
                        })
                        .catch(() => { 
                            alert("Błąd sieciowy przy zapisie."); 
                            saveBtn.disabled = false; 
                            saveBtn.textContent = "Zapisz jako nowy plik"; 
                        });
                    } catch(err) {
                        alert("Błąd: " + err.message);
                        saveBtn.disabled = false;
                        saveBtn.textContent = "Zapisz jako nowy plik";
                    }
                });
            }

            buildUploadUI();
            document.addEventListener("DOMContentLoaded", buildUploadUI);
            window.addEventListener("load", buildUploadUI);
            setTimeout(buildUploadUI, 200);
            setTimeout(buildUploadUI, 600);
        })();
        </script>
        <?php
    }
    add_action('footer', 'wp_simple_render_upload_page_script');
}

# ==============================================================================
# 5. MODAL W EDIT.PHP
# ==============================================================================
if (!function_exists('wp_simple_render_edit_media_modal')) {
    function wp_simple_render_edit_media_modal() {
        ?>
        <div id="wp-media-btn-container" style="margin: 12px 0; clear: both;">
            <button type="button" id="wp-btn-open-media" class="button" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; cursor:pointer;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                Dodaj medium
            </button>
        </div>

        <div id="wp-media-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.75); z-index:9999999; align-items:center; justify-content:center;">
            <div style="background: var(--wp-card-bg); width:880px; max-width:95%; height:620px; max-height:90vh; border-radius:6px; box-shadow:0 5px 25px rgba(0,0,0,0.3); display:flex; flex-direction:column; overflow:hidden;">
                
                <div style="padding:14px 20px; border-bottom: 1px solid var(--wp-border); display:flex; justify-content:space-between; align-items:center; background:var(--wp-surface-alt);">
                    <h3 style="margin:0 !important; font-size:16px; font-weight:600; color:var(--wp-text-strong);">Wybierz lub wgraj medium</h3>
                    <button type="button" id="wp-media-close" style="border:none; background:none; font-size:24px; line-height:1; cursor:pointer; color:var(--wp-text-muted);">&times;</button>
                </div>

                <div class="wp-modal-tabs">
                    <button type="button" class="wp-modal-tab active" id="tab-btn-library">Biblioteka mediów</button>
                    <button type="button" class="wp-modal-tab" id="tab-btn-upload">Wgraj plik</button>
                </div>

                <div id="wp-tab-content-library" style="flex:1; overflow-y:auto; padding:18px 20px; background:var(--wp-content-bg);">
                    <div id="wp-modal-list" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(115px, 1fr)); gap:12px;">
                        Wczytywanie...
                    </div>
                </div>

                <div id="wp-tab-content-upload" style="flex:1; overflow-y:auto; padding:30px; background: var(--wp-card-bg); display:none; flex-direction:column; align-items:center; justify-content:center;">
                    <div class="wp-modal-dropzone" id="wp-modal-dropzone" style="width:100%; max-width:480px;">
                        <input type="file" id="wp-modal-file-input" style="display:none;">
                        <div style="display:flex; justify-content:center; margin-bottom:10px;">
                            <svg viewBox="0 0 24 24" width="46" height="46" fill="var(--wp-blue)"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
                        </div>
                        <h4 style="margin:0 0 6px 0; font-size:15px; color:var(--wp-text-strong);">Upuść plik tutaj</h4>
                        <p style="margin:0; font-size:12px; color:var(--wp-text-muted);">lub kliknij, aby wybrać z dysku</p>
                        <div class="wp-side-progress-wrap" id="wp-modal-pwrap" style="max-width:280px; margin:15px auto 0;">
                            <div class="wp-side-progress-bar" id="wp-modal-pbar"></div>
                        </div>
                    </div>
                </div>

                <div style="padding:12px 20px; border-top: 1px solid var(--wp-border); background: var(--wp-card-bg); display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" id="wp-media-cancel" class="button" style="padding:6px 14px; cursor:pointer;">Anuluj</button>
                    <button type="button" id="wp-media-insert" class="button button-primary" style="padding:6px 14px; cursor:pointer;" disabled>Wstaw do treści</button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var modal = document.getElementById("wp-media-modal");
            var openBtn = document.getElementById("wp-btn-open-media");
            var closeBtn = document.getElementById("wp-media-close");
            var cancelBtn = document.getElementById("wp-media-cancel");
            var insertBtn = document.getElementById("wp-media-insert");
            var modalList = document.getElementById("wp-modal-list");
            var selectedFile = null;

            var tabLib = document.getElementById("tab-btn-library");
            var tabUp = document.getElementById("tab-btn-upload");
            var viewLib = document.getElementById("wp-tab-content-library");
            var viewUp = document.getElementById("wp-tab-content-upload");

            function positionButton() {
                var btnContainer = document.getElementById("wp-media-btn-container");
                var editorBox = document.querySelector(".tox-tinymce") || document.getElementById("post-content");
                if (btnContainer && editorBox && editorBox.parentNode) {
                    editorBox.parentNode.insertBefore(btnContainer, editorBox);
                }
            }
            setTimeout(positionButton, 200);

            tabLib.addEventListener("click", function() {
                tabLib.classList.add("active");
                tabUp.classList.remove("active");
                viewLib.style.display = "block";
                viewUp.style.display = "none";
            });

            tabUp.addEventListener("click", function() {
                tabUp.classList.add("active");
                tabLib.classList.remove("active");
                viewUp.style.display = "flex";
                viewLib.style.display = "none";
            });

            function loadLibrary(autoSelectName) {
                modalList.innerHTML = "<p style='grid-column: 1/-1; text-align:center;'>Wczytywanie biblioteki...</p>";
                insertBtn.disabled = true;

                fetch("upload.php?action=wp_simple_media_list")
                    .then(r => r.json())
                    .then(data => {
                        modalList.innerHTML = "";
                        if (!data.files || data.files.length === 0) {
                            modalList.innerHTML = "<p style='grid-column: 1/-1; text-align:center;'>Brak plików w uploads.</p>";
                            return;
                        }

                        data.files.forEach(file => {
                            var item = document.createElement("div");
                            item.className = "wp-modal-item";

                            var preview = file.type === "image"
                                ? `<img src="${file.thumb}" style="width:100%; height:80px; object-fit:cover; border-radius:3px;" onerror="this.src='${file.url}'">`
                                : `<div style="height:80px; background:var(--wp-content-bg); display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:13px; color:var(--wp-text-muted);">${file.ext.toUpperCase()}</div>`;

                            item.innerHTML = `
                                ${preview}
                                <div style="font-size:11px; margin-top:6px; word-break:break-all; line-height:1.2; max-height:26px; overflow:hidden;" title="${file.name}">${file.name}</div>
                            `;

                            function selectThis() {
                                modalList.querySelectorAll(".wp-modal-item").forEach(el => el.classList.remove("selected"));
                                item.classList.add("selected");
                                selectedFile = file;
                                insertBtn.disabled = false;
                            }

                            item.addEventListener("click", selectThis);
                            if (autoSelectName && file.name === autoSelectName) selectThis();

                            modalList.appendChild(item);
                        });
                    })
                    .catch(() => {
                        modalList.innerHTML = "<p style='grid-column: 1/-1; text-align:center; color:var(--wp-danger);'>Błąd pobierania listy.</p>";
                    });
            }

            var modalDropzone = document.getElementById("wp-modal-dropzone");
            var modalFileInput = document.getElementById("wp-modal-file-input");

            modalDropzone.addEventListener("click", function() { modalFileInput.click(); });
            modalDropzone.addEventListener("dragover", function(e) { e.preventDefault(); });
            modalDropzone.addEventListener("drop", function(e) {
                e.preventDefault();
                if (e.dataTransfer.files.length > 0) uploadModalFile(e.dataTransfer.files[0]);
            });

            modalFileInput.addEventListener("change", function() {
                if (this.files.length > 0) uploadModalFile(this.files[0]);
            });

            function uploadModalFile(file) {
                var pWrap = document.getElementById("wp-modal-pwrap");
                var pBar = document.getElementById("wp-modal-pbar");
                pWrap.style.display = "block";
                pBar.style.width = "0%";

                var formData = new FormData();
                formData.append("file", file);
                formData.append("sp_nonce", "<?php echo sp_e(sp_nonce('media_upload')); ?>");

                var xhr = new XMLHttpRequest();
                xhr.open("POST", "upload.php?action=wp_simple_async_upload");
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) pBar.style.width = ((e.loaded / e.total) * 100) + "%";
                };
                xhr.onload = function() {
                    pBar.style.width = "100%";
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.status === "success") {
                            pWrap.style.display = "none";
                            tabLib.click();
                            loadLibrary(res.file.name);
                        } else {
                            alert("Błąd: " + res.message);
                            pWrap.style.display = "none";
                        }
                    } catch(err) {
                        alert("Błąd zapisu");
                        pWrap.style.display = "none";
                    }
                };
                xhr.send(formData);
            }

            if (openBtn) {
                openBtn.addEventListener("click", function() {
                    modal.style.display = "flex";
                    selectedFile = null;
                    tabLib.click();
                    loadLibrary();
                });
            }

            if (closeBtn) closeBtn.addEventListener("click", function() { modal.style.display = "none"; });
            if (cancelBtn) cancelBtn.addEventListener("click", function() { modal.style.display = "none"; });

            if (insertBtn) {
                insertBtn.addEventListener("click", function() {
                    if (!selectedFile) return;

                    var html = selectedFile.type === "image"
                        ? `<img src="${selectedFile.url}" alt="${selectedFile.name}" />`
                        : `<a href="${selectedFile.url}" target="_blank">${selectedFile.name}</a>`;

                    var postContent = document.getElementById("post-content");

                    if (typeof tinymce !== "undefined" && tinymce.get("post-content")) {
                        tinymce.get("post-content").insertContent(html);
                    } else if (typeof CKEDITOR !== "undefined" && CKEDITOR.instances["post-content"]) {
                        CKEDITOR.instances["post-content"].insertHtml(html);
                    } else if (postContent) {
                        postContent.value += html;
                    }

                    modal.style.display = "none";
                });
            }
        })();
        </script>
        <?php
    }
    add_action('edit-extras', 'wp_simple_render_edit_media_modal');
}