#!/bin/sh
SERVICE='cron.php'

if ps ax | grep -v grep | grep $SERVICE > /dev/null
then
    echo "$SERVICE service running, everything is fine"
else
    echo "$SERVICE is not running"
	echo "Starting..."
	nohup php /var/www/cron/cron.php &
fi
