<?php

namespace Drupal\Tests\commerce_cart_api\Kernel;

use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Drupal\Tests\commerce_cart\Kernel\CartManagerTestTrait;

class RoutesTest extends CommerceKernelTestBase {

  use CartManagerTestTrait;

  /**
   * The variation to test against.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariation
   */
  protected $variation;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManager
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProvider
   */
  protected $cartProvider;

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  public static $modules = [
    'entity_reference_revisions',
    'path',
    'profile',
    'state_machine',
    'commerce_product',
    'commerce_order',
    'commerce_test',
    'serialization',
    'jsonapi',
    'commerce_cart_api',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp() {
    parent::setUp();
  }

  public function testBuildRoutes() {
    $this->container->get('router.builder')->rebuild();
    $routes = $this->container->get('router')->getRouteCollection();

    $cart_collection = $routes->get('commerce_cart_api.cart_collection');
    $this->assertNotEmpty($cart_collection);
    $cart_canonical = $routes->get('commerce_cart_api.cart_canonical');
    $this->assertNotEmpty($cart_canonical);
  }

}
