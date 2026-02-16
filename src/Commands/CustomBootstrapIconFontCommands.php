<?php

namespace Drupal\custom_bootstrap_icon_font\Commands;

use Drush\Commands\DrushCommands;

final class CustomBootstrapIconFontCommands extends DrushCommands {

  /**
   * Builds the icon font + CSS from the configured icon list.
   *
   * @command custom-bootstrap-icon-font:build
  * @aliases di-font:build,ci-font:build,cbi-font:build
  * @usage drush di-font:build
   */
  public function build(): int {
    $result = \Drupal::service('custom_bootstrap_icon_font.builder')->build();

    foreach ($result['errors'] ?? [] as $message) {
      $this->logger()->error((string) $message);
    }
    foreach ($result['messages'] ?? [] as $message) {
      $this->logger()->success((string) $message);
    }

    return !empty($result['success']) ? 0 : 1;
  }

}
