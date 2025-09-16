import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

export const useFormDataStore = defineStore('formdata', {
  state: () => {
    return { 
      formData : {},
      facilities  : [],
      inventories : []
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    setData(data){this.formData = data},
    async setFacilitiesData(){
        const rsp = await (new Plib).request({
            url      : '/api/v1/dashboard/getFacilities',
            method   : 'GET',
        },null);
        const list = {};
        rsp.data.forEach(t => {
            const maindata = {};
            JSON.parse(t.main_attr).forEach(element => {
                maindata[element['Key']] = element['Value'];
            });

            list[t.id] = {...maindata,...t}
        });

        this.facilities = Object.values(list);
    },
    async setInventoriesData(){
        const rsp = await (new Plib).request({
            url      : '/api/v1/dashboard/getInventories',
            method   : 'GET',
        },null);
        const list = {};
        rsp.data.forEach(t => {
            const maindata = {};
            JSON.parse(t.main_attr).forEach(element => {
                maindata[element['Key']] = element['Value'];
            });

            list[t.id] = {...maindata,...t}
        });

        this.inventories = Object.values(list);
    },
  },
})