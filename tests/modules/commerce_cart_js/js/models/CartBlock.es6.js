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
  Drupal.commerceCart.CartBlockModel = Backbone.Model.extend(/** @lends Drupal.commerceCart.CartBlockModel# */{

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
        singular: '1 item',
        plural: '@count items'
      },

      /**
       * @type {string}
       */
      url: '',

      /**
       * @type {Array}
       */
      links: [
        `<a href="${Drupal.url('cart')}">${Drupal.t('View cart')}</a>`
      ],
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
      return this.get('countText').plural;
    },
    getCountSingular() {
      return this.get('countText').singular;
    },
    getLinks() {
      return this.get('links');
    },
    getCarts() {
      return this.get('carts');
    }
  });
}(Backbone, Drupal));
