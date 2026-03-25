import { defineStore } from 'pinia'

import Plib from '@/lib/pickle';
export const useAuthStore = defineStore('auth', {
  state: () => {
    return { 
      data : {},
      permissions : null
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    setData(data){this.data = data},
    async getPermissions(){
      if(this.permissions == null){
        const rsp = await (new Plib).request({
            url      : '/api/v1/getpermissions',
            method   : 'GET',
        },null);
        this.permissions = rsp.permissions;
      }
    }
  },
});