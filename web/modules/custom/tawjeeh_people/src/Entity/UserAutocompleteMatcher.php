<?php

namespace Drupal\tawjeeh_people\Entity;

use Drupal\Core\Entity\EntityAutocompleteMatcher;

/**
 * Service description.
 */
class UserAutocompleteMatcher extends EntityAutocompleteMatcher {

  public function getMatches($target_type, $selection_handler, $selection_settings, $string = ''): array
  {
      return parent::getMatches($target_type, $selection_handler, $selection_settings, $string);
  }
}
