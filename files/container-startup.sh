#!/bin/bash


echo "Starting CityPay/Magento container"

if [[ -z "${NGROK_AUTHTOKEN}" ]]; then
  echo "No NGROK_AUTHTOKEN in env"
  exit
else
  ngrok authtoken $NGROK_AUTHTOKEN
  echo "web_addr: 0.0.0.0:4040" >>./ngrok.conf
  nohup ngrok http -log=ngrok.log -region=eu --config=ngrok.conf 80 &
fi


sed 's/mailhub=mail/mailhub=citypay-paylink-magento_mailhog_1:1025/g' /etc/ssmtp/ssmtp.conf > /etc/ssmtp/ssmtp.conf2
cp /etc/ssmtp/ssmtp.conf2 /etc/ssmtp/ssmtp.conf

sleep 4
NGROK_URL=$(curl http://127.0.0.1:4040/api/tunnels | jq '.tunnels[].public_url'  | grep http:)
NGROK_URL=$(sed -e 's/^"//' -e 's/"$//' <<<"$NGROK_URL")

echo 'ngrokurl=' $NGROK_URL
export NGROK_URL=$NGROK_URL

#cd /var/www/html/magento
#cp composer2.json composer.json
#chown -R www-data:www-data app/code/CityPay
#chown -R www-data:www-data app/code/Mageplaza

service apache2 start
#composer update
#composer require citypay/module-paylink=100.0.3


#sudo -g www-data php bin/magento config:set payment/citypay_gateway/postbackhost $NGROK_URL
#sudo -g www-data php bin/magento  cache:clean config


cat > /root/.composer/auth.json <<- EOM
{
  "http-basic": {
    "repo.magento.com": {
      "username": "$MAGENTO_REPO_USERNAME",
      "password": "$MAGENTO_REPO_PASSWORD"
    }
  }
}
EOM

/bin/bash
#apachectl -D FOREGROUND