<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import { usePermissionDataStore } from '@/stores/permissiondata';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
    import AppFab from '@/components/coalparts/AppFab.vue';

import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import Simplebar from 'simplebar-vue';

export default {
    breadcrumbs: {
        list: [  { title: 'Bildirim Ayarları', path: '/coalpanel/notifications/settings' } ],
        title: 'Bildirim Ayarları'
    },
    components: {
        AppFab,
    },
    setup() {
        const permissionData = usePermissionDataStore();

        return {
            useNavigationStore,
            useAuthStore,
            permissionData,
            PickleTable,
        };
    },
    data() {
        return {
            plib: new Plib(),
            authStore: useAuthStore(),
            navigationStore: useNavigationStore(),
            notificationGroups: [],
            selectedGroup: null,
            groupMembers: {},
            touchedUserIds: [],
        };
    },
    async mounted() {
        this.navigationStore.toggle(true);
        await this.loadNotificationGroups();
        await this.loadAssignedNotificationUsers();
        this.buildTestTable();
        await useAuthStore().getPermissions();

        setTimeout(() => {
            this.navigationStore.toggle(false);
        }, 300);
    },
    methods: {
        async formCallback() {
            const assignedMap = {};

            Object.entries(this.groupMembers).forEach(([groupId, members]) => {
                const group = this.notificationGroups.find(g => String(g.id) === String(groupId));
                if (!group) return;
                const opKey = group.op_key;
                if (!opKey) return;

                members.forEach(member => {
                    const personId = String(member.id);
                    if (!assignedMap[personId]) {
                        assignedMap[personId] = new Set();
                    }
                    assignedMap[personId].add(opKey);
                });
            });

            const allTouchedIds = new Set(this.touchedUserIds.map(String));
            Object.keys(assignedMap).forEach(id => allTouchedIds.add(id));

            const assigned = Array.from(allTouchedIds).map(personId => ({
                person_id: personId,
                op_keys: assignedMap[personId] ? Array.from(assignedMap[personId]) : [],
            }));

            const payload = { assigned };

            this.navigationStore.toggle(true);
            const rsp = await this.plib.request({
                url: '/api/v1/set-notification-groups',
                method: 'POST',
                data: {
                    assigned: JSON.stringify(payload.assigned),
                },
            });
            this.navigationStore.toggle(false);

            if (rsp && rsp.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Kaydedildi',
                    text: 'Bildirim grubu atamaları başarıyla kaydedildi.'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: rsp?.msg || 'Kaydetme sırasında bir hata oluştu.'
                });
            }
        },
        selectGroup(group) {
            this.selectedGroup = group;
            if (!this.groupMembers[group.id]) {
                this.groupMembers[group.id] = [];
            }
        },
        getSelectedGroupMembers() {
            return this.selectedGroup ? this.groupMembers[this.selectedGroup.id] || [] : [];
        },
        searchTable() {
            this.table.setFilter(
                [{
                    key   : 'all', // column key
                    type  : '=', // filtering type ('like','<','>')
                    value : document.getElementById('mainSearch').value.trim()//wanted column value
                }]
            );
        },
        resetSearch() {
            document.getElementById('mainSearch').value = '';
            this.table.setFilter([]);
        },
        addUserToSelectedGroup(data) {
            if (!this.selectedGroup) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Grup seçin',
                    text: 'Lütfen önce sol sütundan bir bildirim grubu seçin.'
                });
                return;
            }

            const members = this.groupMembers[this.selectedGroup.id] || [];
            if (members.some(member => member.id === data.id)) {
                Swal.fire({
                    icon: 'info',
                    title: 'Zaten ekli',
                    text: 'Bu kullanıcı zaten seçili grupta mevcut.'
                });
                return;
            }

            members.push({
                id: data.id,
                name: data.name,
                username: data.username,
                type_title: data.type_title,
                user_status: data.user_status,
            });
            this.groupMembers[this.selectedGroup.id] = members;
            if (!this.touchedUserIds.includes(String(data.id))) {
                this.touchedUserIds.push(String(data.id));
            }
        },
        removeGroupMember(id) {
            if (!this.selectedGroup) {
                return;
            }
            this.groupMembers[this.selectedGroup.id] = (this.groupMembers[this.selectedGroup.id] || []).filter(member => member.id !== id);
            if (!this.touchedUserIds.includes(String(id))) {
                this.touchedUserIds.push(String(id));
            }
        },
        async loadNotificationGroups() {
            const rsp = await this.plib.request({
                url: '/api/v1/notification/groups',
                method: 'GET',
                data: {}
            });

            if (rsp && rsp.success) {
                this.notificationGroups = Array.isArray(rsp.data) ? rsp.data.map((group, index) => ({
                    id: group.id ?? index + 1,
                    title: group.title ?? group.name ?? 'Bildirim Grubu',
                    op_key: group.op_key ?? '',
                })) : [];
            } else {
                this.notificationGroups = [];
            }

            this.notificationGroups.forEach(group => {
                if (!this.groupMembers[group.id]) {
                    this.groupMembers[group.id] = [];
                }
            });

            this.selectedGroup = this.notificationGroups[0] || null;
        },
        async loadAssignedNotificationUsers() {
            const rsp = await this.plib.request({
                url: '/api/v1/notification-users',
                method: 'GET',
                data: {}
            });

            if (!rsp || !rsp.success || typeof rsp.data !== 'object') {
                return;
            }

            Object.entries(rsp.data).forEach(([opKey, membersData]) => {
                const group = this.notificationGroups.find(g => g.op_key === opKey);
                if (!group) return;

                const members = Array.isArray(membersData) ? membersData.map(member => ({
                    id: member.person_id,
                    name: member.name || member.person_id,
                    username: member.username || member.person_id,
                    type_title: member.type_title || '',
                    user_status: member.user_status || '',
                })) : [];

                this.groupMembers[group.id] = members;
                members.forEach(member => {
                    if (!this.touchedUserIds.includes(String(member.id))) {
                        this.touchedUserIds.push(String(member.id));
                    }
                });
            });
        },
        async buildTestTable(){
            await useAuthStore().getPermissions();
            //set headers
            const headers = [
                {
                    title : 'İsim',
                    key   : 'name',
                    order : true,
                    type  : 'string', // if column is string then make type string
                },{
                    title : 'Kullanıcı Adı',
                    key   : 'username',
                    order : true,
                    type  : 'string', // if column is string then make type string
                }
            ];
            
            //initiate table
            this.table = new PickleTable({
                container : '#div_table', //table target div
                headers   : headers,
                pageLimit : 10, // -1 for closing pagination
                height    : '50vh',
                type      : 'ajax',
                columnSearch : true, // true - false for opening and closig
                paginationType : 'number',// scroll - number for default
                ajax:{
                    url:'/api/v1/table/user',
                    data:{
                        //order:{},
                    }
                },
                initialFilter : [
                    {
                        key:'type_key',
                        type:'=',
                        value:'op-pert-admin'
                    }
                ],
                nextPageIcon : '<i class="fa fa-solid fa-chevron-right"></i>',
                prevPageIcon : '<i class="fa fa-solid fa-chevron-left"></i>',
                rowClick:(elm,data)=>this.addUserToSelectedGroup(data),
                rowFormatter:(elm,data)=>{
                    return data;
                },
            });
        }
    }
}
</script>

