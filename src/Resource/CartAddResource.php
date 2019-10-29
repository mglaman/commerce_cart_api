<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart_api\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderItemStorageInterface;
use Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface;
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
   * @var \Drupal\commerce_cart_api\EntityResourceShim
   */
  private $inner;

  /**
   * The chain price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  private $chainPriceResolver;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  private $currentStore;

  /**
   * The chain order type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface
   */
  private $chainOrderTypeResolver;

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  private $entityRepository;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer|object|null
   */
  private $renderer;

  /**
   * Constructs a new CartAddResource object.
   *
   * @param \Drupal\jsonapi_resources\ResourceResponseFactory $resource_response_factory
   *   The resource response factory.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\Access\EntityAccessChecker $entity_access_checker
   *   The entity access checker.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart_api\EntityResourceShim $jsonapi_controller
   *   The JSON:API controller shim.
   * @param \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface $chain_order_type_resolver
   *   The chain order type resolver.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\commerce_price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   The chain price resolver.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(ResourceResponseFactory $resource_response_factory, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManagerInterface $entity_type_manager, EntityAccessChecker $entity_access_checker, CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, EntityResourceShim $jsonapi_controller, ChainOrderTypeResolverInterface $chain_order_type_resolver, CurrentStoreInterface $current_store, ChainPriceResolverInterface $chain_price_resolver, EntityRepositoryInterface $entity_repository, AccountInterface $account, RendererInterface $renderer) {
    parent::__construct($resource_response_factory, $resource_type_repository, $entity_type_manager, $entity_access_checker, $cart_provider, $cart_manager);
    $this->inner = $jsonapi_controller;
    $this->chainOrderTypeResolver = $chain_order_type_resolver;
    $this->currentStore = $current_store;
    $this->chainPriceResolver = $chain_price_resolver;
    $this->entityRepository = $entity_repository;
    $this->currentUser = $account;
    $this->renderer = $renderer;
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
      $container->get('commerce_cart_api.jsonapi_controller_shim'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('commerce_store.current_store'),
      $container->get('commerce_price.chain_price_resolver'),
      $container->get('entity.repository'),
      $container->get('current_user'),
      $container->get('renderer')
    );
  }

  /**
   * Process the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param array $_purchasable_entity_resource_types
   *   The purchasable entity resource types.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
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
      $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
      assert($order_item_storage instanceof OrderItemStorageInterface);
      foreach ($resource_identifiers as $resource_identifier) {
        $meta = $resource_identifier->getMeta();
        $purchased_entity = $this->getPurchasableEntityFromResourceIdentifier($resource_identifier);
        $store = $this->selectStore($purchased_entity);
        $order_item = $order_item_storage->createFromPurchasableEntity($purchased_entity, ['quantity' => $meta['quantity'] ?? 1]);
        $cart = $this->getCartForOrderItem($order_item, $store);
        $order_item = $this->cartManager->addOrderItem($cart, $order_item, $meta['combine'] ?? TRUE);
        // Reload the order item as the cart has refreshed.
        $order_item = $order_item_storage->load($order_item->id());
        $order_items[] = ResourceObject::createFromEntity($this->resourceTypeRepository->get($order_item->getEntityTypeId(), $order_item->bundle()), $order_item);
      }
      return $order_items;
    });

    $primary_data = new ResourceObjectData($order_items);
    return $this->createJsonapiResponse($primary_data, $request);
  }

  /**
   * Get the purchasable entity from a resource identifier.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface $resource_identifier
   *   The resource identifier.
   *
   * @return \Drupal\commerce\PurchasableEntityInterface
   *   The purchasable entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function getPurchasableEntityFromResourceIdentifier(ResourceIdentifierInterface $resource_identifier) {
    $purchased_entity = $this->entityRepository->loadEntityByUuid(
      $resource_identifier->getResourceType()->getEntityTypeId(),
      $resource_identifier->getId()
    );
    if (!$purchased_entity || !$purchased_entity instanceof PurchasableEntityInterface) {
      throw new UnprocessableEntityHttpException(sprintf('The purchasable entity %s does not exist.', $resource_identifier->getId()));
    }
    $purchased_entity = $this->entityRepository->getTranslationFromContext($purchased_entity, NULL, ['operation' => 'entity_upcast']);
    assert($purchased_entity instanceof PurchasableEntityInterface);
    return $purchased_entity;
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
