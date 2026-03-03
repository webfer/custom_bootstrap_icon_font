# Custom Icon Font Builder (Bootstrap Icons + Font Awesome Free → WOFF2) for Drupal

A Drupal module that generates a custom icon font (WOFF2 + optional WOFF) from a
selected subset of Bootstrap Icons and/or Font Awesome Free SVGs, and publishes
matching CSS classes.

🚀 **Why?** Keep your frontend lightweight: instead of shipping entire icon sets, you can select only the icons your site actually uses and generate a small, focused font + CSS just for those.

---

## 📦 Module Overview

- **Name**: Custom Bootstrap Icon Font
- **Package**: Media
- **Compatibility**: Drupal 10, 11

This module:

- Provides an admin page to select icons and configure the build.
- Builds the font either via Drush (recommended) or via a UI button (handy for local/dev).
- Writes generated assets to `public://custom_bootstrap_icon_font/font/`.
- Automatically attaches the generated CSS on the frontend (only if it exists).
- Provides Twig helpers to render icon markup.

### Key features

- Split icon configuration by source
  - Separate lists for Bootstrap Icons and Font Awesome Free.
- Select icons by name, by class, or by pasted HTML snippets
  - Bootstrap accepts lines like `arrow-right-circle-fill`, `bi-arrow-right-circle-fill`, `bi bi-arrow-right-circle-fill`, or `<i class="bi bi-arrow-right-circle-fill"></i>`.
  - Font Awesome accepts lines like `<i class="fa-solid fa-arrow-down"></i>`, `fa-arrow-down`, or `fa-solid-arrow-down`.
- Stable glyph codepoints across rebuilds
  - Codepoints are stored in config so previously-used icons keep the same Unicode value when re-added.
- Bootstrap-like CSS output
  - Generates a small CSS file that maps `.di-<icon>` to a glyph via `::before { content: "\\e001"; }`.
- Composer/CI friendly
  - Bootstrap Icons live in `web/libraries/bootstrap-icons/icons`.
  - Font Awesome SVGs live in `web/libraries/fontawesome/icons`.
  - Fantasticon is installed at the project level (or globally) and executed via Drush.

---

## 🛠 Installation

### ✅ Install with Composer (recommended) 🧰

From your Drupal project root:

```bash
composer require webfer/custom_bootstrap_icon_font
```

Composer will install the module into your Drupal codebase (commonly under `web/modules/contrib/` in standard Drupal Composer templates). 📦

Then enable the module:

- 📍 **Admin > Extend** → enable **Custom Bootstrap Icon Font**, or
- 💻 `drush en custom_bootstrap_icon_font -y`

### 🧩 Install without Composer (development only)

If you’re developing locally, you can still place the module folder under:

- `web/modules/custom/custom_bootstrap_icon_font`

---

## ✅ Requirements

### Drupal

- Drupal core 10/11.
- Ability to write to `public://` (this module stores generated assets under `public://custom_bootstrap_icon_font/`).

### Bootstrap Icons SVG sources

The build needs access to the Bootstrap Icons SVG files (`*.svg`).

Recommended location (Drupal libraries):

- `web/libraries/bootstrap-icons/icons`

You can install Bootstrap Icons there by either:

- Downloading a release from https://github.com/twbs/icons/releases and extracting to `web/libraries/bootstrap-icons`, or
- Using a build/CI step to fetch it.

### Font Awesome Free SVG sources

Font Awesome icons are not bundled by this module. You provide the SVGs.

Recommended location (Drupal libraries):

- `web/libraries/fontawesome/icons`

Where to obtain icons:

- Browse/search the Free collection: https://fontawesome.com/search?ic=free

Recommended workflow:

1. Find an icon in the Free collection.
2. Download the SVG.
3. Upload it into `web/libraries/fontawesome/icons`.
   - You can use the admin UI “Upload SVG icons (optional)” section.
   - Note: some hosts do not allow writing to `DRUPAL_ROOT/libraries` from the web UI; in that case upload via SFTP/CI.
4. Paste the corresponding snippet into the Font Awesome list (example: `<i class="fa-solid fa-arrow-down"></i>`).

### Node tooling (required for building assets)

Asset generation uses Fantasticon, typically via `npx fantasticon`.

You need:

- `node`, `npm`, `npx`
- Fantasticon available to the same environment that runs `drush`.

Tip 💡: If you plan to build from the admin UI (“Save and build now”), the same Node tooling must also be available to the PHP/web user, and the request must be allowed to run long enough.

Recommended (project-level, deterministic):

```bash
cd /path/to/project/root  # the folder you run drush from

# Only needed if you don't already have a root package.json.
npm init -y

npm install --save-dev fantasticon

# Verify it is available without prompting/downloading.
npx --no-install fantasticon --version
```

Alternative (global install):

```bash
npm install -g fantasticon
fantasticon --version
```

Important:

