# Dockerfile — Production PHP-FPM image (multi-stage build)
# Usage: docker build -f Dockerfile -t zxpress-php:prod .

# ── Stage 1: Builder — compile PHP extensions ──
# Pinned digest (amd64 manifest): update with docker pull + tools/docker-image-digests.sh
FROM php:8.5-fpm@sha256:3d98d6bc0e3928478209db6ccc56fd4d5e796dab9d6a7ab56055c9304bf48003 AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libzip-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install mysqli mbstring gd zip \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# ── Stage 2: Runtime — minimal image with only runtime libs ──
FROM php:8.5-fpm@sha256:3d98d6bc0e3928478209db6ccc56fd4d5e796dab9d6a7ab56055c9304bf48003

# Runtime libs for gd/mysqli/mbstring/zip extensions (Debian trixie: libzip5, libpng16-16t64)
# fonts-dejavu-core: chronology graph uses imagettftext (path under /usr/share)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng16-16t64 libjpeg62-turbo libfreetype6 libonig5 libzip5 libwebp7 \
        fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/docker-php-ext-*.ini /usr/local/etc/php/conf.d/

RUN useradd -r -s /usr/sbin/nologin zxpress \
    && mkdir -p /home/zxpress/tmp \
                /home/zxpress/web/zxpress.ru/public_html \
    && chown -R www-data:www-data /home/zxpress

COPY conf/php-hardened.ini /usr/local/etc/php/conf.d/zxpress.ini
COPY conf/opcache-prod.ini /usr/local/etc/php/conf.d/opcache.ini
COPY conf/php-fpm-www.conf /usr/local/etc/php-fpm.d/www.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

HEALTHCHECK --interval=10s --timeout=5s --retries=3 \
    CMD php -r "if(false===@fsockopen('localhost',9000)){exit(1);}" || exit 1

EXPOSE 9000
CMD ["/entrypoint.sh"]
