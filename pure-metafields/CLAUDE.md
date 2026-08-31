# PureFields — Claude Code Instructions

These instructions apply to every task on this plugin. Read all of them before writing a single line of code.

---

## What this plugin is

**pure-metafields** (`tpmeta` package) is a production WordPress plugin shipped with ThemePure themes. It provides:
- Custom metaboxes for any post type
- A static Options Panel framework (`TPMeta_Options`) with theme_mod storage
- A visual Options Panel Builder (JSON-based, stored in `wp_options` via `TPMeta_Builder_Store`)
- A "Bake to PHP" codegen that exports builder panels to standalone PHP files
- WordPress Customizer integration — Option Panels can be mirrored into Appearance → Customize

**Breaking changes affect live sites silently.** Always treat this as a production codebase.

---

## Architecture at a glance

```
pure-metafields/
├── pure-metafields.php              ← bootstrap, defines TPMETA_VERSION / TPMETA_PATH / TPMETA_URL
├── includes/                        ← activator, deactivator, loader
├── assets/                          ← shared CSS/JS (TPMeta_Assets helper for enqueueing)
├── metaboxes/
│   ├── class-metabox.php            ← metabox registration + save; sanitize_array() lives here
│   └── fields/                      ← one PHP template per field type (checkbox, colorpicker, …)
├── options/
│   ├── class-tpmeta-options.php     ← static facade: set_args(), set_section(), get_panels(), get_fields()
│   ├── class-tpmeta-customizer.php  ← mirrors option panels into WP Customizer (customize_register priority 10)
│   ├── templates/fields/            ← shared field templates (used by both metaboxes and options panels)
│   ├── customizer-fields/           ← complex field types in the Customizer sidebar
│   │   ├── class-tpmeta-customize-field-control.php   ← WP_Customize_Control subclass (type: tpmeta_field)
│   │   ├── class-tpmeta-customize-tinymce-control.php ← TinyMCE editor control (type: tpmeta_tinymce)
│   │   ├── class-tpmeta-customizer-fields.php         ← orchestrator (customize_register priority 11)
│   │   ├── js/tpmeta-customizer-fields.js
│   │   ├── js/tpmeta-customize-tinymce.js
│   │   └── css/tpmeta-customizer-fields.css
│   └── builder/
│       ├── class-tpmeta-builder.php           ← admin UI for the visual builder
│       ├── class-tpmeta-builder-store.php     ← CRUD for panel JSON in wp_options
│       ├── class-tpmeta-builder-loader.php    ← JSON → TPMeta_Options registry (init priority 15)
│       └── class-tpmeta-builder-codegen.php   ← PHP code generation from panel JSON
└── metafields-builder/              ← visual metabox builder (separate from options builder)
```

**Data storage:** All options panel values are stored as WordPress `theme_mod`s. Read with `get_theme_mod('field_id')` or `tpmeta_get_option('field_id', $default)`.

---

## Pre-coding checklist — do this before every task

1. **Read the relevant files** — understand the data flow end to end (registration → render → save → retrieve).
2. **List breaking points** — anything that could break: existing saved theme_mods/post_meta, public API functions, script handles, CSS class names, PHP hooks/filters.
3. **State the approach** — 2–3 sentences on strategy + why it avoids the breaking points. Wait for confirmation if anything risky is touched.

---

## Implementation rules

- **Extend, don't modify.** Add new functions/methods/files rather than changing existing ones. If modification is unavoidable, explain why.
- **Isolate changes.** New feature code lives in its own file or clearly separated section — deletable without side effects.
- **Never change public APIs.** These are contracts used by real themes:
  - Functions: `tpmeta_field()`, `tpmeta_get_option()`, `tpmeta_get_repeater_rows()`, `tpmeta_image_field()`, `tpmeta_gallery_field()`
  - Filters: `tp_meta_boxes`, `tpmeta/options/saved`
  - Script handles: `tm-metabox-js`, `dragula`, `tm-fields-js`
- **Never change database/storage structure** without a backward-compatible migration.
- **Follow existing code style:** prefix `tpmeta_`/`TPMeta_`, no PHP namespaces, no autoloader, manual `require_once`, `TPMeta_Assets::enqueue_*` for shared assets.
- **Default to no comments.** Only comment when the WHY is non-obvious (hidden constraint, workaround, subtle invariant).

---

## Delivery pattern

Always split large features into numbered phases. Stop at the end of each phase and wait for the user to review and test before continuing. Never advance to the next phase until the user explicitly says to proceed.

