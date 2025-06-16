import type { RouterConfig } from '@nuxt/schema'

export default {
  // https://router.vuejs.org/api/interfaces/routeroptions.html#routes
  routes: (_routes) => [
    {
        name: 'home',
        path: '/',
        component: () => import('~/pages/index.vue')
    },{
        name: 'documents',
        path: '/documents',
        component: () => import('~/pages/documents/index.vue')
    }
  ],
} satisfies RouterConfig