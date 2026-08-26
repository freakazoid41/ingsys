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
    //console.log(authStore.currentStatus)
    if (authStore.typeKey == 'op-pert-reseller' && !authStore.currentStatus.canProceed) {
      router.push('/coalpanel/client/form/'+authStore.currentStatus.clientQnid); // Redirect to a "no access" route
    }
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

