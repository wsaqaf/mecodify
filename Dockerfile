FROM php:8.2-apache

# Install required system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    wget \
    rsync \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions for MySQL and HTTP requests
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql opcache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy custom PHP configuration
COPY php.ini "$PHP_INI_DIR/conf.d/custom.ini"

# Setup working directory and proper permissions
WORKDIR /var/www/html

# Copy application files (make sure to have .dockerignore to skip node_modules, etc. if relevant)
COPY . /var/www/html/

# Apply proper permissions for Apache and allow script executions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/tmp \
    && chmod +x /var/www/html/update.sh

# Expose port (Apache default)
EXPOSE 80
