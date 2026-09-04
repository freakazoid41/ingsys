<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import { usePermissionDataStore } from '@/stores/permissiondata';
import Plib from '@/lib/pickle';
import { wTrans } from 'laravel-vue-i18n';
import Swal from 'sweetalert2';
import { Datepicker } from 'vanillajs-datepicker';
import tr from '/node_modules/vanillajs-datepicker/js/i18n/locales/tr.js';
import TreeModal from '@/lib/treeModal.js';
import Simplebar from 'simplebar-vue';

export default {
  breadcrumbs: {
      list: [  { title: 'Rol Şablonları', path: '/coalpanel/roles' } ],
      title: 'Rol Şablonları'
  },
  components: { Simplebar },
  setup() {
    Object.assign(Datepicker.locales, tr);
    const permissionData = usePermissionDataStore();

    return {
      useNavigationStore,
      useAuthStore,
      wTrans,
      permissionData,
    };
  },
  data() {
    return {
      plib: new Plib(),
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      groupName: '',
      groupDescription: '',
      selectedPermissions: new Set(),
      savedRoles: [],
      detailRole: null,
      treeInstance: null,
      editingRoleId: null,
      immutableRoles: ['Tedarikçi','İB. Görüntüleme rolü','İş Birimi','Admin'],
      editWarning: '',
    };
  },
  computed: {
    permissionItems() {
      return this.permissionData.items || [];
    },
    permissionMap() {
      const map = {};
      const walk = (nodes) => {
        nodes.forEach((x) => {
          if (x.op_key) {
            map[x.op_key] = x.title || x.op_key;
          }
          if (Array.isArray(x.childs)) {
            walk(x.childs);
          }
        });
      };
      walk(this.permissionItems);
      return map;
    },
    selectedArray() {
      return Array.from(this.selectedPermissions);
    },
  },
  mounted() {
    this.navigationStore.toggle(true);
    
    this.loadGroups();
    this.renderPermissionTree();

    setTimeout(() => {
      this.navigationStore.toggle(false);
    }, 300);
  },
  beforeUnmount() {
    if (this.treeInstance && typeof this.treeInstance.destroy === 'function') {
      this.treeInstance.destroy();
      this.treeInstance = null;
    }
  },
  methods: {
    async loadGroups() {
      this.savedRoles = [];
      try {
        const response = await this.plib.request({ url: '/api/v1/roles/templates', method: 'get' });
        if (response && response.success && Array.isArray(response.data)) {
          // ensure each role has op_keys (mirror of permissions) for compatibility
          this.savedRoles = response.data.map(r => ({
            ...r,
            permissions: Array.isArray(r.permissions) ? r.permissions : [],
            op_keys: Array.isArray(r.op_keys) ? r.op_keys : (Array.isArray(r.permissions) ? r.permissions : []),
            op_key: r.op_key || (r.name ? String(r.name).toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9\-çğıöşüâêîôûüığ]/gi,'') : `role-${r.id || Date.now()}`),
          }));
        }
      } catch (e) {
        console.error('Roles loadGroups error', e);
      }

      // Ensure immutable roles always present.
      const now = new Date().toISOString();
      this.immutableRoles.forEach((name) => {
        const existing = this.savedRoles.find((r) => r.name === name);
        if (!existing) {
          const baseRole = {
            id: `immutable-${name.replace(/\s+/g, '-').toLowerCase()}`,
            name,
            description: 'Sistem sabit rolü',
            permissions: [],
            created_at: now,
          };
          this.savedRoles.push(baseRole);
        }
      });

      this.renderPermissionTree();
      // Do not persist immediately on page load to avoid audit logs from non-user events.
      // persistGroups() is called on explicit create/update/delete actions instead.
    },
    async persistGroups() {
      try {
        // include op_keys for each role when sending to server
        const payloadRoles = this.savedRoles.map(r => ({
          ...r,
          permissions: Array.isArray(r.permissions) ? r.permissions : [],
          op_keys: Array.isArray(r.op_keys) ? r.op_keys : (Array.isArray(r.permissions) ? r.permissions : []),
          op_key: r.op_key || (r.name ? String(r.name).toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9\-çğıöşüâêîôûüığ]/gi,'') : `role-${r.id || Date.now()}`),
        }));

        const response = await this.plib.request({
          url: '/api/v1/roles/templates',
          method: 'post',
          data: {
            roles: JSON.stringify(payloadRoles),
          },
        });
        if (!response || !response.success) {
          throw new Error(response?.message || 'Sunucuya kaydedilemedi');
        }
      } catch (e) {
        console.error('Roles persistGroups error', e);
        Swal.fire('Hata', 'Rol şablonları kaydedilemedi. Lütfen tekrar deneyin.', 'error');
      }
    },
    togglePermission(opKey) {
      if (this.selectedPermissions.has(opKey)) {
        this.selectedPermissions.delete(opKey);
      } else {
        this.selectedPermissions.add(opKey);
      }
    },
    selectAllGroupPermissions(item) {
      if (!item || !item.childs) return;
      this.selectedPermissions.add(item.op_key);
      item.childs.forEach((child) => this.selectedPermissions.add(child.op_key));
    },
    clearSelection() {
      this.selectedPermissions.clear();
    },
    async addGroup() {
      const name = String(this.groupName).trim();
      if (!name) {
        Swal.fire('Uyarı', 'Rol şablonu adı zorunludur.', 'warning');
        return;
      }
      if (!this.selectedPermissions.size) {
        Swal.fire('Uyarı', 'Lütfen en az bir izin seçin.', 'warning');
        return;
      }

      const exists = this.savedRoles.find((i) => i.name === name && i.id !== this.editingRoleId);
      if (exists) {
        Swal.fire('Uyarı', 'Aynı adla bir şablon zaten mevcut.', 'warning');
        return;
      }

      const id = this.editingRoleId || Date.now();
      const generatedOpKey = String(name).toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9\-çğıöşüâêîôûüığ]/gi,'');
      const existingRole = this.editingRoleId ? this.savedRoles.find((r) => r.id === this.editingRoleId) : null;
      const opKey = existingRole?.op_key || generatedOpKey;

      const role = {
        id,
        name,
        op_key: opKey,
        description: String(this.groupDescription).trim(),
        permissions: this.selectedArray,
        op_keys: this.selectedArray, // explicitly include op_keys
        created_at: this.editingRoleId ? (existingRole?.created_at || new Date().toISOString()) : new Date().toISOString(),
      };

      if (this.editingRoleId) {
        this.savedRoles = this.savedRoles.map((existing) => existing.id === this.editingRoleId ? role : existing);
        this.detailRole = role;
        this.editingRoleId = null;
      } else {
        this.savedRoles.push(role);
        this.detailRole = role;
      }

      await this.persistGroups();
      this.groupName = '';
      this.groupDescription = '';
      this.clearSelection();
      this.plib.toast(Swal,'success',this.editingRoleId ? 'Rol şablonu güncellendi.' : 'Rol şablonu kaydedildi.');
    },
    renderPermissionTree() {
      if (this.treeInstance) {
        this.treeInstance.destroy();
      }

      this.treeInstance = TreeModal.render({
        target: '#roles-tree-container',
        items: this.permissionItems,
        idKey: 'op_key',
        parentKey: 'parent', // use non-existing parent key to avoid flat-merge recursion for already nested data
        labelKey: 'title',
        childrenKey: 'childs',
        maxHeight: '360px',
        defaultChecked: this.selectedArray,
        onChange: (checkedItems) => {
          const set = new Set();
          checkedItems.forEach((item) => {
            if (item && item.op_key) {
              set.add(item.op_key);
            } else if (item && item.id) {
              set.add(item.id);
            }
          });
          this.selectedPermissions = set;
        }
      });
    },
    async removeRole(id) {
      const roleToRemove = this.savedRoles.find((r) => r.id === id);
      if (roleToRemove && this.immutableRoles.includes(roleToRemove.name)) {
        Swal.fire('Uyarı', 'Bu rol sistem tarafından korunuyor, silinemez.', 'warning');
        return;
      }

      const confirmResult = await Swal.fire({
        title: 'Rol silinecek',
        text: 'Bu işlemi yapmak istediğinizden emin misiniz? Geri alınamaz.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, sil',
        cancelButtonText: 'Vazgeç',
        confirmButtonColor: '#ff7a00',
        cancelButtonColor: '#6c757d',
      });
      if (!confirmResult.isConfirmed) {
        return;
      }

      try {
        const response = await this.plib.request({ url: `/api/v1/roles/templates/${id}`, method: 'delete' });
        if (!response || !response.success) {
          throw new Error(response?.message || 'Rol silinemedi');
        }
        this.savedRoles = Array.isArray(response.data) ? response.data : this.savedRoles.filter((r) => r.id !== id);
        if (this.detailRole && this.detailRole.id === id) this.detailRole = null;
        Swal.fire('Silindi', 'Rol başarılı bir şekilde silindi.', 'success');
      } catch (e) {
        console.error('Rol silme hatası', e);
        Swal.fire('Hata', e.message || 'Rol silme işlemi sırasında bir hata oluştu', 'error');
      }
    },
    editRole(role) {
      this.groupName = role.name;
      this.groupDescription = role.description;
      this.selectedPermissions = new Set(role.permissions || []);
      this.editingRoleId = role.id;
      this.detailRole = role;
      this.editWarning = `Şu anda "${role.name}" rolünü düzenliyorsunuz. Kaydetmeden sayfayı terk etmeyin.`;
      this.renderPermissionTree();
    },
    cancelEdit() {
      this.editingRoleId = null;
      this.groupName = '';
      this.groupDescription = '';
      this.editWarning = '';
      this.clearSelection();
      this.detailRole = null;
      this.renderPermissionTree();
    },
    viewRole(role) {
      if (this.detailRole && this.detailRole.id === role.id) {
        this.detailRole = null;
      } else {
        this.detailRole = role;
      }
    },
  },
};
</script>

