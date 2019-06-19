<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Tests the cart add resource.
 *
 * @group commerce_cart_api
 */
class CartAddResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_add';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->setUpAuthorization('POST');
  }

  /**
   * Tests malformed payloads.
   */
  public function testMalformedPayload() {
    $url = Url::fromUri('base:cart/add');
    $url->setOption('query', ['_format' => static::$format]);

    $request_options = $this->getAuthenticationRequestOptions('POST');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Missing purchasable entity type.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_id": "1", "quantity": "1"}]';
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'You must specify a purchasable entity type for row: 0', $response);

    // Missing purchasable entity ID.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "quantity": "1"}]';
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'You must specify a purchasable entity ID for row: 0', $response);

    // Invalid purchasable entity type.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "invalid_type", "purchased_entity_id": "1", "quantity": "1"}]';
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'You must specify a valid purchasable entity type for row: 0', $response);
  }

  /**
   * Tests invalid purchased entity.
   */
  public function testInvalidPurchasedEntity() {
    $url = Url::fromUri('base:cart/add');
    $url->setOption('query', ['_format' => static::$format]);

    $request_options = $this->getAuthenticationRequestOptions('POST');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Add item when no cart exists.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "purchased_entity_id": "99", "quantity": "1"}]';

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEmpty($response_body);
  }

  /**
   * Creates order items for a session's cart via the REST API.
   */
  public function testPostOrderItems() {
    $url = Url::fromUri('base:cart/add');
    $url->setOption('query', ['_format' => static::$format]);

    $request_options = $this->getAuthenticationRequestOptions('POST');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Add item when no cart exists.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "purchased_entity_id": "1", "quantity": "1"}]';

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body), 1);
    $this->assertEquals(count($response_body), 1);
    $this->assertEquals($response_body[0]['order_item_id'], 1);
    $this->assertEquals($response_body[0]['purchased_entity']['variation_id'], 1);
    $this->assertEquals($response_body[0]['quantity'], 1);
    $this->assertEquals($response_body[0]['unit_price']['number'], 1000);
    $this->assertEquals($response_body[0]['unit_price']['currency_code'], 'USD');
    $this->assertEquals($response_body[0]['total_price']['number'], 1000);
    $this->assertEquals($response_body[0]['total_price']['currency_code'], 'USD');

    // Add two more of the same item.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "purchased_entity_id": "1", "quantity": "2"}]';

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body), 1);
    $this->assertEquals($response_body[0]['order_item_id'], 1);
    $this->assertEquals($response_body[0]['quantity'], 3);
    $this->assertEquals($response_body[0]['unit_price']['number'], 1000);
    $this->assertEquals($response_body[0]['unit_price']['currency_code'], 'USD');
    $this->assertEquals($response_body[0]['total_price']['number'], 3000);
    $this->assertEquals($response_body[0]['total_price']['currency_code'], 'USD');

    // Add another item.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "purchased_entity_id": "2", "quantity": "5"}]';

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body), 1);
    $item_delta = ($response_body[0]['order_item_id'] == 2) ? 0 : 1;
    $this->assertEquals($response_body[$item_delta]['quantity'], 5);
    $this->assertEquals($response_body[$item_delta]['unit_price']['number'], 500);
    $this->assertEquals($response_body[$item_delta]['unit_price']['currency_code'], 'USD');
    $this->assertEquals($response_body[$item_delta]['total_price']['number'], 2500);
    $this->assertEquals($response_body[$item_delta]['total_price']['currency_code'], 'USD');
  }

  public function testCombine() {
    $url = Url::fromUri('base:cart/add');
    $url->setOption('query', ['_format' => static::$format]);

    $request_options = $this->getAuthenticationRequestOptions('POST');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Add item when no cart exists.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "purchased_entity_id": "1", "quantity": "1"}]';

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body), 1);
    $this->assertEquals($response_body[0]['order_item_id'], 1);
    $this->assertEquals($response_body[0]['purchased_entity']['variation_id'], 1);
    $this->assertEquals($response_body[0]['quantity'], 1);
    $this->assertEquals($response_body[0]['unit_price']['number'], 1000);
    $this->assertEquals($response_body[0]['unit_price']['currency_code'], 'USD');
    $this->assertEquals($response_body[0]['total_price']['number'], 1000);
    $this->assertEquals($response_body[0]['total_price']['currency_code'], 'USD');

    // Add two more of the same item.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "purchased_entity_id": "1", "quantity": "2", "combine": false}]';

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertCount(1, $response_body);
    $this->assertEquals($response_body[0]['order_item_id'], 2);
    $this->assertEquals($response_body[0]['quantity'], 2);
    $this->assertEquals($response_body[0]['unit_price']['number'], 1000);
    $this->assertEquals($response_body[0]['unit_price']['currency_code'], 'USD');
    $this->assertEquals($response_body[0]['total_price']['number'], 2000);
    $this->assertEquals($response_body[0]['total_price']['currency_code'], 'USD');
  }

}
