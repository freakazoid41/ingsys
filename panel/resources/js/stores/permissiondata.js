import { defineStore } from 'pinia'
import Plib from '@/lib/pickle'

export const usePermissionDataStore = defineStore('permissiondata', {
  state: () => ({
    items: [
      {
        parent_id: 0,
        title: 'Mail Bildirimleri',
        ttitle: 'Perm_con_ops',
        ctitle: 'type_id',
        group_key: 'op-perm',
        op_key: 'per-00',
        childs: [
          {
            parent_id: 0,
            title: 'Tedarikçi Kayıt Başvurusu',
            ttitle: 'Perm_con_ops',
            ctitle: 'type_id',
            op_key: 'per-00-01'
          }
        ]
      },
      {
        parent_id: 0,
        title: 'Kontrol Paneli',
        ttitle: 'Perm_con_ops',
        ctitle: 'type_id',
        group_key: 'op-perm',
        op_key: 'per-04',
        childs: [
          { parent_id: 0, title: 'Kullanıcı Listeleme', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-04-01' },
          { parent_id: 0, title: 'Kullanıcı Oluşturma / Düzenleme', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-04-02' },
          { parent_id: 0, title: 'Rol ve Yetki Yönetimi', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-04-03' }
        ]
      },{
        parent_id: 0,
        title: 'Talep Yönetimi',
        ttitle: 'Perm_con_ops',
        ctitle: 'type_id',
        group_key: 'op-perm',
        op_key: 'per-05',
        childs: [
          { parent_id: 0, title: 'Talep Listeleme', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-05-01' },
          { parent_id: 0, title: 'Talep Oluşturma / Düzenleme', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-05-02' },
        ]
      },{
        parent_id: 0,
        title: 'Firma Yönetimi',
        ttitle: 'Perm_con_ops',
        ctitle: 'type_id',
        group_key: 'op-perm',
        op_key: 'per-06',
        childs: [
          { parent_id: 0, title: 'Firma Listeleme', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-06-01' },
          { parent_id: 0, title: 'Firma Oluşturma / Düzenleme', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-06-02' },
        ]
      }
    ],
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
