// composables/useTedarikDropdown.js — shared Filtreler/Detaylı dropdown pos + outside click
// Used by OList (showDetayli) and DList (showFiltre). Keeps dropdown fixed+teleported at z9999, not clipped by tedarik-main overflow:hidden.
import { ref, nextTick } from 'vue';

export function useTedarikDropdown() {
  const show = ref(false);
  const dropdownPos = ref({ top: 0, left: 0, width: 280 });
  const wrapRef = ref(null);
  const ddRef = ref(null);
  const _timeouts = [];

  function updatePos(wrap) {
    try{
      const w = wrap || wrapRef.value;
      if(!w) return;
      const rect = w.getBoundingClientRect();
      let left = rect.left;
      const width = 280;
      if(left + width > window.innerWidth - 12) left = window.innerWidth - width - 12;
      if(left < 12) left = 12;
      dropdownPos.value = { top: rect.bottom + 10, left, width };
    }catch(e){}
  }

  function toggle(e, wrap) {
    if(e) e.stopPropagation();
    show.value = !show.value;
    if(show.value) nextTick(()=> updatePos(wrap));
  }

  function handleOutside(e, wrap, dd, onClose) {
    const w = wrap || wrapRef.value;
    const d = dd || ddRef.value;
    if(!w) return;
    const insideWrap = w.contains(e.target);
    const insideDd = d && d.contains(e.target);
    if(!insideWrap && !insideDd){
      show.value = false;
      if(onClose) onClose();
    }
  }

  function closeDelayed(ms=200) {
    _timeouts.push(setTimeout(()=> { show.value=false; }, ms));
  }

  function cleanup() {
    _timeouts.forEach(id=> clearTimeout(id));
  }

  return { show, dropdownPos, wrapRef, ddRef, updatePos, toggle, handleOutside, closeDelayed, cleanup, _timeouts };
}

// Options-API bridge for current OList/DList (no setup refactor yet)
// Copy-paste this into methods if you don't want to migrate to setup:
// toggleDetayli(e){ if(e) e.stopPropagation(); this.showDetayli=!this.showDetayli; if(this.showDetayli) this.$nextTick(()=> this.updateDropdownPos()); }
// The composable is ready for new pages: `const { show: showFiltre, dropdownPos, toggle, handleOutside } = useTedarikDropdown()`
