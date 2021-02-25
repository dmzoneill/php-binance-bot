# PHP Binance Bot

## Based on
https://github.com/jaggedsoft/php-binance-api

![alt text](https://github.com/dmzoneill/php-binance-bot/raw/master/example.png)

## Donations

Bitcoin 1BLX95wuEQdMkdeRLR5mjgJ1AvJv16CY1r

ETH 0x8831876a202a5722d869a6397e521b94bef483a6

LTC LZSEvLg43YXT1yoPacpKWgzwo3zzUFbF4y

Strategies wrriten on demand - donations helps

## Getting Started

Bot has made me 40% more bitcoin, uses basic fibionacci strategy

modify config.json and put in the relevant api and prikey, 

modify the rest of the settings if you wish

## Basic operation

php binance-bot.class.php

The goal is to accrue more bitcoin
Transactions are limited to BTC pairings.

The bot will buy on downward trending stocks defined by "**downward_trigger_percent**" using the 24hr percent change.


### Buy orders

If the "**downward_trigger_percent**" is set at -5,  then when the stock hits -6% in the last day, the bot will buy set a 'buy limit' at "**buy_at_percent**" (86%).  

The bot will buy "**buy_total_amount_btc**". 

The quantity is that "**buy_total_amount_btc**" divided by the unit price.

The Bot decides to buy penny stocks lower than the "**max_unit_price_btc**" (0.0001) roughly $1.

The bot will buy up to "**max_open_buy_orders**" (15) orders.

the bot will set the 'buy limit' at "**buy_at_percent**" (0.82)

The bot will cancel buy orders if they fall out of the range of "**cancel_buy_at_lower_percent**" (72%) and "**cancel_buy_at_upper_percent**" (132%)


### Sell orders

Once a buy has been detected 
The Bot will immediately 'set limit' for the maximum amount available at "**sell_at_percent**" (1.22) .

The bot will buy up to "**max_open_buy_orders**" (15)

### Loss protection

None at this time.

### Can i change the logic or implement other strategies.

sure, Look at basicTractionStategy (strategy pattern)

### PHP Pthreads installation
on Debian or ubuntu

Note the previous version, we will just compile the threaded version of that
```
sudo su
php --version
PHPVERSION="7.X.X"
```

Remove the old version and install the build requirements
```
apt-get remove php
sudo apt update && \
sudo apt install -y libzip-dev bison autoconf build-essential pkg-config git-core \
libltdl-dev libbz2-dev libxml2-dev libxslt1-dev libssl-dev libicu-dev \
libpspell-dev libenchant-dev libmcrypt-dev libpng-dev libjpeg8-dev \
libfreetype6-dev libmysqlclient-dev libreadline-dev libcurl4-openssl-dev
```

Create directories and get sources
```
mkdir -vp $HOME/src/php
cd $HOME/src/php
wget https://github.com/php/php-src/archive/php-$PHPVERSION.tar.gz
tar --extract --gzip --file php-$PHPVERSION.tar.gz
cd php-src-php-$PHPVERSION
```

Configure and build
```
./buildconf --force
CONFIGURE_STRING="--prefix=/etc/php7 --with-bz2 --with-zlib --enable-zip --disable-cgi \
   --enable-soap --enable-intl --with-openssl --with-readline --with-curl \
   --enable-ftp --enable-mysqlnd --with-mysqli=mysqlnd --with-pdo-mysql=mysqlnd \
   --enable-sockets --enable-pcntl --with-pspell --with-enchant --with-gettext \
   --with-gd --enable-exif --with-jpeg-dir --with-png-dir --with-xsl \
   --enable-bcmath --enable-mbstring --enable-calendar --enable-simplexml --enable-json \
   --enable-hash --enable-session --enable-xml --enable-wddx --enable-opcache \
   --with-pcre-regex --with-config-file-path=/etc/php7/cli \
   --with-config-file-scan-dir=/etc/php7/etc --enable-cli --enable-maintainer-zts \
   --with-tsrm-pthreads --enable-debug --enable-fpm \
   --with-fpm-user=www-data --with-fpm-group=www-data"
./configure $CONFIGURE_STRING
make && sudo make install
```

Setup php.ini
```
mkdir -p /etc/php7/cli/
cp php.ini-production /etc/php7/cli/php.ini
echo "extension=pthreads.so" | sudo tee -a /etc/php7/cli/php.ini
```

Get rid of any old binaries
```
sudo rm /usr/bin/php
sudo ln -s /etc/php7/bin/php /usr/bin/php
```

Install pthreads
```
chmod o+x /etc/php7/bin/phpize
chmod o+x /etc/php7/bin/php-config
git clone https://github.com/krakjoe/pthreads.git
cd pthreads
./configure --prefix='/etc/php7' --with-libdir='/lib/x86_64-linux-gnu' --enable-pthreads=shared --with-php-config='/etc/php7/bin/php-config'
make && sudo make install
```

Check all good
```
php --version
PHP 7.3.27 (cli) (built: Feb 25 2021 12:59:43) ( ZTS DEBUG )
Copyright (c) 1997-2018 The PHP Group
Zend Engine v3.3.27, Copyright (c) 1998-2018 Zend Technologies
    with Zend OPcache v7.3.27, Copyright (c) 1999-2018, by Zend Technologies
```
