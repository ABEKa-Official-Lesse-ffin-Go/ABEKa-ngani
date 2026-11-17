<?php

// JSON for the live crude oil rows on the home page (polled every 5 minutes)

require_once 'includes/config.php';
require_once 'includes/market.php';

// Set the response content type to JSON
header('Content-Type: application/json');

// Return crude oil prices, or an empty items array if no data is available
echo json_encode(
    crude_prices() ?: ['items' => []]
);
