<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\rest\ResourceResponse;

/**
 * Provides a cart collection resource for current session.
 *
 * @RestResource(
 *   id = "commerce_cart_canonical",
 *   label = @Translation("Cart canonical"),
 *   uri_paths = {
 *     "canonical" = "/cart/{commerce_order}"
 *   }
 * )
 */
class CartCanonicalResource extends CartResourceBase {

  /**
   * GET a collection of the current user's carts.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get(OrderInterface $commerce_order) {
    $response = new ResourceResponse($commerce_order);
    $response->addCacheableDependency($commerce_order);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method) {
    $route = parent::getBaseRoute($canonical_path, $method);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $route->setOption('parameters', $parameters);

    return $route;
  }

}
