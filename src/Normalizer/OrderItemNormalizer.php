<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\serialization\Normalizer\EntityNormalizer;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class OrderItemNormalizer extends EntityNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = [OrderItemInterface::class];

  /**
   * Allowed fields to be returned
   *
   * @todo Allow altering?
   *
   * @var array
   */
  protected $allowedFields = [
    'order_item_id',
    'uuid',
    // Should we send the type?
    // 'type',
    'purchased_entity',
    'title',
    // Allow after https://www.drupal.org/project/commerce/issues/2916252.
    // 'adjustments',
    'quantity',
    'unit_price',
    'total_price',
  ];

  /**
   * @inheritDoc
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      if (!is_array($data)) {
        $data = [$data];
      }
      $supported = (bool) array_filter($data, function (OrderItemInterface $order_item) {
        return !empty($order_item->_cart_api);
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
