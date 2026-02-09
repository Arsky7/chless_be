<?php
// File: create_models.php
// Run: php create_models.php

echo "Creating 35 model files...\n\n";

$models = [
    'User',
    'Profile',
    'Address',
    'Role',
    'Permission',
    'Brand',
    'Category',
    'Product',
    'ProductImage',
    'Review',
    'Wishlist',
    'Collection',
    'CollectionProduct',
    'Order',
    'OrderItem',
    'Cart',
    'Payment',
    'Invoice',
    'ShippingMethod',
    'Discount',
    'Coupon',
    'Inventory',
    'Warehouse',
    'Shipment',
    'Blog',
    'Page',
    'FAQ',
    'Testimonial',
    'Ticket',
    'TicketMessage',
    'KnowledgeBase',
    'Setting',
    'Notification',
    'Analytic',
    'Log',
];

foreach ($models as $model) {
    echo "Creating: $model\n";
    exec("php artisan make:model $model");
    sleep(1);
}

echo "\n✅ 35 model files created!\n";
echo "Location: app/Models/\n";