<?php

namespace Drupal\commerce_cart_api\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
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
use Drupal\commerce_cart\CartProvider;
use Drupal\commerce_cart\CartSession;
use Drupal\commerce_cart\CartManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CartResourceController.
 */
class CartResourceController extends ControllerBase {

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
  protected $commerceCartCartProvider;
  /**
   * Drupal\commerce_cart\CartSession definition.
   *
   * @var \Drupal\commerce_cart\CartSession
   */
  protected $commerceCartCartSession;
  /**
   * Drupal\commerce_cart\CartManager definition.
   *
   * @var \Drupal\commerce_cart\CartManager
   */
  protected $commerceCartCartManager;

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
   * Constructs a new CartResourceController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   * @param \Drupal\commerce_cart\CartProvider $commerce_cart_cart_provider
   * @param \Drupal\commerce_cart\CartSession $commerce_cart_cart_session
   * @param \Drupal\commerce_cart\CartManager $commerce_cart_cart_manager
   * @param \Drupal\jsonapi\IncludeResolver $include_resolver
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   */
  public function __construct(EntityTypeManager $entity_type_manager, CartProvider $commerce_cart_cart_provider, CartSession $commerce_cart_cart_session, CartManager $commerce_cart_cart_manager, IncludeResolver $include_resolver, ResourceTypeRepositoryInterface $resource_type_repository, LinkManager $link_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->commerceCartCartProvider = $commerce_cart_cart_provider;
    $this->commerceCartCartSession = $commerce_cart_cart_session;
    $this->commerceCartCartManager = $commerce_cart_cart_manager;
    $this->includeResolver = $include_resolver;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->linkManager = $link_manager;
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
      $container->get('jsonapi.link_manager')
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
    $carts = $this->commerceCartCartProvider->getCarts();

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
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCart(Request $request, OrderInterface $commerce_order) {
    $resource_type = $this->resourceTypeRepository->get($commerce_order->getEntityTypeId(), $commerce_order->bundle());
    $resource_object = new ResourceObject($resource_type, $commerce_order);
    $self_link = new Link(new CacheableMetadata(), Url::fromRoute('commerce_checkout.form', ['commerce_order' => $commerce_order->id()]), ['checkout']);
    $links = new LinkCollection([
      $self_link
    ]);
    $response = $this->buildWrappedResponse($resource_object, $request, $this->getIncludes($request, $resource_object), 200, [], $links);
    return $response;
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
    $this->fixInclude($request);
    return $request->query->has('include') && ($include_parameter = $request->query->get('include')) && !empty($include_parameter)
      ? $this->includeResolver->resolve($data, $include_parameter)
      : new NullEntityCollection();
  }

  /**
   * Fixes the includes parameter to ensure order_item.
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
