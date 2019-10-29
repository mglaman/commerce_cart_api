<?php declare(strict_types=1);

namespace Drupal\Tests\commerce_cart_api\Kernel;

/**
 * @group commerce_cart_api
 */
final class CartResourceRoutesTest extends CartResourceKernelTestBase {

  /**
   * The router.
   *
   * @var \Drupal\Core\Routing\AccessAwareRouter
   */
  protected $router;

  protected function setUp() {
    parent::setUp();
    $this->router = $this->container->get('router');
  }

  public function testCouponAddRoute() {
    $this->installModule('commerce_promotion');
    $route = $this->router->getRouteCollection()->get('commerce_cart_api.jsonapi.cart_coupon_add');
  }

}
