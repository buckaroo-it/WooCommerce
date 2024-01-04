const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');

const wcDepMap = {
  '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
  '@woocommerce/settings': ['wc', 'wcSettings']
};

const wcHandleMap = {
  '@woocommerce/blocks-registry': 'wc-blocks-registry',
  '@woocommerce/settings': 'wc-settings'
};

const requestToExternal = (request) => {
  if (wcDepMap[request]) {
    return wcDepMap[request];
  }
};

const requestToHandle = (request) => {
  if (wcHandleMap[request]) {
    return wcHandleMap[request];
  }
};

// Define your existing entries for applepay and checkout
const applepay = {
  entry: './applepay/index.js',
  output: {
    filename: './apple-pay.js',
    path: path.resolve(__dirname, 'dist'),
  },
};

const checkout = {
  entry: './checkout/index.js',
  output: {
    filename: './checkout.js',
    path: path.resolve(__dirname, 'dist'),
  },
};

// New entry for blocks with Babel configuration for JSX
const blocks = {
  entry: {
    'blocks': './blocks/index.js', // Adjust the path to your blocks index.js
  },
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: '[name].js',
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react'] // Add React preset
          }
        }
      }
    ]
  },
  plugins: [
    ...defaultConfig.plugins.filter(
        (plugin) =>
            plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
    ),
    new WooCommerceDependencyExtractionWebpackPlugin({
      requestToExternal,
      requestToHandle
    })
  ]
};

// Export the merged configuration
module.exports = [
  applepay,
  checkout,
  blocks
];
