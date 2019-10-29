<?php declare(strict_types=1);

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;

/**
 * @group commerce_cart_api
 */
final class CartClearResourceTest extends CartResourceTestBase {

  /**
   * Tests clearing a cart.
   */
  public function testCartClear() {
    // Create a cart for the current user.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 5);

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_collection');
    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
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
            'drupal_internal__order_id' => (int) $cart->id(),
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

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_clear', ['commerce_order' => $cart->uuid()]);
    $response = $this->request('DELETE', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(204, $response->getStatusCode(), (string) $response->getBody());

    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $order_storage->resetCache([$cart->id()]);
    $cart = $order_storage->load($cart->id());
    $this->assertEquals(count($cart->getItems()), 0);

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_collection', [], [
      // 'query' => ['include' => 'order_items,order_items.purchased_entity'],
    ]);
    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));

    $this->assertTrue($response->hasHeader('X-Drupal-Dynamic-Cache'));
    $this->assertSame('UNCACHEABLE', $response->getHeader('X-Drupal-Dynamic-Cache')[0]);
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

}
