
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
    clearNotifications(){
        // Clear blink indicator and reset all notifications
        this.notifications = {
          blink: 0,
          awaitingUsers: [],
          clientChanges: [],
          newOffer: [],
          offerRevisionRequests: [],
          offerChanges: []
        };
    }
  },
})