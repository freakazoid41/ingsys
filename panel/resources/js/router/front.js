import { createRouter, createWebHistory } from "vue-router"
import { useNavigationStore } from '@/stores/navigation.js'


import Front from '@/layouts/Front.vue';

import FIndex from '@/pages/front/FIndex.vue';
import VShow  from '@/pages/front/VShow.vue';
import Quiz   from '@/pages/front/Quiz.vue';
import IWarn  from '@/pages/front/IWarn.vue';
import IGiven from '@/pages/front/IGiven.vue';
import IOwned from '@/pages/front/IOwned.vue';
import EShow  from '@/pages/front/EShow.vue';
import EMessage  from '@/pages/front/EMessage.vue';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/facility',
      component: Front,
      //meta: { requiresAuth: true },
      children: [
        { path: "/facility/:id?",             name: 'Index', component: FIndex },
        { path: "/facility/:id?/quiz",        name: 'VShow', component: VShow },
        { path: "/facility/:id?/quiz/:quiz",  name: 'Quiz',  component: Quiz },
        { path: "/facility/:id?/imessage",    name: 'IWarn', component: IWarn },
        { path: "/facility/:id?/inventory",   name: 'IGiven', component: IGiven },
        { path: "/facility/:id?/myinventory", name: 'IOwned', component: IOwned },
        { path: "/facility/:id?/exit",        name: 'EShow', component: EShow },
        { path: "/facility/:id?/emessage",    name: 'EMessage', component: EMessage },

        /*{ path: "/secpanel/flist", name: 'FList', component: FList },
        { path: "/secpanel/flist/form/:id?", name: 'FForm', component: FForm },
        { path: "/secpanel/inventory", name: 'IList', component: IList },
        { path: "/secpanel/inventory/form/:id?", name: 'IForm', component: IForm },
        { path: "/secpanel/visit", name: 'VList', component: VList },
        { path: "/secpanel/visit/form/:id?", name: 'VForm', component: VForm },
        { path: "/secpanel/users", name: 'UList', component: UList },
        { path: "/secpanel/users/form/:id?", name: 'UForm', component: UForm },  */  
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

router.beforeEach((to, from) => {
  const navStore = useNavigationStore();
  
  
  if((to.name == 'EShow' || to.name == 'VShow') && document.querySelector('[name="qnid"]').value !== ''){
    // here we entered already and awaiting exit transactions 
    // set some informations about us
    navStore.currentUser = {
      phone : document.querySelector('[name="phonedata"]').value,
      conn  : document.querySelector('[name="connkey"]').value,
      qnid  : document.querySelector('[name="qnid"]').value,
    }
  }

  
  // do not let anyone enter other routes if not filled user form
  if(to.name != 'Index' && Object.keys(navStore.currentUser).length == 0) return { name: 'Index' , params: { id  : document.querySelector('[name="facility"]').value } , replace: true};
  
  // ...
  // explicitly return false to cancel the navigation
  return true;
})



export default router;
