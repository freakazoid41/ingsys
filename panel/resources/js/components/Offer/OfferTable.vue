<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import 'pickletable/assets/style.css';
import Plib from '@/lib/pickle';
import { wTrans } from 'laravel-vue-i18n';
import Swal from 'sweetalert2';
import VMasker  from 'vanilla-masker';

export default {
    name: 'OfferTable',
    props: {
        initialFilter: {
            type: Array,
            default: () => []
        },
        height: {
            type: String,
            default: '100vh'
        }
    },
    data(){
        return {
            plib : new Plib(),
            navigationStore : useNavigationStore(),
            authStore    : useAuthStore(),
            items: [],
            loading: false,
            error: null,
            // pagination
            page: 1,
            limit: 10,
            total: 0,
            totalPages: 1,
        }
    },
    mounted(){
        this.navigationStore.toggle(true);
        this.loadData().finally(() => this.navigationStore.toggle(false));
    },
    methods: {
        getRequestIdFromUrl(){
            try{
                const parts = window.location.pathname.split('/').filter(Boolean);
                const last = parts[parts.length-1] ?? '';
                // crude UUID check (hyphenated) to avoid false positives
                if(/^[0-9a-fA-F\-]{8,}$/i.test(last)) return last;
            }catch(e){}
            return null;
        },
        async loadData(){
            this.loading = true;
            this.error = null;
            try{
                const baseFilters = [ { key: 'form-type', type: '=', value: 'op-doc-offer-form' }, { key: 'type', type: '=', value: 'op-doc-offer' } ];
                const filters = this.initialFilter.length ? [...this.initialFilter] : baseFilters;
                // if URL has a request id (last path segment), prepend it as a 'request_id' like filter
                if(!filters.some(f => f.key === 'request_id')){
                    const rid = this.getRequestIdFromUrl();
                    if(rid) filters.unshift({ key: 'request_id', type: 'like', value: rid });
                }
                // Use the app's request wrapper to match server expectations
                // server expects a 'tableReq' form field with scale and filter JSON (see curl example)
                const tableReq = {
                    scale: { limit: this.limit, page: this.page },
                    filter: filters
                };
                let rsp = await this.plib.request({
                    url: '/api/v1/table/documents',
                    method: 'POST',
                    data: { tableReq: JSON.stringify(tableReq) }
                }, null);
                if(!rsp) {
                    // try fallback GET with querystring — some endpoints accept GET
                    rsp = await this.plib.request({
                        url: '/api/v1/table/documents?filter=' + encodeURIComponent(JSON.stringify(filters)),
                        method: 'GET'
                    }, null);
                }
                if(!rsp) throw new Error('Server returned an error');
                // Try common shapes: {data: [...]}, {rows: [...]}, or array directly
                let rows = rsp.data ?? rsp.rows ?? rsp;
                // If server returned an object with pagination envelope
                if(rows && rows.data) rows = rows.data;
                if(!Array.isArray(rows)) rows = [];

                // extract pagination meta when present
                try{
                    const envelope = rsp;
                    const totalItems = envelope.total ?? envelope.meta?.total ?? envelope.pagination?.total ?? null;
                    const perPage = envelope.per_page ?? envelope.meta?.per_page ?? envelope.pagination?.per_page ?? this.limit;
                    const lastPage = envelope.last_page ?? envelope.meta?.last_page ?? Math.ceil((totalItems ?? rows.length)/perPage);
                    if(totalItems) this.total = totalItems;
                    this.totalPages = lastPage ?? Math.max(1, Math.ceil((this.total || rows.length)/this.limit));
                }catch(e){}

                // flatten main_attr into fields
                this.items = rows.map(r => {
                    try{ JSON.parse(r.main_attr).forEach(el => r[el.Key] = el.Value); }catch(e){}
                    return r;
                });
            }catch(err){
                this.error = err.message || String(err);
                console.error(err);
            }finally{
                this.loading = false;
            }
        },
        statusInfo(status){
            const key = String(status ?? '').split('**');
            // If there's no status key, show a clear draft state
            if(!key[0]){
                return {
                    icon: '<i class="ki-outline ki-directbox-default fs-2 me-3"></i>',
                    label: 'Gönderildi',
                    btnClass: 'btn-default'
                };
            }
            let label = key[1] ?? '';
            let icon = '<i class="ph ph-timer fs-2 me-3"></i>';
            let btnClass = 'btn-default';
            switch(key?.[0]){
                case 'doc_trans_offer_draft':
                default:
                    if(key?.[1]) label = 'Taslak';
                    icon  = '<i class="ki-outline ki-directbox-default fs-2 me-3"></i>';
                    btnClass = 'btn-default';
                    break;
                case 'doc_trans_offer_sended':
                    if(key?.[1]) label = 'Teklif Gönderildi';
                    icon  = '<i class="ki-outline ki-timer fs-2 me-3"></i>';
                    btnClass = 'btn-default';
                    break;
                case 'doc_trans_offer_approved':
                    icon  = '<i class="ki-outline ki-check fs-2 me-3"></i>';
                    btnClass = 'btn-success';
                    break;
                case 'doc_trans_offer_revision':
                case 'doc_trans_offer_revised':
                case 'doc_trans_offer_review':
                    icon  = '<i class="ki-outline ki-timer fs-2 me-3"></i>';
                    btnClass = 'btn-warning';
                    break;
                case 'doc_trans_offer_rejected':
                    icon  = '<i class="ki-outline ki-cross-circle fs-2 me-3"></i>';
                    btnClass = 'btn-danger';
                    break;
            }
            return { icon, label: label || (key?.[0] ?? ''), btnClass };
        },
        formatOfferType(val){
            try{ return String(val ?? '').split('**')[1] ?? '' }catch{ return '' }
        },
        formatPrice(val){
            try{ return VMasker.toMoney(String(val ?? ''), {precision: 2, separator: ',', delimiter: '.', unit: '', zeroCents: false}); }catch{ return val ?? '' }
        }
    },
    computed: {
        pages(){
            const pages = [];
            const total = this.totalPages || 1;
            const start = Math.max(1, this.page - 2);
            const end = Math.min(total, this.page + 2);
            for(let i = start; i <= end; i++) pages.push(i);
            return pages;
        }
    }
}
</script>

