const fs = require('fs');
const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const { WebpackManifestPlugin } = require('webpack-manifest-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');

const nodeEnv = (process.env.NODE_ENV || 'development').trim();

// eslint-disable-next-line
const __DEV__ = nodeEnv !== 'production';

const devtool = __DEV__ ? 'source-map' : undefined;

const plugins = [
  new webpack.DefinePlugin({
    'process.env': {
      NODE_ENV: JSON.stringify(nodeEnv),
    },
  }),
  new MiniCssExtractPlugin({
    filename: '[name]-[contenthash].css',
    chunkFilename: '[id]-[contenthash].css',
  }),
  new WebpackManifestPlugin({}),
  new CopyWebpackPlugin({
    patterns: [
      // Copy xterm files
      {
        from: 'node_modules/@xterm/xterm/**/*',
        to: 'special/[name][ext]',
        globOptions: {
          ignore: ['**/package.json', '**/README.md', '**/LICENSE'],
        },
      },
      // Copy Bootstrap CSS
      {
        from: 'node_modules/bootstrap/dist/css/**/*',
        to: 'special/bootstrap/css/[name][ext]',
      },
      // Copy Bootstrap JS  
      {
        from: 'node_modules/bootstrap/dist/js/**/*',
        to: 'special/bootstrap/js/[name][ext]',
      },
      // Copy Bootstrap Icons fonts
      {
        from: 'node_modules/bootstrap-icons/font/',
        to: 'special/bootstrap-icons/font/',
      },
      // Copy Bootstrap Icons SVGs
      {
        from: 'node_modules/bootstrap-icons/icons/**/*',
        to: 'special/bootstrap-icons/icons/[name][ext]',
      },
      // Copy custom install files
      {
        from: 'resources/assets/js/install.js',
        to: 'special/install.js',
      },
      {
        from: 'resources/assets/css/install.css',
        to: 'special/install.css',
      },
    ],
  }),
];

let themeFileNameRegex = /theme\d+/;

if (process.env.THEMES) {
  const themes = process.env.THEMES.replace(/,/g, '|');
  themeFileNameRegex = new RegExp(`theme(${themes})\\.`);
}

const themePath = path.resolve('resources/assets/themes');
const themeEntries = fs
  .readdirSync(themePath)
  .filter((fileName) => fileName.match(themeFileNameRegex))
  .reduce((entries, themeFileName) => {
    entries[path.parse(themeFileName).name] = `${themePath}/${themeFileName}`;
    return entries;
  }, {});

module.exports = {
  mode: __DEV__ ? 'development' : 'production',
  context: __dirname,
  stats: 'detailed',
  resolve: {
    extensions: ['.js'],
  },
  entry: {
    ...themeEntries,
    vendor: './resources/assets/js/vendor.js',
    purge: './resources/assets/js/admin/purge.js',
    dumpmanager: './resources/assets/js/admin/dumpmanager.js',
    certifications: './resources/assets/js/certifications.js',
    shiftManagerV2: './resources/assets/js/ShiftManagerV2.js',
    'shiftManagerV2-css': './resources/assets/css/ShiftManagerV2.css',
    digitalid: './resources/assets/js/digital-id.js',
    'digital-id-css': './resources/assets/css/digital-id.css',
  },
  output: {
    path: path.resolve('public/assets'),
    filename: '[name]-[contenthash].js',
    publicPath: '',
    clean: true,
  },
  optimization: {
    minimizer: __DEV__ ? [] : [new CssMinimizerPlugin(), new TerserPlugin()],
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /(node_modules)/,
        loader: 'babel-loader',
      },
      { test: /\.(jpg|eot|ttf|otf|svg|woff2?)(\?.*)?$/, type: 'asset/resource' },
      { test: /\.json$/, loader: 'json-loader' },
      {
        test: /\.(scss|css)$/,
        use: [
          { loader: MiniCssExtractPlugin.loader },
          { loader: 'css-loader' },
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: {
                plugins: [['autoprefixer']],
              },
            },
          },
          {
            loader: 'resolve-url-loader',
          },
          {
            loader: 'sass-loader',
            options: {
              sourceMap: true,
              sassOptions: {
                quietDeps: true,
              },
            },
          },
        ],
      },
    ],
  },
  plugins,
  devtool,
};
