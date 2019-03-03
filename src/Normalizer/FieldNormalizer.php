<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\serialization\Normalizer\FieldNormalizer as CoreFieldNormalizer;

/**
 * Field normalizer which flattens output.
 */
class FieldNormalizer extends CoreFieldNormalizer {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new FieldNormalizer object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
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
    /** @var \Drupal\Core\Field\FieldItemListInterface $field_item */
    $cardinality = $field_item->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
    $data = parent::normalize($field_item, $format, $context);
    if ($cardinality > 1 || $cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return $data;
    }
    if (empty($data)) {
      return NULL;
    }
    return reset($data);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!is_array($data)) {
      $data = [$data];
    }
    return parent::denormalize($data, $class, $format, $context);
  }

}
