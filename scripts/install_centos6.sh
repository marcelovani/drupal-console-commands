#!/bin/sh
# Performs an installation of Drupal console commands on Centos6
set -x

export PHP_FOLDER=/opt/php/bin

# Install our custom php
sh install_php.sh

# Install composer
sh install_composer.sh

# Install drupal console launcher
sh install_launcher.sh

# In order to use the custom php on centos6 we need to add a wrapper for drupal
sudo mv /usr/local/bin/drupal /usr/local/drupal.phar
echo 'PATH="${PHP_FOLDER}/:$PATH" php -v; /usr/local/drupal.phar' > ~/drupal
sudo mv ~/drupal /usr/local/bin/drupal
sudo chmod +x /usr/local/bin/drupal

# Setup Drupal console
drupal -n --override init
drupal settings:set environment dev

# Install console extend plugin
sh install_extend.sh

# Install custom commands
sh install_custom_commands.sh

rm -rf /tmp/drupal_console_commands
