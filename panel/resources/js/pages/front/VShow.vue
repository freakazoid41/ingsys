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
            this.navigationStore.toggle(true);
            
            setTimeout(() => {
                this.navigationStore.toggle(false);
            }, 300);
        },  
        data() {
            return {
                canPass         : document.querySelector('[name="mustwatch"]') !== null,
                videoEnd        : false,
                startAt         : '',
                endAt           : '',
                plib            : new Plib(),
                frontLib        : new FrontLib(),
                navigationStore : useNavigationStore(),
                buttonShow      : true 
            }
        },
        methods: {
          async updateUserTiming(count = 0){
            const envelope  = new FormData();
            envelope.append('data',JSON.stringify({
                //"typeKey"  : "op-doc-visit",
                "dynamicF" : {
                    [this.navigationStore.currentUser.conn] : {
                        "entities":{
                            "video_start"  : this.startAt,
                            "video_second" : count,
                            "video_end"    : this.endAt,
                            "video_status" : this.videoEnd ? 'Tamamlandı' : 'Kısmen İzledi',
                            "shall_pass"   : this.videoEnd
                        },
                        "tag":"op-doc-visit-form"
                    }
                }
            }));
            

            const response = await this.plib.request({
                url      : '/api/v1/yeniziyaret/'+this.navigationStore.currentUser.qnid,
                method   : 'PUT',
            },null,envelope);
          },
          startVideo (){
            if(this.currentVideoElm !== undefined){
              if (this.currentVideoElm.requestFullscreen) {
                  this.currentVideoElm.requestFullscreen().then(() => {
                    /*video.currentTime = currentTime;
                    if (!isPaused) {
                      video.play();
                    }*/
                  });
                } else if (this.currentVideoElm.webkitRequestFullscreen) { // Safari
                  this.currentVideoElm.webkitRequestFullscreen().then(() => {
                    /*video.currentTime = currentTime;
                    if (!isPaused) {
                      video.play();
                    }*/
                  });
                } else if (this.currentVideoElm.msRequestFullscreen) { // IE/Edge
                  this.currentVideoElm.msRequestFullscreen().then(() => {
                    /*video.currentTime = currentTime;
                    if (!isPaused) {
                      video.play();
                    }*/
                  });
                }
              return true;
            }


            let count = 0;
            this.startAt = this.plib.getConvertedDate(new Date());
            const updateUserCount = setInterval(async () => {
              this.updateUserTiming(count);
              count++;
            }, 1000);

            

            const videoButton  = document.getElementById('playVideo');
            const video        = document.createElement('video');
            video.style.height = '100%';
            video.type         = 'video/mp4';
            //video.controls     = true;
            video.classList.add('video-element');


            document.onclick = () => {
              if (document.fullscreenElement === video) {
                  if(video.paused){
                    video.play();
                  }else{
                    video.pause();
                  }
                  // Add code to execute when entering fullscreen
              } 
            }

            //listen document visibilitiy for tab change
            document.onvisibilitychange = () => {
              if(document.visibilityState === 'visible'){
                video.play();
              }else{
                video.pause();
              }
            };

            const sourceElm = document.createElement('source');
            sourceElm.src   = '/order-file/'+this.navigationStore.facility.currentVideo;
            sourceElm.onerror      = () => {
              sourceElm.src = '/front/videos/default.mp4';
              videoButton.innerHTML = '';
              videoButton.appendChild(video);
              video.play();
              this.buttonShow = false;
            };

            video.appendChild(sourceElm);
            video.onplaying = () =>{
              setTimeout(() => {
                if (video.requestFullscreen) {
                  video.requestFullscreen();
                } else if (video.webkitRequestFullscreen) { // Safari
                  video.webkitRequestFullscreen();
                } else if (video.msRequestFullscreen) { // IE/Edge
                  video.msRequestFullscreen();
                }
              }, 10);
            };
            video.onended = () =>{
              this.videoEnd = true;
              this.buttonShow = true;
              this.endAt = this.getConvertedDate(new Date());

              setTimeout(() => {
                clearInterval(updateUserCount);
              }, 1000);

              if (document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement) {
                if (document.exitFullscreen) {
                  document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                  document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) { 
                  document.msExitFullscreen();
                }
              }

              delete this.currentVideoElm;
            } 

            videoButton.innerHTML = '';
            videoButton.appendChild(video);

            video.play();

            this.currentVideoElm = video;
          },
        }
    }
</script>
<template>
    <div class="main-body d-flex flex-column align-items-center">
      <button class="video-button playVideo mb-3 mt-3" id="playVideo" @click="startVideo">
        <img src="/front/assets/img/poster.webp"  alt="" class="poster">
        <img src="/front/assets/img/playButton.svg" class="play-icon" alt="">
      </button>
      <h3 v-if="videoEnd && !canPass" class="custom-h3">{{ $t('video.end.header') }}</h3>
      <p v-if="videoEnd && !canPass">{{ $t('video.end.subheader') }}</p>
      <ul v-if="videoEnd && !canPass">
        <li>{{ $t('video.end.test').replace('{x}',Object.keys(navigationStore.facility.questions).length) }}</li>
        <li>{{ $t('video.end.test1').replace('{x}',navigationStore.facility.mustKnow) }}</li>
        <li>{{ $t('video.end.testwarn') }}</li>
      </ul>
      <p class="text-muted" v-if="videoEnd && !canPass">{{ $t('video.end.greet') }}</p>



      <h3 v-if="canPass">{{ $t('video.end.repeat') }}</h3>
      <p v-if="canPass">{{ $t('video.end.repeatwarn') }}</p>
      <ul v-if="canPass">
         <li>{{ $t('video.end.test').replace('{x}',Object.keys(navigationStore.facility.questions).length) }}</li>
        <li>{{ $t('video.end.test1').replace('{x}',navigationStore.facility.mustKnow) }}</li>
      </ul>
      <p class="text-muted" v-if="canPass">{{ $t('video.end.greet') }}</p>

    </div>
    <div class="main-footer" v-if="!videoEnd && !canPass">
      <button class="button-theme outline playVideo" @click="startVideo" v-if="buttonShow">{{$t('video.start')}}</button>
    </div>
    <div class="main-footer twin-button" v-if="videoEnd || canPass">
      <button class="button-theme outline" @click="startVideo" v-if="buttonShow">{{ $t('video.repeat') }} <img src="/front/assets/img/return.svg" alt=""></button>
      <router-link class="button-theme playVideo" :to="{ name: 'Quiz'  , params: {id:this.navigationStore.facility.qr, quiz  : Object.keys(navigationStore.facility.questions)[0] }}" >
        {{$t('video.pass')}} <img src="/front/assets/img/contentMarketing.svg" alt="">
      </router-link>
    </div>
</template>