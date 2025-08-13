
import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

export const useNavigationStore = defineStore('navigation', {
  state: () => {
    return { 
      active       : false,
      currentTitle : '',
      breadcrumps  : [],
      breadbuttons : [],
      apartments   : {}
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    toggle(status = false){this.active = status},
    setBread(list = [],title = ''){this.breadcrumps = list ; this.currentTitle = title},
    setButtons(list = []){this.breadbuttons = list; },
    async setApartments(){
      const rsp = await (new Plib).request({
          url      : '/api/v1/get-apartments',
          method   : 'GET',
      },null);

      rsp.forEach(element => {
        this.apartments[element.op_key] = element;
      });
    }
  },
})