<?php

namespace Drupal\custom_bootstrap_icon_font\Helper;

use Drupal\Component\Utility\Html;
use Drupal\Core\File\FileSystemInterface;

final class CustomBootstrapIconFontHelper {

  public const DEFAULT_CODEPOINT_START = 0xE001;

  private const FONT_AWESOME_STYLES = ['solid', 'regular', 'brands'];

  // Common Font Awesome utility/modifier classes that are NOT icon names.
  // Keep this list short and conservative; anything unknown is treated as an icon.
  private const FONT_AWESOME_NON_ICON_TOKENS = [
    'fw',
    'lg',
    'xs',
    'sm',
    'xl',
    '2xl',
    '3x',
    '4x',
    '5x',
    '6x',
    '7x',
    '8x',
    '9x',
    '10x',
    'spin',
    'pulse',
    'spin-pulse',
    'spin-reverse',
    'bounce',
    'beat',
    'beat-fade',
    'fade',
    'flip',
    'flip-horizontal',
    'flip-vertical',
    'rotate-90',
    'rotate-180',
    'rotate-270',
  ];

  /**
   * Resolves a Bootstrap Icons SVG source directory to an absolute path.
   *
   * Expected to be a path relative to DRUPAL_ROOT, typically:
   * - libraries/bootstrap-icons/icons
   */
  public static function resolveIconsSourceDir(string $relative_path): ?string {
    $relative_path = trim($relative_path);
    if ($relative_path === '') {
      return NULL;
    }

    $absolute = rtrim(DRUPAL_ROOT, '/') . '/' . ltrim($relative_path, '/');
    return is_dir($absolute) ? $absolute : NULL;
  }

  /**
   * Normalizes an icon name to the SVG filename base (without .svg).
   */
  public static function normalizeIconName(string $name): string {
    $name = trim($name);
    $name = ltrim($name, ".");
    // Allow users to paste generated classnames too.
    $name = preg_replace('/^di-/', '', $name) ?? $name;
    $name = preg_replace('/^ci-/', '', $name) ?? $name;
    $name = preg_replace('/^bi-/', '', $name) ?? $name;
    return $name;
  }

  /**
   * Normalizes a Font Awesome icon ID to a namespaced Fantasticon-safe value.
   *
   * Output examples:
   * - fa-solid-arrow-down
   * - fa-regular-circle
   * - fa-brands-github
   */
  public static function normalizeFontAwesomeIconId(string $style, string $icon_name): string {
    $style = strtolower(trim($style));
    $icon_name = strtolower(trim($icon_name));

    if (!in_array($style, self::FONT_AWESOME_STYLES, TRUE)) {
      $style = 'solid';
    }

    // Allow users to paste fa-arrow-down or fa-solid-arrow-down too.
    $icon_name = preg_replace('/^fa-/', '', $icon_name) ?? $icon_name;
    $icon_name = preg_replace('/^(solid|regular|brands)-/', '', $icon_name) ?? $icon_name;

    $icon_name = preg_replace('/[^a-z0-9-]/', '', $icon_name) ?? $icon_name;
    $icon_name = trim($icon_name, '-');

    return 'fa-' . $style . '-' . $icon_name;
  }

  /**
   * Parses a normalized Font Awesome icon ID.
   *
   * @return array{style:string,name:string}|null
   */
  public static function parseFontAwesomeIconId(string $icon_id): ?array {
    $icon_id = strtolower(trim($icon_id));
    if (preg_match('/^fa\-(solid|regular|brands)\-([a-z0-9-]+)$/', $icon_id, $m)) {
      return ['style' => $m[1], 'name' => $m[2]];
    }
    return NULL;
  }

