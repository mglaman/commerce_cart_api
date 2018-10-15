<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

/**
 * Expands the purchased entity to their referenced entity.
 */
class PurchasedEntityNormalizer extends EntityReferenceFieldItemNormalizer {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a PurchasedEntityNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, RouteMatchInterface $route_match) {
    parent::__construct($entity_repository);

    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      $route = $this->routeMatch->getRouteObject();
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $data */
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