- If Fantasticon is not installed locally/globally, `npx fantasticon` may try to download it interactively (not suitable for non-interactive servers/CI).
- If you use Yarn or pnpm, install Fantasticon in your project and set `generator_command` accordingly.

---

## ⚙ Configuration

### 1) Permissions

The admin UI requires:

- **Permission**: `administer custom bootstrap icon font`

Assign it under **People > Permissions**.

### 2) Build / update the font

Go to:

- **Admin path**: `/admin/config/media/bootstrap-icon-font`

On this page you can:

- Enter Bootstrap icons (one per line).
  - Names may include or omit the `bi-` prefix; it is normalized automatically.
  - You can also paste full HTML snippets and the module will extract the icon name automatically (example: `<i class="bi bi-basket-fill"></i>`).
- Enter Font Awesome Free icons (one per line).
  - Paste snippets like `<i class="fa-solid fa-arrow-down"></i>`.
  - Or use shorthand `fa-arrow-down` / `fa-solid-arrow-down`.
  - Ensure the matching SVG exists under `web/libraries/fontawesome/icons` (see Requirements above).
- Pick a `font_name` (the `font-family` name used in CSS).
- Configure where Bootstrap Icons live (`icons_source_dir`).
- Configure where Font Awesome SVGs live (`fontawesome_icons_source_dir`).
- Configure the generator command (`generator_command`).

### Optional: Upload SVG icons from the UI

The admin page also includes an **Upload SVG icons (optional)** section.

