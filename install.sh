#!/bin/bash

# find out where init scripts live...
# And check that SMS Server is installed while we're at it
INITDIR=`dirname \`grep -lr 'SMS Daemon' /etc 2>/dev/null\``

if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "This script must be run as root"
    exit
fi

mkdir -p /opt/phpMonitor

cp -a src/* /opt/phpMonitor/
cp cfg/phpMonitor.conf.xml /etc/
cp init/phpMonitor $INITDIR/
 
chmod 755 /opt/phpMonitor/phpMonitor.php
chmod 755 $INITDIR/phpMonitor

echo "You should new edit /etc/phpMonitor.conf.xml"
