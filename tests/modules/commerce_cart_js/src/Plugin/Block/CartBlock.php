<?php

namespace Drupal\commerce_cart_js\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Provides a cart block.
 *
 * @Block(
 *   id = "commerce_cart_js",
 *   admin_label = @Translation("Cart (JS)"),
 *   category = @Translation("Commerce")
 * )
 */
class CartBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $registry = \Drupal::getContainer()->get('theme.registry')->get();
    $cart_block_theme = $registry['commerce_cart_js_block'];
    $twig_theme_registry = \Drupal::getContainer()->get('twig.loader.theme_registry');
    $twig = $twig_theme_registry->getSourceContext($cart_block_theme['template'] . '.html.twig');
    return [
      '#attached' => [
        'library' => [
          'commerce_cart_js/cart',
        ],
        'drupalSettings' => [
          'cartBlock' => [
            'template' => $twig->getCode(),
            'context' => [
              'url' => Url::fromRoute('commerce_cart.page')->toString(),
              'icon' => file_create_url(drupal_get_path('module', 'commerce') . '/icons/ffffff/cart.png'),
            ],
          ],
        ],
      ],
      '#markup' => Markup::create('<div id="commerce_cart_js_block"></div>'),
    ];
  }

}
