Download zip file
Download rootcomposer2.json to a temporary location (see later instructions on what to do with this file)

Place zip file in /usr/local/bin
Remove dir from /var/www/html/magento/app/code

cd /var/www/html/magento
The following should be done as the Magento file sytem owner/ command line user.

Use content of rootcomposer2.json to edit changes to /var/www/html/magento/composer.json

Expand repositories within  /var/www/html/magento/composer.json with the contents of 
repositories.citypay-paylink from rootcomposer2.json

Run 
composer validate

Run 
composer update

Run 
composer require -vvv citypay/module-paylink=100.0.3

Run
php bin/magento module:enable CityPay_Paylink

Further notes:
Configure postback host name
php bin/magento config:set payment /citypay_gateway/postbackhost $NGROK_URL

Configure store payment method CityPay options within the admin user interface

NB test mode is permanently enabled within code at present (see model/PaylinkTokenInformationManagement.php line 427)