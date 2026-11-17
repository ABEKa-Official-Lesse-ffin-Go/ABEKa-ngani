<?php // Run in Command Prompt: php tools/test_market.php   (shows whether forex and fuel news downloads work on this PC)
// Quick test script to verify market data fetching.
require __DIR__.'/../includes/config.php';require __DIR__.'/../includes/market.php';
echo "cURL available: ".(function_exists('curl_init')?'yes':'NO (enable extension=curl in php.ini)')."\n";
foreach(['Forex'=>'https://open.er-api.com/v6/latest/USD'] as $n=>$u){
 if(!function_exists('curl_init')){echo "$n: skipped\n";continue;}
 $c=curl_init($u);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_SSL_VERIFYPEER=>0,CURLOPT_USERAGENT=>'Mozilla/5.0 (ABEKa)']);$r=curl_exec($c);
 echo "$n: HTTP ".curl_getinfo($c,CURLINFO_HTTP_CODE)." ".curl_error($c)." (".strlen((string)$r)." bytes) ".substr(preg_replace('/\s+/',' ',(string)$r),0,80)."\n";curl_close($c);}
@unlink(__DIR__.'/../cache/forex.json');$f=forex_rates();echo $f?'Forex OK: 1 USD = P'.number_format($f['rates']['USD'],2)."\n":"Forex: FAILED\n";
@unlink(__DIR__.'/../cache/fuel_news_pampanga.json');$h=fuel_headlines();echo 'Fuel headlines: '.count($h)."\n";foreach($h as $i)echo ' - '.$i[0]."\n";
