<?php

namespace Drupal\custom_bootstrap_icon_font\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\custom_bootstrap_icon_font\Helper\CustomBootstrapIconFontHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

final class CustomBootstrapIconFontBuilder {

  private LoggerInterface $logger;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly CacheBackendInterface $renderCache,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('custom_bootstrap_icon_font');
  }

  /**
   * Builds the icon font + CSS from the configured icon list.
   *
   * @return array
   *   Keys:
   *   - success: bool
   *   - messages: string[]
   *   - errors: string[]
   *   - assets: array{css_uri?:string, woff2_uri?:string, woff_uri?:string}
   *   - version: int
   */
  public function build(): array {
    $messages = [];
    $errors = [];
    $assets = [];

    $config = $this->configFactory->get('custom_bootstrap_icon_font.settings');

    $icons = $config->get('icons') ?? [];
    if (empty($icons)) {
      $errors[] = 'No icons configured. Use the admin UI to select icons first.';
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
    }

    $font_name = trim((string) ($config->get('font_name') ?: 'custom-bootstrap-icons'));
    $icons_source_dir_rel = (string) ($config->get('icons_source_dir') ?: 'libraries/bootstrap-icons/icons');
    $generator_command = trim((string) ($config->get('generator_command') ?: 'npx fantasticon'));

    if (preg_match('/^npx\s+fantasticon(\s|$)/', $generator_command)) {
      $check = new Process(['npx', '--no-install', 'fantasticon', '--version']);
      $check->setTimeout(15);
      $check->run();
      if (!$check->isSuccessful()) {
        $errors[] = 'Fantasticon is not available for `npx fantasticon` (not installed locally).';
        $errors[] = 'Install it at the project root (recommended): npm install --save-dev fantasticon';
        $errors[] = 'Then verify: npx --no-install fantasticon --version';
        return [
          'success' => FALSE,
          'messages' => $messages,
          'errors' => $errors,
          'assets' => $assets,
          'version' => 0,
        ];
      }
    }

    $source_dir = CustomBootstrapIconFontHelper::resolveIconsSourceDir($icons_source_dir_rel);
    if (!$source_dir) {
      $errors[] = 'Bootstrap Icons source dir not found: ' . $icons_source_dir_rel;
      $errors[] = 'Expected absolute path: ' . rtrim(DRUPAL_ROOT, '/') . '/' . ltrim($icons_source_dir_rel, '/');
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
    }

    $editable = $this->configFactory->getEditable('custom_bootstrap_icon_font.settings');
    $existing_codepoints = $editable->get('codepoints') ?? [];

    $all_codepoints = CustomBootstrapIconFontHelper::assignCodepoints($icons, $existing_codepoints);
    $selected_codepoints = array_intersect_key($all_codepoints, array_fill_keys($icons, TRUE));

    $out_dir_uri = 'public://custom_bootstrap_icon_font/font';
    CustomBootstrapIconFontHelper::prepareStreamDir($out_dir_uri, FileSystemInterface::CREATE_DIRECTORY);
    $out_dir = $this->fileSystem->realpath($out_dir_uri);
    if (!$out_dir) {
      $errors[] = 'Unable to resolve output directory: ' . $out_dir_uri;
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
    }

    $work_dir_uri = 'temporary://custom_bootstrap_icon_font';
    CustomBootstrapIconFontHelper::prepareStreamDir($work_dir_uri, FileSystemInterface::CREATE_DIRECTORY);
    $work_dir = $this->fileSystem->realpath($work_dir_uri);
    if (!$work_dir) {
      $errors[] = 'Unable to resolve temporary directory: ' . $work_dir_uri;
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
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
      $errors[] = 'Icon staging failed: ' . $e->getMessage();
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
    }

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

    $cmd = preg_split('/\s+/', $generator_command) ?: [];
    if (empty($cmd)) {
      $errors[] = 'Empty generator_command.';
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
    }
    $cmd[] = '--config';
    $cmd[] = $config_path;

    $process = new Process($cmd);
    $process->setTimeout(300);
    try {
      $process->run();
    }
    catch (ProcessRuntimeException $e) {
      $errors[] = 'Unable to run the generator command: ' . $generator_command;
      $errors[] = 'Reason: ' . $e->getMessage();
      $errors[] = 'Ensure Fantasticon is installed (npm install --save-dev fantasticon) or set generator_command to a valid executable.';
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
    }

    if (!$process->isSuccessful()) {
      $errors[] = 'Font generation failed.';
      $errors[] = trim($process->getErrorOutput()) ?: trim($process->getOutput());
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
    }

    $woff2_uri = $out_dir_uri . '/' . $font_name . '.woff2';
    $woff2_realpath = $this->fileSystem->realpath($woff2_uri);
    if (!$woff2_realpath || !is_file($woff2_realpath)) {
      $errors[] = 'Expected WOFF2 output not found: ' . $woff2_uri;
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
    }

    $woff_uri = $out_dir_uri . '/' . $font_name . '.woff';
    $woff_realpath = $this->fileSystem->realpath($woff_uri);
    $woff_exists = $woff_realpath && is_file($woff_realpath);

    $version = filemtime($woff2_realpath) ?: time();
    $woff2_src = './' . $font_name . '.woff2?v=' . $version;
    $woff_src = $woff_exists ? './' . $font_name . '.woff?v=' . $version : NULL;

    $css_uri = $out_dir_uri . '/custom-bootstrap-icon-font.css';
    $css_realpath = $this->fileSystem->realpath($css_uri);
    if (!$css_realpath) {
      $errors[] = 'Unable to resolve CSS output path: ' . $css_uri;
      return [
        'success' => FALSE,
        'messages' => $messages,
        'errors' => $errors,
        'assets' => $assets,
        'version' => 0,
      ];
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

    $this->renderCache->invalidateAll();

    $assets = [
      'css_uri' => $css_uri,
      'woff2_uri' => $woff2_uri,
    ];
    if ($woff_exists) {
      $assets['woff_uri'] = $woff_uri;
    }

    $messages[] = 'Generated icon font + CSS.';
    $messages[] = 'CSS: ' . $css_uri;
    $messages[] = 'WOFF2: ' . $woff2_uri;
    if ($woff_exists) {
      $messages[] = 'WOFF: ' . $woff_uri;
    }

    $this->logger->info('Icon font generated.');

    return [
      'success' => TRUE,
      'messages' => $messages,
      'errors' => $errors,
      'assets' => $assets,
      'version' => (int) $version,
    ];
  }

}
