<?php

namespace Drupal\commerce_cart_api;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

interface FieldAccessInterface {

  /**
   * Handle field access.
   *
   * @param string $operation
   *   The operation to be performed. See
   *   \Drupal\Core\Entity\EntityAccessControlHandlerInterface::fieldAccess()
   *   for possible values.
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
   *   The access result.
   */
  public function handle($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL);

}
