<script>
import { useAuthStore } from '@/stores/auth';
import { useNavigationStore } from '@/stores/navigation';
import { usePermissionDataStore } from '@/stores/permissiondata';

const ROLE_LABELS = {
    'op-pert-admin'   : 'Yönetici',
    'op-pert-buyer'   : 'Alıcı',
    'op-pert-reseller': 'Tedarikçi',
};

export default {
    components: {},
    setup() {
        return {
            useAuthStore,
            useNavigationStore,
        }
    },
    async mounted(){
        await useAuthStore().getPermissions();
        this.$nextTick(() => {
            this.markActiveRoute();
            this.bindAccordion();
        });
    },
    data() {
        return {
            authStore   : useAuthStore(),
            title       : document.querySelector('input[name="header"]').value,
            sysCode     : document.querySelector('input[name="SYS_CODE"]').value,
            isMini      : false,
            searchQuery : '',
            showModuleModal : false,
            allModules: [
                {
                    key: 'admin',
                    title: 'Yönetim Paneli',
                    desc: 'Siparişler, kullanıcılar ve sistem yönetimi',
                    icon: 'ki-outline ki-shield-tick',
                    path: '/coalpanel',
                    color: '#154b91',
                    perm: 'per-041-01',
                },
                {
                    key: 'tedarik',
                    title: 'Tedarik Yönetim Panel',
                    desc: 'Sipariş takibi ve tedarik süreçleri',
                    icon: 'ki-outline ki-delivery',
                    path: '/tedarikpanel',
                    color: '#FF5A1F',
                    perm: 'per-041-02',
                },
                {
                    key: 'ihale',
                    title: 'İhale',
                    desc: 'İhale süreçleri — Yakında',
                    icon: 'ki-outline ki-chart-simple',
                    path: '/coalpanel',
                    color: '#059669',
                    perm: 'per-041-03',
                    disabled: true,
                },
                {
                    key: 'fabrika',
                    title: 'Fabrika Kabul',
                    desc: 'Fabrika kabul — Yakında',
                    icon: 'ki-outline ki-home-2',
                    path: '/coalpanel',
                    color: '#7c3aed',
                    perm: 'per-041-04',
                    disabled: true,
                },
            ],
        };
    },
    computed: {
        userName() {
            return this.authStore.userName
                || this.authStore.currentStatus?.main_name
                || 'Kullanıcı';
        },
        userRoleLabel() {
            return ROLE_LABELS[this.authStore.typeKey] || '-';
        },
        userInitials() {
            const src = this.authStore.userName
                || this.authStore.currentStatus?.main_name
                || '?';
            const parts = src.trim().split(/\s+/).filter(Boolean);
            return ((parts[0]?.[0] || '?') + (parts[1]?.[0] || '')).toLocaleUpperCase('tr');
        },
        modules() {
            const perms = this.authStore.permissions || [];
            // DEV_ADMIN (has all via getPermissions) will have per-041 etc; fallback show all if has per-041 parent
            const hasAll = perms.includes('per-041') || perms.includes('per-00');
            return this.allModules.filter(m => hasAll || perms.includes(m.perm));
        },
    },
    watch: {
        searchQuery() {
            this.applySearchFilter();
        },
    },
    methods: {
        toggleMini() {
            this.isMini = !this.isMini;
        },
        markActiveRoute() {
            const url = window.location.href.toString().split(window.location.host)[1].split('/');
            const fullUrl = window.location.protocol + '//' + window.location.host + '/' + url[1]
                + (url[2] !== undefined ? '/' + url[2] : '')
                + (url[3] !== undefined ? '/' + url[3] : '');

            document.querySelectorAll('#kt_aside_menu .menu-link').forEach(el => {
                if (el.href !== undefined && el.href === fullUrl) {
                    if (el.querySelector('.menu-title') !== null) {
                        el.querySelector('.menu-title').classList.add('active-link');
                    }
                    document.querySelectorAll('#kt_aside_menu span.menu-link').forEach(x => x.classList.remove('active'));
                    const mainRow = el.closest('.menu-row-main');
                    if (mainRow) {
                        mainRow.querySelector('.menu-link')?.classList.add('active');
                        mainRow.querySelector('.menu-arrow')?.classList.add('open');
                        mainRow.querySelector('.sub-menu')?.classList.remove('hidden-menu');
                    }
                }
            });
        },
        bindAccordion() {
            document.querySelectorAll('#kt_aside_menu .main-menu').forEach(item => {
                item.addEventListener('click', e => {
                    const row = item.closest('.menu-row-main');
                    if (!row) return;
                    item.querySelector('.menu-arrow')?.classList.toggle('open');
                    row.querySelector('.sub-menu')?.classList.toggle('hidden-menu');
                });
            });
        },
        applySearchFilter() {
            const q = (this.searchQuery || '').trim().toLocaleLowerCase('tr');
            const rows = document.querySelectorAll('#kt_aside_menu .menu-row-main');
            const sectionLabels = document.querySelectorAll('#kt_aside_menu .aside-section-label');

            rows.forEach(row => {
                const top = row.querySelector('.menu-section')?.textContent?.toLocaleLowerCase('tr') || '';
                const subs = Array.from(row.querySelectorAll('.sub-menu .menu-title')).map(n => n.textContent.toLocaleLowerCase('tr'));
                const match = !q || top.includes(q) || subs.some(s => s.includes(q));
                row.classList.toggle('d-none', !match);

                if (q && match && subs.some(s => s.includes(q))) {
                    row.querySelector('.sub-menu')?.classList.remove('hidden-menu');
                    row.querySelector('.menu-arrow')?.classList.add('open');
                }
            });

            sectionLabels.forEach(label => {
                let next = label.nextElementSibling;
                let anyVisible = false;
                while (next && !next.classList.contains('aside-section-label')) {
                    if (next.classList.contains('menu-row-main') && !next.classList.contains('d-none')) {
                        anyVisible = true; break;
                    }
                    next = next.nextElementSibling;
                }
                label.classList.toggle('d-none', !anyVisible);
            });
        },
        openModules() { this.showModuleModal = true; },
        closeModules() { this.showModuleModal = false; },
        goModule(mod) {
            this.showModuleModal = false;
            if (!mod || !mod.path) return;
            const cur = window.location.pathname;
            if (cur.startsWith(mod.path)) return;
            // SPA routes exist in same app — try router first, fallback to hard nav
            try {
                if (this.$router && this.$router.push) {
                    this.$router.push(mod.path);
                    return;
                }
            } catch(e) {}
            window.location.href = mod.path;
        },
        isModuleActive(mod) {
            const cur = window.location.pathname;
            return cur === mod.path || cur.startsWith(mod.path + '/');
        },
    }
}
</script>

