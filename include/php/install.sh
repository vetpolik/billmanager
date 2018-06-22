#!/bin/sh

wget http://xn--80aahre3aeglhu.net/billmanager-master.tar.gz
tar -xvf billmanager-master.tar.gz --strip-components=1 -C /usr/local/mgr5/
chmod +x /usr/local/mgr5/processing/pmpananames.php
pkill core