<?php

namespace Drupal\custom_bootstrap_icon_font\Twig;

use Drupal\Component\Utility\Html;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers for rendering icons from the generated icon font.
 */
final class CustomBootstrapIconFontTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('di_font_icon', [$this, 'render'], ['is_safe' => ['html']]),
      // Backwards compatible alias.
      new TwigFunction('ci_font_icon', [$this, 'render'], ['is_safe' => ['html']]),
      new TwigFunction('cbi_font_icon', [$this, 'render'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * Renders an icon span using the generated CSS classes.
   *
   * @param string $name
   *   Icon name, for example "arrow-right-circle-fill" or "fa-solid-arrow-down".
   *   The "bi-" prefix is accepted and stripped.
   * @param array $options
   *   Optional options.
   *
   * @return string
   *   Rendered HTML.
   */
  public function render(string $name, array $options = []): string {
    $name = preg_replace('/^bi-/', '', trim($name)) ?? $name;
    if ($name === '') {
      return '';
    }

    $classes = ['di', 'di-' . Html::getClass($name)];
    if (!empty($options['class'])) {
      $classes = array_merge($classes, preg_split('/\s+/', trim((string) $options['class'])) ?: []);
    }

    $attrs = 'class="' . Html::escape(implode(' ', array_filter($classes))) . '" aria-hidden="true"';
    return '<span ' . $attrs . '></span>';
  }

}
