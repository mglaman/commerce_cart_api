<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\serialization\Normalizer\FieldItemNormalizer;

/**
 * Adds a `formatted` value for price fields.
 */
class PriceItemNormalizer extends FieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = PriceItem::class;

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
  public function normalize($field_item, $format = NULL, array $context = []) {
    /** @var \Drupal\commerce_price\Plugin\Field\FieldType\PriceItem $field_item */
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
