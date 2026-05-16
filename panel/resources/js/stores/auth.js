import { defineStore } from 'pinia'

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
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    setData(data){this.data = data},
    async getPermissions(){
      const rsp = await (new Plib).request({
          url      : '/api/v1/getpermissions',
          method   : 'GET',
      },null);
      this.permissions = rsp.permissions;
      this.currentStatus = rsp.currentStatus; // for client accounts
      this.typeKey = rsp.typeKey; // for client accounts
      this.personId = rsp.personId; // for client accounts
      this.userName = rsp.userName ?? null;
    }
  },
});