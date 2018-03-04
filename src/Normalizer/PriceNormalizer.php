<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\serialization\Normalizer\FieldItemNormalizer;

/**
 * Ensures normalized prices are rounded.
 *
 * @todo is this needed? JavaScripts locale format may be fine.
 * @todo should it return raw number, currency, then formatted?
 */
class PriceNormalizer extends FieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = PriceItem::class;

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
   *
   * @param \Drupal\commerce_price\Plugin\Field\FieldType\PriceItem $field_item
   *   The price field item.
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $attributes = [];

    /** @var \Drupal\Core\TypedData\TypedDataInterface $property */
    foreach ($field_item as $name => $property) {
      $attributes[$name] = $this->serializer->normalize($property, $format, $context);
    }
    if (!$field_item->isEmpty()) {
      $raw_value = $field_item->toPrice();
      $rounded_value = \Drupal::getContainer()->get('commerce_price.rounder')->round($raw_value);
      $formatted_price = [
        '#type' => 'inline_template',
        '#template' => '{{ price|commerce_price_format }}',
        '#context' => [
          'price' => $rounded_value,
        ],
      ];
      $attributes['formatted'] = \Drupal::getContainer()->get('renderer')->renderPlain($formatted_price);
    }

    return $attributes;
  }

}
