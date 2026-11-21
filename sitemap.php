<?php
// SARVAMTA Food & Spices - Dynamic Sitemap XML Sender
// Fixes IIS 406 Not Acceptable error by serving proper application/xml headers
header("Content-Type: application/xml; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: public, max-age=3600");

$sitemapFile = __DIR__ . '/sitemap.xml';
if (file_exists($sitemapFile)) {
    readfile($sitemapFile);
} else {
    http_response_code(404);
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>Sitemap not found</error>";
}
exit;
