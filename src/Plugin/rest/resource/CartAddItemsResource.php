<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creates order items for the session's carts.
 *
 * @todo Currently hardcoded to support product variations only.
 *
 * @RestResource(
 *   id = "commerce_cart_add_items",
 *   label = @Translation("Cart add items"),
 *   uri_paths = {
 *     "create" = "/cart/items/add"
 *   }
 * )
 */
class CartAddItemsResource extends CartResourceBase {

  /**
   * The order item store.
   *
   * @var \Drupal\commerce_order\OrderItemStorageInterface
   */
  protected $orderItemStorage;

  /**
   * The chain order type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface
   */
  protected $chainOrderTypeResolver;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * Constructs a new CartAddItemsResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface $chain_order_type_resolver
   *   The chain order type resolver.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, EntityTypeManagerInterface $entity_type_manager, ChainOrderTypeResolverInterface $chain_order_type_resolver, CurrentStoreInterface $current_store) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $cart_provider, $cart_manager);
    $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
    $this->chainOrderTypeResolver = $chain_order_type_resolver;
    $this->currentStore = $current_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('entity_type.manager'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('commerce_store.current_store')
    );
  }

  /**
   * Add order items to the session's carts.
   *
   * @param array $body
   *   The unserialized request body.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The resource response.
   *
   * @throws \Exception
   */
  public function post(array $body, Request $request) {
    $carts = [];
    foreach ($body as $order_item_data) {
      // @todo How could we make this easier to support all purchasable entities
      // Perhaps generate routes for each PurchasableEntityInterface
      // Set the entity type as a route option, get storage. Profit.
      $purchased_entity = ProductVariation::load($order_item_data['purchased_entity']);
      if (!$purchased_entity) {
        continue;
      }
      $order_item = $this->orderItemStorage->createFromPurchasableEntity($purchased_entity, [
        'quantity' => (!empty($order_item_data['quantity'])) ? $order_item_data['quantity'] : 1,
      ]);

      $order_type_id = $this->chainOrderTypeResolver->resolve($order_item);
      $store = $this->selectStore($purchased_entity);
      $cart = $this->cartProvider->getCart($order_type_id, $store);
      if (!$cart) {
        $cart = $this->cartProvider->createCart($order_type_id, $store);
      }
      if (!isset($carts[$cart->id()])) {
        $cart->_cart_api = TRUE;
        $carts[$cart->id()] = $cart;
      }
      $this->cartManager->addOrderItem($cart, $order_item, TRUE);
    }

    $response = new ModifiedResourceResponse(array_values($carts), 200);
    return $response;
  }

  /**
   * Selects the store for the given purchasable entity.
   *
   * If the entity is sold from one store, then that store is selected.
   * If the entity is sold from multiple stores, and the current store is
   * one of them, then that store is selected.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The entity being added to cart.
   *
   * @throws \Exception
   *   When the entity can't be purchased from the current store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The selected store.
   */
  protected function selectStore(PurchasableEntityInterface $entity) {
    $stores = $entity->getStores();
    if (count($stores) === 1) {
      $store = reset($stores);
    }
    elseif (count($stores) === 0) {
      // Malformed entity.
      throw new \Exception('The given entity is not assigned to any store.');
    }
    else {
      $store = $this->currentStore->getStore();
      if (!in_array($store, $stores)) {
        // Indicates that the site listings are not filtered properly.
        throw new \Exception("The given entity can't be purchased from the current store.");
      }
    }

    return $store;
  }

}
