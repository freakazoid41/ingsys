
import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

// Several dashboard widgets + headers call getNotifications on mount. Coalesce
// concurrent calls and reuse the result briefly instead of hammering the feed.
let notifInFlight = null;
let notifFetchedAt = 0;
const NOTIF_TTL = 20000;

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
        if (notifInFlight) return notifInFlight;
        if (Date.now() - notifFetchedAt < NOTIF_TTL && this.notifications && this.notifications.unreadTotal !== undefined) {
          return this.notifications;
        }

        notifInFlight = (async () => {
          try {
            const rsp = await (new Plib).request({
              url      : '/api/v1/notifications',
              method   : 'GET',
            }, null);

            this.notifications = rsp || {};
            notifFetchedAt = Date.now();
          } catch(error) {
            console.error('Failed to load notifications:', error);
            this.notifications = { blink: 0 }; // Reset on error
          } finally {
            notifInFlight = null;
          }
          return this.notifications;
        })();

        return notifInFlight;
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
    async markAllNotificationsRead(opKeys = null){
        try {
          await (new Plib).request({
            url      : '/api/v1/notifications/read-all',
            method   : 'POST',
            data     : Array.isArray(opKeys) && opKeys.length ? { op_keys: opKeys } : {},
          }, null);

          if (Array.isArray(opKeys) && opKeys.length) {
            const keyMap = {
              'tedarik-01': 'orderImported', 'tedarik-02': 'orderSent', 'tedarik-03': 'pendingFiles',
              'tedarik-04': 'fileApproved', 'tedarik-05': 'fileRejected', 'tedarik-06': 'orderApproved', 'tedarik-07': 'orderRejected',
              'planlama-01': 'planlamaSent', 'planlama-02': 'planlamaRejected', 'planlama-03': 'planlamaApproved',
              'ihale-01': 'tenderActivated', 'ihale-02': 'tenderCompleted',
              'offer-01': 'offerSubmitted', 'offer-02': 'offerRejected', 'offer-03': 'offerAccepted',
            };
            for (const op of opKeys) {
              const k = keyMap[op];
              if (k) this.notifications[k] = [];
            }
            const sumOf = (keys) => keys.reduce((acc, k) => acc + (Array.isArray(this.notifications[k]) ? this.notifications[k].length : 0), 0);
            const tedarikKeys = ['orderImported', 'orderSent', 'pendingFiles', 'fileApproved', 'fileRejected', 'orderApproved', 'orderRejected'];
            const planlamaKeys = ['planlamaSent', 'planlamaRejected', 'planlamaApproved'];
            const tenderKeys = ['tenderActivated', 'tenderCompleted', 'offerSubmitted', 'offerRejected', 'offerAccepted'];
            this.notifications.tedarikUnread = sumOf(tedarikKeys);
            this.notifications.planlamaUnread = sumOf(planlamaKeys);
            this.notifications.tenderUnread = sumOf(tenderKeys);
            const total = this.notifications.tedarikUnread + this.notifications.planlamaUnread + this.notifications.tenderUnread;
            this.notifications.unreadTotal = total;
            this.notifications.blink = total > 0 ? 1 : 0;
          } else {
            this.clearNotifications();
          }
        } catch(error) {
          console.error('Failed to mark all notifications read:', error);
        }
    },
    clearNotifications(){
        this.notifications = {
          blink: 0,
          unreadTotal: 0,
          tedarikUnread: 0,
          planlamaUnread: 0,
          tenderUnread: 0,
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