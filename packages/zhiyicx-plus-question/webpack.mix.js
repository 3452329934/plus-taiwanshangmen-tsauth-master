let mix = require("laravel-mix");

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

mix.disableNotifications();
mix.sourceMaps(!mix.inProduction());
mix.setResourceRoot('/assets/question-answer/');

// More documents see: https://laravel.com/docs/master/mix
if (mix.inProduction()) {
  mix.setPublicPath('assets');
  mix.js('resources/assets/admin/index.js', 'assets/admin.js');
  
// Dev build.
} else {
  mix.setPublicPath('../../public/assets/question-answer/');
  if (mix.config.hmr === true) {
    mix.setResourceRoot('/');
  }
  mix.js('resources/assets/admin/index.js', '../../public/assets/question-answer/admin.js');
}

