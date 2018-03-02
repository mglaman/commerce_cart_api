import React, { Component } from 'react';
import {object} from 'prop-types';
class Cart extends Component {
  static propTypes = {
    cart: object.isRequired
  };
  state = {
    langCode: drupalSettings.path.currentLanguage,
  };
  formatPrice(priceObject) {
    return new Intl.NumberFormat(this.state.langCode, {style: 'currency', currency: priceObject.currency_code}).format(priceObject.number)
  }
  render() {
    const { cart } = this.props;
    return(
      <div>
        <table>
          {cart.order_items.map(item => (
            <tr>
              <td>{item.quantity[0].value}</td>
              <td>{item.title[0].value}</td>
              <td>{this.formatPrice(item.unit_price[0])}</td>
            </tr>
          ))}
        </table>
        <div>Total: {this.formatPrice(cart.total_price[0])}</div>
      </div>
    )
  }
}
export default Cart;
