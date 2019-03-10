<?php

namespace Drupal\commerce_cart_api\Session;

use Drupal\commerce_cart_api\CartTokenSession;
use Drupal\Core\Session\SessionConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorates SessionConfiguration to support cart tokens.
 *
 * If a cart token header or query parameter is present, return that a session
 * is active. This prevents page_cache from caching the response. This might
 * may be removed once page_cache supports Vary headers.
 *
 * @link https://www.drupal.org/project/drupal/issues/2972483
 */
final class CartTokenSessionConfiguration implements SessionConfigurationInterface {

  /**
   * The decorated service.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  private $decorated;

  /**
   * {@inheritdoc}
   */
  public function __construct(SessionConfigurationInterface $decorated) {
    $this->decorated = $decorated;
  }

  /**
   * {@inheritdoc}
   */
  public function hasSession(Request $request) {
    return $this->decorated->hasSession($request) || $request->headers->has(CartTokenSession::HEADER_NAME) || $request->query->has(CartTokenSession::QUERY_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(Request $request) {
    return $this->decorated->getOptions($request);
  }

}
