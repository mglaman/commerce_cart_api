<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;

/**
 * Tests the cart canonical resource.
 *
 * @group commerce_cart_api
 */
class CartCanonicalResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_canonical';

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
    $request_options = $this->getAuthenticationRequestOptions('GET');

    $cart = $this->container->get('commerce_cart.cart_provider')->createCart('default', $this->store, $this->createUser());
    $this->assertInstanceOf(OrderInterface::class, $cart);

    $url = Url::fromUri('base:cart/' . $cart->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertResourceErrorResponse(403, "", $response, ['4xx-response', 'commerce_order:1', 'http_response'], [''], FALSE);
  }

  /**
   * Creates a cart and retrieves it via the REST API.
   */
  public function testGetCart() {
    $request_options = $this->getAuthenticationRequestOptions('GET');

    // Add a cart that does belong to the account.
    $cart = $this->container->get('commerce_cart.cart_provider')->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    $url = Url::fromUri('base:cart/' . $cart->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response, ['commerce_order:1', 'config:rest.resource.commerce_cart_canonical', 'config:rest.settings', 'http_response'], [''], FALSE, 'MISS');

    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals($response_body['order_id'], $cart->id());
    $this->assertEquals($response_body['order_number'], NULL);
    $this->assertEquals($response_body['store_id'], $this->store->id());
    $this->assertEmpty($response_body['order_items']);

    // Add order item to the cart.
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->assertEquals(count($cart->getItems()), 1);
    $items = $cart->getItems();
    $order_item = $items[0];

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response, ['commerce_order:1', 'config:rest.resource.commerce_cart_canonical', 'config:rest.settings', 'http_response'], [''], FALSE, 'MISS');

    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body['order_items']), 1);
    $this->assertEquals($response_body['order_items'][0]['order_item_id'], $order_item->id());
    $this->assertEquals($response_body['order_items'][0]['purchased_entity']['variation_id'], $order_item->getPurchasedEntityId());
    $this->assertEquals($response_body['order_items'][0]['title'], $order_item->getTitle());
    $this->assertEquals($response_body['order_items'][0]['quantity'], $order_item->getQuantity());
    $this->assertEquals($response_body['order_items'][0]['unit_price']['number'], $order_item->getUnitPrice()->getNumber());
    $this->assertEquals($response_body['order_items'][0]['unit_price']['currency_code'], $order_item->getUnitPrice()->getCurrencyCode());
    $this->assertEquals($response_body['order_items'][0]['unit_price']['formatted'], '$1,000.00');
    $this->assertEquals($response_body['order_items'][0]['total_price']['number'], $order_item->getTotalPrice()->getNumber());
    $this->assertEquals($response_body['order_items'][0]['total_price']['currency_code'], $order_item->getTotalPrice()->getCurrencyCode());
    $this->assertEquals($response_body['order_items'][0]['total_price']['formatted'], '$2,000.00');
  }

}
