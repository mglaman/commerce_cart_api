<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cart collection resource.
 */
final class CartCollectionResource extends CartResourceBase {

  /**
   * Get a carts collection for the current user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request) {
    $this->fixInclude($request);
    $carts = $this->cartProvider->getCarts();
    $top_level_data = $this->createCollectionDataFromEntities($carts);
    $response = $this->createJsonapiResponse($top_level_data, $request);

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['store', 'cart']);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

}
