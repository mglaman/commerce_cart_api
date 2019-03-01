<?php

namespace  Drupal\commerce_cart_api\EventSubscriber;

use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_cart_api\CartTokenSession;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CartTokenClaimSubscriber implements EventSubscriberInterface {

  /**
   * The cart session.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  private $cartSession;

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  private $tempStore;

  public function __construct(CartSessionInterface $cart_session, SharedTempStoreFactory $temp_store_factory) {
    $this->cartSession = $cart_session;
    $this->tempStore = $temp_store_factory->get('commerce_cart_api_tokens');
  }

  public static function getSubscribedEvents() {
    $events = [];
    // Run before router_listener so we execute before access checks, and before
    // dynamic_page_cache so we can populate a session. The ensures proper
    // access to CheckoutController.
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];
    return $events;
  }

  /**
   * Loads the token cart data and resets it to the session.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event, which contains the current request.
   */
  public function onRequest(GetResponseEvent $event) {
    $cart_token = $event->getRequest()->query->get(CartTokenSession::QUERY_NAME);
    if ($cart_token) {
      $token_cart_data = $this->tempStore->get($cart_token);
      foreach ([CartSessionInterface::ACTIVE, CartSessionInterface::COMPLETED] as $cart_type) {
        if (isset($token_cart_data[$cart_type]) && is_array($token_cart_data[$cart_type])) {
          foreach ($token_cart_data[$cart_type] as $token_cart_datum) {
            $this->cartSession->addCartId($token_cart_datum, $cart_type);
          }
        }
      }
    }
  }
}
