<?php

namespace Drupal\Tests\commerce_cart_api\Kernel;

use Drupal\commerce_cart_api\Resource\CartAddResource;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @group commerce_cart_api
 */
final class CartAddResourceTest extends CommerceKernelTestBase {

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
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
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
   * Tests exception when a non-purchasable entity provided.
   */
  public function testNonPurchasableEntityType() {
    $entity = EntityTest::create(['id' => 1, 'type' => 'entity_test']);
    $entity->save();

    $request = $this->prophesize(Request::class);
    $request->getContent()->willReturn(Json::encode([
      'data' => [
        [
          'type' => $entity->getEntityTypeId() . '--' . $entity->bundle(),
          'id' => $entity->uuid(),
          'meta' => [
            'orderQuantity' => 1,
          ],
        ],
      ],
    ]));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The provided type (entity_test--entity_test) does not mach the destination resource types (commerce_product_variation--default).');

    $controller = $this->getController();
    $controller->process($request->reveal(), ['commerce_product_variation--default']);
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
          'type' => $product_variation->getEntityTypeId() . '--' . $product_variation->bundle(),
          'id' => $product_variation->uuid(),
          'meta' => [
            'orderQuantity' => 1,
          ],
        ],
      ],
    ]));

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('The given entity is not assigned to any store.');

    $controller = $this->getController();
    $controller->process($request->reveal(), ['commerce_product_variation--default']);
  }

  /**
   * Tests exception when product's stores is not a current store.
   */
  public function testNotCurrentStoreException() {
    $additional_store1 = $this->createStore();
    $additional_store2 = $this->createStore();
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create([
      'type' => 'default',
      'stores' => [$additional_store2->id(), $additional_store1->id()],
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

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage("The given entity can't be purchased from the current store.");

    $controller = $this->getController();
    $controller->process($request->reveal(), ['commerce_product_variation--default']);
  }

  /**
   * Tests exception when product's stores is not a current store.
   */
  public function testAddedToCart() {
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create([
      'type' => 'default',
      'stores' => [$this->store->id()],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'JSONAPI_SKU',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);
    $product_variation->save();
    $product->addVariation($product_variation);
    $product->save();

    $request = Request::create('https://localhost/cart/add', 'POST', [], [], [], [], Json::encode([
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

    $controller = $this->getController();
    $response = $controller->process($request, ['commerce_product_variation--default']);
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $resource_object = $response->getResponseData()->getData()->getIterator()->offsetGet(0);
    assert($resource_object instanceof ResourceObject);
    $this->assertEquals('commerce_order_item--default', $resource_object->getTypeName());
    $purchased_entity = $resource_object->getField('purchased_entity');
    $this->assertEquals($product_variation->id(), $purchased_entity->target_id);
  }

  /**
   * Gets the controller to test.
   *
   * @return \Drupal\commerce_cart_api\Resource\CartAddResource
   *   The controller.
   *
   * @throws \Exception
   */
  protected function getController() {
    return new CartAddResource(
      $this->container->get('jsonapi_resources.resource_response_factory'),
      $this->container->get('jsonapi.resource_type.repository'),
      $this->container->get('entity_type.manager'),
      $this->container->get('jsonapi_resources.entity_access_checker'),
      $this->container->get('commerce_cart.cart_provider'),
      $this->container->get('commerce_cart.cart_manager'),
      $this->container->get('commerce_cart_api.jsonapi_controller_shim'),
      $this->container->get('commerce_order.chain_order_type_resolver'),
      $this->container->get('commerce_store.current_store'),
      $this->container->get('commerce_price.chain_price_resolver'),
      $this->container->get('entity.repository'),
      $this->container->get('current_user'),
      $this->container->get('renderer')
    );
  }

}
