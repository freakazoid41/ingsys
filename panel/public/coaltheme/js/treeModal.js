/*
  TreeModal - vanilla JS modal tree with checkboxes
  Usage:
    TreeModal.show({ items, idKey, parentKey, labelKey, childrenKey, defaultChecked, title })
      .then(checkedIds => { if(checkedIds) console.log(checkedIds); })

  No dependencies. Returns a Promise resolved with array of checked ids on confirm, or null on cancel.
*/
// TreeModal module (ES module compatible)

function buildTreeFromFlat(items, idKey, parentKey, childrenKey) {
  const map = {};
  const roots = [];
  items.forEach(i => { map[i[idKey]] = Object.assign({}, i, { [childrenKey]: [] }); });
  items.forEach(i => {
    const node = map[i[idKey]];
    const pid = i[parentKey];
    if (pid == null || pid === 0 || pid === '') {
      roots.push(node);
    } else if (map[pid]) {
      map[pid][childrenKey].push(node);
    } else {
      roots.push(node);
    }
  });
  return roots;
}

function ensureArray(a) { return Array.isArray(a) ? a : []; }

function normalizeItems(items, options) {
  const idKey = options.idKey || 'id';
  const parentKey = options.parentKey || 'parent_id';
  const childrenKey = options.childrenKey || 'childs';
  let autoId = 1;

  const assignIds = (nodes, parentId = 0) => {
    return nodes.map(node => {
      const item = Object.assign({}, node);
      if (item[idKey] === undefined || item[idKey] === null) {
        item[idKey] = `auto-${autoId++}`;
      }
      if (item[parentKey] === undefined || item[parentKey] === null) {
        item[parentKey] = parentId;
      }
      const rawChildren = item.children || item[childrenKey] || [];
      const children = Array.isArray(rawChildren) ? rawChildren : [];
      item[childrenKey] = assignIds(children, item[idKey]);
      item.children = item[childrenKey];
      return item;
    });
  };

  if (!Array.isArray(items)) return [];
  if (!items.length) return [];

  if (items[0][parentKey] !== undefined && items[0][idKey] !== undefined) {
    const flatTree = buildTreeFromFlat(items, idKey, parentKey, childrenKey);
    return normalizeItems(flatTree, options);
  }

  return assignIds(items, 0);
}

  function resolveTarget(target) {
    if (!target) return null;
    if (typeof target === 'string') return document.querySelector(target);
    if (target instanceof HTMLElement) return target;
    if (target instanceof NodeList || Array.isArray(target)) return target[0] || null;
    if (typeof target === 'object' && target.jquery && target.length) return target[0];
    return null;
  }

  function injectStyles() {
    if (document.getElementById('tree-modal-styles')) return;
    const style = document.createElement('style');
    style.id = 'tree-modal-styles';
    style.innerHTML = `
      .tm-root, .tm-children { list-style: none; margin: 0; padding-left: 0; }
      .tm-root { width: 100%; max-height: 280px; overflow-y: auto; }
      .tm-node { margin: 4px 0; }
      .tm-node-row { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; transition: all .2s ease; cursor: pointer; height: 40px }
      .tm-node-row:hover { background: #f8fafc; border-color: #93c5fd; }
      .tm-toggle, .tm-toggle-empty { width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; color: #2563eb; font-weight: 700; border-radius: 3px; }
      .tm-toggle-empty { visibility: hidden; }
      .tm-node-row input[type="checkbox"] { accent-color: #154B91; width: 18px; height: 18px; margin: 0; }
      .tm-node-label { font-size: 14px; font-weight: 600; color: #0f172a; }
      .tm-children { padding-left: 14px; margin-top: 3px; }
      .tm-children.tm-collapsed { display: none; }
    `;
    document.head.appendChild(style);
  }

  function normalizeCheckedInput(rawChecked){
    if (rawChecked == null) return [];

    let values = rawChecked;
    if (typeof values === 'string') {
      try {
        const parsed = JSON.parse(values);
        if (Array.isArray(parsed)) {
          values = parsed;
        } else if (parsed && typeof parsed === 'object') {
          values = parsed;
        } else {
          values = values.split(',').map(v => v.trim()).filter(Boolean);
        }
      } catch (e) {
        values = values.split(',').map(v => v.trim()).filter(Boolean);
      }
    }

    if (typeof values === 'object' && values !== null && !Array.isArray(values)) {
      if (values.Value !== undefined) {
        return normalizeCheckedInput(values.Value);
      }
      return [values];
    }

    if (!Array.isArray(values)) {
      return [values];
    }

    return values;
  }

  function createModal(){
    const overlay = document.createElement('div');
    overlay.className = 'tm-overlay';
    const modal = document.createElement('div');
    modal.className = 'tm-modal';
    overlay.appendChild(modal);
    return { overlay, modal };
  }

  function createNodeElement(node, options, checkedSet){
    const { idKey, labelKey, childrenKey } = options;
    const item = document.createElement('li');
    item.className = 'tm-node';

    const row = document.createElement('div'); row.className = 'tm-node-row';
    const children = node[childrenKey] || node.children || node.childs || [];

    const toggle = document.createElement('span');
    toggle.className = 'tm-toggle';
    toggle.textContent = children.length ? '▾' : '▹';
    toggle.style.visibility = children.length ? 'visible' : 'hidden';
    row.appendChild(toggle);

    const input = document.createElement('input'); input.type = 'checkbox';
    input.dataset.tmid = node[idKey];
    input.dataset.opKey = node.op_key || '';
    input.dataset.treenode = JSON.stringify(node);
    input.__treeNode = node;
    input.checked = checkedSet.has(String(node[idKey])) || checkedSet.has(String(node.op_key));
    input.className = 'tm-checkbox';

    const nodeLabel = node[labelKey] || node.title || node.name || node.op_key || '';
    const labelEl = document.createElement('span'); labelEl.className = 'tm-node-label';
    labelEl.textContent = nodeLabel;

    row.appendChild(input);
    row.appendChild(labelEl);
    item.appendChild(row);

    let ul = null;
    if (children.length){
      ul = document.createElement('ul'); ul.className = 'tm-children';
      children.forEach(c => {
        ul.appendChild(createNodeElement(c, options, checkedSet));
      });
      item.appendChild(ul);

      toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const hidden = ul.classList.toggle('tm-collapsed');
        toggle.textContent = hidden ? '▹' : '▾';
      });
    }

    return item;
  }

  function setChildrenChecked(el, checked){
    const inputs = el.querySelectorAll('input[type=checkbox]');
    inputs.forEach(i => { i.checked = checked; i.indeterminate = false; });
  }

  function updateAncestorState(input){
    let parent = input.parentElement; // .tm-node-row
    while (parent && parent !== document.body){
      // find parent li then its parent ul then its parent li
      const li = parent.closest('li');
      if (!li) break;
      const parentUl = li.parentElement; // could be .tm-children
      if (!parentUl) break;
      const ancestorLi = parentUl.closest('li');
      if (!ancestorLi) break;
      const ancestorCheckbox = ancestorLi.querySelector('input[type=checkbox]');
      if (!ancestorCheckbox) break;
      const childrenInputs = Array.from(ancestorLi.querySelectorAll(':scope > ul input[type=checkbox]'));
      const any = childrenInputs.some(i => i.checked || i.indeterminate);
      const all = childrenInputs.every(i => i.checked && !i.indeterminate);
      ancestorCheckbox.checked = all;
      ancestorCheckbox.indeterminate = (!all && any);
      parent = ancestorLi.parentElement;
    }
  }

  function collectChecked(modal){
    return getCheckedValues(modal, true);
  }

  function attachHandlers(nodeContainer, onChange) {
    nodeContainer.addEventListener('change', function(e){
      const tgt = e.target;
      if (tgt && tgt.tagName === 'INPUT' && tgt.type === 'checkbox'){
        const li = tgt.closest('li');
        if (li){
          // set children
          const childUl = li.querySelector('ul');
          if (childUl){ setChildrenChecked(childUl, tgt.checked); }
        }
        // update ancestors
        updateAncestorState(tgt);
        const checked = getCheckedValues(nodeContainer, true);
        console.debug('TreeModal checked data', checked);
        if (typeof onChange === 'function') onChange(checked);
      }
    });
  }

  function getCheckedValues(container, asObjects = false){
    const inputs = container.querySelectorAll('input[type=checkbox]');
    const values = [];
    inputs.forEach(i => {
      if (!i.checked) return;
      if (asObjects) {
        if (i.__treeNode) {
          values.push(i.__treeNode);
        } else if (i.dataset.treenode) {
          try {
            values.push(JSON.parse(i.dataset.treenode));
          } catch (e) {
            values.push({ id: i.dataset.tmid });
          }
        } else {
          values.push({ id: i.dataset.tmid });
        }
      } else {
        values.push(i.dataset.tmid ?? i.value ?? i.getAttribute('data-tmid'));
      }
    });
    return values;
  }

  function render(options){
    options = options || {};
    injectStyles();
    const target = resolveTarget(options.target);
    if (!target) {
      console.error('TreeModal.render target element not found. Provide selector, HTMLElement, NodeList/Array, or jQuery object.');
      return null;
    }

    const idKey = options.idKey || 'id';
    const parentKey = options.parentKey || 'parent_id';
    const labelKey = options.labelKey || options.titleKey || 'title';
    const childrenKey = options.childrenKey || 'childs';
    const items = ensureArray(options.items || []);
    const checkedList = normalizeCheckedInput(options.defaultChecked);
    const normalized = checkedList.flatMap(v => {
      if (v == null) return [];
      if (typeof v === 'object') {
        const mapped = [];
        if (v[idKey] !== undefined && v[idKey] !== null) mapped.push(v[idKey]);
        if (v.op_key !== undefined && v.op_key !== null) mapped.push(v.op_key);
        if (v.Value !== undefined && v.Value !== null) mapped.push(v.Value);
        if (v.value !== undefined && v.value !== null) mapped.push(v.value);
        return mapped;
      }
      return [v];
    });
    const checkedSet = new Set(normalized.map(String));

    let tree = normalizeItems(items, options);

    const root = document.createElement('ul');
    root.className = 'tm-root';
    if (options.height) root.style.height = options.height;
    if (options.maxHeight) {
      root.style.maxHeight = options.maxHeight;
      root.style.overflowY = 'auto';
    }
    if (options.scroll) root.style.overflowY = 'auto';
    tree.forEach(n => root.appendChild(createNodeElement(n, { idKey, labelKey, childrenKey }, checkedSet)));
    target.innerHTML = '';
    target.appendChild(root);

    attachHandlers(target, options.onChange);

    return {
      getChecked: () => getCheckedValues(target, true),
      setChecked: (ids = []) => {
        const normalized = (Array.isArray(ids) ? ids : [ids]).flatMap(v => {
          if (v == null) return [];
          if (typeof v === 'object') {
            const mapped = [];
            if (v[idKey] !== undefined && v[idKey] !== null) mapped.push(v[idKey]);
            if (v.op_key !== undefined && v.op_key !== null) mapped.push(v.op_key);
            return mapped;
          }
          return [v];
        });
        const valueSet = new Set(normalized.map(String));
        target.querySelectorAll('input[type=checkbox]').forEach(i => {
          const idValue = String(i.dataset.tmid || '');
          const opKeyValue = String(i.dataset.opKey || '');
          i.checked = valueSet.has(idValue) || valueSet.has(opKeyValue);
          i.indeterminate = false;
        });
      },
      destroy: () => { target.innerHTML = ''; }
    };
  }

  function show(options){
    injectStyles();
    return new Promise((resolve)=>{
      try{
        options = options || {};
        const idKey = options.idKey || 'id';
        const parentKey = options.parentKey || 'parent_id';
        const labelKey = options.labelKey || options.titleKey || 'title';
        const childrenKey = options.childrenKey || 'childs';
        const items = ensureArray(options.items || []);
        const checkedList = normalizeCheckedInput(options.defaultChecked);
        const normalised = checkedList.flatMap(v => {
          if (v == null) return [];
          if (typeof v === 'object') {
            const mapped = [];
            if (v[idKey] !== undefined && v[idKey] !== null) mapped.push(v[idKey]);
            if (v.op_key !== undefined && v.op_key !== null) mapped.push(v.op_key);
            if (v.Value !== undefined && v.Value !== null) mapped.push(v.Value);
            if (v.value !== undefined && v.value !== null) mapped.push(v.value);
            return mapped;
          }
          return [v];
        });
        const defaultChecked = new Set(normalised.map(String));

        let tree = normalizeItems(items, options);

        const { overlay, modal } = createModal();

        // header
        const header = document.createElement('div'); header.className = 'tm-header';
        const title = document.createElement('div'); title.className = 'tm-title'; title.textContent = options.title || 'Select Items';
        header.appendChild(title);
        modal.appendChild(header);

        // body
        const body = document.createElement('div'); body.className = 'tm-body';
        const ul = document.createElement('ul'); ul.className = 'tm-root';
        const checkedSet = defaultChecked;
        tree.forEach(n => ul.appendChild(createNodeElement(n, { idKey, labelKey, childrenKey }, checkedSet)));
        body.appendChild(ul);
        modal.appendChild(body);

        // footer
        const footer = document.createElement('div'); footer.className = 'tm-footer';
        const btnCancel = document.createElement('button'); btnCancel.className = 'tm-btn tm-cancel'; btnCancel.textContent = options.cancelText || 'Cancel';
        const btnOk = document.createElement('button'); btnOk.className = 'tm-btn tm-ok'; btnOk.textContent = options.okText || 'OK';
        footer.appendChild(btnCancel); footer.appendChild(btnOk);
        modal.appendChild(footer);

        document.body.appendChild(overlay);
        // attach handlers
        attachHandlers(modal);

        btnCancel.addEventListener('click', ()=>{ try{ overlay.remove(); }catch(e){console.error(e);} resolve(null); });
        btnOk.addEventListener('click', ()=>{ try{ const checked = collectChecked(modal, idKey); overlay.remove(); resolve(checked); }catch(e){ console.error(e); overlay.remove(); resolve(null);} });
      }catch(err){ console.error('TreeModal show error', err); resolve(null); }
    });
  }

// expose as module
const TreeModal = { show, render };
export default TreeModal;

