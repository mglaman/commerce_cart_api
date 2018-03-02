<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "cart_collection_resource",
 *   entity_type = "commerce_order",
 *   label = @Translation("Cart collection resource"),
 *   uri_paths = {
 *     "canonical" = "/cart"
 *   }
 * )
 */
class CartCollectionResource extends ResourceBase {

  public function get() {
    $cart_provider = \Drupal::getContainer()->get('commerce_cart.cart_provider');
    $carts = $cart_provider->getCarts();

    $response = new ResourceResponse(array_values($carts), 200);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
    foreach ($carts as $cart) {
      $cart->_cart_api = TRUE;
      $response->addCacheableDependency($cart);
    }
    return $response;
  }

  public function post() {

  }

}
