import React from 'react';
import { render } from 'react-dom';
import CartBlock from "./components/cart-block";

if (document.getElementById('reactCartBlock')) {
  render(<CartBlock/>, document.getElementById('reactCartBlock'));
}
