<?php

namespace Drupal\commerce_cart_api\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\Resource\EntityCollection;
use Drupal\jsonapi\Resource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\commerce_cart\CartProvider;
use Drupal\commerce_cart\CartSession;
use Drupal\commerce_cart\CartManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * Constructs a new CartResourceController object.
   */
  public function __construct(EntityTypeManager $entity_type_manager, CartProvider $commerce_cart_cart_provider, CartSession $commerce_cart_cart_session, CartManager $commerce_cart_cart_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->commerceCartCartProvider = $commerce_cart_cart_provider;
    $this->commerceCartCartSession = $commerce_cart_cart_session;
    $this->commerceCartCartManager = $commerce_cart_cart_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_session'),
      $container->get('commerce_cart.cart_manager')
    );
  }

  public function getCarts(Request $request, RouteMatchInterface $routeMatch) {
    $carts = $this->commerceCartCartProvider->getCarts();
    $route_bundle = $routeMatch->getRouteObject()->getRequirement('_bundle');
    $carts = array_filter($carts, function (OrderInterface $cart) use ($route_bundle) {
      return $cart->bundle() == $route_bundle;
    });

    $this->fixInclude($request);

    $entity_collection = new EntityCollection($carts);
    $response = new ResourceResponse(new JsonApiDocumentTopLevel($entity_collection), 200, []);
    return $response;
  }

  public function getCart(Request $request, OrderInterface $commerce_order) {
    // Validate the cart belongs to user.
    if ($this->commerceCartCartSession->hasCartId($commerce_order->id())) {
      $this->fixInclude($request);
      return new ResourceResponse(new JsonApiDocumentTopLevel($commerce_order));
    }

    throw new NotFoundHttpException();
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @see \Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer::expandContext
   */
  protected function fixInclude(Request $request) {
    // @todo Fix this query param mock.
    // This is read in \Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer::expandContext
    // @todo we also cannot run `include` for included entities (order items)
    $request->query->set('include', 'order_items');

    $request->query->set('fields', [
      'commerce_order--default' => 'uuid,adjustments,total_price,order_items',
      'commerce_order_item--default' => 'uuid,adjustments,unit_price,total_price,quantity,title,purchased_entity',
    ]);
  }

}
