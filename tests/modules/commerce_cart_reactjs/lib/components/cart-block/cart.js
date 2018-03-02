import React, { Component } from 'react';
import {object} from 'prop-types';
class Cart extends Component {
  static propTypes = {
    cart: object.isRequired
  };
  constructor(props) {
    super(props);
    this.state = {
      // Copy the prop into state so we can refresh it.
      cart: props.cart,
      langCode: drupalSettings.path.currentLanguage,
    };
  }
  formatPrice(priceObject) {
    return new Intl.NumberFormat(this.state.langCode, {style: 'currency', currency: priceObject.currency_code}).format(priceObject.number)
  }
  render() {
    return(
      <div>
        <table>
          {this.state.cart.order_items.map(item => (
            <tr key={item.order_item_id[0].value}>
              <td>x{parseInt(item.quantity[0].value)}</td>
              <td>{item.title[0].value}</td>
              <td>{this.formatPrice(item.unit_price[0])}</td>
              <td>{this.formatPrice(item.total_price[0])}</td>
              <td>Remove</td>
            </tr>
          ))}
        </table>
        <div>Total: {this.formatPrice(this.state.cart.total_price[0])}</div>
      </div>
    )
  }
}
export default Cart;
