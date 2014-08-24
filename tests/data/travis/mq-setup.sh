#!/bin/sh

if (php --version | grep -i HHVM > /dev/null); then
    echo "skipping MQ on HHVM"
else
    sudo add-apt-repository -y ppa:chris-lea/zeromq
    sudo add-apt-repository -y ppa:ondrej/php5
    sudo apt-get update

    # Install ZeroMQ
    sudo apt-get install libzmq3 libpgm-5.1-0 php5-zmq

    # Install Gearman
    sudo apt-get install gearman-job-server
    sudo apt-get install php5-gearman
    echo "extension = gearman.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

    php tests/data/mq/zero/simple_server.php &
    php tests/data/mq/zero/pub_server.php &

    php tests/data/mq/gearman/simple_server.php &
    # Install RabbitMQ
    #sudo apt-get install rabbitmq-server
fi