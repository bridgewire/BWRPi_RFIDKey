#!/bin/sh

while [ 1 ] ; do

    echo >> BWRPi_RFID.stdout
    echo '-------------------------------------------------------------- begin' >> BWRPi_RFID.stdout
    date >> BWRPi_RFID.stdout
    php main.php >> BWRPi_RFID.stdout 2>&1
    echo '-------------------------------------------------------------- end' >> BWRPi_RFID.stdout
    echo >> BWRPi_RFID.stdout
    sleep 1         # avoid spinning.

done

