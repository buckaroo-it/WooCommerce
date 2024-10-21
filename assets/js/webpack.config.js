const path = require('path');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');

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
    new WooCommerceDependencyExtractionWebpackPlugin(),
  ],
};