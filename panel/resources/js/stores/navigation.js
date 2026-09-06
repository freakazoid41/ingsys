
import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

export const useNavigationStore = defineStore('navigation', {
  state: () => {
    // try to restore persisted nav state from sessionStorage
    let persisted = {};
    try{
      const raw = sessionStorage.getItem('nav.state');
      if(raw) persisted = JSON.parse(raw) || {};
    }catch(e){ persisted = {}; }
    return { 
      active           : false,
      currentTitle     : persisted.currentTitle || '',
      breadcrumps      : persisted.breadcrumps || [],
      breadbuttons     : persisted.breadbuttons || [],
      routeParams      : persisted.routeParams || {},
      lastUpdated      : persisted.lastUpdated || 0,
      notifications    : {},
      notificationError: null,
      sys_code         : document.querySelector('input[name="SYS_CODE"]').value
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    toggle(status = false){ this.$patch({ active: status }) },
    setBread(list = [], title = ''){
      const payload = { breadcrumps: list, currentTitle: title, lastUpdated: Date.now() };
      try{
        this.$state = Object.assign({}, this.$state, payload);
      }catch(e){
        // fallback to $patch if direct $state assignment fails
        try{ this.$patch(payload); }catch(e){}
      }
      try{ sessionStorage.setItem('nav.state', JSON.stringify({ breadcrumps: this.breadcrumps, currentTitle: this.currentTitle, breadbuttons: this.breadbuttons, routeParams: this.routeParams, lastUpdated: this.lastUpdated })); }catch(e){}
    },
    setButtons(list = []){
      const payload = { breadbuttons: list, lastUpdated: Date.now() };
      try{
        this.$state = Object.assign({}, this.$state, payload);
      }catch(e){
        try{ this.$patch(payload); }catch(e){}
      }
      try{ sessionStorage.setItem('nav.state', JSON.stringify({ breadcrumps: this.breadcrumps, currentTitle: this.currentTitle, breadbuttons: this.breadbuttons, routeParams: this.routeParams, lastUpdated: this.lastUpdated })); }catch(e){}
    },
    setRouteParams(params = {}){
      const payload = { routeParams: params, lastUpdated: Date.now() };
      try{
        this.$state = Object.assign({}, this.$state, payload);
      }catch(e){
        try{ this.$patch(payload); }catch(e){}
      }
      try{ sessionStorage.setItem('nav.state', JSON.stringify({ breadcrumps: this.breadcrumps, currentTitle: this.currentTitle, breadbuttons: this.breadbuttons, routeParams: this.routeParams, lastUpdated: this.lastUpdated })); }catch(e){}
    },
    async getNotifications(){
        try {
          const rsp = await (new Plib).request({
            url      : '/api/v1/notifications',
            method   : 'GET',
          }, null);
          
          this.notifications = rsp || {};
        } catch(error) {
          console.error('Failed to load notifications:', error);
          this.notifications = { blink: 0 }; // Reset on error
        }
    },
    async markNotificationRead(opKey, targetQnid){
        try {
          await (new Plib).request({
            url      : '/api/v1/notifications/read',
            method   : 'POST',
            data     : { op_key: opKey, target_qnid: targetQnid },
          }, null);
          // Remove from local state
          const keyMap = {
            'tedarik-01': 'orderImported',
            'tedarik-02': 'orderSent',
            'tedarik-03': 'pendingFiles',
            'tedarik-04': 'fileApproved',
            'tedarik-05': 'fileRejected',
            'tedarik-06': 'orderApproved',
            'tedarik-07': 'orderRejected',
          };
          const arrKey = keyMap[opKey];
          if(arrKey && Array.isArray(this.notifications[arrKey])){
            this.notifications[arrKey] = this.notifications[arrKey].filter(n => (n.qnid || n.id) !== targetQnid);
            // Recalculate unread total
            let total = 0;
            for(const k of Object.values(keyMap)){
              if(Array.isArray(this.notifications[k])) total += this.notifications[k].length;
            }
            this.notifications.unreadTotal = total;
            this.notifications.blink = total > 0 ? 1 : 0;
          }
        } catch(error) {
          console.error('Failed to mark notification read:', error);
        }
    },
    async markAllNotificationsRead(){
        try {
          await (new Plib).request({
            url      : '/api/v1/notifications/read-all',
            method   : 'POST',
          }, null);
          this.clearNotifications();
        } catch(error) {
          console.error('Failed to mark all notifications read:', error);
        }
    },
    clearNotifications(){
        this.notifications = {
          blink: 0,
          orderImported: [],
          orderSent: [],
          pendingFiles: [],
          fileApproved: [],
          fileRejected: [],
          orderApproved: [],
          orderRejected: []
        };
    }
  },
})