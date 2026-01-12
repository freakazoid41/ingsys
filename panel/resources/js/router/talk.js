import { createRouter, createWebHistory } from "vue-router";

import Talk from '@/layouts/Talk.vue'

import TIndex from '@/pages/talk/TIndex.vue'
import FList from '@/pages/talk/facility/FList.vue'
import FForm from '@/pages/talk/facility/FForm.vue'

import LList from '@/pages/talk/logs/LList.vue'

import UList from '@/pages/talk/users/UList.vue'
import UForm from '@/pages/talk/users/UForm.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/kontent',
      component: Talk,
      //meta: { requiresAuth: true },
      children: [
        { path: "/kontent", name: 'Index', component: TIndex },
        { path: "/kontent/flist", name: 'FList', component: FList },
        { path: "/kontent/flist/form/:id?", name: 'FForm', component: FForm },

        { path: "/kontent/logs", name: 'LList', component: LList },
        
        { path: "/kontent/users", name: 'UList', component: UList },
        { path: "/kontent/users/form/:id?", name: 'UForm', component: UForm },    
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