<template>
    <div>
        <div v-if="loading" class="text-center p-4">Yükleniyor...</div>
        <div v-if="error" class="alert alert-danger">{{ error }}</div>

        <div class="d-flex flex-column gap-3 offer-list" :style="{ '--offer-height': height }">
            <div v-for="item in items" :key="item.id" class="w-100">
                <div class="card w-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold">{{ item.clititle }}</div>
                                <div class="text-muted small">{{ formatOfferType(item.offer_type) }}</div>
                            </div>
                            <div class="text-end">
                                
                                <div class="text-muted small">{{ item.date }}</div>
                            </div>
                        </div>
                        <div class="mb-2">Cinsi: <strong>{{ item.coal_type ?? '' }}</strong></div>
                        <div>Miktar Fiyat: <strong>{{ formatPrice(item.unit_price) }}</strong></div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-end gap-2 footer-buttons">
                                <button type="button" class="btn btn-sm rounded-pill px-3" :class="statusInfo(item.status).btnClass" v-html="statusInfo(item.status).icon + ' ' + statusInfo(item.status).label"></button>
                                <button class="btn btn-sm btn-outline-primary rounded-pill d-flex align-items-center px-3 shadow-sm" title="Detay Göster" @click="$router.push({ name: 'OForm', params: { id: item.id } })">
                                    <i class="ki-outline ki-eye fs-5 me-2"></i>
                                    <span>Detay Göster</span>
                                </button>
                            </div>
                        </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3" v-if="(total || items.length) > limit">
            <div class="text-muted">Sayfa {{ page }} / {{ totalPages }} — Toplam: {{ total || items.length }} </div>
            <div>
                <button class="btn btn-sm btn-outline-secondary me-2" :disabled="page <= 1" @click="(page>1) && (page--, loadData())">Önceki</button>
                <template v-for="p in pages" :key="p">
                    <button class="btn btn-sm me-1" :class="{'btn-primary': p===page, 'btn-outline-secondary': p!==page}" @click="(page=p, loadData())">{{ p }}</button>
                </template>
                <button class="btn btn-sm btn-outline-secondary ms-2" :disabled="page >= totalPages" @click="(page<totalPages) && (page++, loadData())">Sonraki</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.offer-list{
    height: var(--offer-height, 70vh);
    min-height: 0;
    overflow-y: auto;
}
.footer-buttons button{
    transition: transform .12s ease, box-shadow .12s ease;
}
.footer-buttons button:hover{
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(16,24,40,0.08);
}
.footer-buttons .btn-outline-primary{
    background: #fff;
}
</style>
