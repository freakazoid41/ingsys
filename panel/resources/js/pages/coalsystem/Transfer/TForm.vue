<script>
    import Plib from '@/lib/pickle';
    import { useRoute } from 'vue-router'
    import { useNavigationStore } from '@/stores/navigation'
    import { useFormDataStore } from '@/stores/formdata';
    import { useAuthStore } from '@/stores/auth';
    import Swal from 'sweetalert2';
    import Form from '@/components/coalparts/Form.vue';
    export default {
        breadcrumbs: { list:[{title:'Transferler', path:'/coalpanel/transfers'},{title:'Transfer Detayı', path:'#'}], title:'Transfer Detayı' },
        components:{Form},
        setup(){ return { useNavigationStore, useFormDataStore, Plib, Swal, useRoute, useAuthStore } },
        mounted(){
            this.navigationStore.toggle(true);
            const checkData = async ()=>{
                if(this.id){
                    const rsp=await this.plib.request({url:'/api/v1/document/'+this.id, method:'GET'}, null);
                    return rsp;
                } else return {success:false}
            };
            checkData().then(r=>{
                this.formDataStore.setData(r?.data?.formFormat);
                this.formDataStore.rawData=r?.data||{};
                this.loadForm=true;
                setTimeout(()=> this.navigationStore.toggle(false),300);
            })
        },
        data(){
            const route=useRoute();
            return { loadForm:false, plib:new Plib(), navigationStore:useNavigationStore(), formDataStore:useFormDataStore(), id: route.params.id||undefined }
        },
        methods:{
            async submitForm(formData){
                formData.typeKey='op-doc-transfer';
                const chk=this.plib.checkForm('.form-item'); if(!chk.valid){ this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız'); return; }
                const env=new FormData(); env.append('data', JSON.stringify(formData));
                for(let k in formData.files){
                    const fi=formData.files[k];
                    if(fi && fi.reference) env.append(k, JSON.stringify(fi.reference)); else if(fi && fi.file) env.append(k, fi.file);
                }
                const rsp=await this.plib.request({url:'/api/v1/document'+(this.id?'/'+this.id:''), method: this.id?'PUT':'POST'}, null, env);
                this.plib.toast(this.Swal, rsp.success?'success':'error', rsp.msg||'Kaydedildi', ()=>{ if(rsp.success) this.$router.push({name:'TransferList'})});
            },
            async approve(){ const fd=new FormData(); fd.append('id', this.id); fd.append('op_key','doc_trans_transfer_approved'); fd.append('note','Admin onayladı'); const rsp=await this.plib.request({url:'/api/v1/trans/set-status', method:'POST'}, null, fd); this.plib.toast(this.Swal, rsp.success?'success':'error', 'Transfer onaylandı'); },
            async reject(){ const fd=new FormData(); fd.append('id', this.id); fd.append('op_key','doc_trans_transfer_rejected'); fd.append('note','Admin reddetti'); const rsp=await this.plib.request({url:'/api/v1/trans/set-status', method:'POST'}, null, fd); this.plib.toast(this.Swal, rsp.success?'success':'error', 'Transfer reddedildi'); }
        }
    }
</script>
<template>
    <div style="padding-bottom:80px">
        <div class="card mb-4" v-if="id">
            <div class="card-body d-flex gap-2">
                <button class="btn btn-success" @click="approve"><i class="ki-outline ki-check"></i> Transferi Onayla</button>
                <button class="btn btn-danger" @click="reject"><i class="ki-outline ki-cross"></i> Reddet</button>
                <span class="text-muted fs-7 ms-3">Dökümanlar sekmesinden dosyaları Onay/Red yapabilirsiniz (transactions → document_files)</span>
            </div>
        </div>
        <Form formtypes="op-doc-transfer-form" v-if="loadForm" savebtntitle="Kaydet" :savecallback="submitForm" />
    </div>
</template>
