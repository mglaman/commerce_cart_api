<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\commerce_cart_api\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\CouponStorageInterface;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\jsonapi_resources\ResourceResponseFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CartCouponAddResource extends EntityResourceBase {

  use EntityValidationTrait;

  /**
   * The JSON:API controller.
   *
   * @var \Drupal\commerce_cart_api\EntityResourceShim
   */
  protected $inner;

  /**
   * @var \Drupal\Core\Render\Renderer|object|null
   */
  private $renderer;

  public function __construct(ResourceResponseFactory $resource_response_factory, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManagerInterface $entity_type_manager, EntityAccessChecker $entity_access_checker, EntityResourceShim $jsonapi_controller, RendererInterface $renderer) {
    parent::__construct($resource_response_factory, $resource_type_repository, $entity_type_manager, $entity_access_checker);
    $this->inner = $jsonapi_controller;
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
      $container->get('commerce_cart_api.jsonapi_controller_shim'),
      $container->get('renderer')
    );
  }

  public function process(Request $request, OrderInterface $commerce_order) {
    $resource_type = $this->resourceTypeRepository->get($commerce_order->getEntityTypeId(), $commerce_order->bundle());
    $internal_relationship_field_name = $resource_type->getInternalName('coupons');
    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'coupons');

    $context = new RenderContext();
    /** @var \Drupal\commerce_promotion\Entity\CouponInterface[] $coupons */
    $coupons = $this->renderer->executeInRenderContext($context, function () use ($commerce_order, $resource_identifiers) {
      $coupons = [];
      $coupon_storage = $this->entityTypeManager->getStorage('commerce_promotion_coupon');
      assert($coupon_storage instanceof CouponStorageInterface);
      foreach ($resource_identifiers as $resource_identifier) {
        $coupon = $coupon_storage->loadEnabledByCode($resource_identifier->getId());
        if (!$coupon instanceof CouponInterface) {
          throw new UnprocessableEntityHttpException(sprintf('%s is not a valid coupon code.', $resource_identifier->getId()));
        }
        $coupons[] = $coupon;
      }
      return $coupons;
    });

    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $commerce_order->{$internal_relationship_field_name};
    $field_list->setValue($coupons);
    self::validate($commerce_order);

    $commerce_order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
    $commerce_order->save();

    $resource_object = ResourceObject::createFromEntity($this->resourceTypeRepository->get($commerce_order->getEntityTypeId(), $commerce_order->bundle()), $commerce_order);
    $primary_data = new ResourceObjectData([$resource_object], 1);
    return $this->createJsonapiResponse($primary_data, $request);
  }

}
