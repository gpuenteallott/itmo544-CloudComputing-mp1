#!/bin/bash

################################################
# Illinois Institute of Technology
# ITMO 544 Cloud Computing - Mini Project 1 
#
# Student: Guillermo de la Puente
#          https://github.com/gpuenteallott
#
# Setup of environment to run PHP project
#
# Script B -
# install.sh will pull all system pre-reqs and required libraries and install AWS
# SDK library via composer as well as wget your project down and deploy it to the correct
# directory copying your custom-config.php to the correct location.
################################################

# This are the AWS credentials for the server
# They will be dinamically added from this file by installenv.sh
AWS_ACCESS_KEY=
AWS_SECRET_KEY=

# This is the name given by the user when deploying the system though script
# It will be used by the PHP code to name buckets and other resources,
# as well as to access them
# It's value is dinamically inserted from installenv.sh
NAME=

sudo apt-get -y update 
sudo apt-get -y install git apache2 php5 php5-curl php5-cli curl unzip php5-gd

# Restart Tomcat to apply port changes and recognize curl
sudo service apache2 restart

# Download composer
cd /var/www
curl -sS https://getcomposer.org/installer | php

# Get project
sudo wget https://github.com/gpuenteallott/itmo544-CloudComputing-mp1/archive/master.zip
sudo unzip master.zip
shopt -s dotglob # include hidden files in mv operation
sudo mv itmo544-CloudComputing-mp1-master/application/* /var/www
shopt -u dotglob # restore default behaviour
sudo rm master.zip
sudo rm -R itmo544-CloudComputing-mp1-master
sudo rm index.html # remove default apache2 welcome

# Install libraries
sudo php composer.phar install

# Create temporary dir for the webapp
mkdir /var/www/tmp
chmod 777 /var/www/tmp

cp -f custom-config-template.php /var/www/vendor/aws/aws-sdk-php/src/Aws/Common/Resources/custom-config.php

# Setup aws credentials file for PHP
# The string "########" is used to avoid problems with slashes while using 'sed'
sed -i "s/AWS_ACCESS_KEY/$AWS_ACCESS_KEY/g" /var/www/vendor/aws/aws-sdk-php/src/Aws/Common/Resources/custom-config.php
sed -i "s/AWS_SECRET_KEY/$AWS_SECRET_KEY/g" /var/www/vendor/aws/aws-sdk-php/src/Aws/Common/Resources/custom-config.php
# The secret came with slashes converted into ######## to avoid problems
sed -i "s/########/\//g" /var/www/vendor/aws/aws-sdk-php/src/Aws/Common/Resources/custom-config.php

# Save a file with the name in the webapp directory
echo -n $NAME > /var/www/name.txt