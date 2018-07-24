<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Tests the cart update items resource.
 *
 * @group commerce_cart_api
 */
class CartUpdateItemsResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_update_items';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->setUpAuthorization('PATCH');
  }

  /**
   * Tests patch when cart does not exist.
   */
  public function testMissingCart() {
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Attempt to patch items when no cart exists.
    $url = Url::fromUri('base:cart/1/items');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options[RequestOptions::BODY] = '{"1":{"quantity":"1"}}';

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(404, FALSE, $response);
  }

  /**
   * Tests malformed payloads.
   */
  public function testInvalidPayload() {
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $url = Url::fromUri('base:cart/' . $cart->id() . '/items');
    $url->setOption('query', ['_format' => static::$format]);

    $request_options[RequestOptions::BODY] = '{"1":{"quantity":"1"}}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'Unable to find order item 1', $response);

    // Create an item in another cart.
    $another_cart = $this->cartProvider->createCart('default', $this->store);
    $this->cartManager->addEntity($another_cart, $this->variation, 2);

    $request_options[RequestOptions::BODY] = '{"1":{"quantity":"1"}}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'Invalid order item', $response);

    // Give the original cart a valid order item.
    $this->cartManager->addEntity($cart, $this->variation, 2);

    $request_options[RequestOptions::BODY] = '{"2":{"quantity":"1", "another_field":"1"}}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'You only have access to update the quantity', $response);

    $request_options[RequestOptions::BODY] = '{"2":{"not_quantity":"1"}}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'You only have access to update the quantity', $response);

    $request_options[RequestOptions::BODY] = '{"2":{"quantity":"-1"}}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'Quantity must be positive value', $response);
  }

  /**
   * Patch order items for a session's cart via the REST API.
   */
  public function testPatchOrderItems() {
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Cart that does not belong to the account.
    $not_my_cart = $this->cartProvider->createCart('default', $this->store);
    $this->assertInstanceOf(OrderInterface::class, $not_my_cart);
    $this->cartManager->addEntity($not_my_cart, $this->variation, 2);
    $this->assertEquals(count($not_my_cart->getItems()), 1);
    $items = $not_my_cart->getItems();
    $not_my_order_item = $items[0];

    $url = Url::fromUri('base:cart/' . $not_my_cart->id() . '/items');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options[RequestOptions::BODY] = '{"1":{"quantity":"1"},"2":{"quantity":"1.00"}}';

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(403, FALSE, $response);

    // Add a cart that does belong to the account.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->cartManager->addEntity($cart, $this->variation_2, 5);
    $this->assertEquals(count($cart->getItems()), 2);
    $items = $cart->getItems();
    $order_item = $items[0];
    $order_item2 = $items[1];

    // Attempt to update items in two different carts.
    $url = Url::fromUri('base:cart/' . $cart->id() . '/items');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options[RequestOptions::BODY] = '{"1":{"quantity":"1"},"2":{"quantity":"1.00"}}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, FALSE, $response);

    // Verify that neither cart was altered.
    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$not_my_cart->id(), $cart->id()]);
    $not_my_cart = Order::load($not_my_cart->id());
    $cart = Order::load($cart->id());
    $this->assertEquals($not_my_cart->getTotalPrice()->getNumber(), 2000);
    $this->assertEquals($cart->getTotalPrice()->getNumber(), 4500);

    // Update items in cart belonging to account.
    $url = Url::fromUri('base:cart/' . $cart->id() . '/items');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options[RequestOptions::BODY] = '{"2":{"quantity":"1"},"3":{"quantity":"1.00"}}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);

    // Verify order items properly updated.
    $this->container->get('entity_type.manager')->getStorage('commerce_order_item')->resetCache([
      $order_item->id(),
      $order_item2->id(),
    ]);
    $order_item = OrderItem::load($order_item->id());
    $order_item2 = OrderItem::load($order_item2->id());
    $this->assertEquals($order_item->getQuantity(), 1);
    $this->assertEquals($order_item2->getQuantity(), 1);

    // Verify cart total properly updated.
    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$cart->id()]);
    $cart = Order::load($cart->id());
    $this->assertEquals($cart->getTotalPrice()->getNumber(), 1500);
    $this->assertEquals($cart->getTotalPrice()->getCurrencyCode(), 'USD');

    // Verify json response.
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals($response_body['order_id'], $cart->id());
    $this->assertEquals($response_body['order_number'], $cart->getOrderNumber());
    $this->assertEquals($response_body['store_id'], $cart->getStoreId());
    $this->assertEquals($response_body['total_price']['number'], $cart->getTotalPrice()->getNumber());
    $this->assertEquals($response_body['total_price']['currency_code'], $cart->getTotalPrice()->getCurrencyCode());
    $this->assertEquals(count($response_body['order_items']), 2);

    // First order item.
    $item_delta = ($response_body['order_items'][0]['order_item_id'] == 2) ? 0 : 1;
    $this->assertEquals($response_body['order_items'][$item_delta]['order_item_id'], $order_item->id());
    $this->assertEquals($response_body['order_items'][$item_delta]['purchased_entity']['variation_id'], $order_item->getPurchasedEntityId());
    $this->assertEquals($response_body['order_items'][$item_delta]['quantity'], $order_item->getQuantity());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['number'], $order_item->getTotalPrice()->getNumber());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['currency_code'], $order_item->getTotalPrice()->getCurrencyCode());

    // Second order item.
    $item_delta = ($response_body['order_items'][0]['order_item_id'] == 3) ? 0 : 1;
    $this->assertEquals($response_body['order_items'][$item_delta]['order_item_id'], $order_item2->id());
    $this->assertEquals($response_body['order_items'][$item_delta]['purchased_entity']['variation_id'], $order_item2->getPurchasedEntityId());
    $this->assertEquals($response_body['order_items'][$item_delta]['quantity'], $order_item2->getQuantity());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['number'], $order_item2->getTotalPrice()->getNumber());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['currency_code'], $order_item2->getTotalPrice()->getCurrencyCode());
  }

}
