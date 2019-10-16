<?php

namespace Drupal\commerce_cart_api\Controller;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_cart_api\Controller\jsonapi\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderItemStorageInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\jsonapi_resources\ResourceResponseFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\commerce_cart\CartManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class CartResourceController.
 */
class CartResourceController extends EntityResourceBase  {

  /**
   * Drupal\commerce_cart\CartProvider definition.
   *
   * @var \Drupal\commerce_cart\CartProvider
   */
  protected $cartProvider;

  /**
   * Drupal\commerce_cart\CartManager definition.
   *
   * @var \Drupal\commerce_cart\CartManager
   */
  protected $cartManager;

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
   * Constructs a new CartResourceController object.
   *
   * @param \Drupal\jsonapi_resources\ResourceResponseFactory $resource_response_factory
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   * @param \Drupal\commerce_cart\CartProviderInterface $commerce_cart_cart_provider
   * @param \Drupal\commerce_cart\CartManager $commerce_cart_cart_manager
   * @param \Drupal\commerce_cart_api\Controller\jsonapi\EntityResourceShim $jsonapi_controller
   */
  public function __construct(ResourceResponseFactory $resource_response_factory, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManager $entity_type_manager, CartProviderInterface $commerce_cart_cart_provider, CartManager $commerce_cart_cart_manager, EntityResourceShim $jsonapi_controller) {
    parent::__construct($resource_response_factory, $resource_type_repository, $entity_type_manager);
    $this->cartProvider = $commerce_cart_cart_provider;
    $this->cartManager = $commerce_cart_cart_manager;
    $this->inner = $jsonapi_controller;

    $this->chainOrderTypeResolver = \Drupal::getContainer()->get('commerce_order.chain_order_type_resolver');
    $this->currentStore = \Drupal::getContainer()->get('commerce_store.current_store');
    $this->chainPriceResolver = \Drupal::getContainer()->get('commerce_price.chain_price_resolver');
    $this->entityRepository = \Drupal::getContainer()->get('entity.repository');
    $this->currentUser = \Drupal::currentUser();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi_resource.resource_response_factory'),
      $container->get('jsonapi.resource_type.repository'),
      $container->get('entity_type.manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart_api.jsonapi_controller_shim')
    );
  }

  /**
   * Get a carts collection for the current user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCarts(Request $request): ResourceResponse {
    $carts = $this->cartProvider->getCarts();
    $primary_data = new ResourceObjectData(array_map(function (OrderInterface $cart) {
      return $this->getResourceObjectForEntity($cart);
    }, $carts));
    $response = $this->resourceResponseFactory->create($primary_data, $request);
    $response->getCacheableMetadata()->addCacheContexts([
      'store',
      'cart',
    ]);
    return $response;
  }

  /**
   * Get a single cart.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCart(Request $request, OrderInterface $commerce_order): ResourceResponse {
    $this->fixInclude($request);
    $resource_object = $this->getResourceObjectForEntity($commerce_order);
    $primary_data = new ResourceObjectData([$resource_object], 1);
    return $this->resourceResponseFactory->create($primary_data, $request);
  }

  /**
   * Clear a cart's items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The cart.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function clearItems(OrderInterface $cart): ResourceResponse {
    $this->cartManager->emptyCart($cart);
    return new ResourceResponse(NULL, 204);
  }

  public function addItems(Request $request): ResourceResponse {
    // @todo `default` may not exist. Order items are not a based field, yet.
    // @todo once `items` is a base field, change to "virtual".
    $resource_type = new ResourceType('commerce_order', 'default', EntityInterface::class);
    // @todo: ensure that this is *actually* only purchasable entity types.
    /* @var \Drupal\jsonapi\ResourceType\ResourceType[] $purchasable_resource_types */
    $purchasable_resource_types = $this->resourceTypeRepository->all();
    $resource_type->setRelatableResourceTypes(['order_items' => $purchasable_resource_types]);
    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'order_items');

    $renderer = \Drupal::getContainer()->get('renderer');
    $context = new RenderContext();
    $order_items = $renderer->executeInRenderContext($context, function () use ($resource_identifiers) {
      $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
      assert($order_item_storage instanceof OrderItemStorageInterface);
      $order_items = [];
      foreach ($resource_identifiers as $resource_identifier) {
        $purchased_entity = $this->entityRepository->loadEntityByUuid(
          $resource_identifier->getResourceType()->getEntityTypeId(),
          $resource_identifier->getId()
        );
        if (!$purchased_entity || !$purchased_entity instanceof PurchasableEntityInterface) {
          continue;
        }
        $store = $this->selectStore($purchased_entity);
        $quantity = ($meta = ($resource_identifier->getMeta() && isset($meta['orderQuantity']))) ? $meta['orderQuantity'] : 1;
        $order_item = $order_item_storage->createFromPurchasableEntity($purchased_entity, ['quantity' => $quantity]);
        $context = new Context($this->currentUser, $store);
        $order_item->setUnitPrice($this->chainPriceResolver->resolve($purchased_entity, $order_item->getQuantity(), $context));

        $order_type_id = $this->chainOrderTypeResolver->resolve($order_item);
        $cart = $this->cartProvider->getCart($order_type_id, $store);
        if (!$cart) {
          $cart = $this->cartProvider->createCart($order_type_id, $store);
        }

        $order_item = $this->cartManager->addOrderItem($cart, $order_item);
        $order_items[] = $this->getResourceObjectForEntity($order_item);
      }
      return $order_items;
    });

    $primary_data = new ResourceObjectData($order_items);
    return $this->resourceResponseFactory->create($primary_data, $request);
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

  /**
   * Fixes the includes parameter to ensure order_item.
   *
   * @todo remove, allow people to include if they want.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function fixInclude(Request $request) {
    $include = $request->query->get('include');
    $request->query->set('include', $include . (empty($include) ? '' : ',') . 'order_items,order_items.purchased_entity');
  }

  /**
   * Get a resource object for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceObject
   *   The resource object.
   */
  private function getResourceObjectForEntity(EntityInterface $entity) {
    $resource_type = $this->resourceTypeRepository->get($entity->getEntityTypeId(), $entity->bundle());
    return ResourceObject::createFromEntity($resource_type, $entity);
  }

}
