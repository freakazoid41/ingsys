
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';
    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { reset, wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    import dayjs from 'dayjs';

    export default {
        breadcrumbs: {
            list: [ { title: 'Bildirim Kayıtları', path: '/coalpanel/notifikasyon-loglari' } ],
            title: 'Bildirim Kayıtları'
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                useAuthStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            this.buildTestTable();
            
            


            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                plib : new Plib(),
                navigationStore : useNavigationStore(),
                useAuthStore    : useAuthStore(),
            }
        },
        methods: {
            parseRowStatus(lastStatus){
                try {
                    return JSON.parse(lastStatus || '{}');
                } catch (e) {
                    return {};
                }
            },
            
            searchTable(){
                this.table.setFilter(
                    [{
                        key   : 'all', // column key
                        type  : '=', // filtering type ('like','<','>')
                        value : document.getElementById('mainSearch').value.trim()//wanted column value
                    }]
                );
            },
            resetSearch(){
                document.getElementById('mainSearch').value = '';
                this.table.setFilter([]);
            },
            exportTable(){
                this.plib.openTab('POST', '/api/v1/export/notificationlogs', this.table.currentFilter,'_blank');
            },
            buildTestTable(){
                // helper: escape HTML and render JSON as nested <details>
                function escapeHtml(unsafe){
                    if (unsafe === null) return 'null';
                    return String(unsafe)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/\"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                // render JSON as nested details with type-aware styling
                function jsonToDetails(obj, depth = 0){
                    if (obj === null) return '<span class="json-null">null</span>';
                    const t = typeof obj;
                    if (t === 'string') return `<span class="json-primitive json-string">"${escapeHtml(obj)}"</span>`;
                    if (t === 'number' || t === 'bigint') return `<span class="json-primitive json-number">${escapeHtml(obj)}</span>`;
                    if (t === 'boolean') return `<span class="json-primitive json-boolean">${escapeHtml(obj)}</span>`;
                    if (t === 'undefined') return '<span class="json-null">undefined</span>';

                    if (Array.isArray(obj)){
                        const length = obj.length;
                        let html = `<details class="json-node" ${depth===0? 'open': ''}><summary><span class="badge">Array[${length}]</span></summary>`;
                        obj.forEach((item, idx) => {
                            html += `<div class="json-row"><span class="json-key">[${idx}]</span>: ${jsonToDetails(item, depth+1)}</div>`;
                        });
                        html += `</details>`;
                        return html;
                    }

                    // object
                    const keys = Object.keys(obj || {});
                    let html = `<details class="json-node" ${depth===0? 'open': ''}><summary><span class="badge">Object {${keys.length}}</span></summary>`;
                    keys.forEach(key => {
                        html += `<div class="json-row"><span class="json-key">${escapeHtml(key)}</span>: ${jsonToDetails(obj[key], depth+1)}</div>`;
                    });
                    html += `</details>`;
                    return html;
                }
                //set headers
                const headers = [
                    {
                        title : 'Tip',
                        key   : 'type',
                        order : true,
                        width : '250px',
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Hedef',
                        key   : 'to',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Konu',
                        key   : 'subject',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Durum',
                        key   : '#',
                        order : true,
                        colAlign : 'center',
                        headAlign : 'center',
                        width : '150px',
                        type  : 'string', // if column is string then make type string
                        columnFormatter:(elm,data,columnData)=>{
                            return data.status == 'sent' ? '<i style="color:green !important" class="ki-outline ki-check fs-2x text-success"></i>' : '<i style="color:red !important" class="ki-outline ki-cross-circle fs-2x text-danger"></i>';
                        }
                    },{
                        title : 'Tarih',
                        key   : 'last_attempt_at',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Detay',
                        key   : 'detail',
                        order : false,
                        colAlign : 'center',
                        headAlign : 'center',
                        type  : 'string', // if column is string then make type string
                        columnClick:(elm,rowData,columnData)=>{
                            const desc = {...JSON.parse(columnData || '{}'),...JSON.parse(rowData.payload || '{}')};
                            // build a fancy, collapsible HTML view with controls
                            const style = `
                                <style>
                                    .swal2-popup{width:1500px !important}
                                    .json-popup{font-family:Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial}
                                    .json-controls{display:flex;gap:8px;align-items:center;margin-bottom:8px}
                                    .json-controls button{background:#0b5fff;color:#fff;border:0;padding:6px 10px;border-radius:6px;cursor:pointer;font-weight:600}
                                    .json-controls button.secondary{background:#6c757d}
                                    .json-controls button.ghost{background:transparent;color:#0b5fff;border:1px solid #cfe0ff}
                                    .json-container{max-height:60vh;overflow:auto;text-align:left;font-family:SFMono-Regular,Menlo,monospace;font-size:13px;border-radius:8px;padding:12px;background:linear-gradient(180deg,#fbfdff,#f7fbff);border:1px solid #e6f0ff}
                                    .json-row{margin:4px 0;padding-left:6px}
                                    .json-key{font-weight:700;color:#0b2e6b;margin-right:6px}
                                    .json-primitive{color:#0b5fff}
                                    .json-string{color:#0b7a3a}
                                    .json-number{color:#b76b00}
                                    .json-boolean{color:#a855f7}
                                    .json-null{color:#6c757d;font-style:italic}
                                    .badge{background:#eef6ff;color:#0b5fff;padding:2px 8px;border-radius:999px;font-weight:700}
                                    details.json-node{margin:6px 0;padding-left:8px}
                                    details.json-node>summary{cursor:pointer;outline:none}
                                </style>`;

                            const controls = `
                                <div class="json-controls">
                                    <button id="jsonExpandAll">Logu Genişlet</button>
                                    <button id="jsonCollapseAll" class="secondary">Tümünü Daralt</button>
                                    <button id="jsonCopy" class="ghost">JSON'i Kopyala</button>
                                    <button id="jsonRetrigger" class="secondary">Yeniden Gönder</button>
                                    <div style="flex:1"></div>
                                </div>`;

                            const html = `<div class="json-popup">` + style + controls + `<div class="json-container">` + jsonToDetails(desc) + `</div></div>`;

                            Swal.fire({
                                title: rowData.title || 'Bildirim Detayı',
                                html: html,
                                showCloseButton: true,
                                showCancelButton: false,
                                showConfirmButton:false,
                                focusConfirm: false,
                                didOpen: (popup) => {
                                    try{
                                        const root = popup || document;
                                        const details = root.querySelectorAll('.json-node');
                                        const expandBtn = root.querySelector('#jsonExpandAll');
                                        const collapseBtn = root.querySelector('#jsonCollapseAll');
                                        const copyBtn = root.querySelector('#jsonCopy');
                                        const retriggerBtn = root.querySelector('#jsonRetrigger');

                                        expandBtn && expandBtn.addEventListener('click', () => details.forEach(d => d.open = true));
                                        collapseBtn && collapseBtn.addEventListener('click', () => details.forEach(d => d.open = false));

                                        copyBtn && copyBtn.addEventListener('click', async () => {
                                            const txt = JSON.stringify(desc, null, 2);
                                            try{
                                                await navigator.clipboard.writeText(txt);
                                                Swal.fire({toast:true,position:'top-end',title:'Copied',showConfirmButton:false,timer:1200});
                                            }catch(e){
                                                const ta = document.createElement('textarea');
                                                ta.value = txt;document.body.appendChild(ta);ta.select();document.execCommand('copy');ta.remove();
                                                Swal.fire({toast:true,position:'top-end',title:'Copied',showConfirmButton:false,timer:1200});
                                            }
                                        });

                                        retriggerBtn && retriggerBtn.addEventListener('click', async () => {
                                            try {
                                                retriggerBtn.disabled = true;
                                                retriggerBtn.textContent = 'Bekleniyor...';

                                                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                                                const headers = {
                                                    'Accept': 'application/json',
                                                };
                                                if (tokenMeta) headers['X-CSRF-TOKEN'] = tokenMeta.content;

                                                const requestId = Number.isInteger(rowData.row_id) || typeof rowData.row_id === 'string' ? rowData.row_id : rowData.id;
                                                const response = await fetch(`/api/v1/notificationlog/${requestId}/retrigger`, {
                                                    method: 'POST',
                                                    credentials: 'include',
                                                    headers,
                                                });

                                                const result = await response.json();
                                                if (response.ok && result.success) {
                                                    Swal.fire({
                                                        icon: 'success',
                                                        title: 'Başarılı',
                                                        text: 'Bildirim yeniden tetiklendi.',
                                                        timer: 1500,
                                                        showConfirmButton: false,
                                                    });
                                                    if (this.table && typeof this.table.getData === 'function') {
                                                        this.table.getData();
                                                    }
                                                } else {
                                                    Swal.fire({
                                                        icon: 'error',
                                                        title: 'Hata',
                                                        text: result.message || 'Tekrar gönderim başarısız oldu.',
                                                    });
                                                }
                                            } catch (err) {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'Hata',
                                                    text: err.message || 'Sunucuya bağlanırken bir hata oluştu.',
                                                });
                                            } finally {
                                                retriggerBtn.disabled = false;
                                                retriggerBtn.textContent = 'Yeniden Gönder';
                                            }
                                        });
                                    }catch(e){
                                        // ignore
                                    }
                                }
                            });
                        },
                        columnFormatter:(elm,data,columnData)=>{
                            return `<i class="ki-duotone ki-magnifier fs-3 ms-2">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                    </i>`;  
                        }

                    }
                    
                ];
                
                //initiate table
                this.table = new PickleTable({
                    container : '#div_table', //table target div
                    headers   : headers,
                    pageLimit : 10, // -1 for closing pagination
                    height    : '70vh',
                    type      : 'ajax',
                    columnSearch : true, // true - false for opening and closig
                    paginationType : 'number',// scroll - number (number for default)
                    ajax:{
                        url:'/api/v1/table/notificationlog',
                        data:{
                            //order:{},
                        }
                    },
                    initialFilter : [],
                    nextPageIcon : '<i class="ki-outline ki-arrow-right "></i>',
                    prevPageIcon : '<i class="ki-outline ki-arrow-left"></i>',
                    rowFormatter:(elm,data)=>{
                        //console.log(elm,data);
                        //modify row element
                        //elm.style.backgroundColor = 'yellow';
                        //modify data
                        /*JSON.parse(data.relation_detail).forEach(element => {
                            data[element['Key']] = element['Value'];
                            //if(data['cont_name'] == undefined) data['cont_name'] = []
                            //if(element['Key'].includes('cont_name')) data['cont_name'].push(element['Value']);
                        });*/
                        //data['cont_name'] = (data['cont_name'] ?? []).join(' , ');
                        //data.status = JSON.parse(data.status).OpTitle;
                        return data;
                    },
                });
            },
        }
    }

</script>
<template>
    <div class="card rlist-card mt-10">
        <div class="card-header rlist-header">
            <div class="rlist-search-group">
                <div class="rlist-search-wrap">
                    <i class="ki-duotone ki-magnifier fs-4 rlist-search-icon">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Bildirim ara...">
                </div>
                <button type="button" class="rlist-btn rlist-btn-primary" @click="searchTable">
                    <i class="ki-outline ki-magnifier fs-5"></i> Ara
                </button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="resetSearch">Sıfırla</button>
                <button type="button" class="rlist-btn rlist-btn-ghost" @click="exportTable">
                    <i class="ki-outline ki-exit-down fs-5"></i> Excel
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="div_table"></div>
        </div>
    </div>
</template>
