<?php
if (!defined('IN_GS')) { die('You cannot load this file directly!'); }


# ==============================================================================
# POBIERANIE POZYCJI MENU
# ==============================================================================
function wp_simple_get_menu_links() {
    if (!file_exists(WP_SIMPLE_MENU_FILE)) {
        $old = GSDATAOTHERPATH . 'wp_simple_admin/menu_ext.json';
        if (file_exists($old)) { @copy($old, WP_SIMPLE_MENU_FILE); }
    }
    if (file_exists(WP_SIMPLE_MENU_FILE)) {
        $data = json_decode(@file_get_contents(WP_SIMPLE_MENU_FILE), true);
        if (is_array($data)) {
            $clean = [];
            foreach ($data as $key => $item) {
                if (!is_array($item)) { continue; }
                $url = sp_url($item['url'] ?? '', false);
                if (!$url && preg_match('#^[A-Za-z0-9._\-/?=&]+$#', (string) ($item['url'] ?? '')) && strpos((string) ($item['url'] ?? ''), '..') === false) {
                    $url = (string) $item['url'];
                }
                if (!$url) { continue; }
                $clean[$key] = [
                    'name' => strip_tags((string) ($item['name'] ?? '')),
                    'url' => $url,
                    'location' => in_array(($item['location'] ?? 'sidebar'), ['sidebar', 'top_tabs', 'front_bar'], true) ? $item['location'] : 'sidebar',
                    'icon' => preg_replace('/[^a-z0-9\-]/', '', (string) ($item['icon'] ?? 'dashicons-admin-links')) ?: 'dashicons-admin-links',
                    'target' => ($item['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
                ];
            }
            return $clean;
        }
    }
    return [];
}

# ==============================================================================
# PRZETWARZANIE AKCJI POST / GET
# ==============================================================================
function wp_simple_handle_menu_actions() {
    if (!sp_is_administrator()) {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp_delete_menu_link'])) {
        sp_require_post('delete_menu_link');
        $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($_POST['key'] ?? ''));
        $links = wp_simple_get_menu_links();
        if (isset($links[$id])) {
            unset($links[$id]);
            sp_write_json(WP_SIMPLE_MENU_FILE, $links);
            sp_log('menu_delete', $id);
            sp_redirect(sp_admin_url('menu', 'deleted=1'));
        }
        sp_redirect(sp_admin_url('menu', 'error=not_found'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp_add_menu_link'])) {
        sp_require_post('add_menu_link');
        $name = trim(strip_tags((string) ($_POST['link_name'] ?? '')));
        $url = trim((string) ($_POST['link_url'] ?? ''));
        $location = in_array(($_POST['link_location'] ?? 'sidebar'), ['sidebar', 'top_tabs', 'front_bar'], true) ? $_POST['link_location'] : 'sidebar';
        $icon = preg_replace('/[^a-z0-9\-]/', '', (string) ($_POST['link_icon'] ?? 'dashicons-admin-links'));
        if ($icon === '') { $icon = 'dashicons-admin-links'; }
        $target = ($_POST['link_target'] ?? '_self') === '_blank' ? '_blank' : '_self';

        if ($name === '' || strlen($name) > 80) {
            sp_redirect(sp_admin_url('menu', 'error=invalid'));
        }
        $safeUrl = sp_url($url, false);
        if (!$safeUrl && preg_match('#^[A-Za-z0-9._\-/?=&]+$#', $url) && strpos($url, '..') === false) {
            $safeUrl = $url;
        }
        if (!$safeUrl) {
            sp_redirect(sp_admin_url('menu', 'error=invalid_url'));
        }

        $links = wp_simple_get_menu_links();
        $key = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $name))) . '_' . time();
        $links[$key] = ['name' => $name, 'url' => $safeUrl, 'location' => $location, 'icon' => $icon, 'target' => $target];
        sp_write_json(WP_SIMPLE_MENU_FILE, $links);
        sp_log('menu_add', $name);
        sp_redirect(sp_admin_url('menu', 'saved=1'));
    }
}
add_action('load-common', 'wp_simple_handle_menu_actions');

# ==============================================================================
# RENDEROWANIE ELEMENTÓW
# ==============================================================================

// 1. GÓRNE ZAKŁADKI (nav-tab)
add_action('nav-tab', 'wp_simple_render_custom_nav_tabs');
function wp_simple_render_custom_nav_tabs() {
    $links = wp_simple_get_menu_links();
    foreach ($links as $link) {
        if ($link['location'] === 'top_tabs') {
            $icon = !empty($link['icon']) ? '<span class="dashicons ' . htmlspecialchars($link['icon']) . '" style="font-size:18px; line-height:20px; vertical-align:middle; margin-right:6px;"></span>' : '';
            $rel = $link['target'] === '_blank' ? ' rel="noopener noreferrer"' : '';
            echo '<li class="wp-custom-nav-item"><a href="' . sp_e($link['url']) . '" target="' . sp_e($link['target']) . '"' . $rel . '>' . $icon . '<span class="wp-nav-label">' . sp_e($link['name']) . '</span></a></li>';
        }
    }
}

