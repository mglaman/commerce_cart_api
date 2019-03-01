<?php

namespace Drupal\commerce_cart_api\Session;

use Drupal\commerce_cart_api\CartTokenSession;
use Drupal\Core\Session\SessionConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

final class CartTokenSessionConfiguration implements SessionConfigurationInterface {

  private $decorated;

  public function __construct(SessionConfigurationInterface $decorated) {
    $this->decorated = $decorated;
  }

  public function hasSession(Request $request) {
    return $this->decorated->hasSession($request) || $request->headers->has(CartTokenSession::HEADER_NAME) || $request->query->has(CartTokenSession::QUERY_NAME);
  }

  public function getOptions(Request $request) {
    return $this->decorated->getOptions($request);
  }

}
