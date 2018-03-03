import React, { Component } from 'react';
import {baseUrl} from "../../utils";
import superagent from 'superagent';
import Cart from "./cart";

class CartBlock extends Component {
  constructor(props) {
    super(props);
    this.state = {
      loaded: false,
      expanded: false,
      count: 0,
      carts: [],
    };
    this.onClickExpand = this.onClickExpand.bind(this);
  }
  getCarts() {
    console.log('Getting carts');
    const url = `${baseUrl}/cart?_format=json`;
    superagent
      .get(url)
      .end((err, { body }) => {
        let count = 0;
        for (let i in body) {
          count += body[i].order_items.length;
        }
        this.setState({
          loaded: true,
          count: count,
          carts: body.length > 0 ? body : [],
        });
      })
  }
  componentDidMount() {
    this.getCarts();
  }
  onClickExpand(event) {
    event.preventDefault();
    this.setState({
      expanded: !this.state.expanded
    });
  }
  render() {
    if (!this.state.loaded) {
      return null;
    }
    return (
      <div>
        <div className="cart-block--summary">
          <a className="cart-block--link__expand" href={`${baseUrl}/cart`} onClick={this.onClickExpand}>
            <span className="cart-block--summary__icon">
              <img src={`/modules/contrib/commerce/icons/ffffff/drupal-cart.png`} />
            </span>
            <span className="cart-block--summary__count">{this.state.count} items</span>
          </a>
        </div>
        <div
          className={`cart-block--contents ${this.state.expanded ? ['cart-block--contents__expanded is-outside-horizontal'] : []}`}
          style={{
            display: this.state.expanded ? 'inherit' : 'none',
          }}
        >
          <div className="cart-block--contents__inner">
            <div className="cart-block--contents__items">
              {this.state.carts.map(cart => (
                <Cart cart={cart}/>
              ))}
            </div>
            <div className="cart-block--contents__links">
              <a href={`${baseUrl}/cart`}>View your cart</a>
            </div>
          </div>
        </div>
      </div>
    )
  }
}
export default CartBlock;
