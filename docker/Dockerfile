# Dockerfile for local development environment
# Synthesized with PHP 8.3 CLI to host the application services and execute scheduled tasks

FROM php:8.4-cli

# Installing essential system dependencies and libraries required for the project extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    postgresql-client

# Purging package manager cache files to optimize image size footprint
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Constructing required PHP extensions for PostgreSQL, process controls, math, and graphics
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Copying Composer binary directly from the official trusted composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establishing the root directory for the application workspace
WORKDIR /var/www/html

# Exposing target port for local developer server access
EXPOSE 8082

# Initiating the Laravel developer server listening globally on the designated port
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8082", "--no-reload"]
