<?php
// Standalone version
include('src/Publisher.php');

// Composer version
// require __DIR__ . '/vendor/autoload.php';

$publisher = new \Simounet\SteamScreenshotsPublisher\Publisher(
    '/path/to/steam/screenshots/steamscreenshots/',
    'screenshots/'
);
$publisher->publish();
