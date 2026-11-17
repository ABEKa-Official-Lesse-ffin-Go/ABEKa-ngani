<?php // Run in Command Prompt: php tools/test_feed.php   (shows whether fetching works and how many rows were saved)
// Quick test script to verify RSS news feed parsing.
require __DIR__.'/../includes/config.php';require __DIR__.'/../includes/news.php';
echo "cURL available: ".(function_exists('curl_init')?'yes':'NO (enable extension=curl in php.ini)')."\n";
$items=rss_items(capitol_query());echo "Capitol feed returned ".count($items)." item(s)\n";foreach($items as $i)echo " - ".$i[0]." [".$i[3]."]\n";
if(!$items){$c=curl_init('https://news.google.com/');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>0]);curl_exec($c);echo "Test connection to news.google.com: HTTP ".curl_getinfo($c,CURLINFO_HTTP_CODE)." ".curl_error($c)."\n";}
refresh_announcements('capitol',null,capitol_query(),true);refresh_news('pampanga',null,'Pampanga',true);
foreach(q('SELECT id,name,slug FROM lgus')->fetchAll() as $l){refresh_announcements($l['slug'],$l['id'],lgu_ann_query($l['name']),true);refresh_news($l['slug'],$l['id'],'"'.$l['name'].'" Pampanga',true);}
echo "announcements saved: ".q('SELECT COUNT(*) c FROM announcements')->fetch()['c']."\n";echo "news saved: ".q('SELECT COUNT(*) c FROM news')->fetch()['c']."\n";
