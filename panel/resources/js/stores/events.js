import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

export const useEventDataStore = defineStore('taskData', {
  state: () => {
    return { 
      tasks       : [],
      events      : [],
      
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    
  },
})