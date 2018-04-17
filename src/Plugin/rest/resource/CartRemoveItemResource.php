<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\rest\ModifiedResourceResponse;

/**
 * Provides a cart collection resource for current session.
 *
 * @RestResource(
 *   id = "commerce_cart_remove_item",
 *   label = @Translation("Cart remove item"),
 *   uri_paths = {
 *     "canonical" = "/cart/{commerce_order}/items/{commerce_order_item}"
 *   }
 * )
 */
class CartRemoveItemResource extends CartResourceBase {

  /**
   * DELETE an order item from a cart.
   *
   * The ResourceResponseSubscriber provided by rest.module gets weird when
   * going through the serialization process. The method is not cacheable and
   * it does not have a body format, causing it to be considered invalid.
   *
   * @todo Investigate if we can return updated order as response.
   *
   * @see \Drupal\rest\EventSubscriber\ResourceResponseSubscriber::getResponseFormat
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $commerce_order_item
   *   The order item.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   */
  public function delete(OrderInterface $commerce_order, OrderItemInterface $commerce_order_item) {
    $this->cartManager->removeOrderItem($commerce_order, $commerce_order_item);
    return new ModifiedResourceResponse(NULL, 204);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method) {
    $route = parent::getBaseRoute($canonical_path, $method);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $parameters['commerce_order_item']['type'] = 'entity:commerce_order_item';
    $route->setOption('parameters', $parameters);

    return $route;
  }

}
