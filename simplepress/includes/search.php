<?php
/**
 * Plugin search filter.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('sp_plugin_search_assets')) {
    function sp_plugin_search_assets()
    {
        if (is_frontend() || (function_exists('get_filename_id') && get_filename_id() !== 'plugins')) {
            return;
        }
        ?>
        <script>
        (function () {
            function init() {
                var table = document.querySelector("#maincontent table");
                if (!table || document.getElementById("sp-plugin-search")) return;
                var wrap = document.createElement("p");
                wrap.style.margin = "0 0 12px";
                var input = document.createElement("input");
                input.type = "search";
                input.id = "sp-plugin-search";
                input.placeholder = <?php echo sp_js(wp_i18n('REPO_SEARCH', 'Search plugins...')); ?>;
                input.style.cssText = "width:100%;max-width:360px;padding:5px 9px;border: 1px solid var(--wp-input-border);border-radius:4px";
                wrap.appendChild(input);
                table.parentNode.insertBefore(wrap, table);
                input.addEventListener("input", function () {
                    var q = this.value.toLowerCase();
                    table.querySelectorAll("tbody tr").forEach(function (row) {
                        row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? "" : "none";
                    });
                });
            }
            if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
        })();
        </script>
        <?php
    }
}
