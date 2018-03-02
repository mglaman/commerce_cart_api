<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\serialization\Normalizer\EntityNormalizer;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class CartNormalizer extends EntityNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = [OrderInterface::class];

  /**
   * Allowed fields to be returned
   *
   * @todo Allow altering?
   *
   * @var array
   */
  protected $allowedFields = [
    'order_id',
    'uuid',
    'type',
    'order_number',
    'store_id',
    // Allow after https://www.drupal.org/project/commerce/issues/2916252.
    // 'adjustments',
    'total_price',
    'order_items',
  ];

  /**
   * @inheritDoc
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      $supported = (bool) array_filter($data, function (OrderInterface $order) {
        return !empty($order->_cart_api);
      });
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
    foreach ($entity as $name => $field_items) {
      if (!in_array($name, $this->allowedFields)) {
        continue;
      }
      if ($field_items->access('view', $context['account'])) {
        $attributes[$name] = $this->serializer->normalize($field_items, $format, $context);
      }
    }

    return $attributes;
  }

}
