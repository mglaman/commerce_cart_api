<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\commerce_order\Entity\Order;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\Plugin\rest\resource\EntityResource;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "cart_resource",
 *   entity_type = "commerce_order",
 *   serialization_class = "\Drupal\commerce_order\Entity\Order",
 *   label = @Translation("Cart resource"),
 *   uri_paths = {
 *     "create" = "/carts/{commerce_order}/items",
 *     "canonical" = "/carts/{commerce_order}/items"
 *   }
 * )
 */
class CartResource extends ResourceBase {

  public function get($order_id, $unserialized, Request $request) {
    $session = \Drupal::getContainer()->get('commerce_cart.cart_session');

    if (!$session->hasCartId($order_id)) {
      return (new ResourceResponse([], 404))
        ->addCacheableDependency(
          (new CacheableMetadata())->setCacheMaxAge(0)
        );
    }

    $cart = Order::load($order_id);
    $cart->_cart_api = TRUE;
    $response = new ResourceResponse($cart);
    $response->addCacheableDependency($cart);
    return $response;
  }

  /**
   * Adds order items to a cart.
   */
  public function post() {

  }

  /**
   * Updates order items in a cart.
   */
  public function patch() {

  }

  /**
   * Delete order items in a cart.
   */
  public function delete() {

  }

}
