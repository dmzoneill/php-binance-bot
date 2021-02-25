# PHP Pthreads installation
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
/etc/php7/bin/phpize
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
