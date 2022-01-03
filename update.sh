#!/bin/bash

#This script simply updates your files to the latest version on GitHub. Your log and other temporary files will not be affected.
#It assumes that you have access to root priviliges (sudo). If you don't need it, you can simply remove 'sudo' from the below commands

ver1=`cat ./ver.no`

echo "Updating current version ($ver1)"

sudo wget https://github.com/wsaqaf/mecodify/archive/refs/heads/master.zip  -O ./tmp/master.zip

sudo unzip ./tmp/master.zip -d ./tmp/

sudo rsync -av --update --progress ./tmp/mecodify-master/* ./ --exclude tmp

sudo rm -rf ./tmp/master.zip ./tmp/mecodify-master

ver2=`cat ./ver.no`

echo "Update to version ($ver2) completed"

