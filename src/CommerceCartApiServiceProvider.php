<?php

namespace Drupal\commerce_cart_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

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
    if ($parameter['use_token_cart_session']) {
      $container->register('commerce_cart_api.token_cart_session', TokenCartSession::class)
        ->setDecoratedService('commerce_cart.cart_session')
        ->setPublic(FALSE)
        ->setArguments([new Reference('commerce_cart_api.token_cart_session.inner'), new Reference('request_stack'), new Reference('tempstore.private')]);
    }
  }

}
