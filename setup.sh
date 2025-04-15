#!/bin/bash

# Create your project directory then go into it:
mkdir -p ../Magento/citypay-magento-site
cd $_

# Run this automated one-liner from the directory you want to install your project.
curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/onelinesetup | bash -s -- magento.test community 2.4.7-p3

# Copy plugin into Magento code path (now we're inside magento-site)
mkdir -p src/app/code/CityPay/Paylink
cp -R ../../citypay-paylink-magento/* src/app/code/CityPay/Paylink/

bin/copytocontainer app/code

bin/magento module:enable CityPay_Paylink

bin/magento sampledata:deploy
bin/magento setup:upgrade
bin/magento cache:flush

bin/magento admin:user:create \
  --admin-user=citypay \
  --admin-password=password123 \
  --admin-email=your@email.com \
  --admin-firstname=Citypay \
  --admin-lastname=Account