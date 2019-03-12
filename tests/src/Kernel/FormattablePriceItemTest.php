<?php

namespace Drupal\Tests\commerce_cart_api\Kernel;

use Drupal\commerce_cart_api\Plugin\Field\FieldType\FormattablePriceItem;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * @group commerce_cart_api
 */
final class FormattablePriceItemTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_order',
    'commerce_cart',
    'commerce_cart_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    EntityFormMode::create([
      'id' => 'commerce_order_item.add_to_cart',
      'label' => 'Add to cart',
      'targetEntityType' => 'commerce_order_item',
    ])->save();
    OrderItemType::create([
      'id' => 'test',
      'label' => 'Test',
      'orderType' => 'default',
    ])->save();
  }

  /**
   * Test the FormattablePriceItem.
   */
  public function testFormattablePriceItem() {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('10.00', 'USD'),
    ]);

    $unit_price = $order_item->get('unit_price')->first();
    $this->assertInstanceOf(FormattablePriceItem::class, $unit_price);
    assert($unit_price instanceof FormattablePriceItem);
    $this->assertEquals('$10.00', $unit_price->get('formatted')->getValue());
  }

}
