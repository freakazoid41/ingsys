<style scoped>
* {
    box-sizing: border-box;
}

.fab-btns-wrapper {
    position: fixed;
    bottom: 1rem;
    left: 50% !important;
    transform: translateX(-50%);
    width: 600px;
    display: flex;
    justify-content: center;
    align-items: flex-end;
    flex-wrap: nowrap;
    flex-direction: row;
    z-index: 9999;
}

.fab-btns-wrapper .card {
    /* background-color:white; */
    /* border: 1px solid gray; */
    /* border-radius:10px !important; */
}

.fab-btns-wrapper .card-body {
    padding: 0px;
}

.fab-btns-wrapper .btn {
    min-width: 150px;
    margin: 10px !important;
    background-color: #154b91 !important;
    color: #ffffff;
    display: flex;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    font-size: 1.3rem;
    border-color: #154b91;
    width: auto;
}

.fab-btns-wrapper .btn:hover {
    background-color: #fff !important;
    color: #154b91 !important;
}

.fab-btns-wrapper .btn-cancel {
    background-color: #fff !important;
    color: #154b91 !important;
    border: 1.5px solid #154b91 !important;
}

.fab-btns-wrapper .btn-cancel:hover {
    background-color: #154b91 !important;
    color: #fff !important;
}

/* Planlama front panel: keep the save bar floating like the admin panel */
.fab-btns-wrapper.fab-front {
    width: 600px;
    max-width: calc(100vw - 32px);
    margin: 0 !important;
    padding: 12px 16px;
    gap: 10px;
    justify-content: center;
    align-items: center;
    background: #fff;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border: 1px solid #ffe4cc;
    border-top: 3px solid #FF5A1F;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(255, 90, 31, .08);
    z-index: 9999;
    transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.fab-btns-wrapper.fab-front:hover,
.fab-btns-wrapper.fab-front.is-bar-hover {
    background: #fff7ed !important;
    border-color: #FF5A1F !important;
    box-shadow: 0 14px 34px rgba(255, 90, 31, .20) !important;
    transform: translate(-50%, -2px) !important;
}

.fab-btns-wrapper.fab-front .btn {
    min-width: 0;
    margin: 0 !important;
    padding: 10px 24px;
    border-radius: 12px;
    border: 1px solid #FF5A1F !important;
    background: linear-gradient(135deg, #FF5A1F 0%, #ff7a3a 100%) !important;
    color: #fff !important;
    font-size: 14px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 6px 16px rgba(255, 90, 31, .25) !important;
    cursor: pointer;
    transition: all .18s ease;
}

.fab-btns-wrapper.fab-front .btn i {
    position: static;
    transform: none;
    font-size: 18px;
    padding: 0;
}

.fab-btns-wrapper.fab-front .btn:hover,
.fab-btns-wrapper.fab-front .btn.is-btn-hover {
    background: linear-gradient(135deg, #d63d00 0%, #f55a12 100%) !important;
    border-color: #d63d00 !important;
    color: #fff !important;
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 12px 28px rgba(255, 90, 31, .45), 0 0 0 4px rgba(255, 90, 31, .16) !important;
}

.fab-btns-wrapper.fab-front .btn-cancel {
    background: #fff !important;
    border: 1.5px solid #e2e8f0 !important;
    color: #334155 !important;
    box-shadow: none !important;
}

.fab-btns-wrapper.fab-front .btn-cancel:hover,
.fab-btns-wrapper.fab-front .btn-cancel.is-btn-hover {
    background: linear-gradient(135deg, #FF5A1F 0%, #ff7a3a 100%) !important;
    border-color: #FF5A1F !important;
    color: #fff !important;
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 10px 24px rgba(255, 90, 31, .38), 0 0 0 4px rgba(255, 90, 31, .14) !important;
}

/* center the floating save bar over the main content column (210px sidebar) */
@media (min-width: 993px) {
    .fab-btns-wrapper.fab-front {
        left: calc(50% + 120px) !important;
    }
}

@media (max-width: 992px) {
    .fab-btns-wrapper.fab-front {
        left: 50% !important;
    }
}

/* busy state: buttons hide, loading pill shows — nothing clickable mid-save */
.fab-btns-wrapper .fab-loading {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 800;
    color: #334155;
    padding: 10px 24px;
    white-space: nowrap;
}
.fab-btns-wrapper .fab-spinner {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    border-radius: 50%;
    border: 2.5px solid #cbd5e1;
    border-top-color: #154b91;
    animation: fab-spin .7s linear infinite;
}
.fab-btns-wrapper.fab-front .fab-loading {
    color: #9a3412;
}
.fab-btns-wrapper.fab-front .fab-spinner {
    border-color: #fed7aa;
    border-top-color: #FF5A1F;
}
.fab-btns-wrapper.is-busy {
    cursor: wait;
}
@keyframes fab-spin {
    to { transform: rotate(360deg); }
}

@media(max-width:991px) {
    .fab-btns-wrapper{
        width: 100%;
        justify-content: center;
        margin: 0;
    }
    .fab-btns-wrapper .btn {
        width: auto;
        min-width: 120px;
        font-size: .9rem;
        padding: .5rem .75rem;
    }
    .fab-btns-wrapper .btn i{
        position: static;
        transform: none;
        padding: unset;
    }
}

.fab-wrapper {
    position: fixed;
    bottom: 3rem;
    right: 3rem;
}

.fab-checkbox {
    display: none;
}

.fab {
    position: absolute;
    bottom: -1rem;
    right: -1rem;
    width: 4rem;
    height: 4rem;
    background: blue;
    border-radius: 50%;
    background: #126ee2;
    box-shadow: 0px 5px 20px #81a4f1;
    transition: all 0.3s ease;
    z-index: 1;
    border-bottom-right-radius: 6px;
    border: 1px solid #0c50a7;
}

.fab:before {
    content: "";
    position: absolute;
    width: 100%;
    height: 100%;
    left: 0;
    top: 0;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.1);
}

.fab-checkbox:checked~.fab:before {
    width: 90%;
    height: 90%;
    left: 5%;
    top: 5%;
    background-color: rgba(255, 255, 255, 0.2);
}

.fab:hover {
    cursor: pointer !important;
    background: #2c87e8;
    box-shadow: 0px 5px 20px 5px #81a4f1;
}

.fab-dots {
    position: absolute;
    height: 8px;
    width: 8px;
    background-color: white;
    border-radius: 50%;
    top: 50%;
    transform: translateX(0%) translateY(-50%) rotate(0deg);
    opacity: 1;
    animation: blink 3s ease infinite;
    transition: all 0.3s ease;
}

.fab-dots-1 {
    left: 15px;
    animation-delay: 0s;
}

.fab-dots-2 {
    left: 50%;
    transform: translateX(-50%) translateY(-50%);
    animation-delay: 0.4s;
}

.fab-dots-3 {
    right: 15px;
    animation-delay: 0.8s;
}

.fab-checkbox:checked~.fab .fab-dots {
    height: 6px;
}

.fab .fab-dots-2 {
    transform: translateX(-50%) translateY(-50%) rotate(0deg);
}

.fab-checkbox:checked~.fab .fab-dots-1 {
    width: 32px;
    border-radius: 10px;
    left: 50%;
    transform: translateX(-50%) translateY(-50%) rotate(45deg);
}

.fab-checkbox:checked~.fab .fab-dots-3 {
    width: 32px;
    border-radius: 10px;
    right: 50%;
    transform: translateX(50%) translateY(-50%) rotate(-45deg);
}

@keyframes blink {
    50% {
        opacity: 0.25;
    }
}

.fab-checkbox:checked~.fab .fab-dots {
    animation: none;
}

.fab-wheel {
    position: absolute;
    bottom: 0;
    right: 0;

    width: 11rem;
    height: 11rem;
    transition: all 0.3s ease;
    transform-origin: bottom right;
    transform: scale(0);
}

.fab-checkbox:checked~.fab-wheel {
    transform: scale(1);
}

.fab-action {
    position: absolute;
    background: #0f1941;
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: White;
    box-shadow: 0 0.1rem 1rem rgba(24, 66, 154, 0.82);
    transition: all 1s ease;

    opacity: 0;
}

.fab-checkbox:checked~.fab-wheel .fab-action {
    opacity: 1;
}

.fab-action:hover {
    background-color: #f16100;
}

.fab-wheel .fab-action-1 {
    right: -1rem;
    top: 0;
}

.fab-wheel .fab-action-2 {
    right: 3.4rem;
    top: 0.5rem;
}

.fab-wheel .fab-action-3 {
    left: 0.5rem;
    bottom: 3.4rem;
}

.fab-wheel .fab-action-4 {
    left: 0;
    bottom: -1rem;
}
</style>
<script>
export default {
    props: {
        fabType: {
            type: String
        },
        btntype: {
            type: String
        },
        callback: {
            type: Function
        },
        rejectcallback: {
            type: Function
        },
        acceptcallback: {
            type: Function
        },
        savebtntitle:{
            type: String
        },
        cancelcallback: {
            type: Function
        },
    },
    data() {

        return {
            status: 'await',
            type: this.btntype,
            fab: this.fabType == undefined ? 'bar' : this.fabType,
            barHover: false,
            btnHover: ''
        }
    },
    computed: {
        isPlanlamaFront() {
            return typeof this.$route?.path === 'string' && this.$route.path.startsWith('/planlamapanel');
        },
        isFrontShell() {
            // Front shells (tedarik/ihale/planlama) fake-scroll via a CSS
            // transform on the content wrapper. A transformed ancestor turns
            // `position: fixed` into container-relative positioning, so the
            // save bar must live on <body> to actually float.
            const p = typeof this.$route?.path === 'string' ? this.$route.path : '';
            return p.startsWith('/planlamapanel') || p.startsWith('/ihalepanel') || p.startsWith('/tedarikpanel');
        },
        isBusy() {
            return this.status === 'loading';
        }
    },
    mounted() {
        if (this.fab === undefined) this.fab = 'bar';
        if (this.type === undefined) this.type = 'saveBtn';
    },
    methods: {
        async execute() {
            // ... do something here
            /*if (this.callback) {
                document.querySelector('.fab-wrapper').hidden = true;
                console.log(this.callback())
            }*/

            if (this.callback && this.status != 'loading') {
                this.status = 'loading';
                try {
                    //document.querySelector('.fab-wrapper').hidden = true;
                    await this.callback();
                    // #fabCheckbox yalnizca 'leftIcon' duzeninde var; 'bar' duzeninde
                    // kapatilacak bir menu yok, o yuzden optional chaining.
                    if (this.type === 'options') {
                        document.getElementById('fabCheckbox')?.click();
                    }
                } finally {
                    // status 'loading'de kalirsa bu ve diger iki handler bir daha hic
                    // calismaz (hepsi status != 'loading' ile korunuyor) -> form kilitlenir.
                    setTimeout(() => {
                        this.status = 'await';
                    }, 500);
                }
            }
        },
        async executeReject() {

            // ... do something here
            /*if (this.callback) {
                document.querySelector('.fab-wrapper').hidden = true;
                console.log(this.callback())
            }*/

            if (this.rejectcallback && this.status != 'loading') {
                this.status = 'loading';
                try {
                    await this.rejectcallback('reject');
                    if (this.type === 'options') {
                        document.getElementById('fabCheckbox')?.click();
                    }
                } finally {
                    setTimeout(() => {
                        this.status = 'await';
                    }, 500);
                }
            }
        },
        executeCancel() {
            if (this.cancelcallback && this.status != 'loading') this.cancelcallback();
        },
        async executeAccept() {
            // ... do something here
            /*if (this.callback) {
                document.querySelector('.fab-wrapper').hidden = true;
                console.log(this.callback())
            }*/

            if (this.acceptcallback && this.status != 'loading') {
                this.status = 'loading';
                try {
                    await this.acceptcallback('accept');
                    if (this.type === 'options') {
                        document.getElementById('fabCheckbox')?.click();
                    }
                } finally {
                    setTimeout(() => {
                        this.status = 'await';
                    }, 500);
                }
            }
        }
    }
}
</script>
<template>

    <Teleport to="body" :disabled="!isFrontShell">
        <div class="row fab-btns-wrapper" :class="{ 'fab-front': isPlanlamaFront, 'is-bar-hover': barHover, 'is-busy': isBusy }" @mouseenter="barHover = true" @mouseleave="barHover = false; btnHover = ''" v-if="this.fab == 'bar'">
            <template v-if="!isBusy">
                <button v-if="cancelcallback" class="btn btn-cancel" :class="{ 'is-btn-hover': btnHover === 'cancel' }" @mouseenter="btnHover = 'cancel'" @mouseleave="btnHover = ''" @click="executeCancel"><i class="ki-outline ki-arrow-left fs-2x"></i> İptal</button>
                <button class="btn btn-danger" :class="{ 'is-btn-hover': btnHover === 'save' }" @mouseenter="btnHover = 'save'" @mouseleave="btnHover = ''" @click="execute"><i class="ki-outline ki-archive-tick fs-2x"></i> {{ savebtntitle }}</button>
                <button class="btn btn-success" :class="{ 'is-btn-hover': btnHover === 'accept' }" v-if="type === 'options'" @mouseenter="btnHover = 'accept'" @mouseleave="btnHover = ''" @click="executeAccept"><i
                        class="ki-outline ki-double-check fs-2x"></i> Bütün Belgeleri Onayla</button>
                <button class="btn btn-warning" :class="{ 'is-btn-hover': btnHover === 'reject' }" v-if="type === 'options'" @mouseenter="btnHover = 'reject'" @mouseleave="btnHover = ''" @click="executeReject"><i
                        class="ki-outline ki-cross fs-2x"></i> Bütün Belgeleri Reddet</button>
            </template>
            <div v-else class="fab-loading"><span class="fab-spinner"></span> Kaydediliyor...</div>
        </div>
    </Teleport>
    <div class="fab-wrapper" v-if="this.fab == 'leftIcon'">
        <input id="fabCheckbox" v-if="type === 'options'" type="checkbox" class="fab-checkbox" />

        <label class="fab d-flex justify-content-center align-items-center" v-if="type === 'saveBtn'" for="fabCheckbox"
            @click="execute">
            <span class="ki-archive-tick ki-outline fs-1" v-if="status == 'await'"></span>
            <span class="spinner-grow spinner-grow-sm" v-if="status == 'loading'" aria-hidden="true"></span> <span
                class="visually-hidden" role="status">Loading...</span>

        </label>

        <label class="fab" v-if="type === 'options'" for="fabCheckbox">
            <span class="fab-dots fab-dots-1"></span>
            <span class="fab-dots fab-dots-2"></span>
            <span class="fab-dots fab-dots-3"></span>
        </label>
        <div class="fab-wheel">
            <a class="fab-action fab-action-1" @click="execute">
                <i class="ki-outline ki-archive-tick fs-2x"></i>
            </a>
            <a class="fab-action fab-action-2">
                <i class="ki-outline ki-double-check fs-3x" @click="executeAccept"></i>
            </a>
            <a class="fab-action fab-action-3">
                <i class="ki-outline ki-cross fs-3x" @click="executeReject"></i>
            </a>
        </div>
    </div>
</template>
