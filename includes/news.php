<?php // Auto-refreshes news (Inquirer) and announcements (Capitol / LGU) via Google News RSS. Title + link only. Each key is re-checked at most every 6 hours.

// Fetch a Google News RSS search and return items as [title, link, published(Y-m-d H:i:s), source]
function rss_items($query,$limit=5){
 $url='https://news.google.com/rss/search?hl=en-PH&gl=PH&ceid=PH:en&q='.urlencode($query);
 $xml=false;
 if(function_exists('curl_init')){$c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_SSL_VERIFYPEER=>0,CURLOPT_USERAGENT=>'Mozilla/5.0 (ABEKa)']);$xml=curl_exec($c);curl_close($c);}
 else $xml=@file_get_contents($url,false,stream_context_create(['http'=>['timeout'=>5,'user_agent'=>'Mozilla/5.0 (ABEKa)']]));
 $x=$xml?@simplexml_load_string($xml):false;if(!$x||!isset($x->channel->item))return[];
 $out=[];$i=0;
 foreach($x->channel->item as $it){if($i++>=$limit)break;
  $src=isset($it->source)?(string)$it->source:'Google News';
  $out[]=[(string)$it->title,(string)$it->link,date('Y-m-d H:i:s',strtotime((string)$it->pubDate)),$src];}
 return $out;
}

// Search queries used for announcements
function capitol_query(){return '"Provincial Government of Pampanga" OR "Capitol Pampanga" OR site:pampanga.gov.ph';}
function lgu_ann_query($name){return '"'.$name.'" Pampanga (advisory OR announcement OR ordinance OR "public notice")';}

function refresh_news($key,$lguId,$query,$force=false){
 $f=__DIR__.'/../cache/news_'.preg_replace('/\W/','',$key);
 $cnt=(int)q('SELECT COUNT(*) c FROM news WHERE '.($lguId?'lgu_id=?':'lgu_id IS NULL'),$lguId?[$lguId]:[])->fetch()['c'];
 $ttl=$cnt?21600:300; // retry every 5 min while empty, every 6 h otherwise
 if(!$force&&file_exists($f)&&time()-filemtime($f)<$ttl)return;
 @touch($f); // mark first so a failed fetch isn't retried on every page load
 foreach(rss_items('site:inquirer.net '.$query) as $it)
  q('INSERT IGNORE INTO news(lgu_id,title,url,published) VALUES(?,?,?,?)',[$lguId,$it[0],$it[1],$it[2]]);
}

// Same idea for announcements. Needs sql/update_announcements.sql (adds url + source columns).
function refresh_announcements($key,$lguId,$query,$force=false){
 $f=__DIR__.'/../cache/ann_'.preg_replace('/\W/','',$key);
 $cnt=(int)q('SELECT COUNT(*) c FROM announcements WHERE url IS NOT NULL AND '.($lguId?'lgu_id=?':'lgu_id IS NULL'),$lguId?[$lguId]:[])->fetch()['c'];
 $ttl=$cnt?21600:300;
 if(!$force&&file_exists($f)&&time()-filemtime($f)<$ttl)return;
 @touch($f);
 foreach(rss_items($query) as $it)
  q('INSERT IGNORE INTO announcements(lgu_id,title,body,url,source,posted) VALUES(?,?,?,?,?,?)',[$lguId,$it[0],$it[0],$it[1],mb_substr($it[3],0,80),$it[2]]);
}

// Link helpers for announcements: fetched items open the original article, manual ones open post.php
function ann_href($r){return !empty($r['url'])?$r['url']:'post.php?id='.(int)$r['id'];}
function ann_attr($r){return !empty($r['url'])?' target="_blank" rel="noopener"':'';}
