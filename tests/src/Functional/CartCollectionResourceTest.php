<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;

/**
 * Tests the cart collection resource.
 *
 * @group commerce_cart_api
 */
class CartCollectionResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_collection';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->setUpAuthorization('GET');
  }

  /**
   * Tests that cart that doesn't belong to account can't be retrieved.
   */
  public function testNoCartAvailable() {
    $url = Url::fromUri('base:cart');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options = $this->getAuthenticationRequestOptions('GET');

    $cart = $this->cartProvider->createCart('default', $this->store);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    $response = $this->request('GET', $url, $request_options);

    $this->assertResourceResponse(200, FALSE, $response, [
      'config:rest.resource.commerce_cart_collection',
      'config:rest.settings',
      'http_response',
    ], ['cart', 'store'], FALSE, 'MISS');

    $response_body = Json::decode((string) $response->getBody());
    $this->assertEmpty($response_body);
  }

  /**
   * Gets carts via the REST API.
   */
  public function testGetCarts() {
    $url = Url::fromUri('base:cart');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options = $this->getAuthenticationRequestOptions('GET');

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response, [
      'commerce_order:1',
      'config:rest.resource.commerce_cart_collection',
      'config:rest.settings',
      'http_response',
    ], ['cart', 'store'], FALSE, 'MISS');

    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body), 1);
    $response_body = $response_body[0];
    $this->assertEquals($response_body['order_id'], $cart->id());
    $this->assertEquals($response_body['order_number'], NULL);
    $this->assertEquals($response_body['store_id'], $this->store->id());
    $this->assertEmpty($response_body['order_items']);

    // Add another cart for a second store.
    $store2 = $this->createStore();
    $cart2 = $this->cartProvider->createCart('default', $store2, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart2);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response, [
      'commerce_order:1',
      'commerce_order:2',
      'config:rest.resource.commerce_cart_collection',
      'config:rest.settings',
      'http_response',
    ], ['cart', 'store'], FALSE, 'MISS');

    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body), 2);
  }

}
