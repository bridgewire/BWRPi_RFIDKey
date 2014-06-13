#!/bin/bash

export HOME=/home/pi
export PIDFILE=/var/lock/frontdoord
export STOPFILE=$HOME/.frontdoord.stop

# if the stop-file exists then don't do anything at all.
if [ -f $STOPFILE ]
then
    exit 0
fi

LOGCMD='logger -i -t frontdoord --'

cd /home/pi/frontdoord

lockfile -r 0 $PIDFILE > /dev/null 2>&1
success=$?
if [ "$success" == "0" ]
then
    chmod 644 $PIDFILE
    echo -n $$ > $PIDFILE
else
    # there's a lockfile.
    # get rid of it if it's stale

    export OLDPID=`cat $PIDFILE`
    ps -p $OLDPID > /dev/null 2>&1
    success=$?
    if [ "$success" == "0" ]
    then
        # non-zero success indicates we do not own the lockfile
       success=1
    else
        $LOGCMD "removing stale lockfile with pid: $OLDPID"
        rm -f $PIDFILE

        # now, lock it again
        lockfile -r 0 $PIDFILE > /dev/null 2>&1
        success=$?

        # this time success should be guaranteed, but don't assume.
        if [ "$success" == "0" ] 
        then
            chmod 644 $PIDFILE
            echo -n $$ > $PIDFILE 
        fi
    fi
fi

if [ "$success" == "0" ]
then
    # the lockfile is ours

    # if someone creates a stop-file then don't restart main.php
    while [ ! -f $STOPFILE ] ; do

        $LOGCMD '-------------------------------------------------------------- begin'
        php main.php 2>&1 | $LOGCMD

        if [ -f $STOPFILE ]
        then
            $LOGCMD 'found stop file. terminating service.'
        fi

        $LOGCMD '-------------------------------------------------------------- end'
        sleep 1  # if main.php dies instantly, avoid spinning

    done

    # if success is zero then the pidfile is ours to delete
    rm -f $PIDFILE
fi

exit 0

