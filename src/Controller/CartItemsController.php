<?php

namespace Drupal\commerce_cart_api\Controller;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller to provide a collection of carts for current session.
 */
class CartItemsController implements ContainerInjectionInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  protected $cartManager;

  /**
   * CartCollection constructor.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *
   */
  public function __construct(CartProviderInterface $cart_provider, CartManagerInterface $cart_manager) {
    $this->cartProvider = $cart_provider;
    $this->cartManager = $cart_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager')
    );
  }

  /**
   * DELETE an order item from a cart.
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
    $carts = $this->cartProvider->getCartIds();
    if (!in_array($commerce_order->id(), $carts)) {
      throw new AccessDeniedHttpException();
    }
    if (!$commerce_order->hasItem($commerce_order_item)) {
      throw new AccessDeniedHttpException();
    }

    $commerce_order->_cart_api = TRUE;
    $this->cartManager->removeOrderItem($commerce_order, $commerce_order_item);

    // DELETE responses have an empty body.
    // @todo wanted to return the order. But REST reponse subscriber freaks out.
    return new ModifiedResourceResponse(NULL, 204);
  }

}
