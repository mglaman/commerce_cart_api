<?php

namespace Drupal\commerce_cart_api;

use Drupal\commerce_cart_api\EventSubscriber\CartTokenSubscriber;
use Drupal\commerce_cart_api\Session\CartTokenSessionConfiguration;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds services to the container for Commerce Cart API.
 *
 * When the service parameter commerce_cart_api.use_cart_token_session is set
 * to TRUE, this enables the cart token session provider.
 */
class CommerceCartApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   *
   * The ::register method runs before the site's service_yamls have been
   * registered. That means the parameter will always be false. We register the
   * decorated service here so that it respects the customized parameter.
   */
  public function alter(ContainerBuilder $container) {
    $parameter = $container->getParameter('commerce_cart_api');
    if ($parameter['use_cart_token_session']) {
      $container->register('commerce_cart_api.cart_token_session', CartTokenSession::class)
        ->setDecoratedService('commerce_cart.cart_session')
        ->setPublic(FALSE)
        ->setArguments([
          new Reference('commerce_cart_api.cart_token_session.inner'),
          new Reference('request_stack'),
          new Reference('tempstore.shared'),
        ]);

      $container->register('commerce_cart_api.cart_token_session_configuration', CartTokenSessionConfiguration::class)
        ->setDecoratedService('session_configuration')
        ->setPublic(FALSE)
        ->setArguments([new Reference('commerce_cart_api.cart_token_session_configuration.inner')]);

      $container->register('commerce_cart_api.token_cart_convert_subscriber', CartTokenSubscriber::class)
        ->setArguments([new Reference('commerce_cart.cart_session'), new Reference('tempstore.shared')])
        ->addTag('event_subscriber');
    }
  }

}
