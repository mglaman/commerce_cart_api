<?php

namespace Drupal\Tests\commerce_cart_api\Functional;

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
    $this->setUpAuthorization('POST');

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
    $this->variation_2 = ProductVariation::load($this->variation_2->id());

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
    $this->variation_2->addTranslation('fr')->save();
    $this->variation_2->save();
  }

  /**
   * Asserts the proper translation was used when langcode path prefix missing.
   */
  public function testPurchasedEntityAdded() {
    $url = Url::fromUri('base:cart/add');
    $url->setOption('query', ['_format' => static::$format]);

    $request_options = $this->getAuthenticationRequestOptions('POST');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Add item when no cart exists.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "purchased_entity_id": "1", "quantity": "1"}]';

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body), 1);
    $this->assertEquals(count($response_body), 1);
    $this->assertEquals(1, $response_body[0]['order_item_id']);
    $this->assertEquals(1, $response_body[0]['purchased_entity']['variation_id']);
    $this->assertEquals('My Super Product', $response_body[0]['purchased_entity']['title']);
    $this->assertEquals(1, $response_body[0]['quantity']);
    $this->assertEquals(1000, $response_body[0]['unit_price']['number']);
    $this->assertEquals('USD', $response_body[0]['unit_price']['currency_code']);
    $this->assertEquals(1000, $response_body[0]['total_price']['number']);
    $this->assertEquals('USD', $response_body[0]['total_price']['currency_code']);
  }

  /**
   * Asserts the proper translation used based on langcode path prefix.
   */
  public function testProperTranslatedPurchasedEntityAdded() {
    $url = Url::fromUri('base:fr/cart/add');
    $url->setOption('query', ['_format' => static::$format]);

    $request_options = $this->getAuthenticationRequestOptions('POST');
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // Add item when no cart exists.
    $request_options[RequestOptions::BODY] = '[{ "purchased_entity_type": "commerce_product_variation", "purchased_entity_id": "1", "quantity": "1"}]';

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);
    $response_body = Json::decode((string) $response->getBody());
    $this->assertEquals(count($response_body), 1);
    $this->assertEquals(count($response_body), 1);
    $this->assertEquals(1, $response_body[0]['order_item_id']);
    $this->assertEquals(1, $response_body[0]['purchased_entity']['variation_id']);
    $this->assertEquals('Mon super produit', $response_body[0]['purchased_entity']['title']);
    $this->assertEquals(1, $response_body[0]['quantity']);
    $this->assertEquals(1000, $response_body[0]['unit_price']['number']);
    $this->assertEquals('USD', $response_body[0]['unit_price']['currency_code']);
    $this->assertEquals(1000, $response_body[0]['total_price']['number']);
    $this->assertEquals('USD', $response_body[0]['total_price']['currency_code']);
  }

}
