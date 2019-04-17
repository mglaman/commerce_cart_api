<?php

namespace Drupal\commerce_cart_api\Plugin\Field;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

final class ComputedOrderTotalFieldItemList extends FieldItemList {
  use ComputedItemListTrait;

  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    $summary = \Drupal::getContainer()->get('commerce_order.order_total_summary');
    $this->list[0] = $this->createItem(0, $summary->buildTotals($order));
  }
}
