import { createRouter, createWebHistory } from "vue-router";

import Talk from '@/layouts/Talk.vue'

import TIndex from '@/pages/talk/TIndex.vue'
import FList from '@/pages/talk/facility/FList.vue'
import FForm from '@/pages/talk/facility/FForm.vue'

import IList from '@/pages/talk/inventory/IList.vue'
import IForm from '@/pages/talk/inventory/IForm.vue'

import VList from '@/pages/talk/visit/VList.vue'
import VForm from '@/pages/talk/visit/VForm.vue'

import UList from '@/pages/talk/users/UList.vue'
import UForm from '@/pages/talk/users/UForm.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/secpanel',
      component: Talk,
      //meta: { requiresAuth: true },
      children: [
        { path: "/secpanel", name: 'Index', component: TIndex },
        { path: "/secpanel/flist", name: 'FList', component: FList },
        { path: "/secpanel/flist/form/:id?", name: 'FForm', component: FForm },
        { path: "/secpanel/inventory", name: 'IList', component: IList },
        { path: "/secpanel/inventory/form/:id?", name: 'IForm', component: IForm },
        { path: "/secpanel/visit", name: 'VList', component: VList },
        { path: "/secpanel/visit/form/:id?", name: 'VForm', component: VForm },
        { path: "/secpanel/users", name: 'UList', component: UList },
        { path: "/secpanel/users/form/:id?", name: 'UForm', component: UForm },    
      ]
    },
    /*{
      path: '/panel/auth',
      //redirect: "/login",
      component: Empty,
      //meta: { isGuest: true },
      children: [
        { path: "/panel/auth/login", name: 'Login', component: Login },
      ]
    },*/
    /*{
      path: '/:pathMatch(.*)*',
      name: '404',
      component: NotFound,
    }*/
  ],
});

/*router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.currentUser) {
    next({ name: "Login" })
  } else if (to.meta.isGuest && authStore.currentUser) {
    next({ name: "Home" })
  } else {
    next();
  }
});*/

export default router;
