<?php

namespace Drupal\Tests\commerce_cart_api\Functional\jsonapi;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use Drupal\user\Entity\User;
use GuzzleHttp\RequestOptions;

final class CartResourceControllerTest extends CartResourceTestBase {

  protected function setUp() {
    parent::setUp();
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);
  }

  /**
   * Test cart collection.
   */
  public function testCartCollection() {
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_collection', [], [
      // 'query' => ['include' => 'order_items,order_items.purchased_entity'],
    ]);

    // Create a cart for another user.
    $this->cartProvider->createCart('default', $this->store, User::getAnonymousUser());

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));
    // There should be no body as the cart does not belong to the session.
    $this->assertEquals([
      'data' => [],
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

    // Create a cart for the current user.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $order_item = $this->cartManager->addEntity($cart, $this->variation, 5);

    $product_variation_type = ProductVariationType::load('default');

    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));
    // There should be no body as the cart does not belong to the session.
    $this->assertEquals([
      'data' => [
        [
          'type' => 'commerce_order--default',
          'id' => $cart->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.individual', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__order_id' => $cart->id(),
            'order_number' => NULL,
            'total_price' => [
              'number' => '5000.0',
              'currency_code' => 'USD',
              'formatted' => '$5,000.00',
            ],
          ],
          'relationships' => [
            'store_id' => [
              'data' => [
                'type' => 'commerce_store--online',
                'id' => $this->store->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.store_id.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.store_id.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'order_items' => [
              'data' => [
                [
                  'type' => 'commerce_order_item--default',
                  'id' => $order_item->uuid(),
                ],
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.order_items.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.order_items.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
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
      'included' => [
        [
          'type' => 'commerce_order_item--default',
          'id' => $order_item->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_order_item--default.individual', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__order_item_id' => $order_item->id(),
            'title' => $order_item->label(),
            'quantity' => $order_item->getQuantity(),
            'unit_price' => $order_item->get('unit_price')->first()->getValue() + ['formatted' => '$1,000.00'],
            'total_price' => $order_item->get('total_price')->first()->getValue() + ['formatted' => '$5,000.00'],
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
        [
          'type' => 'commerce_product_variation--default',
          'id' => $this->variation->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.individual', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__variation_id' => $this->variation->id(),
            'sku' => $this->variation->getSku(),
            'title' => $this->variation->label(),
            'list_price' => NULL,
            'price' => $this->variation->get('price')->first()->getValue() + ['formatted' => '$1,000.00'],
          ],
          'relationships' => [
            'commerce_product_variation_type' => [
              'data' => [
                'type' => 'commerce_product_variation_type--commerce_product_variation_type',
                'id' => $product_variation_type->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.commerce_product_variation_type.relationship.get', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.commerce_product_variation_type.related', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'product_id' => [
              'data' => [
                'type' => 'commerce_product--default',
                'id' => $this->variation->getProduct()->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.product_id.relationship.get', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.product_id.related', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));
  }

  /**
   * Test cart canonical.
   */
  public function testCartCanonical() {
    // Create a cart for another user.
    $anon_cart = $this->cartProvider->createCart('default', $this->store, User::getAnonymousUser());

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_canonical', ['cart' => $anon_cart->uuid()], [
      'query' => ['include' => 'order_items,order_items.purchased_entity'],
    ]);

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));
    // There should be no body as the cart does not belong to the session.
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
          'status' => 403,
          'detail' => '',
          'links' => [
            'via' => ['href' => $url->setAbsolute()->toString()],
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(403)],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));

    // Create a cart for the current user.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $order_item = $this->cartManager->addEntity($cart, $this->variation, 5);

    $product_variation_type = ProductVariationType::load('default');

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_canonical', ['cart' => $cart->uuid()]);
    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));
    // There should be no body as the cart does not belong to the session.
    $this->assertEquals([
      'data' => [
        'type' => 'commerce_order--default',
        'id' => $cart->uuid(),
        'links' => [
          'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.individual', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
        ],
        'attributes' => [
          'drupal_internal__order_id' => $cart->id(),
          'order_number' => NULL,
          'total_price' => [
            'number' => '5000.0',
            'currency_code' => 'USD',
            'formatted' => '$5,000.00',
          ],
        ],
        'relationships' => [
          'store_id' => [
            'data' => [
              'type' => 'commerce_store--online',
              'id' => $this->store->uuid(),
            ],
            'links' => [
              'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.store_id.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              'related' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.store_id.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
            ],
          ],
          'order_items' => [
            'data' => [
              [
                'type' => 'commerce_order_item--default',
                'id' => $order_item->uuid(),
              ],
            ],
            'links' => [
              'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.order_items.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              'related' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.order_items.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
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
      'included' => [
        [
          'type' => 'commerce_order_item--default',
          'id' => $order_item->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_order_item--default.individual', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__order_item_id' => $order_item->id(),
            'title' => $order_item->label(),
            'quantity' => $order_item->getQuantity(),
            'unit_price' => $order_item->get('unit_price')->first()->getValue() + ['formatted' => '$1,000.00'],
            'total_price' => $order_item->get('total_price')->first()->getValue() + ['formatted' => '$5,000.00'],
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
        [
          'type' => 'commerce_product_variation--default',
          'id' => $this->variation->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.individual', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__variation_id' => $this->variation->id(),
            'sku' => $this->variation->getSku(),
            'title' => $this->variation->label(),
            'list_price' => NULL,
            'price' => $this->variation->get('price')->first()->getValue() + ['formatted' => '$1,000.00'],
          ],
          'relationships' => [
            'commerce_product_variation_type' => [
              'data' => [
                'type' => 'commerce_product_variation_type--commerce_product_variation_type',
                'id' => $product_variation_type->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.commerce_product_variation_type.relationship.get', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.commerce_product_variation_type.related', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'product_id' => [
              'data' => [
                'type' => 'commerce_product--default',
                'id' => $this->variation->getProduct()->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.product_id.relationship.get', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.product_id.related', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));
  }

  /**
   * Tests clearing a cart.
   */
  public function testCartClear() {
    // Create a cart for the current user.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 5);

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_collection', [], [
      // 'query' => ['include' => 'order_items,order_items.purchased_entity'],
    ]);
    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));

    $this->assertTrue($response->hasHeader('X-Drupal-Dynamic-Cache'));
    $this->assertSame('UNCACHEABLE', $response->getHeader('X-Drupal-Dynamic-Cache')[0]);
    $this->assertSame([
      'commerce_order:1',
      'commerce_order_item:1',
      'commerce_product:1',
      'commerce_product_variation:1',
      'commerce_product_variation_view',
      'http_response',
    ], explode(' ', $response->getHeader('X-Drupal-Cache-Tags')[0]));

    $this->assertNotEquals([
      'data' => [
        [
          'type' => 'commerce_order--default',
          'id' => $cart->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.individual', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__order_id' => $cart->id(),
            'order_number' => NULL,
            'total_price' => NULL,
          ],
          'relationships' => [
            'store_id' => [
              'data' => [
                'type' => 'commerce_store--online',
                'id' => $this->store->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.store_id.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.store_id.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'order_items' => [
              'data' => [],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.order_items.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.order_items.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
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

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_clear', ['cart' => $cart->uuid()]);
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertSame(204, $response->getStatusCode(), (string) $response->getBody());

    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $order_storage->resetCache([$cart->id()]);
    $cart = $order_storage->load($cart->id());
    $this->assertEquals(count($cart->getItems()), 0);

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_collection', [], [
      // 'query' => ['include' => 'order_items,order_items.purchased_entity'],
    ]);
    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));

    $this->assertTrue($response->hasHeader('X-Drupal-Dynamic-Cache'));
    $this->assertSame('MISS', $response->getHeader('X-Drupal-Dynamic-Cache')[0]);
    $this->assertSame(['commerce_order:1', 'http_response'], explode(' ', $response->getHeader('X-Drupal-Cache-Tags')[0]));
    $this->assertEquals([
      'data' => [
        [
          'type' => 'commerce_order--default',
          'id' => $cart->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.individual', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__order_id' => $cart->id(),
            'order_number' => NULL,
            'total_price' => NULL,
          ],
          'relationships' => [
            'store_id' => [
              'data' => [
                'type' => 'commerce_store--online',
                'id' => $this->store->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.store_id.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.store_id.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'order_items' => [
              'data' => [],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.order_items.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.commerce_order--default.order_items.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
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
            'orderQuantity' => 1,
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
            'drupal_internal__order_item_id' => $order_item->id(),
            'title' => $order_item->label(),
            'quantity' => $order_item->getQuantity(),
            'unit_price' => $order_item->get('unit_price')->first()->getValue() + ['formatted' => '$1,000.00'],
            'total_price' => $order_item->get('total_price')->first()->getValue() + ['formatted' => '$1,000.00'],
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
