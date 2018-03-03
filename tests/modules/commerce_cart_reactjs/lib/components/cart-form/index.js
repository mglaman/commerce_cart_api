import React, { Component } from 'react';
import {baseUrl} from "../../utils";
import superagent from 'superagent';
import Cart from "./cart";

class CartForm extends Component {
  constructor(props) {
    super(props);
    this.state = {
      loaded: false,
      expanded: false,
      carts: [],
    };
  }
  getCarts() {
    const url = `${baseUrl}/cart?_format=json`;
    superagent
      .get(url)
      .end((err, { body }) => {
        this.setState({
          loaded: true,
          carts: body.length > 0 ? body : [],
        });
      })
  }
  componentDidMount() {
    this.getCarts();
  }
  render() {
    if (!this.state.loaded) {
      return null;
    }
    return(
      <div>
        {this.state.carts.map(cart => (
          <div key={cart.order_id}>
            <Cart cart={cart}/>
          </div>
        ))}
      </div>
    )
  }
}
export default CartForm;
