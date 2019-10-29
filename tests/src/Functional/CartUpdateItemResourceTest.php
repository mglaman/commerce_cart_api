<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use GuzzleHttp\RequestOptions;

/**
 * @group commerce_cart_api
 */
final class CartUpdateItemResourceTest extends CartResourceTestBase {

  /**
   * Tests patch when cart does not exist.
   */
  public function testMissingCart() {
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    $uuid_generator = $this->container->get('uuid');
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_update_item', [
      'commerce_order' => $uuid_generator->generate(),
      'commerce_order_item' => $uuid_generator->generate(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        'type' => 'commerce_order_item--default',
        'id' => $uuid_generator->generate(),
        'attributes' => [
          'quantity' => 5,
        ],
      ]
    ]);

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertSame(404, $response->getStatusCode(), (string) $response->getBody());
  }

  /**
   * Tests patch when order item does not exist in cart.
   */
  public function testMissingOrderItem() {
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $order_item = $this->cartManager->addEntity($cart, $this->variation, 2);

    $non_existent_order_item_uuid = $this->container->get('uuid')->generate();
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_update_item', [
      'commerce_order' => $cart->uuid(),
      'commerce_order_item' => $non_existent_order_item_uuid,
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        'type' => $order_item->getEntityTypeId() . '--' . $order_item->bundle(),
        'id' => $non_existent_order_item_uuid,
        'attributes' => [
          'quantity' => 5,
        ],
      ]
    ]);

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertSame(404, $response->getStatusCode(), (string) $response->getBody());

    // Create order item in another cart.
    $another_cart = $this->cartProvider->createCart('default', $this->store, $this->createUser());
    $order_item = $this->cartManager->addEntity($another_cart, $this->variation, 2);

    // New order item should still be unavailable for patch.
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_update_item', [
      'commerce_order' => $cart->uuid(),
      'commerce_order_item' => $order_item->uuid(),
    ]);
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertSame(403, $response->getStatusCode(), (string) $response->getBody());
    $this->assertEquals([
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'errors' => [
        [
          'title' => 'Forbidden',
          'status' => '403',
          'detail' => 'Order item does not belong to this order.',
          'links' => [
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(403)],
            'via' => ['href' => $url->setAbsolute()->toString()],
          ],
        ]
      ],
    ], Json::decode((string) $response->getBody()));
  }

  /**
   * Tests cart that does not belong to the account.
   */
  public function testCartNoAccess() {
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    // Cart that does not belong to the account.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->createUser());
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->assertEquals(count($cart->getItems()), 1);
    $items = $cart->getItems();
    $order_item = $items[0];

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_update_item', [
      'commerce_order' => $cart->uuid(),
      'commerce_order_item' => $order_item->uuid(),
    ]);

    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        'type' => $order_item->getEntityTypeId() . '--' . $order_item->bundle(),
        'id' => $order_item->uuid(),
        'attributes' => [
          'quantity' => 5,
        ],
      ]
    ]);
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertSame(403, $response->getStatusCode(), (string) $response->getBody());
  }

  /**
   * Tests malformed payloads.
   */
  public function testInvalidPayload() {
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->assertEquals(count($cart->getItems()), 1);
    $items = $cart->getItems();
    $order_item = $items[0];

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_update_item', [
      'commerce_order' => $cart->uuid(),
      'commerce_order_item' => $order_item->uuid(),
    ]);

    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        'type' => $order_item->getEntityTypeId() . '--' . $order_item->bundle(),
        'id' => $order_item->uuid(),
        'attributes' => [
          'quantity' => 5,
          'title' => 'Test title',
        ],
      ]
    ]);
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResponseCode(403, $response);
    $this->assertEquals([
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'errors' => [
        [
          'title' => 'Forbidden',
          'status' => '403',
          'detail' => 'The current user is not allowed to PATCH the selected field (title).',
          'links' => [
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(403)],
            'via' => ['href' => Url::fromRoute('jsonapi.commerce_order_item--default.individual', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
          ],
          'source' => [
            'pointer' => '/data/attributes/title',
          ],
        ]
      ],
    ], Json::decode((string) $response->getBody()));

    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        'type' => $order_item->getEntityTypeId() . '--' . $order_item->bundle(),
        'id' => $order_item->uuid(),
        'attributes' => [
          'quantity' => -1,
        ],
      ]
    ]);
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResponseCode(422, $response);
    $this->assertEquals([
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'errors' => [
        [
          'title' => 'Unprocessable Entity',
          'status' => '422',
          'detail' => 'quantity.0.value: Quantity: the value may be no less than 0.',
          'source' => [
            'pointer' => '/data/attributes/quantity/value'
          ],
        ]
      ],
    ], Json::decode((string) $response->getBody()));
  }

  /**
   * Patch an order item for a session's cart via the REST API.
   */
  public function testPatchOrderItem() {
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->cartManager->addEntity($cart, $this->variation2, 5);
    $this->assertEquals(count($cart->getItems()), 2);
    list($order_item, $order_item_2) = $cart->getItems();
    $this->assertEquals($order_item->getQuantity(), 2);
    $this->assertEquals($order_item_2->getQuantity(), 5);

    // Patch quantity for second order item.
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_update_item', [
      'commerce_order' => $cart->uuid(),
      'commerce_order_item' => $order_item_2->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        'type' => $order_item_2->getEntityTypeId() . '--' . $order_item_2->bundle(),
        'id' => $order_item_2->uuid(),
        'attributes' => [
          'quantity' => 1,
        ],
      ],
    ]);
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResponseCode(200, $response);

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
    assert($cart instanceof OrderInterface);
    $this->assertEquals($cart->getTotalPrice()->getNumber(), 2500);

    // Verify json response.
    $this->assertEquals([
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'data' => [
        'type' => 'commerce_order_item--default',
        'id' => $order_item_2->uuid(),
        'links' => [
          'self' => [
            'href' => Url::fromRoute('jsonapi.commerce_order_item--default.individual', ['entity' => $order_item_2->uuid()])->setAbsolute()->toString(),
          ],
        ],
        'attributes' => [
          'drupal_internal__order_item_id' => $order_item_2->id(),
          'title' => $order_item_2->label(),
          'unit_price' => [
            'number' => '500',
            'currency_code' => 'USD',
            'formatted' => '$500.00',
          ],
          'quantity' => '1',
          'total_price' => [
            'number' => '500',
            'currency_code' => 'USD',
            'formatted' => '$500.00',
          ],
        ],
        'relationships' => [
          'order_id' => [
            'data' => [
              'type' => 'commerce_order--default',
              'id' => $cart->uuid(),
            ],
            'links' => [
              'related' => [
                'href' => Url::fromRoute('jsonapi.commerce_order_item--default.order_id.related', ['entity' => $order_item_2->uuid()])->setAbsolute()->toString(),
              ],
              'self' => [
                'href' => Url::fromRoute('jsonapi.commerce_order_item--default.order_id.relationship.get', ['entity' => $order_item_2->uuid()])->setAbsolute()->toString(),
              ],
            ],
          ],
          'purchased_entity' => [
            'data' => [
              'type' => 'commerce_product_variation--default',
              'id' => $this->variation2->uuid(),
            ],
            'links' => [
              'related' => [
                'href' => Url::fromRoute('jsonapi.commerce_order_item--default.purchased_entity.related', ['entity' => $order_item_2->uuid()])->setAbsolute()->toString(),
              ],
              'self' => [
                'href' => Url::fromRoute('jsonapi.commerce_order_item--default.purchased_entity.relationship.get', ['entity' => $order_item_2->uuid()])->setAbsolute()->toString(),
              ],
            ],
          ],
        ],
      ],
      'links' => [
        'self' => [
          'href' => $url->setAbsolute()->toString(),
        ],
      ],
    ], Json::decode((string) $response->getBody()));
  }

}
