import { defineStore } from 'pinia'
import Plib from '@/lib/pickle'
import { useAuthStore } from '@/stores/auth'

const canSeeRoles = () => {
  try { return !!useAuthStore().permissions?.includes('per-04-03'); }
  catch (e) { return false; }
};

export const usePermissionDataStore = defineStore('permissiondata', {
  state: () => ({
    items: [],
    roleTemplates: []
  }),
  getters: {
    asJson: (state) => JSON.stringify(state.items, null, 2),
    list: (state) => state.items,
    byOpKey: (state) => (opKey) => state.items.find((item) => item.op_key === opKey),
    roleList: (state) => state.roleTemplates
  },
  actions: {
    async fetchRoleTemplates() {
      // role catalog needs per-04-03 — skip silently for suppliers (was noisy 401s)
      if (!canSeeRoles()) { this.roleTemplates = []; return []; }
      try {
        const response = await new Plib().request({ url: '/api/v1/roles/templates', method: 'get' })
        if (response && response.success && Array.isArray(response.data)) {
          this.roleTemplates = response.data
          return response.data
        }
      } catch (e) {
        console.error('permissiondata fetchRoleTemplates failed', e)
      }
      this.roleTemplates = []
      return []
    },
    async fetchRoleItems() {
      if (!canSeeRoles()) { this.items = []; return []; }
      try {
        const response = await new Plib().request({ url: '/api/v1/roles/items', method: 'get' })
        if (response && response.success && Array.isArray(response.data)) {
          this.items = response.data
          return response.data
        }
      } catch (e) {
        console.error('permissiondata fetchRoleItems failed', e)
      }
      this.items = []
      return []
    },
    setList(list) {
      this.items = Array.isArray(list) ? list : []
    },
    setRoleList(list) {
      this.roleTemplates = Array.isArray(list) ? list : []
    },
    addItem(item) {
      if (item && item.op_key) {
        this.items.push(item)
      }
    },
    remove(opKey) {
      this.items = this.items.filter((item) => item.op_key !== opKey)
    },
    loadFromJson(json) {
      try {
        const parsed = JSON.parse(json)
        if (Array.isArray(parsed)) {
          this.items = parsed
        }
      } catch (e) {
        console.error('permissiondata loadFromJson failed', e)
      }
    }
  }
})
