# SimplePress

<p align="center">
  <img src="simplepress/img/sp.svg" alt="SimplePress" width="96" height="96">
</p>

<p align="center">
  A WordPress-inspired administration theme, dashboard and secure admin toolkit for <strong>GetSimple CMS CE</strong>.
</p>

<p align="center">
  <a href="README.pl.md">Polska wersja dokumentacji</a>
</p>

---

## Table of contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [First steps](#first-steps)
- [Admin sections](#admin-sections)
- [Appearance and color schemes](#appearance-and-color-schemes)
- [Editor](#editor)
- [Media library](#media-library)
- [Users and roles](#users-and-roles)
- [Snippets](#snippets)
- [Menu extensions](#menu-extensions)
- [Theme settings and customizer API](#theme-settings-and-customizer-api)
- [Front-end admin bar](#front-end-admin-bar)
- [Maintenance, backups and migration](#maintenance-backups-and-migration)
- [Plugin installer](#plugin-installer)
- [Security](#security)
- [Directory structure](#directory-structure)
- [Hooks and filters](#hooks-and-filters)
- [Localization](#localization)
- [Updating](#updating)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [License](#license)

## Overview

SimplePress replaces the default GetSimple CMS admin experience with a modern,
WordPress-like interface. It is a self-contained plugin: it does not modify
GetSimple core files and does not load WordPress, public CDNs or remote assets at
runtime.

Main features:

- WordPress-style top bar, collapsible dark sidebar, submenus and mobile menu.
- Classic, Modern and Dark color schemes with per-user overrides.
- WordPress-like postboxes, tabs, tables, forms, notices and login screen.
- Custom SimplePress "Monogram Seal" logo.
- Dashboard with statistics, quick draft and server health.
- CKEditor (GetSimple core) or locally bundled TinyMCE 6.
- Media library with drag & drop upload, thumbnails and local Cropper.js.
- Users, roles, per-user scheme/editor preferences and hidden admin sections.
- Reusable snippets, custom menu links and a WordPress-like theme customizer.
- Maintenance mode, backup creator, branding, login options and front-end admin bar.
- Secure plugin installer/uninstaller with a GetSimple CE catalog.
- gsconfig editor, domain migration, managed HTTPS redirect and SQLite3 overview.
- Local Dashicons, TinyMCE, Cropper.js and CodeMirror assets.

## Requirements

- GetSimple CMS CE.
- PHP 7.4 – 8.x.
- The PHP `zip` extension for the plugin installer and backup creator.
- The PHP `sqlite3` extension for settings, users metadata and theme options.
- Write access to the GetSimple `data/other/` directory.

SimplePress does not require WordPress and does not contact WordPress servers.

## Installation

### Manual installation

1. Copy `simplepress.php` and the whole `simplepress/` directory into the
   GetSimple `plugins/` directory:

   ```text
   plugins/
   ├── simplepress.php
   └── simplepress/
   ```

2. Open **Plugins** in the GetSimple admin and enable **SimplePress**.
3. Open **SimplePress → Appearance** and choose a color scheme.

### Installation from the SimplePress plugin manager

1. Open **SimplePress → Plugins**.
2. Paste a GitHub repository URL or a direct ZIP archive URL, for example:

   ```text
   https://github.com/GetSimpleCMS-CE-plugins/plugin-AdminRenamer
   https://github.com/GetSimpleCMS-CE-plugins/plugin-AdminRenamer/archive/refs/heads/1.1.zip
   ```

3. Click **Install**.

The installer:

- accepts `github.com`, `codeload.github.com`, `api.github.com`,
  `raw.githubusercontent.com`, `objects.githubusercontent.com`,
  `github-releases.githubusercontent.com` and `getsimple-ce.ovh` over HTTPS;
- converts a plain GitHub repository URL into the default-branch ZIP archive;
- detects the main plugin file by its `register_plugin()` call or `Plugin Name:`
  header instead of relying on the archive folder name;
- supports flat archives and plugins nested one level deep;
- skips repository documentation and development files when copying;
- validates ZIP entries, symlinks, total size and file count.

### Migrating from `wp_simple_admin`

If the old `wp_simple_admin` plugin files and data are present, SimplePress
imports the old database and settings automatically on first use.

## First steps

1. Enable the plugin.
2. Open **SimplePress → Dashboard** and review the server health card.
3. Open **Appearance** and select the color scheme and editor.
4. Configure the login screen, front-end admin bar and branding in
   **Appearance → Branding / Login**.
5. Review **Users** and **Hidden sections** if you run a multi-user site.
6. Create your first snippet or custom menu link if your theme needs them.

## Admin sections

| Section | Purpose |
| --- | --- |
| Dashboard | Site statistics, quick draft, recent activity and server health. |
| Plugins | Install, uninstall and browse the GetSimple CE plugin catalog. |
| Editor | Choose CKEditor or TinyMCE and configure toolbar options. |
| Users | Create users, change roles and set per-user preferences. |
| Snippets | Reusable PHP/HTML blocks available through `get_snippet()`. |
| Menu | Add custom sidebar, top-tab and front-end admin bar links. |
| Appearance | Color schemes, login screen, branding and front-end admin bar. |
| Theme | WordPress-like theme customizer and backend settings creator. |
| Maintenance | Maintenance mode and maintenance page settings. |
| Backups | Create, list and download backups. |
| Hidden sections | Hide admin menu entries per user. |
| Migration & SSL | Domain migration and managed HTTPS redirect. |
| Tools | gsconfig editor, component editor, Bootstrap grid and utilities. |
| Help | Help desk and documentation links. |
| SQLite3 | Overview of the SimplePress SQLite database. |
| My account | Per-user scheme, editor and front-end bar preferences. |

## Appearance and color schemes

SimplePress ships with three schemes:

- **Classic** — the familiar WordPress blue and gray palette.
- **Modern** — a brighter, flatter palette.
- **Dark** — a low-light palette for night work.

Administrators choose the global scheme. Each user can override it in
**My account**. Preferences are stored in the SimplePress SQLite database.

## Editor

SimplePress can use:

- **CKEditor** — the editor bundled with GetSimple CMS CE.
- **TinyMCE 6** — a local copy bundled inside `simplepress/lib/tinymce/`.

No editor assets are loaded from a CDN. Toolbar options and editor preferences
are configured in **SimplePress → Editor**.

## Media library

The media library supports:

- drag & drop uploads,
- image thumbnails,
- cropping with the bundled Cropper.js,
- insertion into the editor,
- safe document/media extensions.

PHP and SVG uploads are rejected by the upload validator.

## Users and roles

SimplePress adds:

- per-user color scheme and editor preferences,
- per-user front-end admin bar preference,
- per-user hidden admin sections,
- role-aware access checks for administrator-only actions.

The plugin never changes GetSimple core user files. Metadata is stored in the
SimplePress SQLite database.

## Snippets

Snippets are reusable blocks of HTML/PHP that can be inserted into themes and
components.

```php
<?php get_snippet('footer-cta'); ?>
```

To get the content without printing it:

```php
<?php
$html = return_snippet('footer-cta');
?>
```

Snippets are managed in **SimplePress → Snippets** and stored in
`data/other/snippetMassive/snippet.json`.

## Menu extensions

Custom links can be added to:

- the admin sidebar,
- the top tab bar,
- the front-end admin bar.

Manage them in **SimplePress → Menu**. Links are stored in
`data/other/simplepress/menu_ext.json`.

Programmatic access:

```php
<?php
$links = wp_simple_get_menu_links();
foreach ($links as $id => $link) {
    echo '<a href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8')
        . '</a>';
}
?>
```

## Theme settings and customizer API

Themes can register customizer sections and settings. SimplePress stores values
per active theme and exposes a WordPress-like API.

### Register a section

```php
<?php
if (function_exists('sp_customizer_add_section')) {
    sp_customizer_add_section('homepage', 'Homepage', ['priority' => 20]);
}
?>
```

### Register a setting

```php
<?php
if (function_exists('sp_customizer_add_setting')) {
    sp_customizer_add_setting('hero_title', [
        'label'       => 'Hero title',
        'type'        => 'text',
        'default'     => 'Welcome',
        'section'     => 'homepage',
        'description' => 'Title displayed above the fold.',
        'priority'    => 10,
    ]);
}
?>
```

### Read a value

```php
<?php
$title = sp_theme_option('hero_title', 'Welcome');
echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
?>
```

For multi-value settings (galleries, checkbox groups, multiselects):

```php
<?php
$images = sp_theme_option_array('hero_gallery', []);
foreach ($images as $image) {
    echo '<img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt="">';
}
?>
```

### Supported field types

`text`, `textarea`, `wysiwyg`, `select`, `radio`, `button_group`, `toggle`,
`checkbox`, `multiselect`, `color`, `range`, `number`, `date`, `time`,
`image`, `media`, `file`, `page`, `link`, `gallery`, `dashicon`, `code`.

### Backend settings creator

**SimplePress → Theme** includes a settings creator that generates the PHP
registration snippet for you, so you can paste it into your theme's
`functions.php`.

## Front-end admin bar

Authenticated users can see a WordPress-style admin bar on the front end. It
provides quick links to:

- the SimplePress dashboard,
- pages,
- editing the current page,
- adding a new page,
- the theme customizer (administrators),
- the current user's account and logout.

The bar can be disabled globally or per user.

## Maintenance, backups and migration

### Maintenance mode

Enable maintenance mode in **SimplePress → Maintenance**. Administrators keep
access while visitors see a maintenance page.

### Backups

**SimplePress → Backups** creates ZIP archives of selected areas:

- plugins,
- admin,
- data,
- themes,
- uploads.

Backups are stored under `data/other/simplepress/backups/` and can be downloaded
through an authenticated, nonce-protected endpoint.

### Domain migration and SSL

**SimplePress → Migration & SSL** can:

- update the site URL after moving a site to a new domain,
- manage an HTTPS redirect,
- store the last SSL check status.

## Plugin installer

The installer is available at **SimplePress → Plugins**. It is intentionally
restricted:

- HTTPS only,
- allowlisted hosts,
- no symlinks or path traversal in ZIP entries,
- maximum archive size and file count,
- refuses to overwrite SimplePress itself,
- skips `.git`, `.github`, `__MACOSX`, `node_modules` and root documentation
  files when copying.

If a GitHub archive contains a folder such as `plugin-AdminRenamer-1.1/`, the
installer reads the actual main PHP file (`AdminRenamer.php`) and installs it as
`plugins/AdminRenamer.php` (plus its plugin directory, if present).

## Security

- Every state-changing action is POST-only and protected by a SimplePress nonce.
- Administrator-only actions verify the authenticated user and role.
- Uploads are restricted to validated images and safe document/media extensions.
- PHP and SVG uploads are rejected.
- File operations use basename sanitizing, `realpath()` containment and symlink
  checks.
- ZIP installation validates entries, symlinks, size and file count.
- Backups are downloaded only through an authenticated, nonce-protected endpoint.
- JSON writes are atomic and protected files use restrictive permissions.
- Statistics are written with locking and bounded size.

If you discover a security issue, please do not open a public issue with exploit
details. Contact the maintainer privately first.

## Directory structure

```text
plugins/
├── simplepress.php
└── simplepress/
    ├── css/
    ├── fonts/
    ├── img/
    │   └── sp.svg
    ├── includes/
    ├── js/
    ├── lang/
    ├── lib/
    └── README.md
```

Runtime data is stored outside the plugin directory:

```text
data/other/simplepress.db
data/other/simplepress/
data/other/snippetMassive/snippet.json
data/uploads/simplepress/
```

## Hooks and filters

SimplePress uses GetSimple CE's `exec_filter()` for extensibility:

| Filter | Purpose |
| --- | --- |
| `simplepress_admin_tabs` | Add or modify admin tabs. |
| `simplepress_admin_views` | Map tab keys to view callbacks. |
| `simplepress_sidebar_links` | Add or modify sidebar links. |

Example:

```php
<?php
exec_filter('simplepress_admin_tabs', function ($tabs) {
    $tabs['my-section'] = ['My section', 'dashicons-admin-generic'];
    return $tabs;
});
?>
```

## Localization

SimplePress includes:

- English (`en_US`),
- Polish (`pl_PL`),
- Spanish (`es_ES`).

The language follows the GetSimple `$LANG` setting. Custom strings can be added
to the files in `simplepress/lang/`.

## Updating

1. Back up `simplepress.php`, the `simplepress/` directory and
   `data/other/simplepress.db`.
2. Replace the plugin files with the new release.
3. Keep the `data/other/simplepress/` and `data/uploads/simplepress/` directories.
4. Clear the plugin catalog cache if you use the installer:

   ```text
   data/cache/simplepress_plugin_catalog.json
   ```

## Troubleshooting

### The plugin installer says the archive has an invalid structure

Update to SimplePress 6.3.1 or newer. Older versions used the GitHub archive
folder name as the plugin ID, which rejected most GitHub ZIP files.

### The installer cannot download a plugin

- Make sure the URL starts with `https://`.
- Use a supported host (`github.com`, `codeload.github.com`, etc.).
- Check that PHP has the `zip` extension and that `allow_url_fopen` or cURL is
  available.
- Check write permissions for the `plugins/` and `data/other/` directories.

### The admin looks unstyled

- Make sure the whole `simplepress/` directory was uploaded, including `css/`,
  `js/`, `fonts/`, `img/` and `lib/`.
- Check that `SITEURL` in `gsconfig.php` is correct.

### Settings do not save

- Check that PHP has the `sqlite3` extension.
- Check write permissions for `data/other/`.

### A plugin was installed with the wrong name

The installer uses the main plugin PHP file name. If the archive contains
several PHP files, make sure the main file calls `register_plugin()` or contains
a `Plugin Name:` header.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

SimplePress is released under the GNU General Public License v3.0. See
[LICENSE](LICENSE).

SimplePress is an independent project. It is not affiliated with or endorsed by
the WordPress project or Automattic.
