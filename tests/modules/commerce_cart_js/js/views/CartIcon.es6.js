/**
 * @file
 * A Backbone view for the cart icon.
 *
 * This file could be replaced to allow rendering a FontAwesome icon, for instance.
 *
 * @todo see about just making the template overridable.
 */

(function ($, Drupal, Backbone) {
  Drupal.commerceCart.CartIconView = Backbone.View.extend(/** @lends Drupal.commerceCart.CartIconView# */{
    /**
     * Adjusts the body element with the toolbar position and dimension changes.
     *
     * @constructs
     *
     * @augments Backbone.View
     */
    initialize() { },

    /**
     * @inheritdoc
     */
    render() {

      const template = Drupal.commerceCart.getTemplate({
        id: 'commerce_cart_js_block_icon',
        data: '<img src="<%= icon %>" alt="Cart"/>',
      });
      this.$el.html(template.render({
        icon: this.model.getIcon(),
      }));
    },
  });
}(jQuery, Drupal, Backbone));
