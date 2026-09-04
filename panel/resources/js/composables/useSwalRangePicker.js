// composables/useSwalRangePicker.js — shared flatpickr range inside Swal for Filtreler -> Tarih Aralığı
// OList/DList both had same Swal+flatpickr didOpen/onReady 80ms + preConfirm + toPipe. This dedupes.

import flatpickr from 'flatpickr';
import { Turkish } from 'flatpickr/dist/l10n/tr.js';
import { toPipe } from '@/lib/pipe';

export function useSwalRangePicker(Swal, flatpickrRangeRef) {
  // flatpickrRangeRef: ref object { value: null } or { flatpickrRange: null }
  const getRef = () => flatpickrRangeRef;
  async function openSwalRange(toggleFn, tableRef) {
    const { value: rangeVal } = await Swal.fire({
      title:'Tarih Aralığı Seçin',
      html:'<input id="swal-flat-range" class="swal2-input" placeholder="Tarih aralığı seçin" style="width:85%;margin:8px auto;display:block;">',
      showCancelButton:true, confirmButtonText:'Filtrele', cancelButtonText:'Vazgeç', confirmButtonColor:'#FF5A1F',
      didOpen:()=>{
        const el=document.getElementById('swal-flat-range');
        if(!el) return;
        const ref = getRef();
        if(ref.value){ ref.value.destroy(); ref.value=null; }
        // also support plain object { flatpickrRange }
        if(ref.flatpickrRange){ ref.flatpickrRange.destroy(); ref.flatpickrRange=null; }
        const inst = flatpickr(el, { mode:'range', dateFormat:'Y-m-d', allowInput:true, locale: Turkish, onReady:(_,__,fp)=>{ setTimeout(()=> fp.open(), 80); } });
        if(ref.value !== undefined) ref.value = inst; else ref.flatpickrRange = inst;
      },
      preConfirm:()=>{
        const v=document.getElementById('swal-flat-range')?.value?.trim() || '';
        if(!v){ Swal.showValidationMessage('Lütfen tarih aralığı seçin'); return false; }
        return v;
      },
      didClose:()=>{
        const ref = getRef();
        const inst = ref.value ?? ref.flatpickrRange;
        if(inst){ inst.destroy(); if(ref.value!==undefined) ref.value=null; else ref.flatpickrRange=null; }
      }
    });
    if(rangeVal){
      const v = toPipe(rangeVal);
      const tbl = typeof tableRef === 'function' ? tableRef() : tableRef;
      if(tbl) tbl.setFilter([{key:'tarih_araligi', type:'like', value: v }]);
    }
    return rangeVal;
  }
  return { openSwalRange, toPipe };
}
