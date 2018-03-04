/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function ($, Drupal, Backbone) {
  Drupal.commerceCart.CartBlockView = Backbone.View.extend({
    initialize: function initialize() {
      this.listenTo(this.model, 'cartsLoaded', this.render);
    },
    render: function render() {
      var template = Drupal.commerceCart.getTemplate({
        id: 'commerce_cart_js_block',
        data: '<div class="cart-block">\n' + '  <div class="cart-block--summary">\n' + '    <a class="cart-block--link__expand" href="{{ url }}">\n' + '      <span class="cart-block--summary__icon">\n' + '        <img src="{{ icon }}" alt="Cart"/>\n' + '      </span>\n' + '      <span class="cart-block--summary__count">{{ count_text }}</span>\n' + '    </a>\n' + '  </div>\n' + '</div>\n'
      });

      this.$el.html(template.render({
        url: this.model.getUrl(),
        icon: this.model.getIcon(),
        count_text: Drupal.formatPlural(this.model.getCount(), this.model.getCountSingular(), this.model.getCountPlural())
      }));
    }
  });
})(jQuery, Drupal, Backbone);