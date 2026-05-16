- cp .env.example .env

- env içinde APP_URL=http://localhost:8000 olmalı
- env içinde postgresl için veri tabanı bilgileri girilmeli

    DB_CONNECTION=pgsql
    DB_HOST=127.0.0.1
    DB_PORT=5431
    DB_USERNAME=testdb
    DB_DATABASE=b2x
    DB_PASSWORD=b2x
- composer install && npm install çalışmalı (node_modules git reposuna dahil çünkü buildi localde alıyoruz bir sebepten ötürü build alsakta canlıda node_modules olmadan çalışmadı)
- ./prjBuildLocal direk çalışmalı bash dosyası bu sistemi herşeyiyle veri tabanına falan kuruyor
- php artisan serve (backend development ortamı için)
- npm run dev (frontend development ortamı için)
- npm run build (sistemi build alınca gite gönderiyoruz sunucu bunu direk çekip yayınlıyor  build olmazsa canlıya çekince gözükmez sonrasında alırız yine tabi localde ama bilgin olsun )

- Admin kullanıcımız var default tan gelen tam yetkili
 User : kadir@kontent.com.tr
 Pass : Kadir412.

 * bunun sms kodu hep 111111 gelir direk gir geç