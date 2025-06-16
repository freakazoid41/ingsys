<script>
    import { useNavigationStore } from '@/stores/navigation'
    import { wTrans } from 'laravel-vue-i18n';
    import Transactions from '@/components/Transactions.vue';
    import Swal from 'sweetalert2';

    export default {
        components: {
            Transactions 
        },
        setup() {
            // expose to template and other options API hooks
            return {
                useNavigationStore,
                wTrans,
                Swal
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            
            
            this.navigationStore.setBread([
                {
                    title : 'Körfez Apt.',
                    url   : '/panel',
                },
                {
                    title : this.wTrans('menu.transactions'),
                    url   : '/panel/transactions',
                }
            ] ,this.wTrans('menu.transactions'));

            /*this.navigationStore.setButtons([
                {
                icon : 'ph ph-download',
                onclick   : () => {console.log('Falan')},
                }
            ]);*/


            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                navigationStore : useNavigationStore(),
            }
        },
    }
</script>
<template>
    <Transactions/>
</template>