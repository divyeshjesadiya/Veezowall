#!/bin/sh

count=30

for i in `seq 1 $count`; do
    /usr/local/bin/php /usr/local/www/packet_filter.php &
    sleep 2
done
