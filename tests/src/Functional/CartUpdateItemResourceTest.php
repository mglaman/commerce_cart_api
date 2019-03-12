<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Tests the cart update items resource.
 *
 * @group commerce_cart_api
 */
class CartUpdateItemResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_update_item';

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

    $url = Url::fromUri('base:cart/1/items/1');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options[RequestOptions::BODY] = '{"quantity":"1"}';

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(404, FALSE, $response);
  }

  /**
   * Tests patch when order item does not exist in cart.
   */
  public function testMissingOrderItem() {
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 2);

    $url = Url::fromUri('base:cart/' . $cart->id() . '/items/2');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options[RequestOptions::BODY] = '{"quantity":"1"}';

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(404, FALSE, $response);

    // Create order item in another cart.
    $another_cart = $this->cartProvider->createCart('default', $this->store);
    $this->cartManager->addEntity($another_cart, $this->variation, 2);

    // New order item should still be unavailable for patch.
    /* Bug here?
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(404, FALSE, $response);
     */
  }

  /**
   * Tests cart that does not belong to the account.
   */
  public function testCartNoAccess() {
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Cart that does not belong to the account.
    $cart = $this->cartProvider->createCart('default', $this->store);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->assertEquals(count($cart->getItems()), 1);
    $items = $cart->getItems();
    $order_item = $items[0];

    $url = Url::fromUri('base:cart/' . $cart->id() . '/items/' . $order_item->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(403, FALSE, $response);
  }

  /**
   * Tests malformed payloads.
   */
  public function testInvalidPayload() {
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->assertEquals(count($cart->getItems()), 1);
    $items = $cart->getItems();
    $order_item = $items[0];

    $url = Url::fromUri('base:cart/' . $cart->id() . '/items/' . $order_item->id());
    $url->setOption('query', ['_format' => static::$format]);

    $request_options[RequestOptions::BODY] = '{"quantity":"1", "another_field":"1"}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'You only have access to update the quantity', $response);

    $request_options[RequestOptions::BODY] = '{"not_quantity":"1"}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'You only have access to update the quantity', $response);

    $request_options[RequestOptions::BODY] = '{"quantity":"-1"}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'Quantity must be positive value', $response);
  }

  /**
   * Patch an order item for a session's cart via the REST API.
   */
  public function testPatchOrderItem() {
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->cartManager->addEntity($cart, $this->variation_2, 5);
    $this->assertEquals(count($cart->getItems()), 2);
    $items = $cart->getItems();
    $order_item = $items[0];
    $order_item_2 = $items[1];
    $this->assertEquals($order_item->getQuantity(), 2);
    $this->assertEquals($order_item_2->getQuantity(), 5);

    // Patch quantity for second order item.
    $url = Url::fromUri('base:cart/' . $cart->id() . '/items/' . $order_item_2->id());
    $url->setOption('query', ['_format' => static::$format]);
    $request_options[RequestOptions::BODY] = '{"quantity":"1"}';
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);

    // Verify order item updated.
    $this->container->get('entity_type.manager')->getStorage('commerce_order_item')->resetCache([
      $order_item->id(),
      $order_item_2->id(),
    ]);
    $order_item = OrderItem::load($order_item->id());
    $order_item_2 = OrderItem::load($order_item_2->id());
    $this->assertEquals($order_item->getQuantity(), 2);
    $this->assertEquals($order_item_2->getQuantity(), 1);

    // Verify cart total updated.
    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$cart->id()]);
    $cart = Order::load($cart->id());
    $this->assertEquals($cart->getTotalPrice()->getNumber(), 2500);

    // Verify json response.
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals($response_body['order_id'], $cart->id());
    $this->assertEquals($response_body['order_number'], $cart->getOrderNumber());
    $this->assertEquals($response_body['store_id'], $cart->getStoreId());
    $this->assertEquals($response_body['total_price']['number'], $cart->getTotalPrice()->getNumber());
    $this->assertEquals($response_body['total_price']['currency_code'], $cart->getTotalPrice()->getCurrencyCode());
    $this->assertEquals($response_body['total_price']['formatted'], '$2,500.00');
    $this->assertEquals(count($response_body['order_items']), 2);

    // First order item.
    $item_delta = ($response_body['order_items'][0]['order_item_id'] == $order_item->id()) ? 0 : 1;
    $this->assertEquals($response_body['order_items'][$item_delta]['order_item_id'], $order_item->id());
    $this->assertEquals($response_body['order_items'][$item_delta]['purchased_entity']['variation_id'], $order_item->getPurchasedEntityId());
    $this->assertEquals($response_body['order_items'][$item_delta]['quantity'], $order_item->getQuantity());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['number'], $order_item->getTotalPrice()->getNumber());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['currency_code'], $order_item->getTotalPrice()->getCurrencyCode());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['formatted'], '$2,000.00');

    // Second order item.
    $item_delta = ($response_body['order_items'][0]['order_item_id'] == $order_item_2->id()) ? 0 : 1;
    $this->assertEquals($response_body['order_items'][$item_delta]['order_item_id'], $order_item_2->id());
    $this->assertEquals($response_body['order_items'][$item_delta]['purchased_entity']['variation_id'], $order_item_2->getPurchasedEntityId());
    $this->assertEquals($response_body['order_items'][$item_delta]['quantity'], $order_item_2->getQuantity());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['number'], $order_item_2->getTotalPrice()->getNumber());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['currency_code'], $order_item_2->getTotalPrice()->getCurrencyCode());
    $this->assertEquals($response_body['order_items'][$item_delta]['total_price']['formatted'], '$500.00');
  }

}
