#!/bin/bash

cd /usr/local/bin
unzip ngrok-stable-linux-amd64.zip
./ngrok authtoken $NGROK_AUTHTOKEN
echo "web_addr: 0.0.0.0:4040" >>./ngrok.conf
nohup ngrok http -log=ngrok.log -region=eu --config=ngrok.conf 80 &
#> /dev/null


#
sleep 4
sed 's/mailhub=mail/mailhub=citypay-paylink-magento_mailhog_1:1025/g' /etc/ssmtp/ssmtp.conf > /etc/ssmtp/ssmtp.conf2
cp /etc/ssmtp/ssmtp.conf2 /etc/ssmtp/ssmtp.conf

NGROK_URL=$(curl http://127.0.0.1:4040/api/tunnels | jq '.tunnels[].public_url'  | grep http:)
NGROK_URL=$(sed -e 's/^"//' -e 's/"$//' <<<"$NGROK_URL")

echo 'ngrokurl=' $NGROK_URL
export NGROK_URL=$NGROK_URL

cd /var/www/html/magento
#cp composer2.json composer.json
#chown -R www-data:www-data app/code/CityPay
chown -R www-data:www-data app/code/Mageplaza

service apache2 start
composer update
composer require -vvv citypay/module-paylink=100.0.3

sudo -g www-data php bin/magento config:set payment/citypay_gateway/postbackhost $NGROK_URL
sudo -g www-data php bin/magento  cache:clean config


/bin/bash