<template>
  <div class="card shadow-sm border-0 mt-10">
    <div class="card-header py-4 bg-white border-bottom">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <h3 class="card-title mb-1 text-dark">Rol Şablonları</h3>
          <p class="text-secondary mb-0">İzinlerden role dayalı şablonlar oluşturup, daha sonra kolayca atayabilirsiniz.</p>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="row gy-5">
        <div class="col-lg-5">
          <div class="mb-4">
            <label class="form-label fw-bold">Şablon Adı</label>
            <input v-model="groupName" class="form-control form-control-lg" placeholder="Örn. Tedarikçi Yönetici" />
          </div>
          <div class="mb-4">
            <label class="form-label fw-bold">Açıklama</label>
            <textarea v-model="groupDescription" class="form-control" rows="2" placeholder="Opsiyonel"></textarea>
          </div>

          <div class="border rounded-3 p-3 mb-3 bg-light" style="max-height: 360px; overflow-y: auto;">
            <h5 class="mb-3">İzin Seçimi</h5>
            <div id="roles-tree-container" style="min-height: 240px; background: #f8fafc; padding: 10px; border-radius: 10px;">
              <div v-if="permissionItems.length === 0" class="text-muted">İzin bulunamadı.</div>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-theme-outline btn-theme-outline-primary w-100" @click="clearSelection">Seçimi Temizle</button>
            <button class="btn btn-theme-outline btn-theme-outline-primary w-100" @click="addGroup">{{ editingRoleId ? 'Güncelle' : 'Şablonu Kaydet' }}</button>
            <button v-if="editingRoleId" class="btn btn-theme-outline btn-theme-outline-primary w-100" @click="cancelEdit">İptal</button>
          </div>
          <transition name="fade">
            <div v-if="editingRoleId" class="bg-warning bg-opacity-10 border border-warning rounded-3 p-3 mt-3" style="font-size:0.9rem;">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <strong>Düzenleme Modu Aktif:</strong> {{ editWarning }}
                </div>
                <button type="button" class="btn btn-sm edit-cancel-btn" style="border:2px solid #ff6347; color:#ff6347; background-color:#fff; transition: all 0.2s;" @mouseover="event.target.style.backgroundColor = '#ff6347'; event.target.style.color = '#fff';" @mouseout="event.target.style.backgroundColor = '#fff'; event.target.style.color = '#ff6347';" @click="cancelEdit">Düzenlemeyi İptal Et</button>
              </div>
            </div>
          </transition>
        </div>

        <div class="col-lg-7">
          <div class="mb-4">
            <h5 class="fw-bold mb-3">Kaydedilmiş Şablonlar ({{ savedRoles.length }})</h5>
            <div v-if="savedRoles.length === 0" class="text-muted">Henüz kayıtlı rol şablonu yok.</div>
            <div class="role-list-scroll">
              <div v-for="role in savedRoles" :key="role.id" class="card mb-3 m-3 shadow-sm border-0">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h6 class="mb-1">
                        {{ role.name }}
                        <span v-if="immutableRoles.includes(role.name)" class="badge bg-warning text-dark ms-2" style="font-size:0.72rem;">Sabit</span>
                      </h6>
                      <p class="text-secondary mb-1">{{ role.description || 'Açıklama yok' }}</p>
                      <small class="text-muted">Oluşturuldu: {{ new Date(role.created_at).toLocaleString() }}</small>
                    </div>
                    <div class="btn-group" role="group">
                      <button class="btn btn-sm btn-theme-outline btn-theme-outline-info" style="min-width:80px; font-weight:600;" @click="viewRole(role)">
                        {{ detailRole && detailRole.id === role.id ? 'Kapat' : 'Detay' }}
                      </button>
                      <button class="btn btn-sm btn-theme-outline btn-theme-outline-warning" style="min-width:80px; font-weight:600; background:#154b9024; border-color:#154b9024;" @click="editRole(role)">Düzenle</button>
                      <button v-if="!immutableRoles.includes(role.name)" class="btn btn-sm btn-theme-outline btn-theme-outline-danger" style="min-width:80px; font-weight:600; background:#154b9024; border-color:#154b9024;" @click="removeRole(role.id)">Sil</button>
                    </div>
                  </div>
                </div>

                <div v-if="detailRole && detailRole.id === role.id" class="border-top px-3 py-3 bg-light">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                      <h6 class="mb-1 text-primary" style="font-weight:700;">Detay: {{ role.name }}</h6>
                      <small class="text-muted">{{ role.description || 'Açıklama yok' }}</small>
                    </div>
                    <span class="badge bg-success fw-bold">Yetki sayısı: {{ role.permissions.length }}</span>
                  </div>
                  <div class="row gy-2">
                    <div v-for="perm in role.permissions" :key="perm" class="col-12 col-md-6">
                      <div class="d-flex flex-column p-2 rounded border bg-white">
                        <span class="fw-bold small">{{ perm }}</span>
                        <span class="small text-secondary">{{ permissionMap[perm] || 'Açıklama yok' }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.tm-node-row{
  height: 50px!important;
}
</style>