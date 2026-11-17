<?php // Home-page market widgets: forex rates (auto, free API) and Pampanga fuel prices (admin-entered) + latest pump price headlines (auto).
// Market data helper: fetches and caches forex and crude oil prices.

function market_cache_read($name){$f=__DIR__.'/../cache/'.$name;return file_exists($f)?['data'=>json_decode(@file_get_contents($f),true),'age'=>time()-filemtime($f)]:['data'=>null,'age'=>PHP_INT_MAX];}
function market_http($url){
 if(function_exists('curl_init')){$c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_SSL_VERIFYPEER=>0,CURLOPT_USERAGENT=>'Mozilla/5.0 (ABEKa)']);$r=curl_exec($c);curl_close($c);return $r;}
 return @file_get_contents($url,false,stream_context_create(['http'=>['timeout'=>5,'user_agent'=>'Mozilla/5.0 (ABEKa)']]));
}

// How many PHP one unit of each currency buys. Refreshed hourly; falls back to the last good copy if the API is down.
function forex_rates(){
 $codes=['USD'=>'US Dollar','EUR'=>'Euro','JPY'=>'Japanese Yen','GBP'=>'British Pound','SAR'=>'Saudi Riyal','AED'=>'UAE Dirham','SGD'=>'Singapore Dollar','KRW'=>'South Korean Won'];
 $c=market_cache_read('forex.json');
 if($c['data']&&$c['age']<3600)return $c['data']+['codes'=>$codes];
 $f=__DIR__.'/../cache/forex.json';@touch($f); // mark first so a failed fetch isn't retried on every page load
 $j=json_decode((string)market_http('https://open.er-api.com/v6/latest/USD'),true);
 if(!$j||($j['result']??'')!=='success'||empty($j['rates']['PHP'])){return $c['data']?$c['data']+['codes'=>$codes]:null;}
 $out=['updated'=>date('Y-m-d H:i:s'),'rates'=>[]];
 foreach($codes as $k=>$_)if(!empty($j['rates'][$k]))$out['rates'][$k]=$j['rates']['PHP']/$j['rates'][$k];
 file_put_contents($f,json_encode($out));
 return $out+['codes'=>$codes];
}

// Latest fuel price headlines: Pampanga stories first, topped up with national pump price news. 3 items, newest first, refreshed every 30 minutes.
function fuel_headlines(){
 $c=market_cache_read('fuel_news_pampanga.json');
 if($c['data']!==null&&$c['age']<1800)return $c['data'];
 $f=__DIR__.'/../cache/fuel_news_pampanga.json';@touch($f);
 if(!function_exists('rss_items'))require_once __DIR__.'/news.php';
 $byDate=function($x,$y){return strcmp($y[2],$x[2]);};
 $isPH=function($x){return !preg_match('/\$\d|nationwide|per gallon|\bAAA\b|forbes|by state/i',$x[0]);};
 $local=rss_items('(Pampanga OR "Central Luzon" OR "Angeles City" OR Mabalacat OR "San Fernando Pampanga") (gasoline OR diesel OR "pump prices" OR "fuel prices") when:14d',10);$local=array_values(array_filter($local,$isPH));usort($local,$byDate);
 $items=array_slice($local,0,3);
 if(count($items)<3){$seen=array_column($items,1);$nat=rss_items('Philippines DOE pump price adjustment gasoline diesel kerosene when:3d',10);$nat=array_values(array_filter($nat,$isPH));usort($nat,$byDate);
  foreach($nat as $n){if(count($items)>=3)break;if(!in_array($n[1],$seen)){$items[]=$n;$seen[]=$n[1];}}}
 if(!$items)return $c['data']?:[];
 file_put_contents($f,json_encode($items));return $items;
}

// Admin-entered Pampanga pump prices. Returns [] if the table doesn't exist yet.
function fuel_products(){return ['diesel'=>'Diesel','ron91'=>'Gasoline 91','ron95'=>'Premium 95','kerosene'=>'Kerosene','lpg'=>'LPG 11kg'];}
function fuel_prices(){try{$o=[];foreach(q('SELECT product,price,updated FROM fuel_prices')->fetchAll() as $r)$o[$r['product']]=$r;return $o;}catch(Throwable $e){return[];}}

