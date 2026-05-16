import { useNavigationStore } from '@/stores/navigation';

export default {
  install(app) {
    // global helper accessible as this.$setBreadcrumbs(...) in components
    app.config.globalProperties.$setBreadcrumbs = (list = [], title = '') => {
      const nav = useNavigationStore();
      nav.setBread(list, title);
    };

    // global mixin: if a component defines a `breadcrumbs` option, set it on mount
    app.mixin({
      mounted() {
        const opts = this.$options && this.$options.breadcrumbs;
        if (!opts) return;
        const list = Array.isArray(opts) ? opts : (opts.list || []);
        const title = (opts.title || opts.breadTitle || '');
        useNavigationStore().setBread(list, title);
      },
      beforeUnmount() {
        // optionally clear breadcrumbs when leaving a page (keep if you prefer persistence)
        const opts = this.$options && this.$options.breadcrumbs;
        if (!opts) return;
        useNavigationStore().setBread([],'');
      }
    });
  }
}
