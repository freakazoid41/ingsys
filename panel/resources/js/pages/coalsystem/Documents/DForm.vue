<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import dayjs from 'dayjs';

export default {
    breadcrumbs: {
        list: [{ title: 'Belgeler', path: '/coalpanel/documents' }, { title: 'Belge Detayı', path: '#' }],
        title: 'Belge Detayı'
    },
    setup() { return { useNavigationStore, useAuthStore, Plib, Swal, dayjs } },
    computed: {
        isTedarik() { return this.$route.path.startsWith('/tedarikpanel'); },
        fileId() { return this.$route.params.id || ''; },
        canDecide() { return (this.authStore.permissions||[]).includes('per-07-02'); }
    },
    data() {
        return {
            plib: new Plib(),
            navigationStore: useNavigationStore(),
            authStore: useAuthStore(),
            loading: true,
            error: '',
            order: null,
            items: [],
            files: [],
            selectedId: '',
            choice: '',
            note: '',
            saving: false,
        }
    },
    mounted(){
        this.navigationStore.toggle(true);
        if(!this.fileId){ this.$router.replace({ name: this.isTedarik?'TedarikDList':'DList'}); return; }
        this.selectedId = this.fileId;
        this.fetchDetail();
    },
    methods: {
        fmtDate(v){
            if(!v) return '—';
            const d=dayjs(v);
            return d.isValid()? d.format('DD.MM.YYYY') : String(v).slice(0,10);
        },
        fmtDateTime(v){
            if(!v) return '—';
            const d=dayjs(v);
            return d.isValid()? d.format('DD.MM.YYYY HH:mm') : String(v);
        },
        parseStatus(f){
            const s=f?.last_status;
            if(!s) return {};
            if(typeof s === 'string'){ try{ return JSON.parse(s);}catch(e){ return {}; } }
            return s;
        },
        statusLabel(f){
            const s=this.parseStatus(f);
            if(s.title) return s.title;
            const k=s.op_key;
            if(k==='doc_file_accepted') return 'Başarılı';
            if(k==='doc_file_rejected') return 'Başarısız';
            if(k==='doc_file_waiting') return 'Kontrol Bekliyor';
            return 'Bekleniyor';
        },
        statusCls(f){
            const k=this.parseStatus(f)?.op_key;
            if(k==='doc_file_accepted') return 'is-success';
            if(k==='doc_file_rejected') return 'is-fail';
            if(k==='doc_file_refreshed') return 'is-refresh';
            return 'is-waiting';
        },
        personName(f){
            const s=this.parseStatus(f);
            return s.name || '—';
        },
        noteOf(f){
            const s=this.parseStatus(f);
            let n=s.note||'';
            // s.note is t.description JSON `{"actor":".. <email>","note": "real reason"|null}` — unwrap inner note, never show raw JSON when note is null
            try{
                const parsed = JSON.parse(n);
                if(parsed && typeof parsed.note === 'string') n = parsed.note;
                else if(parsed && parsed.note == null) n = '';
                else if(parsed && parsed.note != null) n = String(parsed.note);
            }catch(e){}
            return (n||'').trim();
        },
        isOldVersion(f){
            // document_files.status 0 = replaced/disabled old version — never editable (your rule)
            return String(f?.file_status ?? f?.status ?? '1') === '0';
        },
        isDecidable(f){
            if(this.isOldVersion(f)) return false;
            if(!this.canDecide) return false;
            const k=this.parseStatus(f)?.op_key;
            return k==='doc_file_waiting' || k==='doc_file_refreshed' || !k;
        },
        openFile(f){
            const qnid=f.file_qnid || f.file_qnid || this.fileId;
            window.open('/order-file/'+qnid, '_blank');
        },
        goOrder(){
            if(!this.order?.qnid) return;
            const name=this.isTedarik ? 'TedarikOrderForm' : 'OrderForm';
            this.$router.push({ name, params:{ id:this.order.qnid }});
        },
        async fetchDetail(){
            this.loading=true; this.error='';
            try{
                const rsp=await this.plib.request({ url:'/api/v1/file-detail/'+this.fileId, method:'GET'});
                if(!rsp || !rsp.success){
                    this.error=rsp?.msg || rsp?.message || 'Yüklenemedi';
                    return;
                }
                const d=rsp.data||rsp;
                this.order=d.order||null;
                this.items=d.items||[];
                // map files, mark selected
                this.files=(d.files||[]).map(f=> ({...f, _sel: f.file_qnid===this.selectedId }));
                // preselect choice from current file's status
                const cur=this.files.find(f=> f._sel) || this.files[0];
                if(cur){
                    const k=this.parseStatus(cur).op_key;
                    if(k==='doc_file_accepted') this.choice='accept';
                    else if(k==='doc_file_rejected') this.choice='reject';
                }
                this.note=this.noteOf(cur)||'';
            }catch(e){
                this.error=e?.msg||e?.message||String(e);
            } finally {
                this.loading=false;
                setTimeout(()=> this.navigationStore.toggle(false), 300);
                // docs page is not typewriter — kill frame stretch
                setTimeout(()=>{ try{
                    const frame=document.querySelector('.tedarik-frame');
                    const main=document.querySelector('.tedarik-main');
                    if(frame){ frame.style.height='auto'; frame.style.minHeight='0';}
                    if(main){ main.style.height='auto';}
                    const inner=document.querySelector('.tedarik-main-inner');
                    if(inner) inner.style.transform='none';
                    document.body.style.height=''; document.documentElement.style.height='';
                }catch(e){}}, 400);
            }
        },
        selectFile(f){
            this.selectedId=f.file_qnid;
            this.files=this.files.map(x=> ({...x, _sel: x.file_qnid===this.selectedId}));
            const cur=f;
            const k=this.parseStatus(cur).op_key;
            if(k==='doc_file_accepted') this.choice='accept';
            else if(k==='doc_file_rejected') this.choice='reject';
            else this.choice='';
            this.note=this.noteOf(cur)||'';
            // update url without reload
            const name=this.isTedarik?'TedarikDForm':'DForm';
            this.$router.replace({ name, params:{ id:f.file_qnid }});
        },
        goBack(){
            this.$router.push({ name: this.isTedarik?'TedarikDList':'DList'});
        },
        async saveStatus(){
            if(!this.choice){ Swal.fire({ icon:'warning', title:'Seçim yapın', text:'Onayla veya Reddet seçin'}); return; }
            const cur=this.files.find(f=> f._sel);
            if(!cur) return;
            const op_key = this.choice==='accept' ? 'doc_file_accepted' : 'doc_file_rejected';
            // rejected requires note? optional but show warning style like old: "Açıklama" mandatory when reject? Keep optional to match retake.
            this.saving=true;
            Swal.fire({ title:'Kaydediliyor...', allowOutsideClick:false, didOpen:()=> Swal.showLoading()});
            try{
                const fd=new FormData();
                fd.append('id', cur.file_qnid);
                fd.append('op_key', op_key);
                fd.append('note', (this.note||'').trim());
                const rsp=await this.plib.request({ url:'/api/v1/trans/set-file-status', method:'POST'}, null, fd);
                if(rsp && rsp.success){
                    Swal.close();
                    this.plib.toast(Swal,'success','Durum güncellendi');
                    await this.fetchDetail();
                } else {
                    Swal.fire({ icon:'error', title:'Hata', text: rsp?.msg || rsp?.message || 'Kaydedilemedi'});
                }
            }catch(e){
                Swal.fire({ icon:'error', title:'Hata', text: e?.msg || e?.message || String(e)});
            } finally{ this.saving=false; }
        }
    }
}
</script>
<template>
    <!-- TEDARIK — polished, order shortcut, no jump -->
    <div v-if="isTedarik" class="tedarik-file-detail">
        <div class="back-row">
            <a href="javascript:;" class="back-orange" @click="goBack"><i class="ki-outline ki-left" style="font-size:14px; color:#FF5A1F;"></i> Belgeler</a>
            <a href="javascript:;" class="order-shortcut" @click="goOrder"><i class="ki-outline ki-document"></i> Sipariş Detayına Git →</a>
        </div>
        <div class="supplier-row" @click="goOrder" style="cursor:pointer;" title="Sipariş detayına git">
            <div>
                <div class="muted-label">Tedarikçi Bilgileri</div>
                <div class="supplier-name">{{ order?.ctitle || order?.spec_code || '—' }} <i class="ki-outline ki-arrow-right" style="font-size:12px; color:#9ca3af; margin-left:4px;"></i></div>
                <div style="font-size:11.5px; color:#9ca3af; margin-top:3px;">siparişin tüm belgeleri bu ekranda listelenir</div>
            </div>
            <div class="order-meta">
                <div class="meta-row"><span>Alım No :</span><b>{{ order?.buying_no || '—' }}</b></div>
                <div class="meta-row meta-click" @click.stop="goOrder"><span>Sipariş No :</span><b class="clickable">{{ order?.order_no || '—' }}</b></div>
                <div class="meta-row"><span>Tarih :</span><b>{{ fmtDate(order?.created_at) }}</b></div>
            </div>
        </div>

        <div v-if="loading" style="padding:28px; text-align:center; color:#64748b;"><i class="ki-outline ki-loading" style="animation:spin 1s linear infinite; display:inline-block;"></i> Yükleniyor...</div>
        <div v-else-if="error" style="padding:18px; color:#dc2626; text-align:center;">{{ error }}</div>
        <template v-else>
            <!-- order items -->
            <div class="detail-table-wrap">
                <div class="detail-thead"><span>Malzeme Adı</span><span>Birimi</span><span>Miktarı</span></div>
                <div v-for="it in items" :key="it.qnid" class="detail-row">
                    <span class="detail-cell-title" :title="(it.title||'') + ' ' + (it.prod_code||'')">{{ it.title || it.prod_code || '—' }}<small v-if="it.prod_code && it.title" style="color:#94a3b8; font-weight:400; margin-left:6px;">{{ it.prod_code }}</small></span>
                    <span class="detail-cell-unit">{{ it.unit || '—' }}</span>
                    <span class="detail-cell-qty">{{ it.quantity || '—' }}</span>
                </div>
                <div v-if="!items.length" style="padding:12px; color:#94a3b8; font-size:13px;">Kalem bulunamadı</div>
            </div>

            <!-- files list -->
            <div class="files-head"><span>Tip</span><span>Tarih</span><span>Durum</span><span></span></div>
            <div v-for="(f, idx) in files" :key="f.file_qnid" :class="['file-card', { active: f._sel, 'is-fail': statusCls(f)==='is-fail', 'is-old': isOldVersion(f) }]" @click="selectFile(f)">
                <div v-if="f._sel" class="active-tri"></div>
                <div class="file-main">
                    <div class="file-tip"><i :class="String(f.file_type||'').toLowerCase().includes('test') ? 'ki-outline ki-shield-tick' : String(f.file_type||'').toLowerCase().includes('cins') ? 'ki-outline ki-chart-simple' : 'ki-outline ki-document'" style="margin-right:6px; color:#94a3b8;"></i>{{ f.file_type || 'Belge' }}</div>
                    <div class="file-sub">
                        <span style="color:#ef4444; font-weight:700;">{{ personName(f) }}</span>
                        <span style="color:#6b7280;"> tarafından</span>
                    </div>
                    <div v-if="!f._sel" class="inspected-line-mobile">{{ fmtDate(f.last_status?.created_at || f.file_created_at) }} tarihinde incelendi..</div>
                </div>
                <div class="file-date">{{ fmtDate(f.last_status?.created_at || f.file_created_at) }}</div>
                <div class="file-status-col">
                    <template v-if="!f._sel">
                        <span :class="['status-pill', statusCls(f)]"><i :class="statusCls(f)==='is-success' ? 'ki-outline ki-check-circle' : statusCls(f)==='is-fail' ? 'ki-outline ki-cross-circle' : 'ki-outline ki-time'" style="margin-right:6px; font-size:14px;"></i>{{ statusLabel(f) }}</span>
                        <div v-if="statusCls(f)==='is-fail' && noteOf(f)" class="fail-note"><small>Açıklama :</small> {{ noteOf(f) }}</div>
                    </template>
                    <template v-else>
                        <div v-if="isOldVersion(f)" class="old-locked">
                            <span :class="['status-pill', statusCls(f)]" style="opacity:.92;"><i :class="statusCls(f)==='is-success' ? 'ki-outline ki-check-circle' : 'ki-outline ki-cross-circle'" style="margin-right:6px;"></i>{{ statusLabel(f) }}</span>
                            <div class="old-badge"><i class="ki-outline ki-lock-2" style="font-size:14px;"></i> Eski versiyon — değiştirilemez</div>
                            <div v-if="noteOf(f)" class="old-note"><small>Açıklama :</small> {{ noteOf(f) }}</div>
                            <div class="old-hint">Bu belge güncellendi — güncel sürümü listeden seçin.</div>
                        </div>
                        <div v-else-if="!isDecidable(f)" class="old-locked">
                            <span :class="['status-pill', statusCls(f)]"><i :class="statusCls(f)==='is-success' ? 'ki-outline ki-check-circle' : statusCls(f)==='is-fail' ? 'ki-outline ki-cross-circle' : 'ki-outline ki-time'" style="margin-right:6px;"></i>{{ statusLabel(f) }}</span>
                            <div v-if="noteOf(f)" class="old-note"><small>Açıklama :</small> {{ noteOf(f) }}</div>
                            <div class="old-badge" style="background:#f0fdf4; border-color:#bbf7d0; color:#065f46;"><i class="ki-outline ki-lock-2"></i> İşlem tamamlandı — değiştirilemez</div>
                        </div>
                        <template v-else>
                            <div class="decide-row">
                                <label :class="['decide-pill', { on: choice==='accept' }]"><input type="radio" value="accept" v-model="choice" name="decide"> Onayla</label>
                                <label :class="['decide-pill', { on: choice==='reject' }]"><input type="radio" value="reject" v-model="choice" name="decide"> Reddet</label>
                            </div>
                            <button class="save-red" :disabled="saving || !choice" @click.stop="saveStatus">{{ saving ? 'Kaydediliyor...' : 'Kaydet' }}</button>
                            <div v-if="choice==='reject'" class="reject-box">
                                <label>Red Açıklaması</label>
                                <textarea v-model="note" placeholder="Red nedeni yazın..." rows="3" @click.stop></textarea>
                            </div>
                        </template>
                    </template>
                </div>
                <div class="file-actions">
                    <button class="incele-btn" @click.stop="openFile(f)">İncele</button>
                </div>
                <div v-if="!f._sel" class="inspected-line">{{ fmtDate(f.last_status?.created_at || f.file_created_at) }} tarihinde incelendi..</div>
                <div v-else-if="isOldVersion(f)" class="inspected-line" style="border-top-color:#fee2e2; color:#9ca3af;">Bu eski bir sürüm — işlem yapılamaz.</div>
                <div v-else-if="!isDecidable(f)" class="inspected-line" style="border-top-color:#dcfce7; color:#065f46;">Bu belge zaten değerlendirildi.</div>
            </div>

            <div class="hint-bottom">
                Bu malzeme için <b>"Test Dokümanı"</b> beklenmektedir. Test Dokümanını <b>"İncele"</b> ile açtıktan sonra <b>Onayla</b> veya <b>Reddet</b> seçip <b>Kaydet</b> ile işlemi tamamlayın. Değerlendirilen belgeler kilitlenir.
            </div>
        </template>
    </div>

    <!-- ADMIN (same layout, coal card chrome but shortcut too) -->
    <div v-else class="admin-file-detail">
        <div class="card" style="border-radius:16px; overflow:hidden;">
            <div class="card-header" style="background:#f8fafc; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <a href="javascript:;" @click="goBack" style="color:#64748b; text-decoration:none;">← Belgeler</a>
                    <h3 style="margin:4px 0 0; font-weight:800;"><span @click="goOrder" style="cursor:pointer; text-decoration:underline; text-underline-offset:3px;">{{ order?.ctitle || 'Belge Detayı' }}</span></h3>
                    <small style="color:#64748b;">{{ order?.order_no }} • {{ order?.buying_no }} <a href="javascript:;" @click="goOrder" style="margin-left:6px; color:#3b82f6; text-decoration:none;">Sipariş →</a></small>
                </div>
                <div style="text-align:right; font-size:13px; color:#334155;">
                    <div>Alım No: <b>{{ order?.buying_no }}</b></div>
                    <div>Sipariş No: <b @click="goOrder" style="cursor:pointer; color:#1d4ed8; text-decoration:underline;">{{ order?.order_no }}</b></div>
                    <div>Tarih: <b>{{ fmtDate(order?.created_at) }}</b></div>
                </div>
            </div>
            <div class="card-body">
                <div v-if="loading" style="padding:20px; text-align:center;">Yükleniyor...</div>
                <div v-else-if="error" style="color:#dc2626;">{{ error }}</div>
                <template v-else>
                    <div class="detail-table-wrap admin">
                        <div class="detail-thead"><span>Malzeme Adı</span><span>Birimi</span><span>Miktarı</span></div>
                        <div v-for="it in items" :key="it.qnid" class="detail-row">
                            <span class="detail-cell-title">{{ it.title || it.prod_code }}</span>
                            <span>{{ it.unit }}</span><span>{{ it.quantity }}</span>
                        </div>
                    </div>
                    <div class="files-head"><span>Tip</span><span>Tarih</span><span>Durum</span><span></span></div>
                    <div v-for="f in files" :key="f.file_qnid" :class="['file-card', { active: f._sel, 'is-old': isOldVersion(f) }]" @click="selectFile(f)">
                        <div v-if="f._sel" class="active-tri"></div>
                        <div class="file-main">
                            <div class="file-tip"><i :class="String(f.file_type||'').toLowerCase().includes('test') ? 'ki-outline ki-shield-tick' : String(f.file_type||'').toLowerCase().includes('cins') ? 'ki-outline ki-chart-simple' : 'ki-outline ki-document'" style="margin-right:6px; color:#94a3b8;"></i>{{ f.file_type }}</div>
                            <div class="file-sub"><span style="color:#ef4444; font-weight:700;">{{ personName(f) }}</span> tarafından</div>
                        </div>
                        <div class="file-date">{{ fmtDate(f.last_status?.created_at || f.file_created_at) }}</div>
                        <div class="file-status-col">
                            <template v-if="!f._sel">
                                <span :class="['status-pill', statusCls(f)]"><i :class="statusCls(f)==='is-success' ? 'ki-outline ki-check-circle' : statusCls(f)==='is-fail' ? 'ki-outline ki-cross-circle' : 'ki-outline ki-time'" style="margin-right:6px; font-size:14px;"></i>{{ statusLabel(f) }}</span>
                                <div v-if="statusCls(f)==='is-fail' && noteOf(f)" class="fail-note"><small>Açıklama :</small> {{ noteOf(f) }}</div>
                            </template>
                            <template v-else>
                                <div v-if="isOldVersion(f)" class="old-locked">
                                    <span :class="['status-pill', statusCls(f)]" style="opacity:.92;"><i :class="statusCls(f)==='is-success' ? 'ki-outline ki-check-circle' : 'ki-outline ki-cross-circle'" style="margin-right:6px;"></i>{{ statusLabel(f) }}</span>
                                    <div class="old-badge"><i class="ki-outline ki-lock-2"></i> Eski versiyon — değiştirilemez</div>
                                    <div v-if="noteOf(f)" class="old-note"><small>Açıklama :</small> {{ noteOf(f) }}</div>
                                </div>
                                <template v-else-if="isDecidable(f)">
                                    <div class="decide-row">
                                        <label :class="['decide-pill', { on: choice==='accept' }]"><input type="radio" value="accept" v-model="choice"> Onayla</label>
                                        <label :class="['decide-pill', { on: choice==='reject' }]"><input type="radio" value="reject" v-model="choice"> Reddet</label>
                                    </div>
                                    <button class="save-red" :disabled="!choice" @click.stop="saveStatus">Kaydet</button>
                                    <div v-if="choice==='reject'" class="reject-box"><label>Red Açıklaması</label><textarea v-model="note" placeholder="Red nedeni yazın..." rows="3" @click.stop></textarea></div>
                                </template>
                                <div v-else-if="isOldVersion(f)" class="old-locked">
                                    <span :class="['status-pill', statusCls(f)]"><i :class="statusCls(f)==='is-success' ? 'ki-outline ki-check-circle' : 'ki-outline ki-cross-circle'" style="margin-right:6px;"></i>{{ statusLabel(f) }}</span>
                                    <div class="old-badge"><i class="ki-outline ki-lock-2"></i> Eski versiyon — değiştirilemez</div>
                                </div>
                                <template v-else>
                                    <span :class="['status-pill', statusCls(f)]"><i :class="statusCls(f)==='is-success' ? 'ki-outline ki-check-circle' : 'ki-outline ki-cross-circle'" style="margin-right:6px;"></i>{{ statusLabel(f) }}</span>
                                    <div v-if="noteOf(f)" class="old-note"><small>Açıklama :</small> {{ noteOf(f) }}</div>
                                    <div class="old-badge" style="background:#f0fdf4; border-color:#bbf7d0; color:#065f46;"><i class="ki-outline ki-lock-2"></i> İşlem tamamlandı — değiştirilemez</div>
                                </template>
                            </template>
                        </div>
                        <div class="file-actions"><button class="incele-btn" @click.stop="openFile(f)">İncele</button></div>
                        <div v-if="!f._sel" class="inspected-line">{{ fmtDate(f.last_status?.created_at || f.file_created_at) }} tarihinde incelendi..</div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
