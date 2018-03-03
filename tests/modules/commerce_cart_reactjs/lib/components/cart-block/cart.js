import React, { Component } from 'react';
import {object} from 'prop-types';
import {baseUrl} from "../../utils";
import superagent from 'superagent';

class Cart extends Component {
  static propTypes = {
    cart: object.isRequired
  };
  constructor(props) {
    super(props);
    this.state = {
      // Copy the prop into state so we can refresh it.
      cart: props.cart,
      cartId: props.cart.order_id[0].value,
      langCode: drupalSettings.path.currentLanguage,
    };
  }
  formatPrice(priceObject) {
    if (priceObject.currency_code === null) {
      return '';
    }
    return new Intl.NumberFormat(this.state.langCode, {style: 'currency', currency: priceObject.currency_code}).format(priceObject.number)
  }
  doCartRefresh() {
    const url = `${baseUrl}/cart/${this.state.cartId}?_format=json`;
    superagent
      .get(url)
      .end((err, { body }) => {
        debugger;
        this.setState({
          cart: body,
        });
      })
  }
  doItemDelete(item, event) {
    event.preventDefault();
    superagent
      .delete(`${baseUrl}/cart/${this.state.cartId}/items/${item.order_item_id[0].value}?_format=json`)
      .end((err, { body }) => {
        debugger;
        this.doCartRefresh();
      })
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
              <td><button onClick={this.doItemDelete.bind(this, item)}>
                <span>X</span> <span className="hidden">Remove</span>
              </button></td>
            </tr>
          ))}
        </table>
        <div>Total: {this.formatPrice(this.state.cart.total_price[0])}</div>
      </div>
    )
  }
}
export default Cart;
