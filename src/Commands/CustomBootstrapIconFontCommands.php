<?php

namespace Drupal\custom_bootstrap_icon_font\Commands;

use Drupal\Core\File\FileSystemInterface;
use Drupal\custom_bootstrap_icon_font\Helper\CustomBootstrapIconFontHelper;
use Drush\Commands\DrushCommands;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;

final class CustomBootstrapIconFontCommands extends DrushCommands {

  /**
   * Builds the icon font + CSS from the configured icon list.
   *
   * @command custom-bootstrap-icon-font:build
  * @aliases di-font:build,ci-font:build,cbi-font:build
  * @usage drush di-font:build
   */
  public function build(): int {
    $config = \Drupal::config('custom_bootstrap_icon_font.settings');

    $icons = $config->get('icons') ?? [];
    if (empty($icons)) {
      $this->logger()->warning('No icons configured. Use the admin UI to select icons first.');
      return 1;
    }

    $font_name = trim((string) ($config->get('font_name') ?: 'custom-bootstrap-icons'));
    $icons_source_dir_rel = (string) ($config->get('icons_source_dir') ?: 'libraries/bootstrap-icons/icons');
    $generator_command = trim((string) ($config->get('generator_command') ?: 'npx fantasticon'));

    // Preflight: if the default generator is used, avoid interactive downloads.
    // This makes failures clearer in real environments (CI/prod) where prompts
    // are not possible.
    if (preg_match('/^npx\s+fantasticon(\s|$)/', $generator_command)) {
      $check = new Process(['npx', '--no-install', 'fantasticon', '--version']);
      $check->setTimeout(15);
      $check->run();
      if (!$check->isSuccessful()) {
        $this->logger()->error('Fantasticon is not available for `npx fantasticon` (not installed locally).');
        $this->logger()->error('Install it at the project root (recommended): npm install --save-dev fantasticon');
        $this->logger()->error('Then verify: npx --no-install fantasticon --version');
        return 1;
      }
    }

    $source_dir = CustomBootstrapIconFontHelper::resolveIconsSourceDir($icons_source_dir_rel);
    if (!$source_dir) {
      $this->logger()->error('Bootstrap Icons source dir not found: ' . $icons_source_dir_rel);
      $this->logger()->error('Expected absolute path: ' . rtrim(DRUPAL_ROOT, '/') . '/' . ltrim($icons_source_dir_rel, '/'));
      return 1;
    }

    $editable = \Drupal::service('config.factory')->getEditable('custom_bootstrap_icon_font.settings');
    $existing_codepoints = $editable->get('codepoints') ?? [];
    // Keep a stable, historical codepoint mapping in config, but only emit glyphs
    // (and CSS classes) for the currently configured icon list.
    $all_codepoints = CustomBootstrapIconFontHelper::assignCodepoints($icons, $existing_codepoints);
    $selected_codepoints = array_intersect_key($all_codepoints, array_fill_keys($icons, TRUE));

    $out_dir_uri = 'public://custom_bootstrap_icon_font/font';
    CustomBootstrapIconFontHelper::prepareStreamDir($out_dir_uri, FileSystemInterface::CREATE_DIRECTORY);
    $out_dir = \Drupal::service('file_system')->realpath($out_dir_uri);
    if (!$out_dir) {
      $this->logger()->error('Unable to resolve output directory: ' . $out_dir_uri);
      return 1;
    }

    $work_dir_uri = 'temporary://custom_bootstrap_icon_font';
    CustomBootstrapIconFontHelper::prepareStreamDir($work_dir_uri, FileSystemInterface::CREATE_DIRECTORY);
    $work_dir = \Drupal::service('file_system')->realpath($work_dir_uri);
    if (!$work_dir) {
      $this->logger()->error('Unable to resolve temporary directory: ' . $work_dir_uri);
      return 1;
    }

    $input_dir = $work_dir . '/icons';
    if (is_dir($input_dir)) {
      foreach (glob($input_dir . '/*.svg') ?: [] as $file) {
        @unlink($file);
      }
    }

    try {
      CustomBootstrapIconFontHelper::stageIcons($icons, $input_dir, $source_dir);
    }
    catch (\Throwable $e) {
      $this->logger()->error('Icon staging failed: ' . $e->getMessage());
      return 1;
    }

    // Fantasticon config.
    $config_path = $work_dir . '/fantasticon.config.json';
    $fantasticon_config = [
      'name' => $font_name,
      'inputDir' => $input_dir,
      'outputDir' => $out_dir,
      'fontTypes' => ['woff2', 'woff'],
      'assetTypes' => [],
      'codepoints' => $selected_codepoints,
    ];
    file_put_contents($config_path, json_encode($fantasticon_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Run generator command.
    $cmd = preg_split('/\s+/', $generator_command) ?: [];
    if (empty($cmd)) {
      $this->logger()->error('Empty generator_command.');
      return 1;
    }
    $cmd[] = '--config';
    $cmd[] = $config_path;

    $process = new Process($cmd);
    $process->setTimeout(300);
    try {
      $process->run();
    }
    catch (ProcessRuntimeException $e) {
      $this->logger()->error('Unable to run the generator command: ' . $generator_command);
      $this->logger()->error('Reason: ' . $e->getMessage());
      $this->logger()->error('Ensure Fantasticon is installed (project-level recommended: npm install --save-dev fantasticon) or set generator_command to a valid executable.');
      return 1;
    }

    if (!$process->isSuccessful()) {
      $this->logger()->error('Font generation failed.');
      $this->logger()->error(trim($process->getErrorOutput()) ?: trim($process->getOutput()));
      return 1;
    }

    $woff2_uri = $out_dir_uri . '/' . $font_name . '.woff2';
    $woff2_realpath = \Drupal::service('file_system')->realpath($woff2_uri);
    if (!$woff2_realpath || !is_file($woff2_realpath)) {
      $this->logger()->error('Expected WOFF2 output not found: ' . $woff2_uri);
      return 1;
    }

    $woff_uri = $out_dir_uri . '/' . $font_name . '.woff';
    $woff_realpath = \Drupal::service('file_system')->realpath($woff_uri);
    $woff_exists = $woff_realpath && is_file($woff_realpath);

    $version = filemtime($woff2_realpath) ?: time();
    $woff2_src = './' . $font_name . '.woff2?v=' . $version;
    $woff_src = $woff_exists ? './' . $font_name . '.woff?v=' . $version : NULL;

    $css_uri = $out_dir_uri . '/custom-bootstrap-icon-font.css';
    $css_realpath = \Drupal::service('file_system')->realpath($css_uri);
    if (!$css_realpath) {
      $this->logger()->error('Unable to resolve CSS output path: ' . $css_uri);
      return 1;
    }

    CustomBootstrapIconFontHelper::writeCss($css_realpath, $font_name, $woff2_src, $woff_src, $selected_codepoints);

    $editable
      ->set('icons', $icons)
      ->set('codepoints', $all_codepoints)
      ->set('font_name', $font_name)
      ->set('icons_source_dir', $icons_source_dir_rel)
      ->set('generator_command', $generator_command)
      ->set('version', (int) $version)
      ->save();

    \Drupal::service('cache.render')->invalidateAll();

    $this->logger()->success('Generated icon font + CSS.');
    $this->logger()->success('CSS: ' . $css_uri);
    $this->logger()->success('WOFF2: ' . $woff2_uri);
    if ($woff_exists) {
      $this->logger()->success('WOFF: ' . $woff_uri);
    }

    return 0;
  }

}
