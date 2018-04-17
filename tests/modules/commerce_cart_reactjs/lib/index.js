import React from 'react';
import { render } from 'react-dom';
import CartBlock from "./components/cart-block";
import CartForm from "./components/cart-form";

if (document.getElementById('reactCartBlock')) {
  render(<CartBlock/>, document.getElementById('reactCartBlock'));
}

if (document.getElementById('reactCartForm')) {
  render(<CartForm/>, document.getElementById('reactCartForm'));
}
