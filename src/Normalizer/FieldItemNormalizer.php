<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\serialization\Normalizer\FieldItemNormalizer as CoreFieldItemNormalizer;

/**
 * Ensures normalized prices are rounded.
 *
 * @tod is this needed? JavaScripts locale format may be fine.
 */
class FieldItemNormalizer extends CoreFieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = FieldItemInterface::class;

  /**
   * @inheritDoc
   *
   * Prevent altering a price not from our API request.
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      $parent = $data->getParent();
      if ($parent instanceof FieldItemListInterface) {
        $parent = $parent->getParent();
        if ($parent instanceof EntityAdapter) {
          $entity = $parent->getValue();
          if ($entity instanceof OrderInterface || $entity instanceof OrderItemInterface) {
            return !empty($entity->_cart_api);
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $data = parent::normalize($field_item, $format, $context);
    // This will always be true, but here for type hinting for IDE.
    if (!$field_item instanceof FieldItemInterface) {
      return $data;
    }
    $main_property = $field_item::mainPropertyName();
    if (count($data) == 1) {
      return reset($data);
    }
    elseif (isset($data[$main_property])) {
      return $data[$main_property];
    }
    return $data;
  }

}
