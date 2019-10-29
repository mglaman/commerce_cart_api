<?php

namespace Drupal\commerce_cart_api\Routing;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart_api\Resource\CartAddResource;
use Drupal\commerce_cart_api\Resource\CartCanonicalResource;
use Drupal\commerce_cart_api\Resource\CartClearResource;
use Drupal\commerce_cart_api\Resource\CartCollectionResource;
use Drupal\commerce_cart_api\Resource\CartCouponAddResource;
use Drupal\commerce_cart_api\Resource\CartRemoveItemResource;
use Drupal\commerce_cart_api\Resource\CartUpdateItemResource;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Routing\Routes as JsonapiRoutes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Routes implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * List of providers.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * The JSON:API base path.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param string[] $authentication_providers
   *   The authentication providers, keyed by ID.
   * @param string $jsonapi_base_path
   *   The JSON:API base path.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepositoryInterface $resource_type_repository, array $authentication_providers, $jsonapi_base_path) {
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->providerIds = array_keys($authentication_providers);
    $this->jsonApiBasePath = $jsonapi_base_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('jsonapi.resource_type.repository'),
      $container->getParameter('authentication_providers'),
      $container->getParameter('jsonapi.base_path')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();

    $routes->add('commerce_cart_api.jsonapi.cart_collection', $this->cartsCollection());
    $routes->add('commerce_cart_api.jsonapi.cart_canonical', $this->cartsCanonical());
    $routes->add('commerce_cart_api.jsonapi.cart_clear', $this->cartClear());
    $routes->add('commerce_cart_api.jsonapi.cart_add', $this->cartAdd());
    $routes->add('commerce_cart_api.jsonapi.cart_remove_item', $this->cartRemoveItem());
    $routes->add('commerce_cart_api.jsonapi.cart_update_item', $this->cartUpdateItem());
    $routes->add('commerce_cart_api.jsonapi.cart_coupon_add', $this->cartCouponAdd());

    // All routes must pass _cart_api access check.
    $routes->addRequirements([
      '_access' => 'TRUE',
      '_cart_api' => 'TRUE',
    ]);

    // Set a fake resource type so entity UUID parameter conversion works.
    $routes->addDefaults([JsonapiRoutes::RESOURCE_TYPE_KEY => 'commerce_order--commerce_order']);

    return $routes;
  }

  /**
   * The cart collection resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartsCollection() {
    $route = new Route('/cart');
    $route->addDefaults(['_jsonapi_resource' => CartCollectionResource::class]);
    return $route;
  }

  /**
   * The cart canonical resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartsCanonical() {
    $route = new Route('/cart/{commerce_order}');
    $route->addDefaults(['_jsonapi_resource' => CartCanonicalResource::class]);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $route->setOption('parameters', $parameters);
    return $route;
  }

  /**
   * The cart clear resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartClear() {
    $route = new Route('/cart/{commerce_order}');
    $route->addDefaults(['_jsonapi_resource' => CartClearResource::class]);
    $route->setMethods(['DELETE']);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $route->setOption('parameters', $parameters);
    return $route;
  }

  /**
   * The cart add resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartAdd() {
    $purchasable_entity_resource_types = array_filter($this->resourceTypeRepository->all(), function (ResourceType $resource_type) {
      $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
      return $entity_type->entityClassImplements(PurchasableEntityInterface::class);
    });
    $purchasable_entity_resource_types = array_map(static function (ResourceType $resource_type) {
      return $resource_type->getTypeName();
    }, $purchasable_entity_resource_types);

    $route = new Route('/cart/add');
    $route->addDefaults([
      '_jsonapi_resource' => CartAddResource::class,
      '_purchasable_entity_resource_types' => $purchasable_entity_resource_types,
    ]);
    $route->setMethods(['POST']);

    return $route;
  }

  /**
   * The cart remove item resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartRemoveItem() {
    $order_item_resource_types = array_filter($this->resourceTypeRepository->all(), function (ResourceType $resource_type) {
      return $resource_type->getEntityTypeId() === 'commerce_order_item';
    });
    $order_item_resource_types = array_map(static function (ResourceType $resource_type) {
      return $resource_type->getTypeName();
    }, $order_item_resource_types);

    $route = new Route('/cart/{commerce_order}/items');
    $route->addDefaults([
      '_jsonapi_resource' => CartRemoveItemResource::class,
      '_order_item_resource_types' => $order_item_resource_types,
    ]);
    $route->setMethods(['DELETE']);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $route->setOption('parameters', $parameters);
    return $route;
  }

  /**
   * The cart update item resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartUpdateItem() {
    $route = new Route('/cart/{commerce_order}/items/{commerce_order_item}');
    $route->addDefaults(['_jsonapi_resource' => CartUpdateItemResource::class]);
    $route->setMethods(['PATCH']);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $parameters['commerce_order_item']['type'] = 'entity:commerce_order_item';
    $route->setOption('parameters', $parameters);
    return $route;
  }

  /**
   * The cart coupon add resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartCouponAdd() {
    $route = new Route('/cart/{commerce_order}/coupons');
    $route->setMethods(['PATCH']);
    $route->addDefaults(['_jsonapi_resource' => CartCouponAddResource::class]);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $route->setOption('parameters', $parameters);
    return $route;
  }

}
