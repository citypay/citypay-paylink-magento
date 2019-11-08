#!/bin/bash

cd /usr/local/bin
unzip ngrok-stable-linux-amd64.zip
./ngrok authtoken $NGROK_AUTHTOKEN
./ngrok http -log=ngrok.log  80 > /dev/null  &
sleep 2
NGROK_URL=$(curl http://127.0.0.1:4040/api/tunnels | jq '.tunnels[].public_url'  | grep http:)
echo 'ngrokurl=' $NGROK_URL
export NGROK_URL=$NGROK_URL

service apache2 start
/bin/bash
