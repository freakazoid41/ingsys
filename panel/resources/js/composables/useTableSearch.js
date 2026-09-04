// composables/useTableSearch.js — shared searchTable/resetSearch for admin vs tedarik refs
// OList/DList both had duplicate id="mainSearch" bug, now refs: `ref="adminSearch"` / `ref="tedarikSearch"`
// Usage: `const { searchTable, resetSearch } = useTableSearch(isTedarik, refs, tableRef)` or Options-API:
//   searchTable(){ const inp = this.isTedarik ? this.$refs.tedarikSearch : this.$refs.adminSearch; ... }

export function useTableSearch(isTedarikRef, getRefs, getTable) {
  const getInp = () => {
    const isTed = typeof isTedarikRef === 'function' ? isTedarikRef() : isTedarikRef?.value ?? isTedarikRef;
    const refs = typeof getRefs === 'function' ? getRefs() : getRefs;
    let inp = isTed ? refs?.tedarikSearch : refs?.adminSearch;
    if (!inp) inp = document.querySelector(isTed ? '.tedarik-docs-search-input' : '.dlist-search__input, .order-search-input');
    return inp;
  };
  const getTbl = () => (typeof getTable === 'function' ? getTable() : getTable);
  function searchTable() {
    const inp = getInp();
    const tbl = getTbl();
    if (!tbl) return;
    tbl.setFilter([{ key:'all', type:'=', value:(inp?.value||'').trim() }]);
  }
  function resetSearch() {
    const inp = getInp();
    const tbl = getTbl();
    if (inp) inp.value = '';
    if (tbl) tbl.setFilter([]);
  }
  return { searchTable, resetSearch };
}
