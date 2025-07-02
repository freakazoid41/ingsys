import { defineStore } from 'pinia'

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
  },
});