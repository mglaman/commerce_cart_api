<?php

namespace Drupal\Tests\commerce_cart_api\Functional\jsonapi;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Tests cart api access check.
 *
 * @group commerce_cart_api
 */
class CartAccessApiResourceTest extends CartResourceTestBase {

  /**
   * Check access for route with no parameters (cart collection).
   */
  public function testNoParameters() {
    $request_options = $this->getAuthenticationRequestOptions();
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_collection');

    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResponseCode(200, $response);
  }

  /**
   * Check no access for missing cart (cart canonical).
   */
  public function testNoCart() {
    $request_options = $this->getAuthenticationRequestOptions();

    // Request for cart that does not exist.
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_canonical', [
      'commerce_order' => 99,
    ]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResponseCode(404, $response);
  }

  /**
   * Check no access for non-draft/non-cart cart.
   */
  public function testInvalidCart() {
    $request_options = $this->getAuthenticationRequestOptions();

    // Create non-draft cart.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->assertInstanceOf(OrderInterface::class, $cart);
    $transition = $cart->getState()->getWorkflow()->getTransition('place');
    $cart->getState()->applyTransition($transition);
    $this->assertEquals($cart->getState()->getLabel(), 'Completed');
    $cart->save();
    $cart = Order::load($cart->id());

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_canonical', [
      'commerce_order' => $cart->uuid(),
    ]);
    $response = $this->request('GET', $url, $request_options);
    $this->assertResponseCode(403, $response);

    // Create non-cart order.
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->account->getEmail(),
      'uid' => $this->account->id(),
      'store_id' => $this->store->id(),
      'state' => 'draft',
    ]);
    $this->assertInstanceOf(OrderInterface::class, $order);

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_canonical', [
      'commerce_order' => $order->uuid(),
    ]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResponseCode(403, $response);
  }

  /**
   * Check no access for cart not belonging to user (cart canonical).
   */
  public function testNotUsersCart() {
    $request_options = $this->getAuthenticationRequestOptions('GET');

    $cart = $this->cartProvider->createCart('default', $this->store, $this->createUser());
    $this->cartManager->addEntity($cart, $this->variation, 2);

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_canonical', [
      'commerce_order' => $cart->uuid(),
    ]);

    $response = $this->request('GET', $url, $request_options);
    $this->assertResponseCode(403, $response);
  }

  /**
   * Check no access for order item not in cart (cart update item);
   */
  public function testInvalidOrderItemCart() {
    $request_options = $this->getAuthenticationRequestOptions();

    // Create a cart with an order item.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $this->cartManager->addEntity($cart, $this->variation, 2);

    // Create order item in another cart.
    $another_cart = $this->cartProvider->createCart('default', $this->store, $this->createUser());
    $other_order_item = $this->cartManager->addEntity($another_cart, $this->variation, 2);

    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_update_item', [
      'commerce_order' => $cart->uuid(),
      'commerce_order_item' => $other_order_item->uuid()
    ]);
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        'type' => 'commerce_order_item--default',
        'id' => $other_order_item->uuid(),
        'attributes' => [
          'quantity' => 10
        ],
      ]
    ]);
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResponseCode(403, $response);
  }

  /**
   * Returns Guzzle request options for authentication.
   *
   * @return array
   *   Guzzle request options to use for authentication.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getAuthenticationRequestOptions() {
    return [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($this->account->name->value . ':' . $this->account->passRaw),
      ],
    ];
  }

}
