# PhpQuality - PHP Static Code Analyzer
# Multi-stage build for optimized image size

# Stage 1: Build
FROM php:8.3-cli-alpine AS builder

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install build dependencies
RUN apk add --no-cache git unzip

WORKDIR /app

# Copy composer files first for better caching
COPY symfony-app/composer.json symfony-app/composer.lock* ./

# Install dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application code
COPY symfony-app/ .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Clear and warm up cache
RUN php bin/console cache:clear --env=prod --no-warmup && \
    php bin/console cache:warmup --env=prod

# Stage 2: Runtime
FROM php:8.3-cli-alpine

LABEL org.opencontainers.image.title="PhpQuality"
LABEL org.opencontainers.image.description="PHP Static Code Analyzer - Metrics & Quality Reports"
LABEL org.opencontainers.image.version="0.2.0"
LABEL org.opencontainers.image.vendor="amoifr"
LABEL org.opencontainers.image.source="https://github.com/amoifr/PhpQuality"
LABEL org.opencontainers.image.authors="Pascal CESCON <contact@moi.ruedesjasses.fr>"

# Install runtime dependencies only
RUN apk add --no-cache libzip tini

WORKDIR /app

# Copy application from builder
COPY --from=builder /app /app

# Make console executable and var directory writable by any user
RUN chmod +x /app/bin/console && \
    chmod -R 777 /app/var

# Set production environment
ENV APP_ENV=prod

# Set entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/sbin/tini", "--", "/entrypoint.sh"]

# Default: show help
CMD ["--help"]

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php /app/bin/console --version || exit 1
