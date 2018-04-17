<?php

namespace Drupal\commerce_cart_reactjs\Controller;

use Drupal\commerce_cart\Controller\CartController as BaseCartController;
use Drupal\Core\Render\Markup;

class CartController extends BaseCartController {

  /**
   * Outputs a cart view for each non-empty cart belonging to the current user.
   *
   * @return array
   *   A render array.
   */
  public function cartPage() {
    return [
      '#attached' => [
        'library' => [
          'commerce_cart_reactjs/components',
        ],
      ],
      '#markup' => Markup::create('<div id="reactCartForm"></div>'),
    ];
  }

}
