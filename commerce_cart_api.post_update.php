<?php

/**
 * @file
 * Post update functions for Commerce Cart API.
 */

/**
 * Ensures the cart clear resource configuration is present.
 */
function commerce_cart_api_post_update_install_cart_clear_resource() {
  $config_updater = \Drupal::getContainer()->get('commerce.config_updater');
  $config_updater->import([
    'rest.resource.commerce_cart_clear',
  ]);
}

/**
 * Ensures the cart coupons resource configuration is present.
 */
function commerce_cart_api_post_update_install_cart_coupons_resource() {
  $config_updater = \Drupal::getContainer()->get('commerce.config_updater');
  $config_updater->import([
    'rest.resource.commerce_cart_coupons',
  ]);
}
