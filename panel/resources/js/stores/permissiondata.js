import { defineStore } from 'pinia'

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
        title: 'Sistem Kullanıcı Kartları',
        ttitle: 'Perm_con_ops',
        ctitle: 'type_id',
        group_key: 'op-perm',
        op_key: 'per-04',
        childs: [
          { parent_id: 0, title: 'Listeleme', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-04-01' },
          { parent_id: 0, title: 'Oluşturma / Düzenleme', ttitle: 'Perm_con_ops', ctitle: 'type_id', op_key: 'per-04-02' }
        ]
      }
    ]
  }),
  getters: {
    asJson: (state) => JSON.stringify(state.items, null, 2),
    list: (state) => state.items,
    byOpKey: (state) => (opKey) => state.items.find((item) => item.op_key === opKey)
  },
  actions: {
    setList(list) {
      this.items = Array.isArray(list) ? list : []
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
