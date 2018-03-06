<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\serialization\Normalizer\EntityNormalizer;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 *
 * @todo Review is this is needed any more, of if core EntityNormalizer is fine.
 */
class CartNormalizer extends EntityNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = [OrderInterface::class];

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      $route = \Drupal::routeMatch()->getRouteObject();
      return $route->hasRequirement('_cart_api');
    }
    return $supported;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $context += [
      'account' => NULL,
    ];

    $attributes = [];
    /** @var \Drupal\Core\Field\FieldItemListInterface $field_items */
    foreach ($entity as $name => $field_items) {
      if ($field_items->access('view', $context['account'])) {
        $attributes[$name] = $this->serializer->normalize($field_items, $format, $context);
      }
    }

    return $attributes;
  }

}
