# Use Microsoft's PHP 7.4 image as a foundation
FROM mcr.microsoft.com/appsvc/php:7.4-apache_20201229.1
COPY ./phplib/ /home/site/phplib/
WORKDIR /home/site/wwwroot