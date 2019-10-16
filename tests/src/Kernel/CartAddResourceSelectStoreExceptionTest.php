<?php

namespace Drupal\Tests\commerce_cart_api\Kernel;

use Drupal\commerce_cart_api\Controller\CartResourceController;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @group commerce_cart_api
 */
final class CartAddResourceSelectStoreExceptionTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'jsonapi',
    'jsonapi_resources',
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_order',
    'path',
    'commerce_product',
    'commerce_cart',
    'commerce_cart_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    EntityFormMode::create([
      'id' => 'commerce_order_item.add_to_cart',
      'label' => 'Add to cart',
      'targetEntityType' => 'commerce_order_item',
    ])->save();
    $this->installConfig([
      'commerce_product',
      'commerce_order',
    ]);
  }

  /**
   * Tests exception when product has no stores.
   */
  public function testNoStoresException() {
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create([
      'type' => 'default',
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'type' => 'default',
    ]);
    $product_variation->save();
    $product->addVariation($product_variation);
    $product->save();

    $request = $this->prophesize(Request::class);
    $request->getContent()->willReturn(Json::encode([
      'data' => [
        [
          'type' => 'commerce_product_variation--default',
          'id' => $product_variation->uuid(),
          'meta' => [
            'orderQuantity' => 1,
          ],
        ],
      ],
    ]));

    $this->setExpectedException(UnprocessableEntityHttpException::class, 'The given entity is not assigned to any store.');

    $controller = $this->getController();
    $controller->addItems($request->reveal());
  }

  /**
   * Tests exception when product's stores is not a current store.
   */
  public function testNotCurrentStoreException() {
    $additional_store1 = $this->createStore();
    $addiitonal_store2 = $this->createStore();
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create([
      'type' => 'default',
      'stores' => [$addiitonal_store2->id(), $additional_store1->id()],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'type' => 'default',
    ]);
    $product_variation->save();
    $product->addVariation($product_variation);
    $product->save();

    $request = $this->prophesize(Request::class);
    $request->getContent()->willReturn(Json::encode([
      'data' => [
        [
          'type' => 'commerce_product_variation--default',
          'id' => $product_variation->uuid(),
          'meta' => [
            'orderQuantity' => 1,
          ],
        ],
      ],
    ]));

    $this->setExpectedException(UnprocessableEntityHttpException::class, "The given entity can't be purchased from the current store.");

    $controller = $this->getController();
    $controller->addItems($request->reveal());
  }

  /**
   * Gets the controller to test.
   *
   * @return \Drupal\commerce_cart_api\Controller\CartResourceController
   *   The controller.
   */
  protected function getController() {
    return new CartResourceController(
      $this->container->get('jsonapi_resource.resource_response_factory'),
      $this->container->get('jsonapi.resource_type.repository'),
      $this->container->get('entity_type.manager'),
      $this->container->get('commerce_cart.cart_provider'),
      $this->container->get('commerce_cart.cart_manager'),
      $this->container->get('commerce_cart_api.jsonapi_controller_shim')
    );
  }

}
