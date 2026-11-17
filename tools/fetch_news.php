<?php // Optional manual run: php tools/fetch_news.php  (the site also refreshes itself automatically)
// CLI/helper script to refresh cached local news feeds.
require __DIR__.'/../includes/config.php';require __DIR__.'/../includes/news.php';
refresh_news('pampanga',null,'Pampanga',true);
foreach(q('SELECT id,name,slug FROM lgus')->fetchAll() as $l)refresh_news($l['slug'],$l['id'],'"'.$l['name'].'" Pampanga',true);
refresh_announcements('capitol',null,capitol_query(),true);
foreach(q('SELECT id,name,slug FROM lgus')->fetchAll() as $l)refresh_announcements($l['slug'],$l['id'],lgu_ann_query($l['name']),true);
echo "Done\n";
