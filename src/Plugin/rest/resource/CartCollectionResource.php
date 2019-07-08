<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\Core\Render\RenderContext;
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
    $context = new RenderContext();
    $renderer = \Drupal::service('renderer');
    $carts = $renderer->executeInRenderContext($context, function () {
      return $this->cartProvider->getCarts();
    });

    $response = new ResourceResponse(array_values($carts), 200);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }
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
