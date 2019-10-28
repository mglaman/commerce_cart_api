<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\jsonapi\ResourceResponse;

final class CartClearResource extends CartResourceBase {

  /**
   * Clear a cart's items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The cart.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function process(OrderInterface $commerce_order): ResourceResponse {
    $this->cartManager->emptyCart($commerce_order);
    return new ResourceResponse(NULL, 204);
  }

}
