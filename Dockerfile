FROM php:8.2-apache

# Serve login.php at the site root
RUN printf 'DirectoryIndex login.php index.php\n' > /etc/apache2/conf-available/dirindex.conf \
    && a2enconf dirindex

# Render sends traffic to $PORT (default 10000), so make Apache listen on it
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000
