<?php

namespace Drupal\Tests\commerce_cart_api\Unit;

use Drupal\commerce_cart_api\FieldAccess;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Route;

final class FieldAccessTest extends UnitTestCase {

  /**
   * @var \Drupal\commerce_cart_api\FieldAccess
   */
  private $fieldAccess;

  protected function setUp(){
    parent::setUp();
    $mocked_route = $this->prophesize(Route::class);
    $mocked_route->hasRequirement('_cart_api')->willReturn(TRUE);
    $mocked_route_match = $this->prophesize(RouteMatchInterface::class);
    $mocked_route_match->getRouteObject()->willReturn($mocked_route->reveal());

    $this->fieldAccess = new FieldAccess($mocked_route_match->reveal());
  }

  /**
   * @dataProvider dataAllowedCommerceOrderFields
   */
  public function testAllowedCommerceOrderFields($result, $operation, $field_name) {
    $mocked_field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $mocked_field_definition->getTargetEntityTypeId()->willReturn('commerce_order');
    $mocked_field_definition->getName()->willReturn($field_name);

    $mocked_account = $this->prophesize(AccountInterface::class);

    $this->assertInstanceOf(
      $result,
      $this->fieldAccess->handle(
        $operation,
        $mocked_field_definition->reveal(),
        $mocked_account->reveal()
      )
    );
  }

  public function dataAllowedCommerceOrderFields() {
    return [
      [
        AccessResultForbidden::class, 'view', 'type',
        AccessResultAllowed::class, 'view', 'uuid',
      ]
    ];
  }

}
