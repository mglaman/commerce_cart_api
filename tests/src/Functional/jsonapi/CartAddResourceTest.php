<?php

namespace Drupal\Tests\commerce_cart_api\Functional\jsonapi;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * @group commerce_cart_api
 */
final class CartAddResourceTest extends CartResourceTestBase {

  /**
   * Test add to cart.
   */
  public function testCartAdd() {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => $this->variation->getEntityTypeId() . '--' . $this->variation->bundle(),
          'id' => $this->variation->uuid(),
          'meta' => [
            'quantity' => 1,
          ]
        ],
      ],
    ]);
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_add');
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));

    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $cart = $order_storage->load(1);
    assert($cart instanceof OrderInterface);
    $this->assertEquals(count($cart->getItems()), 1);
    $order_item = $cart->getItems()[0];

    $this->assertEquals([
      'data' => [
        [
          'type' => 'commerce_order_item--default',
          'id' => $order_item->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_order_item--default.individual', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__order_item_id' => (int) $order_item->id(),
            'title' => $order_item->label(),
            'quantity' => (int) $order_item->getQuantity(),
            'unit_price' => [
              'number' => '1000.0',
              'currency_code' => 'USD',
              'formatted' => '$1,000.00'
            ],
            'total_price' => [
              'number' => '1000.00',
              'currency_code' => 'USD',
              'formatted' => '$1,000.00'
            ],
          ],
          'relationships' => [
            'order_id' => [
              'data' => [
                'type' => 'commerce_order--default',
                'id' => $cart->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_order_item--default.order_id.relationship.get', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_order_item--default.order_id.related', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'purchased_entity' => [
              'data' => [
                'type' => 'commerce_product_variation--default',
                'id' => $this->variation->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_order_item--default.purchased_entity.relationship.get', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_order_item--default.purchased_entity.related', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
      ],
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'links' => [
        'self' => ['href' => $url->setAbsolute()->toString()],
      ],
    ], Json::decode((string) $response->getBody()));
  }

}
