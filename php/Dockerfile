FROM php:8.5.4-apache

COPY apache.conf /etc/apache2/conf-enabled/custom.conf

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    msmtp \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli gd \
    && printf "defaults\nauth off\ntls off\nlogfile /var/log/msmtp.log\n\naccount default\nhost mailhog\nport 1025\nfrom noreply@pixelplayground.local\n" > /etc/msmtprc \
    && chmod 644 /etc/msmtprc \
    && echo 'sendmail_path = /usr/bin/msmtp -t' > /usr/local/etc/php/conf.d/mail.ini
