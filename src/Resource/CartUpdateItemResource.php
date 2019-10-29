<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart_api\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_resources\ResourceResponseFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CartUpdateItemResource extends CartResourceBase {

  use EntityValidationTrait;

  /**
   * The JSON:API controller shim.
   *
   * @var \Drupal\commerce_cart_api\EntityResourceShim
   */
  protected $inner;

  /**
   * Constructs a new CartUpdateItemResource object.
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
   * Update an order item from a cart.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $commerce_order_item
   *   The order item.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function process(Request $request, OrderInterface $commerce_order, OrderItemInterface $commerce_order_item): ResourceResponse {
    $resource_type = $this->resourceTypeRepository->get($commerce_order_item->getEntityTypeId(), $commerce_order_item->bundle());
    $parsed_entity = $this->inner->deserialize($resource_type, $request, JsonApiDocumentTopLevel::class);
    assert($parsed_entity instanceof OrderItemInterface);

    $body = Json::decode($request->getContent());
    $data = $body['data'];
    if ($data['id'] !== $commerce_order_item->uuid()) {
      throw new BadRequestHttpException(sprintf('The selected entity (%s) does not match the ID in the payload (%s).', $commerce_order_item->uuid(), $data['id']));
    }
    $data += ['attributes' => [], 'relationships' => []];
    $data_field_names = array_merge(array_keys($data['attributes']), array_keys($data['relationships']));

    $field_names = [];
    foreach ($data_field_names as $data_field_name) {
      $field_name = $resource_type->getInternalName($data_field_name);

      $parsed_field_item = $parsed_entity->get($field_name);
      $original_field_item = $commerce_order_item->get($field_name);
      if ($this->inner->checkPatchFieldAccess($parsed_field_item, $original_field_item)) {
        $commerce_order_item->set($field_name, $parsed_field_item->getValue());
      }
      $field_names[] = $field_name;
    }

    static::validate($commerce_order_item, $field_names);

    $commerce_order_item->save();
    $commerce_order->save();

    $resource_object = ResourceObject::createFromEntity($this->resourceTypeRepository->get($commerce_order->getEntityTypeId(), $commerce_order->bundle()), $commerce_order);
    $primary_data = new ResourceObjectData([$resource_object], 1);
    return $this->createJsonapiResponse($primary_data, $request);
  }

}
