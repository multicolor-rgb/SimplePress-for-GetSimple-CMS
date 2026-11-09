(function () {
    "use strict";

    var cfg = window.SP_CUSTOMIZER;
    if (!cfg) return;

    var root = document.getElementById("sp-customizer");
    if (!root) return;

    var sectionsEl = document.getElementById("sp-cz-sections");
    var statusEl = document.getElementById("sp-cz-status");
    var publishBtn = document.getElementById("sp-cz-publish");
    var resetBtn = document.getElementById("sp-cz-reset");
    var searchEl = document.getElementById("sp-cz-search");
    var iframe = document.getElementById("sp-cz-iframe");

    var state = {
        defs: [],
        sections: [],
        values: {},
        pages: [],
        menuLinks: [],
        dirty: false,
        open: false,
        saving: false,
        previewTimer: null
    };

    var controlSetters = {};
    var mediaModal = null;
    var mediaState = null;
    var tinymceLoading = false;
    var tinymceCallbacks = [];

    var DASHICONS = [
        "dashicons-admin-appearance", "dashicons-admin-customizer", "dashicons-admin-generic",
        "dashicons-admin-home", "dashicons-admin-links", "dashicons-admin-media",
        "dashicons-admin-network", "dashicons-admin-page", "dashicons-admin-plugins",
        "dashicons-admin-site", "dashicons-admin-site-alt3", "dashicons-admin-tools",
        "dashicons-admin-users", "dashicons-align-center", "dashicons-align-left",
        "dashicons-align-right", "dashicons-analytics", "dashicons-awards",
        "dashicons-backup", "dashicons-book", "dashicons-book-alt", "dashicons-building",
        "dashicons-businessman", "dashicons-camera", "dashicons-cart", "dashicons-category",
        "dashicons-chart-area", "dashicons-chart-bar", "dashicons-chart-line",
        "dashicons-chart-pie", "dashicons-clock", "dashicons-cloud", "dashicons-color-picker",
        "dashicons-format-aside", "dashicons-format-chat", "dashicons-format-gallery",
        "dashicons-format-image", "dashicons-format-quote", "dashicons-format-status",
        "dashicons-format-video", "dashicons-groups", "dashicons-heart", "dashicons-hidden",
        "dashicons-html", "dashicons-image-filter", "dashicons-images-alt2", "dashicons-info",
        "dashicons-instagram", "dashicons-layout", "dashicons-leftright", "dashicons-lightbulb",
        "dashicons-list-view", "dashicons-location", "dashicons-lock", "dashicons-mail",
        "dashicons-menu", "dashicons-menu-alt", "dashicons-microphone", "dashicons-migrate",
        "dashicons-money-alt", "dashicons-palmtree", "dashicons-phone", "dashicons-playlist-audio",
        "dashicons-portfolio", "dashicons-post-status", "dashicons-products", "dashicons-rest-api",
        "dashicons-rss", "dashicons-search", "dashicons-share", "dashicons-shield",
        "dashicons-shortcode", "dashicons-smartphone", "dashicons-star-filled", "dashicons-store",
        "dashicons-tag", "dashicons-thumbs-up", "dashicons-translation", "dashicons-twitter",
        "dashicons-unlock", "dashicons-update", "dashicons-upload", "dashicons-video-alt3",
        "dashicons-visibility", "dashicons-welcome-widgets-menus", "dashicons-wordpress",
        "dashicons-yes-alt"
    ];

    function t(key, fallback) {
        if (cfg.labels && cfg.labels[key]) return cfg.labels[key];
        return fallback !== undefined ? fallback : key;
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text || "";
    }

    function defArg(def, key, fallback) {
        if (def && def.args && def.args[key] !== undefined) return def.args[key];
        if (def && def[key] !== undefined && key !== "args") return def[key];
        return fallback;
    }

    function normalizeOptions(options) {
        var out = [];
        if (typeof options === "string") {
            var text = options.trim();
            if (!text) return out;
            try {
                options = JSON.parse(text);
            } catch (e) {
                options = text.split(/\r\n|\r|\n|\|\|/);
            }
        }
        if (Array.isArray(options)) {
            options.forEach(function (opt) {
                if (opt && typeof opt === "object") {
                    out.push({ value: String(opt.value !== undefined ? opt.value : ""), label: String(opt.label !== undefined ? opt.label : opt.value) });
                } else {
                    out.push({ value: String(opt), label: String(opt) });
                }
            });
        } else if (options && typeof options === "object") {
            Object.keys(options).forEach(function (key) {
                out.push({ value: key, label: String(options[key]) });
            });
        }
        return out;
    }

    function request(action, data, method) {
        var url = cfg.endpoint + action;
        var options = { credentials: "same-origin", headers: { "Accept": "application/json" } };
        if (method === "POST") {
            var form = new FormData();
            form.append("sp_nonce", cfg.nonce);
            Object.keys(data || {}).forEach(function (key) { form.append(key, data[key]); });
            options.method = "POST";
            options.body = form;
        } else {
            var params = new URLSearchParams(data || {});
            params.set("sp_nonce", cfg.nonce);
            url += "&" + params.toString();
        }
        return fetch(url, options).then(function (response) {
            return response.text().then(function (text) {
                var json;
                try {
                    json = JSON.parse(text);
                } catch (e) {
                    throw new Error(cfg.labels.error);
                }
                if (!response.ok) throw new Error(json.message || cfg.labels.error);
                return json;
            });
        });
    }

    function reloadPreview(useCacheBust) {
        if (!iframe) return;
        var url = cfg.preview;
        if (useCacheBust !== false) {
            url += "&_=" + Date.now();
        }
        iframe.src = url;
    }

    function markDirty() {
        state.dirty = true;
        setStatus("");
    }

    function schedulePreview() {
        window.clearTimeout(state.previewTimer);
        state.previewTimer = window.setTimeout(function () {
            setStatus(cfg.labels.saving);
            request("preview", { values: JSON.stringify(state.values) }, "POST").then(function () {
                setStatus("");
                reloadPreview();
            }).catch(function (error) {
                setStatus(error.message || cfg.labels.error);
            });
        }, 400);
    }

    function commit(key, value) {
        state.values[key] = value;
        markDirty();
        schedulePreview();
    }

    function optionElement(value, label, current) {
        var opt = document.createElement("option");
        opt.value = value;
        opt.textContent = label;
        opt.selected = String(value) === String(current);
        return opt;
    }

    function isImageValue(value) {
        return /\.(jpe?g|png|gif|webp|svg|ico|bmp|avif)(?:[?#].*)?$/i.test(String(value || ""));
    }

    function normalizeUrl(value) {
        var text = String(value || "");
        try {
            var url = new URL(text, window.location.href);
            return url.pathname + url.search + url.hash;
        } catch (e) {
            return text;
        }
    }

    function sameUrl(a, b) {
        var left = normalizeUrl(a);
        var right = normalizeUrl(b);
        return left === right || left.replace(/\/$/, "") === right.replace(/\/$/, "");
    }

    function parseList(value) {
        if (Array.isArray(value)) return value.slice();
        var text = String(value || "").trim();
        if (!text) return [];
        try {
            var decoded = JSON.parse(text);
            if (Array.isArray(decoded)) return decoded;
        } catch (e) {}
        return text.split(/\s*,\s*/).filter(function (item) { return item !== ""; });
    }

    function bindInput(input, def, getValue) {
        input.setAttribute("data-sp-cz-key", def.key);
        var handler = function () {
            commit(def.key, getValue ? getValue() : input.value);
        };
        input.addEventListener("input", handler);
        input.addEventListener("change", handler);
    }

    var builders = {};

    builders.text = function (wrap, def, value) {
        var input = el("input", "sp-cz-input");
        input.type = defArg(def, "input_type", "text");
        input.value = value !== undefined && value !== null ? value : "";
        if (defArg(def, "placeholder")) input.placeholder = defArg(def, "placeholder");
        bindInput(input, def);
        wrap.appendChild(input);
        return function (next) { input.value = next === undefined || next === null ? "" : next; };
    };

    builders.number = function (wrap, def, value) {
        var input = el("input", "sp-cz-input");
        input.type = "number";
        input.value = value !== undefined && value !== null ? value : "";
        if (defArg(def, "min") !== undefined) input.min = defArg(def, "min");
        if (defArg(def, "max") !== undefined) input.max = defArg(def, "max");
        if (defArg(def, "step") !== undefined) input.step = defArg(def, "step");
        bindInput(input, def);
        wrap.appendChild(input);
        return function (next) { input.value = next === undefined || next === null ? "" : next; };
    };

    builders.textarea = function (wrap, def, value) {
        var input = el("textarea", "sp-cz-input");
        input.rows = parseInt(defArg(def, "rows", 4), 10) || 4;
        input.value = value !== undefined && value !== null ? value : "";
        bindInput(input, def);
        wrap.appendChild(input);
        return function (next) { input.value = next === undefined || next === null ? "" : next; };
    };

    builders.code = function (wrap, def, value) {
        var input = el("textarea", "sp-cz-input sp-cz-code");
        input.rows = parseInt(defArg(def, "rows", 7), 10) || 7;
        input.spellcheck = false;
        input.value = value !== undefined && value !== null ? value : "";
        bindInput(input, def);
        wrap.appendChild(input);
        return function (next) { input.value = next === undefined || next === null ? "" : next; };
    };

    builders.select = function (wrap, def, value) {
        var select = el("select", "sp-cz-input");
        var options = normalizeOptions(defArg(def, "options", []));
        var hasEmpty = options.some(function (opt) { return opt.value === ""; });
        if (!hasEmpty) {
            select.appendChild(optionElement("", t("link_empty", "— none —"), value));
        }
        options.forEach(function (opt) {
            select.appendChild(optionElement(opt.value, opt.label, value));
        });
        select.value = value === undefined || value === null ? "" : String(value);
        bindInput(select, def);
        wrap.appendChild(select);
        return function (next) { select.value = next === undefined || next === null ? "" : String(next); };
    };

    builders.page = function (wrap, def, value) {
        var select = el("select", "sp-cz-input");
        select.appendChild(optionElement("", t("link_empty", "— none —"), value));
        state.pages.forEach(function (page) {
            if (page.private && !defArg(def, "include_private", false)) return;
            var prefix = new Array((page.level || 0) + 1).join("— ");
            select.appendChild(optionElement(page.slug, prefix + page.title, value));
        });
        select.value = value === undefined || value === null ? "" : String(value);
        bindInput(select, def);
        wrap.appendChild(select);
        return function (next) { select.value = next === undefined || next === null ? "" : String(next); };
    };

    builders.multiselect = function (wrap, def, value) {
        var select = el("select", "sp-cz-input sp-cz-multiselect");
        select.multiple = true;
        select.size = Math.min(8, Math.max(3, normalizeOptions(defArg(def, "options", [])).length || 3));
        var selected = parseList(value).map(String);
        normalizeOptions(defArg(def, "options", [])).forEach(function (opt) {
            var option = optionElement(opt.value, opt.label, "");
            option.selected = selected.indexOf(String(opt.value)) !== -1;
            select.appendChild(option);
        });
        select.addEventListener("change", function () {
            var values = [];
            select.querySelectorAll("option:checked").forEach(function (option) { values.push(option.value); });
            commit(def.key, JSON.stringify(values));
        });
        wrap.appendChild(select);
        return function (next) {
            var values = parseList(next).map(String);
            select.querySelectorAll("option").forEach(function (option) {
                option.selected = values.indexOf(String(option.value)) !== -1;
            });
        };
    };

    builders.checkbox_group = function (wrap, def, value) {
        var group = el("div", "sp-cz-choices");
        var selected = parseList(value).map(String);
        var options = normalizeOptions(defArg(def, "options", []));
        function sync() {
            commit(def.key, JSON.stringify(selected));
        }
        options.forEach(function (opt) {
            var label = el("label", "sp-cz-choice");
            var checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.value = opt.value;
            checkbox.checked = selected.indexOf(String(opt.value)) !== -1;
            checkbox.addEventListener("change", function () {
                var valueString = String(checkbox.value);
                if (checkbox.checked) {
                    if (selected.indexOf(valueString) === -1) selected.push(valueString);
                } else {
                    selected = selected.filter(function (item) { return item !== valueString; });
                }
                sync();
            });
            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(opt.label));
            group.appendChild(label);
        });
        wrap.appendChild(group);
        return function (next) {
            selected = parseList(next).map(String);
            group.querySelectorAll("input[type=checkbox]").forEach(function (checkbox) {
                checkbox.checked = selected.indexOf(String(checkbox.value)) !== -1;
            });
        };
    };

    builders.radio = function (wrap, def, value) {
        var group = el("div", "sp-cz-choices");
        var options = normalizeOptions(defArg(def, "options", []));
        options.forEach(function (opt) {
            var label = el("label", "sp-cz-choice");
            var radio = document.createElement("input");
            radio.type = "radio";
            radio.name = "sp-cz-radio-" + def.key;
            radio.value = opt.value;
            radio.checked = String(opt.value) === String(value);
            radio.addEventListener("change", function () {
                if (radio.checked) commit(def.key, radio.value);
            });
            label.appendChild(radio);
            label.appendChild(document.createTextNode(opt.label));
            group.appendChild(label);
        });
        wrap.appendChild(group);
        return function (next) {
            group.querySelectorAll("input[type=radio]").forEach(function (radio) {
                radio.checked = String(radio.value) === String(next);
            });
        };
    };

    builders.button_group = function (wrap, def, value) {
        var group = el("div", "sp-cz-button-group");
        var options = normalizeOptions(defArg(def, "options", []));
        options.forEach(function (opt) {
            var button = el("button", "sp-cz-segment", opt.label);
            button.type = "button";
            if (String(opt.value) === String(value)) button.classList.add("is-active");
            button.addEventListener("click", function () {
                group.querySelectorAll(".sp-cz-segment").forEach(function (b) { b.classList.remove("is-active"); });
                button.classList.add("is-active");
                commit(def.key, opt.value);
            });
            group.appendChild(button);
        });
        wrap.appendChild(group);
        return function (next) {
            group.querySelectorAll(".sp-cz-segment").forEach(function (button, index) {
                button.classList.toggle("is-active", String(options[index].value) === String(next));
            });
        };
    };

    builders.toggle = function (wrap, def, value) {
        var label = wrap.querySelector(".sp-cz-label");
        if (label) label.textContent = "";
        var check = el("label", "sp-cz-check");
        var input = document.createElement("input");
        input.type = "checkbox";
        input.checked = String(value) === "1" || value === true || value === "true";
        input.setAttribute("data-sp-cz-key", def.key);
        input.addEventListener("change", function () {
            commit(def.key, input.checked ? "1" : "0");
        });
        check.appendChild(input);
        check.appendChild(document.createTextNode(" " + (def.label || def.key)));
        wrap.appendChild(check);
        return function (next) {
            input.checked = String(next) === "1" || next === true || next === "true";
        };
    };

    builders.range = function (wrap, def, value) {
        var row = el("div", "sp-cz-range-row");
        var input = document.createElement("input");
        input.type = "range";
        input.value = value !== undefined && value !== null ? value : 0;
        if (defArg(def, "min") !== undefined) input.min = defArg(def, "min");
        if (defArg(def, "max") !== undefined) input.max = defArg(def, "max");
        if (defArg(def, "step") !== undefined) input.step = defArg(def, "step");
        var badge = el("span", "sp-cz-range-value", input.value);
        input.addEventListener("input", function () { badge.textContent = input.value; });
        bindInput(input, def);
        row.appendChild(input);
        row.appendChild(badge);
        wrap.appendChild(row);
        return function (next) {
            input.value = next === undefined || next === null ? 0 : next;
            badge.textContent = input.value;
        };
    };

    builders.color = function (wrap, def, value) {
        var row = el("div", "sp-cz-color-row");
        var picker = document.createElement("input");
        picker.type = "color";
        picker.value = /^#[0-9a-f]{3,6}$/i.test(value) ? value : "#000000";
        var text = el("input", "sp-cz-input");
        text.type = "text";
        text.value = value || "#000000";
        picker.addEventListener("input", function () {
            text.value = picker.value;
            commit(def.key, picker.value);
        });
        text.addEventListener("input", function () {
            if (/^#[0-9a-f]{3,6}$/i.test(text.value)) picker.value = text.value;
            commit(def.key, text.value);
        });
        row.appendChild(picker);
        row.appendChild(text);
        wrap.appendChild(row);
        return function (next) {
            text.value = next === undefined || next === null ? "" : next;
            if (/^#[0-9a-f]{3,6}$/i.test(text.value)) picker.value = text.value;
        };
    };

    builders.image = function (wrap, def, value) {
        return buildMediaControl(wrap, def, value, "image");
    };

    builders.file = function (wrap, def, value) {
        return buildMediaControl(wrap, def, value, "file");
    };

    function buildMediaControl(wrap, def, value, kind) {
        var box = el("div", "sp-cz-media");
        var preview = el("div", "sp-cz-media-preview");
        var input = el("input", "sp-cz-input");
        input.type = "text";
        input.placeholder = t("file_url", "File URL");
        input.value = value || "";
        var actions = el("div", "sp-cz-media-actions");
        var choose = el("button", "sp-cz-btn-small", t("choose", "Choose file"));
        choose.type = "button";
        var clear = el("button", "sp-cz-btn-small sp-cz-btn-ghost", t("clear", "Clear"));
        clear.type = "button";

        function updatePreview(next) {
            preview.innerHTML = "";
            if (!next) {
                preview.classList.add("is-empty");
                preview.textContent = "";
                return;
            }
            preview.classList.remove("is-empty");
            if (kind === "image" || isImageValue(next)) {
                var img = document.createElement("img");
                img.src = next;
                img.alt = "";
                img.onerror = function () {
                    preview.innerHTML = "";
                    preview.appendChild(el("span", "sp-cz-file-badge", String(next).split("/").pop() || "file"));
                };
                preview.appendChild(img);
            } else {
                var ext = String(next).split(".").pop().split(/[?#]/)[0].toUpperCase();
                preview.appendChild(el("span", "sp-cz-file-badge", ext || "FILE"));
            }
        }

        input.addEventListener("input", function () {
            updatePreview(input.value);
            commit(def.key, input.value);
        });
        choose.addEventListener("click", function () {
            openMediaModal({
                kind: kind === "file" ? "file" : "image",
                multiple: false,
                def: def,
                onSelect: function (file) {
                    input.value = file.url;
                    updatePreview(file.url);
                    commit(def.key, file.url);
                }
            });
        });
        clear.addEventListener("click", function () {
            input.value = "";
            updatePreview("");
            commit(def.key, "");
        });

        actions.appendChild(choose);
        actions.appendChild(clear);
        box.appendChild(preview);
        box.appendChild(input);
        box.appendChild(actions);
        wrap.appendChild(box);
        updatePreview(value);

        return function (next) {
            input.value = next === undefined || next === null ? "" : next;
            updatePreview(input.value);
        };
    }

    builders.gallery = function (wrap, def, value) {
        var list = parseList(value);
        var grid = el("div", "sp-cz-gallery");
        var add = el("button", "sp-cz-btn-small", t("add_images", "Add images"));
        add.type = "button";

        function sync() {
            commit(def.key, JSON.stringify(list));
        }

        function renderGrid() {
            grid.innerHTML = "";
            if (!list.length) {
                grid.appendChild(el("p", "sp-cz-gallery-empty", t("gallery_empty", "No images yet. Add your first one.")));
                return;
            }
            list.forEach(function (url, index) {
                var item = el("div", "sp-cz-gallery-item");
                var img = document.createElement("img");
                img.src = url;
                img.alt = "";
                var remove = el("button", "sp-cz-gallery-remove", "×");
                remove.type = "button";
                remove.title = t("remove", "Remove");
                remove.addEventListener("click", function () {
                    list.splice(index, 1);
                    renderGrid();
                    sync();
                });
                item.appendChild(img);
                item.appendChild(remove);
                grid.appendChild(item);
            });
        }

        add.addEventListener("click", function () {
            openMediaModal({
                kind: "image",
                multiple: true,
                def: def,
                onSelect: function (files) {
                    files.forEach(function (file) { list.push(file.url); });
                    renderGrid();
                    sync();
                }
            });
        });

        renderGrid();
        wrap.appendChild(grid);
        wrap.appendChild(add);

        return function (next) {
            list = parseList(next);
            renderGrid();
        };
    };

    builders.link = function (wrap, def, value) {
        var box = el("div", "sp-cz-link");
        var select = el("select", "sp-cz-input");
        var customWrap = el("div", "sp-cz-link-custom");
        var custom = el("input", "sp-cz-input");
        custom.type = "text";
        custom.placeholder = "https://...";
        var source = String(defArg(def, "source", "all")).toLowerCase();
        var includePrivate = !!defArg(def, "include_private", false);
        var current = value === undefined || value === null ? "" : String(value);
        var options = [];

        select.appendChild(optionElement("", t("link_empty", "— none —"), current));
        if (source !== "menu") {
            var pagesGroup = document.createElement("optgroup");
            pagesGroup.label = t("pages_group", "Pages");
            state.pages.forEach(function (page) {
                if (page.private && !includePrivate) return;
                var prefix = new Array((page.level || 0) + 1).join("— ");
                options.push({ value: page.path, url: page.url });
                pagesGroup.appendChild(optionElement(page.path, prefix + page.title, current));
            });
            if (pagesGroup.children.length) select.appendChild(pagesGroup);
        }
        if (source !== "pages") {
            var menuGroup = document.createElement("optgroup");
            menuGroup.label = t("menu_group", "Menu links");
            state.menuLinks.forEach(function (link) {
                options.push({ value: link.url, url: link.url });
                menuGroup.appendChild(optionElement(link.url, link.name, current));
            });
            if (menuGroup.children.length) select.appendChild(menuGroup);
        }
        select.appendChild(optionElement("__custom__", t("custom_url", "Custom URL…"), current));

        var matched = options.some(function (opt) {
            return sameUrl(opt.value, current) || sameUrl(opt.url, current);
        });
        if (matched) {
            var selected = options.filter(function (opt) {
                return sameUrl(opt.value, current) || sameUrl(opt.url, current);
            })[0];
            select.value = selected.value;
            customWrap.style.display = "none";
        } else {
            select.value = "__custom__";
            customWrap.style.display = current ? "block" : "none";
        }
        custom.value = current;

        select.addEventListener("change", function () {
            if (select.value === "__custom__") {
                customWrap.style.display = "block";
                custom.focus();
            } else {
                customWrap.style.display = "none";
                commit(def.key, select.value);
            }
        });
        custom.addEventListener("input", function () {
            commit(def.key, custom.value);
        });

        customWrap.appendChild(custom);
        box.appendChild(select);
        box.appendChild(customWrap);
        wrap.appendChild(box);

        return function (next) {
            var text = next === undefined || next === null ? "" : String(next);
            custom.value = text;
            var found = options.filter(function (opt) {
                return sameUrl(opt.value, text) || sameUrl(opt.url, text);
            })[0];
            if (found) {
                select.value = found.value;
                customWrap.style.display = "none";
            } else {
                select.value = "__custom__";
                customWrap.style.display = text ? "block" : "none";
            }
        };
    };

    builders.icon = function (wrap, def, value) {
        var box = el("div", "sp-cz-icon-control");
        var row = el("div", "sp-cz-icon-row");
        var preview = el("span", "sp-cz-icon-preview dashicons " + (value || "dashicons-admin-generic"));
        var input = el("input", "sp-cz-input");
        input.type = "text";
        input.value = value || "";
        input.placeholder = "dashicons-admin-generic";
        var choose = el("button", "sp-cz-btn-small", t("choose_icon", "Choose icon"));
        choose.type = "button";
        var panel = el("div", "sp-cz-icon-panel");
        panel.style.display = "none";
        var search = el("input", "sp-cz-input");
        search.type = "search";
        search.placeholder = t("search_icons", "Search icons...");
        var grid = el("div", "sp-cz-icon-grid");

        function renderIcons(filter) {
            grid.innerHTML = "";
            var q = String(filter || "").toLowerCase();
            var count = 0;
            DASHICONS.forEach(function (name) {
                if (q && name.toLowerCase().indexOf(q) === -1) return;
                var button = el("button", "sp-cz-icon-choice");
                button.type = "button";
                button.title = name;
                var icon = el("span", "dashicons " + name);
                button.appendChild(icon);
                button.addEventListener("click", function () {
                    input.value = name;
                    preview.className = "sp-cz-icon-preview dashicons " + name;
                    commit(def.key, name);
                    panel.style.display = "none";
                });
                grid.appendChild(button);
                count++;
            });
            if (!count) grid.appendChild(el("p", "sp-cz-gallery-empty", t("no_results", "No results.")));
        }

        input.addEventListener("input", function () {
            if (/^dashicons-[a-z0-9-]+$/i.test(input.value) || input.value === "") {
                preview.className = "sp-cz-icon-preview dashicons " + (input.value || "dashicons-admin-generic");
            }
            commit(def.key, input.value);
        });
        choose.addEventListener("click", function () {
            var visible = panel.style.display !== "none";
            panel.style.display = visible ? "none" : "block";
            if (!visible) {
                search.value = "";
                renderIcons("");
                search.focus();
            }
        });
        search.addEventListener("input", function () { renderIcons(search.value); });

        row.appendChild(preview);
        row.appendChild(input);
        row.appendChild(choose);
        panel.appendChild(search);
        panel.appendChild(grid);
        box.appendChild(row);
        box.appendChild(panel);
        wrap.appendChild(box);
        renderIcons("");

        return function (next) {
            input.value = next || "";
            preview.className = "sp-cz-icon-preview dashicons " + (next || "dashicons-admin-generic");
        };
    };

    builders.wysiwyg = function (wrap, def, value) {
        var textarea = el("textarea", "sp-cz-input sp-cz-wysiwyg");
        textarea.id = "sp-cz-wysiwyg-" + String(def.key).replace(/[^a-z0-9_-]/gi, "_");
        textarea.rows = parseInt(defArg(def, "rows", 7), 10) || 7;
        textarea.value = value !== undefined && value !== null ? value : "";
        textarea.setAttribute("data-sp-cz-wysiwyg", "1");
        textarea.setAttribute("data-sp-cz-key", def.key);
        textarea.addEventListener("input", function () {
            commit(def.key, textarea.value);
        });
        wrap.appendChild(textarea);
        return function (next) {
            textarea.value = next === undefined || next === null ? "" : next;
            if (window.tinymce) {
                var editor = window.tinymce.get(textarea.id);
                if (editor) editor.setContent(textarea.value);
            }
        };
    };

    builders.heading = function (wrap, def) {
        wrap.innerHTML = "";
        wrap.className = "sp-cz-heading";
        wrap.textContent = def.label || def.key;
        return function () {};
    };

    builders.divider = function (wrap) {
        wrap.innerHTML = "";
        wrap.className = "sp-cz-divider";
        return function () {};
    };

    function createControl(def) {
        var type = String(def.type || "text").toLowerCase();
        if (type === "wysywig") type = "wysiwyg";
        if (type === "colour") type = "color";
        if (type === "dropdown") type = "select";
        if (type === "checkbox") type = "toggle";
        if (type === "media") type = "image";
        if (type === "datetime") type = "datetime-local";
        if (["email", "url", "tel", "date", "time", "datetime-local", "month", "week"].indexOf(type) !== -1 && !builders[type]) {
            def.input_type = type;
        }

        var wrap = el("div", "sp-cz-control");
        wrap.setAttribute("data-sp-cz-control", def.key);

        if (type === "heading" || type === "divider") {
            (builders[type] || builders.text)(wrap, def, "");
            return wrap;
        }

        var label = el("label", "sp-cz-label", def.label || def.key);
        wrap.appendChild(label);
        if (def.description) {
            wrap.appendChild(el("p", "sp-cz-description", def.description));
        }

        var value = state.values[def.key];
        var setter = (builders[type] || builders.text)(wrap, def, value);
        if (typeof setter === "function") {
            controlSetters[def.key] = setter;
        }
        return wrap;
    }

    function render() {
        if (!sectionsEl) return;
        sectionsEl.innerHTML = "";
        controlSetters = {};
        if (!state.defs.length) {
            sectionsEl.appendChild(el("p", "sp-cz-empty", cfg.labels.empty));
            return;
        }

        var bySection = {};
        state.defs.forEach(function (def) {
            var section = def.section || "general";
            if (!bySection[section]) bySection[section] = [];
            bySection[section].push(def);
        });

        var order = state.sections.map(function (s) { return s.id; });
        Object.keys(bySection).forEach(function (id) {
            if (order.indexOf(id) === -1) order.push(id);
        });

        order.forEach(function (id) {
            if (!bySection[id] || !bySection[id].length) return;
            var sectionLabel = id;
            state.sections.forEach(function (s) {
                if (s.id === id) sectionLabel = s.label || id;
            });
            var details = document.createElement("details");
            details.className = "sp-cz-section";
            details.open = true;
            details.setAttribute("data-sp-cz-section", id);
            var summary = document.createElement("summary");
            summary.textContent = sectionLabel;
            details.appendChild(summary);
            var body = el("div", "sp-cz-section-body");
            bySection[id].forEach(function (def) {
                body.appendChild(createControl(def));
            });
            details.appendChild(body);
            sectionsEl.appendChild(details);
        });

        initWysiwyg();
    }

    function loadTinymce(callback) {
        if (!cfg.tinymce) return;
        if (window.tinymce) {
            callback();
            return;
        }
        tinymceCallbacks.push(callback);
        if (tinymceLoading) return;
        tinymceLoading = true;
        var script = document.createElement("script");
        script.src = cfg.tinymce;
        script.onload = function () {
            tinymceLoading = false;
            var callbacks = tinymceCallbacks.slice();
            tinymceCallbacks = [];
            callbacks.forEach(function (cb) { cb(); });
        };
        script.onerror = function () {
            tinymceLoading = false;
            tinymceCallbacks = [];
        };
        document.head.appendChild(script);
    }

    function initWysiwyg() {
        var areas = root.querySelectorAll("[data-sp-cz-wysiwyg]");
        if (!areas.length) return;
        loadTinymce(function () {
            if (!window.tinymce) return;
            areas.forEach(function (textarea) {
                if (textarea.dataset.spCzEditorReady === "1") return;
                textarea.dataset.spCzEditorReady = "1";
                window.tinymce.init({
                    target: textarea,
                    menubar: false,
                    plugins: "lists link code",
                    toolbar: "bold italic bullist numlist link code",
                    height: 220,
                    license_key: "gpl",
                    promotion: false,
                    branding: false,
                    setup: function (editor) {
                        editor.on("change keyup input undo redo", function () {
                            commit(textarea.getAttribute("data-sp-cz-key"), editor.getContent());
                        });
                    }
                });
            });
        });
    }

    function destroyWysiwyg() {
        if (window.tinymce && window.tinymce.remove) {
            try { window.tinymce.remove(); } catch (e) {}
        }
        root.querySelectorAll("[data-sp-cz-wysiwyg]").forEach(function (textarea) {
            textarea.dataset.spCzEditorReady = "";
        });
    }

    function load() {
        setStatus("");
        destroyWysiwyg();
        request("load", {}, "GET").then(function (res) {
            state.defs = res.defs || [];
            state.sections = res.sections || [];
            state.pages = res.pages || [];
            state.menuLinks = res.menu_links || [];
            state.values = {};
            state.defs.forEach(function (def) {
                state.values[def.key] = def.value !== undefined ? def.value : (def.default !== undefined ? def.default : "");
            });
            if (res.preview) cfg.preview = res.preview;
            render();
            reloadPreview();
        }).catch(function (error) {
            setStatus(error.message || cfg.labels.error);
        });
    }

    function open() {
        state.open = true;
        state.dirty = false;
        root.classList.add("sp-cz-open");
        root.setAttribute("aria-hidden", "false");
        document.body.classList.add("sp-cz-open");
        request("preview_clear", {}, "POST").catch(function () {});
        load();
    }

    function close() {
        if (state.dirty && !window.confirm(cfg.labels.unsaved)) {
            return;
        }
        closeMediaModal();
        destroyWysiwyg();
        state.open = false;
        state.dirty = false;
        root.classList.remove("sp-cz-open");
        root.setAttribute("aria-hidden", "true");
        document.body.classList.remove("sp-cz-open");
        if (iframe) iframe.src = "about:blank";
        if (/[?&]sp_customizer=1(?:&|$)/.test(window.location.search)) {
            window.history.replaceState({}, "", window.location.pathname);
        }
        request("preview_clear", {}, "POST").catch(function () {});
    }

    function publish() {
        if (state.saving) return;
        state.saving = true;
        setStatus(cfg.labels.publishing);
        if (publishBtn) publishBtn.disabled = true;
        request("save", { values: JSON.stringify(state.values) }, "POST").then(function () {
            state.dirty = false;
            setStatus(cfg.labels.published);
            reloadPreview();
            window.setTimeout(function () {
                if (publishBtn) publishBtn.disabled = false;
                state.saving = false;
            }, 800);
        }).catch(function (error) {
            setStatus(error.message || cfg.labels.error);
            if (publishBtn) publishBtn.disabled = false;
            state.saving = false;
        });
    }

    function resetDefaults() {
        state.defs.forEach(function (def) {
            var value = def.default !== undefined ? def.default : "";
            state.values[def.key] = value;
            if (controlSetters[def.key]) {
                controlSetters[def.key](value);
                return;
            }
            root.querySelectorAll('[data-sp-cz-key="' + def.key + '"]').forEach(function (input) {
                if (input.type === "checkbox") {
                    input.checked = String(value) === "1";
                } else {
                    input.value = value;
                }
            });
        });
        markDirty();
        schedulePreview();
    }

    function filterControls() {
        var q = (searchEl ? searchEl.value : "").toLowerCase().trim();
        root.querySelectorAll(".sp-cz-control").forEach(function (control) {
            var text = control.textContent.toLowerCase();
            control.style.display = !q || text.indexOf(q) !== -1 ? "" : "none";
        });
    }

    /* ------------------------------------------------------------------
     * Media modal
     * ------------------------------------------------------------------ */
    function ensureMediaModal() {
        if (mediaModal) return mediaModal;
        var modal = el("div", "sp-cz-modal");
        modal.setAttribute("aria-hidden", "true");
        modal.innerHTML = '' +
            '<div class="sp-cz-modal-backdrop" data-sp-cz-close="1"></div>' +
            '<div class="sp-cz-modal-panel" role="dialog" aria-modal="true">' +
            '  <div class="sp-cz-modal-head">' +
            '    <h3 class="sp-cz-modal-title"></h3>' +
            '    <button type="button" class="sp-cz-modal-x" data-sp-cz-close="1" aria-label="Close">&times;</button>' +
            '  </div>' +
            '  <div class="sp-cz-modal-tabs">' +
            '    <button type="button" class="sp-cz-modal-tab is-active" data-sp-cz-tab="library"></button>' +
            '    <button type="button" class="sp-cz-modal-tab" data-sp-cz-tab="upload"></button>' +
            '  </div>' +
            '  <div class="sp-cz-modal-body">' +
            '    <div class="sp-cz-modal-pane" data-sp-cz-pane="library"><div class="sp-cz-media-list"></div></div>' +
            '    <div class="sp-cz-modal-pane" data-sp-cz-pane="upload" hidden>' +
            '      <div class="sp-cz-dropzone">' +
            '        <input type="file" class="sp-cz-upload-input">' +
            '        <p class="sp-cz-dropzone-title"></p>' +
            '        <p class="sp-cz-dropzone-hint"></p>' +
            '        <div class="sp-cz-progress"><span></span></div>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="sp-cz-modal-error" style="display:none"></div>' +
            '  <div class="sp-cz-modal-foot">' +
            '    <button type="button" class="sp-cz-btn" data-sp-cz-close="1"></button>' +
            '    <button type="button" class="sp-cz-btn sp-cz-btn-primary sp-cz-media-insert" disabled></button>' +
            '  </div>' +
            '</div>';

        modal.querySelector(".sp-cz-modal-title").textContent = t("media_title", "Choose or upload media");
        modal.querySelector('[data-sp-cz-tab="library"]').textContent = t("library", "Media library");
        modal.querySelector('[data-sp-cz-tab="upload"]').textContent = t("upload", "Upload file");
        modal.querySelector(".sp-cz-dropzone-title").textContent = t("upload", "Upload file");
        modal.querySelector(".sp-cz-dropzone-hint").textContent = t("upload_hint", "Drop a file here or click to choose from disk.");
        modal.querySelector('.sp-cz-modal-foot [data-sp-cz-close="1"]').textContent = t("cancel", "Cancel");
        modal.querySelector(".sp-cz-media-insert").textContent = t("insert", "Insert");

        var refs = {
            list: modal.querySelector(".sp-cz-media-list"),
            error: modal.querySelector(".sp-cz-modal-error"),
            insert: modal.querySelector(".sp-cz-media-insert"),
            input: modal.querySelector(".sp-cz-upload-input"),
            dropzone: modal.querySelector(".sp-cz-dropzone"),
            progress: modal.querySelector(".sp-cz-progress"),
            progressBar: modal.querySelector(".sp-cz-progress span"),
            library: modal.querySelector('[data-sp-cz-pane="library"]'),
            upload: modal.querySelector('[data-sp-cz-pane="upload"]')
        };

        modal.addEventListener("click", function (event) {
            var closeEl = event.target.closest("[data-sp-cz-close]");
            if (closeEl) {
                closeMediaModal();
                return;
            }
            var tab = event.target.closest("[data-sp-cz-tab]");
            if (tab) switchMediaTab(tab.getAttribute("data-sp-cz-tab"));
        });
        refs.insert.addEventListener("click", confirmMedia);
        refs.input.addEventListener("change", function () {
            if (this.files && this.files[0]) uploadMediaFile(this.files[0]);
        });
        refs.dropzone.addEventListener("click", function (event) {
            if (event.target !== refs.input) refs.input.click();
        });
        refs.dropzone.addEventListener("dragover", function (event) { event.preventDefault(); });
        refs.dropzone.addEventListener("drop", function (event) {
            event.preventDefault();
            if (event.dataTransfer.files && event.dataTransfer.files[0]) uploadMediaFile(event.dataTransfer.files[0]);
        });

        root.appendChild(modal);
        mediaModal = { el: modal, refs: refs };
        return mediaModal;
    }

    function switchMediaTab(name) {
        if (!mediaModal) return;
        mediaModal.el.querySelectorAll("[data-sp-cz-tab]").forEach(function (tab) {
            tab.classList.toggle("is-active", tab.getAttribute("data-sp-cz-tab") === name);
        });
        mediaModal.refs.library.hidden = name !== "library";
        mediaModal.refs.upload.hidden = name !== "upload";
    }

    function openMediaModal(opts) {
        ensureMediaModal();
        mediaState = {
            kind: opts.kind || "all",
            multiple: !!opts.multiple,
            selected: [],
            onSelect: opts.onSelect,
            def: opts.def
        };
        mediaModal.refs.error.style.display = "none";
        mediaModal.refs.error.textContent = "";
        mediaModal.refs.list.innerHTML = "";
        mediaModal.refs.input.value = "";
        mediaModal.refs.input.accept = mediaState.kind === "image" ? "image/*" : "";
        mediaModal.refs.insert.disabled = true;
        mediaModal.refs.insert.textContent = mediaState.multiple ? t("insert", "Insert") : t("insert", "Insert");
        mediaModal.el.classList.add("sp-cz-modal-open");
        mediaModal.el.setAttribute("aria-hidden", "false");
        switchMediaTab("library");
        loadLibrary();
    }

    function closeMediaModal() {
        if (!mediaModal) return;
        mediaModal.el.classList.remove("sp-cz-modal-open");
        mediaModal.el.setAttribute("aria-hidden", "true");
        mediaState = null;
    }

    function showMediaError(message) {
        if (!mediaModal) return;
        mediaModal.refs.error.textContent = message || "";
        mediaModal.refs.error.style.display = message ? "block" : "none";
    }

    function loadLibrary(autoSelectName) {
        if (!mediaModal || !mediaState) return;
        var refs = mediaModal.refs;
        refs.list.innerHTML = '<p class="sp-cz-media-loading">' + t("loading", "Loading...") + '</p>';
        request("media_list", { kind: mediaState.kind }, "GET").then(function (res) {
            refs.list.innerHTML = "";
            var files = res.files || [];
            if (!files.length) {
                refs.list.appendChild(el("p", "sp-cz-media-empty", t("no_media", "No files in the library.")));
                return;
            }
            files.forEach(function (file) {
                var item = el("div", "sp-cz-media-item");
                item.setAttribute("data-sp-cz-name", file.name);
                if (file.type === "image") {
                    var img = document.createElement("img");
                    img.src = file.thumb || file.url;
                    img.alt = file.name;
                    img.onerror = function () { this.src = file.url; };
                    item.appendChild(img);
                } else {
                    item.appendChild(el("div", "sp-cz-media-ext", (file.ext || "file").toUpperCase()));
                }
                item.appendChild(el("div", "sp-cz-media-name", file.name));
                item.addEventListener("click", function () {
                    var already = mediaState.selected.some(function (selected) { return selected.name === file.name; });
                    if (mediaState.multiple) {
                        if (already) {
                            mediaState.selected = mediaState.selected.filter(function (selected) { return selected.name !== file.name; });
                            item.classList.remove("is-selected");
                        } else {
                            mediaState.selected.push(file);
                            item.classList.add("is-selected");
                        }
                    } else {
                        refs.list.querySelectorAll(".sp-cz-media-item").forEach(function (node) { node.classList.remove("is-selected"); });
                        item.classList.add("is-selected");
                        mediaState.selected = [file];
                    }
                    refs.insert.disabled = mediaState.selected.length === 0;
                });
                if (autoSelectName && file.name === autoSelectName) {
                    item.classList.add("is-selected");
                    mediaState.selected = [file];
                }
                refs.list.appendChild(item);
            });
            if (autoSelectName) {
                refs.insert.disabled = mediaState.selected.length === 0;
            }
        }).catch(function (error) {
            refs.list.innerHTML = "";
            refs.list.appendChild(el("p", "sp-cz-media-empty", t("media_error", "Could not load the library.")));
            showMediaError(error.message || t("media_error", "Could not load the library."));
        });
    }

    function confirmMedia() {
        if (!mediaState || !mediaState.selected.length || typeof mediaState.onSelect !== "function") return;
        var selected = mediaState.selected.slice();
        mediaState.onSelect(mediaState.multiple ? selected : selected[0]);
        closeMediaModal();
    }

    function uploadMediaFile(file) {
        if (!mediaModal || !mediaState) return;
        var refs = mediaModal.refs;
        showMediaError("");
        refs.progress.style.display = "block";
        refs.progressBar.style.width = "0%";
        var form = new FormData();
        form.append("sp_nonce", cfg.nonce);
        form.append("kind", mediaState.kind);
        form.append("file", file);
        var xhr = new XMLHttpRequest();
        xhr.open("POST", cfg.endpoint + "media_upload");
        xhr.upload.onprogress = function (event) {
            if (event.lengthComputable) refs.progressBar.style.width = ((event.loaded / event.total) * 100) + "%";
        };
        xhr.onload = function () {
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) {}
            refs.progress.style.display = "none";
            if (!mediaModal || !mediaState) return;
            if (xhr.status >= 200 && xhr.status < 300 && res && res.status === "success") {
                switchMediaTab("library");
                mediaState.selected = [];
                loadLibrary(res.file.name);
            } else {
                showMediaError((res && res.message) || t("media_error", "Could not upload the file."));
            }
        };
        xhr.onerror = function () {
            refs.progress.style.display = "none";
            showMediaError(t("media_error", "Could not upload the file."));
        };
        xhr.send(form);
    }

    /* ------------------------------------------------------------------
     * Events
     * ------------------------------------------------------------------ */
    var closeButton = root.querySelector(".sp-cz-close");
    if (closeButton) closeButton.addEventListener("click", close);
    if (publishBtn) publishBtn.addEventListener("click", publish);
    if (resetBtn) resetBtn.addEventListener("click", resetDefaults);
    if (searchEl) searchEl.addEventListener("input", filterControls);

    root.querySelectorAll(".sp-cz-device").forEach(function (button) {
        button.addEventListener("click", function () {
            root.querySelectorAll(".sp-cz-device").forEach(function (b) { b.classList.remove("sp-cz-device-active"); });
            button.classList.add("sp-cz-device-active");
            var width = button.getAttribute("data-width") || "100%";
            if (iframe) {
                iframe.style.width = width;
                iframe.style.maxWidth = "100%";
            }
        });
    });

    document.addEventListener("click", function (event) {
        var link = event.target.closest("[data-sp-customizer]");
        if (link) {
            event.preventDefault();
            open();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (mediaModal && mediaModal.el.classList.contains("sp-cz-modal-open") && event.key === "Escape") {
            event.preventDefault();
            closeMediaModal();
            return;
        }
        if (!state.open) return;
        if (event.key === "Escape") {
            event.preventDefault();
            close();
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
            event.preventDefault();
            publish();
        }
    });

    if (/(?:^|[?&])sp_customizer=1(?:&|$)/.test(window.location.search)) {
        open();
    }
})();
