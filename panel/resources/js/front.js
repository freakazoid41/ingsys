import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue'
import router from '@/router/front';
import App from '@/layouts/App.vue';
import '../css/front.css';
//import axios from 'axios';
import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n'; 




const pinia = createPinia();

const app = createApp(App)
  .use(pinia)
  .use(i18nVue, { 
        resolve: lang => {
          const langs = import.meta.glob('../../lang/*.json', { eager: true });
          return langs[`../../lang/${lang}.json`].default;
      },
    })
  .use(createHead())
  .use(router);

// load language based on document.documentElement.lang before mounting
const langFromDoc = (document && document.querySelector('html').lang)
  ? document.querySelector('html').lang
  : (navigator.language || navigator.userLanguage || 'en').split('-')[0];

loadLanguageAsync(langFromDoc)
  .then(() => {
    app.mount('#app');
  })
  .catch((err) => {
    console.error('Failed to load language', err);
    // mount anyway to avoid blocking the app
    app.mount('#app');
  });

/*const userStore = useAuthStore()
userStore.attempt_user()
  .catch((error) => {
    console.log('Please login.')
  })
  .finally(() => {
    app.use(router)
      .mount('#app');
  })*/

