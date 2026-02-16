<?php

namespace Drupal\custom_bootstrap_icon_font\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_bootstrap_icon_font\Helper\CustomBootstrapIconFontHelper;

final class CustomBootstrapIconFontGenerateForm extends FormBase {

  public function getFormId(): string {
    return 'custom_bootstrap_icon_font_generate_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('custom_bootstrap_icon_font.settings');
    $request = $this->getRequest();

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

    $icons = $config->get('icons') ?? [];
    $form['icons'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bootstrap Icons (one per line)'),
      '#default_value' => implode("\n", $icons),
      '#rows' => 14,
      '#required' => TRUE,
      '#description' => $this->t('Examples: arrow-right-circle-fill, bi-arrow-right-circle-fill, bi bi-arrow-right-circle-fill, or <i class="bi bi-arrow-right-circle-fill"></i>. The module extracts the bi-* classname automatically.'),
    ];

    // Tooling configuration (used by the Drush build command).
    $form['tooling'] = [
      '#type' => 'details',
      '#title' => $this->t('Tooling'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $icons_source_dir = (string) ($config->get('icons_source_dir') ?: 'libraries/bootstrap-icons/icons');
    $generator_command = (string) ($config->get('generator_command') ?: 'npx fantasticon');
    $resolved_source_dir = CustomBootstrapIconFontHelper::resolveIconsSourceDir($icons_source_dir);

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

    // Preview section (uses the generated CSS/font when present).
    $font_name = $config->get('font_name') ?: 'custom-bootstrap-icons';
    $existing_codepoints = $config->get('codepoints') ?? [];
    $preview_codepoints = CustomBootstrapIconFontHelper::assignCodepoints($icons, $existing_codepoints);

    $css_uri = 'public://custom_bootstrap_icon_font/font/custom-bootstrap-icon-font.css';
    $css_realpath = $this->fileSystem()->realpath($css_uri);
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
        $icon = CustomBootstrapIconFontHelper::normalizeIconName((string) $icon);
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
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
      '#suffix' => '<div class="description">' . $this->t('Saves the settings only. Does not generate CSS or font files.') . '</div>',
    ];

    $form['actions']['build'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and build now'),
      '#submit' => ['::submitBuild'],
      '#button_type' => 'secondary',
      '#suffix' => '<div class="description">' . $this->t('Saves settings and generates the CSS + font files immediately. Requires Node tooling (Fantasticon).') . '</div>',
    ];

    return $form;
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
    $result = \Drupal::service('custom_bootstrap_icon_font.builder')->build();

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
    $raw_lines = preg_split('/\r\n|\r|\n/', (string) $form_state->getValue('icons'));

    $icons = [];
    foreach ($raw_lines as $line) {
      foreach (CustomBootstrapIconFontHelper::extractIconNamesFromLine((string) $line) as $icon) {
        $icons[] = $icon;
      }
    }
    $icons = array_values(array_unique(array_filter($icons)));

    if (empty($icons)) {
      $form_state->setErrorByName('icons', $this->t('No icons provided.'));
      return FALSE;
    }

    $font_name = trim((string) $form_state->getValue('font_name'));
    if ($font_name === '') {
      $form_state->setErrorByName('font_name', $this->t('Font family name is required.'));
      return FALSE;
    }

    $icons_source_dir = trim((string) $form_state->getValue(['tooling', 'icons_source_dir']));
    if ($icons_source_dir === '') {
      $form_state->setErrorByName('tooling][icons_source_dir', $this->t('Bootstrap Icons source directory is required.'));
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
      ->set('icons', $icons)
      ->set('codepoints', $codepoints)
      ->set('font_name', $font_name)
      ->set('icons_source_dir', $icons_source_dir)
      ->set('generator_command', $generator_command)
      ->save();

    return TRUE;
  }

  private function fileSystem(): FileSystemInterface {
    return \Drupal::service('file_system');
  }

}
