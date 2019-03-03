<?php

namespace Drupal\commerce_cart_api\Normalizer;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\serialization\Normalizer\FieldItemNormalizer as CoreFieldItemNormalizer;

/**
 * Field item normalizer which flattens output.
 */
class FieldItemNormalizer extends CoreFieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = FieldItemInterface::class;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new FieldItemNormalizer object.
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
    $data = parent::normalize($field_item, $format, $context);
    // This will always be true, but here for type hinting for IDE.
    if (!$field_item instanceof FieldItemInterface) {
      return $data;
    }
    $main_property = $field_item::mainPropertyName();
    if (count($data) == 1) {
      return reset($data);
    }
    elseif (isset($data[$main_property])) {
      return $data[$main_property];
    }
    return $data;
  }

}
