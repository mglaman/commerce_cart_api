<?php

namespace Drupal\commerce_cart_api\PageCache;

use Drupal\Core\PageCache\ResponsePolicyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Cache policy for cart collection API route.
 *
 * This policy rule denies caching of responses generated by the
 * rest.commerce_cart_collection.GET route. A user with no carts will receive
 * an empty response that cannot be invalidated, so this route cannot be cached
 * by tags, but can via context and dynamic_page_cache.
 */
final class DenyCartCollection implements ResponsePolicyInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a deny cart collection page cache policy.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function check(Response $response, Request $request) {
    if ($this->routeMatch->getRouteName() === 'rest.commerce_cart_collection.GET') {
      return static::DENY;
    }
  }
}
