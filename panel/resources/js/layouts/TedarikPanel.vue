<script>
import { useNavigationStore } from '@/stores/navigation'
import { useHead } from '@unhead/vue'

export default {
    setup() { return { useNavigationStore, useHead } },
    data() { return { navigationStore: useNavigationStore() } },
    mounted() {
      document.body.dataset.saTheme = localStorage.getItem("sa-theme");
    },
}
</script>
<template>
    <div class="page d-flex flex-column flex-root" style="background:#f2f2f3; min-height:100vh;">
        <!-- Minimal header for public Tedarik panel -->
        <div class="d-flex align-items-center justify-content-between px-4 py-3" style="background:#fff; border-bottom:1px solid #e5e7eb;">
            <a href="/tedarikpanel" class="d-flex align-items-center text-decoration-none">
                <svg width="90" height="32" viewBox="0 0 110 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <text x="0" y="28" font-family="Inter, Arial, sans-serif" font-size="30" font-weight="800" fill="#FF4A15" letter-spacing="-1">Gdz</text>
                    <g transform="translate(62, 4)">
                        <path d="M6 4 L22 0 L22 8 L6 12 Z" fill="#FF4A15"/><path d="M6 14 L22 10 L22 18 L6 22 Z" fill="#FF4A15"/><path d="M24 4 L38 12 L24 20 Z" fill="#FF4A15"/>
                    </g>
                </svg>
                <span class="ms-3 fw-semibold" style="color:#111827; font-size:14px;">Sipariş Platformu — Tedarik Yönetim Paneli</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted" style="font-size:13px;">{{ navigationStore.breadcrumps?.title || '' }}</span>
                <a href="/logout" class="btn btn-sm" style="background:#FF4A15; color:#fff; border-radius:8px; padding:6px 14px; font-weight:600;">Çıkış</a>
            </div>
        </div>
        <div class="flex-grow-1 p-4">
            <div class="container-xxl">
                <router-view :key="$route.path"></router-view>
            </div>
        </div>
    </div>
</template>
<style>
#kt_content_container { max-height: unset !important; height: auto !important; overflow: visible !important; }
</style>
