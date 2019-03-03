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
 * Provides a cart collection resource for current session.
 *
 * @RestResource(
 *   id = "commerce_cart_update_items",
 *   label = @Translation("Cart items update"),
 *   uri_paths = {
 *     "canonical" = "/cart/{commerce_order}/items"
 *   }
 * )
 */
class CartUpdateItemsResource extends CartResourceBase {

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

  /**
   * PATCH to update order items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param array $unserialized
   *   The request body.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function patch(OrderInterface $commerce_order, array $unserialized) {
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');

    // Go through the request and validate the payload.
    $order_items = [];
    foreach ($unserialized as $order_item_id => $data) {
      $order_item = $order_item_storage->load($order_item_id);

      if (!$order_item instanceof OrderItemInterface) {
        throw new UnprocessableEntityHttpException(sprintf('Unable to find order item %s', $order_item_id));
      }
      if (!$commerce_order->hasItem($order_item)) {
        throw new UnprocessableEntityHttpException('Invalid order item');
      }
      if (count($data) > 1 || empty($data['quantity'])) {
        throw new UnprocessableEntityHttpException('You only have access to update the quantity');
      }
      if ($data['quantity'] < 1) {
        throw new UnprocessableEntityHttpException('Quantity must be positive value');
      }

      $order_item->setQuantity($data['quantity']);
      $violations = $order_item->validate();
      if (count($violations) > 0) {
        throw new UnprocessableEntityHttpException('You have provided an invalid quantity value');
      }

      $order_items[] = $order_item;
    }
    // We made it without errors, save the order items.
    foreach ($order_items as $order_item) {
      $order_item->save();
    }

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
    $route->setOption('parameters', $parameters);

    return $route;
  }

}
