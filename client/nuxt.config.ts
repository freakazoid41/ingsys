// https://nuxt.com/docs/api/configuration/nuxt-config


export default defineNuxtConfig({
  
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },
  components: [
    {
      path: '~/components',
      pathPrefix: false,
    },
  ],
  app: {
    head: {
      link: [
        { rel: 'stylesheet', href: '/css/theme.css' },
      ],
      script: [{
        src : '/js/index.js',
        type : 'module',
        tagPosition: 'bodyClose',
      },{
        src : '/js/vendor.js',
        type : 'module',
        tagPosition: 'bodyClose',
      },{
        src : '/js/theme.js',
        type : 'module',
        tagPosition: 'bodyClose',
      },]
    },
  },
  
})
