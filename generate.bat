cd C:\laragon\www\chless\database\migrations

echo "🔥 FIXING ORDER_ITEMS FOREIGN KEY..."
echo "===================================="

:: 1. Pindahkan warehouses ke depan
ren 2026_02_11_120337_24_create_warehouses_table.php 2026_02_11_120337_17_create_warehouses_table.php 2>nul

:: 2. Geser orders dan order_items
ren 2026_02_11_120337_17_create_orders_table.php 2026_02_11_120337_18_create_orders_table.php 2>nul
ren 2026_02_11_120337_18_create_order_items_table.php 2026_02_11_120337_19_create_order_items_table.php 2>nul

:: 3. Perbaiki typo di order_items
powershell -Command "(Get-Content 2026_02_11_120337_19_create_order_items_table.php) -replace \"decimal\('discount_amount', 12, 2'\)\", \"decimal('discount_amount', 12, 2)\" | Set-Content 2026_02_11_120337_19_create_order_items_table.php"

echo "✅ URUTAN FIXED!"
dir *.php | findstr /i "17 18 19"

cd ..\..\..

:: 4. Migrate fresh
php artisan migrate:fresh