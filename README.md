# wordpress_payment_gateway
Woopkassa payment gateway for Wordpress CMS as a module.

## Требования

Для работы модуля должно быть установлено и включено PHP расширение SOAP.

## Установка

1. Перейти в раздел администратора
2. Установить плагины wooppay-1.1.5/wooppay-1.1.5 mobile в зависимости от выбранного инструмента приема платежей: перейти на страницу Plugins, нажать Add New -> Upload Plugin, загрузить распакованный модуль в .zip формате
![Alt text](.README/wordpress_1.png?raw=true)
3. Активировать плагины в WooCommerce -> Settings -> Payments
![Alt text](.README/wordpress_2.png?raw=true)
4. В настройках каждого из них ввести ваши данные.
````
Пример:

API URL: http://www.test.wooppay.com/api/wsdl

API Username: test_merch

API Password: A12345678a

Order prefix: mobile

Service name: test_merch_invoice
````
Ссылку WSDL можно взять в кабинете мерчанта, в разделе Online прием платежей -> WSDL

![Alt text](.README/wordpress_3.png?raw=true)

Перейти в магазин и произвести оплату.
