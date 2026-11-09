document.addEventListener("DOMContentLoaded", function () {
  const headerWrapper = document.querySelector(".header .wrapper");
  const nav = document.querySelector(".header .wrapper .nav, .wrapper .nav, ul.nav");
  const t = window.WP_I18N || {};

  // 1. OBSŁUGA ZWIJANIA MENU (DESKTOP) ORAZ ETYKIET I PODMENU
  if (nav) {
    nav.querySelectorAll("li a").forEach(function (link) {
      if (!link.querySelector(".wp-nav-label")) {
        const labelSpan = document.createElement("span");
        labelSpan.className = "wp-nav-label";
        while (link.firstChild) {
          labelSpan.appendChild(link.firstChild);
        }
        link.appendChild(labelSpan);
      }
      if (link.querySelector(".dashicons, svg, img")) {
        link.classList.add("sp-has-inline-icon");
      }
    });

    // Przeniesienie SimplePress i Dashboardu na górę menu.
    const simplePressTab = nav.querySelector(".wp-menu-simplepress");
    if (simplePressTab) {
      nav.insertBefore(simplePressTab, nav.firstChild);
    }
    const dashboardTab = nav.querySelector(".wp-menu-dashboard");
    if (dashboardTab) {
      nav.insertBefore(dashboardTab, simplePressTab ? simplePressTab.nextSibling : nav.firstChild);
    }

    // Najpierw bezwzględnie czścimy stare klasy podświetleń z szablonu GetSimple
    nav.querySelectorAll("li, a").forEach(function (el) {
      el.classList.remove("current");
    });

    // Precyzyjne dopasowanie aktywnego elementu (najdłuższy pasujący URL ma pierwszeństwo)
    const currentPath = window.location.pathname.split("/").pop().toLowerCase();
    const currentQuery = window.location.search.toLowerCase();
    const pathMap = {
      'edit.php': 'pages.php', 'menu-manager.php': 'pages.php',
      'upload.php': 'upload.php', 'image.php': 'upload.php', 'filebrowser.php': 'upload.php',
      'theme.php': 'theme.php', 'theme-edit.php': 'theme.php', 'components.php': 'theme.php', 'sitemap.php': 'theme.php', 'navigation.php': 'theme.php',
      'backups.php': 'backups.php', 'backup-edit.php': 'backups.php', 'archive.php': 'backups.php',
      'plugins.php': 'plugins.php',
      'settings.php': 'settings.php',
      'support.php': 'support.php', 'log.php': 'support.php', 'log-error.php': 'support.php', 'health-check.php': 'support.php', 'phpinfo.php': 'support.php', 'salt-shaker.php': 'support.php'
    };
    
    let bestMatchLink = null;
    let bestMatchScore = -1;

    const wantedPath = pathMap[currentPath] || currentPath;
    nav.querySelectorAll("li a").forEach(function (link) {
      const href = link.getAttribute("href");
      if (!href || href === "#" || href.startsWith("javascript")) return;

      const linkParts = href.split("?");
      const linkPath = linkParts[0].toLowerCase();
      if (linkPath === 'load.php' && currentPath === 'load.php') {
        const linkQuery = linkParts[1] ? linkParts[1].toLowerCase() : '';
        const queryParams = linkQuery.split('&').filter(Boolean);
        let all = queryParams.length > 0;
        queryParams.forEach(function (param) { if (!currentQuery.includes(param)) all = false; });
        if (all && queryParams.length > 0) {
          bestMatchLink = link;
          bestMatchScore = 10;
        }
        return;
      }
      const linkQuery = linkParts[1] ? linkParts[1].toLowerCase() : "";

      let score = 0;
      // Sprawdzenie dopasowania pliku (np. load.php, edit.php)
      if (wantedPath === linkPath || (wantedPath === "" && linkPath === "index.php")) {
        score += 1;
        // Dokładniejsze dopasowanie parametrów GET (np. sub=plugin-repo lub id=wp_simple_admin)
        if (linkQuery) {
          const queryParams = linkQuery.split("&");
          let allParamsMatch = true;
          queryParams.forEach(param => {
            if (!currentQuery.includes(param)) {
              allParamsMatch = false;
            }
          });
          if (allParamsMatch) {
            score += 5; // Wyższy priorytet dla pasujących parametrów URL
          } else {
            score = -1; // Odrzucamy, jeśli parametry GET się nie zgadzają
          }
        } else if (!currentQuery && !linkQuery) {
          score += 2;
        }
      }

      if (score > 0 && score > bestMatchScore) {
        bestMatchScore = score;
        bestMatchLink = link;
      }
    });

    if (!bestMatchLink && currentPath === 'load.php' && currentQuery.indexOf('id=simplepress') !== -1) {
      bestMatchLink = nav.querySelector('.wp-menu-simplepress a');
      bestMatchScore = 1;
    }

    // Podświetlamy wyłącznie najlepsze dopasowanie
    if (bestMatchLink && bestMatchScore > 0) {
      bestMatchLink.classList.add("current");
      const parentLi = bestMatchLink.closest("li");
      if (parentLi) {
        parentLi.classList.add("current");
      }

      // Jeśli element jest w podmenu (snav), podświetl też jego głównego rodzica
      const parentSubNav = bestMatchLink.closest("ul.snav");
      if (parentSubNav) {
        const parentCategoryLi = parentSubNav.closest("li.parent");
        if (parentCategoryLi) {
          parentCategoryLi.classList.add("current");
          const mainCategoryLink = parentCategoryLi.querySelector("a");
          if (mainCategoryLink) {
            mainCategoryLink.classList.add("current");
          }
        }
      }
    }

    // Oznaczenie klasą .parent elementów posiadających podmenu
    nav.querySelectorAll("li").forEach(function (li) {
      if (li.querySelector("ul.snav")) {
        li.classList.add("parent");
      }
    });

    // Move the section submenu into the active top-level item, WordPress-style.
    const sidebar = document.querySelector("#sidebar");
    // edit.php and upload.php build their own sidebars; never hide them or move their extras away.
    const isEditScreen = document.body.id === "edit" || !!document.querySelector("#editform");
    const isMediaScreen = document.body.id === "upload" || /\/upload\.php$/.test(window.location.pathname);
    if (sidebar && bestMatchLink) {
      const activeLi = bestMatchLink.closest("li");
      const snav = sidebar.querySelector("ul.snav");
      if (activeLi && snav) {
        snav.className = "snav wp-sub-menu";
        activeLi.appendChild(snav);
        activeLi.classList.add("parent", "wp-has-current-submenu");
        if (!isEditScreen && !isMediaScreen) {
          document.body.classList.add("wp-slideout-active");
        }
      }
      if (!isEditScreen && !isMediaScreen) {
        // Preserve extra sidebar content (uploads, component list) by moving it into the content.
        const extras = [];
        Array.prototype.slice.call(sidebar.children).forEach(function (child) {
          if (child.tagName === "SCRIPT" || child.tagName === "STYLE" || child.tagName === "NOSCRIPT") return;
          if (child.classList && (child.classList.contains("snav") || child.classList.contains("uploadform"))) return;
          extras.push(child);
        });
        if (extras.length) {
          const card = document.createElement("div");
          card.className = "postbox sp-sidebar-extra";
          const inside = document.createElement("div");
          inside.className = "inside";
          extras.forEach(function (child) { child.parentNode.removeChild(child); inside.appendChild(child); });
          card.appendChild(inside);
          const main = document.querySelector("#maincontent");
          if (main) main.insertBefore(card, main.firstChild);
        }
        const uploads = sidebar.querySelectorAll("form.uploadform");
        if (uploads.length) {
          const card = document.createElement("div");
          card.className = "postbox sp-sidebar-extra";
          const inside = document.createElement("div");
          inside.className = "inside";
          uploads.forEach(function (form) { form.parentNode.removeChild(form); inside.appendChild(form); });
          card.appendChild(inside);
          const main = document.querySelector("#maincontent");
          if (main) main.insertBefore(card, main.firstChild);
        }
      }
    }

    if (!document.querySelector("#collapse-menu")) {
      const collapseBtn = document.createElement("button");
      collapseBtn.id = "collapse-menu";
      collapseBtn.type = "button";
      
      const collapseText = t.COLLAPSE_MENU || t.SIDEBAR_COLLAPSE || "Collapse menu";

      collapseBtn.innerHTML = `<span class="wp-nav-label">${collapseText}</span>`;
      nav.appendChild(collapseBtn);

      if (localStorage.getItem("wp_sidebar_folded") === "true") {
        document.body.classList.add("folded");
      }

      collapseBtn.addEventListener("click", function () {
        document.body.classList.toggle("folded");
        localStorage.setItem("wp_sidebar_folded", document.body.classList.contains("folded"));
      });
    }
  }
  // 2. GÓRNY PASEK (ADMIN BAR) + HAMBURGER MOBILNY
  if (headerWrapper) {
    const oldPill = document.querySelector("#pill");
    const oldH1 = headerWrapper.querySelector("h1");
    let siteName = "GetSimple";

    if (oldH1 && oldH1.querySelector("a")) {
      siteName = oldH1.querySelector("a").textContent.trim();
    }

    let username = window.SP_USER || "Admin";
    let logoutUrl = "logout.php";
    let hasDebug = false;

    if (oldPill) {
      if (oldPill.querySelector(".debug, [class*='debug']")) { hasDebug = true; }
      const logoutA = oldPill.querySelector("a[href*='logout']");
      if (logoutA) { logoutUrl = logoutA.getAttribute("href"); }

      const match = oldPill.textContent.match(/Welcome\s+([a-zA-Z0-9_-]+)/i);
      if (match && match[1]) { username = match[1]; }
      oldPill.remove();
    }

    if (oldH1) { oldH1.remove(); }

    const leftGroup = document.createElement("div");
    leftGroup.className = "header-left-group";
    leftGroup.innerHTML = `
      <button type="button" id="wp-mobile-menu-toggle" aria-label="${t.MENU || "Menu"}">
        <span class="dashicons dashicons-menu-alt3"></span>
      </button>
      <a href="load.php?id=simplepress&amp;sub=dashboard" class="wp-logo-icon" title="SimplePress"><img src="${(window.SP_PLUGIN_URL || "plugins/simplepress/")}img/sp.svg" alt="SP" style="width:22px;height:22px;display:block;object-fit:contain"></a>
      <a href="../" target="_blank" class="wp-site-name"><span class="dashicons dashicons-admin-home"></span> <span class="site-name-text">${siteName}</span></a>
      <a href="edit.php" class="header-quick-add"><span class="dashicons dashicons-plus"></span> <span class="quick-add-text">${t.CREATE_NEW_PAGE || "New"}</span></a>
    `;

    const rightGroup = document.createElement("div");
    rightGroup.className = "header-right-group";
    rightGroup.innerHTML = `
      ${hasDebug ? '<span class="wp-debug-badge">DEBUG</span>' : ''}
      <a href="load.php?id=simplepress&amp;sub=account" class="wp-user-profile">
        <span class="user-welcome-text">${t.WELCOME || "Howdy"}, <strong>${username}</strong></span>
        <span class="dashicons dashicons-admin-users"></span>
      </a>
      <a href="${logoutUrl}" class="wp-logout-link" title="${t.LOGOUT || "Log Out"}">
        <span class="dashicons dashicons-migrate"></span>
      </a>
    `;

    headerWrapper.insertBefore(rightGroup, headerWrapper.firstChild);
    headerWrapper.insertBefore(leftGroup, headerWrapper.firstChild);

    let overlay = document.querySelector("#wp-responsive-overlay");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.id = "wp-responsive-overlay";
      document.body.appendChild(overlay);
    }

    const mobileToggle = document.querySelector("#wp-mobile-menu-toggle");
    if (mobileToggle) {
      mobileToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        document.body.classList.toggle("wp-mobile-menu-open");
      });

      overlay.addEventListener("click", function () {
        document.body.classList.remove("wp-mobile-menu-open");
      });

      if (nav) {
        nav.querySelectorAll("li a").forEach(function (a) {
          a.addEventListener("click", function () {
            if (window.innerWidth <= 782) {
              document.body.classList.remove("wp-mobile-menu-open");
            }
          });
        });
      }
    }
  }

  // 3. EKRAN EDYCJI STRONY (edit.php)
  const editForm = document.querySelector("#editform, form[name='create'], form[action*='changedata.php']");
  if (editForm) {
    document.body.classList.add("wp-edit-screen");

    const mainContent = document.querySelector("#maincontent");
    const sideContent = document.querySelector("#sidebar");

    if (mainContent && sideContent && editForm.contains(mainContent)) {
      editForm.appendChild(sideContent);
    }

    const headerButtons = document.querySelectorAll(".edit-nav, #maincontent h3 a.button, #maincontent h2 a.button");
    headerButtons.forEach(el => el.remove());

    const slugInput = document.querySelector("input[name='post-id'], input#post-id");
    const titleInput = document.querySelector("input.title");
    if (titleInput && slugInput) {
      const oldPerm = document.querySelector(".wp-permalink-container");
      if (oldPerm) oldPerm.remove();

      const permalinkDiv = document.createElement("div");
      permalinkDiv.className = "wp-permalink-container";
      permalinkDiv.innerHTML = `<strong>${t.PERMALINK || "Permalink"}:</strong> <a href="${window.location.origin}/${slugInput.value}" target="_blank" class="wp-permalink-url">${window.location.origin}/${slugInput.value}</a>`;
      titleInput.parentNode.insertBefore(permalinkDiv, titleInput.nextSibling);
    }

    const backupP = document.querySelector("p.backuplink");
    let backupLinkHtml = "";
    if (backupP) {
      const bLink = backupP.querySelector("a");
      if (bLink) {
        backupLinkHtml = ` (<a href="${bLink.href}" class="wp-backup-browse-link">${bLink.textContent}</a>)`;
      }
    }

    if (sideContent) {
      sideContent.innerHTML = "";

      const publishBox = document.createElement("div");
      publishBox.className = "postbox wp-metabox";
      publishBox.innerHTML = `
        <div class="postbox-header">
          <h2>${t.PUBLISH || "Publish"}</h2>
          <button type="button" class="handlediv"><span class="toggle-indicator"></span></button>
        </div>
        <div class="inside">
          <div class="misc-pub-section misc-pub-post-status">
            <span class="dashicons dashicons-post-status"></span> ${t.STATUS || "Status"}: <strong>${t.PUBLISHED || "Published"}</strong>
          </div>
          <div class="misc-pub-section misc-pub-visibility">
            <span class="dashicons dashicons-visibility"></span> ${t.KEEP_PRIVATE || "Visibility"}: <strong>${t.PUBLIC || "Public"}</strong>
          </div>
          <div class="misc-pub-section misc-pub-revisions">
            <span class="dashicons dashicons-backup"></span> ${t.PAGE_REVISION || "Revision"}: <strong>${t.CURRENT || "Current"}</strong>${backupLinkHtml}
          </div>
          <div class="misc-pub-actions">
            <div id="publishing-action">
              <button type="button" id="wp-save-btn" class="button button-primary button-large">${t.BTN_SAVEUPDATES || "Save Updates"}</button>
            </div>
            <div id="delete-action"></div>
            <div class="clear"></div>
          </div>
        </div>
      `;
      sideContent.appendChild(publishBox);

      const originalSubmit = document.querySelector("#page_submit, input[name='submitted']");
      const saveBtn = publishBox.querySelector("#wp-save-btn");
      if (saveBtn) {
        saveBtn.addEventListener("click", function (e) {
          e.preventDefault();
          if (originalSubmit) {
            originalSubmit.click();
          } else {
            editForm.submit();
          }
        });
      }

      const deleteLink = document.querySelector("a.cancel, a[href*='delete']");
      if (deleteLink) {
        deleteLink.className = "submitdelete deletion";
        deleteLink.textContent = t.MOVE_TO_TRASH || "Move to Trash";
        publishBox.querySelector("#delete-action").appendChild(deleteLink);
      }

      const pageType = document.querySelector("select[name='post-template'], select#post-template");
      const pageParent = document.querySelector("select[name='post-parent'], select#post-parent");
      const pageMenuOrder = document.querySelector("input[name='post-menu-order'], input#post-menu-order");

      if (pageType || pageParent || pageMenuOrder) {
        const attrBox = document.createElement("div");
        attrBox.className = "postbox wp-metabox";
        attrBox.innerHTML = `
          <div class="postbox-header">
            <h2>${t.PAGE_ATTRIBUTES || "Page Attributes"}</h2>
            <button type="button" class="handlediv"><span class="toggle-indicator"></span></button>
          </div>
          <div class="inside" id="wp-attr-inside"></div>
        `;
        sideContent.appendChild(attrBox);
        const attrInside = attrBox.querySelector("#wp-attr-inside");

        if (pageParent) {
          pageParent.setAttribute("form", editForm.id || "editform");
          const pWrap = document.createElement("p");
          pWrap.innerHTML = `<label class="post-attributes-label"><strong>${t.PARENT_PAGE || "Page Parent"}:</strong></label>`;
          pWrap.appendChild(pageParent);
          attrInside.appendChild(pWrap);
        }
        if (pageType) {
          pageType.setAttribute("form", editForm.id || "editform");
          const tWrap = document.createElement("p");
          tWrap.innerHTML = `<label class="post-attributes-label"><strong>${t.TEMPLATE || "Page Template"}:</strong></label>`;
          tWrap.appendChild(pageType);
          attrInside.appendChild(tWrap);
        }
        if (pageMenuOrder) {
          pageMenuOrder.setAttribute("form", editForm.id || "editform");
          const oWrap = document.createElement("p");
          oWrap.innerHTML = `<label class="post-attributes-label"><strong>${t.PRIORITY || "Priority"}:</strong></label>`;
          oWrap.appendChild(pageMenuOrder);
          attrInside.appendChild(oWrap);
        }
      }
    }

    const metaWindow = document.querySelector("#metadata_window");
    if (metaWindow && mainContent && sideContent) {
      const advancedBox = document.createElement("div");
      advancedBox.className = "postbox wp-metabox wp-options-box";
      advancedBox.innerHTML = `
        <div class="postbox-header">
          <h2>${t.PAGE_OPTIONS || "Page Options"}</h2>
          <div class="postbox-actions-group">
            <button type="button" class="wp-toggle-position-btn" title="${t.MOVE || "Move"}">
              <span class="dashicons dashicons-move"></span> <span class="pos-text">${t.MOVE || "Move"}</span>
            </button>
            <button type="button" class="handlediv"><span class="toggle-indicator"></span></button>
          </div>
        </div>
        <div class="inside" id="wp-advanced-inside"></div>
      `;

      metaWindow.style.display = "block";
      metaWindow.style.border = "none";
      metaWindow.style.background = "transparent";
      metaWindow.style.padding = "0";

      metaWindow.querySelectorAll("input, select, textarea").forEach(function (el) {
        el.setAttribute("form", editForm.id || "editform");
      });

      advancedBox.querySelector("#wp-advanced-inside").appendChild(metaWindow);

      function setOptionsPosition(pos) {
        if (pos === "sidebar") {
          sideContent.appendChild(advancedBox);
          advancedBox.classList.add("in-sidebar");
          advancedBox.classList.remove("in-bottom");
        } else {
          mainContent.appendChild(advancedBox);
          advancedBox.classList.add("in-bottom");
          advancedBox.classList.remove("in-sidebar");
        }
        localStorage.setItem("wp_page_options_pos", pos);
      }

      const savedPos = localStorage.getItem("wp_page_options_pos") || "bottom";
      setOptionsPosition(savedPos);

      advancedBox.querySelector(".wp-toggle-position-btn").addEventListener("click", function (e) {
        e.stopPropagation();
        const currentPos = advancedBox.classList.contains("in-sidebar") ? "sidebar" : "bottom";
        setOptionsPosition(currentPos === "sidebar" ? "bottom" : "sidebar");
      });
    }

    document.querySelectorAll(".postbox-header").forEach(function (headerEl) {
      headerEl.addEventListener("click", function (e) {
        if (e.target.closest(".wp-toggle-position-btn")) return;
        this.closest(".postbox").classList.toggle("closed");
      });
    });
  }
});


