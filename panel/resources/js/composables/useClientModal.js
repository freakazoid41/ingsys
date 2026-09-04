// composables/useClientModal.js — shared Şirket modal for OList/DList and future listings
// Not breaking: keep OList/DList current code as-is; new pages can import this. Existing pages can migrate later with `setup()` usage.
// Usage (Options API):
//   import { useClientModal } from '@/composables/useClientModal';
//   data() { return { ...useClientModalData(), ... } }  or  setup() { return useClientModal(plib) }
// This file provides both Options-API helper and Composition helper.

import { ref, computed } from 'vue';
import Plib from '@/lib/pickle';

// Options-API helper: returns data + methods to spread into component
export function useClientModalData() {
  return {
    sirketSearch: '',
    selectedSirkets: [],
    showClientModal: false,
    clientModalMode: 'multi', // multi = Filtreler Şirkete Göre, single = Detaylı Şirket/Tedarikçi Ara
    detailedModalTarget: 'sirket',
    modalClients: [],
    dropdownPos: { top: 0, left: 0, width: 280 },
    clientOptions: [], // kept for compat, not used in template
    loadingClients: false,
    _clientsFetchedAt: 0,
  };
}

// Composition helper — for new pages (Vue 3 setup)
export function useClientModal(plibInstance = null) {
  const plib = plibInstance || new Plib();
  const sirketSearch = ref('');
  const selectedSirkets = ref([]);
  const showClientModal = ref(false);
  const clientModalMode = ref('multi');
  const detailedModalTarget = ref('sirket');
  const modalClients = ref([]);
  const loadingClients = ref(false);
  const _clientsFetchedAt = ref(0);
  const _buildTimeouts = [];

  const modalFilteredClients = computed(() => {
    const q = sirketSearch.value.trim().toLowerCase();
    if (!q) return modalClients.value;
    return modalClients.value.filter(o => o.clititle.toLowerCase().includes(q) || o.lifnr.toLowerCase().includes(q) || (o.label && o.label.toLowerCase().includes(q)));
  });

  async function buildClientTable(force=false) {
    if (loadingClients.value) return;
    if (!force && modalClients.value.length && Date.now() - _clientsFetchedAt.value < 300000) return;
    _buildTimeouts.forEach(id => clearTimeout(id));
    loadingClients.value = true;
    modalClients.value = [];
    try {
      const fd = new FormData();
      fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-client-form'},{key:'type',type:'=',value:'op-doc-client'}], scale:{page:1, limit:200}, order:{key:'id', style:'asc'}}));
      const rsp = await plib.request({url:'/api/v1/table/documents', method:'POST'}, null, fd);
      const rows = rsp?.data?.data || rsp?.data || [];
      const list = Array.isArray(rows) ? rows : (rows?.data || []);
      const localData = list.map(r=>{
        try{
          const attrs=JSON.parse(r.main_attr||'[]');
          const lifnr=(attrs.find(a=>a.Key==='lifnr')||{}).Value||'';
          const title=(attrs.find(a=>a.Key==='title')||{}).Value||lifnr||'-';
          const label = title ? `${title} (${lifnr})` : lifnr;
          return { id:r.id, lifnr, clititle:title, label, main_attr:r.main_attr, qnid:r.id };
        }catch(e){ return null; }
      }).filter(Boolean);
      modalClients.value = localData;
      _clientsFetchedAt.value = Date.now();
    } catch(e){
      console.warn('client modal fetch failed',e);
      modalClients.value = [];
    } finally {
      loadingClients.value = false;
    }
  }

  function openClientModal(mode='multi') {
    clientModalMode.value = mode;
    showClientModal.value = true;
    sirketSearch.value = '';
    setTimeout(()=> buildClientTable(), 120);
  }

  function toggleSirket(val, applyFn) {
    const idx = selectedSirkets.value.indexOf(val);
    if (idx>-1) selectedSirkets.value.splice(idx,1);
    else selectedSirkets.value.push(val);
    if (applyFn && !showClientModal.value) applyFn();
  }

  function toggleAllModalClients(e) {
    const checked = e.target.checked;
    if (checked) {
      const set = new Set(selectedSirkets.value);
      modalFilteredClients.value.forEach(o=> set.add(o.lifnr));
      selectedSirkets.value = [...set];
    } else {
      const removeSet = new Set(modalFilteredClients.value.map(o=> o.lifnr));
      selectedSirkets.value = selectedSirkets.value.filter(v=> !removeSet.has(v));
    }
  }

  function clearFilter(tableRef, detayRef) {
    if (clientModalMode.value === 'single' && detayRef) {
      detayRef[clientModalMode.value] = '';
      // caller handles detay[target] clearing
      sirketSearch.value = '';
      return;
    }
    selectedSirkets.value = [];
    sirketSearch.value = '';
    if (tableRef) tableRef.setFilter([]);
  }

  return {
    sirketSearch, selectedSirkets, showClientModal, clientModalMode, detailedModalTarget,
    modalClients, loadingClients, modalFilteredClients, _clientsFetchedAt, _buildTimeouts,
    openClientModal, buildClientTable, toggleSirket, toggleAllModalClients, clearFilter,
  };
}
