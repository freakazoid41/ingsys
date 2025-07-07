import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

export const useEventDataStore = defineStore('taskData', {
  state: () => {
    return { 
      tasks  : [],
      events : [],
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    async setTaskData(){
        /*const rsp = await (new Plib).request({
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

        this.tasks = Object.values(list);*/
    },
    async setEventData(){
        /*const dateObj = new Date();
        const month   = dateObj.getUTCMonth() + 1; // months from 1-12
        const year    = dateObj.getUTCFullYear();
        const isValid  = (date) => {  
          if (!(date instanceof Date)) {
            throw new Error('Invalid argument: you must provide a "date" instance')
          }

          const tomorrow = new Date()
          tomorrow.setDate(tomorrow.getDate() + 1)
          //is tomorrow or today
          const today = new Date();
          return (date.getDate() === tomorrow.getDate() &&
                date.getMonth() === tomorrow.getMonth() &&
                date.getFullYear() === tomorrow.getFullYear()) || 
                
                (date.getDate() === today.getDate() &&
                date.getMonth() === today.getMonth() &&
                date.getFullYear() === today.getFullYear());
        };

        const rsp = await (new Plib).request({
            url      : '/api/v1/dashboard/monthlyEvents/'+year+'-'+month,
            method   : 'GET',
        },null).then(rsp => {return rsp});

        const list = [];
        rsp.data.forEach(el => {
            const attr = {
                id : el.id,
            };
            JSON.parse(el.main_attr).forEach(at => {
                attr[at['Key']] = at['Value'];
            });

          
            if(isValid(new Date(attr.start_date)) || isValid(new Date(attr.end_date))) list.push(attr);
        });


        this.events = Object.values(list);*/
    },
  },
})