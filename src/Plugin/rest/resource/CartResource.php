<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\Plugin\rest\resource\EntityResource;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "cart_resource",
 *   entity_type = "commerce_order",
 *   serialization_class = "\Drupal\commerce_order\Entity\Order",
 *   label = @Translation("Cart resource"),
 *   uri_paths = {
 *     "create" = "/carts/{id}/items",
 *     "canonical" = "/carts/{id}/items"
 *   }
 * )
 */
class CartResource extends ResourceBase {

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
