<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Entity\Coupon;
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
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_coupons';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->setUpAuthorization('GET');
    $this->setUpAuthorization('PATCH');
    $this->setUpAuthorization('DELETE');
  }

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

    // Add a cart that does belong to the account.
    $cart = $this->container->get('commerce_cart.cart_provider')->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    // Add order item to the cart.
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->assertEquals(count($cart->getItems()), 1);

    $url = Url::fromUri("base:cart/{$cart->id()}/coupons");
    $url->setOption('query', ['_format' => static::$format]);
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;
    $request_options[RequestOptions::BODY] = sprintf('{ "coupon_code": "%s"}', $coupon->getCode());
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals([$coupon->id()], $response_body['coupons']);

    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $order_storage->resetCache();
    $cart = $order_storage->load($cart->id());
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
    $url = Url::fromUri("base:cart/{$cart->id()}/coupons");
    $url->setOption('query', ['_format' => static::$format]);
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;
    $request_options[RequestOptions::BODY] = sprintf('{ "coupon_code": "%s"}', '12232');
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, '12232 is not a valid coupon code.', $response);

    // Test the disabled coupon.
    $url = Url::fromUri("base:cart/{$cart->id()}/coupons");
    $url->setOption('query', ['_format' => static::$format]);
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;
    $request_options[RequestOptions::BODY] = sprintf('{ "coupon_code": "%s"}', $coupon->getCode());
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(422, 'INVALID_COUPON_CODE is not a valid coupon code.', $response);
  }

}
