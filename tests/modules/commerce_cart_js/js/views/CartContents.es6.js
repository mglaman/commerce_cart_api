/**
 * @file
 * A Backbone view for the body element.
 */

(function ($, Drupal, Backbone) {
  Drupal.commerceCart.CartContentsView = Backbone.View.extend(/** @lends Drupal.commerceCart.CartContentsView# */{
    /**
     * @inheritdoc
     */
    render() {

      const template = Drupal.commerceCart.getTemplate({
        id: 'commerce_cart_js_block_contents',
        data:
        '<div>' +
        '        <% _.each(carts, function(cart) { %>' +
        '         <div data-cart-contents=\'<% print(JSON.stringify(cart)) %>\'></div>' +
        '        <% }); %>' +
        '</div>'
      });
      this.$el.html(template.render({
        carts: this.model.getCarts(),
      }));

      this.$el.find('[data-cart-contents]').each(function () {
        const contents = new Drupal.commerceCart.CartContentsItemsView({
          el: this,
          model: Drupal.commerceCart.model
        });
        contents.render();
      });
    },
  });
}(jQuery, Drupal, Backbone));
