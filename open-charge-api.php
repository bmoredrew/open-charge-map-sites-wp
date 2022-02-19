<?php
/* 
Plugin Name: Open Charge Map API
Description: Pull data from Open Charge Map
Version: 1
*/

foreach ( \glob( __DIR__ . '/lib/*.php' ) as $file ) include $file;