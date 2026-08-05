# TODO

Deferred items from the WP 7.0.2 upgrade audit (August 2026). None are blockers
for the 7.0.2 upgrade; they are pre-existing issues or heads-ups for the next
core bump.

## Fixes

- **Bump the security scan's core version.** `.github/workflows/wp-security.yml`
  passes `wp_core_version: '6.7.3'` to the scanner — three minors behind what we
  ship. One-liner: bump to `7.0.2` (recommended soon, and on every future core
  bump).
- **moj-anchor non-breaking space reaches the front end.** Since WP 6.9,
  `src/components/core-rich-text/anchor.js` inserts ` ` into empty
  `moj-anchor` elements, but `BlockEditor::formatBlocks()`
  (`public/app/themes/justice/inc/block-editor.php`) strips only an ASCII space,
  so content saved since 6.9 renders a visible NBSP. Fix the regex to:
  `'/(<a[^>]*class="moj-anchor"[^>]*>)(?: |\x{00A0}|&nbsp;)(<\/a>)/u'`.
- **Global styles are only half-removed.** `inc/core.php` removes
  `wp_enqueue_global_styles` from `wp_enqueue_scripts`, but core also hooks it
  on `wp_footer` priority 1 (`wp-includes/default-filters.php`), so global
  styles still print in the footer. Add the matching `remove_action`.
- **`anchor.js` touches the top document only.** The legacy-anchor auto-format
  (`document.querySelectorAll`) and the icon-preference class on
  `document.body` silently no-op when the editor canvas is iframed (WP 7.0+).
  Rewrite using the same `editor.BlockListBlock` / canvas `ownerDocument`
  approach used for `src/components/core-image/block-editor.js`.

## Maintenance

- **`@wordpress/*` npm devDependencies are 6.9-era.** WP 7.0 no longer
  publishes per-release package versions; pin against the Gutenberg `wp/7.0`
  branch (sha `a2a354cf35e5b69c3330d6c1cfd42d8dc2efb9fd`) when updating
  `public/app/themes/justice/package.json`.
- **Add a "bumping WP core" checklist to the README.** Hand-maintained
  touchpoints found during this upgrade: composer pins, wp-security.yml
  version, theme `style.css` header, `wp core update-db` after deploy, S3/CDN
  asset push (asset `?ver=` hashes change with the core version), nginx
  logout-regex check (`deploy/config/server.conf`).

## WP 7.1 heads-up

Deprecations already warning in 7.0.2 that are removed/enforced in 7.1:

- The 36px default component size (`__next40pxDefaultSize` opt-out) is removed
  across ~20 `wp.components` controls.
- `wp.components.Navigation` is removed (→ `wp.components.Navigator`).
- Iframe enforcement for classic themes arrives (currently Gutenberg-plugin
  only) — editor styles were moved to `add_editor_style()` in this upgrade, so
  we should be ready.
- `wp.editor.EditorNotices`/`EditorSnackbars` and
  `wp.blockEditor.HeightControl` are removed in 7.2.
