<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Tests cart api access check.
 *
 * @group commerce_cart_api
 */
class CartAccessApiResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_canonical';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Parent will provision resource for canonical; need others here.
    $auth = isset(static::$auth) ? [static::$auth] : [];

    self::$resourceConfigId = 'commerce_cart_collection';
    $this->provisionResource([static::$format], $auth);
    self::$resourceConfigId = 'commerce_cart_update_item';
    $this->provisionResource([static::$format], $auth);

    $this->initAuthentication();
    $this->setUpAuthorization('GET');
    $this->setUpAuthorization('PATCH');
  }

  /**
   * Check access for route with no parameters (cart collection).
   */
  public function testNoParameters() {
    $request_options = $this->getAuthenticationRequestOptions('GET');

    $url = Url::fromUri('base:cart');
    $url->setOption('query', ['_format' => static::$format]);

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response, ['commerce_order:1', 'config:rest.resource.commerce_cart_collection', 'config:rest.settings', 'http_response'], ['cart', 'store'], FALSE, 'MISS');
  }

  /**
   * Check no access for missing cart (cart canonical).
   */
  public function testNoCart() {
    $request_options = $this->getAuthenticationRequestOptions('GET');

    // Request for cart that does not exist.
    $url = Url::fromUri('base:cart/99');
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceErrorResponse(404, 'The "commerce_order" parameter was not converted for the path "/cart/{commerce_order}" (route name: "rest.commerce_cart_canonical.GET")', $response);
  }

  /**
   * Check no access for non-draft/non-cart cart.
   */
  public function testInvalidCart() {
    $request_options = $this->getAuthenticationRequestOptions('GET');

    // Create non-draft cart.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    $transition = $cart->getState()->getWorkflow()->getTransition('place');
    $cart->getState()->applyTransition($transition);
    $this->assertEquals($cart->getState()->getLabel(), 'Completed');
    $cart->save();
    $cart = Order::load($cart->id());

    $url = Url::fromUri('base:cart/' . $cart->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceErrorResponse(403, "", $response, ['4xx-response', 'commerce_order:1', 'http_response'], [''], FALSE);

    // Create non-cart order.
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->account->getEmail(),
      'uid' => $this->account->id(),
      'store_id' => $this->store->id(),
      'state' => 'draft',
    ]);
    $this->assertInstanceOf(OrderInterface::class, $order);

    $url = Url::fromUri('base:cart/' . $cart->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceErrorResponse(403, "", $response, ['4xx-response', 'commerce_order:1', 'http_response'], [''], FALSE);
  }

  /**
   * Check no access for cart not belonging to user (cart canonical).
   */
  public function testNotUsersCart() {
    $request_options = $this->getAuthenticationRequestOptions('GET');

    $cart = $this->cartProvider->createCart('default', $this->store);
    $this->cartManager->addEntity($cart, $this->variation, 2);

    $url = Url::fromUri('base:cart/' . $cart->id());
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResourceErrorResponse(403, "", $response, ['4xx-response', 'commerce_order:1', 'http_response'], [''], FALSE);
  }

  /**
   * Check no access for order item not in cart (cart update item);
   */
  public function testInvalidOrderItemCart() {
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Create a cart with an order item.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 2);

    $url = Url::fromUri('base:cart/' . $cart->id() . '/items/2');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options[RequestOptions::BODY] = '{"quantity":"1"}';

    // Create order item in another cart.
    $another_cart = $this->cartProvider->createCart('default', $this->store);
    $this->cartManager->addEntity($another_cart, $this->variation, 2);

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(403, '', $response);
  }

}
