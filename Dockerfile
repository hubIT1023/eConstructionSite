FROM php:7.4-apache

# Install PostgreSQL driver dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and AllowOverride
RUN a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files and configure permissions
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
