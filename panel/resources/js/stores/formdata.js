import { defineStore } from 'pinia'

export const useFormDataStore = defineStore('formdata', {
  state: () => {
    return { 
      formData : {}
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    setData(data){this.formData = data},
  },
})