<template>
    <div :id="'kt_aside'"
         :class="['aside d-flex flex-column', { 'aside-mini': isMini }]"
         data-kt-drawer="true"
         data-kt-drawer-name="aside"
         data-kt-drawer-activate="{default: true, lg: false}"
         data-kt-drawer-overlay="true"
         data-kt-drawer-width="auto"
         data-kt-drawer-direction="start"
         data-kt-drawer-toggle="#kt_aside_toggle">

        <!-- Scrollable üst blok: brand + user + search + menu (10a tek scroll) -->
        <div class="aside-scroll d-flex flex-column flex-grow-1" style="min-height:0; overflow-y:auto; overflow-x:hidden;">

            <!-- Brand: logo üstte + başlık altta + collapse btn -->
            <div class="aside-brand d-flex align-items-center px-4 py-3 flex-shrink-0">
                <a href="/coalpanel" class="d-flex flex-column align-items-start text-decoration-none flex-grow-1 overflow-hidden">
                    <img alt="Logo" :src="`/coaltheme/${sysCode}.svg`" class="aside-brand-logo">
                </a>
            </div>

            <!-- User card (2a) -->
            <div v-if="!isMini" class="aside-user d-flex align-items-center px-4 py-2 flex-shrink-0">
                <span class="aside-user-avatar d-flex align-items-center justify-content-center flex-shrink-0">
                    {{ userInitials }}
                </span>
                <div class="aside-user-info ms-3 flex-grow-1 overflow-hidden">
                    <div class="fw-bold text-dark text-truncate" :title="userName">{{ userName }}</div>
                    <div class="text-muted fs-7 text-truncate">{{ userRoleLabel }}</div>
                </div>
                <router-link v-if="authStore.personId"
                             :to="{ name: 'UForm', params: { id: authStore.personId } }"
                             class="btn btn-sm btn-icon flex-shrink-0"
                             title="Profil">
                    <i class="ki-outline ki-setting-2 fs-4 text-muted"></i>
                </router-link>
            </div>

            <!-- Search (3a) -->
            <div v-if="!isMini" class="aside-search mt-2 px-4 pb-2 flex-shrink-0" >
                <div class="position-relative">
                    <i class="ki-outline ki-magnifier fs-4 position-absolute top-50 start-0 ms-3 translate-middle-y text-muted"></i>
                    <input type="text"
                           class="form-control form-control-sm"
                           placeholder="Menüde ara..."
                           v-model="searchQuery">
                </div>
            </div>

            <hr v-if="!isMini" class="aside-divider mx-4 my-2">

            <!-- Menü -->
            <div class="aside-menu flex-grow-0 px-2 pb-3" id="kt_aside_menu_wrap">
                <div id="kt_aside_menu"
                     class="menu menu-column menu-title-gray-600 menu-state-primary menu-state-icon-primary menu-state-bullet-primary menu-icon-primary menu-arrow-primary fw-semibold fs-6"
                     data-kt-menu="true">

                    <!-- Anasayfa -->
                    <div class="menu-item py-1 menu-row-main">
                        <router-link :to="{ name: 'CIndex' }" class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-solid ki-home-2 fs-1"></i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section ps-2 py-1 text-dark">Anasayfa</span>
                            </div>
                        </router-link>
                    </div>

                    <!-- Section: OPERASYON (üstte) -->
                    <div v-if="!isMini" class="aside-section-label text-muted text-uppercase fs-8 fw-bold ls-1 px-3 pt-3 pb-1">
                        Operasyon
                    </div>

                    <!-- Siparişler (Order Management) -->
                    <div v-if="this.useAuthStore().permissions?.includes('per-05-01')"
                         data-kt-menu-trigger="click"
                         class="menu-item py-1 menu-row-main">
                        <span class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-solid ki-briefcase fs-1"></i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section ps-2 py-1 text-dark">Siparişler</span>
                                <span class="menu-arrow"><i class="ki-outline ki-down fs-7"></i></span>
                            </div>
                        </span>
                        <div class="sub-menu hidden-menu">
                            <div class="menu-item" v-if="this.useAuthStore().permissions?.includes('per-05-01')">
                                <router-link :to="{ name: 'OrderList' }" class="menu-link sub-link">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Sipariş Listesi</span>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Transferler removed: transfers are EBELN-X rows inside Sipariş Listesi, status shows Kalite Onayı/Dosyalar Kontrol -->
                    <div v-if="false" class="menu-item py-1 menu-row-main"></div>

                    <!-- Legacy Talep/Teklif hidden but keep route alive for compat -->
                    <div v-if="false" class="menu-item py-1 menu-row-main">
                        <span class="menu-link main-menu px-3"><span class="menu-section ps-2 py-1 text-dark">Legacy</span></span>
                    </div>

                    <!-- Dökümanlar -->
                    <div v-if="this.useAuthStore().permissions?.includes('per-07-01')"
                         class="menu-item py-1 menu-row-main">
                        <router-link :to="{ name: 'DList' }" class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-solid ki-document fs-1"></i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section ps-2 py-1 text-dark">Dökümanlar</span>
                            </div>
                        </router-link>
                    </div>

                    <!-- Firma -->
                    <div v-if="this.useAuthStore().permissions?.includes('per-06-01')"
                         data-kt-menu-trigger="click"
                         class="menu-item py-1 menu-row-main">
                        <span class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-solid ki-office-bag fs-1"></i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section ps-2 py-1 text-dark">Firma</span>
                                <span class="menu-arrow"><i class="ki-outline ki-down fs-7"></i></span>
                            </div>
                        </span>
                        <div class="sub-menu hidden-menu">
                            <div class="menu-item" v-if="this.useAuthStore().permissions?.includes('per-06-02')">
                                <router-link :to="{ name: 'CForm' }" class="menu-link sub-link">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Firma Oluştur</span>
                                </router-link>
                            </div>
                            <div class="menu-item" v-if="this.useAuthStore().permissions?.includes('per-06-01')">
                                <router-link :to="{ name: 'CList' }" class="menu-link sub-link">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Firma Listesi</span>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Section: YÖNETİM (altta) -->
                    <div v-if="!isMini && authStore.typeKey !== 'op-pert-reseller'" class="aside-section-label text-muted text-uppercase fs-8 fw-bold ls-1 px-3 pt-4 pb-1">
                        Yönetim
                    </div>

                    <!-- Kontrol Paneli -->
                    <div v-if="this.useAuthStore().permissions?.includes('per-04-01')"
                         data-kt-menu-trigger="click"
                         class="menu-item py-1 menu-row-main">
                        <span class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="21.248" height="20.702" viewBox="0 0 21.248 20.702">
                                    <path id="Union_6" data-name="Union 6" d="M17.095,19.1l-4.52-4.523-.006-.005a.357.357,0,0,1,.006-.5l.141-.141L8.988,10.2,10.3,8.882l.513-.514L14.549,12.1c.165-.208.419-.359.647-.14l4.523,4.519a1.809,1.809,0,0,1,.528,1.281,1.907,1.907,0,0,1-1.92,1.869A1.687,1.687,0,0,1,17.095,19.1ZM.35,18.392a.362.362,0,0,1,0-.723H.726v-.835a1.48,1.48,0,0,1,1.479-1.482H8.948a1.484,1.484,0,0,1,1.483,1.482v.835H10.8a.361.361,0,0,1,0,.723ZM5.2,13.542.943,9.284A1.107,1.107,0,0,1,2.508,7.718c.175.173.619.621.8.8l2.658,2.657c.39.455,1.139.892,1.117,1.565a1.119,1.119,0,0,1-1.884.8ZM3.308,7.5,8.114,2.692l3.68,3.681c-1.5,1.5-3.306,3.3-4.805,4.805Zm9.281-1.348c-.175-.172-.619-.621-.8-.8L9.132,2.692c-.177-.18-.624-.622-.8-.8A1.109,1.109,0,0,1,9.9.324l4.259,4.259a1.109,1.109,0,0,1-1.569,1.566Z" transform="translate(0.5 0.574)" fill="#154b91" stroke="rgba(0,0,0,0)" stroke-width="1"></path>
                                </svg>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section ps-2 py-1 text-dark">Kontrol Paneli</span>
                                <span class="menu-arrow"><i class="ki-outline ki-down fs-7"></i></span>
                            </div>
                        </span>
                        <div class="sub-menu hidden-menu">
                            <div class="menu-item" v-if="this.useAuthStore().permissions?.includes('per-04-04')">
                                <router-link :to="{ name: 'LList' }" class="menu-link sub-link">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Log Kayıtları</span>
                                </router-link>
                            </div>
                            <div class="menu-item" v-if="this.useAuthStore().permissions?.includes('per-04-04')">
                                <router-link :to="{ name: 'NList' }" class="menu-link sub-link">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Bildirim Kayıtları</span>
                                </router-link>
                            </div>
                            <div class="menu-item" v-if="this.useAuthStore().permissions?.includes('per-04-03')">
                                <router-link :to="{ name: 'Roles' }" class="menu-link sub-link">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Rol ve Yetki Yönetimi</span>
                                </router-link>
                            </div>
                            <div class="menu-item" v-if="this.useAuthStore().permissions?.includes('per-04-01')">
                                <router-link :to="{ name: 'UList' }" class="menu-link sub-link">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Kullanıcı Yönetimi</span>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Raporlar -->
                    <div v-if="this.useAuthStore().permissions?.includes('per-061-01') || this.useAuthStore().permissions?.includes('per-061')"
                         class="menu-item py-1 menu-row-main">
                        <a class="menu-link main-menu px-3" style="opacity:0.92; cursor:default;">
                            <span class="menu-icon me-0">
                                <i class="ki-outline ki-chart-simple fs-1"></i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section ps-2 py-1 text-dark">Raporlar <span style="font-size:10px; background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:1px 6px; border-radius:999px; margin-left:6px;">Yakında</span></span>
                            </div>
                        </a>
                    </div>

                    <!-- Bildirimler -->
                    <div v-if="this.useAuthStore().permissions?.includes('per-00-01')"
                         data-kt-menu-trigger="click"
                         class="menu-item py-1 menu-row-main">
                        <span class="menu-link main-menu px-3">
                            <span class="menu-icon me-0">
                                <i class="ki-solid ki-notification-on fs-1"></i>
                            </span>
                            <div class="menu-content d-flex justify-content-between align-items-center w-100">
                                <span class="menu-section ps-2 py-1 text-dark">Bildirimler</span>
                                <span class="menu-arrow"><i class="ki-outline ki-down fs-7"></i></span>
                            </div>
                        </span>
                        <div class="sub-menu hidden-menu">
                            <div class="menu-item" v-if="this.useAuthStore().permissions?.includes('per-00-01')">
                                <router-link :to="{ name: 'NSettings' }" class="menu-link sub-link">
                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                    <span class="menu-title">Bildirim Şablonları</span>
                                </router-link>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div v-if="authStore.currentStatus?.canResponse != true && authStore.typeKey == 'op-pert-reseller'"
                 class="alert alert-danger mx-3 my-2 fs-7"
                 role="alert">
                Firma bilgileriniz henüz onaylanmamıştır. Bu süreçte henüz teklif veremezsiniz.
            </div>
        </div>

        <!-- Footer (8a tam genişlik) -->
        <div class="aside-footer flex-shrink-0 px-3 py-3 border-top d-flex flex-column gap-2">
            <button @click="openModules" class="btn btn-light-primary w-100 d-flex align-items-center justify-content-center fw-bold" style="background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3;" :title="isMini ? 'Modüller' : ''">
                <i class="ki-outline ki-element-11 fs-3 me-2"></i>
                <span v-if="!isMini">Modüller</span>
            </button>
            <a href="/logout" class="btn btn-light-danger w-100 d-flex align-items-center justify-content-center fw-bold">
                <i class="ki-duotone ki-entrance-left fs-3 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <span v-if="!isMini">Çıkış</span>
            </a>
        </div>
    </div>

    <!-- Module Switcher Modal -->
    <teleport to="body">
        <div v-if="showModuleModal" class="module-modal-overlay" @click.self="closeModules" @keydown.esc="closeModules" tabindex="0">
            <div class="module-modal-card" role="dialog" aria-modal="true" aria-label="Modüller">
                <div class="module-modal-head">
                    <div class="module-modal-title">
                        <span class="module-modal-icon-wrap"><i class="ki-outline ki-element-11"></i></span>
                        <div>
                            <div class="module-modal-title-text">Modüller</div>
                            <div class="module-modal-sub">Çalışmak istediğiniz panele geçin</div>
                        </div>
                    </div>
                    <button class="module-modal-close" @click="closeModules" aria-label="Kapat"><i class="ki-outline ki-cross fs-2"></i></button>
                </div>
                <div class="module-modal-body">
                    <a v-for="mod in modules" :key="mod.key" href="javascript:;" class="module-card" :class="{ active: isModuleActive(mod), disabled: mod.disabled }" @click="!mod.disabled && goModule(mod)">
                        <span class="module-card-icon" :style="{ background: mod.color }"><i :class="mod.icon"></i></span>
                        <span class="module-card-text">
                            <span class="module-card-title">{{ mod.title }} <span v-if="mod.disabled" style="font-size:11px; background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:2px 6px; border-radius:999px; margin-left:6px;">Yakında</span></span>
                            <span class="module-card-desc">{{ mod.desc }}</span>
                            <span class="module-card-path">{{ mod.path }}</span>
                        </span>
                        <span class="module-card-arrow"><i class="ki-outline ki-right fs-3"></i></span>
                        <span v-if="isModuleActive(mod) && !mod.disabled" class="module-card-badge">Aktif</span>
                    </a>
                    <div v-if="!modules.length" style="padding:16px; text-align:center; color:#94a3b8; font-size:13px;">Yetkili modül bulunamadı.</div>
                </div>
                <div class="module-modal-foot">Admin olarak paneller arası serbest geçiş yapabilirsiniz.</div>
            </div>
        </div>
    </teleport>
