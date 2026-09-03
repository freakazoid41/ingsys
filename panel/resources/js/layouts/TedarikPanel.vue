<script>
import { useNavigationStore } from '@/stores/navigation'
import { useAuthStore } from '@/stores/auth'
import { useHead } from '@unhead/vue'

export default {
    setup() { return { useNavigationStore, useAuthStore, useHead } },
    data() {
        return {
            navigationStore: useNavigationStore(),
            authStore: useAuthStore(),
            sysCode: 'ADM',
            showModuleModal: false,
            modules: [
                {
                    key: 'admin',
                    title: 'Yönetim Paneli',
                    desc: 'Siparişler, kullanıcılar ve sistem yönetimi',
                    icon: 'ki-outline ki-shield-tick',
                    path: '/coalpanel',
                    color: '#154b91',
                },
                {
                    key: 'tedarik',
                    title: 'Tedarik Yönetim Panel',
                    desc: 'Sipariş takibi ve tedarik süreçleri',
                    icon: 'ki-outline ki-delivery',
                    path: '/tedarikpanel',
                    color: '#FF5A1F',
                },
            ],
        }
    },
    mounted() {
        document.body.dataset.saTheme = localStorage.getItem("sa-theme");
        try {
            const el = document.querySelector('input[name="SYS_CODE"]');
            if (el && el.value) this.sysCode = el.value;
        } catch(e) {}
        // typewriter: ONLY tedarik-main travels, frame+tabs stay — hidden at browser limit
        document.body.style.background = '#f2f2f3';
        try { document.documentElement.style.scrollBehavior = 'smooth'; } catch(e) {}
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
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
        userName() { return this.authStore.userName || this.authStore.currentStatus?.main_name || ''; },
        isOrdersActive() { return this.$route.path.includes('/orders'); },
        isDokumanActive() { return this.$route.path.includes('/document'); },
    },
    methods: {
        isActive(path) { return this.$route.path === path || this.$route.path.startsWith(path); },
        goModule(mod){
            this.showModuleModal=false;
            if(!mod||!mod.path) return;
            const cur=window.location.pathname;
            if(cur.startsWith(mod.path)) return;
            try{ if(this.$router&&this.$router.push){ this.$router.push(mod.path); return; } }catch(e){}
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
                    <img :src="`/coaltheme/${sysCode}.svg`" :alt="sysCode" @error="(e)=> e.target.style.display='none'" />
                    <div class="tedarik-logo-label">Malzeme Tedarik İş Süreci</div>
                </div>

                <nav class="tedarik-menu">
                    <router-link to="/tedarikpanel/orders" custom v-slot="{ navigate, href }">
                        <a :href="href" @click="navigate" :class="['tedarik-menu-item', { active: isOrdersActive }]">
                            <span>Siparişler</span>
                            <i class="ki-outline ki-right tedarik-menu-arrow"></i>
                        </a>
                    </router-link>
                    <router-link to="/tedarikpanel/documents" custom v-slot="{ navigate, href }">
                        <a :href="href" @click="navigate" :class="['tedarik-menu-item', { active: isDokumanActive }]">
                            <span>Doküman</span>
                            <i class="ki-outline ki-right tedarik-menu-arrow"></i>
                        </a>
                    </router-link>
                    <a class="tedarik-menu-item" @click="() => {}">
                        <span>Raporlar</span>
                        <i class="ki-outline ki-right tedarik-menu-arrow"></i>
                    </a>
                </nav>

                <div class="tedarik-bottom">
                    <a class="tedarik-info-card" href="javascript:;">
                        <span class="tedarik-info-label">Bilgilendirmeler</span>
                        <span class="tedarik-info-badge">0</span>
                    </a>

                    <a href="javascript:;" class="tedarik-modules-btn" @click="showModuleModal=true">
                        <span>Modüller</span>
                        <span class="tedarik-modules-icon"><i class="ki-outline ki-element-11"></i></span>
                    </a>

                    <a href="/logout" class="tedarik-logout">
                        <span>TEST TALK</span>
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
                                <a v-for="mod in modules" :key="mod.key" href="javascript:;" class="module-card" :class="{ active: isModuleActive(mod) }" @click="goModule(mod)">
                                    <span class="module-card-icon" :style="{ background: mod.color }"><i :class="mod.icon"></i></span>
                                    <span class="module-card-text">
                                        <span class="module-card-title">{{ mod.title }}</span>
                                        <span class="module-card-desc">{{ mod.desc }}</span>
                                        <span class="module-card-path">{{ mod.path }}</span>
                                    </span>
                                    <span class="module-card-arrow"><i class="ki-outline ki-right fs-3"></i></span>
                                    <span v-if="isModuleActive(mod)" class="module-card-badge">Aktif</span>
                                </a>
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
<style scoped>
.tedarik-root {
    background: #f2f2f3;
    position: fixed;
    inset: 0;
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
    padding: 22px 18px 18px 48px;
    display: flex;
    justify-content: center;
    align-items: stretch;
    box-sizing: border-box;
}
.tedarik-frame {
    width: 100%;
    max-width: 1360px;
    height: calc(100vh - 40px);
    height: calc(100dvh - 40px);
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.07);
    display: flex;
    align-items: stretch;
    overflow: visible;
    position: relative;
}
.tedarik-sidebar {
    width: 210px;
    min-width: 210px;
    background: transparent;
    padding: 22px 16px 16px 14px;
    display: flex;
    flex-direction: column;
    border-right: none;
    border-radius: 12px 0 0 12px;
    overflow: visible;
    position: relative;
    z-index: 10;
    height: 100%;
    flex-shrink: 0;
    align-self: stretch;
}
.tedarik-logo {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    margin-bottom: 22px;
    padding-left: 4px;
    gap: 8px;
}
.tedarik-logo img {
    height: 82px;
    width: auto;
    display: block;
    object-fit: contain;
}
.tedarik-logo-label {
    font-size: 11.5px;
    font-weight: 600;
    color: #6b7280;
    letter-spacing: 0.3px;
    line-height: 1;
    padding-left: 6px;
    white-space: nowrap;
    user-select: none;
}
.tedarik-menu {
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow: visible;
    flex: 1;
    justify-content: start;
    margin: 12px 0;
}
.tedarik-menu-item {
    height: 64px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 18px 0 20px;
    font-size: 17.5px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: all .15s ease;
    background: #8e8e93;
    color: #ffffff;
    border: none;
    user-select: none;
    letter-spacing: 0.15px;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-left: -52px;
    width: calc(100% + 52px);
}
.tedarik-menu-item:hover {
    background: #7e7e82;
    color: #fff;
}
.tedarik-menu-item.active {
    background: #FF5A1F;
    color: #fff;
    box-shadow: 0 2px 8px rgba(255,90,31,0.28);
}
.tedarik-menu-arrow {
    font-size: 25px;
    opacity: 0.9;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.tedarik-bottom {
    margin-top: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding-top: 12px;
    padding-bottom: 8px;
}
.tedarik-info-card {
    height: 64px;
    border: 1px solid #e6e6e7;
    border-radius: 12px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 18px;
    text-decoration: none;
    color: #2f2f33;
    font-size: 16.5px;
    font-weight: 600;
    transition: border-color .15s;
    margin-left: -52px;
    width: calc(100% + 52px);
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.tedarik-info-card:hover { border-color: #d1d5db; }
.tedarik-info-badge {
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #22c55e;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}
.tedarik-logout {
    height: 64px;
    border: 1.5px solid #ef3b3b;
    border-radius: 12px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 18px;
    text-decoration: none;
    color: #2f2f33;
    font-size: 16.5px;
    font-weight: 700;
    transition: background .15s;
    margin-left: -52px;
    width: calc(100% + 52px);
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.tedarik-logout:hover { background: #fef2f2; }
.tedarik-logout-icon {
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ef3b3b;
    font-size: 16px;
}

.tedarik-logout-icon i {font-size: 25px;}
.tedarik-modules-btn{
    height: 64px; border:1px solid #c7d2fe; border-radius:12px; background:#eef2ff;
    display:flex; align-items:center; justify-content:space-between; padding:0 18px;
    text-decoration:none; color:#3730a3; font-size:16.5px; font-weight:700;
    transition: all .15s; margin-left:-52px; width:calc(100% + 52px); position:relative;
    box-shadow:0 2px 8px rgba(0,0,0,0.04); cursor:pointer;
}
.tedarik-modules-btn:hover{ background:#e0e7ff; border-color:#a5b4fc; }
.tedarik-modules-icon{ width:20px; height:20px; display:inline-flex; align-items:center; justify-content:center; font-size:18px; color:#6366f1; }
.tedarik-module-overlay.module-modal-overlay{ padding: 18px 14px 18px 48px; justify-content: center; }
.tedarik-main {
    flex: 1;
    min-width: 0;
    min-height: 0;
    height: 100%;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    overflow: visible;
    border-left: 1px solid #f2f2f3;
    border-radius: 0 12px 12px 0;
    position: relative;
    z-index: 1;
    align-self: stretch;
}
.tedarik-main-inner {
    flex: 0 0 auto;
    min-height: min-content;
    height: auto;
    padding: 22px 24px 10px 20px;
    overflow: visible;
    min-width: 0;
    display: flex;
    flex-direction: column;
    will-change: transform;
    background: #ffffff;
    border-radius: 0 12px 0 0;
}
.tedarik-main-inner > * {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    min-height: min-content;
}
@media (max-width: 992px) {
    .tedarik-root { position: relative; inset: auto; height: auto; min-height: 100vh; overflow: visible; align-items: flex-start; padding: 0; }
    .tedarik-frame { border-radius: 0; height: auto; max-height: none; min-height: 100vh; flex-direction: column; overflow: visible; align-items: stretch; position: relative; }
    .tedarik-sidebar { position: relative; top: auto; width: 100%; min-width: 0; height: auto; min-height: 0; max-height: none; border-right: none; border-bottom: 1px solid #f1f5f9; overflow: visible; align-self: auto; }
    .tedarik-main { height: auto; min-height: 0; overflow: visible; align-self: auto; }
    .tedarik-main-inner { height: auto; min-height: 0; overflow: visible; transform: none !important; will-change: auto; }
    .tedarik-menu-item { margin-left: 0; width: 100%; }
    .tedarik-info-card { margin-left: 0; width: 100%; }
    .tedarik-logout { margin-left: 0; width: 100%; }
    .tedarik-modules-btn { margin-left: 0; width: 100%; }
}
</style>
<!-- UNSCOPED GLOBAL: override pickletable defaults for entire tedarik panel -->
<style>

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
    table-layout: auto !important;
    border: none !important;
    width: 100% !important;
}
.tedarik-main .pickletable thead th {
    background: transparent !important;
    color: #b0b0b5 !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    border: none !important;
    box-shadow: none !important;
    position: relative !important;
    top: auto !important;
    padding: 0 14px 10px !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
}
.tedarik-main .pickletable thead th:last-child {
    text-align: right !important;
}
.tedarik-main .pickletable tbody tr {
    background: transparent !important;
}
.tedarik-main .pickletable tbody tr:hover td {
    background: #fcfcfc !important;
}
.tedarik-main .pickletable tbody td {
    background: #fff !important;
    border: 1px solid #e8e8ea !important;
    border-left: 1px solid #e8e8ea !important;
    border-right: 1px solid #e8e8ea !important;
    font-size: 13.5px !important;
    padding: 13px 14px !important;
    text-overflow: clip !important;
    overflow: visible !important;
    white-space: nowrap !important;
    vertical-align: middle !important;
}
.tedarik-main .pickletable tbody td:first-child {
    border-left: 1px solid #e8e8ea !important;
    border-top-left-radius: 8px !important;
    border-bottom-left-radius: 8px !important;
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
