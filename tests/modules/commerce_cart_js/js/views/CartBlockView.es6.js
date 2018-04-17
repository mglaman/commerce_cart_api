/**
 * @file
 * A Backbone view for the body element.
 */

(function ($, Drupal, Backbone) {
  let cartCount = 0;
  let isOpen = false;
  let isOutsideHorizontal = false;
  Drupal.commerceCart.CartBlockView = Backbone.View.extend(/** @lends Drupal.commerceCart.CartBlockView# */{
    /**
     * Adjusts the body element with the toolbar position and dimension changes.
     *
     * @constructs
     *
     * @augments Backbone.View
     */
    initialize() {
      this.listenTo(this.model, 'cartsLoaded', this.render);
    },

    events: {
      'click .cart-block--cart-table__remove button': 'removeItem',
      'click .cart-block--link__expand': 'expandContents'
    },
    removeItem(e) {
      const target = JSON.parse(e.target.value);
      const endpoint = Drupal.url(`cart/${target[0]}/items/${target[1]}?_format=json`);
      fetch(endpoint, {
        // By default cookies are not passed, and we need the session cookie!
        credentials: 'include',
        method: 'delete'
      })
        .then((res) => {})
        .then(() => Drupal.commerceCart.fetchCarts());
    },
    expandContents(e) {
      e.preventDefault();
      if (cartCount > 0) {
        const $cart = $('.cart--cart-block');
        const $cartContents = $cart.find('.cart-block--contents');
        // Get the shopping cart width + the offset to the left.
        const windowWidth = $(window).width();
        const cartWidth = $cartContents.width() + $cart.offset().left;
        // If the cart goes out of the viewport we should align it right.
        isOutsideHorizontal = cartWidth > windowWidth;
        if (isOutsideHorizontal) {
          $cartContents.addClass('is-outside-horizontal');
        }
        // Toggle the expanded class.
        $cartContents
          .toggleClass('cart-block--contents__expanded')
          .slideToggle();
        isOpen = !isOpen;
      }
    },

    /**
     * @inheritdoc
     */
    render() {
      cartCount = this.model.getCount();
      const template = Drupal.commerceCart.getTemplate({
        id: 'commerce_cart_js_block',
        data: '<div class="cart--cart-block">\n' +
        '  <div class="cart-block--summary">\n' +
        '    <a class="cart-block--link__expand" href="<%= url %>">\n' +
        '      <span class="cart-block--summary__icon" />\n' +
        '      <span class="cart-block--summary__count"><%= count_text %></span>\n' +
        '    </a>\n' +
        '  </div>\n' +
        '<% if (count > 0) { %>' +
        '  <div class="cart-block--contents">\n' +
        '    <div class="cart-block--contents__inner">\n' +
        '      <div class="cart-block--contents__items">\n' +
        '      </div>\n' +
        '      <div class="cart-block--contents__links">\n' +
        '        <%= links %>\n' +
        '      </div>\n' +
        '    </div>\n' +
        '  </div>' +
        '<% } %>' +
        '</div>\n',
      });

      this.$el.html(template.render({
        url: this.model.getUrl(),
        icon: this.model.getIcon(),
        count: this.model.getCount(),
        count_text: Drupal.formatPlural(
          this.model.getCount(),
          this.model.getCountSingular(),
          this.model.getCountPlural(),
        ),
        links: this.model.getLinks(),
        carts: this.model.getCarts(),
      }));

      // Hack to fix cart block contents disappearing on order item remove.
      if (isOpen) {
        this.$el.find('.cart-block--contents')
          .addClass('cart-block--contents__expanded')
          .addClass('is-outside-horizontal', isOutsideHorizontal)
          .show();
      }

      const icon = new Drupal.commerceCart.CartIconView({
        el: this.$el.find('.cart-block--summary__icon'),
        model: this.model
      });
      icon.render();
      const contents = new Drupal.commerceCart.CartContentsView({
        el: this.$el.find('.cart-block--contents__items'),
        model: this.model
      });
      contents.render();

      // Rerun any Drupal behaviors.
      Drupal.attachBehaviors();
    },
  });
}(jQuery, Drupal, Backbone));