  /**
   * Extracts one or more Bootstrap Icon names from a free-form line.
   *
   * Supports inputs like:
   * - arrow-down-left-square
   * - bi-arrow-down-left-square
   * - bi bi-arrow-down-left-square
   * - <i class="bi bi-arrow-down-left-square"></i>
   *
   * @return string[]
   *   Normalized icon names (without the "bi-" prefix).
   */
  public static function extractIconNamesFromLine(string $line): array {
    $line = trim($line);
    if ($line === '') {
      return [];
    }

    // Font Awesome: accept pasted HTML like:
    // <i class="fa-solid fa-arrow-down"></i>
    // or class lists like: fa-solid fa-arrow-down
    // We convert them to a namespaced ID: fa-solid-arrow-down.
    if (stripos($line, 'fa-') !== FALSE) {
      $fa_matches = [];
      if (preg_match_all('/\bfa-([a-z0-9-]+)\b/i', $line, $fa_matches) && !empty($fa_matches[1])) {
        $tokens = array_values(array_unique(array_map('strtolower', $fa_matches[1])));
        $style = 'solid';
        foreach ($tokens as $token) {
          if (in_array($token, self::FONT_AWESOME_STYLES, TRUE)) {
            $style = $token;
            break;
          }
        }

        $icon_name = NULL;
        foreach ($tokens as $token) {
          if (in_array($token, self::FONT_AWESOME_STYLES, TRUE)) {
            continue;
          }
          if (in_array($token, self::FONT_AWESOME_NON_ICON_TOKENS, TRUE)) {
            continue;
          }
          // First unknown token is assumed to be the icon name (e.g. arrow-down).
          $icon_name = $token;
          break;
        }

        if (is_string($icon_name) && $icon_name !== '') {
          return [self::normalizeFontAwesomeIconId($style, $icon_name)];
        }
      }

      // Allow manual entry like: fa-arrow-down
      if (preg_match('/^fa\-([a-z0-9-]+)$/i', $line, $m)) {
        return [self::normalizeFontAwesomeIconId('solid', $m[1])];
      }

      // Allow manual entry like: fa-solid-arrow-down
      if (preg_match('/^fa\-(solid|regular|brands)\-([a-z0-9-]+)$/i', $line, $m)) {
        return [self::normalizeFontAwesomeIconId($m[1], $m[2])];
      }
    }

    $matches = [];
    // Capture any bi-* classname occurrences (works for HTML, class lists, etc).
    if (preg_match_all('/\bbi-([a-z0-9-]+)\b/i', $line, $matches) && !empty($matches[1])) {
      $icons = array_map(
        static fn (string $m): string => self::normalizeIconName('bi-' . $m),
        $matches[1]
      );
      $icons = array_values(array_unique(array_filter($icons)));
      if (!empty($icons)) {
        return $icons;
      }
    }

    // Fallback: treat the whole line as a single icon identifier.
    $icon = self::normalizeIconName($line);
    return $icon !== '' ? [$icon] : [];
  }

  /**
   * Copies selected SVG files into an input directory for font generation.
   *
   * Supports Bootstrap Icons (no prefix) and Font Awesome IDs like:
   * - fa-solid-arrow-down
   */
  public static function stageIconsFromSources(
    array $icons,
    string $input_dir_realpath,
    ?string $bootstrap_source_dir,
    ?string $fontawesome_source_dir
  ): void {
    if (!is_dir($input_dir_realpath)) {
      mkdir($input_dir_realpath, 0775, TRUE);
    }

    foreach ($icons as $icon) {
      $icon = (string) $icon;
      $fa = self::parseFontAwesomeIconId($icon);
      if ($fa) {
        if (!$fontawesome_source_dir) {
          throw new \RuntimeException('Font Awesome source dir is not configured.');
        }
        $src = self::resolveFontAwesomeSvgPath($fontawesome_source_dir, $fa['style'], $fa['name']);
        $dest = $input_dir_realpath . '/' . $icon . '.svg';
        if (!$src) {
          throw new \RuntimeException('Font Awesome icon not found: ' . $icon . '. Checked: ' . $fontawesome_source_dir);
        }
        copy($src, $dest);
        continue;
      }

      if (!$bootstrap_source_dir) {
        throw new \RuntimeException('Bootstrap Icons source dir is not configured.');
      }

      $src = rtrim($bootstrap_source_dir, '/') . '/' . $icon . '.svg';
      $dest = $input_dir_realpath . '/' . $icon . '.svg';

      if (!is_file($src)) {
        throw new \RuntimeException('Icon not found: ' . $icon . '. Checked: ' . $bootstrap_source_dir);
      }
      copy($src, $dest);
    }
  }

  /**
   * Resolves the real path to a Font Awesome SVG file.
   *
   * Supports several common directory layouts:
   * - <dir>/arrow-down.svg
   * - <dir>/solid/arrow-down.svg
   * - <dir>/svgs/solid/arrow-down.svg
   */
  private static function resolveFontAwesomeSvgPath(string $dir, string $style, string $name): ?string {
    $dir = rtrim($dir, '/');
    $style = strtolower($style);
    $name = strtolower($name);

    $candidates = [
      $dir . '/' . $name . '.svg',
      $dir . '/fa-' . $name . '.svg',
      $dir . '/' . $style . '/' . $name . '.svg',
      $dir . '/svgs/' . $style . '/' . $name . '.svg',
      $dir . '/' . $style . '-' . $name . '.svg',
    ];

    foreach ($candidates as $path) {
      if (is_file($path)) {
        return $path;
      }
    }

    // Fallback: some downloads (or user uploads) include extra tokens like
    // "-solid-full" in the filename. Try to find a best-effort match.
    $patterns = [
      $dir . '/*' . $name . '*.svg',
      $dir . '/*' . $style . '*' . $name . '*.svg',
      $dir . '/*' . $name . '*' . $style . '*.svg',
      $dir . '/' . $style . '/*' . $name . '*.svg',
      $dir . '/svgs/' . $style . '/*' . $name . '*.svg',
    ];

    $matches = [];
    foreach ($patterns as $pattern) {
      foreach (glob($pattern) ?: [] as $match) {
        if (is_file($match)) {
          $matches[$match] = TRUE;
        }
      }
    }

    if (!empty($matches)) {
      $paths = array_keys($matches);
      // Prefer paths containing the style token, then shortest basename.
      usort($paths, static function (string $a, string $b) use ($style): int {
        $a_has = stripos(basename($a), $style) !== FALSE;
        $b_has = stripos(basename($b), $style) !== FALSE;
        if ($a_has !== $b_has) {
          return $a_has ? -1 : 1;
        }
        return strlen(basename($a)) <=> strlen(basename($b));
      });
      return $paths[0];
    }

    return NULL;
  }

