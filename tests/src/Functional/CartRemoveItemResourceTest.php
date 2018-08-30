<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Url;

/**
 * Tests the cart remove item resource.
 *
 * @group commerce_cart_api
 */
class CartRemoveItemResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_remove_item';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->setUpAuthorization('DELETE');
  }

  /**
   * Test request to delete item from non-existent cart.
   */
  public function testNoCartRemoveItem() {
    $request_options = $this->getAuthenticationRequestOptions('DELETE');
    $url = Url::fromUri('base:cart/1/items/1');
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResourceErrorResponse(404, 'The "commerce_order" parameter was not converted for the path "/cart/{commerce_order}/items/{commerce_order_item}" (route name: "rest.commerce_cart_remove_item.DELETE")', $response);
  }

  /**
   * Removes cart items via the REST API.
   */
  public function testRemoveItem() {
    $request_options = $this->getAuthenticationRequestOptions('DELETE');

    // Failed request to delete item from cart that doesn't belong to the account.
    $not_my_cart = $this->cartProvider->createCart('default', $this->store);
    $this->assertInstanceOf(OrderInterface::class, $not_my_cart);
    $this->cartManager->addEntity($not_my_cart, $this->variation, 2);
    $this->assertEquals(count($not_my_cart->getItems()), 1);
    $items = $not_my_cart->getItems();
    $not_my_order_item = $items[0];

    $url = Url::fromUri('base:cart/' . $not_my_cart->id() . '/items/' . $not_my_order_item->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResourceErrorResponse(403, '', $response);

    // Add a cart that does belong to the account.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->cartManager->addEntity($cart, $this->variation_2, 5);
    $this->assertEquals(count($cart->getItems()), 2);
    $items = $cart->getItems();
    $order_item = $items[0];
    $order_item2 = $items[1];

    // Request for order item that does not exist in the cart should fail.
    $url = Url::fromUri('base:cart/' . $cart->id() . '/items/' . $not_my_order_item->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResourceErrorResponse(403, '', $response);
    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$not_my_cart->id(), $cart->id()]);
    $not_my_cart = Order::load($not_my_cart->id());
    $cart = Order::load($cart->id());

    $this->assertEquals(count($not_my_cart->getItems()), 1);
    $this->assertEquals(count($cart->getItems()), 2);

    // Delete second order item from the cart.
    $url = Url::fromUri('base:cart/' . $cart->id() . '/items/' . $order_item2->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResourceResponse(204, '', $response);
    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$cart->id()]);
    $cart = Order::load($cart->id());

    $this->assertEquals(count($cart->getItems()), 1);
    $items = $cart->getItems();
    $remaining_order_item = $items[0];
    $this->assertEquals($order_item->id(), $remaining_order_item->id());

    // Delete remaining order item from the cart.
    $url = Url::fromUri('base:cart/' . $cart->id() . '/items/' . $remaining_order_item->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResourceResponse(204, '', $response);
    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$cart->id()]);
    $cart = Order::load($cart->id());

    $this->assertEquals(count($cart->getItems()), 0);
  }

}
