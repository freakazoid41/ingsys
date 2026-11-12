<script>
import { useNavigationStore } from '@/stores/navigation'
import { useAuthStore } from '@/stores/auth'
import { useHead } from '@unhead/vue'

import '../../css/front-shell.css';
export default {
    setup() { return { useNavigationStore, useAuthStore, useHead } },
    data() {
        return {
            navigationStore: useNavigationStore(),
            authStore: useAuthStore(),
            sysCode: 'ADM',
            showModuleModal: false,
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
                    title: 'İhale Yönetimi',
                    desc: 'İhaleler ve teklif süreçleri',
                    icon: 'ki-outline ki-chart-simple',
                    path: '/ihalepanel',
                    color: '#059669',
                    perm: 'per-041-03',
                    permAny: ['per-041-03', 'per-041-03-01'],
                },
                {
                    key: 'planlama',
                    title: 'Malzeme Planlama',
                    desc: 'Sipariş talepleri ve onay süreçleri',
                    icon: 'ki-outline ki-clipboard',
                    path: '/planlamapanel',
                    color: '#0e7490',
                    perm: 'per-041-05',
                },
            ],
        }
    },
    mounted() {
        // fetch notifications for Bilgilendirmeler badge
        try { this.navigationStore.getNotifications(); } catch(e) {}
        document.body.dataset.saTheme = localStorage.getItem("sa-theme");
        try {
            const el = document.querySelector('input[name="SYS_CODE"]');
            if (el && el.value) this.sysCode = el.value;
        } catch(e) {}
        // typewriter: ONLY tedarik-main travels, frame+tabs stay — hidden at browser limit
        document.body.style.background = '#f2f2f3';
        try { document.documentElement.style.scrollBehavior = 'smooth'; } catch(e) {}
        // html overflow:scroll set globally in custom.css (needed for ::-webkit-scrollbar to apply)
        document.body.style.height = '';
        document.body.style.transition = 'height 0.35s cubic-bezier(0.4,0,0.2,1)';
        this.$nextTick(()=> this.setupTypewriterScroll());
        window.addEventListener('resize', this.setupTypewriterScroll);
        window.addEventListener('scroll', this.syncTypewriter, { passive: true });
        // keep body height in sync when inner content grows (pickletable async)
        this.$nextTick(()=>{
            try {
                const inner = document.querySelector('.tedarik-main-inner');
                if(inner && window.ResizeObserver){
                    this._ro = new ResizeObserver(()=> {
                        clearTimeout(this._roTimer);
                        this._roTimer = setTimeout(()=> this.setupTypewriterScroll(), 80);
                    });
                    this._ro.observe(inner);
                }
            } catch(e) {}
        });
    },
    watch: {
        '$route.path'(){
            this.$nextTick(()=> setTimeout(()=> this.setupTypewriterScroll(), 220));
        }
    },
    beforeUnmount() {
        try { document.documentElement.style.scrollBehavior = ''; } catch(e) {}
        document.documentElement.style.overflowY = '';
        document.documentElement.style.overflow = '';
        window.removeEventListener('resize', this.setupTypewriterScroll);
    window.removeEventListener('scroll', this.syncTypewriter);
        try { if(this._ro) this._ro.disconnect(); } catch(e) {}
        clearTimeout(this._roTimer);
        document.body.style.height = '';
        document.documentElement.style.height = '';
        document.body.style.transition = '';
        const inner = document.querySelector('.tedarik-main-inner');
        if(inner) inner.style.transform = '';
    },
    computed: {
        tenantLogo() { return (String(this.sysCode || '').toUpperCase() === 'ADM') ? '/coaltheme/adm-logo.svg' : '/coaltheme/GDZ.svg'; },
        userName() { return this.authStore.userName || this.authStore.currentStatus?.main_name || ''; },
        canManage() { return (this.authStore.permissions || []).includes('per-041-03'); },
        canTender() { const p = this.authStore.permissions || []; return p.includes('per-041-03') || p.includes('per-041-03-01'); },
        isDashboardActive() { return this.$route.path === '/ihalepanel' || this.$route.path === '/ihalepanel/'; },
        isTenderListActive() {
            const p = this.$route.path;
            return p.startsWith('/ihalepanel/ihaleler') && !p.includes('/form') && !p.includes('/teklifler');
        },
        isNewTenderActive() { return this.$route.path.includes('/ihaleler/form'); },
        isOffersActive() { return this.$route.path.includes('/teklifler'); },
        isBilgilendirmelerActive() { return this.$route.path.includes('/bilgilendirmeler'); },
        modules() {
            const perms = this.authStore.permissions || [];
            const hasAll = perms.includes('per-041') || perms.includes('per-00');
            return this.allModules.filter(m => hasAll || perms.includes(m.perm) || (m.permAny && m.permAny.some(p => perms.includes(p))));
        },
        bilgilendirmeCount() {
            const n = this.navigationStore?.notifications || {};
            let c = 0;
            if (Array.isArray(n.tenderActivated)) c += n.tenderActivated.length;
            if (Array.isArray(n.tenderCompleted)) c += n.tenderCompleted.length;
            if (Array.isArray(n.offerSubmitted)) c += n.offerSubmitted.length;
            if (Array.isArray(n.offerRejected)) c += n.offerRejected.length;
            if (Array.isArray(n.offerAccepted)) c += n.offerAccepted.length;
            return c;
        },
    },
    methods: {
        isActive(path) { return this.$route.path === path || this.$route.path.startsWith(path); },
        goModule(mod){
            this.showModuleModal=false;
            if(!mod||!mod.path) return;
            const cur=window.location.pathname;
            if(cur.startsWith(mod.path)) return;

            // Only /ihalepanel lives in this SPA entry point; other modules are
            // separate shells and need a full-page navigation.
            if (mod.path.startsWith('/ihalepanel')) {
                try{ if(this.$router&&this.$router.push){ this.$router.push(mod.path); return; } }catch(e){}
            }
            window.location.href=mod.path;
        },
        isModuleActive(mod){
            const cur=window.location.pathname;
            return cur===mod.path||cur.startsWith(mod.path+'/');
        },
        setupTypewriterScroll(){
            try {
                // mobile: disable typewriter, let normal page scroll
                if(window.innerWidth <= 992){
                    document.body.style.height = '';
                    document.documentElement.style.height = '';
                    const innerM = document.querySelector('.tedarik-main-inner');
                    if(innerM) innerM.style.transform = '';
                    return;
                }
                const inner = document.querySelector('.tedarik-main-inner');
                const frame = document.querySelector('.tedarik-frame');
                if(!inner || !frame) return;
                // keep current transform - don't bounce to top
                const curTransform = inner.style.transform;
                setTimeout(()=>{
                    if(window.innerWidth <= 992) return;
                    const contentH = inner.scrollHeight;
                    const viewportH = window.innerHeight;
                    const frameChrome = 40;
                    const need = Math.max(contentH + frameChrome + 24, viewportH + 1);
                    const curBodyH = document.body.style.height;
                    const needStr = need + 'px';
                    frame.style.height = '';
                    frame.style.minHeight = '';
                    const main2 = document.querySelector('.tedarik-main');
                    if(main2) { main2.style.height = ''; main2.style.minHeight = ''; }
                    if(contentH + 48 > viewportH){
                        if(curBodyH !== needStr){
                            document.body.style.height = needStr;
                            document.documentElement.style.height = needStr;
                        }
                    } else {
                        if(curBodyH !== ''){
                            document.body.style.height = '';
                            document.documentElement.style.height = '';
                        }
                    }
                    // restore transform if it was there, then sync
                    if(curTransform) inner.style.transform = curTransform;
                    this.syncTypewriter();
                }, 60);
            } catch(e) {}
        },
        syncTypewriter(){
            try {
                if(window.innerWidth <= 992) return;
                const inner = document.querySelector('.tedarik-main-inner');
                if(!inner) return;
                const st = window.scrollY || document.documentElement.scrollTop || 0;
                inner.style.transform = `translateY(${-st}px)`;
                inner.style.willChange = 'transform';
            } catch(e) {}
        }
    }
}
</script>
<template>
    <div class="tedarik-root">
        <div class="tedarik-frame">
            <!-- Sidebar -->
            <aside class="tedarik-sidebar">
                <div class="tedarik-logo">
                    <img :src="tenantLogo" :alt="sysCode" :class="{ 'logo-adm': String(sysCode || '').toUpperCase() === 'ADM' }" @error="(e)=> e.target.style.display='none'" />
                    <div class="tedarik-logo-label">İhale ve Teklif İş Süreci</div>
                </div>

                <nav class="tedarik-menu">
                    <router-link to="/ihalepanel" custom v-slot="{ navigate, href }">
                        <a :href="href" @click="navigate" :class="['tedarik-menu-item', { active: isDashboardActive }]">
                            <i class="ki-outline ki-home-2 tedarik-menu-icon"></i>
                            <span>Ana Sayfa</span>
                            <i class="ki-outline ki-right tedarik-menu-arrow"></i>
                        </a>
                    </router-link>
                    <router-link v-if="canTender" to="/ihalepanel/ihaleler" custom v-slot="{ navigate, href }">
                        <a :href="href" @click="navigate" :class="['tedarik-menu-item', { active: isTenderListActive }]">
                            <i class="ki-outline ki-chart-simple tedarik-menu-icon"></i>
                            <span>İhaleler</span>
                            <i class="ki-outline ki-right tedarik-menu-arrow"></i>
                        </a>
                    </router-link>
                    <router-link v-if="canManage" to="/ihalepanel/ihaleler/form" custom v-slot="{ navigate, href }">
                        <a :href="href" @click="navigate" :class="['tedarik-menu-item', { active: isNewTenderActive }]">
                            <i class="ki-outline ki-plus tedarik-menu-icon"></i>
                            <span>Yeni İhale</span>
                            <i class="ki-outline ki-right tedarik-menu-arrow"></i>
                        </a>
                    </router-link>
                    <router-link v-if="canManage" to="/ihalepanel/ihaleler/teklifler" custom v-slot="{ navigate, href }">
                        <a :href="href" @click="navigate" :class="['tedarik-menu-item', { active: isOffersActive }]">
                            <i class="ki-outline ki-document tedarik-menu-icon"></i>
                            <span>Teklifler</span>
                            <i class="ki-outline ki-right tedarik-menu-arrow"></i>
                        </a>
                    </router-link>
                </nav>

                <div class="tedarik-bottom">
                    <router-link to="/tedarikpanel/bilgilendirmeler" custom v-slot="{ navigate, href }">
                        <a :href="href" @click="navigate" class="tedarik-info-card" :class="{ active: isBilgilendirmelerActive, 'has-unread': bilgilendirmeCount > 0 }">
                            <span class="tedarik-info-label">Bilgilendirmeler</span>
                            <span class="tedarik-info-badge" :class="{ 'is-zero': bilgilendirmeCount === 0 }">{{ bilgilendirmeCount }}</span>
                        </a>
                    </router-link>

                    <a href="javascript:;" class="tedarik-modules-btn" @click="showModuleModal=true">
                        <span>Modüller</span>
                        <span class="tedarik-modules-icon"><i class="ki-outline ki-element-11"></i></span>
                    </a>

                    <a href="/logout" class="tedarik-logout">
                        <span>Çıkış</span>
                        <span class="tedarik-logout-icon"><i class="ki-outline ki-entrance-left"></i></span>
                    </a>
                </div>

                <teleport to="body">
                    <div v-if="showModuleModal" class="module-modal-overlay tedarik-module-overlay" @click.self="showModuleModal=false">
                        <div class="module-modal-card" role="dialog" aria-modal="true" aria-label="Modüller">
                            <div class="module-modal-head">
                                <div class="module-modal-title">
                                    <span class="module-modal-icon-wrap"><i class="ki-outline ki-element-11"></i></span>
                                    <div>
                                        <div class="module-modal-title-text">Modüller</div>
                                        <div class="module-modal-sub">Çalışmak istediğiniz panele geçin</div>
                                    </div>
                                </div>
                                <button class="module-modal-close" @click="showModuleModal=false" aria-label="Kapat"><i class="ki-outline ki-cross fs-2"></i></button>
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
            </aside>

            <!-- Main -->
            <main class="tedarik-main">
                <div class="tedarik-main-inner">
                    <router-view :key="$route.path"></router-view>
                </div>
            </main>
        </div>
    </div>
