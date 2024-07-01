const path = require('path');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');

// WooCommerce dependency maps
const wcDepMap = {
  '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
  '@woocommerce/settings': ['wc', 'wcSettings'],
};

const wcHandleMap = {
  '@woocommerce/blocks-registry': 'wc-blocks-registry',
  '@woocommerce/settings': 'wc-settings',
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

module.exports = {
  entry: {
    applepay: './applepay/index.js',
    checkout: './checkout/index.js',
    blocks: './blocks/index.js', // Adjust this path to your blocks index.js
  },
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: '[name].js', // Output each entry to a unique file
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/, // Allow both .js and .jsx files
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react'], // Add React preset for JSX
          },
        },
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader'],
      },
    ],
  },
  resolve: {
    extensions: ['.js', '.jsx'], // Resolve both .js and .jsx
  },
  plugins: [
    new WooCommerceDependencyExtractionWebpackPlugin({
      requestToExternal,
      requestToHandle,
    }),
  ],
};