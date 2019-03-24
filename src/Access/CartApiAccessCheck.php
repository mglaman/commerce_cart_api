<?php

namespace Drupal\commerce_cart_api\Access;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Access check for the cart rest plugins.
 *
 * Uses the cart provider to check if a cart belongs to the current session,
 * and also verifies order items belong to a valid cart.
 */
class CartApiAccessCheck implements AccessInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * Constructs a new CartApiAccessCheck object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   */
  public function __construct(CartProviderInterface $cart_provider) {
    $this->cartProvider = $cart_provider;
  }

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    // If the route has no parameters (cart collection), allow.
    $parameters = $route->getOption('parameters');
    $order_parameter_name = 'commerce_order';

    if (empty($parameters[$order_parameter_name])) {
      return AccessResult::allowed();
    }

    // If there is no cart, no access.
    $order = $route_match->getParameter($order_parameter_name);
    if (!$order || !$order instanceof OrderInterface) {
      return AccessResult::forbidden('Order is not converted');
    }

    // Carts must be a draft and flagged as a cart.
    if (empty($order->cart->value) || $order->getState()->value !== 'draft') {
      return AccessResult::forbidden('Order is not a draft or cart')->addCacheableDependency($order);
    }

    // Ensure cart belongs to the current user.
    $carts = $this->cartProvider->getCartIds($account);
    if (!in_array((int) $order->id(), $carts, TRUE)) {
      return AccessResult::forbidden('Order does not belong to the current user.')->addCacheableDependency($order);
    }

    // If there is also an order item in the route, make sure it belongs
    // to this cart as well.
    $order_item = $route_match->getParameter('commerce_order_item');
    if ($order_item && $order_item instanceof OrderItemInterface) {
      if (!$order->hasItem($order_item)) {
        return AccessResult::forbidden()
          ->addCacheableDependency($order_item)
          ->addCacheableDependency($order);
      }
    }

    return AccessResult::allowed()->addCacheableDependency($order);
  }

}
