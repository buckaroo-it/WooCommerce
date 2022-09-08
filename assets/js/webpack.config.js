const path = require('path');

const applepay =  {
  entry: './applepay/index.js',
  mode: 'production',
  output: {
    filename: './apple-pay.js',
    path: path.resolve(__dirname, 'dist'),
  },
};

const checkout =  {
  entry: './checkout/index.js',
  mode: 'production',
  output: {
    filename: './checkout.js',
    path: path.resolve(__dirname, 'dist'),
  },
};

module.exports = [
  applepay,
  checkout
]