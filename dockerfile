FROM ubuntu:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y \
    software-properties-common \
    curl \
    git \
    unzip \
    zip \
    php \
    php-cli \
    php-mbstring \
    php-xml \
    php-bcmath \
    php-tokenizer \
    php-json \
    php-mysql \
    php-zip \
    php-curl \
    apache2 \
    && apt-get clean

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY . .

RUN composer install

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host", "0.0.0.0", "--port", "8000"]
