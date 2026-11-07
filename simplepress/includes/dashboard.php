<?php
if (!defined('IN_GS')) { die('You cannot load this file directly!'); }

# ==============================================================================
# 1. PRZETWARZANIE SZYBKIEGO SZKICU (QUICK DRAFT)
# ==============================================================================
function wp_simple_process_quick_draft() {
    if (!isset($_POST['wp_quick_draft'])) {
        return;
    }
    sp_require_post('quick_draft');
    $title = trim(strip_tags((string) ($_POST['post_title'] ?? '')));
    $content = (string) ($_POST['post_content'] ?? '');
    if ($title === '') {
        return;
    }
    if (function_exists('xss_clean')) {
        $content = xss_clean($content);
    }
    $base = function_exists('clean_url') ? clean_url(to7bit($title)) : preg_replace('/[^a-z0-9_-]/', '-', strtolower($title));
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'draft-' . time();
    }
    $url = $base;
    $count = 1;
    while (file_exists(GSDATAPAGESPATH . $url . '.xml')) {
        $url = $base . '-' . $count;
        $count++;
    }
    $xml = new SimpleXMLExtended('<?xml version="1.0" encoding="UTF-8"?><item></item>');
    $xml->addChild('url', $url);
    $xml->addChild('title')->addCData($title);
    $xml->addChild('pubDate', date('r'));
    $xml->addChild('content')->addCData($content);
    $xml->addChild('template', 'template.php');
    $xml->addChild('parent', '');
    $xml->addChild('menu', '');
    $xml->addChild('menuStatus', '');
    $xml->addChild('menuOrder', '0');
    $xml->addChild('private', 'Y');
    $xml->addChild('author', sp_current_user());
    if (XMLsave($xml, GSDATAPAGESPATH . $url . '.xml')) {
        @chmod(GSDATAPAGESPATH . $url . '.xml', 0640);
        if (function_exists('create_pagesxml')) {
            create_pagesxml(true);
        }
        sp_log('quick_draft', $url);
        sp_redirect(sp_admin_url('dashboard', 'saved=1'));
    }
    sp_redirect(sp_admin_url('dashboard', 'error=draft'));
}

