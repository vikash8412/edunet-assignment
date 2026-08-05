# Local development image — builds frontend assets and installs PHP deps.
# Not used for the live demo (shared hosting builds assets locally and
# uploads them; see README "Deployment").
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        git unzip libzip-dev libonig-dev libxml2-dev \
        nodejs npm \
    && docker-php-ext-install pdo_mysql zip mbstring xml bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist

COPY package.json package-lock.json ./
RUN npm install

COPY . .
RUN npm run build

EXPOSE 8000
