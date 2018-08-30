<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Url;

/**
 * Tests the cart clear resource.
 *
 * @group commerce_cart_api
 */
class CartClearResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_clear';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->setUpAuthorization('DELETE');
  }

  /**
   * Removes all items from a cart.
   */
  public function testClearCart() {
    $request_options = $this->getAuthenticationRequestOptions('DELETE');

    // Failed request to clear cart that doesn't belong to the account.
    $not_my_cart = $this->cartProvider->createCart('default', $this->store);
    $this->assertInstanceOf(OrderInterface::class, $not_my_cart);
    $this->cartManager->addEntity($not_my_cart, $this->variation, 2);
    $this->assertEquals(count($not_my_cart->getItems()), 1);

    $url = Url::fromUri('base:cart/' . $not_my_cart->id() . '/items');
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResourceErrorResponse(403, '', $response);

    // Add a cart that does belong to the account.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    $this->cartManager->addEntity($cart, $this->variation, 2);
    $this->cartManager->addEntity($cart, $this->variation_2, 5);
    $this->assertEquals(count($cart->getItems()), 2);

    $url = Url::fromUri('base:cart/' . $cart->id() . '/items');
    $url->setOption('query', ['_format' => static::$format]);

    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResourceErrorResponse(204, '', $response);

    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$cart->id()]);
    $cart = Order::load($cart->id());

    $this->assertEquals(count($cart->getItems()), 0);
  }

}
