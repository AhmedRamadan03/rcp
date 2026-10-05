#FROM node:20-alpine AS build-stage
#WORKDIR /app
#COPY . .
#RUN npm install
#RUN npm run build

FROM php:8.4-fpm

ARG user
ARG uid

# Install system dependencies
RUN apt-get update && apt-get install -y libpq-dev git libzip-dev unzip zip libicu-dev libxml2-dev && \
    docker-php-ext-install pdo pdo_pgsql zip intl soap


RUN apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev && \
    docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ && \
    docker-php-ext-install gd

#Install Node Js
RUN curl -fsSL https://deb.nodesource.com/setup_23.x -o nodesource_setup.sh | bash -
RUN apt-get update && apt-get install -y \
    npm
# Install puppteer
RUN apt-get update && apt-get install -y --no-install-recommends \
  fonts-liberation \
  libasound2 \
  libatk-bridge2.0-0 \
  libatk1.0-0 \
  libcups2 \
  libdrm2 \
  libgbm1 \
  libgtk-3-0 \
  libnspr4 \
  libnss3 \
  libx11-xcb1 \
  libxcomposite1 \
  libxdamage1 \
  libxrandr2 \
  xdg-utils \
  libu2f-udev \
  libxshmfence1 \
  libglu1-mesa \
  chromium \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*


RUN apt-get update && apt-get install gnupg wget -y && \
  wget --quiet --output-document=- https://dl-ssl.google.com/linux/linux_signing_key.pub | gpg --dearmor > /etc/apt/trusted.gpg.d/google-archive.gpg && \
  sh -c 'echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google.list' && \
  apt-get update && \
  apt-get install google-chrome-stable -y --no-install-recommends && \
  rm -rf /var/lib/apt/lists/*

RUN mkdir /puppeteer

RUN cd /puppeteer

RUN npm install --global --unsafe-perm puppeteer

RUN puppeteer browsers install chrome --path=/puppeteer

RUN npm install --global --unsafe-perm vite

RUN mkdir /app

RUN cd /app

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Add User
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user && \
    chown -R $user:$user /var/www/html

WORKDIR /app



#COPY --from=build-stage /app .
USER root
COPY . .

# Install Laravel dependencies

RUN chown -R $user:$user /app
RUN composer install

RUN npm install
RUN npm run build

#RUN php artisan serve
RUN php artisan storage:link
RUN composer dump-autoload
RUN php artisan key:generate
EXPOSE 8000
#ENTRYPOINT php artisan serve --host=0.0.0.0
CMD ["php", "artisan", "serve", "--host=0.0.0.0"]

