/**
 * @file
 * A Backbone view for the body element.
 */

(function ($, Drupal, Backbone) {
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
      // @todo add click event for .cart-block--link__expand to replace Commerce Cart JS.
      'click .cart-block--cart-table__remove button': 'removeItem'
    },
    views: {
      icon: null,
    },
    // @todo move to CartContentsItemsView
    removeItem(e) {
      const target = JSON.parse(e.target.value);
      const endpoint = Drupal.url(`cart/${target[0]}/items/${target[1]}?_format=json`);
      fetch(endpoint, {
        // By default cookies are not passed, and we need the session cookie!
        credentials: 'include',
        method: 'delete'
      })
        .then((res) => {})
        .finally(() => this.model.fetchCarts());
    },

    /**
     * @inheritdoc
     */
    render() {
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

      // @todo Cart model and Collection.
      this.$el.find('[data-cart-contents]').each(function () {
        const contents = new Drupal.commerceCart.CartContentsItemsView({
          el: this,
        });
        contents.render();
      });
    },
  });
  Drupal.commerceCart.CartContentsItemsView = Backbone.View.extend(/** @lends Drupal.commerceCart.CartContentsItemsView# */{
    /**
     * @inheritdoc
     */
    render() {
      const template = Drupal.commerceCart.getTemplate({
        id: 'commerce_cart_js_block_item_contents',
        data:
        '        <div>\n' +
        '        <table class="cart-block--cart-table"><tbody>\n' +
        '        <% _.each(cart.order_items, function(orderItem) { %>' +
        '            <tr>\n' +
        '              <td class="cart-block--cart-table__quantity"><% print(parseInt(orderItem.quantity)) %>&nbsp;x</td>\n' +
        '              <td class="cart-block--cart-table__title"><%- orderItem.title %></td>\n' +
        '              <td class="cart-block--cart-table__price"><%= orderItem.total_price.formatted %></td>\n' +
        '              <td class="cart-block--cart-table__remove"><button value="<% print(JSON.stringify([cart.order_id, orderItem.order_item_id]))  %>">x</button></td>' +
        '            </tr>\n' +
        '        <% }); %>' +
        '          </tbody>\n</table>\n' +
        '        </div>'
      });
      this.$el.html(template.render({
        cart: this.$el.data('cart-contents')
      }));
    },
  });
}(jQuery, Drupal, Backbone));
