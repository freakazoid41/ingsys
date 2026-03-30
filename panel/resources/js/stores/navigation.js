
import { defineStore } from 'pinia'
import Plib from '@/lib/pickle';

export const useNavigationStore = defineStore('navigation', {
  state: () => {
    return { 
      active       : false,
      currentTitle : '',
      breadcrumps  : [],
      breadbuttons : [],
      apartments   : {},
      fileEntities : {
        'clientodasicil': 'Müşteri Odası Sicil Belgesi',
        'clientibanbilgi' : 'Müşteri IBAN Bilgisi',
        'clientvergilevha' : 'Müşteri Vergi Levhası',
        'clientimzasirku' : 'Müşteri İmza Sirküleri',
      }
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