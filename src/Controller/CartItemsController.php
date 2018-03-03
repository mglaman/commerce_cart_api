<?php

namespace Drupal\commerce_cart_api\Controller;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Controller to provide a collection of carts for current session.
 */
class CartItemsController implements ContainerInjectionInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  protected $cartManager;

  /**
   * CartCollection constructor.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *
   */
  public function __construct(CartProviderInterface $cart_provider, CartManagerInterface $cart_manager) {
    $this->cartProvider = $cart_provider;
    $this->cartManager = $cart_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager')
    );
  }

  /**
   * DELETE an order item from a cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $commerce_order_item
   *   The order item.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   */
  public function delete(OrderInterface $commerce_order, OrderItemInterface $commerce_order_item) {
    $carts = $this->cartProvider->getCartIds();
    if (!in_array($commerce_order->id(), $carts)) {
      throw new AccessDeniedHttpException();
    }
    if (!$commerce_order->hasItem($commerce_order_item)) {
      throw new AccessDeniedHttpException();
    }

    $commerce_order->_cart_api = TRUE;
    $this->cartManager->removeOrderItem($commerce_order, $commerce_order_item);

    // DELETE responses have an empty body.
    // @todo wanted to return the order. But REST reponse subscriber freaks out.
    return new ModifiedResourceResponse(NULL, 204);
  }

  public function patch(OrderInterface $commerce_order, Request $request) {
    $carts = $this->cartProvider->getCartIds();
    if (!in_array($commerce_order->id(), $carts)) {
      throw new AccessDeniedHttpException();
    }

    $received = $request->getContent();
    $format = $request->getContentType();
    // @todo injection.
    $serializer = \Drupal::getContainer()->get('serializer');
    try {
      $unserialized = $serializer->decode($received, $format, ['request_method' => 'patch']);
    }
    catch (UnexpectedValueException $e) {
      // If an exception was thrown at this stage, there was a problem
      // decoding the data. Throw a 400 http exception.
      throw new BadRequestHttpException($e->getMessage());
    }

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
        /** @var \Drupal\commerce_order\Entity\OrderItemInterface $updated_order_item */
        $updated_order_item = $serializer->denormalize($unserialized_order_item, OrderItem::class, $format, ['request_method' => 'patch']);
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

    $commerce_order->_cart_api = TRUE;
    $commerce_order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
    $commerce_order->save();

    // Return the updated entity in the response body.
    return new ModifiedResourceResponse($commerce_order, 200);
  }

}
