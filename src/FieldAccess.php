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
    $method = 'check' . Container::camelize("{$entity_type_id}_field_access");


    $result = AccessResult::neutral();
    if (method_exists($this, $method)) {
      $result = $this->{$method}($operation, $field_definition, $account, $items) ?: AccessResult::neutral();
    } else {
      throw new \RuntimeException($method);
    }

    return $result;
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
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The allowed fields.
   */
  protected function checkCommerceOrderFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    if ($operation === 'edit') {
      $disallowed = [
        'order_number',
        'store_id',
        'adjustments',
        'coupons',
        'order_total',
        'total_price',
      ];
      return AccessResult::forbiddenIf(in_array($field_definition->getName(), $disallowed, TRUE));
    }
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
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The allowed fields.
   */
  protected function checkCommerceOrderItemFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    if ($operation === 'edit') {
      $disallowed = [
        'purchased_entity',
        'title',
        'adjustments',
//        'quantity',
        'unit_price',
        'total_price',
      ];
      return AccessResult::forbiddenIf(in_array($field_definition->getName(), $disallowed, TRUE));
    }
  }

}