# ==============================================================================
# 2. ŚLEDZENIE ODSŁON I WIZYT (STATYSTYKI)
# ==============================================================================
function wp_simple_track_views() {
    if (sp_is_authenticated()) {
        return;
    }
    $stats_file = GSDATAOTHERPATH . 'simplepress/stats.json';
    $today = date('Y-m-d');
    $page = isset($_GET['id']) ? clean_url((string) $_GET['id']) : 'index';
    if ($page === '') { $page = 'index'; }
    $ip = sha1(($GLOBALS['SALT'] ?? 'sp') . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

    $data = sp_read_json($stats_file, ['days' => [], 'pages' => []]);
    if (!isset($data['days'][$today])) {
        $data['days'][$today] = ['views' => 0, 'visitors' => []];
    }
    $data['days'][$today]['views'] = (int) $data['days'][$today]['views'] + 1;
    $visitors = is_array($data['days'][$today]['visitors']) ? $data['days'][$today]['visitors'] : [];
    if (!in_array($ip, $visitors, true) && count($visitors) < 500) {
        $visitors[] = $ip;
    }
    $data['days'][$today]['visitors'] = $visitors;
    $data['pages'][$page] = (int) ($data['pages'][$page] ?? 0) + 1;

    // Keep the data bounded.
    krsort($data['days']);
    $data['days'] = array_slice($data['days'], 0, 90, true);
    arsort($data['pages']);
    $data['pages'] = array_slice($data['pages'], 0, 100, true);

    sp_write_json($stats_file, $data);
}

# ==============================================================================
# 3. GŁÓWNY WIDOK KOKPITU
# ==============================================================================
function wp_simple_dashboard_view() {
    global $SITEURL, $TEMPLATE;

    $pages_count = 0;
    $drafts_count = 0;
    $recent_pages = [];
    $data_file = GSDATAOTHERPATH . 'pages.xml';

    if (file_exists($data_file)) {
        $xml = getXML($data_file);
        if ($xml && isset($xml->item)) {
            $pages_count = count($xml->item);
            foreach ($xml->item as $page) {
                $is_private = ((string)$page->private === 'Y' || (string)$page->private === '1');
                if ($is_private) {
                    $drafts_count++;
                }

                $recent_pages[] = [
                    'url' => (string)$page->url,
                    'title' => (string)$page->title,
                    'date' => (string)$page->pubDate,
                    'private' => $is_private
                ];
            }
            usort($recent_pages, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            $recent_pages = array_slice($recent_pages, 0, 5);
        }
    }

    $components_count = 0;
    $comp_file = GSDATAOTHERPATH . 'components.xml';
    if (file_exists($comp_file)) {
        $c_xml = getXML($comp_file);
        if ($c_xml && isset($c_xml->item)) {
            $components_count = count($c_xml->item);
        }
    }

    global $live_plugins;
    $active_plugins_count = is_array($live_plugins) ? count(array_filter($live_plugins, function ($v) { return $v === 'true'; })) : count(glob(GSPLUGINPATH . '*.php'));

    $upload_dir = defined('GSDATAUPLOADPATH') ? GSDATAUPLOADPATH : GSROOTPATH . 'data/uploads/';
    $files_count = 0;
    $uploads_size = 0;
    if (is_dir($upload_dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $f) {
            if ($f->isFile()) {
                $files_count++;
                $uploads_size += $f->getSize();
            }
        }
    }
    $uploads_size_str = function_exists('fSize') ? fSize($uploads_size) : round($uploads_size / 1048576, 2) . ' MB';

    $chart_days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_days[$d] = ['label' => date('d.m', strtotime($d)), 'views' => 0, 'visitors' => 0];
    }

    $stats_file = GSDATAOTHERPATH . 'simplepress/stats.json';
    if (!is_file($stats_file) && is_file(GSDATAOTHERPATH . 'wp_stats.xml')) {
        $oldXml = @simplexml_load_file(GSDATAOTHERPATH . 'wp_stats.xml');
        if ($oldXml) {
            $migrated = ['days' => [], 'pages' => []];
            foreach ($oldXml->day as $day) {
                $migrated['days'][(string) $day['date']] = [
                    'views' => (int) $day->views,
                    'visitors' => array_values(array_filter(explode(',', (string) $day->visitors))),
                ];
            }
            foreach ($oldXml->page as $page) {
                $migrated['pages'][(string) $page['slug']] = (int) $page->views;
            }
            sp_write_json($stats_file, $migrated);
        }
    }
    $stats = sp_read_json($stats_file, ['days' => [], 'pages' => []]);
    $total_views_week = 0;
    $total_visitors_week = 0;
    foreach ($chart_days as $date => $unused) {
        $day = $stats['days'][$date] ?? ['views' => 0, 'visitors' => []];
        $views = (int) ($day['views'] ?? 0);
        $visitors = is_array($day['visitors'] ?? null) ? count($day['visitors']) : 0;
        $chart_days[$date]['views'] = $views;
        $chart_days[$date]['visitors'] = $visitors;
        $total_views_week += $views;
        $total_visitors_week += $visitors;
    }

    $max_views = 1;
    foreach ($chart_days as $cd) {
        if ($cd['views'] > $max_views) { $max_views = $cd['views']; }
    }

    $php_ver = phpversion();
    $gd_installed = extension_loaded('gd');
    $sqlite_installed = class_exists('SQLite3');
    $upload_max = ini_get('upload_max_filesize');
    $memory_limit = ini_get('memory_limit');
    $uploads_writable = is_writable($upload_dir);
    $pages_writable = is_writable(GSDATAPAGESPATH);
    ?>
    <style>
        /* UKRYCIE PRAWEGO SIDEBARA ORAZ ROZCIĄGNIĘCIE KOKPITU NA PEŁNĄ SZEROKOŚĆ */
        #sidebar {
            display: none !important;
        }
        #maincontent {
            width: 100% !important;
            float: none !important;
            margin-right: 0 !important;
        }
        .bodycontent.clearfix {
            display: block !important;
        }

        /* Siatka widgetów kokpitu */
        .wp-dashboard-grid {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        .wp-dashboard-grid .postbox-container {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .wp-dashboard-grid.single-column {
            flex-direction: column !important;
        }
        .wp-dashboard-grid.single-column .postbox-container {
            width: 100% !important;
        }

        .postbox {
            background: var(--wp-card-bg);
            border: 1px solid var(--wp-border, var(--wp-border));
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            overflow: hidden;
            transition: all 0.2s;
        }
        .postbox-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--wp-border);
            background: var(--wp-surface-alt);
            cursor: pointer;
            user-select: none;
        }
        .postbox-header h2 {
            margin: 0 !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            color: var(--wp-text-strong);
        }
        .postbox-header .toggle-indicator {
            color: var(--wp-text-faint);
            font-size: 14px;
            transition: transform 0.2s;
        }
        .postbox.closed .postbox-header {
            border-bottom: none;
        }
        .postbox.closed .inside {
            display: none !important;
        }
        .postbox.closed .toggle-indicator {
            transform: rotate(-90deg);
        }
        .postbox .inside {
            padding: 16px;
        }

        .stats-summary {
            display: flex;
            gap: 15px;
            margin-bottom: 16px;
        }
        .stat-box {
            flex: 1;
            background: var(--wp-surface-alt);
            border: 1px solid var(--wp-border);
            border-radius: 4px;
            padding: 12px;
            text-align: center;
        }
        .stat-box .stat-num {
            display: block;
            font-size: 22px;
            font-weight: 700;
            color: var(--wp-blue);
            line-height: 1.2;
        }
        .stat-box .stat-desc {
            font-size: 11px;
            color: var(--wp-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-bar-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 120px;
            padding-top: 15px;
            border-bottom: 1px solid var(--wp-border);
            gap: 8px;
        }
        .chart-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }
        .bar-fill {
            width: 100%;
            max-width: 24px;
            background: var(--wp-blue);
            border-radius: 3px 3px 0 0;
            transition: height 0.3s ease, background 0.2s;
            cursor: pointer;
        }
        .bar-fill:hover {
            background: var(--wp-blue-hover);
        }
        .bar-label {
            font-size: 10px;
            color: var(--wp-text-muted);
            margin-top: 6px;
        }

        #wp-screen-meta-panel {
            background: var(--wp-card-bg);
            border: 1px solid var(--wp-border, var(--wp-border));
            padding: 16px 20px;
            margin-bottom: 20px;
            display: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            border-radius: 4px;
        }
    </style>

    <div id="wpbody-content">
        <?php wp_simple_render_subtabs(); ?>

        <div id="wp-screen-meta-panel">
            <div style="font-size:13px;">
                <legend style="font-weight:600; margin-bottom:12px; color:var(--wp-text-strong); font-size:14px;">Widoczność kafelków na pulpicie</legend>
                <div style="display:flex; gap:22px; flex-wrap:wrap; align-items:center;">
                    <label style="cursor:pointer;"><input type="checkbox" class="screen-meta-toggle-box" data-target="widget-stats" checked> Statystyki odsłon</label>
                    <label style="cursor:pointer;"><input type="checkbox" class="screen-meta-toggle-box" data-target="widget-glance" checked> W skrócie</label>
                    <label style="cursor:pointer;"><input type="checkbox" class="screen-meta-toggle-box" data-target="widget-health" checked> Stan witryny i serwera</label>
                    <label style="cursor:pointer;"><input type="checkbox" class="screen-meta-toggle-box" data-target="widget-draft" checked> Szybki szkic</label>
                    <label style="cursor:pointer;"><input type="checkbox" class="screen-meta-toggle-box" data-target="widget-activity" checked> Aktywność</label>
                    <button type="button" id="wp-toggle-grid-layout" class="button" style="margin-left:auto;">Pojedyncza kolumna</button>
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0 !important; font-size:22px; font-weight:600;">Kokpit</h2>
            <button type="button" id="wp-screen-options-toggle" class="button" style="font-size:12px; background:var(--wp-surface-alt); border-color:var(--wp-border); color:var(--wp-blue); cursor:pointer; padding:5px 12px; border-radius:3px;">
                Opcje ekranu <span id="wp-screen-arrow">▼</span>
            </button>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div class="updated" style="background: var(--wp-card-bg); border-left:4px solid var(--wp-success); padding:10px 15px; margin-bottom:20px; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                <p style="margin:0; color:var(--wp-text-strong);">Szkic strony został pomyślnie zapisany.</p>
            </div>
        <?php endif; ?>

        <div class="welcome-panel" style="background: var(--wp-card-bg); border:1px solid var(--wp-border, var(--wp-border)); border-radius:4px; padding:20px; margin-bottom:20px; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
            <div class="welcome-panel-content">
                <h2 style="margin:0 0 8px 0; font-size:18px;">Witaj w SimplePress!</h2>
                <p style="margin:0 0 16px 0; color:var(--wp-text-muted); font-size:13px;">Skróty do najważniejszych obszarów Twojej witryny:</p>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">
                    <div>
                        <h3 style="font-size:14px; margin:0 0 10px 0;">Rozpocznij</h3>
                        <a class="button button-primary" href="theme.php" style="padding:6px 14px;">Dostosuj motyw</a>
                    </div>
                    <div>
                        <h3 style="font-size:14px; margin:0 0 10px 0;">Tworzenie treści</h3>
                        <ul style="margin:0; padding-left:18px; line-height:1.8; font-size:13px;">
                            <li><a href="edit.php" style="text-decoration:none; color:var(--wp-blue);">Dodaj nową stronę</a></li>
                            <li><a href="upload.php" style="text-decoration:none; color:var(--wp-blue);">Biblioteka mediów</a></li>
                            <li><a href="<?php echo $SITEURL; ?>" target="_blank" style="text-decoration:none; color:var(--wp-blue);">Odwiedź witrynę ↗</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 style="font-size:14px; margin:0 0 10px 0;">Zarządzanie</h3>
                        <ul style="margin:0; padding-left:18px; line-height:1.8; font-size:13px;">
                            <li><a href="load.php?id=simplepress&sub=plugin-repo" style="text-decoration:none; color:var(--wp-blue); font-weight:600;">Instalator wtyczek</a></li>
                            <li><a href="components.php" style="text-decoration:none; color:var(--wp-blue);">Komponenty</a></li>
                            <li><a href="plugins.php" style="text-decoration:none; color:var(--wp-blue);">Zainstalowane wtyczki (<?php echo $active_plugins_count; ?>)</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="wp-dashboard-grid" id="wp-main-dashboard-grid">
            
            <div class="postbox-container">
                <div class="postbox" id="widget-stats">
                    <div class="postbox-header">
                        <h2>Statystyki odsłon (ostatnie 7 dni)</h2>
                        <span class="toggle-indicator">▼</span>
                    </div>
                    <div class="inside">
                        <div class="stats-summary">
                            <div class="stat-box">
                                <span class="stat-num"><?php echo $total_views_week; ?></span>
                                <span class="stat-desc">Wszystkie odsłony</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-num"><?php echo $total_visitors_week; ?></span>
                                <span class="stat-desc">Unikalne wizyty</span>
                            </div>
                        </div>

                        <div class="stats-bar-chart">
                            <?php foreach ($chart_days as $ddata): 
                                $pct = ($max_views > 0 && $ddata['views'] > 0) ? round(($ddata['views'] / $max_views) * 100) : 4;
                            ?>
                                <div class="chart-col">
                                    <div class="bar-fill" style="height: <?php echo max(5, $pct); ?>%;" title="<?php echo $ddata['views']; ?> odsłon / <?php echo $ddata['visitors']; ?> wizyt"></div>
                                    <span class="bar-label"><?php echo $ddata['label']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="postbox" id="widget-glance">
                    <div class="postbox-header">
                        <h2>W skrócie</h2>
                        <span class="toggle-indicator">▼</span>
                    </div>
                    <div class="inside">
                        <ul style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:0; padding:0; list-style:none; font-size:13px;">
                            <li><a href="pages.php" style="text-decoration:none; color:var(--wp-blue); font-weight:600;">📄 <?php echo ($pages_count - $drafts_count); ?> Stron opublikowanych</a></li>
                            <li><a href="pages.php" style="text-decoration:none; color:var(--wp-text-muted);">📝 <?php echo $drafts_count; ?> Szkiców</a></li>
                            <li><a href="upload.php" style="text-decoration:none; color:var(--wp-blue); font-weight:600;">🖼️ <?php echo $files_count; ?> Plików mediów</a></li>
                            <li><span style="color:var(--wp-text-muted);">💾 <?php echo $uploads_size_str; ?> w Uploads</span></li>
                            <li><a href="components.php" style="text-decoration:none; color:var(--wp-blue);">🧩 <?php echo $components_count; ?> Komponentów</a></li>
                            <li><a href="plugins.php" style="text-decoration:none; color:var(--wp-blue);">🔌 <?php echo $active_plugins_count; ?> Aktywnych wtyczek</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="postbox-container">
                <div class="postbox" id="widget-health">
                    <div class="postbox-header">
                        <h2>Stan środowiska i serwera</h2>
                        <span class="toggle-indicator">▼</span>
                    </div>
                    <div class="inside">
                        <table style="width:100%; border-collapse:collapse; font-size:12px; line-height:2.2;">
                            <tr style="border-bottom: 1px solid var(--wp-border-light);">
                                <td>Wersja PHP:</td>
                                <td style="text-align:right;"><strong><?php echo $php_ver; ?></strong></td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--wp-border-light);">
                                <td>Baza SQLite3:</td>
                                <td style="text-align:right;">
                                    <?php if ($sqlite_installed): ?>
                                        <span style="color:var(--wp-success); font-weight:600;">Aktywna</span>
                                    <?php else: ?>
                                        <span style="color:var(--wp-danger); font-weight:600;">Niedostępna</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--wp-border-light);">
                                <td>Biblioteka graficzna GD:</td>
                                <td style="text-align:right;">
                                    <?php if ($gd_installed): ?>
                                        <span style="color:var(--wp-success);">Dostępna (Generowanie miniatur)</span>
                                    <?php else: ?>
                                        <span style="color:var(--wp-danger);">Brak GD</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--wp-border-light);">
                                <td>Limit pamięci RAM:</td>
                                <td style="text-align:right;"><strong><?php echo $memory_limit; ?></strong></td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--wp-border-light);">
                                <td>Maks. rozmiar uploadu:</td>
                                <td style="text-align:right;"><strong><?php echo $upload_max; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Uprawnienia zapisu (Uploads / Pages):</td>
                                <td style="text-align:right;">
                                    <?php if ($uploads_writable && $pages_writable): ?>
                                        <span style="color:var(--wp-success); font-weight:600;">Prawidłowe</span>
                                    <?php else: ?>
                                        <span style="color:var(--wp-danger); font-weight:600;">Wymagana korekta uprawnień</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox" id="widget-draft">
                    <div class="postbox-header">
                        <h2>Szybki szkic</h2>
                        <span class="toggle-indicator">▼</span>
                    </div>
                    <div class="inside">
                        <form method="post" action="load.php?id=simplepress&amp;sub=dashboard">
                            <?php sp_nonce_field('quick_draft'); ?>
                            <p style="margin:0 0 10px 0;">
                                <input type="text" name="post_title" placeholder="Tytuł wpisu lub podstrony..." style="width:100%; box-sizing:border-box; padding:7px 10px; border: 1px solid var(--wp-input-border); border-radius:4px; font-size:13px;" required />
                            </p>
                            <p style="margin:0 0 12px 0;">
                                <textarea name="post_content" placeholder="O czym chcesz napisać?" rows="4" style="width:100%; box-sizing:border-box; padding:8px 10px; border: 1px solid var(--wp-input-border); border-radius:4px; font-size:13px;"></textarea>
                            </p>
                            <p style="margin:0;">
                                <input type="submit" name="wp_quick_draft" class="button button-primary" value="Zapisz jako szkic" style="cursor:pointer;" />
                            </p>
                        </form>
                    </div>
                </div>

                <div class="postbox" id="widget-activity">
                    <div class="postbox-header">
                        <h2>Aktywność</h2>
                        <span class="toggle-indicator">▼</span>
                    </div>
                    <div class="inside">
                        <p style="margin:0 0 10px 0; font-size:12px; color:var(--wp-text-muted);">Ostatnio modyfikowane podstrony:</p>
                        <ul style="margin:0; padding:0; list-style:none;">
                            <?php foreach ($recent_pages as $p): ?>
                                <li style="padding:7px 0; border-bottom: 1px solid var(--wp-border-light); display:flex; justify-content:space-between; align-items:center; font-size:12px;">
                                    <a href="edit.php?id=<?php echo urlencode($p['url']); ?>" style="text-decoration:none; font-weight:600; color:var(--wp-blue);">
                                        <?php echo htmlspecialchars($p['title']); ?>
                                        <?php if (!empty($p['private'])): ?>
                                            <span style="color:var(--wp-danger); font-weight:normal; font-size:11px;">[Szkic]</span>
                                        <?php endif; ?>
                                    </a>
                                    <span style="color:var(--wp-text-faint);"><?php echo date('d.m, H:i', strtotime($p['date'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
    (function() {
        var screenToggle = document.getElementById("wp-screen-options-toggle");
        var screenMeta = document.getElementById("wp-screen-meta-panel");
        var screenArrow = document.getElementById("wp-screen-arrow");

        if (screenToggle && screenMeta) {
            screenToggle.addEventListener("click", function() {
                var isOpen = (screenMeta.style.display === "block");
                screenMeta.style.display = isOpen ? "none" : "block";
                screenArrow.textContent = isOpen ? "▼" : "▲";
            });
        }

        var gridLayoutBtn = document.getElementById("wp-toggle-grid-layout");
        var gridContainer = document.getElementById("wp-main-dashboard-grid");

        if (gridLayoutBtn && gridContainer) {
            if (localStorage.getItem("wp_dashboard_single_col") === "true") {
                gridContainer.classList.add("single-column");
                gridLayoutBtn.textContent = "Dwie kolumny";
            }
            gridLayoutBtn.addEventListener("click", function() {
                gridContainer.classList.toggle("single-column");
                var isSingle = gridContainer.classList.contains("single-column");
                localStorage.setItem("wp_dashboard_single_col", isSingle ? "true" : "false");
                gridLayoutBtn.textContent = isSingle ? "Dwie kolumny" : "Pojedyncza kolumna";
            });
        }

        document.querySelectorAll(".screen-meta-toggle-box").forEach(function(checkbox) {
            var targetId = checkbox.dataset.target;
            var targetBox = document.getElementById(targetId);

            if (localStorage.getItem("wp_box_hidden_" + targetId) === "true") {
                checkbox.checked = false;
                if (targetBox) targetBox.style.display = "none";
            } else {
                checkbox.checked = true;
                if (targetBox) targetBox.style.display = "block";
            }

            checkbox.addEventListener("change", function() {
                if (targetBox) {
                    targetBox.style.display = this.checked ? "block" : "none";
                    localStorage.setItem("wp_box_hidden_" + targetId, this.checked ? "false" : "true");
                }
            });
        });

        document.querySelectorAll(".postbox-header").forEach(function(header) {
            var box = header.closest(".postbox");
            if (!box) return;
            var boxId = box.id;

            if (boxId && localStorage.getItem("wp_box_closed_" + boxId) === "true") {
                box.classList.add("closed");
            }

            header.addEventListener("click", function(e) {
                if (e.target.tagName.toLowerCase() === "a" || e.target.tagName.toLowerCase() === "input") return;

                box.classList.toggle("closed");
                if (boxId) {
                    localStorage.setItem("wp_box_closed_" + boxId, box.classList.contains("closed") ? "true" : "false");
                }
            });
        });
    })();
    </script>
    <?php
}