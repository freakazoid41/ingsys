import { createRouter, createWebHistory } from "vue-router"
import { useAuthStore } from '@/stores/auth.js'


//coalRoutes
import CoalPanel from '@/layouts/CoalPanel.vue';
import CIndex from '@/pages/coalsystem/Dashboard.vue';
import ExampleList from "@/pages/coalsystem/Example/FlatList.vue";
import ExampleForm from "@/pages/coalsystem/Example/FlatForm.vue";
import RList from "@/pages/coalsystem/Request/RList.vue";
import RForm from "@/pages/coalsystem/Request/RForm.vue";
import CList from "@/pages/coalsystem/Client/CList.vue";
import CForm from "@/pages/coalsystem/Client/CForm.vue";
import UList from "@/pages/coalsystem/Users/UList.vue";
import UForm from "@/pages/coalsystem/Users/UForm.vue";
import TreeExample from "@/pages/coalsystem/treeTest.vue";
import Roles from "@/pages/coalsystem/Roles/Roles.vue";
import DList from "@/pages/coalsystem/Documents/DList.vue";
import OList from "@/pages/coalsystem/Offer/OList.vue";
import OForm from "@/pages/coalsystem/Offer/OForm.vue";
import NSettings from "@/pages/coalsystem/Notifications/NSettings.vue";
import LList from "@/pages/coalsystem/Logs/LList.vue";
import NList from "@/pages/coalsystem/NotificationLogs/NList.vue";
// Order Management System (new)
import OrderList from "@/pages/coalsystem/Order/OList.vue";
import OrderForm from "@/pages/coalsystem/Order/OForm.vue";
import TransferList from "@/pages/coalsystem/Transfer/TList.vue";
import TransferForm from "@/pages/coalsystem/Transfer/TForm.vue";
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
        { path: "/coalpanel/example", name: 'ExampleList', component: ExampleList},
        { path: "/coalpanel/example/form/:id?", name: 'ExampleForm', component: ExampleForm},
        { path: "/coalpanel/request", name: 'RequestList', component: RList},
        { path: "/coalpanel/request/form/:id?", name: 'RequestForm', component: RForm},
        { path: "/coalpanel/orders", name: 'OrderList', component: OrderList},
        { path: "/coalpanel/orders/form/:id?", name: 'OrderForm', component: OrderForm},
        { path: "/coalpanel/transfers", name: 'TransferList', component: TransferList},
        { path: "/coalpanel/transfers/form/:id?", name: 'TransferForm', component: TransferForm},
        { path: "/coalpanel/client", name: 'CList', component: CList},
        { path: "/coalpanel/client/form/:id?", name: 'CForm', component: CForm},
        { path: "/coalpanel/users", name: 'UList', component: UList},
        { path: "/coalpanel/users/form/:id?", name: 'UForm', component: UForm},
        { path: "/coalpanel/roles", name: 'Roles', component: Roles},
        { path: "/coalpanel/documents", name: 'DList', component: DList},
        { path: "/coalpanel/offer", name: 'OList', component: OList},
        { path: "/coalpanel/offer/form/:id?", name: 'OForm', component: OForm},
        { path: "/coalpanel/sistem-loglari", name: 'LList', component: LList},
        { path: "/coalpanel/notifikasyon-loglari", name: 'NList', component: NList},
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