</template>
<!-- UNSCOPED GLOBAL: override pickletable defaults for entire tedarik panel -->
<style>
/* Hide native scrollbar — html has overflow:scroll so ::-webkit-scrollbar applies, width:0 makes it invisible */
html { scrollbar-width: none !important; -ms-overflow-style: none !important; }
html::-webkit-scrollbar { width: 0 !important; height: 0 !important; display: none !important; }

/* PickleTable overrides — tedarik panel */
.tedarik-main .pickletable {
    height: auto !important;
    border: none !important;
}
.tedarik-main .pickletable .divTable {
    height: auto !important;
    overflow: visible !important;
    border-bottom: none !important;
}
.tedarik-main .pickletable table {
    border-collapse: separate !important;
    border-spacing: 0 7px !important;
    table-layout: fixed !important;
    border: none !important;
    width: 100% !important;
}
.tedarik-main .pickletable thead {
    position: sticky !important;
    top: 0 !important;
    z-index: 5 !important;
    background: #fff !important;
}
.tedarik-main .pickletable thead th {
    background: #fff !important;
    color: #b0b0b5 !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    border: none !important;
    border-bottom: none !important;
    box-shadow: none !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 5 !important;
    padding: 10px 14px !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    text-align: left !important;
    vertical-align: middle !important;
    line-height: 1.4 !important;
}
.tedarik-main .pickletable thead th:last-child {
    text-align: right !important;
}
.tedarik-main .pickletable thead th.orderable span {
    display: inline-flex !important;
    align-items: center !important;
    line-height: 1 !important;
    vertical-align: middle !important;
}
.tedarik-main .pickletable thead th.orderable span:after {
    display: inline-flex !important;
    align-items: center !important;
    line-height: 1 !important;
    margin-left: 6px !important;
}
.tedarik-main .pickletable thead th i, .tedarik-main .pickletable thead th svg {
    vertical-align: middle !important;
}
.tedarik-main .pickletable tbody tr {
    background: #fff !important;
    border-radius: 14px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important;
    border: none !important;
}
.tedarik-main .pickletable tbody tr:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
}
.tedarik-main .pickletable tbody tr:hover td {
    background: transparent !important;
}
.tedarik-main .pickletable tbody td {
    background: transparent !important;
    padding: 14px 12px !important;
    font-size: 13px !important;
    color: #333 !important;
    border: none !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.tedarik-main .pickletable tbody td:first-child {
    border-radius: 14px 0 0 14px !important;
    font-weight: 600 !important;
    color: #111827 !important;
}
.tedarik-main .pickletable tbody td:last-child {
    border-right: 1px solid #e8e8ea !important;
    border-top-right-radius: 8px !important;
    border-bottom-right-radius: 8px !important;
}
.tedarik-main .pickletable .divPagination {
    border-top: none !important;
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    padding: 12px 0 0 !important;
}
.tedarik-main .pickletable .divPagination button,
.tedarik-main .pickletable .divPagination .page-link {
    border: 1px solid #e5e7eb !important;
    background: #fff !important;
    color: #8a8a8e !important;
    border-radius: 6px !important;
    padding: 5px 10px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    min-width: 32px;
    height: 30px;
}
.tedarik-main .pickletable .divPagination .active button,
.tedarik-main .pickletable .divPagination .active .page-link {
    background: #fff !important;
    color: #FF5A1F !important;
    border-color: #FF5A1F !important;
    font-weight: 700 !important;
}
</style>

<style scoped>
.tedarik-menu-item.active {
    background: linear-gradient(135deg, #059669 0%, #34d399 100%);
    color: #fff;
    height: 70px;
    font-size: 18px;
    box-shadow: 0 6px 20px rgba(5,150,105,0.35);
    transform: scale(1.04);
    border-color: rgba(255,255,255,0.15);
}
.tedarik-info-card.has-unread .tedarik-info-badge { background: #059669; animation: tdk-badge-pulse 1.8s infinite; }
</style>
