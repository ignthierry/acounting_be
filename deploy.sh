#!/bin/bash

# Hentikan eksekusi jika ada command yang gagal/error
set -e

echo "🚀 Memulai proses deployment..."

# 1. Masuk ke mode maintenance agar user tidak melihat error berantakan saat update
echo "🚧 Mengaktifkan Mode Maintenance..."
php artisan down || echo "Nasihat: Aplikasi mungkin sudah dalam mode maintenance."

# 2. Ambil kode terbaru dari Git
echo "📦 Menarik kode terbaru dari Git..."
# Menggunakan 'git pull origin main' jika branch utama Anda bernama main
git pull origin main

# 3. Install/Update dependensi PHP (Composer)
#echo "📥 Mengupdate dependensi Composer..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 4. Jalankan migrasi database
#echo "🗄️ Menjalankan migrasi database..."
php artisan migrate --force

# 5. Reset dan optimalkan cache Laravel
echo "🧹 Membersihkan dan mengoptimalkan cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Mengompilasi konfigurasi dan route ke dalam cache untuk performa maksimal
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Atur ulang permission folder agar Nginx bisa membaca/menulis dengan lancar
echo "🔒 Mengatur ulang permission storage dan cache..."
# Menyesuaikan dengan user web server (biasanya www-data)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 7. Reload/Restart Nginx & PHP-FPM
echo "🔄 Merestart Nginx dan menyegarkan PHP-FPM..."
# Menggunakan 'reload' lebih disarankan untuk Nginx agar tidak ada downtime bagi user
sudo systemctl reload nginx
# Ganti versi php-fpm di bawah ini sesuai dengan versi yang Anda gunakan (contoh: php8.2-fpm)
#sudo systemctl restart php8.2-fpm
sudo systemctl restart php8.3-fpm
# 8. Matikan mode maintenance
echo "✅ Menonaktifkan Mode Maintenance..."
php artisan up

echo "🎉 Deployment selesai dengan sukses!"