- Step 1: Upload one or more `.svg` files (they are stored temporarily).
- Step 2: Click **Copy uploaded SVGs into libraries/** to copy them into either:
  - `web/libraries/fontawesome/icons` (recommended for Font Awesome), or
  - your configured Bootstrap Icons source directory.

If your server does not allow writing to `DRUPAL_ROOT/libraries` from PHP, upload icons via SFTP/CI instead.

### ✅ Build assets (two options)

#### Option A: Build from the UI (friendly) 🖱️

1. Click **Save and build now**.
2. The module generates the CSS + font files under `public://custom_bootstrap_icon_font/font/`.

This is great for local/dev. On production, Drush is usually safer (no web timeouts and doesn’t require Node to run during a web request).

#### Option B: Build via Drush (recommended) 🧑‍💻

```bash
drush di-font:build
```

Aliases are kept for backwards compatibility:

```bash
drush custom-bootstrap-icon-font:build
drush ci-font:build
drush cbi-font:build
```

When the build completes, the module:

- Saves the selected icons to config (`custom_bootstrap_icon_font.settings`).
- Maintains a stable `codepoints` mapping so glyphs don’t change between regenerations.
- Writes:
  - `public://custom_bootstrap_icon_font/font/<font_name>.woff2`
  - `public://custom_bootstrap_icon_font/font/<font_name>.woff` (optional)
  - `public://custom_bootstrap_icon_font/font/custom-bootstrap-icon-font.css`
- Updates `version` for cache-busting.
- Invalidates render cache.

---

## 🧩 Usage

### Option A: CSS classes (Bootstrap-like)

After generating assets, you can render icons using classes and a pseudo-element:

```html
<span class="di di-arrow-right-circle-fill" aria-hidden="true"></span>
<span class="di di-fa-solid-arrow-down" aria-hidden="true"></span>
<span class="di-youtube" aria-hidden="true"></span>
```

Notes:

- The generated CSS includes Bootstrap-icons style selectors:
  - `.di::before, [class^="di-"]::before, [class*=" di-"]::before { ... }`
- The icon is rendered by `::before { content: "\\e001"; }`.

### Option B: Twig helper

This module provides:

```twig
{{ di_font_icon('arrow-right-circle-fill') }}
{{ di_font_icon('fa-solid-arrow-down') }}
{{ di_font_icon('youtube', { class: 'text-danger me-2' }) }}
```

Backwards-compatible Twig function names are also available (if you used older templates):

```twig
{{ ci_font_icon('arrow-right-circle-fill') }}
{{ cbi_font_icon('arrow-right-circle-fill') }}
```

## 🧱 Fantasticon specification

This module is a thin wrapper around the Fantasticon CLI.

### Inputs

- **Bootstrap Icons list**: stored in `custom_bootstrap_icon_font.settings:bootstrap_icons`
- **Font Awesome list**: stored in `custom_bootstrap_icon_font.settings:fontawesome_icons`
  - Backwards compatibility: older installs may still have a merged `custom_bootstrap_icon_font.settings:icons` list; the build keeps it in sync.
- **Bootstrap Icons source directory**: stored in `custom_bootstrap_icon_font.settings:icons_source_dir`
  - Default: `libraries/bootstrap-icons/icons` (relative to `DRUPAL_ROOT`)
- **Font Awesome source directory**: stored in `custom_bootstrap_icon_font.settings:fontawesome_icons_source_dir`
  - Default: `libraries/fontawesome/icons` (relative to `DRUPAL_ROOT`)
- **Generator command**: stored in `custom_bootstrap_icon_font.settings:generator_command`
  - Default: `npx fantasticon`
  - Note: it is split on whitespace (simple tokenization). If you need complex quoting, use a small wrapper script and point `generator_command` at it.

### Generated Fantasticon config

During `drush di-font:build`, the module stages the selected SVGs into a temporary folder and generates a Fantasticon JSON config equivalent to:

```json
{
  "name": "<font_name>",
  "inputDir": "<temporary>/icons",
  "outputDir": "<public_files>/custom_bootstrap_icon_font/font",
  "fontTypes": ["woff2", "woff"],
  "assetTypes": [],
  "normalize": true,
  "fontHeight": 512,
  "descent": 0,
  "codepoints": {
    "arrow-right-circle-fill": 57345,
    "fa-solid-arrow-down": 57346
  }
}
```

Important details:

- `codepoints` are provided explicitly to keep glyphs stable between builds.
- Only the currently selected icons are emitted into the font/CSS, but the module keeps a historical mapping in config so re-adding an icon later can reuse the same codepoint.
- `normalize/fontHeight/descent` are set to keep icon sizes consistent when mixing Bootstrap Icons (typically 16×16 viewBox) with Font Awesome (typically 512×512 viewBox).

### Outputs

Files written to `public://custom_bootstrap_icon_font/font/`:

- `<font_name>.woff2`
- `<font_name>.woff` (optional fallback)
- `custom-bootstrap-icon-font.css` (uses relative `./<font_name>.woff2?v=<version>` URLs)

---

## 🎨 Styling

Because this is a font:

- Icon color is controlled by `color`.
- Icon size is controlled by `font-size`.

Example:

```css
.di,
[class^='di-'],
[class*=' di-'] {
  color: currentColor;
}
```

---

## 🚨 Troubleshooting

- **“Icon not found: …”**
  - For Bootstrap Icons: confirm the icon exists in your Bootstrap Icons version. Example: `youtube.svg` should exist.
  - For Font Awesome: confirm the SVG exists under `web/libraries/fontawesome/icons`.
    - Some downloads have non-canonical filenames; the module attempts a best-effort match, but it’s still safest to keep filenames close to the icon name (example: `arrow-down.svg`).
  - Ensure the configured source directories exist:
    - `icons_source_dir` (recommended: `web/libraries/bootstrap-icons/icons`)
    - `fontawesome_icons_source_dir` (recommended: `web/libraries/fontawesome/icons`)

- **Build fails**
  - Ensure Node.js + npm + npx are installed.
  - Ensure Fantasticon is available on PATH (or set `generator_command` accordingly).
  - Run the build from the same environment where `drush` runs.
  - If you use the UI build, confirm the web/PHP user can run the generator command and that the request won’t time out.

- **I clicked “Save configuration” but nothing was generated**
  - That button only saves settings.
  - To generate the CSS + fonts, click **Save and build now** (or run `drush di-font:build`).

- **CSS loads but icons show as empty squares**
  - Font files are missing or blocked. Check the network tab for `.woff2`.
  - Confirm the CSS and fonts are in the same directory (so relative `./<font>.woff2` resolves).

- **Icons changed after regeneration**
  - Stable glyphs require stable codepoints. This module stores `codepoints` in config; avoid deleting that config between runs.

---

## 📂 File Structure

```
custom_bootstrap_icon_font/
├── composer.json
├── LICENSE
├── custom_bootstrap_icon_font.info.yml
├── custom_bootstrap_icon_font.module
├── custom_bootstrap_icon_font.routing.yml
├── custom_bootstrap_icon_font.permissions.yml
├── custom_bootstrap_icon_font.links.menu.yml
├── custom_bootstrap_icon_font.services.yml
├── custom_bootstrap_icon_font.libraries.yml
├── drush.services.yml
├── css/
│   └── custom_bootstrap_icon_font.admin.css
├── config/
│   ├── install/
│   │   └── custom_bootstrap_icon_font.settings.yml
│   └── schema/
│       └── custom_bootstrap_icon_font.schema.yml
└── src/
    ├── Commands/
    │   └── CustomBootstrapIconFontCommands.php
    ├── Form/
    │   └── CustomBootstrapIconFontGenerateForm.php
    ├── Helper/
    │   └── CustomBootstrapIconFontHelper.php
  ├── Service/
  │   └── CustomBootstrapIconFontBuilder.php
    └── Twig/
        └── CustomBootstrapIconFontTwigExtension.php
```

---

## 📜 License

This project is licensed under the **GNU General Public License, version 2 or (at your option) any later version**.

- SPDX identifier: `GPL-2.0-or-later`
- Created by: WebFer

---

\_Created and maintained by [WebFer](https://www.linkedin.com/in/webfer/)
