import React, { Component } from 'react';
import {object} from 'prop-types';
import {baseUrl, formatPrice} from "../../utils";
import superagent from 'superagent';
import superagentCache from 'superagent-cache';

superagentCache(superagent);

class Cart extends Component {
  static propTypes = {
    cart: object.isRequired
  };
  constructor(props) {
    super(props);
    this.state = {
      // Copy the prop into state so we can refresh it.
      cart: props.cart,
      cartId: props.cart.order_id,
      langCode: drupalSettings.path.currentLanguage,
    };
  }
  doCartRefresh() {
    const url = `${baseUrl}/cart/${this.state.cartId}?_format=json`;
    superagent
      .get(url)
      .end((err, { body }) => {
        this.setState({
          cart: body,
        });
      })
  }
  doItemDelete(item, event) {
    event.preventDefault();
    superagent
      .delete(`${baseUrl}/cart/${this.state.cartId}/items/${item.order_item_id}?_format=json`)
      .end((err, { body }) => {
        this.doCartRefresh();
      })
  }
  doItemsUpdate() {
    event.preventDefault();

    const payload = {};
    this.state.cart.order_items.map(item => {
      payload[item.order_item_id] = {
        quantity: item.quantity
      };
    });

    superagent
      .patch(`${baseUrl}/cart/${this.state.cartId}/items?_format=json`)
      .set('Content-Type', 'application/json')
      .send(JSON.stringify(payload))
      .end((err, { body }) => {
        this.setState({
          cart: body,
        });
      })
  }
  handleQuantityChange(item, _key, event) {
    // Update the items quantity.
    item.quantity = event.target.value;
    let cart = this.state.cart;
    cart.order_items[_key] = item;
    this.setState({
      cart: cart
    })
  }
  render() {
    if (this.state.cart.order_items.length === 0) {
      return (
        <div>No items, yet. Go shopping!</div>
      );
    }
    return(
      <div>
        <table>
          <tr>
            <th>Item</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Total</th>
            <th>Remove</th>
          </tr>
          {this.state.cart.order_items.map((item, _key) => (
            <tr key={item.order_item_id}>
              <td>{item.title}</td>
              <td>{formatPrice(item.unit_price)}</td>
              <td><input
                type="number"
                value={parseInt(item.quantity)}
                onChange={this.handleQuantityChange.bind(this, item, _key)}
              /></td>
              <td>{formatPrice(item.total_price)}</td>
              <td><button onClick={this.doItemDelete.bind(this, item)}><span>Remove</span></button></td>
            </tr>
          ))}
          <tfoot>
            <td colSpan="2" />
            <td><button onClick={this.doItemsUpdate.bind(this)}>Update quantities</button></td>
            <td><div>{formatPrice(this.state.cart.total_price)}</div></td>
          </tfoot>
        </table>
        <button>Checkout</button>
      </div>
    )
  }
}
export default Cart;
