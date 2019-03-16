<?php

namespace Drupal\commerce_cart_api\ParamConverter;

use Drupal\jsonapi\ParamConverter\EntityUuidConverter;
use Symfony\Component\Routing\Route;

class CartUuidConverter extends EntityUuidConverter {
  // @todo JSON API's does not work unless resource_type param exists
  public function applies($definition, $name, Route $route) {
    return (
      $route->hasRequirement('_cart_api') && $route->getRequirement('_content_type_format') === 'api_json' &&
      !empty($definition['type']) && strpos($definition['type'], 'entity') === 0
    );
  }
}
