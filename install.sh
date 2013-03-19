#!/bin/bash

# find out where init scripts live...
# And check that SMS Server is installed while we're at it
INITDIR=`dirname \`grep -lr 'SMS Daemon' /etc 2>/dev/null\``

if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "This script must be run as root"
    exit
fi

echo "I think your init scripts live in $INITDIR"
echo "Making new directory /opt/phpMonitor"
mkdir -p /opt/phpMonitor

echo "Copying files into place"
cp -a src/* /opt/phpMonitor/
cp cfg/phpMonitor.conf.xml /etc/
cp init/phpMonitor $INITDIR/

echo "Changing permissions"
chmod 755 /opt/phpMonitor/phpMonitor.php
chmod 755 $INITDIR/phpMonitor

echo "You should now edit /etc/phpMonitor.conf.xml"
echo "Then you can use 'update-rc.d phpMonitor defaults' to make phpMonitor start on boot"

