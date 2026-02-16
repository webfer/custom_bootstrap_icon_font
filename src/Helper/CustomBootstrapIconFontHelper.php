<?php

namespace Drupal\custom_bootstrap_icon_font\Helper;

use Drupal\Component\Utility\Html;
use Drupal\Core\File\FileSystemInterface;

final class CustomBootstrapIconFontHelper {

  public const DEFAULT_CODEPOINT_START = 0xE001;

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
