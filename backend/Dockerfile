FROM php:8.2-cli

# Install system dependencies including Apache, PHP-FPM, and supervisor
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    apache2 \
    php8.2-fpm \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql

# Use production PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure Apache modules
RUN a2enmod rewrite headers proxy proxy_fcgi \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configure PHP-FPM
RUN sed -i 's/listen = \/run\/php\/php8.2-fpm.sock/listen = 127.0.0.1:9000/' /etc/php/8.2/fpm/pool.d/www.conf \
    && echo "clear_env = no" >> /etc/php/8.2/fpm/pool.d/www.conf

# Configure PHP error logging for Railway
RUN echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "error_log = /proc/self/fd/2" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/php.ini

# Configure Apache for Railway (dynamic port support)
RUN sed -i 's/Listen 80/Listen ${PORT:-80}/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:${PORT:-80}/' /etc/apache2/sites-available/000-default.conf

# Configure Apache logging for Railway (stdout/stderr)
RUN sed -i 's|ErrorLog .*|ErrorLog /proc/self/fd/2|' /etc/apache2/apache2.conf \
    && sed -i 's|CustomLog .*|CustomLog /proc/self/fd/1 combined|' /etc/apache2/sites-available/000-default.conf

# Add PHP-FPM configuration to Apache default site
RUN sed -i '/<VirtualHost \*:${PORT:-80}>/a\\t# PHP-FPM Configuration\n\t<FilesMatch \\.php$>\n\t\tSetHandler "proxy:fcgi://127.0.0.1:9000"\n\t</FilesMatch>' /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Copy Apache configuration file
COPY apache-api.conf /etc/apache2/conf-available/api.conf

# Enable the API configuration
RUN a2enconf api

# Create supervisor configuration
RUN cat > /etc/supervisor/conf.d/supervisord.conf << 'EOF'
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=/usr/sbin/php-fpm8.2 --nodaemonize
autostart=true
autorestart=true
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0

[program:apache2]
command=/usr/local/bin/start-apache
autostart=true
autorestart=true
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
EOF

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && touch /var/www/html/requests.log \
    && chown www-data:www-data /var/www/html/requests.log \
    && chmod 666 /var/www/html/requests.log

# Create startup script for Railway
RUN cat > /usr/local/bin/start-apache << 'EOF'
#!/bin/bash

echo "Starting Apache for Railway..."

# Update port configuration if PORT is set
if [ ! -z "$PORT" ]; then
    echo "Railway PORT detected: $PORT"
    sed -i "s/Listen \${PORT:-80}/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/:\${PORT:-80}/:$PORT/" /etc/apache2/sites-available/000-default.conf
fi

# Start Apache in foreground
echo "Apache starting on port ${PORT:-80}"
exec apache2 -DFOREGROUND
EOF

RUN chmod +x /usr/local/bin/start-apache

# Expose port (Railway will override this)
EXPOSE 80

# Start supervisor to manage both PHP-FPM and Apache
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
