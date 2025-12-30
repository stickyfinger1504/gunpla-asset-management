
FROM php:8.2-fpm


RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini && echo "post_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini

COPY src/ /var/www/html/