<?php

namespace Drupal\commerce_cart_api\Plugin\rest\resource;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
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
   * Constructs a CartResourceBase object.
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, SerializerInterface $serializer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $cart_provider, $cart_manager);
    $this->serializer = $serializer;
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
      $container->get('serializer')
    );
  }

  /**
   * PATCH to update order items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param array $unserialized
   *   The request body.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function patch(OrderInterface $commerce_order, array $unserialized, Request $request) {
    $format = $request->getContentType();
    // Purge read only fields.
    // @todo We should investigate implementing field access checks on these.
    foreach ($unserialized as $delta => $unserialized_order_item) {
      unset($unserialized[$delta]['purchased_entity']);
      unset($unserialized[$delta]['title']);
      unset($unserialized[$delta]['unit_price']);
      unset($unserialized[$delta]['total_price']);
    }

    foreach ($unserialized as $unserialized_order_item) {
      try {
        // @todo this would bork if someone customized entity class.
        // @todo use entity type manager to get entity class, and storage.
        /** @var \Drupal\commerce_order\Entity\OrderItemInterface $updated_order_item */
        $updated_order_item = $this->serializer->denormalize($unserialized_order_item, OrderItem::class, $format, ['request_method' => 'patch']);
        $original_order_item = OrderItem::load($updated_order_item->id());

        if (!$commerce_order->hasItem($original_order_item)) {
          throw new UnprocessableEntityHttpException('Invalid order item');
        }
      }
      catch (UnexpectedValueException $e) {
        throw new UnprocessableEntityHttpException($e->getMessage());
      }
      catch (InvalidArgumentException $e) {
        throw new UnprocessableEntityHttpException($e->getMessage());
      }

      foreach ($updated_order_item->_restSubmittedFields as $field_name) {
        $field = $updated_order_item->get($field_name);
        $original_order_item->set($field_name, $field->getValue());
      }

      $original_order_item->save();
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
