#!/bin/sh

yum -y install php php-mysqli
wget http://xn--80aahre3aeglhu.net/billmanager-master.tar.gz
tar -xvf billmanager-master.tar.gz --strip-components=1 -C /usr/local/mgr5/
chmod +x /usr/local/mgr5/processing/pmpananames.php
rm /usr/local/mgr5/install_pananames.sh
rm /usr/local/mgr5/readme.txt
rm /tmp/billmanager-master.tar.gz
rm /tmp/install_pananames.sh
pkill core