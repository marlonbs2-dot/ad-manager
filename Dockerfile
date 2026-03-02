# PHP Application Dockerfile - Com PowerShell Core
FROM php:8.2-fpm-alpine

# Install system dependencies including ICU libraries for PowerShell
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    wget \
    bash \
    icu-libs \
    icu-data-full \
    libgcc \
    libstdc++ \
    krb5-libs \
    libintl \
    openssl \
    ca-certificates

# Install PowerShell Core via direct download with proper ICU setup
RUN wget -O /tmp/powershell.tar.gz https://github.com/PowerShell/PowerShell/releases/download/v7.4.1/powershell-7.4.1-linux-musl-x64.tar.gz \
    && mkdir -p /opt/microsoft/powershell/7 \
    && tar zxf /tmp/powershell.tar.gz -C /opt/microsoft/powershell/7 \
    && chmod +x /opt/microsoft/powershell/7/pwsh \
    && ln -s /opt/microsoft/powershell/7/pwsh /usr/bin/pwsh \
    && rm /tmp/powershell.tar.gz

# Set ICU environment variables for PowerShell
ENV DOTNET_SYSTEM_GLOBALIZATION_INVARIANT=false
ENV LC_ALL=en_US.UTF-8
ENV LANG=en_US.UTF-8

# Install PHP extensions including LDAP
RUN apk add --no-cache openldap-dev \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    ldap

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create required directories and set permissions
RUN mkdir -p /var/www/html/storage \
    && mkdir -p /var/www/html/storage/cache \
    && mkdir -p /var/www/html/storage/sessions \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /var/lib/php/sessions \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/lib/php/sessions \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/storage \
    && chmod -R 777 /var/www/html/logs

# Copy configurations
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# SSL Configuration - Copy SSL certificates for Nginx
RUN mkdir -p /etc/nginx/ssl
COPY docker/ssl/server.crt /etc/nginx/ssl/server.crt
COPY docker/ssl/server.key /etc/nginx/ssl/server.key
RUN chmod 644 /etc/nginx/ssl/server.crt && chmod 600 /etc/nginx/ssl/server.key

# Expose ports
EXPOSE 80 443

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 
