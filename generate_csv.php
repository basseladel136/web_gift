<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$ids = \Illuminate\Support\Facades\DB::table('products')
    ->where('is_active', true)
    ->pluck('id')
    ->toArray();

$output = implode("\n", $ids);
file_put_contents('C:/apache-jmeter-5.6.3/bin/product_ids.csv', $output);

echo 'Done! ' . count($ids) . ' product IDs written to C:/apache-jmeter-5.6.3/bin/product_ids.csv' . PHP_EOL;
