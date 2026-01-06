FROM php:8.2-apache

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Configure Apache DocumentRoot to point to public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf .

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install Node.js dependencies
RUN npm install

# Fix permissions for data directory so Apache (www-data) can write to it
RUN chown -R www-data:www-data /var/www/html/data && \
    chmod -R 775 /var/www/html/data

# Expose HTTP port
EXPOSE 80

# By default, start Apache.
# To use MCP, you can also execute: node src/index.js (inside container)
CMD ["apache2-foreground"]
