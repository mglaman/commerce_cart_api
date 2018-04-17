(function ($, _, Drupal, drupalSettings) {
  const cache = {};

  // Use Mustache.js style formatting (and Twig)
  // @see http://underscorejs.org/#template
  // @todo This breaks conditionals within the template.
  /*
  _.templateSettings = {
    interpolate: /\{\{(.+?)\}\}/g
  };
  */
  Drupal.commerceCart = {
    models: [],
    views: [],
    fetchCarts() {
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
        _.each(Drupal.commerceCart.models, (model) => {
          model.set('count', count);
          model.set('carts', json);
          model.trigger('cartsLoaded', model);
        });
      });
    },
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
        Drupal.commerceCart.models.push(model);
        const view = new Drupal.commerceCart.CartBlockView({
          el: this,
          model,
        });
        Drupal.commerceCart.views.push(view);
        Drupal.commerceCart.fetchCarts();
      });
    }
  };

}(jQuery, _, Drupal, drupalSettings));
