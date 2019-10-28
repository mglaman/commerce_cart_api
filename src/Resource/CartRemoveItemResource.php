<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\jsonapi\ResourceResponse;

final class CartRemoveItemResource extends CartResourceBase {

  /**
   * DELETE an order item from a cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $commerce_order_item
   *   The order item.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function process(OrderInterface $commerce_order, OrderItemInterface $commerce_order_item): ResourceResponse {
    $this->cartManager->removeOrderItem($commerce_order, $commerce_order_item);
    return new ResourceResponse(NULL, 204);
  }

}
