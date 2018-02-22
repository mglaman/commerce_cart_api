<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\Core\Url;
use Drupal\Tests\commerce_cart\Functional\CartBrowserTestBase;
use GuzzleHttp\RequestOptions;

class CartCollectionResourceTest extends CartBrowserTestBase {

  public static $modules = [
    'commerce_cart_api',
  ];

  /**
   * The store entity.
   *
   * @var \Drupal\commerce_store\Entity\Store
   */
  protected $store2;

  /**
   * The cart order to test against.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $cart2;

  /**
   * The variation to test against.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariation
   */
  protected $variation2;

  protected function setUp() {
    parent::setUp();
    $this->store2 = $this->createStore(
      $this->randomMachineName(8),
      $this->randomString() . '@example.com',
      'online',
      FALSE
    );

    // Create a product variation.
    $this->variation2 = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'price' => [
        'number' => 999,
        'currency_code' => 'USD',
      ],
    ]);

    // We need a product too otherwise tests complain about the missing
    // backreference.
    $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store2],
      'variations' => [$this->variation2],
    ]);

    $cart_provider = $this->container->get('commerce_cart.cart_provider');

    $this->cartManager->addEntity($this->cart, $this->variation);
    $this->cart2 = $cart_provider->createCart('default', $this->store2, $this->loggedInUser);
    $this->cartManager->addEntity($this->cart2, $this->variation2);
    $this->assertCount(2, $cart_provider->getCarts($this->loggedInUser));
  }

  public function testAuthenticatedCartCollection() {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $url = Url::fromRoute('commerce_cart_api.cart_collection')->setRouteParameter('_format', 'json');
    $this->assertEquals('/cart?_format=json', $url->toString());
    $response = $this->request('GET', $url, $request_options);
    $body = $response->getBody()->getContents();
    $this->assertEquals(200, $response->getStatusCode(), $body);
    # @todo assert responses.
    $this->assertEquals('dd', $body, $body);
  }

  /**
   * Performs a HTTP request. Wraps the Guzzle HTTP client.
   *
   * Why wrap the Guzzle HTTP client? Because we want to keep the actual test
   * code as simple as possible, and hence not require them to specify the
   * 'http_errors = FALSE' request option, nor do we want them to have to
   * convert Drupal Url objects to strings.
   *
   * We also don't want to follow redirects automatically, to ensure these tests
   * are able to detect when redirects are added or removed.
   *
   * @param string $method
   *   HTTP method.
   * @param \Drupal\Core\Url $url
   *   URL to request.
   * @param array $request_options
   *   Request options to apply.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function request($method, Url $url, array $request_options) {
    $request_options[RequestOptions::HTTP_ERRORS] = FALSE;
    $request_options[RequestOptions::ALLOW_REDIRECTS] = FALSE;
    $request_options = $this->decorateWithXdebugCookie($request_options);
    $client = $this->getSession()->getDriver()->getClient()->getClient();
    return $client->request($method, $url->setAbsolute(TRUE)->toString(), $request_options);
  }

  /**
   * Adds the Xdebug cookie to the request options.
   *
   * @param array $request_options
   *   The request options.
   *
   * @return array
   *   Request options updated with the Xdebug cookie if present.
   */
  protected function decorateWithXdebugCookie(array $request_options) {
    $session = $this->getSession();
    $driver = $session->getDriver();
    if ($driver instanceof BrowserKitDriver) {
      $client = $driver->getClient();
      foreach ($client->getCookieJar()->all() as $cookie) {
        if (isset($request_options[RequestOptions::HEADERS]['Cookie'])) {
          $request_options[RequestOptions::HEADERS]['Cookie'] .= '; ' . $cookie->getName() . '=' . $cookie->getValue();
        }
        else {
          $request_options[RequestOptions::HEADERS]['Cookie'] = $cookie->getName() . '=' . $cookie->getValue();
        }
      }
    }
    return $request_options;
  }

}
