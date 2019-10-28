<?php

namespace Drupal\Tests\commerce_cart_api\Functional\jsonapi;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use GuzzleHttp\RequestOptions;

/**
 * Tests the cart add resource.
 *
 * @group commerce_cart_api
 */
class TranslationCartAddResourceTest extends CartResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'commerce_cart_add';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Add a new language.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Enable translation for the product and ensure the change is picked up.
    $this->container->get('content_translation.manager')->setEnabled('commerce_product', $this->variation->bundle(), TRUE);
    $this->container->get('content_translation.manager')->setEnabled('commerce_product_variation', $this->variation->bundle(), TRUE);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('router.builder')->rebuild();

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Reload the variation since we have new fields.
    $this->variation = ProductVariation::load($this->variation->id());
    $this->variation2 = ProductVariation::load($this->variation2->id());

    // Translate the product's title.
    $product = $this->variation->getProduct();
    $product->setTitle('My Super Product');
    $product->addTranslation('fr', [
      'title' => 'Mon super produit',
    ]);
    $product->save();

    // Create a translation for each variation on the product.
    $this->variation->addTranslation('fr')->save();
    $this->variation->save();
    $this->variation2->addTranslation('fr')->save();
    $this->variation2->save();
  }

  /**
   * Asserts the proper translation was used when langcode path prefix missing.
   */
  public function testPurchasedEntityAdded() {
    $url = Url::fromRoute('commerce_cart_api.jsonapi.cart_add');
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    // Add item when no cart exists.
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'commerce_product_variation--default',
          'id' => $this->variation->uuid(),
          'meta' => [
            'orderQuantity' => 1,
          ],
        ],
      ],
    ]);

    $response = $this->request('POST', $url, $request_options);
    $this->assertResponseCode(200,$response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body['data']), 1);
    $this->assertEquals(1, $response_body['data'][0]['attributes']['drupal_internal__order_item_id']);
    $this->assertEquals($this->variation->uuid(), $response_body['data'][0]['relationships']['purchased_entity']['data']['id']);
    $this->assertEquals('My Super Product', $response_body['data'][0]['attributes']['title']);
    $this->assertEquals(1, $response_body['data'][0]['attributes']['quantity']);
    $this->assertEquals(1000, $response_body['data'][0]['attributes']['unit_price']['number']);
    $this->assertEquals('USD', $response_body['data'][0]['attributes']['unit_price']['currency_code']);
    $this->assertEquals(1000, $response_body['data'][0]['attributes']['total_price']['number']);
    $this->assertEquals('USD', $response_body['data'][0]['attributes']['total_price']['currency_code']);
  }

  /**
   * Asserts the proper translation used based on langcode path prefix.
   */
  public function testProperTranslatedPurchasedEntityAdded() {
    $url = Url::fromUri('base:fr/jsonapi/cart/add');
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    // Add item when no cart exists.
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'commerce_product_variation--default',
          'id' => $this->variation->uuid(),
          'meta' => [
            'orderQuantity' => 1,
          ],
        ],
      ],
    ]);

    $response = $this->request('POST', $url, $request_options);
    $this->assertResponseCode(200, $response);

    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body['data']), 1);
    $this->assertEquals(1, $response_body['data'][0]['attributes']['drupal_internal__order_item_id']);
    $this->assertEquals($this->variation->uuid(), $response_body['data'][0]['relationships']['purchased_entity']['data']['id']);
    $this->assertEquals('Mon super produit', $response_body['data'][0]['attributes']['title']);
    $this->assertEquals(1, $response_body['data'][0]['attributes']['quantity']);
    $this->assertEquals(1000, $response_body['data'][0]['attributes']['unit_price']['number']);
    $this->assertEquals('USD', $response_body['data'][0]['attributes']['unit_price']['currency_code']);
    $this->assertEquals(1000, $response_body['data'][0]['attributes']['total_price']['number']);
    $this->assertEquals('USD', $response_body['data'][0]['attributes']['total_price']['currency_code']);
  }

}
