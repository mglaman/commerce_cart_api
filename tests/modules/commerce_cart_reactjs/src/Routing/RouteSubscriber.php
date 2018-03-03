<?php

namespace Drupal\commerce_cart_reactjs\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('commerce_cart.page');
    if ($route) {
      $route->setDefault('_controller', '\Drupal\commerce_cart_reactjs\Controller\CartController::cartPage');
    }
  }

}
