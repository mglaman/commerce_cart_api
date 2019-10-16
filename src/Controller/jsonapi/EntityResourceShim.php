<?php

namespace Drupal\commerce_cart_api\Controller\jsonapi;

use Drupal\jsonapi\Controller\EntityResource;
use Drupal\jsonapi\JsonApiResource\IncludedData;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shims the JSON:API controller to make some of its methods accessible.
 *
 * @internal Commerce Cart API is extending an internal class of the JSON:API
 *   module. Do *not* use this class as it highly unstable.
 */
final class EntityResourceShim extends EntityResource {

  /**
   * {@inheritdoc}
   */
  public function deserialize(ResourceType $resource_type, Request $request, $class, $relationship_field_name = NULL) {
    return parent::deserialize($resource_type, $request, $class, $relationship_field_name);
  }

}
