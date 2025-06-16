
import { defineStore } from 'pinia'

export const useNavigationStore = defineStore('navigation', {
  state: () => {
    return { 
      active: false,
      currentTitle : '',
      breadcrumps : [],
      breadbuttons : [],
    }
  },
  // could also be defined as
  // state: () => ({ count: 0 })
  actions: {
    toggle(status = false){this.active = status},
    setBread(list = [],title = ''){this.breadcrumps = list ; this.currentTitle = title},
    setButtons(list = []){this.breadbuttons = list; },
  },
})