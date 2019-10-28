<?php

namespace Drupal\commerce_cart_api\Resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;

final class CartCanonicalResource extends CartResourceBase {

  /**
   * Get a single cart.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, OrderInterface $commerce_order): ResourceResponse {
    $this->fixInclude($request);
    $top_level_data = $this->createIndividualDataFromEntity($commerce_order);
    return $this->createJsonapiResponse($top_level_data, $request);
  }

}
