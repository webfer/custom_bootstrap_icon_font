# Custom Bootstrap Icon Font (Bootstrap Icons → WOFF2) for Drupal

A Drupal module that generates a custom icon font (WOFF2 + optional WOFF) from a
selected subset of Bootstrap Icons, and publishes matching CSS classes.

---

## 📦 Module Overview

- **Name**: Custom Bootstrap Icon Font
- **Package**: Custom
- **Compatibility**: Drupal 10, 11

This module:

- Provides an admin page to select icons and configure the build.
- Builds the font either via Drush (recommended) or via a UI button (handy for local/dev).
- Writes generated assets to `public://custom_bootstrap_icon_font/font/`.
- Automatically attaches the generated CSS on the frontend (only if it exists).
- Provides Twig helpers to render icon markup.

### Key features

- Select icons by name, by class, or by pasted HTML snippets
  - Accepts lines like `arrow-right-circle-fill`, `bi-arrow-right-circle-fill`, `bi bi-arrow-right-circle-fill`, or `<i class="bi bi-arrow-right-circle-fill"></i>`.
- Stable glyph codepoints across rebuilds
  - Codepoints are stored in config so previously-used icons keep the same Unicode value when re-added.
- Bootstrap-like CSS output
  - Generates a small CSS file that maps `.di-<icon>` to a glyph via `::before { content: "\\e001"; }`.
- Composer/CI friendly
  - Bootstrap Icons live in `web/libraries/bootstrap-icons/icons`.
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

- Downloading the last release from https://github.com/twbs/icons and extracting to `web/libraries/bootstrap-icons`, or
- Using a build/CI step to fetch it.

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

- Enter one Bootstrap icon name per line (example: `arrow-right-circle-fill`).
- Names may include or omit the `bi-` prefix; it is normalized automatically.
- Pick a `font_name` (the `font-family` name used in CSS).
- Configure where Bootstrap Icons live (`icons_source_dir`).
- Configure the generator command (`generator_command`).

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

- **Icons list**: stored in `custom_bootstrap_icon_font.settings:icons`
- **Source directory**: stored in `custom_bootstrap_icon_font.settings:icons_source_dir`
  - Default: `libraries/bootstrap-icons/icons` (relative to `DRUPAL_ROOT`)
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
  "codepoints": {
    "arrow-right-circle-fill": 57345
  }
}
```

Important details:

- `codepoints` are provided explicitly to keep glyphs stable between builds.
- Only the currently selected icons are emitted into the font/CSS, but the module keeps a historical mapping in config so re-adding an icon later can reuse the same codepoint.

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
  - Confirm the icon exists in your Bootstrap Icons version. Example: `youtube.svg` should exist.
  - Ensure the configured `icons_source_dir` exists (recommended: `web/libraries/bootstrap-icons/icons`).

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
