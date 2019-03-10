<?php

namespace Drupal\commerce_cart_api\EventSubscriber;

use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_cart_api\CartTokenSession;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cart token subscriber.
 *
 * This subscriber provides two pieces of functionality.
 *
 * On response, it ensures the Vary header contains the cart token header. This
 * handles browser and reverse proxy caching handling.
 *
 * On request, it checks if the cart token query parameter is available. This
 * ensures cart data is passed to the user's session. For example, a user that
 * created a cart from a decoupled application but visits checkout using the
 * cart token to finish order purchased.
 */
final class CartTokenSubscriber implements EventSubscriberInterface {

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

  /**
   * Constructs a new CartTokenSubscriber object.
   *
   * @param \Drupal\commerce_cart\CartSessionInterface $cart_session
   *   The cart session.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The temp store factory.
   */
  public function __construct(CartSessionInterface $cart_session, SharedTempStoreFactory $temp_store_factory) {
    $this->cartSession = $cart_session;
    $this->tempStore = $temp_store_factory->get('commerce_cart_api_tokens');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    // Run before router_listener so we execute before access checks, and before
    // dynamic_page_cache so we can populate a session. The ensures proper
    // access to CheckoutController.
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];

    $events[KernelEvents::RESPONSE][] = ['onResponse'];
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

  /**
   * Ensures the Vary header contains the cart token header name.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The response event.
   */
  public function onResponse(FilterResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->headers->has(CartTokenSession::HEADER_NAME)) {
      $response = $event->getResponse();
      $response->setVary(CartTokenSession::HEADER_NAME, FALSE);
    }
  }

}
