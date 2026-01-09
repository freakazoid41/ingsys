
<script>
    import { useNavigationStore } from '@/stores/navigation';
    import { useAuthStore } from '@/stores/auth';

    import PickleTable from 'pickletable';
    import 'pickletable/assets/style.css';
    import Plib from '@/lib/pickle';
    import { wTrans } from 'laravel-vue-i18n';
    import Swal from 'sweetalert2';
    
    
    import Simplebar from 'simplebar-vue';
    import 'simplebar-vue/dist/simplebar.min.css';


    export default {
        components: {
           Simplebar
        },
        setup() {
            
            // expose to template and other options API hooks
            return {
                useAuthStore,
                useNavigationStore,
                PickleTable,
                Plib,
                wTrans,
                Swal,
                
            }
        },
        mounted(){
            this.navigationStore.toggle(true);
            //this.buildTestTable();
            
            this.navigationStore.setBread([
                {
                    title : this.wTrans('menu.home'),
                    url   : '/panel',
                },
                {
                    title : 'Çalışma Alanı',
                    url   : '/panel/workspace',
                }
            ] ,this.wTrans('form.inventory.list'));
            /*this.navigationStore.setButtons([
              {
                icon : 'ph ph-download',
                onclick   : () => window.open('/export/documents/inventory'),
              },
              ...(this.authStore.data.type == 'admin' ? [{
                icon : 'ph ph-plus-circle',
                onclick   : () => this.$router.push({ name: 'InventoryForm' }),
              }] : [{}])
            ]);*/
            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 500);
        },  
        data() {
            const plib = new Plib();
            return {
                plib : plib,
                navigationStore : useNavigationStore(),
                authStore       : useAuthStore(),
                // Chat state
                newMessage: '',
                isLoading : false,
                messages: [
                    
                ],
            }
        },
        methods: {
            sendMessage() {
                this.isLoading = true;
                const div = document.querySelector('#chat-container .simplebar-content-wrapper');
                const text = (this.newMessage || '').trim();
                if (!text) {
                    this.isLoading = false;
                    return;
                }
                this.messages.push({ id: Date.now(), sender: 'me', text });
                this.newMessage = '';
                this.$nextTick(() => {
                    if (div) div.scrollTo(0, div.scrollHeight);
                });
                
                // Simulate a bot reply
                setTimeout(async () => {
                    this.messages.push({ id: Date.now() + 1, sender: 'bot', text: 'Düşünüyor :  <i class="fa fa-spinner fa-spin fs-5 text-body-emphasis"></i>' });

                    /**
                     * Defer execution of the provided callback until after Vue has completed DOM updates
                     * and the component has re-rendered for the current tick.
                     *
                     * Use this.$nextTick(...) when you must read or manipulate the DOM (or rely on child/component
                     * layout) immediately after changing reactive data so that you see the updated state.
                     *
                     * @param {Function} callback - Function to invoke after DOM updates complete.
                     * @returns {Promise<void>|undefined} If no callback is provided, returns a Promise that resolves
                     *                                   after the DOM update (Vue 2.6+ and Vue 3).
                     */
                    this.$nextTick(() => {
                        if (div) div.scrollTo(0, div.scrollHeight);
                    });


                    //here ask AI service
                    const   envelope  = new FormData();
                            envelope.append('question',text);
                        
                    const response = await this.plib.request({
                        url      : '/api/v1/ai/question',
                        method   : 'POST',
                    },null,envelope);
                    
                    if(response?.answer){
                        this.messages.pop(); //remove thinking message
                        this.messages.push({ id: Date.now() + 2, sender: 'bot', text: response.answer,meta:response.meta });
                        this.isLoading = false;
                        this.$nextTick(() => {
                            if (div) div.scrollTo(0, div.scrollHeight);
                        });
                    }
                }, 20);

                

            }
        }
    }

</script>
<template>
    <div class="card">
        <div class="card-body" style="height: 70vh !important;">
            <div class="row w-100 h-100 align-items-center justify-content-center" id="chat-container">
                <div class="col-8 text-center d-flex flex-column align-items-stretch" :class="{
                            'h-100' : messages.length > 0
                        }">
                    <div v-if="messages.length === 0">
                        <h3 class="mb-4">Çalışma Alanına Hoşgeldiniz</h3>
                        <p>Burada yapay zeka destekli araçlara erişebilir ve projelerinizi yönetebilirsiniz.</p>
                    </div>
                    <Simplebar :style="{
                        margin : messages.length > 0 ? '50px' : '0'
                    }">
                    <div ref="chatBody" class="chat-messages flex-grow-1 mb-2 p-2" :style="{
                        margin : messages.length > 0 ? '50px' : '0'
                    }">
                        <div v-for="msg in messages" :key="msg.id" :class="['chat-msg', msg.sender === 'me' ? 'me' : 'bot']">
                            <div class="chat-text text-left" v-html="msg.text"></div>
                            <div class="chat-text text-left" v-if="msg.sender === 'bot' && msg?.meta?.length > 0" >
                                {{ msg.meta.length > 0 ? 'Referanslar:' : '' }}
                                <ul>
                                    <li class="mt-5" v-for="meta in msg.meta">
                                        Karar Numarası : {{ meta?.id }} <br>
                                        Ülke : {{ meta?.origin }} <br> 
                                        Tarih : {{ meta?.date }}  <br> 
                                        Referans : <a :href="meta?.url" target="_blank" rel="noopener noreferrer">{{ meta?.url }}</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    </Simplebar>
                    <div class="input-group mt-auto chat-footer">
                        <input v-model="newMessage" @keydown.enter.prevent="sendMessage" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" type="text" placeholder="Mesaj yazın..." class="form-control" :class="{
                            'animated-grow' : messages.length > 0
                        }">
                        <span class="input-group-text selectable-icon" v-if="!isLoading" @click="sendMessage">
                            <i class="fa fa-search fs-5 text-body-emphasis"></i>
                        </span>
                        <span class="input-group-text selectable-icon" v-if="isLoading">
                            <i class="fa fa-spinner fa-spin fs-5 text-body-emphasis"></i>
                        </span>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</template>

