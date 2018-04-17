<?php

namespace Drupal\commerce_cart_reactjs\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;

/**
 * Provides a cart block.
 *
 * @Block(
 *   id = "commerce_cart_reactjs",
 *   admin_label = @Translation("Cart (ReactJS)"),
 *   category = @Translation("Commerce")
 * )
 */
class CartBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#attached' => [
        'library' => [
          'commerce_cart_reactjs/components',
        ],
      ],
      '#markup' => Markup::create('<div id="reactCartBlock"></div>'),
    ];
  }

}
