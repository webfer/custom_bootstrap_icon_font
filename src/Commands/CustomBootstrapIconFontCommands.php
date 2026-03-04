<?php

namespace Drupal\custom_bootstrap_icon_font\Commands;

use Drupal\custom_bootstrap_icon_font\Service\CustomBootstrapIconFontBuilder;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for building the custom icon font.
 */
final class CustomBootstrapIconFontCommands extends DrushCommands {

  public function __construct(
    private readonly CustomBootstrapIconFontBuilder $builder,
  ) {
    parent::__construct();
  }

  /**
   * Builds the icon font + CSS from the configured icon list.
   *
   * @command custom-bootstrap-icon-font:build
   * @aliases di-font:build,ci-font:build,cbi-font:build
   * @usage drush di-font:build
   */
  public function build(): int {
    $result = $this->builder->build();

    foreach ($result['errors'] ?? [] as $message) {
      $this->logger()->error((string) $message);
    }
    foreach ($result['messages'] ?? [] as $message) {
      $this->logger()->success((string) $message);
    }

    return !empty($result['success']) ? 0 : 1;
  }

}
