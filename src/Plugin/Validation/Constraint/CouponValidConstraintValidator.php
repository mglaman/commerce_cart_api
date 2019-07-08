<?php

namespace Drupal\commerce_cart_api\Plugin\Validation\Constraint;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CouponValidConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function validate($value, Constraint $constraint) {
    assert($value instanceof EntityReferenceFieldItemListInterface);
    $order = $value->getEntity();
    assert($order instanceof OrderInterface);
    // Only draft orders should be processed.
    if ($order->getState()->getId() !== 'draft') {
      return;
    }
    $coupons = $value->referencedEntities();
    foreach ($coupons as $delta => $coupon) {
      assert($coupon instanceof CouponInterface);
      if (!$coupon->available($order) || !$coupon->getPromotion()->applies($order)) {
        $this->context->buildViolation($constraint->message, ['%code' => $coupon->getCode()])
          ->atPath((string) $delta)
          ->addViolation();
      }
    }
  }

}
