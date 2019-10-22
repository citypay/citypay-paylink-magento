#!/bin/bash

cat >/root/.composer/auth.json <<- EOM
    {
      "http-basic": {
        "repo.magento.com": {
          "username": "$MAGENTO_REPO_USERNAME",
          "password": "$MAGENTO_REPO_PASSWORD"
          "ausername": "$A_MAGENTO_REPO_USERNAME",
          "apassword": "$A_MAGENTO_REPO_PASSWORD"
        }
      }
    }
EOM

cat /root/.composer/auth.json