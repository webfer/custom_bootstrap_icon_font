<?php

namespace Drupal\custom_bootstrap_icon_font\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_bootstrap_icon_font\Helper\CustomBootstrapIconFontHelper;
use Drupal\custom_bootstrap_icon_font\Service\CustomBootstrapIconFontBuilder;
use Drupal\file\Entity\File;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CustomBootstrapIconFontGenerateForm extends ConfigFormBase {

  /**
   * NOTE: FormBase uses DependencySerializationTrait. Service properties must
   * not be private/readonly, otherwise they may not survive form caching across
   * requests (e.g. managed_file upload rebuilds).
   */
  protected LoggerInterface $logger;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager,
    protected FileSystemInterface $fileSystem,
    protected CustomBootstrapIconFontBuilder $builder,
    LoggerInterface $logger,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->logger = $logger;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('file_system'),
      $container->get('custom_bootstrap_icon_font.builder'),
      $container->get('logger.channel.custom_bootstrap_icon_font'),
    );
  }

  protected function getEditableConfigNames(): array {
    return ['custom_bootstrap_icon_font.settings'];
  }

  public function getFormId(): string {
    return 'custom_bootstrap_icon_font_generate_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('custom_bootstrap_icon_font.settings');
    $request = $this->getRequest();

    // Required for file uploads (including managed_file upload button).
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['#attached']['library'][] = 'custom_bootstrap_icon_font/admin';

    $form['intro'] = [
      '#markup' => '<p>Configure a custom icon font based on Bootstrap Icons. Generation is performed via Drush for production/Composer friendliness.</p>',
    ];

    if ($request->query->has('di_saved') || $request->query->has('ci_saved')) {
      $form['saved'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--status'],
          'role' => 'status',
          'aria-label' => $this->t('Status message'),
        ],
        'message' => [
          '#markup' => '<p>' . $this->t('Configuration saved. Next: click <strong>Save and build now</strong> to generate the CSS + font files (or run <code>drush di-font:build</code>).') . '</p>',
        ],
      ];
    }

    if ($request->query->has('di_built')) {
      $form['built'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--status'],
          'role' => 'status',
          'aria-label' => $this->t('Status message'),
        ],
        'message' => [
          '#markup' => '<p>' . $this->t('Build completed. Assets should now be available in public files.') . '</p>',
        ],
      ];
    }

    $form['font_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font family name'),
      '#default_value' => $config->get('font_name') ?: 'custom-bootstrap-icons',
      '#required' => TRUE,
      '#description' => $this->t('Used in @font-face and as the font-family value.'),
    ];

    // Split config: Bootstrap vs Font Awesome.
    // Backwards compatibility: if the split lists aren't populated yet, infer
    // them from the legacy `icons` list.
    $bootstrap_icons = $config->get('bootstrap_icons') ?? [];
    $fontawesome_icons = $config->get('fontawesome_icons') ?? [];
    if (empty($bootstrap_icons) && empty($fontawesome_icons)) {
      $legacy_icons = $config->get('icons') ?? [];
      foreach ($legacy_icons as $icon) {
        $icon = (string) $icon;
        if (CustomBootstrapIconFontHelper::parseFontAwesomeIconId($icon)) {
          $fontawesome_icons[] = $icon;
        }
        else {
          $bootstrap_icons[] = $icon;
        }
      }
    }

    $form['icons_bootstrap'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bootstrap Icons (one per line)'),
      '#default_value' => implode("\n", $bootstrap_icons),
      '#rows' => 10,
      '#required' => FALSE,
      '#description' => $this->t('Examples: arrow-right-circle-fill, bi-arrow-right-circle-fill, bi bi-arrow-right-circle-fill. You can also paste the HTML, for example: <code>&lt;i class="bi bi-twitter"&gt;&lt;/i&gt;</code>.'),
    ];

    $form['icons_fontawesome'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Font Awesome (Free) icons (one per line)'),
      '#default_value' => implode("\n", $fontawesome_icons),
      '#rows' => 10,
      '#required' => FALSE,
      '#description' => $this->t('Examples: fa-arrow-down or fa-solid-arrow-down. You can also paste the HTML, for example: <code>&lt;i class="fa-solid fa-arrow-down"&gt;&lt;/i&gt;</code>. Upload the SVGs into libraries/fontawesome/icons first (use the upload section below).'),
    ];

    // Tooling configuration (used by the Drush build command).
    $form['tooling'] = [
      '#type' => 'details',
      '#title' => $this->t('Tooling'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $icons_source_dir = (string) ($config->get('icons_source_dir') ?: 'libraries/bootstrap-icons/icons');
    $fontawesome_icons_source_dir = (string) ($config->get('fontawesome_icons_source_dir') ?: 'libraries/fontawesome/icons');
    $generator_command = (string) ($config->get('generator_command') ?: 'npx fantasticon');
    $resolved_source_dir = CustomBootstrapIconFontHelper::resolveIconsSourceDir($icons_source_dir);
    $resolved_fa_source_dir = CustomBootstrapIconFontHelper::resolveIconsSourceDir($fontawesome_icons_source_dir);

    $form['tooling']['icons_source_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bootstrap Icons source directory'),
      '#default_value' => $icons_source_dir,
      '#required' => TRUE,
      '#description' => $this->t('Path relative to DRUPAL_ROOT. Recommended: libraries/bootstrap-icons/icons'),
    ];

    $form['tooling']['icons_source_dir_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Source dir status'),
      '#markup' => $resolved_source_dir
        ? $this->t('Found: @path', ['@path' => $resolved_source_dir])
        : $this->t('Not found. Expected: @path', ['@path' => rtrim(DRUPAL_ROOT, '/') . '/' . ltrim($icons_source_dir, '/')]),
    ];

    $form['tooling']['fontawesome_icons_source_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Awesome SVG source directory'),
      '#default_value' => $fontawesome_icons_source_dir,
      '#required' => FALSE,
      '#description' => $this->t('Path relative to DRUPAL_ROOT. Recommended: libraries/fontawesome/icons (this is where the upload tool copies SVGs).'),
    ];

    $form['tooling']['fontawesome_icons_source_dir_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Font Awesome dir status'),
      '#markup' => $resolved_fa_source_dir
        ? $this->t('Found: @path', ['@path' => $resolved_fa_source_dir])
        : $this->t('Not found. Expected: @path', ['@path' => rtrim(DRUPAL_ROOT, '/') . '/' . ltrim($fontawesome_icons_source_dir, '/')]),
    ];

    $form['tooling']['generator_command'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font generator command'),
      '#default_value' => $generator_command,
      '#required' => TRUE,
      '#description' => $this->t('Command used by Drush to run Fantasticon. Example: npx fantasticon or /path/to/fantasticon'),
    ];

    $form['tooling']['drush_help'] = [
      '#markup' => '<p><strong>Build assets:</strong> click <strong>Save and build now</strong> below, or run <code>drush di-font:build</code>.</p><p>This step generates the CSS file and the font files under <code>public://custom_bootstrap_icon_font/font/</code>.</p>',
    ];

    // Optional uploads (useful for Font Awesome SVGs or custom SVGs).
    // NOTE: Writing to DRUPAL_ROOT/libraries may not be allowed on all hosts.
    $form['uploads'] = [
      '#type' => 'details',
      '#title' => $this->t('Upload SVG icons (optional)'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#description' => $this->t('Uploads SVGs via Drupal and copies them into a /libraries/... folder under your Drupal webroot. This is handy for Font Awesome Free SVGs or custom SVGs. Your web server user must have write permissions to the destination folder.'),
    ];

    $form['uploads']['two_step_note'] = [
      '#markup' => '<p><strong>Two-step:</strong> first click the <em>Upload</em> button next to “SVG files”. After the upload completes, click <em>Copy uploaded SVG(s) to destination</em>.</p>',
    ];

    $form['uploads']['howto'] = [
      '#markup' => '<p><strong>Font Awesome Free workflow:</strong></p>'
        . '<ol>'
        . '<li>Find an icon in the Free collection: <a href="https://fontawesome.com/search?ic=free" target="_blank" rel="noopener noreferrer">fontawesome.com/search?ic=free</a></li>'
        . '<li>Download the SVG for that icon.</li>'
        . '<li>Upload the SVG here and choose the <strong>Font Awesome</strong> destination.</li>'
        . '</ol>'
        . '<p><strong>Tip:</strong> If you specifically want the file to end up under <code>/libraries/</code> (like Bootstrap Icons), pick the destination below. If your host disallows writing to the webroot, upload via SFTP/CI instead.</p>',
    ];

    $form['uploads']['destination'] = [
      '#type' => 'radios',
      '#title' => $this->t('Destination'),
      '#default_value' => 'fontawesome',
      '#options' => [
        'fontawesome' => $this->t('Font Awesome: /libraries/fontawesome/icons'),
        'bootstrap' => $this->t('Bootstrap Icons source dir (as configured above)'),
      ],
    ];

    $form['uploads']['svgs'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('SVG files'),
      '#multiple' => TRUE,
      '#upload_location' => 'temporary://custom_bootstrap_icon_font_uploads',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'svg'],
      ],
      '#description' => $this->t('Choose one or more .svg files, then click the Upload button that appears next to this field.'),
    ];

    $form['uploads']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Copy uploaded SVG(s) to destination'),
      '#submit' => ['::submitUploadSvgs'],
      '#limit_validation_errors' => [['uploads', 'destination'], ['uploads', 'svgs']],
    ];

    // Preview section (uses the generated CSS/font when present).
    $font_name = $config->get('font_name') ?: 'custom-bootstrap-icons';
    $existing_codepoints = $config->get('codepoints') ?? [];
    $icons = array_values(array_unique(array_filter(array_merge($bootstrap_icons, $fontawesome_icons))));
    $preview_codepoints = CustomBootstrapIconFontHelper::assignCodepoints($icons, $existing_codepoints);

    $css_uri = 'public://custom_bootstrap_icon_font/font/custom-bootstrap-icon-font.css';
    $css_realpath = $this->fileSystem->realpath($css_uri);
    $has_assets = $css_realpath && is_file($css_realpath);
    $version = (int) ($config->get('version') ?? 0);

    $form['build_status'] = [
      '#type' => 'details',
      '#title' => $this->t('Build status'),
      '#open' => TRUE,
    ];
    $form['build_status']['assets'] = [
      '#type' => 'item',
      '#title' => $this->t('Assets'),
      '#markup' => $has_assets
        ? $this->t('Found generated CSS in public files.')
        : $this->t('Not built yet. Run <code>drush di-font:build</code>.'),
    ];
    $form['build_status']['version'] = [
      '#type' => 'item',
      '#title' => $this->t('Version'),
      '#markup' => $version ? $this->t('@v', ['@v' => $version]) : $this->t('N/A'),
    ];

    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Icons preview'),
      '#open' => TRUE,
    ];

    $form['preview']['help'] = [
      '#markup' => $has_assets
        ? '<p>' . $this->t('Preview uses the generated font-family <strong>@font</strong>.', ['@font' => $font_name]) . '</p>'
        : '<p>' . $this->t('Generate the font once to enable visual previews. Codepoints are still shown below.') . '</p>',
    ];

    if (!empty($icons)) {
      $rows = [];
      foreach ($icons as $icon) {
        $icon = trim((string) $icon);
        if ($icon === '') {
          continue;
        }

        $class = 'di-' . Html::getClass($icon);
        $cp = (int) ($preview_codepoints[$icon] ?? 0);
        $hex = strtoupper(str_pad(dechex($cp), 4, '0', STR_PAD_LEFT));
        $unicode = $cp ? ('U+' . $hex) : '';
        $css_code = $cp ? ('\\' . strtolower($hex)) : '';

        $preview = '<span class="di ' . Html::escape($class) . '" aria-hidden="true"></span>';
        $html_snippet = '<span class="di ' . Html::escape($class) . '" aria-hidden="true"></span>';
        $rows[] = [
          'preview' => [
            'data' => [
              '#markup' => $preview,
            ],
          ],
          'name' => Html::escape($icon),
          'class' => Html::escape($class),
          'html' => [
            'data' => [
              '#markup' => '<code>' . Html::escape($html_snippet) . '</code>',
            ],
          ],
          'code' => [
            'data' => [
              '#markup' => $cp
                ? 'Unicode: <code>' . Html::escape($unicode) . '</code><br>CSS: <code>' . Html::escape($css_code) . '</code>'
                : '',
            ],
          ],
        ];
      }

      $form['preview']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Icon'),
          $this->t('Name'),
          $this->t('Class'),
          $this->t('HTML'),
          $this->t('Code'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No icons selected.'),
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['custom-bootstrap-icon-font-action'],
      ],
    ];
    $form['actions']['save_group']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];
    $form['actions']['save_group']['help'] = [
      '#markup' => '<div class="description">' . $this->t('Saves the settings only. Does not generate CSS or font files.') . '</div>',
    ];

    $form['actions']['build_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['custom-bootstrap-icon-font-action'],
      ],
    ];
    $form['actions']['build_group']['build'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and build now'),
      '#submit' => ['::submitBuild'],
      '#button_type' => 'secondary',
    ];
    $form['actions']['build_group']['help'] = [
      '#markup' => '<div class="description">' . $this->t('Saves settings and generates the CSS + font files immediately. Requires Node tooling (Fantasticon).') . '</div>',
    ];

    return $form;
  }

  /**
   * Copies uploaded SVGs into a /libraries/... folder under DRUPAL_ROOT.
   */
  public function submitUploadSvgs(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('uploads');
    $destination = (string) ($values['destination'] ?? 'fontawesome');

    // managed_file can come back in multiple shapes depending on configuration:
    // - [12, 13]
    // - ['fids' => [12, 13]]
    // - '12 13'
    $svgs_value = $form_state->getValue(['uploads', 'svgs']);
    if (is_array($svgs_value) && isset($svgs_value['fids'])) {
      $file_ids = $svgs_value['fids'];
    }
    elseif (is_string($svgs_value)) {
      $file_ids = preg_split('/[\s,]+/', trim($svgs_value)) ?: [];
    }
    elseif (is_int($svgs_value) || is_numeric($svgs_value)) {
      $file_ids = [(int) $svgs_value];
    }
    else {
      $file_ids = $svgs_value;
    }

    $file_ids = array_values(array_unique(array_filter(array_map('intval', (array) $file_ids))));

    if (empty($file_ids)) {
      // Diagnostic for admins: record what managed_file returned.
      try {
        $this->logger->notice('SVG upload copy attempted but no FIDs found. Raw value: @value', [
          '@value' => is_scalar($svgs_value) ? (string) $svgs_value : json_encode($svgs_value),
        ]);
      }
      catch (\Throwable $e) {
        // Ignore.
      }
      $this->messenger()->addError($this->t('No uploaded SVGs found yet. Use the Upload button next to “SVG files” first, then click “Copy uploaded SVG(s) to destination”.'));
      return;
    }

    $config = $this->config('custom_bootstrap_icon_font.settings');
    $bootstrap_rel = (string) ($config->get('icons_source_dir') ?: 'libraries/bootstrap-icons/icons');

    $target_rel = $destination === 'bootstrap'
      ? $bootstrap_rel
      : 'libraries/fontawesome/icons';

    $target_abs = rtrim(DRUPAL_ROOT, '/') . '/' . ltrim($target_rel, '/');

    if (!is_dir($target_abs)) {
      if (!@mkdir($target_abs, 0775, TRUE) && !is_dir($target_abs)) {
        $this->messenger()->addError($this->t('Unable to create destination directory: @dir', ['@dir' => $target_abs]));
        $this->messenger()->addError($this->t('Tip: on many hosts you cannot write to DRUPAL_ROOT/libraries via the web UI. Upload via SFTP/CI instead.'));
        return;
      }
    }

    if (!is_writable($target_abs)) {
      $this->messenger()->addError($this->t('Destination directory is not writable by the web server: @dir', ['@dir' => $target_abs]));
      $this->messenger()->addError($this->t('Upload the files via SFTP/CI or adjust permissions.'));
      return;
    }

    $copied = 0;
    foreach ($file_ids as $fid) {
      $file = File::load((int) $fid);
      if (!$file) {
        continue;
      }

      $src_realpath = $this->fileSystem->realpath($file->getFileUri());
      if (!$src_realpath || !is_file($src_realpath)) {
        continue;
      }

      $basename = $this->fileSystem->basename($src_realpath);
      // Keep the original filename, but ensure it ends with .svg.
      if (!preg_match('/\.svg$/i', $basename)) {
        $basename .= '.svg';
      }
      $dest_realpath = rtrim($target_abs, '/') . '/' . $basename;

      if (@copy($src_realpath, $dest_realpath)) {
        $copied++;
      }
      else {
        $this->messenger()->addError($this->t('Failed to copy @file to @dir', ['@file' => $basename, '@dir' => $target_abs]));
      }

      // Clean up temp file entity.
      try {
        $file->delete();
      }
      catch (\Throwable $e) {
        // Ignore.
      }
    }

    if ($copied > 0) {
      $this->messenger()->addStatus($this->t('Copied @count SVG file(s) to @dir', ['@count' => $copied, '@dir' => $target_abs]));
    }

    $form_state->setRebuild(TRUE);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->saveConfiguration($form_state)) {
      return;
    }

    $this->messenger()->addStatus($this->t('Configuration saved. Next: click “Save and build now” to generate assets (or run: @cmd).', ['@cmd' => 'drush di-font:build']));

    $form_state->setRedirect('custom_bootstrap_icon_font.generate', [], [
      'query' => ['di_saved' => time()],
    ]);
  }

  public function submitBuild(array &$form, FormStateInterface $form_state): void {
    if (!$this->saveConfiguration($form_state)) {
      return;
    }

    // Run the same build logic as the Drush command, but from the UI.
    // This may take some time depending on your server and Node tooling.
    $result = $this->builder->build();

    foreach ($result['errors'] ?? [] as $message) {
      $this->messenger()->addError($this->t((string) $message));
    }
    foreach ($result['messages'] ?? [] as $message) {
      $this->messenger()->addStatus($this->t((string) $message));
    }

    if (!empty($result['success'])) {
      $form_state->setRedirect('custom_bootstrap_icon_font.generate', [], [
        'query' => ['di_built' => time()],
      ]);
    }
  }

  private function saveConfiguration(FormStateInterface $form_state): bool {
    $bootstrap_lines = preg_split('/\r\n|\r|\n/', (string) $form_state->getValue('icons_bootstrap'));
    $fontawesome_lines = preg_split('/\r\n|\r|\n/', (string) $form_state->getValue('icons_fontawesome'));

    $bootstrap_icons = [];
    foreach ($bootstrap_lines as $line) {
      foreach (CustomBootstrapIconFontHelper::extractIconNamesFromLine((string) $line) as $icon) {
        if (!CustomBootstrapIconFontHelper::parseFontAwesomeIconId((string) $icon)) {
          $bootstrap_icons[] = $icon;
        }
      }
    }
    $bootstrap_icons = array_values(array_unique(array_filter($bootstrap_icons)));

    $fontawesome_icons = [];
    foreach ($fontawesome_lines as $line) {
      foreach (CustomBootstrapIconFontHelper::extractIconNamesFromLine((string) $line) as $icon) {
        if (CustomBootstrapIconFontHelper::parseFontAwesomeIconId((string) $icon)) {
          $fontawesome_icons[] = $icon;
        }
      }
    }
    $fontawesome_icons = array_values(array_unique(array_filter($fontawesome_icons)));

    $icons = array_values(array_unique(array_filter(array_merge($bootstrap_icons, $fontawesome_icons))));
    if (empty($icons)) {
      $form_state->setErrorByName('icons_bootstrap', $this->t('Provide at least one Bootstrap icon or one Font Awesome icon.'));
      $form_state->setErrorByName('icons_fontawesome', $this->t('Provide at least one Bootstrap icon or one Font Awesome icon.'));
      return FALSE;
    }

    $has_bootstrap = !empty($bootstrap_icons);
    $has_fontawesome = !empty($fontawesome_icons);

    $font_name = trim((string) $form_state->getValue('font_name'));
    if ($font_name === '') {
      $form_state->setErrorByName('font_name', $this->t('Font family name is required.'));
      return FALSE;
    }

    $icons_source_dir = trim((string) $form_state->getValue(['tooling', 'icons_source_dir']));
    if ($has_bootstrap && $icons_source_dir === '') {
      $form_state->setErrorByName('tooling][icons_source_dir', $this->t('Bootstrap Icons source directory is required when Bootstrap icons are selected.'));
      return FALSE;
    }

    $fontawesome_icons_source_dir = trim((string) $form_state->getValue(['tooling', 'fontawesome_icons_source_dir']));
    if ($has_fontawesome && $fontawesome_icons_source_dir === '') {
      $form_state->setErrorByName('tooling][fontawesome_icons_source_dir', $this->t('Font Awesome SVG source directory is required when Font Awesome icons are selected.'));
      return FALSE;
    }

    $generator_command = trim((string) $form_state->getValue(['tooling', 'generator_command']));
    if ($generator_command === '') {
      $form_state->setErrorByName('tooling][generator_command', $this->t('Generator command is required.'));
      return FALSE;
    }

    $editable = $this->configFactory()->getEditable('custom_bootstrap_icon_font.settings');
    $existing_codepoints = $editable->get('codepoints') ?? [];
    $codepoints = CustomBootstrapIconFontHelper::assignCodepoints($icons, $existing_codepoints);

    $editable
      // Backwards-compatible merged list.
      ->set('icons', $icons)
      ->set('bootstrap_icons', $bootstrap_icons)
      ->set('fontawesome_icons', $fontawesome_icons)
      ->set('codepoints', $codepoints)
      ->set('font_name', $font_name)
      ->set('icons_source_dir', $icons_source_dir)
      ->set('fontawesome_icons_source_dir', $fontawesome_icons_source_dir)
      ->set('generator_command', $generator_command)
      ->save();

    return TRUE;
  }

}
