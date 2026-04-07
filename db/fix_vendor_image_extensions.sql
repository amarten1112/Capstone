-- Fix vendor image URLs: change .jpg to .webp
-- Run this in phpMyAdmin on the Ionos database

UPDATE vendors SET image_url = REPLACE(image_url, '.jpg', '.webp')
WHERE image_url LIKE '%.jpg';
