<?php declare(strict_types=1);

namespace Drupal\Tests\commerce_cart_api\Kernel;

use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

abstract class CartResourceKernelTestBase extends CommerceKernelTestBase {

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

}
