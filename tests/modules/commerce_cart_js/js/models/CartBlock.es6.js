/**
 * @file
 * A Backbone Model for collapsible menus.
 */

(function (Backbone, Drupal) {
  /**
   * Backbone Model for the cart block.
   *
   * @constructor
   *
   * @augments Backbone.Model
   */
  Drupal.commerceCart.CartBlockModel = Backbone.Model.extend(/** @lends Drupal.commerceCart.CartBlockModell# */{

    /**
     * @type {object}
     *
     * @prop {object} subtrees
     */
    defaults: /** @lends Drupal.commerceCart.CartBlockModel# */ {

      /**
       * @type {string}
       */
      icon: '',

      /**
       * @type {number}
       */
      count: 0,

      /**
       * @type {Array}
       */
      carts: [],

      /**
       * @type {Object}
       */
      countText: {
        singular: '@count item',
        plural: '@count items'
      },

      /**
       * @type {string}
       */
      url: '',

      /**
       * @type {Array}
       */
      links: [],
    },

    getUrl() {
      return this.get('url');
    },
    getIcon() {
      return this.get('icon');
    },
    getCount() {
      return this.get('count');
    },
    getCountPlural() {
      return this.get('countText').singular;
    },
    getCountSingular() {
      return this.get('countText').plural;
    },
    fetchCarts() {
      // @todo will not work on IE11 w/o a polyfill.
      let data = fetch(Drupal.url(`cart?_format=json`), {
        // By default cookies are not passed, and we need the session cookie!
        credentials: 'include'
      });
      data.then((res) => {
        return res.json();
      }).then((json) => {
        let count = 0;
        for (let i in json) {
          count += json[i].order_items.length;
        }
        this.set('count', count);
        this.trigger('cartsLoaded', this);
      });

    }
  });
}(Backbone, Drupal));
