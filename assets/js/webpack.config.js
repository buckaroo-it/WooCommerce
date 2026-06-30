const path = require('path');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');

module.exports = {
    entry: {
        applepay: './applepay/index.js',
        googlepay: './googlepay/index.js',
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
                        // Force the classic JSX runtime so JSX compiles to
                        // React.createElement (provided by the externalized
                        // window.React). Babel 8 defaults to the automatic
                        // runtime, which emits jsx/jsxDEV calls that fail at
                        // runtime ("jsxDEV is not a function") because the
                        // externalized React has no jsx-runtime. Every component
                        // imports React explicitly, so classic is the correct fit.
                        presets: [
                            '@babel/preset-env',
                            ['@babel/preset-react', { runtime: 'classic', development: false }],
                        ],
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
    plugins: [new WooCommerceDependencyExtractionWebpackPlugin()],
};
