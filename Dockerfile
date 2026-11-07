FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM serversideup/php:8.4-fpm-nginx AS app
USER root
RUN install-php-extensions intl bcmath gd
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/logs storage/app/google storage/app/public docker/runtime bootstrap/cache \
    && ln -s /var/www/html/docker/runtime/.env /var/www/html/.env \
    && chown -R www-data:www-data storage bootstrap/cache docker/runtime vendor/pestphp
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY docker/entrypoint.d/60-bullsmedia.sh /etc/entrypoint.d/60-bullsmedia.sh
ENV AUTORUN_ENABLED=false SSL_MODE=off HEALTHCHECK_PATH=/up
USER www-data
EXPOSE 8080