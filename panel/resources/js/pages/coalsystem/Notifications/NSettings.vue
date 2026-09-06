<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import { usePermissionDataStore } from '@/stores/permissiondata';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';

export default {
    breadcrumbs: {
        list: [  { title: 'Bildirim Ayarları', path: '/coalpanel/notifications/settings' } ],
        title: 'Bildirim Ayarları'
    },
    setup() {
        const permissionData = usePermissionDataStore();
        return { useNavigationStore, useAuthStore, permissionData, PickleTable };
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
            searchQuery: '',
            roleFilter: 'op-pert-admin',
        };
    },
    computed: {
        totalMembers() {
            return Object.values(this.groupMembers).reduce((s, arr) => s + (arr?.length || 0), 0);
        },
        selectedCount() {
            return this.getSelectedGroupMembers().length;
        },
        unassignedCount() {
            return Math.max(0, 7 - this.totalMembers);
        },
        isDirty() {
            return this.touchedUserIds.length > 0;
        },
        roleOptions() {
            return [
                { value: 'op-pert-admin', label: 'Yöneticiler' },
                { value: 'all', label: 'Tüm Kullanıcılar' },
            ];
        },
    },
    async mounted() {
        this.navigationStore.toggle(true);
        await this.loadNotificationGroups();
        await this.loadAssignedNotificationUsers();
        this.buildTestTable();
        await useAuthStore().getPermissions();
        setTimeout(() => this.navigationStore.toggle(false), 300);
    },
    methods: {
        groupMeta(opKey) {
            const map = {
                'tedarik-01': { icon: 'ki-outline ki-package', color: '#2563eb', bg: '#eff6ff', label: 'SAP Giriş', desc: 'SAP cron ile sisteme düşen sipariş' },
                'tedarik-02': { icon: 'ki-outline ki-send', color: '#f59e0b', bg: '#fff7ed', label: 'Onaya Gönderim', desc: 'Tedarikçi parçalı / tümden gönderim' },
                'tedarik-03': { icon: 'ki-outline ki-file-added', color: '#d97706', bg: '#fef3c7', label: 'Dosya Bekliyor', desc: 'İnceleme bekleyen dosyalar' },
                'tedarik-04': { icon: 'ki-outline ki-check-circle', color: '#059669', bg: '#ecfdf5', label: 'Onaylandı', desc: 'Dosya onaylandı' },
                'tedarik-05': { icon: 'ki-outline ki-cross-circle', color: '#dc2626', bg: '#fef2f2', label: 'Yeniden Talep', desc: 'Dosya reddedildi / yeniden talep' },
                'tedarik-06': { icon: 'ki-outline ki-medal-star', color: '#16a34a', bg: '#f0fdf4', label: 'Kalite Onayı', desc: 'Sipariş kapatıldı' },
                'tedarik-07': { icon: 'ki-outline ki-cross-square', color: '#991b1b', bg: '#fef2f2', label: 'Reddedildi', desc: 'Sipariş reddedildi' },
            };
            return map[opKey] || { icon: 'ki-outline ki-notification-bing', color: '#64748b', bg: '#f1f5f9', label: 'Bildirim', desc: 'Bildirim grubu' };
        },
        initials(name) {
            if(!name) return '?';
            const parts = name.trim().split(/\s+/).slice(0,2);
            return parts.map(p=>p[0]?.toUpperCase()||'').join('') || '?';
        },
        async formCallback() {
            const assignedMap = {};
            Object.entries(this.groupMembers).forEach(([groupId, members]) => {
                const group = this.notificationGroups.find(g => String(g.id) === String(groupId));
                if (!group) return;
                const opKey = group.op_key;
                if (!opKey) return;
                members.forEach(member => {
                    const personId = String(member.id);
                    if (!assignedMap[personId]) assignedMap[personId] = new Set();
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
                data: { assigned: JSON.stringify(payload.assigned) },
            });
            this.navigationStore.toggle(false);
            if (rsp && rsp.success) {
                Swal.fire({ icon: 'success', title: 'Kaydedildi', text: 'Bildirim grubu atamaları başarıyla kaydedildi.', confirmButtonColor: '#154b91' });
            } else {
                Swal.fire({ icon: 'error', title: 'Hata', text: rsp?.msg || 'Kaydetme sırasında bir hata oluştu.' });
            }
        },
        selectGroup(group) {
            this.selectedGroup = group;
            if (!this.groupMembers[group.id]) this.groupMembers[group.id] = [];
        },
        getSelectedGroupMembers() {
            return this.selectedGroup ? this.groupMembers[this.selectedGroup.id] || [] : [];
        },
        searchTable() {
            const val = (this.searchQuery || document.getElementById('mainSearch')?.value || '').trim();
            this.table.setFilter([{ key: 'all', type: '=', value: val }]);
        },
        resetSearch() {
            this.searchQuery = '';
            if(document.getElementById('mainSearch')) document.getElementById('mainSearch').value = '';
            this.table.setFilter([]);
        },
        onRoleChange() {
            const el = document.getElementById('div_table');
            if(el) el.innerHTML = '';
            this.table = null;
            this.buildTestTable();
        },
        clearSelectedGroup() {
            if(!this.selectedGroup) return;
            Swal.fire({
                title: 'Grubu temizle?',
                text: `${this.selectedGroup.title} grubundaki ${this.selectedCount} üye kaldırılacak.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Evet, temizle',
                cancelButtonText: 'Vazgeç'
            }).then(res=>{
                if(res.isConfirmed){
                    const ids = (this.groupMembers[this.selectedGroup.id]||[]).map(m=>String(m.id));
                    this.groupMembers[this.selectedGroup.id]=[];
                    ids.forEach(id=>{ if(!this.touchedUserIds.includes(id)) this.touchedUserIds.push(id); });
                }
            });
        },
        copyGroupMails() {
            const mails = this.getSelectedGroupMembers().map(m=>m.username).filter(Boolean).join(', ');
            if(!mails) return Swal.fire({ icon:'info', title:'Liste boş', text:'Kopyalanacak e-posta yok.' });
            navigator.clipboard.writeText(mails).then(()=> Swal.fire({ icon:'success', title:'Kopyalandı', text:`${this.selectedCount} e-posta panoya kopyalandı.`, timer:1800, showConfirmButton:false }));
        },
        addUserToSelectedGroup(data) {
            if (!this.selectedGroup) {
                Swal.fire({ icon: 'warning', title: 'Grup seçin', text: 'Lütfen önce sol sütundan bir bildirim grubu seçin.' });
                return;
            }
            const members = this.groupMembers[this.selectedGroup.id] || [];
            if (members.some(member => member.id === data.id)) {
                Swal.fire({ icon: 'info', title: 'Zaten ekli', text: 'Bu kullanıcı zaten seçili grupta mevcut.' });
                return;
            }
            members.push({ id: data.id, name: data.name, username: data.username, type_title: data.type_title, user_status: data.user_status });
            this.groupMembers[this.selectedGroup.id] = members;
            if (!this.touchedUserIds.includes(String(data.id))) this.touchedUserIds.push(String(data.id));
        },
        removeGroupMember(id) {
            if (!this.selectedGroup) return;
            this.groupMembers[this.selectedGroup.id] = (this.groupMembers[this.selectedGroup.id] || []).filter(member => member.id !== id);
            if (!this.touchedUserIds.includes(String(id))) this.touchedUserIds.push(String(id));
        },
        async loadNotificationGroups() {
            const rsp = await this.plib.request({ url: '/api/v1/notification/groups', method: 'GET', data: {} });
            if (rsp && rsp.success) {
                this.notificationGroups = Array.isArray(rsp.data) ? rsp.data.map((group, index) => ({
                    id: group.id ?? index + 1,
                    title: group.title ?? group.name ?? 'Bildirim Grubu',
                    op_key: group.op_key ?? '',
                })) : [];
            } else this.notificationGroups = [];
            this.notificationGroups.forEach(group => { if (!this.groupMembers[group.id]) this.groupMembers[group.id] = []; });
            this.selectedGroup = this.notificationGroups[0] || null;
        },
        async loadAssignedNotificationUsers() {
            const rsp = await this.plib.request({ url: '/api/v1/notification-users', method: 'GET', data: {} });
            if (!rsp || !rsp.success || typeof rsp.data !== 'object') return;
            Object.entries(rsp.data).forEach(([opKey, membersData]) => {
                const group = this.notificationGroups.find(g => g.op_key === opKey);
                if (!group) return;
                const members = Array.isArray(membersData) ? membersData.map(member => ({
                    id: member.person_id, name: member.name || member.person_id, username: member.username || member.person_id, type_title: member.type_title || '', user_status: member.user_status || '',
                })) : [];
                this.groupMembers[group.id] = members;
                members.forEach(member => { if (!this.touchedUserIds.includes(String(member.id))) this.touchedUserIds.push(String(member.id)); });
            });
        },
        async buildTestTable(){
            await useAuthStore().getPermissions();
            const headers = [
                { title : 'İsim', key : 'name', order : true, type  : 'string' },
                { title : 'Kullanıcı Adı', key : 'username', order : true, type  : 'string' }
            ];
            const filter = this.roleFilter === 'all' ? [{ key:'type_key', type:'!=', value:'op-pert-reseller' }] : [{ key:'type_key', type:'=', value:this.roleFilter }];
            const el = document.getElementById('div_table');
            if(el) el.innerHTML = '';
            this.table = new PickleTable({
                container : '#div_table',
                headers   : headers,
                pageLimit : 10,
                height    : '50vh',
                type      : 'ajax',
                columnSearch : true,
                paginationType : 'number',
                ajax:{ url:'/api/v1/table/user', data:{} },
                initialFilter : filter,
                nextPageIcon : '<i class="ki-outline ki-arrow-right"></i>',
                prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                rowClick:(elm,data)=>this.addUserToSelectedGroup(data),
                rowFormatter:(elm,data)=> data,
            });
        }
    }
}
</script>

<template>
  <div class="nset-wrapper">
    <div class="adm-nset">
      <!-- Header -->
      <div class="nset-header">
        <div class="nset-header__left">
          <div class="nset-header__icon">
            <i class="ki-outline ki-notification-bing"></i>
          </div>
          <div>
            <h1 class="nset-header__title">Bildirim Yönetimi</h1>
            <p class="nset-header__sub">TEDARIK 7 bildirim grubunu yönet — kim hangi sipariş / dosya olayında haberdar edilsin</p>
          </div>
        </div>
        <div class="nset-header__right">
          <span class="nset-pill nset-pill--light"><i class="ki-outline ki-category"></i> {{ notificationGroups.length }} Grup</span>
          <span class="nset-pill nset-pill--dark"><i class="ki-outline ki-people"></i> {{ totalMembers }} Atama</span>
          <button v-if="authStore.permissions?.includes('per-00-01')" class="nset-save-btn" :class="{ 'nset-save-btn--dirty': isDirty }" @click="formCallback">
            <i class="ki-outline ki-check-circle"></i> Kaydet
            <span v-if="isDirty" class="nset-save-btn__dot"></span>
          </button>
        </div>
      </div>

      <!-- Unsaved changes banner -->
      <transition name="nset-banner">
        <div v-if="isDirty" class="nset-unsaved-banner">
          <i class="ki-outline ki-information-2"></i>
          <span>Kaydedilmemiş <b>{{ touchedUserIds.length }}</b> değişiklik var</span>
        </div>
      </transition>

      <!-- Stats -->
      <div class="nset-stats">
        <div class="nset-stat">
          <div class="nset-stat__icon" style="background:#eff6ff;color:#2563eb"><i class="ki-outline ki-notification-bing"></i></div>
          <div class="nset-stat__body">
            <span class="nset-stat__label">TOPLAM GRUP</span>
            <span class="nset-stat__value" style="color:#2563eb">{{ notificationGroups.length }}</span>
          </div>
        </div>
        <div class="nset-stat">
          <div class="nset-stat__icon" style="background:#f0fdf4;color:#16a34a"><i class="ki-outline ki-people"></i></div>
          <div class="nset-stat__body">
            <span class="nset-stat__label">TOPLAM ATAMA</span>
            <span class="nset-stat__value">{{ totalMembers }}</span>
          </div>
        </div>
        <div class="nset-stat nset-stat--active">
          <div class="nset-stat__icon" :style="{ background: selectedGroup ? groupMeta(selectedGroup.op_key).bg : '#f1f5f9', color: selectedGroup ? groupMeta(selectedGroup.op_key).color : '#94a3b8' }">
            <i :class="selectedGroup ? groupMeta(selectedGroup.op_key).icon : 'ki-outline ki-information-5'"></i>
          </div>
          <div class="nset-stat__body">
            <span class="nset-stat__label">SEÇİLİ GRUP</span>
            <span class="nset-stat__value nset-stat__value--truncate">{{ selectedGroup?.title || 'Henüz seçim yok' }}</span>
            <span class="nset-stat__sub" v-if="selectedCount > 0">{{ selectedCount }} üye atandı</span>
          </div>
        </div>
        <div class="nset-stat">
          <div class="nset-stat__icon" style="background:#fef3c7;color:#d97706"><i class="ki-outline ki-information-2"></i></div>
          <div class="nset-stat__body">
            <span class="nset-stat__label">NASIL ÇALIŞIR?</span>
            <span class="nset-stat__value" style="font-size:0.9rem">1 → 2 → 3</span>
            <span class="nset-stat__sub">Seç • Tıkla • Kaydet</span>
          </div>
        </div>
      </div>

      <!-- Main Grid -->
      <div class="nset-grid">
        <!-- Left: Groups -->
        <div class="nset-card nset-col-left">
          <div class="nset-card__head">
            <h5 class="nset-card__title"><i class="ki-outline ki-notification-bing" style="color:#2563eb"></i> Bildirim Grupları</h5>
            <span class="nset-badge">{{ notificationGroups.length }}</span>
          </div>
          <div class="nset-groups-scroll">
            <button
              v-for="group in notificationGroups"
              :key="group.id"
              type="button"
              class="nset-group"
              :class="{ 'is-active': selectedGroup && selectedGroup.id === group.id }"
              @click="selectGroup(group)"
            >
              <span class="nset-group__icon" :style="{ background: groupMeta(group.op_key).bg, color: groupMeta(group.op_key).color }">
                <i :class="groupMeta(group.op_key).icon"></i>
              </span>
              <span class="nset-group__info">
                <span class="nset-group__title">{{ group.title }}</span>
                <span class="nset-group__desc">{{ groupMeta(group.op_key).desc }}</span>
                <span class="nset-group__tags">
                  <span class="nset-tag nset-tag--key">{{ group.op_key }}</span>
                  <span class="nset-tag" :style="{ color: groupMeta(group.op_key).color, background: groupMeta(group.op_key).bg }">{{ groupMeta(group.op_key).label }}</span>
                </span>
              </span>
              <span class="nset-group__count" :style="{ background: groupMeta(group.op_key).color }">{{ (groupMembers[group.id] || []).length }}</span>
            </button>
          </div>
        </div>

        <!-- Center: Users Table -->
        <div class="nset-card nset-col-center">
          <div class="nset-card__head">
            <h5 class="nset-card__title"><i class="ki-outline ki-people" style="color:#16a34a"></i> Kullanıcılar</h5>
            <span class="nset-hint-tag"><i class="ki-outline ki-mouse-circle"></i> Satıra tıkla, gruba ekle</span>
          </div>
          <!-- Search Toolbar -->
          <div class="nset-toolbar">
            <div class="nset-search">
              <i class="ki-outline ki-magnifier"></i>
              <input id="mainSearch" v-model="searchQuery" @keyup.enter="searchTable" type="text" placeholder="İsim, e-posta veya unvan ara..." />
              <button v-if="searchQuery" class="nset-search__clear" @click="resetSearch"><i class="ki-outline ki-cross"></i></button>
            </div>
            <select class="nset-select" v-model="roleFilter" @change="onRoleChange">
              <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <button class="nset-btn nset-btn--primary" @click="searchTable"><i class="ki-outline ki-magnifier"></i> Ara</button>
            <button class="nset-btn nset-btn--ghost" @click="resetSearch">Sıfırla</button>
          </div>
          <!-- Table -->
          <div class="nset-table-area">
            <div id="div_table" class="ww"></div>
          </div>
          <div class="nset-table-hint">
            <i class="ki-outline ki-information-2"></i> Satıra tıklamak kullanıcıyı <b>{{ selectedGroup?.title || 'seçili gruba' }}</b> ekler.
          </div>
        </div>

        <!-- Right: Members -->
        <div class="nset-card nset-col-right">
          <div class="nset-card__head">
            <h5 class="nset-card__title"><i class="ki-outline ki-user-check" style="color:#8b5cf6"></i> Grup Üyeleri</h5>
            <div class="nset-members-actions" v-if="selectedCount > 0">
              <button class="nset-icon-btn" @click="copyGroupMails" title="E-postaları kopyala"><i class="ki-outline ki-copy"></i></button>
              <button class="nset-icon-btn nset-icon-btn--danger" @click="clearSelectedGroup" title="Grubu temizle"><i class="ki-outline ki-trash"></i></button>
              <span class="nset-badge nset-badge--primary">{{ selectedCount }} Üye</span>
            </div>
          </div>
          <!-- Selected group info -->
          <div v-if="selectedGroup" class="nset-selected-info" :style="{ borderColor: groupMeta(selectedGroup.op_key).color + '30', background: groupMeta(selectedGroup.op_key).bg + '40' }">
            <span class="nset-selected-info__icon" :style="{ background: groupMeta(selectedGroup.op_key).color + '15', color: groupMeta(selectedGroup.op_key).color }">
              <i :class="groupMeta(selectedGroup.op_key).icon"></i>
            </span>
            <div class="nset-selected-info__text">
              <span class="nset-selected-info__title">{{ selectedGroup.title }}</span>
              <span class="nset-selected-info__desc">{{ groupMeta(selectedGroup.op_key).desc }}</span>
            </div>
            <span class="nset-selected-info__key">{{ selectedGroup.op_key }}</span>
          </div>
          <!-- Members list / empty -->
          <div class="nset-members-body">
            <div v-if="!selectedGroup" class="nset-empty">
              <div class="nset-empty__icon"><i class="ki-outline ki-information-5"></i></div>
              <p class="nset-empty__title">Grup seçin</p>
              <span class="nset-empty__desc">Sol listeden bir bildirim grubu seçerek devam edin</span>
            </div>
            <div v-else-if="getSelectedGroupMembers().length === 0" class="nset-empty">
              <div class="nset-empty__icon nset-empty__icon--warn"><i class="ki-outline ki-user"></i></div>
              <p class="nset-empty__title">Bu grupta henüz üye yok</p>
              <span class="nset-empty__desc">Ortadaki tablodan kullanıcı satırına tıklayarak ekleyin</span>
              <div class="nset-empty__hint">
                <i class="ki-outline ki-information-2"></i>
                <span>Üstteki rol filtresi ile Tedarikçiler / Tüm Kullanıcılar arasında geçiş yapın.</span>
              </div>
            </div>
            <div v-else class="nset-member-list">
              <div v-for="(member, index) in getSelectedGroupMembers()" :key="member.id" class="nset-member">
                <span class="nset-member__idx">{{ index + 1 }}</span>
                <span class="nset-member__avatar" :style="{ background: groupMeta(selectedGroup.op_key).color }">{{ initials(member.name) }}</span>
                <span class="nset-member__info">
                  <span class="nset-member__name">{{ member.name }}</span>
                  <span class="nset-member__mail">{{ member.username }}</span>
                </span>
                <button type="button" class="nset-member__remove" @click="removeGroupMember(member.id)" title="Kaldır"><i class="ki-outline ki-cross"></i></button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Wrapper ── */
.nset-wrapper{ width:100%; max-width:1640px; margin:0 auto; padding:0 8px; box-sizing:border-box; }
.adm-nset{ display:flex; flex-direction:column; gap:16px; padding-bottom:24px; }

/* ── Header ── */
.nset-header{
  display:flex; justify-content:space-between; align-items:center; gap:16px;
  background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);
  padding:20px 24px; border-radius:16px;
  box-shadow:0 4px 24px rgba(15,23,42,.12);
  position:relative; overflow:hidden;
}
.nset-header::before{ content:''; position:absolute; right:-40px; top:-40px; width:200px; height:200px; background:radial-gradient(circle,rgba(59,130,246,.15),transparent 70%); border-radius:50%; pointer-events:none; }
.nset-header::after{ content:''; position:absolute; left:50%; bottom:-60px; width:300px; height:300px; background:radial-gradient(circle,rgba(99,102,241,.08),transparent 70%); border-radius:50%; pointer-events:none; transform:translateX(-50%); }
.nset-header__left{ display:flex; align-items:center; gap:14px; position:relative; z-index:1; }
.nset-header__icon{ width:48px; height:48px; border-radius:14px; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center; color:#60a5fa; font-size:22px; backdrop-filter:blur(8px); }
.nset-header__title{ font-size:1.35rem; font-weight:800; color:#fff; margin:0; letter-spacing:-.3px; }
.nset-header__sub{ color:#94a3b8; font-size:0.8rem; margin:4px 0 0; font-weight:500; }
.nset-header__right{ display:flex; align-items:center; gap:10px; flex-shrink:0; position:relative; z-index:1; }
.nset-pill{ display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; font-size:11px; font-weight:700; }
.nset-pill i{ font-size:12px; }
.nset-pill--light{ background:rgba(255,255,255,.1); color:#e2e8f0; border:1px solid rgba(255,255,255,.1); backdrop-filter:blur(8px); }
.nset-pill--dark{ background:#2563eb; color:#fff; }
.nset-save-btn{
  display:inline-flex; align-items:center; gap:7px;
  padding:10px 20px; border-radius:10px;
  background:linear-gradient(135deg,#22c55e 0%,#16a34a 100%);
  color:#fff; font-size:12.5px; font-weight:700;
  border:none; cursor:pointer; transition:all .2s ease;
  box-shadow:0 2px 12px rgba(34,197,94,.3);
  position:relative;
}
.nset-save-btn i{ font-size:15px; }
.nset-save-btn:hover{ transform:translateY(-2px); box-shadow:0 6px 20px rgba(34,197,94,.4); }
.nset-save-btn--dirty{
  background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);
  box-shadow:0 2px 12px rgba(245,158,11,.35);
  animation:nset-pulse 2s ease-in-out infinite;
}
.nset-save-btn--dirty:hover{ box-shadow:0 6px 20px rgba(245,158,11,.45); }
.nset-save-btn__dot{
  width:8px; height:8px; border-radius:50%; background:#fff;
  position:absolute; top:6px; right:6px;
  animation:nset-dot-pulse 1.5s ease-in-out infinite;
}
@keyframes nset-pulse{ 0%,100%{ box-shadow:0 2px 12px rgba(245,158,11,.35); } 50%{ box-shadow:0 2px 20px rgba(245,158,11,.55); } }
@keyframes nset-dot-pulse{ 0%,100%{ opacity:1; transform:scale(1); } 50%{ opacity:.6; transform:scale(1.3); } }

/* ── Stats ── */
.nset-unsaved-banner{
  display:flex; align-items:center; gap:8px; padding:10px 16px;
  background:#fffbeb; border:1px solid #fde68a; border-radius:12px;
  font-size:12px; color:#92400e; font-weight:600;
}
.nset-unsaved-banner i{ color:#f59e0b; font-size:16px; }
.nset-unsaved-banner b{ color:#78350f; font-weight:800; }
.nset-banner-enter-active{ animation:nset-slide-down .25s ease-out; }
.nset-banner-leave-active{ animation:nset-slide-down .2s ease-in reverse; }
@keyframes nset-slide-down{ from{ opacity:0; transform:translateY(-8px); max-height:0; padding:0 16px; margin:0; } to{ opacity:1; transform:translateY(0); max-height:60px; } }

.nset-stats{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
.nset-stat{
  display:flex; align-items:center; gap:12px;
  background:#fff; border:1px solid #e2e8f0; border-radius:14px;
  padding:16px 18px; transition:all .2s ease;
}
.nset-stat:hover{ border-color:#cbd5e1; box-shadow:0 4px 16px rgba(15,23,42,.06); transform:translateY(-2px); }
.nset-stat--active{ border-color:#3b82f6; background:linear-gradient(135deg,#eff6ff 0%,#fff 100%); }
.nset-stat__icon{ width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.nset-stat__body{ display:flex; flex-direction:column; gap:2px; min-width:0; }
.nset-stat__label{ font-size:0.6rem; font-weight:800; color:#94a3b8; letter-spacing:.08em; text-transform:uppercase; }
.nset-stat__value{ font-size:1.1rem; font-weight:800; color:#0f172a; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nset-stat__value--truncate{ max-width:160px; }
.nset-stat__sub{ font-size:0.65rem; color:#94a3b8; font-weight:600; }

/* ── Main Grid ── */
.nset-grid{ display:grid; grid-template-columns:360px 1fr 340px; gap:16px; align-items:stretch; }

/* ── Card ── */
.nset-card{
  background:#fff; border:1px solid #e2e8f0; border-radius:14px;
  display:flex; flex-direction:column; overflow:hidden;
  box-shadow:0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.02);
}
.nset-card__head{
  display:flex; justify-content:space-between; align-items:center;
  padding:16px 20px;
  background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);
  border-bottom:1px solid #e2e8f0;
}
.nset-card__title{ font-size:0.85rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px; }
.nset-card__title i{ font-size:16px; }
.nset-badge{ min-width:24px; height:24px; padding:0 8px; border-radius:999px; background:#0f172a; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; }
.nset-badge--primary{ background:#2563eb; }

/* ── Left Column: Groups ── */
.nset-col-left{ }
.nset-groups-scroll{ display:flex; flex-direction:column; gap:8px; padding:12px; overflow:auto; flex:1; }
.nset-group{
  display:flex; align-items:center; gap:10px; width:100%; text-align:left;
  background:#fff; border:1.5px solid #f1f5f9; border-radius:12px;
  padding:12px; transition:all .18s ease; cursor:pointer; position:relative;
}
.nset-group:hover{ border-color:#e2e8f0; box-shadow:0 4px 12px rgba(15,23,42,.06); transform:translateY(-1px); }
.nset-group.is-active{ border-color:var(--group-color,#2563eb); background:var(--group-bg,#eff6ff); box-shadow:0 4px 16px rgba(37,99,235,.1); }
.nset-group.is-active::before{ content:''; position:absolute; left:0; top:10px; bottom:10px; width:3px; border-radius:0 3px 3px 0; background:var(--group-color,#2563eb); }
.nset-group__icon{ width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.nset-group__info{ flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
.nset-group__title{ font-size:12.5px; font-weight:700; color:#0f172a; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nset-group__desc{ font-size:11px; color:#64748b; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nset-group__tags{ display:flex; gap:4px; margin-top:3px; }
.nset-tag{ font-size:9.5px; font-weight:700; padding:2px 6px; border-radius:999px; letter-spacing:.02em; }
.nset-tag--key{ background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
.nset-group__count{ min-width:28px; height:28px; border-radius:999px; color:#fff; font-weight:800; font-size:11px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }

/* ── Center Column: Users ── */
.nset-col-center{ }
.nset-hint-tag{ font-size:10px; font-weight:700; color:#154b91; background:#eff6ff; border:1px solid #bfdbfe; padding:4px 10px; border-radius:999px; display:inline-flex; align-items:center; gap:5px; }
.nset-toolbar{ display:flex; gap:8px; padding:12px 14px; align-items:center; background:#fff; border-bottom:1px solid #f1f5f9; }
.nset-search{ flex:1; min-width:160px; display:flex; align-items:center; gap:8px; background:#fff; border:1.5px solid #e2e8f0; border-radius:10px; padding:8px 12px; transition:all .15s; }
.nset-search i{ color:#94a3b8; font-size:14px; flex-shrink:0; }
.nset-search input{ flex:1; border:none; outline:none; background:transparent; font-size:12.5px; color:#0f172a; font-weight:500; min-width:0; }
.nset-search input::placeholder{ color:#94a3b8; }
.nset-search:focus-within{ border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.08); }
.nset-search__clear{ border:none; background:#f1f5f9; width:20px; height:20px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; color:#64748b; font-size:10px; cursor:pointer; transition:all .15s; flex-shrink:0; }
.nset-search__clear:hover{ background:#e2e8f0; color:#475569; }
.nset-select{ width:140px; border-radius:10px; border:1.5px solid #e2e8f0; font-size:12px; font-weight:600; color:#0f172a; background:#fff; padding:8px 10px; cursor:pointer; flex-shrink:0; }
.nset-select:focus{ outline:none; border-color:#3b82f6; }
.nset-btn{ display:inline-flex; align-items:center; gap:5px; padding:8px 14px; border-radius:10px; font-size:12px; font-weight:700; cursor:pointer; transition:all .15s; border:none; flex-shrink:0; }
.nset-btn--primary{ background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%); color:#fff; box-shadow:0 2px 8px rgba(37,99,235,.2); }
.nset-btn--primary:hover{ transform:translateY(-1px); box-shadow:0 4px 12px rgba(37,99,235,.3); }
.nset-btn--ghost{ background:#fff; border:1.5px solid #e2e8f0; color:#475569; }
.nset-btn--ghost:hover{ background:#f8fafc; border-color:#cbd5e1; }
.nset-table-area{ flex:1; overflow:auto; min-height:0; display:flex; flex-direction:column; }
.nset-table-hint{ padding:10px 16px; font-size:11px; color:#94a3b8; display:flex; align-items:center; gap:6px; border-top:1px solid #f1f5f9; flex-shrink:0; }
.nset-table-hint b{ color:#0f172a; font-weight:700; }
.nset-table-hint i{ color:#2563eb; font-size:13px; }

/* ── PickleTable Overrides — Admin Order List Style ── */
.nset-table-area :deep(.pickletable){ border:none; background:transparent; flex:1; display:flex; flex-direction:column; height:100%!important; }
.nset-table-area :deep(.pickletable .divTable){ border-radius:0; overflow:hidden; border:none; background:#fff; flex:1; overflow:auto; }
.nset-table-area :deep(.pickletable table){ width:100%!important; border-collapse:separate!important; border-spacing:0!important; }
/* Header — light gray like admin orders */
.nset-table-area :deep(.pickletable thead){ position:sticky; top:0; z-index:2; }
.nset-table-area :deep(.pickletable thead tr){ background:#f8fafc!important; }
.nset-table-area :deep(.pickletable thead th),
.nset-table-area :deep(.pickletable thead td){
  background:transparent!important;
  color:#64748b!important;
  font-size:0.72rem!important;
  font-weight:600!important;
  text-transform:uppercase!important;
  letter-spacing:.04em!important;
  padding:11px 16px!important;
  border-bottom:1px solid #e2e8f0!important;
  border-top:none!important;
  border-right:none!important;
  white-space:nowrap;
}
.nset-table-area :deep(.pickletable thead th:first-child),
.nset-table-area :deep(.pickletable thead td:first-child){ padding-left:16px!important; }
.nset-table-area :deep(.pickletable thead th:last-child),
.nset-table-area :deep(.pickletable thead td:last-child){ padding-right:16px!important; }
.nset-table-area :deep(.pickletable thead th .sort-icon),
.nset-table-area :deep(.pickletable thead td .sort-icon){ color:#94a3b8; font-size:10px; margin-left:4px; }
/* Filter row */
.nset-table-area :deep(.pickletable thead tr:nth-child(2) td){ padding:8px 12px!important; background:#f8fafc!important; border-bottom:1px solid #e2e8f0!important; }
.nset-table-area :deep(.pickletable thead tr:nth-child(2) td:first-child){ padding-left:16px!important; }
.nset-table-area :deep(.pickletable thead tr:nth-child(2) td:last-child){ padding-right:16px!important; }
.nset-table-area :deep(.pickletable thead tr:nth-child(2) input){
  width:100%; border:1px solid #e2e8f0; border-radius:8px;
  padding:7px 10px; font-size:0.82rem; color:#0f172a; background:#fff;
  transition:all .15s; outline:none; font-weight:500;
}
.nset-table-area :deep(.pickletable thead tr:nth-child(2) input::placeholder){ color:#94a3b8; }
.nset-table-area :deep(.pickletable thead tr:nth-child(2) input:focus){ border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.08); }
/* Body rows — clean admin style */
.nset-table-area :deep(.pickletable tbody tr){ background:#fff!important; transition:all .15s ease!important; cursor:pointer; }
.nset-table-area :deep(.pickletable tbody tr:hover){ background:#f8fafc!important; }
.nset-table-area :deep(.pickletable tbody td){
  padding:13px 16px!important;
  font-size:0.86rem!important;
  border-bottom:1px solid #f1f5f9!important;
  border-top:none!important;
  border-left:none!important;
  border-right:none!important;
  vertical-align:middle!important;
  color:#334155;
}
.nset-table-area :deep(.pickletable tbody tr:last-child td){ border-bottom:none!important; }
.nset-table-area :deep(.pickletable tbody td:first-child){ font-weight:700; color:#0f172a; }
.nset-table-area :deep(.pickletable tbody td:nth-child(2)){ color:#64748b; font-weight:500; }
/* Pagination */
.nset-table-area :deep(.pickletable .divPagination),
.nset-table-area :deep(.pickletable .pagination){ background:#fff!important; border-top:1px solid #f1f5f9!important; padding:10px 16px!important; display:flex; align-items:center; gap:6px; justify-content:flex-end; }
.nset-table-area :deep(.pickletable .pagination button),
.nset-table-area :deep(.pickletable .divPagination button){
  min-width:30px; height:30px; padding:0 8px; border-radius:8px;
  border:1px solid #e2e8f0; background:#fff; color:#475569;
  font-size:0.78rem; font-weight:600; cursor:pointer; transition:all .15s;
}
.nset-table-area :deep(.pickletable .pagination button:hover),
.nset-table-area :deep(.pickletable .divPagination button:hover){ background:#f1f5f9; border-color:#cbd5e1; }
.nset-table-area :deep(.pickletable .pagination button.active),
.nset-table-area :deep(.pickletable .divPagination button.active){ background:#0f172a; color:#fff; border-color:#0f172a; }

/* ── Right Column: Members ── */
.nset-col-right{ }
.nset-members-actions{ display:flex; align-items:center; gap:8px; }
.nset-icon-btn{ width:30px; height:30px; padding:0; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; border:1.5px solid #e2e8f0; background:#fff; color:#475569; font-size:13px; cursor:pointer; transition:all .15s; }
.nset-icon-btn:hover{ background:#f1f5f9; border-color:#cbd5e1; color:#2563eb; }
.nset-icon-btn--danger{ border-color:#fecaca; color:#dc2626; }
.nset-icon-btn--danger:hover{ background:#fef2f2; border-color:#f87171; }
.nset-selected-info{ display:flex; align-items:center; gap:10px; padding:12px 14px; margin:12px 12px 0; border:1px solid; border-radius:12px; }
.nset-selected-info__icon{ width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
.nset-selected-info__text{ flex:1; min-width:0; display:flex; flex-direction:column; gap:1px; }
.nset-selected-info__title{ font-weight:700; color:#0f172a; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nset-selected-info__desc{ font-size:10.5px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nset-selected-info__key{ font-size:10px; font-weight:800; background:#fff; border:1px solid #e2e8f0; padding:3px 8px; border-radius:999px; color:#475569; flex-shrink:0; }
.nset-members-body{ flex:1; overflow:auto; padding:12px; display:flex; flex-direction:column; }

/* Empty state */
.nset-empty{ text-align:center; padding:32px 16px; display:flex; flex-direction:column; align-items:center; gap:8px; flex:1; justify-content:center; }
.nset-empty__icon{ width:56px; height:56px; border-radius:16px; background:#f1f5f9; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:center; font-size:24px; color:#94a3b8; }
.nset-empty__icon--warn{ background:#fff7ed; border-color:#fed7aa; color:#f59e0b; }
.nset-empty__title{ font-weight:800; color:#0f172a; margin:0; font-size:13px; }
.nset-empty__desc{ font-size:12px; color:#64748b; max-width:220px; line-height:1.5; }
.nset-empty__hint{ margin-top:12px; background:#f8fafc; border:1px dashed #e2e8f0; padding:10px 12px; border-radius:10px; font-size:11px; color:#475569; max-width:240px; line-height:1.5; display:flex; align-items:flex-start; gap:6px; text-align:left; }
.nset-empty__hint i{ color:#2563eb; flex-shrink:0; margin-top:1px; }

/* Member list */
.nset-member-list{ display:flex; flex-direction:column; gap:8px; }
.nset-member{ display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid #f1f5f9; border-radius:12px; background:#fff; transition:all .18s; }
.nset-member:hover{ border-color:#e2e8f0; box-shadow:0 4px 12px rgba(15,23,42,.06); transform:translateY(-1px); }
.nset-member__idx{ font-size:10px; font-weight:800; color:#94a3b8; width:22px; height:22px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; background:#f1f5f9; }
.nset-member__avatar{ width:32px; height:32px; border-radius:999px; color:#fff; font-weight:800; font-size:11px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
.nset-member__info{ flex:1; min-width:0; display:flex; flex-direction:column; gap:1px; }
.nset-member__name{ font-size:12.5px; font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.3; }
.nset-member__mail{ font-size:11px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nset-member__remove{ width:28px; height:28px; border-radius:999px; border:1.5px solid #fecaca; background:#fff; color:#dc2626; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; transition:.15s; font-size:12px; cursor:pointer; }
.nset-member__remove:hover{ background:#dc2626; color:#fff; border-color:#dc2626; }

/* ── Responsive ── */
@media(max-width:1400px){ .nset-grid{ grid-template-columns:320px 1fr 300px; gap:12px; } }
@media(max-width:1100px){ .nset-grid{ grid-template-columns:1fr; } .nset-stats{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:768px){ .nset-header{ flex-direction:column; align-items:flex-start; gap:12px; } .nset-stats{ grid-template-columns:1fr; } }
</style>
