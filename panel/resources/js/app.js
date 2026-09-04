import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue'
import { useAuthStore } from '@/stores/auth';
import { usePermissionDataStore } from '@/stores/permissiondata';
import router from '@/router/index';
import App from '@/layouts/App.vue';
import '../css/app.css';
import breadcrumbsPlugin from '@/plugins/breadcrumbs';
//import axios from 'axios';
import { i18nVue } from 'laravel-vue-i18n'; 


/*window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Content-Type'] = 'application/json';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;*/

const pinia = createPinia();
const authStore = useAuthStore(pinia);
const permissionDataStore = usePermissionDataStore(pinia);

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
  .use(breadcrumbsPlugin);

const initApp = async () => {
  try {
    await authStore.getPermissions();

    await Promise.all([
      permissionDataStore.fetchRoleTemplates(),
      permissionDataStore.fetchRoleItems(),
    ]);
    // canProceed redirect removed for INGSYS — reseller firma form no longer blocks navigation
  } catch (e) {
    console.error('app init failed:', e);
  }

  authStore.startHeartbeat();
  app.mount('#app');
};

initApp();

authStore.setData({
  type: 'admin',
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

