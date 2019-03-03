<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Resource for updating the quantity of a cart's single order item.
 *
 * @RestResource(
 *   id = "commerce_cart_update_item",
 *   label = @Translation("Cart item update"),
 *   uri_paths = {
 *     "canonical" = "/cart/{commerce_order}/items/{commerce_order_item}"
 *   }
 * )
 */
class CartUpdateItemResource extends CartResourceBase {

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
   * Constructs a new CartUpdateItemResource object.
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

  /**
   * PATCH to update order items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $commerce_order_item
   *   The order item.
   * @param array $unserialized
   *   The request body.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function patch(OrderInterface $commerce_order, OrderItemInterface $commerce_order_item, array $unserialized) {
    if (count($unserialized) > 1 || empty($unserialized['quantity'])) {
      throw new UnprocessableEntityHttpException('You only have access to update the quantity');
    }
    if ($unserialized['quantity'] < 1) {
      throw new UnprocessableEntityHttpException('Quantity must be positive value');
    }

    $commerce_order_item->setQuantity($unserialized['quantity']);
    $violations = $commerce_order_item->validate();
    if (count($violations) > 0) {
      throw new UnprocessableEntityHttpException('You have provided an invalid quantity value');
    }

    $commerce_order_item->save();
    $commerce_order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
    $commerce_order->save();

    // Return the updated entity in the response body.
    return new ModifiedResourceResponse($commerce_order, 200);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method) {
    $route = parent::getBaseRoute($canonical_path, $method);
    $parameters = $route->getOption('parameters') ?: [];
    $parameters['commerce_order']['type'] = 'entity:commerce_order';
    $parameters['commerce_order_item']['type'] = 'entity:commerce_order_item';
    $route->setOption('parameters', $parameters);

    return $route;
  }

}
