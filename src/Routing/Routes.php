<?php

namespace Drupal\commerce_cart_api\Routing;

use Drupal\commerce_cart_api\Controller\CartResourceController;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Routing\Routes as JsonapiRoutes;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Routes implements ContainerInjectionInterface {
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
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param string[] $authentication_providers
   *   The authentication providers, keyed by ID.
   * @param string $jsonapi_base_path
   *   The JSON:API base path.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, array $authentication_providers, $jsonapi_base_path) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->providerIds = array_keys($authentication_providers);
    assert(is_string($jsonapi_base_path));
    assert(
      strpos($jsonapi_base_path, '/') === 0,
      sprintf('The provided base path should contain a leading slash "/". Given: "%s".', $jsonapi_base_path)
    );
    assert(
      substr($jsonapi_base_path, -1) !== '/',
      sprintf('The provided base path should not contain a trailing slash "/". Given: "%s".', $jsonapi_base_path)
    );
    $this->jsonApiBasePath = $jsonapi_base_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
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

    // Ensure JSON API prefix.
    $routes->addPrefix($this->jsonApiBasePath);

    // All routes must pass _cart_api access check.
    $routes->addRequirements(['_cart_api' => 'TRUE']);

    // Require the JSON:API media type header on every route, except on file
    // upload routes, where we require `application/octet-stream`.
    $routes->addRequirements(['_content_type_format' => 'api_json']);

    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);

    // Flag every route as belonging to the JSON:API module.
    $routes->addDefaults([JsonapiRoutes::JSON_API_ROUTE_FLAG_KEY => TRUE]);

    // All routes serve only the JSON:API media type.
    $routes->addRequirements(['_format' => 'api_json']);

    return $routes;
  }

  protected function cartsCollection() {
    $collection_route = new Route('/cart');
    $collection_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => CartResourceController::class . ':getCarts']);
    $collection_route->setMethods(['GET']);
    $collection_route->setRequirement('_access', 'TRUE');
    return $collection_route;
  }

  protected function cartsCanonical() {
    $collection_route = new Route('/cart/{commerce_order}');
    $collection_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => CartResourceController::class . ':getCart']);
    $collection_route->setMethods(['GET']);
    $collection_route->setRequirement('_access', 'TRUE');
    $parameters = $collection_route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $collection_route->setOption('parameters', $parameters);
    return $collection_route;
  }

  protected function cartClear() {
    $collection_route = new Route('/cart/{commerce_order}/items');
    $collection_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => CartResourceController::class . ':clearItems']);
    $collection_route->setMethods(['DELETE']);
    $parameters = $collection_route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $collection_route->setOption('parameters', $parameters);
    return $collection_route;
  }

  protected function cartAdd() {
    $collection_route = new Route('/cart/add');
    $collection_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => CartResourceController::class . ':addItems']);
    $collection_route->setMethods(['POST']);
    return $collection_route;
  }
}
