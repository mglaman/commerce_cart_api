<?php

namespace Drupal\commerce_cart_api\Plugin\DataType;

use Drupal\commerce_cart_api\Plugin\Field\FieldType\FormattablePriceItem;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

/**
 * Defines a data type for formatted price fields.
 *
 * @DataType(
 *   id = "formatted_price",
 *   label = @Translation("Formatted price")
 * )
 */
class FormattedPrice extends StringData {
  public function getValue() {
    if (!$this->getParent()->isEmpty()) {
      $price = $this->getParent()->toPrice();
      $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
      $formatted = $currency_formatter->format($price->getNumber(), $price->getCurrencyCode());
      return $formatted;
    }
    return NULL;
  }

  /**
   * @return \Drupal\commerce_cart_api\Plugin\Field\FieldType\FormattablePriceItem
   */
  public function getParent() {
    $parent = parent::getParent();
    assert($parent instanceof FormattablePriceItem);
    return $parent;
  }
}
