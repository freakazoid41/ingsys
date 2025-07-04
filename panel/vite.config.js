import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite'; 


export default defineConfig({
  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },
  build: {
    sourcemap: true,
  },
  plugins: [
    laravel({
      input: [
        'resources/js/app.js',
        'public/js/index.js',
        'public/js/vendor.js',
        'public/css/theme.css'
      ],
      refresh: true,
      // detectTls: 'vue-laravel-spa.test',
    }),
    vue({
      template: {
        transformAssetUrls: {
          // The Vue plugin will re-write asset URLs, when referenced
          // in Single File Components, to point to the Laravel web
          // server. Setting this to `null` allows the Laravel plugin
          // to instead re-write asset URLs to point to the Vite
          // server instead.
          base: null,

          // The Vue plugin will parse absolute URLs and treat them
          // as absolute paths to files on disk. Setting this to
          // `false` will leave absolute URLs un-touched so they can
          // reference assets in the public directory as expected.
          includeAbsolute: false,
        },
      },
    }),
    i18n() 
  ],
});
