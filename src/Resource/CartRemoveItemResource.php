<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart_api\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderItemStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\Exception\UnprocessableHttpEntityException;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRelationship;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_resources\ResourceResponseFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CartRemoveItemResource extends CartResourceBase {

  /**
   * The JSON:API controller shim.
   *
   * @var \Drupal\commerce_cart_api\EntityResourceShim
   */
  protected $inner;

  /**
   * Constructs a new CartRemoveItemResource object.
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
   */
  public function __construct(ResourceResponseFactory $resource_response_factory, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManagerInterface $entity_type_manager, EntityAccessChecker $entity_access_checker, CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, EntityResourceShim $jsonapi_controller) {
    parent::__construct($resource_response_factory, $resource_type_repository, $entity_type_manager, $entity_access_checker, $cart_provider, $cart_manager);
    $this->inner = $jsonapi_controller;
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

  /**
   * DELETE an order item from a cart.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function process(Request $request, OrderInterface $commerce_order, array $_order_item_resource_types = []): ResourceResponse {
    // @todo `default` may not exist. Order items are not a based field, yet.
    // @todo once `items` is a base field, change to "virtual".
    // @see https://www.drupal.org/project/commerce/issues/3002939
    $resource_type = new ResourceType('commerce_order', 'default', EntityInterface::class, FALSE, TRUE, FALSE, FALSE,
      [
        'order_items' => new ResourceTypeRelationship('order_items', 'order_items', TRUE, FALSE),
      ]
    );
    assert($resource_type->getInternalName('order_items') === 'order_items');
    /* @var \Drupal\jsonapi\ResourceType\ResourceType[] $order_item_resource_types */
    $order_item_resource_types = array_map(function ($resource_type_name) {
      return $this->resourceTypeRepository->getByTypeName($resource_type_name);
    }, $_order_item_resource_types);
    $resource_type->setRelatableResourceTypes(['order_items' => $order_item_resource_types]);

    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
    assert($order_item_storage instanceof OrderItemStorageInterface);

    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'order_items');
    foreach ($resource_identifiers as $resource_identifier) {
      // @todo inject entity repository.
      $order_item = $order_item_storage->loadByProperties(['uuid' => $resource_identifier->getId()]);
      $order_item = reset($order_item);
      if (!$order_item instanceof OrderItemInterface || !$commerce_order->hasItem($order_item)) {
        throw new UnprocessableEntityHttpException("Order item {$resource_identifier->getId()} does not exist for order {$commerce_order->uuid()}.");
      }
      $this->cartManager->removeOrderItem($commerce_order, $order_item);
    }

    return new ResourceResponse(NULL, 204);
  }

}
