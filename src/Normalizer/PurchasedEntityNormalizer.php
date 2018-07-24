<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

/**
 * Expands the purchased entity to their referenced entity.
 */
class PurchasedEntityNormalizer extends EntityReferenceFieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      $route = \Drupal::routeMatch()->getRouteObject();
      $name = $data->getFieldDefinition()->getName();
      return $name == 'purchased_entity' && $route->hasRequirement('_cart_api');
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
