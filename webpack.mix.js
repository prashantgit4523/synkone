const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */
mix.disableNotifications()
mix.js('resources/js/app.js', 'public/js')
    .react()
    .sass('resources/sass/app.scss', 'public/css').webpackConfig({
        resolve: {
            fallback: {
                crypto: false
            }
        },
});

// enable versioning of js files on production
if (mix.inProduction()) {
    mix.version();
}

// Extract all node_modules vendor libraries into a vendor.js file.
mix.extract();


// js uglify options
mix.options({
    uglify: {
        uglifyOptions: {
            sourceMap: true,
            compress: {
                warnings: false,
                screw_ie8: true,
                conditionals: true,
                unused: true,
                comparisons: true,
                sequences: true,
                dead_code: true,
                evaluate: true,
                if_return: true,
                join_vars: true,
            },
            output: {
                comments: false,
                beautify: false,
            },
        },
    }
});



// this do exact same thing as mix.extract() , but we can further explore

// mix.webpackConfig({
//     resolve: {
//         fallback: {
//             crypto: false
//         }
//     },
//     optimization: {
//         runtimeChunk: {
//             name: '/js/runtime',
//         },
//         splitChunks: {
//             cacheGroups: {
//                 vendor: {
//                 test: /[\\/]node_modules[\\/]/,
//                 name: '/js/vendor',
//                 chunks: 'all',
//                 },
//             },
//         },
//         providedExports: false,
//         sideEffects: false,
//         usedExports: false
//     },
// });