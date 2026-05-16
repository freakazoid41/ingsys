<template>
  <div class="tree-test">
    <h2>TreeCheck Demo</h2>

    <div class="controls">
      <button @click="selectAll">Select All</button>
      <button @click="clearAll">Clear All</button>
      <button @click="setDefaults">Set Defaults</button>
      
      <button @click="embedNativeTree">Embed Native Tree</button>
    </div>

    

    <div id="inline-tree-container" style="border:1px solid #ccc; padding:12px; min-height: 220px; background:#fff; margin-bottom:12px;"></div>

    <div class="output">
      <h3>Checked IDs</h3>
      <pre>{{ checked }}</pre>
    </div>
  </div>
</template>

<script>
import TreeModal from '@/lib/treeModal.js';

export default {
  name: 'TreeTestPage',
  breadcrumbs: {
    list: [ { title: 'Panel', path: '/' }, { title: 'Tree Test', path: '/tree-test' } ],
    title: 'Tree Test'
  },
  data() {
    return {
      items: [
        { id: 1, parent_id: 0, name: 'Root A' },
        { id: 2, parent_id: 1, name: 'Child A.1' },
        { id: 3, parent_id: 1, name: 'Child A.2' },
        { id: 4, parent_id: 2, name: 'Child A.1.a' },
        { id: 5, parent_id: 0, name: 'Root B' },
        { id: 6, parent_id: 5, name: 'Child B.1' },
        { id: 7, parent_id: 5, name: 'Child B.2' }
      ],
      checked: [2]
    };
  },
  methods: {
    selectAll() { this.checked = this.items.map(i => i.id); },
    clearAll() { this.checked = []; },
    setDefaults() { this.checked = [1,5]; },

    async openNativeTree() {
      const checkedIds = await TreeModal.show({
        items: this.items,
        defaultChecked: this.checked,
        title: 'Native Tree Modal',
        okText: 'Confirm',
        cancelText: 'Cancel'
      });
      if (checkedIds !== null) {
        this.checked = checkedIds.map(id => (Number(id).toString() === id ? Number(id) : id));
      }
    },
    async embedNativeTree() {
      const renderInstance = TreeModal.render({
        target: '#inline-tree-container',
        items: this.items,
        defaultChecked: this.checked,
        maxHeight: '280px',
        onChange: (checkedItems) => {
          this.checked = checkedItems.map(id => (Number(id).toString() === id ? Number(id) : id));
        }
      });

      if (renderInstance) {
        this._treeModalInstance = renderInstance;
      }
    }
  }
}
</script>