// 2. LEWE MENU BOCZNE (WSTRZYKIWANE DO GŁÓWNEGO MENU BOCZNEGO)
add_action('footer', 'wp_simple_inject_sidebar_links_js');
function wp_simple_inject_sidebar_links_js() {
    $links = wp_simple_get_menu_links();
    $sidebar_links = array_values(array_filter($links, function ($l) {
        return ($l['location'] ?? '') === 'sidebar';
    }));
    if (empty($sidebar_links)) { return; }
    ?>
    <script>
    (function () {
        function inject() {
            var target = document.querySelector("#admin-menu, ul.nav");
            if (!target || document.querySelector(".sp-custom-injected")) return;
            var links = <?php echo json_encode($sidebar_links, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            links.forEach(function (l) {
                var li = document.createElement("li");
                li.className = "sp-custom-injected wp-custom-nav-item";
                var a = document.createElement("a");
                a.href = l.url;
                a.target = l.target === "_blank" ? "_blank" : "_self";
                if (a.target === "_blank") a.rel = "noopener noreferrer";
                var icon = document.createElement("span");
                icon.className = "dashicons " + (l.icon || "dashicons-admin-links");
                icon.style.cssText = "font-size:18px;width:20px;height:20px;margin-right:8px;display:inline-flex;align-items:center;justify-content:center";
                var label = document.createElement("span");
                label.className = "wp-nav-label";
                label.textContent = l.name;
                a.appendChild(icon);
                a.appendChild(label);
                li.appendChild(a);
                var collapse = target.querySelector('#collapse-menu');
                if (collapse) target.insertBefore(li, collapse); else target.appendChild(li);
            });
        }
        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", inject); else inject();
    })();
    </script>
    <?php
}

// 3. GÓRNY PASEK FRONT-ENDU (ADMIN BAR)
function wp_simple_front_bar_links() {
    $links = wp_simple_get_menu_links();
    foreach ($links as $link) {
        if (($link['location'] ?? '') !== 'front_bar') { continue; }
        $target = ($link['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
        echo '<a href="' . sp_e($link['url']) . '"' . $target . '>' . sp_e($link['name']) . '</a>';
    }
}
# ==============================================================================
# JEDNOKOLUMNOWY WIDOK ZARZĄDZANIA W KOKPICIE
# ==============================================================================
function wp_simple_menu_ext_view() {
    wp_simple_handle_menu_actions();
    $links = wp_simple_get_menu_links();

    $icons = [
        'dashicons-admin-links'     => wp_i18n('MENU_ICON_LINK', 'Link'),
        'dashicons-external'        => wp_i18n('MENU_ICON_EXTERNAL', 'External link'),
        'dashicons-admin-site-alt3' => wp_i18n('MENU_ICON_WEBSITE', 'Website'),
        'dashicons-store'           => wp_i18n('MENU_ICON_STORE', 'Store'),
        'dashicons-cart'            => wp_i18n('MENU_ICON_CART', 'Cart'),
        'dashicons-email'           => wp_i18n('MENU_ICON_CONTACT', 'Contact'),
        'dashicons-analytics'       => wp_i18n('MENU_ICON_STATS', 'Statistics'),
        'dashicons-star-filled'     => wp_i18n('MENU_ICON_STAR', 'Star'),
        'dashicons-media-document'  => wp_i18n('MENU_ICON_DOCS', 'Documentation'),
        'dashicons-rest-api'        => wp_i18n('MENU_ICON_API', 'API / Integrations'),
        'dashicons-admin-generic'   => wp_i18n('MENU_ICON_SETTINGS', 'Settings'),
        'dashicons-admin-users'     => wp_i18n('MENU_ICON_USERS', 'Users'),
        'dashicons-visibility'      => wp_i18n('MENU_ICON_PREVIEW', 'Preview')
    ];
    ?>
    <div id="wpbody-content">
        <div class="wrap" >
            <?php if (function_exists('wp_simple_render_subtabs')) { wp_simple_render_subtabs(); } ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h2 style="margin:0 !important;"><?php echo sp_e(wp_i18n('MENU_TITLE', 'Custom menu items')); ?></h2>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="updated"><p><?php echo sp_e(wp_i18n('MENU_ADDED', 'New menu item added successfully.')); ?></p></div>
            <?php elseif (isset($_GET['deleted'])): ?>
                <div class="updated"><p><?php echo sp_e(wp_i18n('MENU_DELETED', 'Menu item deleted.')); ?></p></div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="error"><p><?php echo sp_e(wp_i18n('MENU_ERROR', 'Could not add the item. Check the name and URL.')); ?></p></div>
            <?php endif; ?>

            <!-- SEKCJA 1: TABELA LINKÓW NA CAŁĄ SZEROKOŚĆ -->
            <div style="background: var(--wp-card-bg); border:1px solid var(--wp-border); border-radius:3px; overflow:hidden; box-shadow:0 1px 1px rgba(0,0,0,0.04); margin-bottom: 30px;">
                <div style="padding:14px 18px; background:var(--wp-surface-alt); border-bottom:1px solid var(--wp-border);">
                    <h3 style="margin:0; font-size:14px; font-weight:600;"><?php echo sp_e(wp_i18n('MENU_CURRENT_ITEMS', 'Current menu items')); ?></h3>
                </div>

                <table class="highlight" style="width: 100%; margin: 0; border: none; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--wp-card-bg); border-bottom: 1px solid var(--wp-border); text-align: left;">
                            <th style="padding: 12px 18px; width: 35%;"><?php echo sp_e(wp_i18n('MENU_NAME_ICON', 'Name and icon')); ?></th>
                            <th style="padding: 12px 18px; width: 30%;"><?php echo sp_e(wp_i18n('MENU_URL', 'URL')); ?></th>
                            <th style="padding: 12px 18px; width: 20%;"><?php echo sp_e(wp_i18n('MENU_LOCATION', 'Location')); ?></th>
                            <th style="padding: 12px 18px; width: 15%; text-align: right;"><?php echo sp_e(wp_i18n('MENU_ACTION', 'Action')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($links)): ?>
                            <tr><td colspan="4" style="text-align:center; padding: 30px; color:var(--wp-text-muted);"><?php echo sp_e(wp_i18n('MENU_EMPTY', 'No custom menu items. Add one using the form below.')); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($links as $key => $item): 
                                $loc_label = wp_i18n('MENU_SIDEBAR_LABEL', 'Left sidebar');
                                if ($item['location'] === 'top_tabs') { $loc_label = wp_i18n('MENU_TOP_TABS_SHORT', 'Top tabs (Nav)'); }
                                if ($item['location'] === 'front_bar') { $loc_label = wp_i18n('MENU_FRONT_BAR_SHORT', 'Top bar (Front-end)'); }
                            ?>
                                <tr style="border-bottom: 1px solid var(--wp-border-light); vertical-align: middle;">
                                    <td style="padding: 12px 18px;">
                                        <span class="dashicons <?php echo htmlspecialchars($item['icon']); ?>" style="color:var(--wp-blue); margin-right:8px; vertical-align:middle; font-size:18px;"></span>
                                        <strong style="font-size:13px;"><?php echo htmlspecialchars($item['name']); ?></strong>
                                        <?php if ($item['target'] === '_blank'): ?>
                                            <small style="color:var(--wp-text-muted); margin-left:4px;" title="<?php echo sp_e(wp_i18n('MENU_OPEN_NEW_TAB', 'Opens in a new tab')); ?>">↗</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 18px; font-family: monospace; font-size: 13px;">
                                        <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" style="color:var(--wp-blue); text-decoration:none;">
                                            <?php echo htmlspecialchars(substr($item['url'], 0, 45)) . (strlen($item['url']) > 45 ? '...' : ''); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px 18px;">
                                        <span style="background: var(--wp-content-bg); padding: 4px 10px; border-radius: 12px; font-size: 12px; color:var(--wp-text-strong);">
                                            <?php echo $loc_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 18px; text-align: right;">
                                        <form method="post" action="load.php?id=simplepress&amp;sub=menu" style="display:inline" onsubmit="return confirm(<?php echo sp_e(sp_js(wp_i18n('MENU_DELETE_CONFIRM', 'Are you sure you want to remove this menu item?'))); ?>);">
                                            <?php sp_nonce_field('delete_menu_link'); ?>
                                            <input type="hidden" name="wp_delete_menu_link" value="1">
                                            <input type="hidden" name="key" value="<?php echo sp_e($key); ?>">
                                            <button type="submit" class="button" style="background:var(--wp-danger-strong) !important;border-color:var(--wp-danger-strong) !important;color: var(--wp-on-accent) !important;"><?php echo sp_e(wp_i18n('DELETE', 'Delete')); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- SEKCJA 2: FORMULARZ DODAWANIA POZYCJI NA CAŁĄ SZEROKOŚĆ -->
            <div class="postbox" style="background: var(--wp-card-bg); border:1px solid var(--wp-border); border-radius:3px; box-shadow:0 1px 1px rgba(0,0,0,0.04); margin-bottom: 20px;">
                <div class="postbox-header" style="padding:14px 18px; background:var(--wp-surface-alt); border-bottom:1px solid var(--wp-border);">
                    <h3 style="margin:0; font-size:14px; font-weight:600;"><?php echo sp_e(wp_i18n('MENU_ADD_TITLE', 'Add a new menu item')); ?></h3>
                </div>
                <div class="inside" style="padding:20px;">
                    <form method="post" action="load.php?id=simplepress&amp;sub=menu">
                        <?php sp_nonce_field('add_menu_link'); ?>
                        <table class="form-table" style="width: 100%; border-collapse: collapse;">
                            <tbody>
                                <tr>
                                    <th scope="row" style="width: 220px; padding: 12px 10px; font-weight: 600; vertical-align: middle;">
                                        <?php echo sp_e(wp_i18n('MENU_NAME', 'Label')); ?>
                                    </th>
                                    <td style="padding: 12px 10px;">
                                        <input type="text" name="link_name" placeholder="<?php echo sp_e(wp_i18n('MENU_NAME_PLACEHOLDER', 'e.g. Client panel')); ?>" required style="width: 100%; max-width: 450px; padding: 7px 10px; border: 1px solid var(--wp-input-border); border-radius: 4px;">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" style="padding: 12px 10px; font-weight: 600; vertical-align: middle;">
                                        <?php echo sp_e(wp_i18n('MENU_URL', 'URL')); ?>
                                    </th>
                                    <td style="padding: 12px 10px;">
                                        <input type="text" name="link_url" placeholder="<?php echo sp_e(wp_i18n('MENU_URL_PLACEHOLDER', 'https://example.com or page.php')); ?>" required style="width: 100%; max-width: 450px; padding: 7px 10px; border: 1px solid var(--wp-input-border); border-radius: 4px; font-family: monospace;">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" style="padding: 12px 10px; font-weight: 600; vertical-align: middle;">
                                        <?php echo sp_e(wp_i18n('MENU_LOCATION', 'Location')); ?>
                                    </th>
                                    <td style="padding: 12px 10px;">
                                        <select name="link_location" style="width: 100%; max-width: 450px; padding: 7px 10px; border: 1px solid var(--wp-input-border); border-radius: 4px;">
                                            <option value="sidebar"><?php echo sp_e(wp_i18n('MENU_SIDEBAR', 'Admin sidebar')); ?> (#sidebar)</option>
                                            <option value="top_tabs"><?php echo sp_e(wp_i18n('MENU_TOP_TABS', 'Top navigation tabs (Nav-tabs)')); ?></option>
                                            <option value="front_bar"><?php echo sp_e(wp_i18n('MENU_FRONT_BAR', 'Front-end admin bar')); ?></option>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" style="padding: 12px 10px; font-weight: 600; vertical-align: middle;">
                                        <?php echo sp_e(wp_i18n('MENU_ICON_DASHICONS', 'Dashicons icon')); ?>
                                    </th>
                                    <td style="padding: 12px 10px;">
                                        <div style="display:flex; gap:10px; align-items:center; max-width: 450px;">
                                            <span id="icon-preview" class="dashicons dashicons-admin-links" style="font-size:24px; width:26px; height:26px; color:var(--wp-blue);"></span>
                                            <select name="link_icon" id="icon-select" style="flex:1; padding: 7px 10px; border: 1px solid var(--wp-input-border); border-radius: 4px;">
                                                <?php foreach ($icons as $cls => $name): ?>
                                                    <option value="<?php echo $cls; ?>"><?php echo $name; ?> (<?php echo $cls; ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" style="padding: 12px 10px; font-weight: 600; vertical-align: middle;">
                                        <?php echo sp_e(wp_i18n('MENU_TARGET', 'Open in')); ?>
                                    </th>
                                    <td style="padding: 12px 10px;">
                                        <select name="link_target" style="width: 100%; max-width: 450px; padding: 7px 10px; border: 1px solid var(--wp-input-border); border-radius: 4px;">
                                            <option value="_self"><?php echo sp_e(wp_i18n('MENU_SELF', 'Same window')); ?> (_self)</option>
                                            <option value="_blank"><?php echo sp_e(wp_i18n('MENU_BLANK', 'New window')); ?> (_blank)</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit" style="margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--wp-border-light);">
                            <input type="submit" name="wp_add_menu_link" class="button button-primary button-hero" value="<?php echo sp_e(wp_i18n('MENU_ADD', 'Add item')); ?>">
                        </p>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const iconSelect = document.getElementById("icon-select");
        const iconPreview = document.getElementById("icon-preview");
        if (iconSelect && iconPreview) {
            iconSelect.addEventListener("change", function() {
                iconPreview.className = "dashicons " + this.value;
            });
        }
    });
    </script>
    <?php
}