<template>
  <div class="card border-0 mt-10 notification-card">
    <div class="card-header py-6 text-white notification-header">
      <div class="d-flex align-items-center justify-content-between flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="notification-icon d-flex align-items-center justify-content-center" v-if="false">
            <i class="fa fa-bell fa-2x"></i>
          </div>
          <div>
            <h1 class="card-title mb-1 text-white">Notifikasyon Şablonları</h1>
            <p class="text-white-75 mb-0">Bildirim mailleri için şablonları yönetebilir, üyeleri düzenleyebilir ve gruplar arasında hızlı geçiş yapabilirsiniz.</p>
          </div>
        </div>
        
      </div>
    </div>
    <div class="card-body py-6">
      <div class="row g-4">
        <div class="col-12 col-xl-3">
          <div class="card h-100">
            <div class="">
              <h5 class="mb-4">Bildirim Grubu Seç</h5>
              <div class="list-group notification-group-list">
                <button
                  v-for="group in notificationGroups"
                  :key="group.id"
                  type="button"
                  class="list-group-item list-group-item-action d-flex justify-content-between align-items-start rounded-2 mb-3 notification-group-button"
                  :class="{ 'active-group': selectedGroup && selectedGroup.id === group.id }"
                  @click="selectGroup(group)"
                >
                  <div class="d-flex flex-column gap-1">
                    <div class="fw-semibold fs-6">{{ group.title }}</div>
                    <div class="text-muted small">Bu grubu yönetmek için seçin.</div>
                  </div>
                  <span class="badge notification-count-badge">{{ (groupMembers[group.id] || []).length }}</span>
                </button>
              </div>
              <p class="text-muted small mt-3">Seçili grubun üyelerini sağdaki tablodan görebilir ve düzenleyebilirsiniz.</p>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-6">
          <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                  <h5 class="card-title mb-1">Kullanıcılar</h5>
                  <p class="text-muted mb-0">Bir kullanıcıyı seçili gruba eklemek için satıra tıklayın.</p>
                </div>
                <div class="input-group input-group-sm w-100 w-md-auto">
                  <input id="mainSearch" type="text" class="form-control form-control-solid" placeholder="Kullanıcı Ara" />
                  <button class="btn btn-primary" type="button" @click="searchTable">Ara</button>
                  <button class="btn btn-outline-secondary" type="button" @click="resetSearch">Sıfırla</button>
                </div>
              </div>
              <div class="table-card rounded-2 overflow-hidden border">
                <div id="div_table" class="m-5 ww"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-3">
          <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex flex-column h-100">
              <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-column flex-sm-row">
                  <div>
                    <h5 class="card-title mb-1">Grup Üyeleri</h5>
                    <p class="text-muted mb-0">Seçili gruba eklenmiş kullanıcılar burada listelenir.</p>
                  </div>
                  <span class="badge notification-members-badge">{{ getSelectedGroupMembers().length }} Üye</span>
                </div>
              </div>
              <div class="member-card-list flex-grow-1 rounded-2 overflow-hidden border border-1 border-light" style="min-height: 300px; max-height: 72vh; overflow:auto;">
                <div class="p-3">
                  <div v-if="!selectedGroup" class="text-center text-muted py-5">
                    Lütfen sol sütundan bir bildirim grubu seçin.
                  </div>
                  <div v-else-if="getSelectedGroupMembers().length === 0" class="text-center text-muted py-5">
                    Bu grupta üye yok.
                  </div>
                  <div v-else class="d-grid gap-3">
                    <div v-for="(member, index) in getSelectedGroupMembers()" :key="member.id" class="member-card p-3 rounded-2 border shadow-sm">
                      <div class="member-card-header d-flex align-items-start justify-content-between gap-3">
                        <div class="member-card-title">
                          <div class="member-card-number">#{{ index + 1 }}</div>
                          <div class="member-card-name">{{ member.name }}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger member-card-remove" @click="removeGroupMember(member.id)">Kaldır</button>
                      </div>
                      <div class="member-card-email text-muted">{{ member.username }}</div>
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
  <AppFab v-if="authStore.permissions?.includes('per-00-01')" :btntype="single" :callback="formCallback" />
