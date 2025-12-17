import { defineStore } from 'pinia';
import Plib from '@/lib/pickle';

export const useAuthStore = defineStore('auth', {
  state: () => {
    return { 
      data : {}
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    setData(data){this.data = data},
    async getData(){
      const rsp = await (new Plib).request({
          url      : '/api/v1/getcurrentf',
          method   : 'GET',
      },null).then(rsp => {return rsp});

      for(let key in rsp) this.data[key] = rsp[key];
    }
  },
});