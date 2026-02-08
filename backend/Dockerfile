FROM ubuntu:22.04

# Set environment variables to avoid interactive prompts
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install system dependencies and add PHP repository
RUN apt-get update && apt-get install -y \
    software-properties-common \
    curl \
    wget \
    unzip \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y \
    apache2 \
    php8.2 \
    php8.2-fpm \
    php8.2-cli \
    php8.2-common \
    php8.2-mysql \
    php8.2-zip \
    php8.2-gd \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-xml \
    php8.2-bcmath \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache modules
RUN a2enmod rewrite headers proxy proxy_fcgi \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configure PHP-FPM
RUN sed -i 's/listen = \/run\/php\/php8.2-fpm.sock/listen = 127.0.0.1:9000/' /etc/php/8.2/fpm/pool.d/www.conf \
    && echo "clear_env = no" >> /etc/php/8.2/fpm/pool.d/www.conf \
    && echo "pm.max_children = 20" >> /etc/php/8.2/fpm/pool.d/www.conf \
    && echo "pm.start_servers = 3" >> /etc/php/8.2/fpm/pool.d/www.conf \
    && echo "pm.min_spare_servers = 2" >> /etc/php/8.2/fpm/pool.d/www.conf \
    && echo "pm.max_spare_servers = 4" >> /etc/php/8.2/fpm/pool.d/www.conf

# Configure PHP for production
RUN sed -i 's/display_errors = On/display_errors = Off/' /etc/php/8.2/fpm/php.ini \
    && sed -i 's/;log_errors = On/log_errors = On/' /etc/php/8.2/fpm/php.ini \
    && sed -i 's/;error_log = php_errors.log/error_log = \/proc\/self\/fd\/2/' /etc/php/8.2/fpm/php.ini

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

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && touch /var/www/html/requests.log \
    && chown www-data:www-data /var/www/html/requests.log \
    && chmod 666 /var/www/html/requests.log

# Create startup script for Railway
RUN cat > /usr/local/bin/start-services << 'EOF'
#!/bin/bash

echo "Starting services for Railway..."

# Update port configuration if PORT is set
if [ ! -z "$PORT" ]; then
    echo "Railway PORT detected: $PORT"
    sed -i "s/Listen \${PORT:-80}/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/:\${PORT:-80}/:$PORT/" /etc/apache2/sites-available/000-default.conf
fi

# Set proper environment for Apache
export APACHE_RUN_USER=www-data
export APACHE_RUN_GROUP=www-data
export APACHE_PID_FILE=/var/run/apache2/apache2.pid
export APACHE_RUN_DIR=/var/run/apache2
export APACHE_LOCK_DIR=/var/lock/apache2
export APACHE_LOG_DIR=/var/log/apache2

# Create necessary directories
mkdir -p $APACHE_RUN_DIR $APACHE_LOCK_DIR $APACHE_LOG_DIR

# Start PHP-FPM first
echo "Starting PHP-FPM..."
/usr/sbin/php-fpm8.2 --daemonize --fpm-config /etc/php/8.2/fpm/php-fpm.conf

# Wait for PHP-FPM to start
sleep 2

# Start Apache in foreground
echo "Apache starting on port ${PORT:-80}"
exec apache2 -DFOREGROUND
EOF

RUN chmod +x /usr/local/bin/start-services

# Expose port (Railway will override this)
EXPOSE 80

# Start services
CMD ["/usr/local/bin/start-services"]
