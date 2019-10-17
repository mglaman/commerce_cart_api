<?php

namespace Drupal\commerce_cart_api\Routing;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart_api\Controller\CartResourceController;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
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

    // All routes must pass _cart_api access check.
    $routes->addRequirements(['_cart_api' => 'TRUE']);

    return $routes;
  }

  protected function cartsCollection() {
    $collection_route = new Route('/cart');
    $collection_route->addDefaults(['_jsonapi_resource' => CartResourceController::class . ':getCarts']);
    $collection_route->setMethods(['GET']);
    $collection_route->setRequirement('_access', 'TRUE');
    return $collection_route;
  }

  protected function cartsCanonical() {
    $collection_route = new Route('/cart/{commerce_order}');
    $collection_route->addDefaults(['_jsonapi_resource' => CartResourceController::class . ':getCart']);
    $collection_route->setMethods(['GET']);
    $collection_route->setRequirement('_access', 'TRUE');
    $parameters = $collection_route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $collection_route->setOption('parameters', $parameters);
    return $collection_route;
  }

  protected function cartClear() {
    $collection_route = new Route('/cart/{commerce_order}/items');
    $collection_route->addDefaults(['_jsonapi_resource' => CartResourceController::class . ':clearItems']);
    $collection_route->setMethods(['DELETE']);
    $parameters = $collection_route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $collection_route->setOption('parameters', $parameters);
    return $collection_route;
  }

  protected function cartAdd() {
    $purchasble_entity_resource_types = array_filter($this->resourceTypeRepository->all(), function (ResourceType $resource_type) {
      $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
      return $entity_type->entityClassImplements(PurchasableEntityInterface::class);
    });
    $purchasble_entity_resource_types = array_map(static function (ResourceType $resource_type) {
      return $resource_type->getTypeName();
    }, $purchasble_entity_resource_types);

    $collection_route = new Route('/cart/add');
    $collection_route->addDefaults([
      '_jsonapi_resource' => CartResourceController::class . ':addItems',
      '_purchasable_entity_resource_types' => $purchasble_entity_resource_types,
    ]);
    $collection_route->setMethods(['POST']);

    return $collection_route;
  }

  protected function cartRemoveItem() {
    $collection_route = new Route('/cart/{commerce_order}/items/{commerce_order_item}');
    $collection_route->addDefaults(['_jsonapi_resource' => CartResourceController::class . ':removeItem']);
    $collection_route->setMethods(['DELETE']);
    $parameters = $collection_route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $parameters['commerce_order_item']['type'] = 'entity:commerce_order_item';
    $collection_route->setOption('parameters', $parameters);
    return $collection_route;
  }
  protected function cartUpdateItem() {
    $collection_route = new Route('/cart/{commerce_order}/items/{commerce_order_item}');
    $collection_route->addDefaults(['_jsonapi_resource' => CartResourceController::class . ':updateItem']);
    $collection_route->setMethods(['PATCH']);
    $parameters = $collection_route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $parameters['commerce_order_item']['type'] = 'entity:commerce_order_item';
    $collection_route->setOption('parameters', $parameters);
    return $collection_route;
  }

}
