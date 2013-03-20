#!/bin/bash

CONFIG_DEST=/etc/phpMonitor.conf.xml
# find out where init scripts live...
# And check that SMS Server is installed while we're at it
SMS_DAEMON_SCRIPT=`find /etc/ -type f -name sms3 2>/dev/null | xargs grep -lri 'Starting SMS Daemon' 2>/dev/null`

if [ -z "$SMS_DAEMON_SCRIPT" ]; then
    echo "phpMonitor requires SMS Server Tools 3 to be installed"
    echo "I can't find the startup script for this anywhere in your /etc/ directory"
    echo "You can obtain this from http://smstools3.kekekasvi.com/"
    exit
fi

INITDIR=`dirname $SMS_DAEMON_SCRIPT`

if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "This script must be run as root"
    exit
fi

echo "I think your init scripts live in $INITDIR"
echo "Making new directory /opt/phpMonitor"
mkdir -p /opt/phpMonitor

echo "Copying files into place"
cp -a src/* /opt/phpMonitor/

if [ -e $CONFIG_DEST ]; then
    echo "Found existing configuration file: $CONFIG_DEST"
    echo "Leaving this file in place"
    echo "There is an example configuration file available (cfg/phpMonitor.conf.xml) if you need it"
else
    echo "Moving example configuration file into place"
    echo "*****************************************************************"
    echo "YOU MUST EDIT /etc/phpMonitor.conf.xml BEFORE STARTING phpMonitor"
    echo "*****************************************************************"
    cp cfg/phpMonitor.conf.xml /etc/
fi

cp init/phpMonitor $INITDIR/

echo "Changing permissions"
chmod 755 /opt/phpMonitor/phpMonitor.php
chmod 755 $INITDIR/phpMonitor

echo
echo "You can use 'update-rc.d phpMonitor defaults' to make phpMonitor start on boot"

