import { createRouter, createWebHistory } from "vue-router"
import { useAuthStore } from '@/stores/auth.js'


import Talk from '@/layouts/Talk.vue'

import TIndex from '@/pages/talk/TIndex.vue'
import FList from '@/pages/talk/flats/FList.vue'
import FForm from '@/pages/talk/flats/FForm.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/talkpanel',
      component: Talk,
      //meta: { requiresAuth: true },
      children: [
        { path: "/talkpanel", name: 'Index', component: TIndex },
        { path: "/talkpanel/flist", name: 'FList', component: FList },
        { path: "/talkpanel/fform", name: 'FForm', component: FForm },

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