If bugs are reported in the current phase, fix them before asking about the next phase.

---

## Known patterns and traps

### TinyMCE inside the Customizer sidebar
- Use `content_template()` on the control (Kirki pattern), NOT `render_content()`
- Enqueue `wp-tinymce` with `wp_enqueue_script('wp-tinymce')` directly — `wp_enqueue_editor()` defers the script to priority 50 (after print queue) and will NOT load it
- Init with `tinymce.init({ selector: '#editorId' })` — NOT `target: node` (TinyMCE 4 doesn't support `target`), NOT `wp.editor.initialize()` (requires `tinyMCEPreInit` which is only emitted by `wp_editor()` PHP calls)
- Always set `init_instance_callback` to clear TinyMCE's `visibility: hidden` on the container
- Defer init to `control.deferred.embedded.done()` + `section.expanded.bind()` — section must be open and visible before TinyMCE attaches

### WP_Customize_Control subclass — lazy load rule
Never `require_once` a file that extends `WP_Customize_Control` at plugin boot. Load it inside the `customize_register` callback only — `WP_Customize_Control` doesn't exist on non-Customizer requests.

### Array-to-string PHP warnings
Three sources in this plugin:
1. `sanitize_array()` in `class-metabox.php` — always guard with `is_array()` before `sanitize_text_field()`
2. Output CSS `sprintf` in the options renderer — guard array field values before formatting
3. Catch-all `else` branch in the `$types` switch — same guard

### Don't double-wp_unslash JSON in AJAX sanitizers
The AJAX handler already calls `wp_unslash()`. A second call inside the sanitizer breaks nested JSON escape sequences.

### Repeater cloneNode widget contamination
When cloning a repeater row: strip `.wp-picker-container` wrappers and remove the `hasDatepicker` class before reinitialising widgets. `cloneNode` copies dead DOM state from the original.

### Datepicker ID collision after clone
Rename the input `id` (e.g. append `__n{idx}`) after cloning. jQuery datepicker's day-cell `onclick` uses `"#id"` which hits the first DOM match — collisions cause the calendar to open on the wrong row.

### jQuery trigger vs native event
Use `element.dispatchEvent(new Event('change'))` — NOT `$(element).trigger('change')`. The `bindRow` listener in repeater is attached via native `addEventListener` and won't receive jQuery synthetic events.

### Select2 in the Customizer
Use `dropdownParent: $('body')` so the dropdown escapes the Customizer panel's `overflow: hidden`. Set z-index to `500001` (above the Customizer overlay at `500000`).

### Multicolor / checkbox unchecked state
Only add a key to the collected value object `if (this.checked)`. Storing `''` for unchecked causes `isset()` to return `true` on the PHP side, making all options appear checked.

### Color gradient in the Customizer
The `.tm-gradient-value` hidden input has `name="field_id"` (no `[key]` suffix) — it is skipped by `collectSubFields()`. Branch on `color_gradient` type and read the carrier input directly by `name` attribute.

### Customizer panel disappearing after builder save
`TPMeta_Builder_Loader::register_panel()` must forward `customizer_integration` to `TPMeta_Options::set_args()`. The default is `false`, so if this flag is dropped the panel will vanish from the Customizer even if the checkbox was checked in the builder.

---

## Field types reference

| Type | Value shape | Notes |
|---|---|---|
| text, textarea, url, number | string | |
| colorpicker | `#rrggbb` string | |
| select | string key | options assoc `[value => label]` |
| tabs / radio_buttonset | string key | options stored as `choices` key (not `options`) |
| switch | `'on'`/`'off'` or `'1'`/`'0'` depending on `data_type` | |
| checkbox | assoc `[key => '1']` for checked only | |
| datepicker | date string | |
| image | attachment ID string | |
| gallery | JSON array of IDs | |
| repeater | JSON array of row objects | sub-fields in `field['fields']` sequential array |
| post_select | ID or slug depending on `save_format` | uses Select2 |
| color_gradient | assoc `{stops, angle, type, …}` | value in `.tm-gradient-value` hidden input |
| multicolor | assoc `[key => '#color']` | |
| editor | HTML string | sanitize with `wp_kses_post` |
| group | sub-fields rendered inline | |

---

## Reading option values in themes

```php
// Any option panel field:
$value = tpmeta_get_option( 'field_id', $default );
// or:
$value = get_theme_mod( 'field_id', $default );

// Repeater rows:
$rows = tpmeta_get_repeater_rows( 'repeater_field_id' );
foreach ( $rows as $row ) {
    echo $row['sub_field_id'];
}
```
