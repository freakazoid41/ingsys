import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

export const useFormDataStore = defineStore('formdata', {
  state: () => {
    return { 
      rawData  : {},
      formData : {},
      addional : {},
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    setData(data,addional){this.formData = data ; this.addional = addional},
    getData(){return this.formData},
  },
})