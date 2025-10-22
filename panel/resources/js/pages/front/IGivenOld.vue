<script>
import { useNavigationStore } from '@/stores/navigation';
import Plib from '@/lib/pickle';
import FrontLib from '@/lib/frontlib';
import { wTrans } from 'laravel-vue-i18n';


export default {
    components: {

    },
    setup() {
        // expose to template and other options API hooks
        return {
            useNavigationStore,
            FrontLib,
            Plib,
            wTrans
        }
    },
    mounted() {
        this.inventories = this.navigationStore.facility.inventories;
    },
    data() {
        return {
            inventories: {},
            plib: new Plib(),
            frontLib: new FrontLib(),
            navigationStore: useNavigationStore(),
        }
    },
    methods: {
        selectAll(e) {
            document.querySelectorAll('.inv-item').forEach(el => {
                el.checked = e.target.checked;
            });
        },
        async enterFacility() {
            //get selected inventories
            const items = document.querySelectorAll('.inv-item:checked');
            let invItems = {};

            items.forEach((el, i) => {
                const key = "givengroup**" + ((new Date()).getTime() + i) + "-" + i;
                invItems["inventory**" + key] = el.dataset.title;
                invItems["description**" + key] = el.dataset.code;
            });

            invItems['inventory-taken'] = true;

            this.navigationStore.toggle(true);
            //save selections
            const envelope = new FormData();
            envelope.append('data', JSON.stringify({
                "dynamicF": {
                    [this.navigationStore.currentUser.conn]: {
                        "entities": invItems,
                        "tag": "op-doc-visit-form"
                    }
                }
            }));


            const response = await this.plib.request({
                url: '/api/v1/yeniziyaret/' + this.navigationStore.currentUser.qnid,
                method: 'PUT',
            }, null, envelope);

            this.navigationStore.toggle(false);

            this.frontLib.popup({
                text: {
                    class: 'login-popup',
                    head: '',
                    body: `
                            <div class="view">
                                <div class="icon">
                                <img src='/front/assets/img/successCheckGreen.svg'>
                                <p>`+ this.wTrans("inv.canenter").value + `</p>
                                </div>
                                <div class="info-box">
                                <div class="info-box-head"><img src='/front/assets/img/purpleCirc.svg'>`+ this.wTrans("inv.canenter.greet").value + `</div>
                                <p>`+ this.wTrans("inv.canenter.warn").value + `</p>
                                </div>
                            </div>
                        `,

                },
                items: 'kvkk-choice'
            });
            document.querySelector("button.close").remove();
        }
    }
}
</script>
<template>
    <div class="main-body iwarn-body">
        <div class="main-rew-head">
            <h3>{{ $t('invshow.header') }}</h3>

        </div>
        <div class="check-list mt-4" id="elist">
            <div class="form-check" v-for="value, key in inventories">
                <div class="checkbox-theme md">
                    <input type="checkbox" :data-code="value.code" :data-title="value.title" class="inv-item"
                        :id="'inv' + key" :name="'inv' + key" :value="value.qnid" />
                    <label :for="'inv' + key">
                        <svg viewBox="0,0,50,50">
                            <path d="M5 30 L 20 45 L 45 5"></path>
                        </svg>
                    </label>
                </div>
                <label class="form-check-label text-dark" :for="'inv' + key">{{ this.navigationStore.currentLanguge !=
                    'tr' ? value['title--lng--' + this.navigationStore.currentLanguge] : value.title }}</label>
            </div>
        </div>
    </div>
    <div class="main-footer iwarn-footer">
        <div class="form-check mt-auto mb-3">
            <div class="checkbox-theme md ck-inv">
                <input type="checkbox" id="gurultu5" name="" @click="selectAll($event)">
                <label for="gurultu5">
                    <svg viewBox="0,0,50,50">
                        <path d="M5 30 L 20 45 L 45 5"></path>
                    </svg>
                </label>
            </div>
            <label class="form-check-label" for="gurultu5"><b>{{ $t('invshow.check') }}</b></label>
        </div>
        <div class="d-flex w-100 gap-3">
            <div class="button-theme outline back-custom">
                <img src="/front/assets/img/leftArrow.svg" alt="">
            </div>
            <button class="button-theme resume-button w-100" @click="enterFacility">
                {{ $t('inv.button') }} <img src="/front/assets/img/rightArrow.png" alt="">
            </button>
        </div>
    </div>
</template>