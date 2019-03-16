<?php

namespace Drupal\commerce_cart_api\Controller;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Url;
use Drupal\jsonapi\Exception\UnprocessableHttpEntityException;
use Drupal\jsonapi\IncludeResolver;
use Drupal\jsonapi\JsonApiResource\EntityCollection;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\NullEntityCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\commerce_cart\CartManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class CartResourceController.
 */
class CartResourceController implements ContainerInjectionInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;
  /**
   * Drupal\commerce_cart\CartProvider definition.
   *
   * @var \Drupal\commerce_cart\CartProvider
   */
  protected $cartProvider;
  /**
   * Drupal\commerce_cart\CartSession definition.
   *
   * @var \Drupal\commerce_cart\CartSession
   */
  protected $cartSession;
  /**
   * Drupal\commerce_cart\CartManager definition.
   *
   * @var \Drupal\commerce_cart\CartManager
   */
  protected $cartManager;

  /**
   * The include resolver.
   *
   * @var \Drupal\jsonapi\IncludeResolver
   */
  protected $includeResolver;

  protected $resourceTypeRepository;

  /**
   * The link manager service.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The JSON:API serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $serializer;

  private $chainPriceResolver;

  private $currentStore;

  private $chainOrderTypeResolver;

  private $orderItemStorage;

  private $entityRepository;

  private $currentUser;

  /**
   * Constructs a new CartResourceController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   * @param \Drupal\commerce_cart\CartProviderInterface $commerce_cart_cart_provider
   * @param \Drupal\commerce_cart\CartSessionInterface $commerce_cart_cart_session
   * @param \Drupal\commerce_cart\CartManager $commerce_cart_cart_manager
   * @param \Drupal\jsonapi\IncludeResolver $include_resolver
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   * @param \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface $serializer
   *   The JSON:API serializer.
   */
  public function __construct(EntityTypeManager $entity_type_manager, CartProviderInterface $commerce_cart_cart_provider, CartSessionInterface $commerce_cart_cart_session, CartManager $commerce_cart_cart_manager, IncludeResolver $include_resolver, ResourceTypeRepositoryInterface $resource_type_repository, LinkManager $link_manager, SerializerInterface $serializer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->cartProvider = $commerce_cart_cart_provider;
    $this->cartSession = $commerce_cart_cart_session;
    $this->cartManager = $commerce_cart_cart_manager;
    $this->includeResolver = $include_resolver;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->linkManager = $link_manager;
    $this->serializer = $serializer;

    $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
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
      $container->get('entity_type.manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_session'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('jsonapi.include_resolver'),
      $container->get('jsonapi.resource_type.repository'),
      $container->get('jsonapi.link_manager'),
      $container->get('jsonapi.serializer')
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
   */
  public function getCarts(Request $request) {
    // @todo removing fixInclude here breaks the response...
    $this->fixInclude($request);
    $carts = $this->cartProvider->getCarts();
    $grouped_by_resource_type = array_reduce($carts, function ($grouped, OrderInterface $cart) {
      $resource_type = $this->resourceTypeRepository->get($cart->getEntityTypeId(), $cart->bundle());
      $grouped[$resource_type->getTypeName()][] = new ResourceObject($resource_type, $cart);
      return $grouped;
    }, []);

    $includes = array_reduce($grouped_by_resource_type, function ($includes, array $cart_subset) use ($request) {
      $subset = $this->getIncludes($request, new EntityCollection($cart_subset));
      return EntityCollection::merge($includes, $subset);
    }, new NullEntityCollection());

    $entity_collection = array_reduce($grouped_by_resource_type, function ($entity_collection, array $cart_subset) {
      return EntityCollection::merge($entity_collection, new EntityCollection($cart_subset));
    }, new EntityCollection([]));

    return $this->buildWrappedResponse($entity_collection, $request, $includes);
  }

  /**
   * Get a single cart.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The order.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCart(Request $request, OrderInterface $cart) {
    $this->fixInclude($request);
    $resource_type = $this->resourceTypeRepository->get($cart->getEntityTypeId(), $cart->bundle());
    $resource_object = new ResourceObject($resource_type, $cart);
    // @todo generating this causes 500.
    // $self_link = new Link(new CacheableMetadata(), Url::fromRoute('commerce_checkout.form', ['commerce_order' => $cart->id()]), ['checkout']);
    $links = new LinkCollection([]);
    $response = $this->buildWrappedResponse($resource_object, $request, $this->getIncludes($request, $resource_object), 200, [], $links);
    return $response;
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
  public function clearItems(OrderInterface $cart) {
    $this->cartManager->emptyCart($cart);
    return new ResourceResponse(NULL, 204);
  }

  public function addItems(Request $request) {
    try {
      $received = (string) $request->getContent();
      $document = $this->serializer->decode($received, 'api_json');
    }
    catch (UnexpectedValueException $e) {
      // If an exception was thrown at this stage, there was a problem decoding
      // the data. Throw a 400 HTTP exception.
      throw new BadRequestHttpException($e->getMessage());
    }
    if (empty($document['data'])) {
      throw new BadRequestHttpException('Document must contain data');
    }

    // Do an initial validation of the payload before any processing.
    foreach ($document['data'] as $key => $order_item_data) {
      if (!isset($order_item_data['purchased_entity_type'])) {
        throw new UnprocessableEntityHttpException(sprintf('You must specify a purchasable entity type for row: %s', $key));
      }
      if (!isset($order_item_data['purchased_entity_id'])) {
        throw new UnprocessableEntityHttpException(sprintf('You must specify a purchasable entity ID for row: %s', $key));
      }
      if (!$this->entityTypeManager->hasDefinition($order_item_data['purchased_entity_type'])) {
        throw new UnprocessableEntityHttpException(sprintf('You must specify a valid purchasable entity type for row: %s', $key));
      }
    }

    $renderer = \Drupal::getContainer()->get('renderer');
    $context = new RenderContext();
    $order_items = $renderer->executeInRenderContext($context, function () use ($document) {
      $order_items = [];
      foreach ($document['data'] as $order_item_data) {
        $purchased_entity = $this->entityRepository->loadEntityByUuid(
          $order_item_data['purchased_entity_type'],
          $order_item_data['purchased_entity_id']
        );
        if (!$purchased_entity || !$purchased_entity instanceof PurchasableEntityInterface) {
          continue;
        }
        $store = $this->selectStore($purchased_entity);
        $order_item = $this->orderItemStorage->createFromPurchasableEntity($purchased_entity, [
          'quantity' => !empty($order_item_data['quantity']) ? $order_item_data['quantity'] : 1,
        ]);
        $context = new Context($this->currentUser, $store);
        $order_item->setUnitPrice($this->chainPriceResolver->resolve($purchased_entity, $order_item->getQuantity(), $context));

        $order_type_id = $this->chainOrderTypeResolver->resolve($order_item);
        $cart = $this->cartProvider->getCart($order_type_id, $store);
        if (!$cart) {
          $cart = $this->cartProvider->createCart($order_type_id, $store);
        }

        $order_item = $this->cartManager->addOrderItem($cart, $order_item, TRUE);
        $resource_type = $this->resourceTypeRepository->get($order_item->getEntityTypeId(), $order_item->bundle());
        $order_items[] = new ResourceObject($resource_type, $order_item);
      }
      return $order_items;
    });

    $entity_collection = new EntityCollection($order_items);

    return $this->buildWrappedResponse($entity_collection, $request, $this->getIncludes($request, $entity_collection));
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
      if (!in_array($store, $stores)) {
        // Indicates that the site listings are not filtered properly.
        throw new UnprocessableEntityHttpException("The given entity can't be purchased from the current store.");
      }
    }

    return $store;
  }

  /**
   * Builds a response with the appropriate wrapped document.
   *
   * @param mixed $data
   *   The data to wrap.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection $includes
   *   The resources to be included in the document. Use NullEntityCollection if
   *   there should be no included resources in the document.
   * @param int $response_code
   *   The response code.
   * @param array $headers
   *   An array of response headers.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $links
   *   The URLs to which to link. A 'self' link is added automatically.
   * @param array $meta
   *   (optional) The top-level metadata.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  protected function buildWrappedResponse($data, Request $request, EntityCollection $includes, $response_code = 200, array $headers = [], LinkCollection $links = NULL, array $meta = []) {
    $self_link = new Link(new CacheableMetadata(), $this->linkManager->getRequestLink($request), ['self']);
    $links = ($links ?: new LinkCollection([]));
    $links = $links->withLink('self', $self_link);
    $response = new ResourceResponse(new JsonApiDocumentTopLevel($data, $includes, $links, $meta), $response_code, $headers);
    $cacheability = (new CacheableMetadata())->addCacheContexts([
      // Make sure that different sparse fieldsets are cached differently.
      'url.query_args:fields',
      // Make sure that different sets of includes are cached differently.
      'url.query_args:include',
    ]);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Gets includes for the given response data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Entity\EntityInterface|\Drupal\jsonapi\JsonApiResource\EntityCollection $data
   *   The response data from which to resolve includes.
   *
   * @return \Drupal\jsonapi\JsonApiResource\EntityCollection
   *   An EntityCollection to be included or a NullEntityCollection if the
   *   request does not specify any include paths.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getIncludes(Request $request, $data) {
    return $request->query->has('include') && ($include_parameter = $request->query->get('include')) && !empty($include_parameter)
      ? $this->includeResolver->resolve($data, $include_parameter)
      : new NullEntityCollection();
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

}