</template>

<style scoped>
.notification-card {
  /* background: linear-gradient(132deg, #eef2ff 0%, #f5f7ff 48%, #ffffff 100%); */
}
.notification-header {
  /* background: linear-gradient(135deg, #1f3ddb 0%, #5c7ef2 45%, #9bb7ff 100%); */
  border-bottom: 1px solid #f1f1f4;
}
.notification-icon {
  width: 64px;
  height: 64px;
  border-radius: 18px;
  background: rgba(255,255,255,0.18);
  /* color: #fff; */
}
.notification-icon i {
  font-size: 1.4rem;
}
.notification-group-button {
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background-color .2s ease;
  background: #ffffff;
  border: 1px solid rgba(51, 122, 255, 0.08);
}
.notification-group-button:hover {
  transform: translateY(-2px);
  /* box-shadow: 0 15px 30px rgba(31, 61, 187, 0.12); */
}
.notification-group-button.active-group {
  background: #154b9024;
  border-color: #154b91;
  color: #154b91;
}
.notification-group-list .notification-group-button {
  padding: 1.15rem 1rem;
}
.notification-count-badge {
  min-width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #154b91;
  color: #ffffff;
  font-weight: 700;
  box-shadow: 0 6px 15px rgba(59, 109, 255, 0.18);
}
.notification-members-badge {
  background: #154b91;
  color: #fff;
  padding: 0.65rem 1rem;
  border-radius: 999px;
  font-size: 0.95rem;
}
.member-card-list {
  background: #fff;
}
.member-card {
  background: #ffffff;
  border-color: rgba(59, 109, 255, 0.12);
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  min-height: 110px;
  transition: transform .2s ease, box-shadow .2s ease;
}
.member-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(31, 61, 187, 0.1);
}
.member-card-header {
  width: 100%;
}
.member-card-title {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.member-card-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(59, 109, 255, 0.1);
  color: #154b91;
  font-weight: 700;
  font-size: 0.95rem;
}
.member-card-name {
  font-size: 1rem;
  line-height: 1.4;
  color: #0f234d;
  font-weight: 600;
}
.member-card-email {
  margin-top: 0.75rem;
  color: rgba(34, 34, 34, 0.65);
  word-break: break-word;
  font-size: 0.95rem;
}
.member-card-remove {
  border-color: rgba(255, 82, 82, 0.35);
}
.table-card {
  background: #ffffff;
  min-height: 420px;
}
.notification-header h1 {
  font-size: 1.8rem;
}
.notification-header p {
  opacity: 0.95;
}
@media (max-width: 1199px) {
  .notification-header .text-end {
    text-align: center !important;
  }
}

@media(min-width:768px) {
  .ww .pickletable table{
    width: 100%!important;
  }
}
</style>