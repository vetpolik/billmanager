#!/bin/sh

yum -y install php php-mysqli
wget https://codeload.github.com/Pananames/billmanager/zip/master
tar -xvf billmanager-master.zip --strip-components=1 -C /usr/local/mgr5/
chmod +x /usr/local/mgr5/processing/pmpananames.php
rm /usr/local/mgr5/install_pananames.sh
rm /usr/local/mgr5/README.md
rm /tmp/billmanager-master.zip
rm /tmp/install_pananames.sh
pkill core
