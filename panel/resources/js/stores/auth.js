import { defineStore } from 'pinia';
import Plib from '@/lib/pickle';

import Plib from '@/lib/pickle';
export const useAuthStore = defineStore('auth', {
  state: () => {
    return { 
      data : {},
      permissions : null,
      currentStatus : null,
      typeKey : null,
      personId : null,
      userName : null,
      _heartbeat: null,
    }
  },
  actions: {
    setData(data){this.data = data},
    async getPermissions(){
      const rsp = await (new Plib).request({
          url      : '/api/v1/getpermissions',
          method   : 'GET',
      },null);
      if(!rsp) return;
      this.permissions = rsp.permissions;
      this.currentStatus = rsp.currentStatus; // for client accounts
      this.typeKey = rsp.typeKey; // for client accounts
      this.personId = rsp.personId; // for client accounts
      this.userName = rsp.userName ?? null;
    },
    startHeartbeat(){
      if(this._heartbeat) return;
      this._heartbeat = setInterval(() => {
        this.getPermissions();
      }, 30000);
    },
    stopHeartbeat(){
      if(this._heartbeat){
        clearInterval(this._heartbeat);
        this._heartbeat = null;
      }
    },
  },
});