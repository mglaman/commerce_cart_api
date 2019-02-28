<?php

namespace Drupal\commerce_cart_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

class CommerceCartApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('commerce_cart.cart_session') && $container->getParameter('commerce_cart_api.token_cart_session')) {
      $container->getDefinition('commerce_cart.cart_session')
        ->setClass(TokenCartSession::class)
        ->setArguments([new Reference('request_stack'), new Reference('tempstore.private')]);
    }
  }

}
