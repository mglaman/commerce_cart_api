<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
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
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * Constructs a PriceItemNormalizer object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   */
  public function __construct(RouteMatchInterface $route_match, RendererInterface $renderer, RounderInterface $rounder) {
    $this->routeMatch = $route_match;
    $this->renderer = $renderer;
    $this->rounder = $rounder;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    $supported = parent::supportsNormalization($data, $format);
    if ($supported) {
      $route = $this->routeMatch->getRouteObject();
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
      $rounded_value = $this->rounder->round($raw_value);
      $formatted_price = [
        '#type' => 'inline_template',
        '#template' => '{{ price|commerce_price_format }}',
        '#context' => [
          'price' => $rounded_value,
        ],
      ];
      $attributes['formatted'] = $this->renderer->renderPlain($formatted_price);
    }

    return $attributes;
  }

}
