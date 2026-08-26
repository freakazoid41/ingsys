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
            fab: this.fabType == undefined ? 'bar' : this.fabType
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
            if (this.cancelcallback) this.cancelcallback();
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

    <div class="row fab-btns-wrapper" v-if="this.fab == 'bar'">
        <button v-if="cancelcallback" class="btn btn-cancel" @click="executeCancel"><i class="ki-outline ki-arrow-left fs-2x"></i> İptal</button>
        <button class="btn btn-danger" @click="execute"><i class="ki-outline ki-archive-tick fs-2x"></i> {{ savebtntitle }}</button>
        <button class="btn btn-success" v-if="type === 'options'" @click="executeAccept"><i
                class="ki-outline ki-double-check fs-2x"></i> Bütün Belgeleri Onayla</button>
        <button class="btn btn-warning" v-if="type === 'options'" @click="executeReject"><i
                class="ki-outline ki-cross fs-2x"></i> Bütün Belgeleri Reddet</button>
    </div>
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
