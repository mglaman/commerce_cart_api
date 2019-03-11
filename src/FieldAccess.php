<?php

namespace Drupal\commerce_cart_api;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Container;

class FieldAccess implements FieldAccessInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new FieldAccess object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function handle($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    $route = $this->routeMatch->getRouteObject();
    // Only check access if this is running on our API routes.
    if (!$route || !$route->hasRequirement('_cart_api')) {
      return AccessResult::neutral();
    }

    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $method = 'allowed' . Container::camelize("{$entity_type_id}_fields");

    if (method_exists($this, $method)) {
      $allowed_fields = $this->{$method}($operation, $field_definition, $account, $items) ?: [];
      return AccessResult::forbiddenIf(!in_array($field_definition->getName(), $allowed_fields, TRUE));
    }
    if ($operation === 'view') {
      // Disallow access to generic entity fields for any other entity which
      // has been normalized and being returns (like purchasable entities.)
      $disallowed_fields = [
        'created',
        'changed',
        'default_langcode',
        'langcode',
        'status',
        'uid',
      ];
      return AccessResult::forbiddenIf(in_array($field_definition->getName(), $disallowed_fields, TRUE));
    }

    return AccessResult::neutral();
  }

  /**
   * Allowed commerce_order fields.
   *
   * @param string $operation
   *   The operation to be performed.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   (optional) The entity field object for which to check access, or NULL if
   *   access is checked for the field definition, without any specific value
   *   available. Defaults to NULL.
   *
   * @return array
   *   The allowed fields.
   */
  protected function allowedCommerceOrderFields($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    if ($operation === 'view') {
      return [
        'order_id',
        'uuid',
        'order_number',
        'store_id',
        // Allow after https://www.drupal.org/project/commerce/issues/2916252.
        // 'adjustments',
        'coupons',
        'order_total',
        'total_price',
        'order_items',
      ];
    }
    return [];
  }

  /**
   * Allowed commerce_order_item fields.
   *
   * @param string $operation
   *   The operation to be performed.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   (optional) The entity field object for which to check access, or NULL if
   *   access is checked for the field definition, without any specific value
   *   available. Defaults to NULL.
   *
   * @return array
   *   The allowed fields.
   */
  protected function allowedCommerceOrderItemFields($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    if ($operation === 'view') {
      return [
        'order_id',
        'order_item_id',
        'uuid',
        'purchased_entity',
        'title',
        // Allow after https://www.drupal.org/project/commerce/issues/2916252.
        // 'adjustments',
        'quantity',
        'order_total',
        'unit_price',
        'total_price',
      ];
    }
    return [];
  }

}
