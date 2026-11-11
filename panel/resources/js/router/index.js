import { createRouter, createWebHistory } from "vue-router"
import { useAuthStore } from '@/stores/auth.js'


//coalRoutes
import CoalPanel from '@/layouts/CoalPanel.vue';
import CIndex from '@/pages/coalsystem/Dashboard.vue';

import CList from "@/pages/coalsystem/Client/CList.vue";
import CForm from "@/pages/coalsystem/Client/CForm.vue";
import PCList from "@/pages/coalsystem/ProductCategory/PCList.vue";
import PCForm from "@/pages/coalsystem/ProductCategory/PCForm.vue";
import PList from "@/pages/coalsystem/Product/PList.vue";
import PForm from "@/pages/coalsystem/Product/PForm.vue";
import UList from "@/pages/coalsystem/Users/UList.vue";
import UForm from "@/pages/coalsystem/Users/UForm.vue";
import TreeExample from "@/pages/coalsystem/treeTest.vue";
import Roles from "@/pages/coalsystem/Roles/Roles.vue";
import DList from "@/pages/coalsystem/Documents/DList.vue";
import DForm from "@/pages/coalsystem/Documents/DForm.vue";
import NSettings from "@/pages/coalsystem/Notifications/NSettings.vue";
import MList from "@/pages/coalsystem/Standardization/MList.vue";
import LList from "@/pages/coalsystem/Logs/LList.vue";
import NList from "@/pages/coalsystem/NotificationLogs/NList.vue";
// Order Management System (new)
import OrderList from "@/pages/coalsystem/Order/OList.vue";
import OrderForm from "@/pages/coalsystem/Order/OForm.vue";
// Tedarik Public Panel
import TedarikPanel from '@/layouts/TedarikPanel.vue';
import TedarikDashboard from '@/pages/tedarik/Dashboard.vue';
import TedarikBilgilendirmeler from '@/pages/tedarik/Bilgilendirmeler.vue';
// Malzeme Planlama (empty shell)
import PlanlamaPanel from '@/layouts/PlanlamaPanel.vue';
import PlanlamaDashboard from '@/pages/planlama/Dashboard.vue';
import PlanlamaRList from '@/pages/planlama/Request/RList.vue';
import PlanlamaRForm from '@/pages/planlama/Request/RForm.vue';
import PlanlamaFList from '@/pages/planlama/TalepDoc/TList.vue';
import PlanlamaFForm from '@/pages/planlama/TalepDoc/TForm.vue';
import PlanlamaBilgilendirmeler from '@/pages/planlama/Bilgilendirmeler.vue';
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
        // legacy aliases — dashboard still pushes 'OList' / 'RequestList' (coal era) → keep alive to avoid No match
        { path: "/coalpanel/OList", name: 'OList', redirect: { name: 'OrderList' } },
        { path: "/coalpanel/request", name: 'RequestList', redirect: { name: 'OrderList' } },
        
        { path: "/coalpanel/client", name: 'CList', component: CList},
        { path: "/coalpanel/client/form/:id?", name: 'CForm', component: CForm},
        { path: "/coalpanel/product-categories", name: 'PCList', component: PCList},
        { path: "/coalpanel/product-categories/form/:id?", name: 'PCForm', component: PCForm},
        { path: "/coalpanel/products", name: 'PList', component: PList},
        { path: "/coalpanel/products/form/:id?", name: 'PForm', component: PForm},
        { path: "/coalpanel/users", name: 'UList', component: UList},
        { path: "/coalpanel/users/form/:id?", name: 'UForm', component: UForm},
        { path: "/coalpanel/roles", name: 'Roles', component: Roles},
        { path: "/coalpanel/documents", name: 'DList', component: DList},
        { path: "/coalpanel/documents/:id", name: 'DForm', component: DForm},
        { path: "/coalpanel/sistem-loglari", name: 'LList', component: LList},
        { path: "/coalpanel/notifikasyon-loglari", name: 'NList', component: NList},
        { path: "/coalpanel/mudurlukler", name: 'MList', component: MList },
        { path: "/coalpanel/planlama", name: 'PlanlamaList', component: PlanlamaDashboard },
        { path: "/coalpanel/planlama/talepler", name: 'PlanlamaRListAdmin', component: PlanlamaRList },
        { path: "/coalpanel/planlama/talepler/form/:id?", name: 'PlanlamaRFormAdmin', component: PlanlamaRForm },
        { path: "/coalpanel/planlama/talep-dokumanlari", name: 'PlanlamaFListAdmin', component: PlanlamaFList },
        { path: "/coalpanel/planlama/talep-dokumanlari/form/:id?", name: 'PlanlamaFFormAdmin', component: PlanlamaFForm },
      ]
    },
    {
      path: '/planlamapanel',
      component: PlanlamaPanel,
      children: [
        { path: "/planlamapanel", name: 'PlanlamaDashboard', component: PlanlamaDashboard },
        { path: "/planlamapanel/talepler", name: 'PlanlamaRList', component: PlanlamaRList },
        { path: "/planlamapanel/talepler/form/:id?", name: 'PlanlamaRForm', component: PlanlamaRForm },
        { path: "/planlamapanel/talep-dokumanlari", name: 'PlanlamaFList', component: PlanlamaFList },
        { path: "/planlamapanel/talep-dokumanlari/form/:id?", name: 'PlanlamaFForm', component: PlanlamaFForm },
        { path: "/planlamapanel/bilgilendirmeler", name: 'PlanlamaBilgilendirmeler', component: PlanlamaBilgilendirmeler },
      ]
    },
    {
      path: '/tedarikpanel',
      component: TedarikPanel,
      children: [
        { path: "/tedarikpanel", name: 'TedarikDashboard', component: TedarikDashboard },
        { path: "/tedarikpanel/bilgilendirmeler", name: 'TedarikBilgilendirmeler', component: TedarikBilgilendirmeler },
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
router.beforeEach((to, from, next) => {
    closeAsideDrawer();
    try {
        const authStore = useAuthStore();
        const perms = authStore.permissions || [];
        // Tedarik Doküman guard: per-07 required
        if (to.path.startsWith('/tedarikpanel/documents') && perms.length) {
            const hasDoc = perms.includes('per-07') || perms.includes('per-07-01');
            if (!hasDoc) return next('/tedarikpanel/orders');
        }
        // Tedarik Sipariş guard: per-05 required
        if (to.path.startsWith('/tedarikpanel/orders') && perms.length) {
            const hasOrder = perms.includes('per-05') || perms.includes('per-05-01');
            if (!hasOrder) return next('/tedarikpanel');
        }
        // Planlama guard: per-041-05 required
        if ((to.path.startsWith('/planlamapanel') || to.path.startsWith('/coalpanel/planlama')) && perms.length) {
            if (!perms.includes('per-041-05')) return next('/coalpanel');
        }
        // Talep Dökümanı guard: view needs any of 05-01/05-03/05-04 (approve/upload/standardize); enter-new stays 05-03 via no query/from + button gates
        if ((to.path.includes('/talep-dokumanlari')) && perms.length) {
            const ok = perms.includes('per-041-05-01') || perms.includes('per-041-05-03') || perms.includes('per-041-05-04');
            if (!ok) return next('/coalpanel/planlama/talepler');
        }
        // İhtiyaç Listesi create guard: per-041-05-02 (İhtiyaç Oluşturma) required — enter only
        if ((/\/talepler\/form$/.test(to.path)) && perms.length) {
            if (!perms.includes('per-041-05-02')) return next('/coalpanel/planlama/talepler');
        }
    } catch(e) {}
    next();
});
router.afterEach(() => {
    closeAsideDrawer();
    setTimeout(closeAsideDrawer, 50);
    setTimeout(closeAsideDrawer, 300);
});

export default router;
