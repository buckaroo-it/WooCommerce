const path = require('path');

const applepay =  {
  entry: './applepay/index.js',
  output: {
    filename: './apple-pay.js',
    path: path.resolve(__dirname, 'dist'),
  },
};

const checkout =  {
  entry: './checkout/index.js',
  output: {
    filename: './checkout.js',
    path: path.resolve(__dirname, 'dist'),
  },
};

module.exports = [
  applepay,
  checkout
]