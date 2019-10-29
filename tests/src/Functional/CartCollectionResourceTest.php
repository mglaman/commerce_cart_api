<?php declare(strict_types=1);

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * @group commerce_cart_api
 */
final class CartCollectionResourceTest extends CartResourceTestBase {
  /**
   * Test cart collection.
   */
  public function testCartCollection() {
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_collection', []);

    // Create a cart for another user.
    $this->cartProvider->createCart('default', $this->store, User::getAnonymousUser());

    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
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

    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
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
            'drupal_internal__order_id' => (int) $cart->id(),
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
            'drupal_internal__order_item_id' => (int) $order_item->id(),
            'title' => $order_item->label(),
            'quantity' => (int) $order_item->getQuantity(),
            'unit_price' => [
              'number' => '1000.0',
              'currency_code' => 'USD',
              'formatted' => '$1,000.00'
            ],
            'total_price' => [
              'number' => '5000.0',
              'currency_code' => 'USD',
              'formatted' => '$5,000.00',
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
        [
          'type' => 'commerce_product_variation--default',
          'id' => $this->variation->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.commerce_product_variation--default.individual', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'drupal_internal__variation_id' => (int) $this->variation->id(),
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
}