<style scoped>
.tedarik-file-detail{ background:#fff; border-radius:12px; padding:16px 18px; border:1px solid #e8e8ea; box-shadow:0 4px 20px rgba(15,23,42,.05); }
.back-row{ display:flex; justify-content:space-between; align-items:center; gap:12px; }
.back-orange{ font-size:12.5px; font-weight:700; color:#6b7280; text-decoration:none; display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid #e5e7eb; background:#fff; }
.back-orange:hover{ background:#f8fafc; }
.order-shortcut{ font-size:12px; font-weight:700; color:#FF5A1F; text-decoration:none; display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; background:#fff7ed; border:1px solid #fed7aa; }
.order-shortcut:hover{ background:#ffedd5; }
.supplier-row{ display:flex; justify-content:space-between; align-items:flex-start; margin-top:12px; gap:16px; background: linear-gradient(135deg, #fff 0%, #fff7ed 100%); border:1px solid #fed7aa; border-radius:12px; padding:12px 14px; }
.supplier-row:hover{ border-color:#fdba74; }
.muted-label{ font-size:11px; color:#9ca3af; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
.supplier-name{ margin-top:4px; font-size:15px; font-weight:800; color:#0f172a; display:inline-flex; align-items:center; }
.order-meta{ text-align:right; font-size:12.5px; color:#475569; line-height:1.9; }
.meta-row{ display:flex; gap:8px; justify-content:flex-end; align-items:center; }
.meta-row span{ color:#94a3b8; min-width:70px; }
.meta-row.meta-click .clickable{ color:#c2410c; background:#fff7ed; padding:2px 8px; border-radius:999px; border:1px solid #fed7aa; cursor:pointer; }
.meta-row.meta-click .clickable:hover{ background:#ffedd5; }

.detail-table-wrap{ margin-top:18px; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04); }
.detail-thead{ display:grid; grid-template-columns:1fr 110px 90px; padding:10px 14px; font-size:11px; color:#64748b; font-weight:800; letter-spacing:.04em; text-transform:uppercase; background:linear-gradient(135deg,#f8fafc,#f1f5f9); border-bottom:1px solid #e5e7eb; }
.detail-row{ display:grid; grid-template-columns:1fr 110px 90px; padding:14px; border-bottom:1px solid #f1f5f9; background:#fff; align-items:center; transition:background .12s; }
.detail-row:hover{ background:#fffbeb; }
.detail-row:last-child{ border-bottom:none; }
.detail-cell-title{ font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.detail-cell-unit{ font-weight:600; color:#475569; text-align:center; }
.detail-cell-qty{ font-weight:800; color:#0f172a; text-align:center; background:#f8fafc; padding:4px 8px; border-radius:8px; border:1px solid #f1f5f9; display:inline-flex; justify-content:center; }
.files-head{ display:grid; grid-template-columns:1fr 130px 300px 90px; padding:12px 14px 8px; font-size:11px; color:#64748b; font-weight:800; letter-spacing:.04em; text-transform:uppercase; margin-top:22px; }
.files-head span:nth-child(1){ text-align:left; }
.files-head span:nth-child(2){ text-align:center; }
.files-head span:nth-child(3){ text-align:center; }
.file-card{
    position:relative; display:grid; grid-template-columns:1fr 130px 300px 90px; gap:12px;
    padding:14px 14px 12px; border:1px solid #e5e7eb; border-radius:14px; background:#fff; margin-bottom:10px;
    align-items:start; cursor:pointer; transition: all .14s;
}
.file-card:hover{ border-color:#cbd5e1; transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.06); }
.file-card.active{ border-color:#FF5A1F; background: linear-gradient(135deg, #fff 0%, #fff7ed 100%); box-shadow:0 8px 20px rgba(255,90,31,.14); }
.active-tri{
    position:absolute; left:-1px; top:18px;
    width:0; height:0; border-top:8px solid transparent; border-bottom:8px solid transparent; border-left:8px solid #FF5A1F;
}
.file-tip{ font-weight:800; color:#0f172a; font-size:13.5px; margin-bottom:8px; display:flex; align-items:center; }
.file-sub{ font-size:11.5px; color:#6b7280; }
.inspected-line-mobile{ display:none; }
.file-date{ font-size:13px; font-weight:700; color:#1e293b; text-align:center; background:#f8fafc; border-radius:8px; padding:6px 8px; height:fit-content; justify-self:center; min-width:110px; }
.file-status-col{ text-align:center; display:flex; flex-direction:column; align-items:stretch; gap:8px; width:100%; max-width:300px; box-sizing:border-box; justify-self:center; }
.status-pill{ display:inline-flex; align-items:center; justify-content:center; min-width:156px; height:36px; padding:0 14px; border-radius:999px; font-size:12.5px; font-weight:800; color:#fff; box-shadow:0 1px 2px rgba(15,23,42,.08); }
.status-pill.is-success{ background:linear-gradient(135deg,#00a651,#059669); }
.status-pill.is-fail{ background:linear-gradient(135deg,#e30613,#dc2626); }
.status-pill.is-waiting{ background:linear-gradient(135deg,#94a3b8,#64748b); }
.status-pill.is-refresh{ background:linear-gradient(135deg,#f59e0b,#d97706); }
.fail-note{ font-size:11.5px; color:#334155; background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; padding:6px 10px; width:100%; text-align:left; }
.decide-row{ display:flex; gap:8px; justify-content:stretch; align-items:center; flex:1; }
.decide-pill{ flex:1 1 0; display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:9px 10px; border-radius:999px; border:1.5px solid #e2e8f0; background:#fff; font-size:13px; font-weight:700; cursor:pointer; min-width:0; }
.decide-pill.on{ border-color:#FF5A1F; background:#fff7ed; color:#9a3412; }
.decide-pill input{ accent-color:#FF5A1F; flex-shrink:0; }
.reject-box{ width:100%; background:#fff; border:1px solid #fed7aa; border-radius:12px; padding:10px; box-sizing:border-box; }
.reject-box label{ font-size:11px; font-weight:700; color:#9a3412; display:block; text-align:center; }
.reject-box textarea{ width:100%; min-height:84px; margin-top:8px; border:1.5px solid #e2e8f0; border-radius:10px; padding:10px 12px; font-size:13px; outline:none; resize:vertical; background:#f8fafc; box-sizing:border-box; }
.reject-box textarea:focus{ border-color:#fdba74; background:#fff; }
.incele-btn{ background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none; border-radius:999px; padding:8px 16px; font-size:13px; font-weight:800; cursor:pointer; box-shadow:0 2px 8px rgba(217,119,6,.22); }
.incele-btn:hover{ transform:translateY(-1px); box-shadow:0 6px 14px rgba(217,119,6,.28); }
.save-red{ background:linear-gradient(135deg,#e30613,#dc2626); color:#fff; border:1px solid transparent; border-radius:999px; padding:11px 16px; font-size:13px; font-weight:800; cursor:pointer; width:100%; box-sizing:border-box; display:block; box-shadow:0 2px 8px rgba(220,38,38,.22); }
.save-red:disabled{ opacity:.45; cursor:not-allowed; transform:none; box-shadow:none; }
.save-red:hover:not(:disabled){ transform:translateY(-1px); box-shadow:0 6px 14px rgba(220,38,38,.28); }
.inspected-line{ grid-column:1 / -1; font-size:11.5px; color:#6b7280; margin-top:2px; border-top:1px dashed #f1f5f9; padding-top:8px; }
.hint-bottom{ margin-top:14px; font-size:12.5px; color:#6b7280; line-height:1.7; border-top:1px solid #f1f5f9; padding-top:12px; background:#f8fafc; border-radius:10px; padding:12px 14px; }
.decide-top{ display:flex; align-items:center; gap:8px; width:100%; box-sizing:border-box; }
.decide-top .decide-row{ flex:1; justify-content:stretch; min-width:0; }
.incele-inline{ flex-shrink:0; height:38px; padding:0 14px; font-size:12.5px; min-width:72px; justify-content:center; box-sizing:border-box; }
.file-actions.hidden{ visibility:hidden; pointer-events:none; }
.file-status-col{ min-width:0; }

/* old version locked — same widths as active */
.old-locked{ display:flex; flex-direction:column; align-items:stretch; gap:8px; width:100%; }
.old-locked .status-pill{ width:100%; min-width:0; }
.old-badge{
    display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 10px; border-radius:999px;
    background:#fff; border:1px solid #e2e8f0; color:#475569; font-size:11.5px; font-weight:700;
    width:100%; box-sizing:border-box;
}
.old-note{ font-size:11.5px; color:#334155; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; width:100%; text-align:left; box-sizing:border-box; }
.old-hint{ font-size:11px; color:#94a3b8; text-align:center; }
.file-card.active.is-old{ opacity:.92; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important; border-color:#cbd5e1 !important; }
.file-card.active.is-old .file-tip{ color:#475569; }

/* admin tweaks */
.admin-file-detail .file-card{ background:#fff; }
@media (max-width: 768px){
    .files-head{ display:none; }
    .file-card{ grid-template-columns:1fr; }
    .file-date{ text-align:left; }
    .file-status-col{ text-align:left; }
    .detail-thead, .detail-row{ grid-template-columns:1fr 60px 60px; }
}
@keyframes spin{ from{transform:rotate(0)} to{transform:rotate(360deg)} }
</style>