</template>

<style>
.module-modal-overlay{
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(15,23,42,0.48);
    backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.module-modal-card{
    width: 100%; max-width: 560px;
    background: #fff; border-radius: 18px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.18);
    overflow: hidden; animation: moduleModalIn .18s ease;
}
@keyframes moduleModalIn{ from{ transform: translateY(8px) scale(.98); opacity:0 } to{ transform:none; opacity:1 } }
.module-modal-head{
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 20px 16px; border-bottom: 1px solid #eef2ff;
}
.module-modal-title{ display: flex; align-items: center; gap: 12px; }
.module-modal-icon-wrap{
    width: 40px; height: 40px; border-radius: 12px; background:#eef2ff; color:#3730a3;
    display: inline-flex; align-items: center; justify-content: center; font-size: 20px;
}
.module-modal-title-text{ font-size: 15px; font-weight: 800; color:#0f172a; line-height: 1; }
.module-modal-sub{ font-size: 12px; color:#64748b; margin-top: 4px; }
.module-modal-close{
    width: 36px; height: 36px; border-radius: 10px; border:1px solid #e2e8f0; background:#fff; color:#64748b;
    display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
}
.module-modal-close:hover{ background:#f8fafc; color:#0f172a; }
.module-modal-body{ padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.module-card{
    position: relative; display: flex; align-items: center; gap: 14px;
    padding: 16px 16px 16px 14px; border-radius: 14px; border:1px solid #e2e8f0; background:#fff;
    text-decoration: none; cursor: pointer; transition: all .15s ease;
}
.module-card:hover{ border-color:#c7d2fe; background:#f8fafc; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(99,102,241,0.08); }
.module-card.active{ border-color:#3730a3; background:#f5f3ff; }
.module-card-icon{
    width: 46px; height: 46px; border-radius: 13px; display:inline-flex; align-items:center; justify-content:center;
    color:#fff; font-size: 22px; flex-shrink:0;
}
.module-card-text{ display:flex; flex-direction:column; gap:2px; flex:1; min-width:0; }
.module-card-title{ font-size: 14.5px; font-weight: 800; color:#0f172a; line-height:1.1; }
.module-card-desc{ font-size: 12.5px; color:#64748b; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.module-card-path{ font-size: 11px; color:#94a3b8; font-family: ui-monospace, monospace; }
.module-card-arrow{ color:#94a3b8; flex-shrink:0; }
.module-card:hover .module-card-arrow{ color:#3730a3; }
.module-card-badge{
    position:absolute; top:12px; right:44px; background:#22c55e; color:#fff; font-size:10px; font-weight:800;
    padding: 2px 8px; border-radius:999px; letter-spacing:.04em;
}
.module-modal-foot{
    padding: 12px 20px 16px; font-size: 11.5px; color:#94a3b8; text-align:center; border-top: 1px solid #f1f5f9; background:#fcfcff;
}
</style>
