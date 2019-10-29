<?php declare(strict_types=1);

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use Drupal\user\Entity\User;

/**
 * @group commerce_cart_api
 */
final class CartCanonicalResourceTest extends CartResourceTestBase {

  /**
   * Test cart canonical.
   */
  public function testCartCanonical() {
    // Create a cart for another user.
    $anon_cart = $this->cartProvider->createCart('default', $this->store, User::getAnonymousUser());

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_canonical', ['commerce_order' => $anon_cart->uuid()], [
      'query' => ['include' => 'order_items,order_items.purchased_entity'],
    ]);

    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
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
          'detail' => 'Order does not belong to the current user.',
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

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_canonical', ['commerce_order' => $cart->uuid()]);
    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
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
            'quantity' => (int) $order_item->getQuantity(),
            'unit_price' => $order_item->get('unit_price')->first()->getValue() + ['formatted' => '$1,000.00'],
            'total_price' => [
              'number' => '5000.0',
              'currency_code' => 'USD',
              'formatted' => '$5,000.00'
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

}
