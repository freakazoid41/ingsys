okey slut.
we are preparing a system.
this system will be multiple specialities but we will make step by step.

system is Order Management System.
we have users (you know the user management system)
we have clients (documents and document_details).
we have orders (documents and document_details).
we have order items (documents and document_details). 
we have files (documents , document_files and document_details (you should know how to connect or ask me)).

process : 

1 - Order will come from some api resource (make dummy data from backend api endpoint.)

[front panel (new)]
2 - system client user will be enter system then order detail
3 - on order detail client will see some options and inputs
4 - client will select order willbe single or multiple transfer. (we will build detail fork later)
5 - client will see order detail items
6 - client will enter multirow description about something
7 - client will enter 'İmalatçı Firma Adı' one line textbox
8 - client will be enter 2 file (Malzeme Kabul Formu) and (Malzeme Vins-Miktar Kabul Formu) (dosyanun 40Mb'dan daha küçük, JPG , PNG veya PDF formatında olduğundan emin olunuz.)
9 - client will be send order to transfer.
    * Orders will be have statuses as transactions you can use "transactions" table for it
10 - system admin will enter to system and check entered files from "Dökümanlar" tab.
    * Files also have status transactions you can use "transactions" table for it for another type or something but enter status for "document_files" row
11 - files can be rejected or accepted if files are rejected clients needs to be enter again
12 - if files are okey admin can approve order transfer and change its status.

note : in current panel will be change for ruling that system with dashboards and process notes , fix , informations but only admins are will be enter there. front panel will be diffrent and whole process will be rule from there i will give design for front panel.


Check : 

1 - Order will come from sap to our database
  - now we have main order and its contents (ordered products).
2 - Client will enter order detail , choose he will transfer partially or at once
  - client will select partially or at once,
  - client will add files
  - client press send.
  - if client will choose partialy clon order will create with selected products
   * EBLN-xnumber as new order number
   * old order status will not change but it will remember some parts are sended
   
  - new clone order or if not partitioned main order will be lock and await admin file check
  - these files can be also be wiewed from order detail but any other info cannot be changed

3 - admin will check files
  - can reject only files => client will se it on order detail and order list and enter that
  - can reject and cancel whole order from order detail or order list
  - can accept order
  - order status are remembered by order rows
  - file status are remember by file rows.
  - if any file is rejected by admin also order status is going to be 'Reddedilen Dosyalar Mevcut'
  - rejected files will be renewed from order list or order detail by client and old files will be exist and viewed from order detail and file lists by everyone



create new docker container if not exist with name 'tedarikNewApp' from 61d0571c2f7bbc4b32f0d88475e8fa6a9beac12aac664a0fdc9f25b568ec8aa7 docker postgresql image and use it wth DB_USERNAME=tedarikNewApp
DB_DATABASE=tedarikNewApp
DB_PASSWORD=tedarikNewApp cradentials




now lets talk about partitioning mechanic detailed on order items mechanics. 

every order item has quanitites. this quantities have types

ST => Adet  (integer)
KG => KG    (float)
M  => Meter (float)

while saving order items they are important.
while partitioning order we need to select how much we are splitting from the main order.

splitted amount should be gone missing from main order's item's quantity and we should see what was before and what is it left after partition

** more detail about partitioning

we have serial entering mechanic.
exmp : 
  - for KG and M
    * we should enter serial number with partitioned amount and 'Malzeme Üretim Tarihi (Month-Year)' .
    * as default serial number will be '-''
  - for ST
    * every st can be have a serial number and 'Malzeme Üretim Tarihi (Month-Year)' if quanitity is below the 300 else no serial entering just quantity entering. 



if an order is partitioned before , cannot sand at once again. its always partial anymore.. (except all partitioned parts are removed)

on order detail , if product is kg-m its auto serial row's date input is not required. just add same date with order's own date YYYY-MM-DD

- Malzeme Cins Formu
- Dosya yenileme mantığında hala hata var
- order items have 'Test Dökümanı' file and bunch of product image so make area for them