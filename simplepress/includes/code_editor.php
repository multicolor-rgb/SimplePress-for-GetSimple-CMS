<?php
/**
 * Local CodeMirror integration for the components editor.
 *
 * GetSimple already ships CodeMirror for theme-edit.php; this module reuses the
 * same local assets and adds an editor to components.php without any CDN.
 *
 * @package SimplePress
 */

if (!defined('IN_GS')) {
    die('You cannot load this file directly.');
}

if (!function_exists('wp_simple_components_editor')) {
    function wp_simple_components_editor()
    {
        if (!function_exists('get_filename_id') || get_filename_id() !== 'components') {
            return;
        }
        global $SITEURL, $GSADMIN;
        $base = rtrim($SITEURL, '/') . '/' . $GSADMIN . '/template/js/codemirror/';
        $files = ['codemirror.min.js', 'xml.min.js', 'javascript.min.js', 'css.min.js', 'htmlmixed.min.js', 'clike.min.js', 'php.min.js', 'matchbrackets.min.js', 'comment.min.js', 'closetag.min.js'];
        echo '<link rel="stylesheet" href="' . sp_e($base . 'codemirror.min.css') . '">';
        echo '<link rel="stylesheet" href="' . sp_e($base . 'blackboard.min.css') . '">';
        echo '<style>.CodeMirror{height:260px;border: 1px solid var(--wp-border);border-radius:4px;margin-top:6px}.sp-component-tools{display:flex;gap:8px;align-items:center;margin:6px 0 12px}.sp-component-tools select{padding:4px 8px;border: 1px solid var(--wp-input-border);border-radius:4px}</style>';
        foreach ($files as $file) {
            if (is_file(GSADMINPATH . 'template/js/codemirror/' . $file)) {
                echo '<script src="' . sp_e($base . $file) . '"></script>';
            }
        }
        echo '<script>(function(){function init(){if(typeof CodeMirror==="undefined")return;document.querySelectorAll(".manyinputs textarea").forEach(function(t){if(t.dataset.spCm)return;t.dataset.spCm="1";var wrap=document.createElement("div");wrap.className="sp-component-tools";var label=document.createElement("span");label.textContent=' . sp_js(wp_i18n('CODE_EDITOR_SYNTAX', 'Syntax:')) . ';var sel=document.createElement("select");[["htmlmixed","HTML / PHP"],["css","CSS"],["javascript","JavaScript"],["xml","XML"]].forEach(function(o){var op=document.createElement("option");op.value=o[0];op.textContent=o[1];sel.appendChild(op);});wrap.appendChild(label);wrap.appendChild(sel);t.parentNode.insertBefore(wrap,t.nextSibling);var cm=CodeMirror.fromTextArea(t,{theme:"blackboard",lineNumbers:true,matchBrackets:true,indentUnit:4,indentWithTabs:true,lineWrapping:true,mode:"htmlmixed"});sel.addEventListener("change",function(){cm.setOption("mode",sel.value);});cm.on("change",function(){t.value=cm.getValue();});var form=t.closest("form");if(form){form.addEventListener("submit",function(){t.value=cm.getValue();});}});}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init);else init();setTimeout(init,300);})();</script>';
    }
}
add_action('footer', 'wp_simple_components_editor');

if (!function_exists('wp_simple_theme_editor_note')) {
    function wp_simple_theme_editor_note()
    {
        if (!function_exists('get_filename_id') || get_filename_id() !== 'theme-edit') {
            return;
        }
        echo '<style>.CodeMirror{height:70vh;border: 1px solid var(--wp-border);border-radius:4px;font-size:13px}</style>';
    }
}
add_action('footer', 'wp_simple_theme_editor_note');
