(function ($, _, Drupal, drupalSettings) {
  const cache = {};

  // Use Mustache.js style formatting (and Twig)
  // @see http://underscorejs.org/#template
  _.templateSettings = {
    interpolate: /\{\{(.+?)\}\}/g
  };
  Drupal.commerceCart = {
    getTemplate(data) {
      const id = data.id;
      if (!cache.hasOwnProperty(id)) {
        cache[id] = {
          render: _.template(data.data)
        };
      }
      return cache[id];
    }
  };

  /**
   * Registers tabs with the toolbar.
   *
   * The Drupal toolbar allows modules to register top-level tabs. These may
   * point directly to a resource or toggle the visibility of a tray.
   *
   * Modules register tabs with hook_toolbar().
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the toolbar rendering functionality to the toolbar element.
   */
  Drupal.behaviors.cartBlock = {
    attach(context) {
      $(context).find('#commerce_cart_js_block').once('cart-block-render').each(function () {
        const model = new Drupal.commerceCart.CartBlockModel(
          drupalSettings.cartBlock.context
        );
        const view = new Drupal.commerceCart.CartBlockView({
          el: this,
          model,
        });
        model.fetchCarts();
      });
    }
  };

}(jQuery, _, Drupal, drupalSettings));
