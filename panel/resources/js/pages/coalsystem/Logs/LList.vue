
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
            list: [ { title: 'Sistem logları', path: '/coalpanel/sistem-loglari' } ],
            title: 'Sistem logları'
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
                this.plib.openTab('POST', '/api/v1/export/userlogs', this.table.currentFilter,'_blank');
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
                        key   : 'title',
                        order : true,
                        width : '250px',
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Belge Tipi',
                        key   : 'form_type',
                        order : true,
                        type  : 'string',
                    },{
                        title : 'İsim',
                        key   : 'name',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Kullanıcı',
                        key   : 'email',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Rol',
                        key   : 'role',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'IP',
                        key   : 'ip',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Tarih',
                        key   : 'created_at',
                        order : true,
                        type  : 'string', // if column is string then make type string
                    },{
                        title : 'Açıklama',
                        key   : '#',
                        colAlign : 'center',
                        headAlign : 'center',
                        order : false,
                        search : false,
                        type  : 'string', // if column is string then make type string
                        columnClick : (el,rowData,columnData) => {
                            const desc = JSON.parse(rowData.description || '{}');
                            const actor = desc.actor || null;
                            const doc = desc.document || null;
                            // file can be desc.file (new) or legacy file_id
                            const file = desc.file || (desc.file_id ? {id:desc.file_id, qnid:desc.file_qnid || null, field: null, group_key:null, order_no: (doc && doc.order_no) || null} : null);
                            const from = desc.from || null;
                            const to = desc.to || null;
                            const noteVal = (desc.note && String(desc.note).trim() !== '' && String(desc.note).trim() !== '-') ? String(desc.note) : null;
                            const hasSide = !!(actor || doc || file || from || to || noteVal);
                            // build a fancy, collapsible HTML view with controls
                            const style = `
                                <style>
                                    .swal2-popup{width:1500px !important; max-width:95vw !important}
                                    .json-popup{font-family:Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial}
                                    .json-controls{display:flex;gap:8px;align-items:center;margin-bottom:8px; flex-wrap:wrap}
                                    .json-controls button{background:#0b5fff;color:#fff;border:0;padding:6px 10px;border-radius:6px;cursor:pointer;font-weight:600; font-size:12px}
                                    .json-controls button.secondary{background:#6c757d}
                                    .json-controls button.ghost{background:transparent;color:#0b5fff;border:1px solid #cfe0ff}
                                    .log-modal-grid{display:flex; gap:16px; align-items:stretch; text-align:left}
                                    .log-modal-main{flex:1; min-width:0; display:flex; flex-direction:column}
                                    .log-modal-side{width:340px; flex-shrink:0; display:flex; flex-direction:column; gap:12px; max-height:60vh; overflow:auto; padding-right:2px}
                                    .json-container{max-height:60vh;overflow:auto;text-align:left;font-family:SFMono-Regular,Menlo,monospace;font-size:13px;border-radius:8px;padding:12px;background:linear-gradient(180deg,#fbfdff,#f7fbff);border:1px solid #e6f0ff; flex:1}
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
                                    .side-card{background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; box-shadow:0 1px 2px rgba(15,23,42,0.04)}
                                    .side-card-title{font-size:10.5px; font-weight:800; letter-spacing:0.07em; text-transform:uppercase; color:#94a3b8; margin-bottom:8px; display:flex; align-items:center; gap:6px}
                                    .side-card-title i{font-size:13px; color:#94a3b8}
                                    .side-row{display:flex; justify-content:space-between; gap:8px; padding:5px 0; border-bottom:1px dashed #f1f5f9; font-size:12.5px}
                                    .side-row:last-child{border-bottom:none}
                                    .side-label{color:#64748b; font-weight:600; flex-shrink:0}
                                    .side-value{color:#0f172a; font-weight:600; text-align:right; word-break:break-all}
                                    .side-pill{display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; line-height:1.4}
                                    .pill-role{background:#eef2ff; color:#4338ca; border:1px solid #c7d2fe}
                                    .pill-sys{background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0}
                                    .pill-ip{background:#f8fafc; color:#475569; border:1px solid #e2e8f0; font-family:monospace; font-size:11px}
                                    .actor-head{display:flex; gap:10px; align-items:center; margin-bottom:10px}
                                    .actor-avatar{width:38px; height:38px; border-radius:999px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; color:#fff; background:linear-gradient(135deg,#0b5fff,#7c3aed); flex-shrink:0}
                                    .actor-name{font-weight:800; color:#0f172a; font-size:13.5px; line-height:1.2}
                                    .actor-email{font-size:11.5px; color:#64748b; word-break:break-all}
                                    .order-no-big{font-family:monospace; font-size:15px; font-weight:800; color:#0f172a; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 10px; text-align:center; letter-spacing:0.02em}
                                    .from-to{display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:center; padding:6px 0}
                                    .status-pill{padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; border:1px solid #e2e8f0; background:#f8fafc; color:#334155; max-width:100%; word-break:break-word; text-align:center}
                                    .status-pill.to{background:#eff6ff; border-color:#bfdbfe; color:#1e40af}
                                    .status-pill.from{background:#fff7ed; border-color:#fed7aa; color:#9a3412}
                                    .arrow{color:#94a3b8; font-weight:800}
                                    .note-box{background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px 11px; font-size:12.5px; color:#92400e; line-height:1.5; white-space:pre-wrap; word-break:break-word}
                                    @media(max-width:900px){ .log-modal-grid{flex-direction:column} .log-modal-side{width:100%; max-height:none} .json-container{max-height:42vh} }
                                </style>`;

                            function initials(name){
                                if(!name) return '?';
                                const parts = String(name).trim().split(/\s+/).filter(Boolean);
                                if(parts.length===1) return parts[0].slice(0,2).toUpperCase();
                                return (parts[0][0]+parts[parts.length-1][0]).toUpperCase();
                            }
                            function badge(text, cls){ if(!text) return ''; return `<span class="side-pill ${cls}">${escapeHtml(text)}</span>`; }

                            let sideHtml = '';
                            if(hasSide){
                                // actor card
                                if(actor){
                                    const av = initials(actor.name || actor.email || '?');
                                    const rolePill = actor.role ? badge(actor.role,'pill-role') : '';
                                    const sysPill = actor.sys_code ? badge(actor.sys_code,'pill-sys') : '';
                                    const ipPill = actor.ip ? badge(actor.ip,'pill-ip') : '';
                                    const typePill = actor.type_key ? `<span class="side-pill" style="background:#fdf2f8; color:#be185d; border:1px solid #fecdd3">${escapeHtml(actor.type_key)}</span>` : '';
                                    sideHtml += `<div class="side-card">
                                        <div class="side-card-title"><i class="ki-duotone ki-user fs-6"><span class="path1"></span><span class="path2"></span></i> İşlemi Yapan</div>
                                        <div class="actor-head">
                                            <div class="actor-avatar">${escapeHtml(av)}</div>
                                            <div style="min-width:0">
                                                <div class="actor-name">${escapeHtml(actor.name || '-')}</div>
                                                <div class="actor-email">${escapeHtml(actor.email || '')}</div>
                                            </div>
                                        </div>
                                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px">${rolePill}${typePill}${sysPill}${ipPill}</div>
                                        <div class="side-row"><span class="side-label">Kullanıcı ID</span><span class="side-value">${escapeHtml(String(actor.user_id ?? '-'))}</span></div>
                                        <div class="side-row"><span class="side-label">Person QNID</span><span class="side-value" style="font-family:monospace; font-size:11px">${escapeHtml(String(actor.person_qnid || '-')).slice(0,22)}${(actor.person_qnid||'').length>22?'…':''}</span></div>
                                    </div>`;
                                }
                                // order card
                                if(doc && (doc.order_no || doc.transfer_no || doc.buying_no || doc.spec_code || doc.ctitle)){
                                    const bigNo = doc.order_no || doc.transfer_no || '-';
                                    sideHtml += `<div class="side-card">
                                        <div class="side-card-title"><i class="ki-duotone ki-package fs-6"><span class="path1"></span><span class="path2"></span></i> Sipariş</div>
                                        <div class="order-no-big">${escapeHtml(bigNo)}</div>
                                        <div style="margin-top:10px">
                                            ${doc.transfer_no && doc.transfer_no!==doc.order_no ? `<div class="side-row"><span class="side-label">Transfer No</span><span class="side-value" style="font-family:monospace">${escapeHtml(doc.transfer_no)}</span></div>` : ''}
                                            ${doc.buying_no ? `<div class="side-row"><span class="side-label">Alım No</span><span class="side-value">${escapeHtml(doc.buying_no)}</span></div>` : ''}
                                            ${doc.spec_code ? `<div class="side-row"><span class="side-label">Cari Kodu</span><span class="side-value" style="font-family:monospace">${escapeHtml(doc.spec_code)}</span></div>` : ''}
                                            ${doc.ctitle ? `<div class="side-row"><span class="side-label">Firma</span><span class="side-value">${escapeHtml(doc.ctitle)}</span></div>` : ''}
                                            ${doc.qnid ? `<div class="side-row"><span class="side-label">QNID</span><span class="side-value" style="font-family:monospace; font-size:11px">${escapeHtml(String(doc.qnid)).slice(0,22)}…</span></div>` : ''}
                                        </div>
                                    </div>`;
                                }
                                // file card
                                if(file && (file.id || file.field || file.order_no)){
                                    const fieldLabel = file.field ? escapeHtml(file.field) : (file.id ? 'Dosya #'+escapeHtml(String(file.id)) : 'Dosya');
                                    const groupLabel = file.group_key ? escapeHtml(file.group_key) : '';
                                    sideHtml += `<div class="side-card">
                                        <div class="side-card-title"><i class="ki-duotone ki-document fs-6"><span class="path1"></span><span class="path2"></span></i> Dosya</div>
                                        <div class="side-row"><span class="side-label">Alan</span><span class="side-value">${fieldLabel}${groupLabel ? ' <span style="color:#94a3b8">('+groupLabel+')</span>' : ''}</span></div>
                                        ${file.qnid ? `<div class="side-row"><span class="side-label">QNID</span><span class="side-value" style="font-family:monospace; font-size:11px">${escapeHtml(String(file.qnid)).slice(0,22)}…</span></div>` : ''}
                                        ${file.order_no ? `<div class="side-row"><span class="side-label">Sipariş</span><span class="side-value" style="font-family:monospace">${escapeHtml(file.order_no)}</span></div>` : ''}
                                        ${file.entity_tag ? `<div class="side-row"><span class="side-label">Tag</span><span class="side-value" style="font-family:monospace; font-size:11px">${escapeHtml(file.entity_tag)}</span></div>` : ''}
                                    </div>`;
                                }
                                // transition
                                if(from || to){
                                    const fromLabel = from ? (from.title || from.op_key || '') : '';
                                    const toLabel = to ? (to.title || to.op_key || '') : '';
                                    sideHtml += `<div class="side-card">
                                        <div class="side-card-title"><i class="ki-duotone ki-arrow-right-left fs-6"><span class="path1"></span><span class="path2"></span></i> Durum Geçişi</div>
                                        <div class="from-to">
                                            ${from ? `<span class="status-pill from">${escapeHtml(fromLabel)}</span>` : '<span class="status-pill from" style="opacity:0.5">—</span>'}
                                            <span class="arrow">→</span>
                                            ${to ? `<span class="status-pill to">${escapeHtml(toLabel)}</span>` : '<span class="status-pill to" style="opacity:0.5">—</span>'}
                                        </div>
                                        ${from && from.op_key ? `<div style="text-align:center; margin-top:4px; font-family:monospace; font-size:10px; color:#94a3b8">${escapeHtml(from.op_key)}</div>` : ''}
                                        ${to && to.op_key ? `<div style="text-align:center; font-family:monospace; font-size:10px; color:#94a3b8">${escapeHtml(to.op_key)}</div>` : ''}
                                    </div>`;
                                }
                                // note
                                if(noteVal){
                                    sideHtml += `<div class="side-card">
                                        <div class="side-card-title"><i class="ki-duotone ki-notepad fs-6"><span class="path1"></span><span class="path2"></span></i> Not</div>
                                        <div class="note-box">${escapeHtml(noteVal)}</div>
                                    </div>`;
                                }
                                sideHtml = `<div class="log-modal-side">${sideHtml}</div>`;
                            }

                            const controls = `
                                <div class="json-controls">
                                    <button id="jsonExpandAll">Logu Genişlet</button>
                                    <button id="jsonCollapseAll" class="secondary">Tümünü Daralt</button>
                                    <button id="jsonCopy" class="ghost">JSON'i Kopyala</button>
                                    <div style="flex:1"></div>
                                </div>`;

                            const mainHtml = `<div class="log-modal-main">` + controls + `<div class="json-container">` + jsonToDetails(desc) + `</div></div>`;
                            const html = `<div class="json-popup">` + style + `<div class="log-modal-grid">` + mainHtml + sideHtml + `</div></div>`;

                            Swal.fire({
                                title: rowData.title,
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
                                    }catch(e){
                                        // ignore
                                    }
                                }
                            });
                        },
                        columnFormatter : (el,rowData,columnData) => {
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
                        url:'/api/v1/table/userlog',
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
                    <input type="text" id="mainSearch" class="rlist-search-input" placeholder="Log ara...">
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
