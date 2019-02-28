<?php

namespace Drupal\commerce_cart_api;

use Drupal\commerce_cart\CartSessionInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class TokenCartSession implements CartSessionInterface {

  const HEADER_NAME = 'X-Cart-Token';

  /**
   * Request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  public function __construct(RequestStack $request_stack, PrivateTempStoreFactory $temp_store_factory) {
    $this->requestStack = $request_stack;
    $this->tempStore = $temp_store_factory->get('commerce_cart_api_tokens');
  }

  /**
   * {@inheritdoc}
   */
  public function getCartIds($type = self::ACTIVE) {
    $data = $this->getTokenCartData();
    return $data[$type];
  }

  /**
   * {@inheritdoc}
   */
  public function addCartId($cart_id, $type = self::ACTIVE) {
    $data = $this->getTokenCartData();
    $ids = $data[$type];
    $ids[] = $cart_id;
    $data[$type] = $ids;
    $this->setTokenCartData($data);
  }

  /**
   * {@inheritdoc}
   */
  public function hasCartId($cart_id, $type = self::ACTIVE) {
    $data = $this->getTokenCartData();
    $ids = $data[$type];
    return in_array($cart_id, $ids, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCartId($cart_id, $type = self::ACTIVE) {
    $data = $this->getTokenCartData();
    $ids = $data[$type];
    $ids = array_diff($ids, [$cart_id]);
    $data[$type] = $ids;
    $this->setTokenCartData($data);
  }

  /**
   * Get the token cart data.
   *
   * @return array
   *   The data.
   */
  private function getTokenCartData() {
    $defaults = [
      static::ACTIVE => [],
      static::COMPLETED => [],
    ];
    $request = $this->requestStack->getCurrentRequest();
    assert($request instanceof Request);
    $token = $request->headers->get(static::HEADER_NAME);
    if (empty($token)) {
      return $defaults;
    }
    return $this->tempStore->get($token) ?: $defaults;
  }

  private function setTokenCartData(array $data) {
    $request = $this->requestStack->getCurrentRequest();
    assert($request instanceof Request);
    $token = $request->headers->get(static::HEADER_NAME);
    if (!empty($token)) {
      $this->tempStore->set($token, $data);
    }
  }


}
