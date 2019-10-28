<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart_api\Controller\jsonapi\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderItemStorageInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRelationship;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_resources\ResourceResponseFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CartAddResource extends CartResourceBase {

  /**
   * The JSON:API controller.
   *
   * @var \Drupal\commerce_cart_api\Controller\jsonapi\EntityResourceShim
   */
  protected $inner;

  private $chainPriceResolver;

  private $currentStore;

  private $chainOrderTypeResolver;

  private $entityRepository;

  private $currentUser;

  /**
   * @var \Drupal\Core\Render\Renderer|object|null
   */
  private $renderer;

  public function __construct(ResourceResponseFactory $resource_response_factory, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManagerInterface $entity_type_manager, EntityAccessChecker $entity_access_checker, CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, EntityResourceShim $jsonapi_controller) {
    parent::__construct($resource_response_factory, $resource_type_repository, $entity_type_manager, $entity_access_checker, $cart_provider, $cart_manager);
    $this->inner = $jsonapi_controller;
    $this->chainOrderTypeResolver = \Drupal::getContainer()->get('commerce_order.chain_order_type_resolver');
    $this->currentStore = \Drupal::getContainer()->get('commerce_store.current_store');
    $this->chainPriceResolver = \Drupal::getContainer()->get('commerce_price.chain_price_resolver');
    $this->entityRepository = \Drupal::getContainer()->get('entity.repository');
    $this->currentUser = \Drupal::currentUser();
    $this->renderer = \Drupal::getContainer()->get('renderer');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi_resources.resource_response_factory'),
      $container->get('jsonapi.resource_type.repository'),
      $container->get('entity_type.manager'),
      $container->get('jsonapi_resources.entity_access_checker'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart_api.jsonapi_controller_shim')
    );
  }

  public function process(Request $request, array $_purchasable_entity_resource_types = []): ResourceResponse {
    // @todo `default` may not exist. Order items are not a based field, yet.
    // @todo once `items` is a base field, change to "virtual".
    // @see https://www.drupal.org/project/commerce/issues/3002939
    $resource_type = new ResourceType('commerce_order', 'default', EntityInterface::class, FALSE, TRUE, FALSE, FALSE,
      [
        'order_items' => new ResourceTypeRelationship('order_items', 'order_items', TRUE, FALSE),
      ]
    );

    assert($resource_type->getInternalName('order_items') === 'order_items');

    /* @var \Drupal\jsonapi\ResourceType\ResourceType[] $purchasable_resource_types */
    $purchasable_resource_types = array_map(function ($resource_type_name) {
      return $this->resourceTypeRepository->getByTypeName($resource_type_name);
    }, $_purchasable_entity_resource_types);

    $resource_type->setRelatableResourceTypes(['order_items' => $purchasable_resource_types]);
    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'order_items');

    $context = new RenderContext();
    $order_items = $this->renderer->executeInRenderContext($context, function () use ($resource_identifiers) {
      $order_items = [];
      foreach ($resource_identifiers as $resource_identifier) {
        $purchased_entity = $this->entityRepository->loadEntityByUuid(
          $resource_identifier->getResourceType()->getEntityTypeId(),
          $resource_identifier->getId()
        );
        if (!$purchased_entity || !$purchased_entity instanceof PurchasableEntityInterface) {
          throw new UnprocessableEntityHttpException(sprintf('The purchasable entity %s does not exist.', $resource_identifier->getId()));
        }
        $purchased_entity = $this->entityRepository->getTranslationFromContext($purchased_entity, NULL, ['operation' => 'entity_upcast']);
        assert($purchased_entity instanceof PurchasableEntityInterface);
        $store = $this->selectStore($purchased_entity);
        $quantity = ($meta = ($resource_identifier->getMeta() && isset($meta['orderQuantity']))) ? $meta['orderQuantity'] : 1;
        $order_item = $this->createOrderItemFromPurchasableEntity($store, $purchased_entity, $quantity);

        $cart = $this->getCartForOrderItem($order_item, $store);

        $order_item = $this->cartManager->addOrderItem($cart, $order_item);
        $order_items[] = ResourceObject::createFromEntity($this->resourceTypeRepository->get($order_item->getEntityTypeId(), $order_item->bundle()), $order_item);
      }
      return $order_items;
    });

    $primary_data = new ResourceObjectData($order_items);
    return $this->createJsonapiResponse($primary_data, $request);
  }

  /**
   * Creates an order item from the purchased entity.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store.
   * @param \Drupal\commerce\PurchasableEntityInterface $purchased_entity
   *   The purchased entity.
   * @param int $quantity
   *   THe quantity.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface
   *   The order item.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function createOrderItemFromPurchasableEntity(StoreInterface $store, PurchasableEntityInterface $purchased_entity, $quantity): OrderItemInterface {
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
    assert($order_item_storage instanceof OrderItemStorageInterface);
    $order_item = $order_item_storage->createFromPurchasableEntity($purchased_entity, ['quantity' => $quantity]);
    $context = new Context($this->currentUser, $store);
    $order_item->setUnitPrice($this->chainPriceResolver->resolve($purchased_entity, $order_item->getQuantity(), $context));
    return $order_item;
  }

  /**
   * Gets the proper cart for a order item in the user's session.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The cart.
   */
  private function getCartForOrderItem(OrderItemInterface $order_item, StoreInterface $store): OrderInterface {
    $order_type_id = $this->chainOrderTypeResolver->resolve($order_item);
    $cart = $this->cartProvider->getCart($order_type_id, $store);
    if (!$cart) {
      $cart = $this->cartProvider->createCart($order_type_id, $store);
    }
    return $cart;
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
  private function selectStore(PurchasableEntityInterface $entity): StoreInterface {
    $stores = $entity->getStores();
    if (count($stores) === 1) {
      $store = reset($stores);
    }
    elseif (count($stores) === 0) {
      // Malformed entity.
      throw new UnprocessableEntityHttpException('The given entity is not assigned to any store.');
    }
    else {
      $store = $this->currentStore->getStore();
      if (!in_array($store, $stores, TRUE)) {
        // Indicates that the site listings are not filtered properly.
        throw new UnprocessableEntityHttpException("The given entity can't be purchased from the current store.");
      }
    }

    return $store;
  }

}
