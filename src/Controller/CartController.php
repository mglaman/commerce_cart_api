<?php

namespace Drupal\commerce_cart_api\Controller;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller to provide a collection of carts for current session.
 */
class CartController implements ContainerInjectionInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * CartCollection constructor.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   */
  public function __construct(CartProviderInterface $cart_provider) {
    $this->cartProvider = $cart_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider')
    );
  }

  /**
   * GET a collection of the current user's carts.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function collection() {
    $carts = $this->cartProvider->getCarts();

    $response = new ResourceResponse(array_values($carts), 200);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
    foreach ($carts as $cart) {
      $cart->_cart_api = TRUE;
      $response->addCacheableDependency($cart);
    }
    return $response;
  }

  /**
   * GET a single cart.
   *
   * If the cart is not part of the user's session, then an empty response
   * is returned. Otherwise the cart is returned.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The cart.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get(OrderInterface $commerce_order) {
    $carts = $this->cartProvider->getCartIds();
    if (!in_array($commerce_order->id(), $carts)) {
      return (new ResourceResponse([], 404))
        ->addCacheableDependency(
          (new CacheableMetadata())->setCacheMaxAge(0)
        );
    }

    $commerce_order->_cart_api = TRUE;
    $response = new ResourceResponse($commerce_order);
    $response->addCacheableDependency($commerce_order);
    return $response;
  }

}