  /**
   * Assigns stable codepoints for a set of icons.
   *
   * @param string[] $icons
   * @param array $existing
   *
   * @return array
   *   Mapping [iconName => intCodepoint].
   */
  public static function assignCodepoints(array $icons, array $existing = []): array {
    $icons = array_values(array_unique(array_filter(array_map('strval', $icons))));

    $codepoints = [];
    $used = [];

    foreach ($existing as $icon => $cp) {
      $icon = (string) $icon;
      $cp_int = is_numeric($cp) ? (int) $cp : hexdec((string) $cp);
      if ($icon !== '' && $cp_int > 0) {
        $codepoints[$icon] = $cp_int;
        $used[$cp_int] = TRUE;
      }
    }

    $next = self::DEFAULT_CODEPOINT_START;
    foreach ($icons as $icon) {
      if (isset($codepoints[$icon])) {
        continue;
      }
      while (isset($used[$next])) {
        $next++;
      }
      $codepoints[$icon] = $next;
      $used[$next] = TRUE;
      $next++;
    }

    ksort($codepoints);
    return $codepoints;
  }

  /**
   * Writes a CSS file that exposes icon classes as ::before glyphs.
   */
  public static function writeCss(
    string $css_realpath,
    string $font_family,
    string $woff2_src,
    ?string $woff_src,
    array $codepoints
  ): void {
    $font_family_safe = str_replace('"', "'", $font_family);
    $css = [];

    $css[] = '@font-face {';
    $css[] = '  font-display: block;';
    $css[] = '  font-family: "' . $font_family_safe . '";';
    if ($woff_src) {
      $css[] = '  src: url("' . $woff2_src . '") format("woff2"), url("' . $woff_src . '") format("woff");';
    }
    else {
      $css[] = '  src: url("' . $woff2_src . '") format("woff2");';
    }
    $css[] = '  font-weight: normal;';
    $css[] = '  font-style: normal;';
    $css[] = '}';
    $css[] = '';

    // Bootstrap-icons style selectors.
    $css[] = '.di::before,';
    $css[] = '[class^="di-"]::before,';
    $css[] = '[class*=" di-"]::before {';
    $css[] = '  display: inline-block;';
    $css[] = '  font-family: "' . $font_family_safe . '" !important;';
    $css[] = '  font-style: normal;';
    $css[] = '  font-weight: normal !important;';
    $css[] = '  font-variant: normal;';
    $css[] = '  text-transform: none;';
    $css[] = '  line-height: 1;';
    $css[] = '  vertical-align: -0.125em;';
    $css[] = '  -webkit-font-smoothing: antialiased;';
    $css[] = '  -moz-osx-font-smoothing: grayscale;';
    $css[] = '}';

    foreach ($codepoints as $icon => $cp) {
      $class = 'di-' . Html::getClass((string) $icon);
      $hex = dechex((int) $cp);
      $css[] = '.' . $class . '::before { content: "\\' . $hex . '"; }';
    }

    file_put_contents($css_realpath, implode("\n", $css) . "\n");
  }

  /**
   * Copies selected SVG files into an input directory for font generation.
   */
  public static function stageIcons(array $icons, string $input_dir_realpath, string $source_dir): void {
    if (!is_dir($input_dir_realpath)) {
      mkdir($input_dir_realpath, 0775, TRUE);
    }

    foreach ($icons as $icon) {
      $src = rtrim($source_dir, '/') . '/' . $icon . '.svg';
      $dest = $input_dir_realpath . '/' . $icon . '.svg';

      if (!is_file($src)) {
        throw new \RuntimeException('Icon not found: ' . $icon . '. Checked: ' . $source_dir);
      }
      copy($src, $dest);
    }
  }

  /**
   * Ensures a Drupal stream-wrapper directory exists.
   */
  public static function prepareStreamDir(string $uri, int $flags = FileSystemInterface::CREATE_DIRECTORY): void {
    \Drupal::service('file_system')->prepareDirectory($uri, $flags);
  }

}
