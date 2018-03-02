<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

class OrderItemsNormalizer extends EntityReferenceFieldItemNormalizer {

  public function supportsNormalization($data, $format = NULL) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $data */
    $supports = parent::supportsNormalization($data, $format);
    if ($supports) {
      $name = $data->getFieldDefinition()->getName();
      return $name == 'order_items';
    }
    return FALSE;
  }

  public function normalize($field_item, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $field_item */
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $field_item->get('entity')->getValue()) {
      $entity->_cart_api = TRUE;
      return $this->serializer->normalize($entity, $format, $context);
    }
    return $this->serializer->normalize([], $format, $context);
  }

}
