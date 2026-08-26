<script>
import { useNavigationStore } from '@/stores/navigation';
import { useAuthStore } from '@/stores/auth';
import PickleTable from 'pickletable';
import 'pickletable/assets/style.css';
import Plib from '@/lib/pickle';
import Swal from 'sweetalert2';
import Default from '@/components/Dashboard/Default.vue';
import Client from '@/components/Dashboard/Client.vue';
import Admin from '@/components/Dashboard/Admin.vue';

export default {
  breadcrumbs: {
    list: [
      { title: 'Dashboard', path: '/coalpanel' }
    ],
    title: 'Dashboard'
  },
  components : {
    Default,
    Client,
    Admin
  },
  setup() {
    return {
      useNavigationStore,
      useAuthStore,
      Plib,
    }
  },
  data() {
    return {
      plib : new Plib(),
      authStore: useAuthStore(),
      navigationStore: useNavigationStore(),
      
    }
  },
  mounted() {
    this.navigationStore.toggle(true);
    
    document.getElementById('kt_content').classList.remove('hero-overlap');
    document.getElementById('kt_content').style.marginTop = '0px';
    document.getElementById('kt_header').hidden = true;

    setTimeout(() => {
      this.navigationStore.toggle(false);
    }, 500);
    const content = document.getElementById('kt_content');
    if (content) content.classList.add('hero-overlap');
  },
  beforeUnmount() {
    document.getElementById('kt_content').classList.add('hero-overlap');
    document.getElementById('kt_content').style.marginTop = '-70px';
    document.getElementById('kt_header').hidden = false;

    const content = document.getElementById('kt_content');
    if (content) content.classList.remove('hero-overlap');
  },
  methods: {
   
    
   
  }
}
</script>

<template>
  <section class="dashboard-page">
    
    <Admin v-if="authStore.typeKey !== 'op-pert-reseller'" />
    <Client v-if="authStore.typeKey === 'op-pert-reseller'" />
  </section>
</template>


