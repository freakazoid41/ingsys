import { createRouter, createWebHistory } from "vue-router"
import { useAuthStore } from '@/stores/auth.js'


import Panel from '@/layouts/Panel.vue'

import PIndex from '@/pages/PIndex.vue'



/*import Documents from '@/pages/documents/Documents.vue'
import DocumentList from '@/pages/documents/DocumentList.vue'*/
import UserList from '@/pages/users/UsersList.vue'
import UserForm from '@/pages/users/UsersForm.vue'


import LinkList from '@/pages/links/LinkList.vue'
import LinkForm from '@/pages/links/LinkForm.vue'


const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/panel',
      component: Panel,
      //meta: { requiresAuth: true },
      children: [
        { path: "/panel", name: 'Index', component: PIndex },
       
        { path: "/panel/users", name: 'UserList', component: UserList },
        { path: "/panel/users/form/:id?", name: 'UserForm', component: UserForm },
        { path: "/panel/links/form/:id?", name: 'LinkForm', component: LinkForm },
        { path: "/panel/links",           name: 'LinkList', component: LinkList },
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
