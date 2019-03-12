<?php

namespace Drupal\commerce_cart_api\Plugin\Field\FieldType;

use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

class FormattablePriceItem extends PriceItem {
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['formatted'] = DataDefinition::create('formatted_price')
      ->setLabel(t('Formatted price'))
      ->setRequired(FALSE);
    return $properties;
  }

}
