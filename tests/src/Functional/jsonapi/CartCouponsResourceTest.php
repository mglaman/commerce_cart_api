<?php

namespace Drupal\Tests\commerce_cart_api\Functional\jsonapi;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Tests the cart coupons resource.
 *
 * @group commerce_cart_api
 */
class CartCouponsResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_promotion',
  ];

  /**
   * Tests applying a valid coupon.
   */
  public function testApplyCoupons() {
    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'usage_limit' => 1,
      'start_date' => '2017-01-01',
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_item_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.5',
        ],
      ],
    ]);
    $promotion->save();

    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'PERCENTAGE_OFF',
      'usage_limit' => 1,
      'status' => TRUE,
    ]);
    $coupon->save();
    assert($coupon instanceof CouponInterface);

    // Add a cart that does belong to the account.
    $cart = $this->container->get('commerce_cart.cart_provider')->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    // Add order item to the cart.
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->assertEquals(count($cart->getItems()), 1);

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_coupon_add', [
      'commerce_order' => $cart->uuid()
    ]);
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'commerce_promotion_coupon--commerce_promotion_coupon',
          'id' => $coupon->getCode(),
        ]
      ]
    ]);

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResponseCode(200, $response);

    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $order_storage->resetCache();
    $cart = $order_storage->load($cart->id());
    assert($cart instanceof OrderInterface);
    $this->assertFalse($cart->get('coupons')->isEmpty());
  }

  /**
   * Tests applying an invalid coupon.
   */
  public function testInvalidCoupons() {
    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'usage_limit' => 1,
      'start_date' => '2017-01-01',
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_item_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.5',
        ],
      ],
    ]);
    $promotion->save();
    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'INVALID_COUPON_CODE',
      'usage_limit' => 1,
      'status' => FALSE,
    ]);
    $coupon->save();

    $cart = $this->container->get('commerce_cart.cart_provider')->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    // Add order item to the cart.
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->assertEquals(count($cart->getItems()), 1);

    // Test an invalid coupon.
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_coupon_add', [
      'commerce_order' => $cart->uuid()
    ]);
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'commerce_promotion_coupon--commerce_promotion_coupon',
          'id' => '12232',
        ]
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
          'detail' => '12232 is not a valid coupon code.',
          'links' => [
            'via' => [
              'href' => $url->setAbsolute()->toString(),
            ]
          ]
        ]
      ],
    ], Json::decode((string) $response->getBody()));

    // Test the disabled coupon.
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'commerce_promotion_coupon--commerce_promotion_coupon',
          'id' => $coupon->getCode(),
        ]
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
          'detail' => 'INVALID_COUPON_CODE is not a valid coupon code.',
          'links' => [
            'via' => [
              'href' => $url->setAbsolute()->toString(),
            ]
          ]
        ]
      ],
    ], Json::decode((string) $response->getBody()));
  }

}
