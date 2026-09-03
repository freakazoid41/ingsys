import { createRouter, createWebHistory } from "vue-router"
import { useAuthStore } from '@/stores/auth.js'


//coalRoutes
import CoalPanel from '@/layouts/CoalPanel.vue';
import CIndex from '@/pages/coalsystem/Dashboard.vue';

import CList from "@/pages/coalsystem/Client/CList.vue";
import CForm from "@/pages/coalsystem/Client/CForm.vue";
import UList from "@/pages/coalsystem/Users/UList.vue";
import UForm from "@/pages/coalsystem/Users/UForm.vue";
import TreeExample from "@/pages/coalsystem/treeTest.vue";
import Roles from "@/pages/coalsystem/Roles/Roles.vue";
import DList from "@/pages/coalsystem/Documents/DList.vue";
import DForm from "@/pages/coalsystem/Documents/DForm.vue";
import NSettings from "@/pages/coalsystem/Notifications/NSettings.vue";
import LList from "@/pages/coalsystem/Logs/LList.vue";
import NList from "@/pages/coalsystem/NotificationLogs/NList.vue";
// Order Management System (new)
import OrderList from "@/pages/coalsystem/Order/OList.vue";
import OrderForm from "@/pages/coalsystem/Order/OForm.vue";
// Tedarik Public Panel
import TedarikPanel from '@/layouts/TedarikPanel.vue';
import TedarikDashboard from '@/pages/tedarik/Dashboard.vue';
const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/coalpanel',
      component: CoalPanel,
      //meta: { requiresAuth: true },
      children: [
        
        
        { path: "/coalpanel", name: 'CIndex', component: CIndex, },
        { path: "/coalpanel/notifications/settings", name: 'NSettings', component: NSettings},
        { path: "/coalpanel/treeexample", name: 'TreeExample', component: TreeExample},
        
        
        { path: "/coalpanel/orders", name: 'OrderList', component: OrderList},
        { path: "/coalpanel/orders/form/:id?", name: 'OrderForm', component: OrderForm},
        
        { path: "/coalpanel/client", name: 'CList', component: CList},
        { path: "/coalpanel/client/form/:id?", name: 'CForm', component: CForm},
        { path: "/coalpanel/users", name: 'UList', component: UList},
        { path: "/coalpanel/users/form/:id?", name: 'UForm', component: UForm},
        { path: "/coalpanel/roles", name: 'Roles', component: Roles},
        { path: "/coalpanel/documents", name: 'DList', component: DList},
        { path: "/coalpanel/documents/:id", name: 'DForm', component: DForm},
        { path: "/coalpanel/sistem-loglari", name: 'LList', component: LList},
        { path: "/coalpanel/notifikasyon-loglari", name: 'NList', component: NList},
      ]
    },
    {
      path: '/tedarikpanel',
      component: TedarikPanel,
      children: [
        { path: "/tedarikpanel", name: 'TedarikDashboard', component: TedarikDashboard },
        { path: "/tedarikpanel/orders", name: 'TedarikOrderList', component: OrderList },
        { path: "/tedarikpanel/orders/form/:id?", name: 'TedarikOrderForm', component: OrderForm },
        { path: "/tedarikpanel/documents", name: 'TedarikDList', component: DList },
        { path: "/tedarikpanel/documents/:id", name: 'TedarikDForm', component: DForm },
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

function closeAsideDrawer(){
    const aside = document.getElementById('kt_aside');
    try {
        if (window.KTDrawer && window.KTDrawer.getInstance && aside) {
            const inst = window.KTDrawer.getInstance(aside);
            if (inst && typeof inst.hide === 'function') inst.hide();
        }
    } catch (e) {}
    if (aside) aside.classList.remove('drawer-on');
    document.body.classList.remove('drawer-on');
    document.body.removeAttribute('data-kt-drawer');
    document.body.removeAttribute('data-kt-drawer-aside');
    document.querySelectorAll('.drawer-overlay').forEach(el => el.remove());
}
router.beforeEach((to, from, next) => { closeAsideDrawer(); next(); });
router.afterEach(() => {
    closeAsideDrawer();
    setTimeout(closeAsideDrawer, 50);
    setTimeout(closeAsideDrawer, 300);
});

export default router;
