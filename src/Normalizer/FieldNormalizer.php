<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\serialization\Normalizer\FieldNormalizer as CoreFieldNormalizer;

class FieldNormalizer extends CoreFieldNormalizer {

  /**
   * @inheritDoc
   *
   * Prevent altering a price not from our API request.
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      $parent = $data->getParent();
      if ($parent instanceof EntityAdapter) {
        $entity = $parent->getValue();
        if ($entity instanceof OrderInterface || $entity instanceof OrderItemInterface) {
          return !empty($entity->_cart_api);
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
    if (empty($data)) {
      return NULL;
    }
    if (count($data) > 1) {
      return $data;
    }
    return reset($data);
  }

  /**
   * @inheritDoc
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!is_array($data)) {
      $data = [$data];
    }
    return parent::denormalize($data, $class, $format, $context);
  }


}
