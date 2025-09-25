<script>
  import {
    useNavigationStore
  } from '@/stores/navigation';
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
      
      this.currentIndex = this.navigationStore?.facility?.questionKeys?.indexOf(String(this.$route.params.quiz)) ?? 0;
    },
    data() {

      return {
        questionKey: this.$route.params.quiz,
        currentIndex: 0,
        plib: new Plib(),
        frontLib: new FrontLib(),
        navigationStore: useNavigationStore(),
      }
    },
    methods: {
      selectOption(selectedOption) {
        this.navigationStore.currentOpt[this.$route.params.quiz] = selectedOption;

        const questionRef = this.navigationStore?.facility?.questions[this.$route.params.quiz];

        this.navigationStore.testLog[this.$route.params.quiz] = {
          question       : questionRef.question,
          trueOption     : questionRef.rightAnswer,
          trueDesc       : questionRef.answers[questionRef.rightAnswer],
          selectedOption : selectedOption,
          selectedDesc   : questionRef.answers[selectedOption]
        }
      },
      stepChange(type = 'next') {
        if (type == 'next') {
          this.currentIndex++;
        } else {
          this.currentIndex--;
        }

        if (this.currentIndex < 0) this.currentIndex = 0;
        if (this.currentIndex >= this.navigationStore?.facility?.questionKeys.length) this.currentIndex = this.navigationStore?.facility?.questionKeys?.length - 1;

        this.$router.push({
          name: 'Quiz',
          params: {
            id: this.navigationStore.facility.qr,
            quiz: this.navigationStore?.facility?.questionKeys?.[this.currentIndex]
          }
        });
      },
      async checkOptions() {
        let trueCount = 0
        for(let key in this.navigationStore?.facility?.questions){
          if(this.navigationStore?.facility?.questions[key].rightAnswer == this.navigationStore.currentOpt[key]) trueCount++;
        }


        if(trueCount >= parseInt(this.navigationStore.facility.mustKnow)){
          this.navigationStore.isPassed = true;

          this.navigationStore.toggle(true);
          //save selections
          const envelope  = new FormData();
          envelope.append('data',JSON.stringify({
              //"typeKey"  : "op-doc-visit",
              "dynamicF" : {
                  [this.navigationStore.currentUser.conn] : {
                      "entities":{
                          "test-result"  : this.navigationStore?.facility?.questionKeys.length+' Toplam Soru'+ ' / '+trueCount+' Doğru / '+this.navigationStore.facility.mustKnow+ ' Bilinmesi Gereken',
                          "test-answers" : JSON.stringify(this.navigationStore.testLog)
                      },
                      "tag":"op-doc-visit-form"
                  }
              }
          }));
          

          const response = await this.plib.request({
              url      : '/api/v1/yeniziyaret/'+this.navigationStore.currentUser.qnid,
              method   : 'PUT',
          },null,envelope);

          this.navigationStore.toggle(false);

          

          this.frontLib.popup({
            text: {
              class : 'success-popup',
              head: 'Tebrikler!',
              body: `<h4>Testi başarıyla tamamladınız. Bir sonraki adıma geçebilirsiniz.</h4> <div class="view"><div class="icon"><img src='/front/assets/img/successCheck.svg'></div></div>`,
              button: {
                name: `Sonraki adıma geç <img src='/front/assets/img/rightArrowDark.png' class='arrow'>`,
                proccess: () => {
                    document.querySelector("button.close").click();
                    this.$router.push({ name: 'IShow' , params: { id  : this.navigationStore.facility.qr }});
                }
              }
            },
            items: 'kvkk-choice'
          });
          

        }else{
          this.navigationStore.isPassed = false;


          this.frontLib.popup({
            text: {
              class : 'error-popup',
              head: 'Hay Aksi!',
              body: `<h4>Soruları doğru yanıtlamadınız.<br> Videoyu tekrar izleyiniz.</h4> <div class="view"><div class="icon"><img src='/front/assets/img/successCheck.svg'></div></div>`,
              button: {
                name: `Videoyu tekrar izle <img src='/front/assets/img/rightArrowDark.png' class='arrow'>`,
                proccess: () => {
                    document.querySelector("button.close").click();
                    this.$router.push({ name: 'VShow' , params: { id  : this.navigationStore.facility.qr }});
                }
              }
            },
            items: 'kvkk-choice'
          })

        }


        document.querySelector("button.close").style.display = 'none';
      }
    }
  }

</script>
<template>
  <div class="main-body questions">
    <div class="progress-head">
      <div class="label">{{this.currentIndex+1}}/{{navigationStore?.facility?.questionKeys?.length}}</div>
      <div class="progress" :style="'--lenght: '+ navigationStore?.facility?.questionKeys?.length">
        <span  :class="{
          invalid : navigationStore.isPassed == false && navigationStore.currentOpt[key] != navigationStore?.facility?.questions?.[key].rightAnswer
        }" v-for="(value,key) in navigationStore?.facility?.questions"></span>

        
      </div>
    </div>
    <div class="questions-img">
      <img src="/front/assets/img/photo.webp" alt="">
    </div>
    <div class="questions-head">
      <h3>{{ navigationStore?.facility?.questions?.[questionKey]?.question }}</h3>
      <!-- <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, eiusmod tempor incididunt ut labore et dolore magna
          aliqua.</p> -->
    </div>
    <div class="questions-body">
      <div class="questions-reply" :class="{
          active : navigationStore.currentOpt[this.$route.params.quiz] == key,
          valid  : navigationStore.isPassed == false && navigationStore.currentOpt[this.$route.params.quiz] == navigationStore?.facility?.questions[this.$route.params.quiz].rightAnswer && key == navigationStore?.facility?.questions?.[this.$route.params.quiz].rightAnswer,
          invalid : navigationStore.isPassed == false && navigationStore.currentOpt[this.$route.params.quiz] != navigationStore?.facility?.questions[this.$route.params.quiz].rightAnswer && key == navigationStore.currentOpt[this.$route.params.quiz],
        }"
        @click="selectOption(key)" v-for="(value,key) in navigationStore?.facility?.questions?.[this.$route.params.quiz]?.answers">
        <input type="checkbox" id="reply-a">
        <label for="reply-a"></label>
        <h4>{{ key}}</h4>
        <p>{{ value }}</p>
      </div>

    </div>
  </div>
  <div class="main-footer twin-button">
    <button class="button-theme outline back-button" v-if="currentIndex != 0" @click="stepChange('prev')">{{ $t('test.prev') }}</button>
    <button class="button-theme resume-button" v-if="navigationStore?.facility?.questionKeys?.length-1 > currentIndex"
      :style="currentIndex == 0 ? 'width:100%;' : '' " @click="stepChange('next')">
      {{ $t('test.next') }} <img src="/front/assets/img/rightArrow.png" alt="">
    </button>
    <button class="button-theme resume-button" @click="checkOptions" v-if="navigationStore?.facility?.questionKeys?.length-1 == currentIndex">
      {{ $t('test.end') }} <img src="/front/assets/img/rightArrow.png" alt="">
    </button>
  </div>
</template>
