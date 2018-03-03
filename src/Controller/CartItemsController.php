<?php

namespace Drupal\commerce_cart_api\Controller;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\Resolver\OrderTypeResolverInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

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

  /**
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * @var \Drupal\commerce_order\OrderItemStorageInterface
   */
  protected $orderItemStorage;

  protected $chainOrderTypeResolver;

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  protected $currentStore;

  /**
   * CartCollection constructor.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_order\Resolver\OrderTypeResolverInterface $chain_order_type_resolver
   *   The chain order type resolver.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    CartProviderInterface $cart_provider,
    CartManagerInterface $cart_manager,
    EntityTypeManagerInterface $entity_type_manager,
    OrderTypeResolverInterface $chain_order_type_resolver,
    SerializerInterface $serializer,
    CurrentStoreInterface $current_store
  ) {
    $this->cartProvider = $cart_provider;
    $this->cartManager = $cart_manager;
    $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
    $this->chainOrderTypeResolver = $chain_order_type_resolver;
    $this->serializer = $serializer;
    $this->currentStore = $current_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('entity_type.manager'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('serializer'),
      $container->get('commerce_store.current_store')
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
    $commerce_order->_cart_api = TRUE;
    $this->cartManager->removeOrderItem($commerce_order, $commerce_order_item);

    // DELETE responses have an empty body.
    // @todo wanted to return the order. But REST reponse subscriber freaks out.
    return new ModifiedResourceResponse(NULL, 204);
  }

  /**
   * PATCH to update order items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function patch(OrderInterface $commerce_order, Request $request) {
    $received = $request->getContent();
    $format = $request->getContentType();
    try {
      $unserialized = $this->serializer->decode($received, $format, ['request_method' => 'patch']);
    }
    catch (UnexpectedValueException $e) {
      // If an exception was thrown at this stage, there was a problem
      // decoding the data. Throw a 400 http exception.
      throw new BadRequestHttpException($e->getMessage());
    }

    // Purge read only fields.
    // @todo We should investigate implementing field access checks on these.
    foreach ($unserialized as $delta => $unserialized_order_item) {
      unset($unserialized[$delta]['purchased_entity']);
      unset($unserialized[$delta]['title']);
      unset($unserialized[$delta]['unit_price']);
      unset($unserialized[$delta]['total_price']);
    }

    foreach ($unserialized as $unserialized_order_item) {
      try {
        // @todo this would bork if someone customized entity class.
        /** @var \Drupal\commerce_order\Entity\OrderItemInterface $updated_order_item */
        $updated_order_item = $this->serializer->denormalize($unserialized_order_item, OrderItem::class, $format, ['request_method' => 'patch']);
        $original_order_item = OrderItem::load($updated_order_item->id());

        if (!$commerce_order->hasItem($original_order_item)) {
          throw new UnprocessableEntityHttpException('Invalid order item');
        }
      }
      catch (UnexpectedValueException $e) {
        throw new UnprocessableEntityHttpException($e->getMessage());
      }
      catch (InvalidArgumentException $e) {
        throw new UnprocessableEntityHttpException($e->getMessage());
      }

      foreach ($updated_order_item->_restSubmittedFields as $field_name) {
        $field = $updated_order_item->get($field_name);
        $original_order_item->set($field_name, $field->getValue());
      }

      $original_order_item->save();
    }

    $commerce_order->_cart_api = TRUE;
    $commerce_order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
    $commerce_order->save();

    // Return the updated entity in the response body.
    return new ModifiedResourceResponse($commerce_order, 200);
  }

  /**
   * POST to add purchased entities to new or existing carts.
   *
   * Example payload:
   * [
   *   {
   *     "purchased_entity": "21",
   *     "quantity": "1"
   *   }
   * ]
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   A cart collection response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Exception
   */
  public function addItems(Request $request) {
    $received = $request->getContent();
    $format = $request->getContentType();
    try {
      $unserialized = $this->serializer->decode($received, $format, ['request_method' => 'post']);
    }
    catch (UnexpectedValueException $e) {
      // If an exception was thrown at this stage, there was a problem
      // decoding the data. Throw a 400 http exception.
      throw new BadRequestHttpException($e->getMessage());
    }

    $carts = [];
    foreach ($unserialized as $order_item_data) {
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
