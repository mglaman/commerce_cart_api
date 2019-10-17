<?php

namespace Drupal\Tests\commerce_cart_api\Kernel;

use Drupal\commerce_cart_api\Controller\CartResourceController;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @group commerce_cart_api
 */
final class CartUpdateItemResourceTest extends CommerceKernelTestBase {

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
   * @dataProvider dataUpdateItemAttributes
   */
  public function testUpdateItem(array $test_document, $expected_exception_class = '', $expected_exception_message = '') {
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'uuid' => '9dc0ce8a-1d62-40a2-bbf9-7b6041fd08d1',
      'type' => 'default',
      'sku' => 'TEST',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);
    $product_variation->save();

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

    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => '1',
      'unit_price' => $product_variation->getPrice(),
      'purchased_entity' => $product_variation->id(),
    ]);
    assert($order_item instanceof OrderItem);
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'store_id' => $this->store,
      'order_items' => [$order_item],
    ]);
    assert($order instanceof Order);
    $order->save();

    $document['data'] = [
      'type' => $order_item->getEntityTypeId() . '--' . $order_item->bundle(),
      'id' => $order_item->uuid(),
    ];
    $document['data'] += $test_document;
    $document['data'] += ['attributes' => [], 'relationships' => []];
    $request = Request::create("https://localhost/cart/{$order->uuid()}/items/{$order_item->uuid()}", 'POST', [], [], [], [], Json::encode($document));

    $route = $this->container->get('router')->getRouteCollection()->get('commerce_cart_api.jsonapi.cart_update_item');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $this->container->get('request_stack')->push($request);

    $controller = $this->getController();

    if (!empty($expected_exception_class)) {
      $this->expectException($expected_exception_class);
      if (!empty($expected_exception_message)) {
        $this->expectExceptionMessage($expected_exception_message);
      }
    }

    $controller->updateItem($request, $order, $order_item);
  }

  public function dataUpdateItemAttributes() {
    yield [
      [
        'attributes' => [
          'quantity' => 10,
        ],
      ],
    ];
    yield [
      [
        'relationships' => [
          'purchased_entity' => [
            'data' => [
              'type' => 'commerce_product_variation--default',
              'id' => '9dc0ce8a-1d62-40a2-bbf9-7b6041fd08d1',
            ],
          ],
        ],
      ],
      EntityAccessDeniedHttpException::class,
      'The current user is not allowed to PATCH the selected field (purchased_entity).',
    ];
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
