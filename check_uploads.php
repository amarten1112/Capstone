<?php
$path = __DIR__ . '/uploads/products/';
echo is_dir($path) ? 'Folder EXISTS' : 'Folder MISSING';
echo '<br>';
echo is_writable($path) ? 'Writable: YES' : 'Writable: NO';
