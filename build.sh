#!/bin/bash

export $(cat .env | xargs)

docker build -t citypay/magento2:latest \
 --build-arg MAGENTO_REPO_USERNAME=$MAGENTO_REPO_USERNAME \
 --build-arg MAGENTO_REPO_PASSWORD=$MAGENTO_REPO_PASSWORD .
