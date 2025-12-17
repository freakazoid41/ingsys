<script>
import { useNavigationStore } from '@/stores/navigation';
import Plib from '@/lib/pickle';
import FrontLib from '@/lib/frontlib';
import IMask from 'imask';
import { wTrans } from 'laravel-vue-i18n';
/*import dayjs from "dayjs";
import tr from "dayjs/locale/tr";
import customParseFormat from "dayjs/plugin/customParseFormat";*/

export default {
    components: {

    },
    setup() {
        /*dayjs.locale(tr);
        dayjs.extend(customParseFormat);*/
        // expose to template and other options API hooks
        return {
            useNavigationStore,
            FrontLib,
            Plib,
            IMask,
            wTrans,
            //dayjs
        }
    },
    data() {
        return {
            pageStatus: 'list',
            activeLang: useNavigationStore().currentLanguage,
            plib: new Plib(),
            frontLib: new FrontLib(),
            navigationStore: useNavigationStore(),
            //dayjs           : dayjs
        }
    },
    mounted() {
        
        if (document.querySelector('[name="phonedata"]') !== null){
            document.getElementById('phone').value = document.querySelector('[name="phonedata"]').value;
            document.getElementById('phone2').value = document.querySelector('[name="phonedata"]').value.replace('+90','');
        } 

        IMask(document.getElementById('phone'), {
            mask: '+{9\\0} (500) 000 00 00'
        });

        IMask(document.getElementById('phone2'), {
            mask: '{\\0} (500) 000 00 00'
        });

        

        document.getElementById('email').addEventListener('input', e => {
            e.target.value = this.convertEmail(e.target.value);
        });

        document.querySelector('.kvkk-choice').addEventListener('click', (e) => {
            this.frontLib.popup({
                text: {
                    class: 'kvkk',
                    head: this.wTrans('main.kvkk.head').value,
                    body: this.wTrans('main.kvkk').value,
                    button: {
                        name: this.wTrans('main.kvkk.okey').value,
                        proccess: () => {
                            document.getElementById('kvkk').checked = true;
                            document.querySelector("button.close").click();
                        }
                    }
                }
            });
            document.querySelector("button.close").style.display = 'none';
        });

        /*this.navigationStore.toggle(true);
        setTimeout(() => {
            this.navigationStore.toggle(false);
        }, 300);*/

    },

    methods: {
        convertEmail(string) {
            string = string.replace(/(([İIŞĞÜÇÖışöçğ]))+/g, '').toLowerCase().replace(/\//g, '').trim().replace(/[^a-zA-Z0-9_/-@.]/g, '').replace(/[&\/\\#,+()$~%'":;*?<>{}]/g, '');
            string = string.replace('..', '.');
            return string;
        },

        async saveDocument() {
            const rsp = this.plib.checkForm('.form-control');
            if (!document.getElementById('kvkk').checked && rsp.valid) {
                document.querySelector('.kvkk-choice').click();
                return false;
            }
            //get item values
            this.navigationStore.toggle(true);
            
            if (rsp.valid) {

                rsp.obj.phone = document.querySelector('input[name="phone-'+this.navigationStore.currentLanguage+'"]').value.trim();
                

                const date = new Date();
                const envelope = new FormData();
                envelope.append('data', JSON.stringify({
                    //"typeKey"  : "op-doc-visit",
                    "main_grp_code": this.navigationStore.facility.qnid,
                    "dynamicF": {
                        ["op-doc-visit-form**new-" + date.getTime()]: {
                            "entities": {
                                "name": rsp.obj.name,
                                "surname": '-',
                                "phone": rsp.obj.phone,
                                "email": rsp.obj.email,
                                "kvkk": document.getElementById('kvkk').checked,
                                "facility": this.navigationStore.facility.title,
                                "facility_id": this.navigationStore.facility.qnid,
                                "entered_at": this.plib.getConvertedDate(new Date())
                            },
                            "tag": "op-doc-visit-form"
                        }
                    }
                }));
                

                const response = await this.plib.request({
                    url: '/api/v1/yeniziyaret',
                    method: 'POST',
                }, null, envelope);

                setTimeout(() => {
                    this.navigationStore.toggle(false);
                    if (response.success == true) {
                        //set result to session
                        rsp.obj.qnid = response.data.data.qnid;
                        rsp.obj.conn = 'op-doc-visit-form**' + response.data.connEntries['op-doc-visit-form'];
                        this.navigationStore.currentUser = rsp.obj;

                        response.data.data = { ...response.data.data, ...rsp.obj };
                        this.frontLib.popup({
                            text: {
                                class: 'login',
                                head: this.wTrans('main.enter.dear').value + ' ' + response.data.data.name + ',',
                                body: this.wTrans('main.enter.info').value,
                                button: {
                                    name: this.wTrans('main.enter.button').value + ` <img src='/front/assets/img/rightArrowDark.png' class='arrow'>`,
                                    proccess: () => {
                                        document.querySelector("button.close").click();
                                    }
                                }
                            },
                            items: 'kvkk-choice'
                        });
                        this.$router.push({ name: 'VShow', params: { id: this.navigationStore.facility.qr } });
                        //this.$router.push({ name: 'IGiven', params: { id: this.navigationStore.facility.qr } });
                    } else {
                        this.plib.toast(this.Swal, 'error', 'Arıza Oluştu..', () => { });
                    }

                }, 300);
                return true;
            } else {
                this.navigationStore.toggle(false);
                //this.plib.toast(this.Swal,'info','Eksik Alanları Doldurmalısınız..',() => {});

                return false;
            }

        }
    }
}

</script>
<template>
    <div class="main-body">
        <div class="main-body-head">

            <h1 v-if="this.navigationStore.currentLanguage == 'tr'">{{ this.navigationStore.facility?.title }}’e {{ $t('main.greet') }}</h1>
            <h1 v-if="this.navigationStore.currentLanguage != 'tr'">{{ $t('main.greet') }} {{ this.navigationStore.facility?.title }}</h1>
            <p>{{ $t('main.desc') }}</p>
        </div>
        <div class="form-main">
            <div class="form-group theme-group">
                <label for="">{{ $t('main.form.namesurname') }}</label>
                <input type="text" name="name" required class="form-control" :placeholder="$t('main.form.enterinfo')">
            </div>
            <div class="form-group theme-group" v-show="this.navigationStore.currentLanguage != 'en'">
                <label for="">{{ $t('main.form.phone') }}</label>
                <input type="text" readonly name="phone-tr" required id="phone2" class="form-control"
                    placeholder="+90 (5__) ___ __ __">
            </div>
            <div class="form-group theme-group" v-show="this.navigationStore.currentLanguage == 'en'">
                <label for="">{{ $t('main.form.phone') }}</label>
                <input type="text" readonly name="phone-en" required id="phone" class="form-control"
                    placeholder="0 (5__) ___ __ __">
            </div>
            <div class="form-group theme-group mb-3">
                <label for="">{{ $t('main.form.email') }}</label>
                <input type="text"  name="email"  id="email" class="form-control"
                    :placeholder="$t('main.form.enterinfo')">
            </div>
            <div class="form-check kvkk-choice popupshow">
                <div class="checkbox-theme">
                    <input type="checkbox" id="kvkk" name="" />
                    <label for="kvkk">
                        <svg viewBox="0,0,50,50">
                            <path d="M5 30 L 20 45 L 45 5"></path>
                        </svg>
                    </label>
                </div>
                <label class="form-check-label" for="" v-html="$t('main.form.kvkk')"></label>
            </div>
        </div>
    </div>
    <div class="main-footer">
        <button class="button-theme" @click="saveDocument">{{ $t('main.enter') }} <img
                src="/front/assets/img/rightArrow.png" class="icon"></button>
    </div>
</template>