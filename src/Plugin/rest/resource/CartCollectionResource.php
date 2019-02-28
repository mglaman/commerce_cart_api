<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\rest\ResourceResponse;

/**
 * Provides a cart collection resource for current session.
 *
 * @RestResource(
 *   id = "commerce_cart_collection",
 *   label = @Translation("Cart collection"),
 *   uri_paths = {
 *     "canonical" = "/cart"
 *   }
 * )
 */
class CartCollectionResource extends CartResourceBase {

  /**
   * GET a collection of the current user's carts.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get() {
    $carts = $this->cartProvider->getCarts();

    $response = new ResourceResponse(array_values($carts), 200);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
    foreach ($carts as $cart) {
      $response->addCacheableDependency($cart);
    }
    $response->getCacheableMetadata()->addCacheContexts([
      'store',
      'cart',
    ]);

    return $response;
  }

}
