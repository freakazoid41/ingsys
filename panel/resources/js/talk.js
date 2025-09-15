import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue'
import { useAuthStore } from '@/stores/auth';
import router from '@/router/talk';
import App from '@/layouts/App.vue';
import '../css/talk.css';
//import axios from 'axios';
import { i18nVue } from 'laravel-vue-i18n'; 



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
  .use(router)
  .mount('#app');

useAuthStore().getData();
useAuthStore().setData({
  'type'  : 'admin',
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

