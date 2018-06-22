#!/bin/sh

mkdir -p /usr/local/mgr5/include/php/
wget -O /usr/local/mgr5/processing/pananames.txt https://quest-time.com.ua/pananames.txt
wget -O /usr/local/mgr5/include/php/pananames.txt https://quest-time.com.ua/pananames.txt
wget -O /usr/local/mgr5/etc/xml/pananames.txt https://quest-time.com.ua/pananames.txt
chmod +x /usr/local/mgr5/processing/pananames.txt

#pkill core