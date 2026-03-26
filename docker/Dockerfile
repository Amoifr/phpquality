# PhpQuality - PHP Static Code Analyzer
# Multi-stage build for optimized image size

# Stage 1: Build
FROM php:8.3-cli-alpine AS builder

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install build dependencies
RUN apk add --no-cache git unzip

WORKDIR /build

# Copy bundle source first
COPY src/ /build/bundle/src/
COPY composer.json /build/bundle/composer.json

# Copy docker app
COPY docker/app/ /build/app/

# Configure path repository to use local bundle (with symlink disabled for Docker copy)
RUN cd /build/app && \
    composer config repositories.phpquality '{"type": "path", "url": "/build/bundle", "options": {"symlink": false}}' && \
    composer config minimum-stability dev && \
    composer require amoifr/phpquality-bundle:@dev --no-scripts --no-interaction

# Generate optimized autoloader
RUN cd /build/app && composer dump-autoload --optimize --classmap-authoritative

# Clear and warm up cache
RUN cd /build/app && \
    php bin/console cache:clear --env=prod --no-warmup && \
    php bin/console cache:warmup --env=prod

# Stage 2: Runtime
FROM php:8.3-cli-alpine

LABEL org.opencontainers.image.title="PhpQuality"
LABEL org.opencontainers.image.description="PHP Static Code Analyzer - Metrics & Quality Reports"
LABEL org.opencontainers.image.version="1.4.1"
LABEL org.opencontainers.image.vendor="amoifr"
LABEL org.opencontainers.image.source="https://github.com/amoifr/PhpQuality"
LABEL org.opencontainers.image.authors="Pascal CESCON <pascal.cescon@gmail.com>"

# Install runtime dependencies (including git for blame analysis)
RUN apk add --no-cache libzip tini git

WORKDIR /app

# Copy application from builder
COPY --from=builder /build/app /app

# Make console executable and var directory writable by any user
RUN chmod +x /app/bin/console && \
    chmod -R 777 /app/var

# Set production environment
ENV APP_ENV=prod
ENV APP_SECRET=docker-build-secret-change-in-production

# PHP memory limit (default: unlimited for large project analysis)
# Override with: docker run -e PHP_MEMORY_LIMIT=512M ...
ENV PHP_MEMORY_LIMIT=-1

# Set entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/sbin/tini", "--", "/entrypoint.sh"]

# Default: show help
CMD ["--help"]

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php /app/bin/console --version || exit 1
