import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

export const useTaskDataStore = defineStore('taskData', {
  state: () => {
    return { 
      tasks : []
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    async setData(){
        const rsp = await (new Plib).request({
            url      : '/api/v1/dashboard/getOngoingTasks',
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

        this.tasks = Object.values(list);
    },
  },
})