// Next-week pump price forecast, entered by the admin (DOE/oil companies announce it every Monday). Stored in fuel_prices as fc_* rows (+ = increase, - = rollback).
function fuel_forecast_products(){return ['fc_gasoline'=>'Gasoline','fc_diesel'=>'Diesel','fc_kerosene'=>'Kerosene'];}

// Per-brand Pampanga pump prices (admin-entered). Stored in fuel_prices as "<brand>:<product>" rows, e.g. petron:diesel.
function fuel_brands(){return ['caltex'=>'Caltex','shell'=>'Shell','petron'=>'Petron'];}
function fuel_brand_products(){return ['diesel'=>'Diesel','ron91'=>'Gasoline 91','ron95'=>'Premium 95','kerosene'=>'Kerosene','lpg'=>'LPG 11kg'];}

// North Luzon brand prices: refresh the public FuelPrice.ph page once every 24 hours.
// The source changes its published pump-price data on adjustment days, so the date shown
// is the source's effective date (not the time this page was viewed).
function north_luzon_fuel_data(){
 $cacheFile=__DIR__.'/../cache/fuel_north_luzon.json';
 $cached=market_cache_read('fuel_north_luzon.json');
 if($cached['data'] && $cached['age']<86400)return $cached['data'];
 $html=market_http('https://www.fuelprice.ph/fuel-price/north-luzon');
 if($html){
  $text=strip_tags($html);$date=null;
  if(preg_match('/as of\\s+([A-Za-z]+\\s+\\d{1,2},\\s+\\d{4})/i',$text,$m)){$t=strtotime($m[1]);if($t)$date=date('Y-m-d',$t);}
  $brands=[];$averages=[];
  foreach(['ron91'=>'Gasoline RON 91','diesel'=>'Diesel','kerosene'=>'Kerosene'] as $key=>$label){if(preg_match('/'.preg_quote($label,'/').'.*?₱\s*([\d,]+\.\d{2})\s*\/L/is',$text,$m))$averages[$key]=(float)str_replace(',','',$m[1]);}
  if(preg_match('/Premium 95.*?₱\s*([\d,]+\.\d{2})/is',$text,$m))$averages['ron95']=(float)str_replace(',','',$m[1]);
  if(class_exists('DOMDocument')){
   $dom=new DOMDocument();$old=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);libxml_clear_errors();libxml_use_internal_errors($old);
   $xp=new DOMXPath($dom);
   foreach($xp->query('//table') as $table){$headers=[];$rows=$xp->query('.//tr',$table);$rowIndex=0;foreach($rows as $tr){$cells=[];foreach($tr->childNodes as $node)if(in_array(strtolower($node->nodeName),['td','th']))$cells[]=trim(preg_replace('/\s+/u',' ',$node->textContent));if(!$cells)continue;
    if($rowIndex++===0){$headers=array_map(function($h){return strtolower(preg_replace('/[^a-z0-9]/i','',$h));},$cells);continue;}
    $brand=strtolower(preg_replace('/[^a-z]/','',$cells[0]));if(!in_array($brand,['caltex','shell','petron']))continue;$row=[];
    foreach(array_slice($cells,1) as $i=>$v){$h=$headers[$i+1]??'';$v=str_replace([',','₱'],'',$v);$row[$h]=is_numeric($v)?(float)$v:null;}
    $pick=function($contains)use($row){foreach($row as $h=>$v)if(strpos($h,$contains)!==false)return $v;return null;};
    $brands[$brand]=['ron91'=>$pick('ron91'),'ron95'=>$pick('ron95'),'diesel'=>$pick('diesel'),'kerosene'=>$pick('kerosene')];
   }if($brands)break;}
  }
  if(preg_match('/LPG\\s*11\\s*kg.*?₱?\\s*([\\d,]+\\.\\d{2})\\s*\\/tank/is',$text,$m))$averages['lpg']=(float)str_replace(',','',$m[1]);
  if($brands && $date){$out=['source'=>'https://www.fuelprice.ph/fuel-price/north-luzon','effective_date'=>$date,'fetched_at'=>date('Y-m-d H:i:s'),'brands'=>$brands,'averages'=>$averages];@file_put_contents($cacheFile,json_encode($out));return $out;}
 }
 if($cached['data'])return $cached['data'];
 return ['source'=>'https://www.fuelprice.ph/fuel-price/north-luzon','effective_date'=>null,'brands'=>[],'averages'=>[]];
}
