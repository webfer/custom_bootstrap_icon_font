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
          '#markup' => '<p>' . $this->t('Configuration saved. Next: run <code>drush di-font:build</code>.') . '</p>',
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
      '#markup' => '<p><strong>Build command:</strong> <code> drush di-font:build</code></p><p>(Run this command to build the font-family and the CSS code.)</p>',
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
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw_lines = preg_split('/\r\n|\r|\n/', (string) $form_state->getValue('icons'));

    $icons = [];
    foreach ($raw_lines as $line) {
      foreach (CustomBootstrapIconFontHelper::extractIconNamesFromLine((string) $line) as $icon) {
        $icons[] = $icon;
      }
    }
    $icons = array_values(array_unique(array_filter($icons)));

    if (empty($icons)) {
      $this->messenger()->addError($this->t('No icons provided.'));
      return;
    }

    $font_name = trim((string) $form_state->getValue('font_name'));
    if ($font_name === '') {
      $this->messenger()->addError($this->t('Font family name is required.'));
      return;
    }

    $icons_source_dir = trim((string) $form_state->getValue(['tooling', 'icons_source_dir']));
    if ($icons_source_dir === '') {
      $this->messenger()->addError($this->t('Bootstrap Icons source directory is required.'));
      return;
    }

    $generator_command = trim((string) $form_state->getValue(['tooling', 'generator_command']));
    if ($generator_command === '') {
      $this->messenger()->addError($this->t('Generator command is required.'));
      return;
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

    $this->messenger()->addStatus($this->t('Configuration saved. Build assets with: @cmd', ['@cmd' => 'drush di-font:build']));

    $form_state->setRedirect('custom_bootstrap_icon_font.generate', [], [
      'query' => ['di_saved' => time()],
    ]);
  }

  private function fileSystem(): FileSystemInterface {
    return \Drupal::service('file_system');
  }

}
