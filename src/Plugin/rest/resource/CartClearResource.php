<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\rest\ModifiedResourceResponse;

/**
 * Clear order items and reset the cart to initial.
 *
 * @RestResource(
 *   id = "commerce_cart_clear",
 *   label = @Translation("Cart clear"),
 *   uri_paths = {
 *     "canonical" = "/cart/{commerce_order}/items"
 *   }
 * )
 */
class CartClearResource extends CartResourceBase {

  /**
   * DELETE all order item from a cart.
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
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   */
  public function delete(OrderInterface $commerce_order) {
    $this->cartManager->emptyCart($commerce_order);
    return new ModifiedResourceResponse(NULL, 204);
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
