<?php

namespace Drupal\commerce_cart_api\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes CSRF header requirements from our routes.
 */
class RoutingAlterSubscriber implements EventSubscriberInterface {

  /**
   * Alters our cart API routes to remove _csrf_request_header_token.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The event to process.
   */
  public function onRoutingRouteAlter(RouteBuildEvent $event) {
    $route_collection = $event->getRouteCollection();
    foreach ($route_collection as $route) {
      if ($route->hasRequirement('_cart_api')) {
        $requirements = $route->getRequirements();
        unset($requirements['_csrf_request_header_token']);
        $route->setRequirements($requirements);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER][] = ['onRoutingRouteAlter', -100];
    return $events;
  }

}
