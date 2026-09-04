// composables/useTedarikHeight.js — shared 75vh enforce for tedarik card tables
// OList/DList both had: `height:75vh !important` + `min-height:calc(75vh-280px)` + `.divTable height:90% overflow:auto`
// with `setTimeout 300+1000`. This version uses rAF + single 400ms fallback, cleans up on unmount.
// Selector differs: OList ".tedarik-card .pickletable" vs DList ".tedarik-docs-page .pickletable" — pass selector.

export function useTedarikHeight(isTedarikRef, selector, timeoutsRef) {
  const enforce = () => {
    if (typeof isTedarikRef === 'function' ? !isTedarikRef() : !isTedarikRef.value) return;
    const el = document.querySelector(selector || '.tedarik-card .pickletable');
    if (!el) return;
    if (el.style.getPropertyValue('height') !== '75vh') el.style.setProperty('height','75vh','important');
    if (el.style.getPropertyValue('min-height') !== 'calc(75vh - 280px)') el.style.setProperty('min-height','calc(75vh - 280px)','important');
    const divTable = el.querySelector('.divTable');
    if (divTable) {
      if (divTable.style.getPropertyValue('height') !== '90%') divTable.style.setProperty('height','90%','important');
      if (divTable.style.getPropertyValue('overflow') !== 'auto') divTable.style.setProperty('overflow','auto','important');
    }
  };

  function run(nextTickFn) {
    const runInner = () => {
      requestAnimationFrame(()=> {
        enforce();
        if (timeoutsRef) timeoutsRef.value?.push?.(setTimeout(enforce, 400));
        else if (Array.isArray(timeoutsRef)) timeoutsRef.push(setTimeout(enforce, 400));
      });
    };
    if (nextTickFn) nextTickFn(runInner); else runInner();
  }

  return { enforce, run };
}
