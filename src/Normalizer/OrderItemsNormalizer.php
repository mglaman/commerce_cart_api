<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

/**
 * Expands order items to their referenced entity.
 */
class OrderItemsNormalizer extends EntityReferenceFieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      $route = \Drupal::routeMatch()->getRouteObject();
      $name = $data->getFieldDefinition()->getName();
      return $name == 'order_items' && $route->hasRequirement('_cart_api');
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $field_item */
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $field_item->get('entity')->getValue()) {
      return $this->serializer->normalize($entity, $format, $context);
    }
    return $this->serializer->normalize([], $format, $context);
  }

}
