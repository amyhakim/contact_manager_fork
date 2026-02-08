# 1. Use the Apache + PHP base image
FROM php:8.2-apache

# 2. Install dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev unzip libpng-dev libjpeg-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# 3. Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql mysqli

# 4. Enable Apache Modules
RUN a2enmod rewrite headers

# 5. Set working directory and copy files
WORKDIR /var/www/html
COPY . /var/www/html/

# 6. Configure Apache for Railway
# This setup replaces the complex "cat" command with a simpler echo approach
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 7. Copy Apache configuration and set up virtual host
COPY apache-api.conf /etc/apache2/conf-available/api.conf
RUN a2enconf api

# Create updated virtual host with CORS headers
RUN echo '<VirtualHost *:${PORT}>\n\
    DocumentRoot /var/www/html\n\
    \n\
    # CORS Headers\n\
    Header always set Access-Control-Allow-Origin "*"\n\
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"\n\
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"\n\
    Header always set Access-Control-Allow-Credentials "false"\n\
    \n\
    # Security Headers\n\
    Header always set X-Content-Type-Options "nosniff"\n\
    Header always set X-Frame-Options "SAMEORIGIN"\n\
    Header always set X-XSS-Protection "1; mode=block"\n\
    Header always set Referrer-Policy "strict-origin-when-cross-origin"\n\
    \n\
    <Directory "/var/www/html">\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# 8. Set permissions
RUN chown -R www-data:www-data /var/www/html

# 9. Startup Script
RUN echo '#!/bin/sh\n\
# Force disable conflicting MPMs and enable prefork\n\
a2dismod mpm_event mpm_worker || true\n\
a2enmod mpm_prefork || true\n\
\n\
# Update port for Railway\n\
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf\n\
\n\
# Start Apache\n\
exec apache2-foreground' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Railway default port
ENV PORT=80

CMD ["/usr/local/bin/start.sh"]
