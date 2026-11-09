# Changelog

All notable changes to SimplePress are documented in this file.

## [6.4.0] - 2026-11-09

### Added

- Complete English, Polish and Spanish translations for the admin UI.
- JavaScript localization via `window.WP_I18N`.
- Localized media library, customizer, dashboard, users, snippets, menu,
  backups, migration, branding and theme settings.
- Generic language loader with safe fallback to English.
- New "Aurora Monogram" logo.

### Fixed

- Hardcoded Polish and English strings that ignored the selected language.
- Missing `window.WP_I18N` object used by `wp-admin.js`.
- Mixed-language labels and untranslated terms in Polish and Spanish.
- Inconsistent fallbacks for the same translation keys.
- Hardcoded `<html lang="pl">` on the maintenance page.
- JavaScript confirm/alert messages that were not translatable.

### Changed

- Version bumped to 6.4.0.

## [6.3.1] - 2026-11-07

### Added

- New "Monogram Seal" logo in `simplepress/img/sp.svg`.
- Full English and Polish documentation (`README.md`, `README.pl.md`).
- GPL-3.0 license file.
- Project changelog.

### Fixed

- Plugin installer rejected most GitHub ZIP archives with the message
  "Archiwum ma nieoczekiwaną strukturę katalogów".
- The installer now detects the main plugin file by its `register_plugin()` call
  or `Plugin Name:` header instead of using the archive folder name.
- GitHub repository URLs are automatically converted to ZIP archive URLs.
- Flat archives and plugins nested one level deep are now supported.
- Repository documentation and development files are skipped when copying a
  plugin into `plugins/`.

### Changed

- Version bumped to 6.3.1.

## [6.3.0] - 2026-11-05

- WordPress-inspired admin theme, dashboard and secure admin toolkit.
- Classic, Modern and Dark color schemes with per-user overrides.
- CKEditor and local TinyMCE 6 support.
- Media library, users, snippets, custom menu links and theme customizer.
- Maintenance mode, backups, branding, login options and front-end admin bar.
- Secure plugin installer/uninstaller, gsconfig editor, migration and SQLite3
  overview.
