# Docker Magento2

## Use ./init.sh when:

* first install
* you changed .env BASE_URL / NGROK_URL
* you want to install sample data
* you changed Magento install/config logic
* you reset the Magento folder/database

## Use docker compose up -d when:
* Magento is already installed
* you just stopped/restarted the AWS instance
* you only need containers running again

## Use the following to setup a new base_url:

```
docker compose exec -T -u www-data app bash -lc '
cd /var/www/html
bin/magento config:set web/unsecure/base_url "https://dac3-212-9-31-132.ngrok-free.app/"
bin/magento config:set web/secure/base_url "https://dac3-212-9-31-132.ngrok-free.app/"
bin/magento config:set web/secure/use_in_frontend 1
bin/magento config:set web/secure/use_in_adminhtml 1
bin/magento config:set web/secure/offloader_header X-Forwarded-Proto
bin/magento config:set web/cookie/cookie_domain "dac3-212-9-31-132.ngrok-free.app"
bin/magento cache:flush
'
```

### The to verify
```
docker compose exec -T -u www-data app bash -lc '
cd /var/www/html
bin/magento config:show web/unsecure/base_url
bin/magento config:show web/secure/base_url
bin/magento config:show web/cookie/cookie_domain
'
```