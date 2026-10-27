FROM php:8.3-cli

# Install system dependencies yang dibutuhkan Laravel
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions yang diperlukan (Pdo, Mbstring, Exif, PCNTL, Bcmath, Gd, Zip)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer resmi dari image composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory di dalam container
WORKDIR /var/www

# Copy seluruh file project order-service ke container
COPY . /var/www

# Install dependency PHP menggunakan Composer
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set permissions untuk storage dan cache Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Expose port 3000 (sesuai konfigurasi docker-compose)
EXPOSE 3000

# Perintah default dijalankan saat container menyala (migrasi database & jalankan server built-in PHP)
CMD bash -c "php artisan migrate --force && php -S 0.0.0.0:3000 -t public"