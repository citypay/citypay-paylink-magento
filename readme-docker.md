# Docker Magento2

Used https://github.com/markshust/docker-magento to create the docker instances needed.

## Steps
* run script setup.sh inside the plugin folder.
* magento endpoints
  * https://magento.test/
  * https://magento.test/admin
* magento admin user
  * username: citypay
  * password: password123
* Two factor authentication email in
  * http://magento.test:1080/

Ngrok: 
* ngrok http https://magento.test

Manually copying files/folders to the container e.g.
* bin/copytocontainer app/code/CityPay/Paylink/Model