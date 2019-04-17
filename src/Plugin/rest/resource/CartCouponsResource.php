<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\CouponStorageInterface;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\rest\resource\EntityResourceValidationTrait;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Provides a cart collection resource for current session.
 *
 * @RestResource(
 *   id = "commerce_cart_coupons",
 *   label = @Translation("Cart coupons"),
 *   uri_paths = {
 *     "canonical" = "/cart/{commerce_order}/coupons"
 *   }
 * )
 */
class CartCouponsResource extends CartResourceBase {

  use EntityResourceValidationTrait;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CartUpdateItemsResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, SerializerInterface $serializer, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $cart_provider, $cart_manager);
    $this->serializer = $serializer;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('serializer'),
      $container->get('entity_type.manager')
    );
  }

  public function get(OrderInterface $commerce_order) {
    $response = new ResourceResponse($commerce_order->get('coupons'));
    $response->addCacheableDependency($commerce_order);
    return $response;
  }

  public function patch(OrderInterface $commerce_order, array $unserialized) {
    // Add coupons.
    if (!isset($unserialized['coupon_code'])) {
      throw new BadRequestHttpException('Coupon code not provided.');
    }

    $coupon_storage = $this->entityTypeManager->getStorage('commerce_promotion_coupon');
    assert($coupon_storage instanceof CouponStorageInterface);

    $coupon = $coupon_storage->loadEnabledByCode($unserialized['coupon_code']);
    if (!$coupon instanceof CouponInterface) {
      throw new UnprocessableEntityHttpException(sprintf('%s is not a valid coupon code.', $unserialized['coupon_code']));
    }

    $commerce_order->get('coupons')->setValue([$coupon]);
    $this->validate($commerce_order);
    try {
      $commerce_order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
      $commerce_order->save();
      return new ModifiedResourceResponse($commerce_order, 200);
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

  public function delete(OrderInterface $commerce_order) {
    $commerce_order->get('coupons')->setValue(NULL);
    $commerce_order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
    $commerce_order->save();
    return new ModifiedResourceResponse(NULL, 204);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method) {
    $route = parent::getBaseRoute($canonical_path, $method);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $route->setOption('parameters', $parameters);

    return $route;
  }

}
