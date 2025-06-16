import { createRouter, createWebHistory } from "vue-router"
import { useAuthStore } from '@/stores/auth.js'


import Panel from '@/layouts/Panel.vue'

import PIndex from '@/pages/PIndex.vue'
import User from '@/pages/User.vue'
import Calendar from '@/pages/events/Calendar.vue'
import CalendarForm from '@/pages/events/CalendarForm.vue'


import Transactions from '@/pages/Transactions.vue'
/*import Documents from '@/pages/documents/Documents.vue'
import DocumentList from '@/pages/documents/DocumentList.vue'*/
import UserList from '@/pages/users/UsersList.vue'
import UserForm from '@/pages/users/UsersForm.vue'

import FlatList from '@/pages/flats/FlatList.vue'
import FlatForm from '@/pages/flats/FlatForm.vue'

import TargetList from '@/pages/targets/TargetList.vue'
import TargetForm from '@/pages/targets/TargetForm.vue'

import MeetingList from '@/pages/meetings/MeetingList.vue'
import MeetingForm from '@/pages/meetings/MeetingForm.vue'

import ProjectList from '@/pages/projects/ProjectList.vue'
import ProjectForm from '@/pages/projects/ProjectForm.vue'

import DocumentList from '@/pages/documentFiles/DocumentList.vue'


const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/panel',
      component: Panel,
      //meta: { requiresAuth: true },
      children: [
        { path: "/panel", name: 'Index', component: PIndex },
        { path: "/panel/user", name: 'User', component: User },
        { path: "/panel/documents", name: 'DocumentList', component: DocumentList },
        { path: "/panel/calendar", name: 'Calendar', component: Calendar },
        { path: "/panel/calendar/form/:id?", name: 'CalendarForm', component: CalendarForm },
        { path: "/panel/users", name: 'UserList', component: UserList },
        { path: "/panel/users/form/:id?", name: 'UserForm', component: UserForm },
        { path: "/panel/transactions", name: 'Transactions', component: Transactions },
        { path: "/panel/flats", name: 'FlatList', component: FlatList },
        { path: "/panel/flats/form/:id?", name: 'FlatForm', component: FlatForm },
        { path: "/panel/targets", name: 'TargetList', component: TargetList },
        { path: "/panel/targets/form/:id?", name: 'TargetForm', component: TargetForm },
        { path: "/panel/meetings", name: 'MeetingList', component: MeetingList },
        { path: "/panel/meetings/form/:id?", name: 'MeetingForm', component: MeetingForm },
        { path: "/panel/projects", name: 'ProjectList', component: ProjectList },
        { path: "/panel/projects/form/:id?", name: 'ProjectForm', component: ProjectForm }
        /*{ path: "/panel/documents", name: 'DocumentList', component: DocumentList },
        { path: "/panel/documents/form/:id?", name: 'Documents', component: Documents },*/

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
