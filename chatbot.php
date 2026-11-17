<?php
// Bida: warm, direct, paragraph-style assistant for the ABEKa platform.
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/tourist-spot.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Save interaction to chatbot_logs (for both logged-in users and guests) and send JSON response.
function chat_json($reply, $status = 200)
{
    if ($status === 200 && !empty($GLOBALS['abeka_chat_user_msg'])) {
        try {
            $cu = user();
            q(
                'INSERT INTO chatbot_logs (user_id, user_name, user_role, session_id, user_message, bot_reply) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $cu['id'] ?? null,
                    $cu['name'] ?? 'Guest',
                    $cu['role'] ?? 'guest',
                    session_id(),
                    $GLOBALS['abeka_chat_user_msg'],
                    (string)$reply
                ]
            );
        } catch (Throwable $e) {
            error_log('ABEKa chatbot_logs insert: ' . $e->getMessage());
        }
    }
    http_response_code($status);
    echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chat_json('Send me a message anytime and I will be happy to help you.', 405);
}

$token = (string)($_SERVER['HTTP_X_ABEKA_TOKEN'] ?? '');
if (empty($_SESSION['chatbot_token']) || !hash_equals($_SESSION['chatbot_token'], $token)) {
    chat_json('It looks like your chat session timed out. Please refresh the page and send your message again.', 403);
}

// Basic per-session rate limit to prevent accidental or abusive request bursts.
$now = time();
$_SESSION['chatbot_requests'] = array_values(array_filter(
    $_SESSION['chatbot_requests'] ?? [],
    static function ($time) use ($now) {
        return $time > $now - 60;
    }
));
if (count($_SESSION['chatbot_requests']) >= 25) {
    chat_json('You have sent quite a few messages in a short time. Please wait a minute before trying again.', 429);
}
$_SESSION['chatbot_requests'][] = $now;

// Handle clearing the active chat progression view (keeps logs in database)
if (($_POST['action'] ?? '') === 'clear') {
    try {
        $cu = user();
        if ($cu) {
            q('UPDATE chatbot_logs SET is_cleared = 1 WHERE user_id = ? OR session_id = ?', [$cu['id'], session_id()]);
        } else {
            q('UPDATE chatbot_logs SET is_cleared = 1 WHERE session_id = ?', [session_id()]);
        }
    } catch (Throwable $e) {
        error_log('ABEKa chatbot_logs clear: ' . $e->getMessage());
    }
    chat_json('Cleared.', 200);
}

$message = trim((string)($_POST['message'] ?? ''));
if ($message === '' || mb_strlen($message) > 500) {
    chat_json('Please enter a short message of up to 500 characters so I can help you.', 422);
}
$GLOBALS['abeka_chat_user_msg'] = $message;

// Preserve decimal numbers (e.g. 93.53) while normalizing punctuation.
$rawLower = mb_strtolower($message, 'UTF-8');
$normal = preg_replace('/[^\p{L}\p{N}\s.-]/u', ' ', $rawLower);
$normal = preg_replace('/\s+/u', ' ', trim($normal));
$normal = str_replace(['re apply', 're-apply', 'e learning', 'e-learning', 'check mark', 'pag ibig', 'pag-ibig'], ['reapply', 'reapply', 'elearning', 'elearning', 'checkmark', 'pagibig', 'pagibig'], $normal);

// Check whether any keyword appears in the normalized message.
function chat_has($text, $words)
{
    foreach ($words as $word) {
        if (mb_strpos($text, $word, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    return false;
}

// Run a safe database query and return rows.
function chat_rows($sql, $params = [])
{
    try {
        return q($sql, $params)->fetchAll();
    } catch (Throwable $e) {
        error_log('ABEKa chatbot query: ' . $e->getMessage());
        return [];
    }
}

// Load and decode a JSON cache file safely.
function chat_load_json($relPath)
{
    $file = __DIR__ . '/' . ltrim($relPath, '/');
    if (!is_file($file)) {
        return null;
    }
    $decoded = json_decode((string)file_get_contents($file), true);
    return is_array($decoded) ? $decoded : null;
}

// Save the active conversation topic in session for follow-up questions.
function chat_set_ctx($topic, $extra = [])
{
    $_SESSION['bida_context'] = array_merge(['topic' => $topic, 'at' => time()], $extra);
}

// Join an array of items into a natural English list.
function chat_join_list($items)
{
    $count = count($items);
    if ($count === 0) {
        return '';
    }
    if ($count === 1) {
        return $items[0];
    }
    if ($count === 2) {
        return $items[0] . ' and ' . $items[1];
    }
    $last = array_pop($items);
    return implode(', ', $items) . ', and ' . $last;
}

$ctx = $_SESSION['bida_context'] ?? [];
$currentUser = user();
$isEmployee = ($currentUser && $currentUser['role'] === 'employee');

// Build a natural paragraph response for fuel prices from cache/fuel_north_luzon.json.
function chat_fuel_reply($productKey = null, $brandKey = null, $guessedNum = null)
{
    $fuelLabels = [
        'diesel'   => 'Diesel',
        'ron91'    => 'Gasoline 91',
        'ron95'    => 'Premium 95',
        'kerosene' => 'Kerosene',
        'lpg'      => 'LPG 11 kg',
    ];

    $json = chat_load_json('cache/fuel_north_luzon.json');
    if ($json && (!empty($json['averages']) || !empty($json['brands']))) {
        $eff = !empty($json['effective_date']) ? date('M j, Y', strtotime($json['effective_date'])) : 'the latest update';
        $avgs = $json['averages'] ?? [];
        $brands = $json['brands'] ?? [];

        if ($guessedNum !== null) {
            $g = round((float)$guessedNum, 2);
            $matches = [];
            foreach ($avgs as $k => $v) {
                if ($v !== null && abs((float)$v - $g) < 0.015) {
                    $matches[] = ($fuelLabels[$k] ?? strtoupper($k)) . ' North Luzon average';
                }
            }
            foreach ($brands as $bName => $bRates) {
                foreach ($bRates as $k => $v) {
                    if ($v !== null && abs((float)$v - $g) < 0.015) {
                        $matches[] = ucfirst($bName) . ' ' . ($fuelLabels[$k] ?? strtoupper($k));
                    }
                }
            }
            if ($matches) {
                return "Yes, you got it right. ₱" . number_format($g, 2) . " is the " . chat_join_list($matches) . " as of {$eff}.";
            }
            if ($productKey && isset($avgs[$productKey])) {
                $actual = (float)$avgs[$productKey];
                $unit = $productKey === 'lpg' ? ' per 11 kg tank' : ' per liter';
                return "Not quite. According to our cached North Luzon data effective {$eff}, " .
                    ($fuelLabels[$productKey] ?? $productKey) . " averages ₱" . number_format($actual, 2) . $unit . ".";
            }
        }

        if ($brandKey && isset($brands[$brandKey])) {
            $bData = $brands[$brandKey];
            $bTitle = ucfirst($brandKey);
            if ($productKey && array_key_exists($productKey, $bData)) {
                if ($bData[$productKey] === null) {
                    $avgTxt = isset($avgs[$productKey]) ? ' The North Luzon average is ₱' . number_format((float)$avgs[$productKey], 2) . '.' : '';
                    return "{$bTitle} does not list a separate " . ($fuelLabels[$productKey] ?? $productKey) . " price in our latest table effective {$eff}.{$avgTxt}";
                }
                return "{$bTitle} " . ($fuelLabels[$productKey] ?? $productKey) . " is currently ₱" . number_format((float)$bData[$productKey], 2) . " per liter based on the North Luzon reference effective {$eff}.";
            }
            $parts = [];
            foreach ($bData as $k => $v) {
                if ($v !== null) {
                    $parts[] = ($fuelLabels[$k] ?? $k) . ' is ₱' . number_format((float)$v, 2) . ' per liter';
                }
            }
            return "For {$bTitle} as of {$eff}, " . chat_join_list($parts) . ".";
        }

        if ($productKey && isset($avgs[$productKey])) {
            $label = $fuelLabels[$productKey] ?? ucfirst($productKey);
            $unit = $productKey === 'lpg' ? ' per 11 kg tank' : ' per liter';
            $brandParts = [];
            foreach (['petron', 'shell', 'caltex'] as $b) {
                if (isset($brands[$b][$productKey]) && $brands[$b][$productKey] !== null) {
                    $brandParts[] = ucfirst($b) . ' is at ₱' . number_format((float)$brands[$b][$productKey], 2);
                }
            }
            $brandText = $brandParts ? ' By brand, ' . chat_join_list($brandParts) . '.' : '';
            return "As of {$eff}, the North Luzon average for {$label} is ₱" . number_format((float)$avgs[$productKey], 2) . $unit . ".{$brandText} Actual pump prices may vary slightly by station in Pampanga.";
        }

        $parts = [];
        foreach ($avgs as $k => $v) {
            if ($v !== null) {
                $unit = $k === 'lpg' ? ' per 11 kg tank' : ' per liter';
                $parts[] = ($fuelLabels[$k] ?? $k) . ' at ₱' . number_format((float)$v, 2) . $unit;
            }
        }
        return "As of {$eff}, the North Luzon fuel reference averages are " . chat_join_list($parts) . ". You can also ask me for Caltex, Shell, or Petron prices.";
    }

    return "Fuel updates for Caltex, Shell, and Petron are shown on the Home page once the latest North Luzon reference prices are loaded.";
}

// Build live ABEKa context and call Google Gemini API when configured in includes/ai_config.php.
function chat_ask_gemini($userMessage, $isEmployee, $currentUser, $touristSpots)
{
    $cfgFile = __DIR__ . '/includes/ai_config.php';
    if (!is_file($cfgFile) || !function_exists('curl_init')) {
        return null;
    }
    $cfg = require $cfgFile;
    $apiKey = trim((string)($cfg['gemini_api_key'] ?? ''));
    $model = trim((string)($cfg['gemini_model'] ?? 'gemini-3.5-flash-lite'));
    if ($apiKey === '') {
        return null;
    }

    // Gather live ABEKa context (fuel, forex, jobs, LGUs, tourist spots) without exposing private user data.
    $role = $currentUser['role'] ?? 'guest';
    $fuel = chat_load_json('cache/fuel_north_luzon.json');
    $fuelSummary = $fuel ? json_encode($fuel, JSON_UNESCAPED_UNICODE) : 'unavailable';
    $forex = chat_load_json('cache/forex.json');
    $forexSummary = $forex ? json_encode($forex, JSON_UNESCAPED_UNICODE) : 'unavailable';

    $jobsList = chat_rows('SELECT j.title, j.location, j.industry, j.salary_offer, ep.company FROM jobs j LEFT JOIN employer_profiles ep ON ep.user_id = j.employer_id ORDER BY j.created DESC LIMIT 8');
    $jobsSummary = $jobsList ? json_encode($jobsList, JSON_UNESCAPED_UNICODE) : 'none posted';

    $spotSummary = [];
    foreach ($touristSpots as $slug => $list) {
        $spotSummary[$slug] = array_map(static function ($s) {
            return $s['name'];
        }, $list);
    }

    $systemPrompt =
        "You are Bida, a warm, playful, and helpful assistant for ABEKa (ABEKa For Kapampangan), a Pampanga community, job, and civic portal. " .
        "Keep every reply super short—just 1 to 3 warm, natural, conversational sentences (under 45 words) like a friendly human helper. Answer only what was asked, in plain sentences with no bullet points, lists, asterisks, markdown, or raw .php filenames. " .
        "Current user role: {$role}. " .
        "ABEKa features: Home (About Us with Kulitan-inspired logo, Most Viewed Places top 3 by real clicks, See all towns), News, Announcements, Alerts (911 and Pampanga LGU/PNP/DRRMO hotlines), Gov Offices (public locator with Google Maps directions for NBI, Police, Barangay, National ID, SSS/UMID, Pag-IBIG at Robinsons Starmills, PhilHealth, TIN/BIR, and PSA at SACOP Maimpis near Calcutta), Employment (Jobs Hiring with Estimated Salary Offer, Employee Profile with 9 document uploads including PSA Birth Certificate, Employer green checkmark checklist, Hired Congratulations notification, Inbox direct messaging, 14-day Hired offer expiration when hired by multiple companies, 1 active application per company rule, 30-day rejection cooldown, 6-month hired cooldown), E-Learnings (Employee login only: Lean Six Sigma, TESDA, free Google/Coursera/Microsoft certs, 200-item shuffled Civil Service Reviewer with Quick 10 mode, and Employment Development YouTube guides), Forgot Password (all roles), Dashboard, and Admin Audit Trail. " .
        "Live Fuel Cache: {$fuelSummary}. " .
        "Live Forex Cache: {$forexSummary}. " .
        "Latest Jobs: {$jobsSummary}. " .
        "Pampanga Tourist Spots by town slug: " . json_encode($spotSummary, JSON_UNESCAPED_UNICODE) . ".";

    // Maintain short session conversation history (last 6 turns).
    $history = $_SESSION['bida_ai_history'] ?? [];
    $contents = [];
    foreach (array_slice($history, -6) as $turn) {
        $contents[] = [
            'role' => $turn['role'],
            'parts' => [['text' => $turn['text']]],
        ];
    }
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $userMessage]],
    ];

    $payload = json_encode([
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents' => $contents,
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 110],
    ], JSON_UNESCAPED_UNICODE);

    $modelsToTry = array_values(array_unique([$model, 'gemini-3.5-flash-lite', 'gemini-3.8-flash']));
    $raw = false;
    $httpCode = 0;

    foreach ($modelsToTry as $tryModel) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($tryModel) . ':generateContent?key=' . rawurlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw !== false && $httpCode === 200) {
            break;
        }
        error_log("ABEKa Gemini fallback [{$tryModel}] (HTTP {$httpCode}): " . ($curlErr ?: substr((string)$raw, 0, 200)));
    }

    if ($raw === false || $httpCode !== 200) {
        return null;
    }

    $resp = json_decode($raw, true);
    $replyText = trim((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    if ($replyText === '') {
        return null;
    }

    // Strip any accidental markdown asterisks if returned.
    $replyText = str_replace(['**', '*'], '', $replyText);

    $history[] = ['role' => 'user', 'text' => $userMessage];
    $history[] = ['role' => 'model', 'text' => $replyText];
    $_SESSION['bida_ai_history'] = array_slice($history, -6);

    return $replyText;
}

// Try Google Gemini first if configured; automatically fall back to built-in Bida rules if offline or unconfigured.
$aiReply = chat_ask_gemini($message, $isEmployee, $currentUser, $touristSpots);
if ($aiReply !== null) {
    chat_json($aiReply);
}

// 1. Greetings, identity, and thanks.
if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening|kumusta|kamusta)\b/u', $normal)) {
    chat_json("Kumusta! I am Bida, your friendly ABEKa guide. You can ask me about job openings, how employers and applicants connect, Pampanga tourist spots, emergency hotlines, nearest government offices, fuel prices, exchange rates, or the Civil Service Reviewer. What would you like to know today?");
}

if (chat_has($normal, ['who are you', 'what is bida', 'why bida', 'your name'])) {
    chat_json("I am Bida, a playful take on being the hero who always comes to the rescue on ABEKa. I am here to help you explore Pampanga towns, tourist spots, jobs, e-learning tools, and public services.");
}

if (preg_match('/^(thanks|thank you|salamat|ty|ok|okay|got it)\b/u', $normal)) {
    chat_json("You are very welcome. Just send me a message anytime if you need help with anything else on ABEKa.");
}

// 2. Context-aware follow-up for fuel numbers or brands.
$hasNumberFollowup = preg_match('/\b(\d+(?:\.\d+)?)\b/', $normal, $numMatch);

if (($ctx['topic'] ?? '') === 'fuel' && ($hasNumberFollowup || chat_has($normal, ['petron', 'shell', 'caltex', 'ron91', 'ron95', 'diesel', 'kerosene', 'lpg']))) {
    $prod = $ctx['product'] ?? null;
    if (chat_has($normal, ['diesel'])) $prod = 'diesel';
    elseif (chat_has($normal, ['91', 'ron91', 'unleaded'])) $prod = 'ron91';
    elseif (chat_has($normal, ['95', 'ron95', 'premium'])) $prod = 'ron95';
    elseif (chat_has($normal, ['kerosene'])) $prod = 'kerosene';
    elseif (chat_has($normal, ['lpg', 'gasul'])) $prod = 'lpg';

    $brand = null;
    if (chat_has($normal, ['petron'])) $brand = 'petron';
    elseif (chat_has($normal, ['shell'])) $brand = 'shell';
    elseif (chat_has($normal, ['caltex'])) $brand = 'caltex';

    $guess = $hasNumberFollowup ? $numMatch[1] : null;
    chat_set_ctx('fuel', ['product' => $prod, 'brand' => $brand]);
    chat_json(chat_fuel_reply($prod, $brand, $guess));
}

// 3. Tourist Spots & LGU lookup (including Guagua and every Pampanga city/town).
$lgus = chat_rows('SELECT name, slug FROM lgus ORDER BY name');
$matchedLgu = null;
foreach ($lgus as $lgu) {
    $nameLower = mb_strtolower($lgu['name'], 'UTF-8');
    $shortLower = trim(str_replace(' city', '', $nameLower));
    if (mb_strpos($normal, $nameLower, 0, 'UTF-8') !== false || ($shortLower !== '' && mb_strpos($normal, $shortLower, 0, 'UTF-8') !== false)) {
        $matchedLgu = $lgu;
        break;
    }
}

$askingTourist = chat_has($normal, ['tourist', 'spot', 'spots', 'attraction', 'attractions', 'visit', 'travel', 'resort', 'resorts', 'church', 'churches', 'pasyalan', 'landmark', 'landmarks', 'places in']);

if ($askingTourist && $matchedLgu) {
    $slug = $matchedLgu['slug'];
    $spots = $touristSpots[$slug] ?? [];
    chat_set_ctx('lgu', ['lgu' => $matchedLgu['name']]);
    if ($spots) {
        $spotNames = array_map(static function ($s) {
            return $s['name'];
        }, $spots);
        chat_json("In {$matchedLgu['name']}, you can visit " . chat_join_list($spotNames) . ". To see their photos and open their Google Maps directions, go to the Home page, open {$matchedLgu['name']} under Most viewed places or See all, and scroll down to Tourist Spots Recommendation.");
    }
    chat_json("You can explore {$matchedLgu['name']} on the Home page under Most viewed places or See all to view its latest local news and announcements.");
}

if ($askingTourist) {
    chat_set_ctx('tourist');
    chat_json("Every Pampanga city and municipality page on ABEKa includes Tourist Spots Recommendations with photos and Google Maps links. For example, in Guagua you can check out Betes and local heritage spots like St. James the Apostle Parish Church and nearby destinations. Just go to the Home page, click any town under Most viewed places or See all, or ask me about tourist spots in a specific town like Guagua, Angeles City, or Sasmuan.");
}

// 4. Nearest Government Offices Locator.
$govReqMap = [
    'psa'         => ['psa', 'nso', 'birth certificate', 'cenomar', 'statistics authority'],
    'nbi'         => ['nbi'],
    'police'      => ['police clearance'],
    'barangay'    => ['barangay clearance'],
    'national_id' => ['national id', 'philsys'],
    'sss'         => ['sss', 'umid'],
    'pagibig'     => ['pag-ibig', 'pagibig', 'pag ibig', 'hdmf'],
    'philhealth'  => ['philhealth'],
    'tin'         => ['tin', 'bir', 'rdo'],
];

$matchedGovReq = null;
foreach ($govReqMap as $reqKey => $words) {
    if (chat_has($normal, $words)) {
        $matchedGovReq = $reqKey;
        break;
    }
}

$askingWhereToGet = chat_has($normal, ['where', 'get', 'apply for', 'obtain', 'renew', 'nearest', 'office', 'offices', 'branch', 'location', 'direction', 'directions', 'gov office', 'gov offices', 'sacop', 'sakop', 'calcutta', 'starmills']);
$askingUploadOnly = chat_has($normal, ['upload', 'my profile', 'check mark', 'checkmark', 'what documents', 'required documents']);

if ($askingWhereToGet && ($matchedGovReq || chat_has($normal, ['gov office', 'gov offices', 'government office', 'government offices', 'nearest', 'direction', 'directions', 'sacop', 'sakop', 'calcutta', 'starmills', 'bir', 'rdo'])) && !$askingUploadOnly) {
    chat_set_ctx('gov_offices');
    $reqNames = [
        'psa'         => 'your PSA Birth Certificate, such as the PSA CRS Outlet at SACOP in Barangay Maimpis, City of San Fernando near Calcutta',
        'nbi'         => 'your NBI Clearance',
        'police'      => 'your Police Clearance',
        'barangay'    => 'your Barangay Clearance',
        'national_id' => 'your National ID',
        'sss'         => 'SSS or UMID services',
        'pagibig'     => 'Pag-IBIG Fund, such as the branch at Robinsons Starmills Pampanga near Mexico',
        'philhealth'  => 'PhilHealth services',
        'tin'         => 'your TIN ID at the BIR office',
    ];
    if ($matchedGovReq) {
        chat_json("You can find the nearest Pampanga office for " . $reqNames[$matchedGovReq] . " by opening the Gov Offices tab at the top of the page, which anyone can use even without logging in. Simply choose your city or municipality, select the document you need, and click Directions to open Google Maps.");
    }
    chat_json("You can open the Gov Offices tab in the top navigation bar without logging in to find the nearest Pampanga offices for PSA, NBI, Police, Barangay, National ID, SSS, Pag-IBIG, PhilHealth, and TIN ID along with Google Maps directions.");
}

// 4B. Specific Reapply & Cooldown Questions (checks live employee application history if logged in).
if (chat_has($normal, ['reapply', 'apply again', 'cooldown', 'rejected', 'not selected', 'withdrew', 'withdraw'])) {
    chat_set_ctx('reapply');
    if ($isEmployee) {
        $recentApps = chat_rows(
            "SELECT a.status, COALESCE(a.status_changed, a.created) AS decided_at, j.title, COALESCE(ep.company, emp.name) AS company_name
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN users emp ON emp.id = j.employer_id
             LEFT JOIN employer_profiles ep ON ep.user_id = j.employer_id
             WHERE a.employee_id = ?
             ORDER BY COALESCE(a.status_changed, a.created) DESC
             LIMIT 10",
            [$currentUser['id']]
        );
        $waits = [];
        foreach ($recentApps as $ra) {
            if ($ra['status'] === 'Not Selected') {
                $until = strtotime($ra['decided_at']) + 30 * 86400;
                if ($until > time()) {
                    $waits[] = $ra['title'] . ' at ' . $ra['company_name'] . ' after ' . date('M j, Y', $until);
                }
            } elseif (in_array($ra['status'], ['Hired', 'Offer Accepted', 'Expired'], true)) {
                $until = strtotime('+6 months', strtotime($ra['decided_at']));
                if ($until > time()) {
                    $waits[] = $ra['company_name'] . ' after ' . date('M j, Y', $until);
                }
            }
        }
        if ($waits) {
            chat_json("Yes, you can reapply once your waiting period finishes. Right now, your account has active cooldowns for " . chat_join_list($waits) . ". In general on ABEKa, you can reapply to a rejected job or company after 30 days, reapply to a company that hired you after 6 months, or apply anytime to the same job role at a different company where you have no active application.");
        }
    }
    chat_json("Yes, you can reapply on ABEKa depending on your application status. If you were not selected or rejected, you can reapply to that same job or company after 30 days. If you were hired by a company, you can reapply to that same company after 6 months. If you withdrew an active application, you can apply again anytime, and you are always welcome to apply for the same job role across different companies as long as you only have one active application per company at a time.");
}

// 5. Employer-to-Employee Connection, Salary Offer, and Hiring Rules.
if (chat_has($normal, ['connect', 'how employer', 'how do employer', 'employee to employer', 'employer to employee', 'salary', 'expire', 'expires', '14 days', '2 weeks', '30 days', '6 months', 'reapply', 're-apply', 'rejected', 'same company', 'multiple job', 'multiple jobs', 'accept offer'])) {
    chat_set_ctx('workflow');
    chat_json("ABEKa connects employers and job seekers in a clear step-by-step flow. Employers post job openings from their dashboard with an Estimated Salary Offer so applicants can see the expected pay right away. Once logged in as an Employee, you can browse openings under Employment and then Jobs Hiring, where you are allowed to apply for the same job role across different companies, though you can only hold one active application per company at a time. Employers review your resume, check a green checkmark list of your uploaded clearances and IDs, and message you directly with interview or onboarding details that you can read and reply to in your Inbox. If an employer marks you as Hired, you receive a Congratulations notification, and if you get hired by multiple companies you have 14 days to accept an offer in your Inbox before unaccepted offers expire. Finally, if an application is not selected, you can reapply to that job or company after 30 days, and if you were hired by a company you can reapply to that same company after 6 months.");
}

// 6. Website Troubleshooting.
if (chat_has($normal, ['troubleshoot', 'problem', 'issue', 'error', 'not working', 'cant', 'cannot', 'unable', 'fix', 'broken', 'xampp', 'mysql', 'apache', 'phpmyadmin', 'not showing', 'not loading', 'failed'])) {
    chat_set_ctx('troubleshooting');
    chat_json("If something is not working properly, first check that Apache and MySQL are both running in XAMPP and that the database SQL files have been imported in phpMyAdmin. Remember that Jobs Hiring and E-Learnings only appear when you are logged in with an Employee account, and ABEKa prevents applying to a second job at the same company while you still have an active application there or are within a 30-day rejection or 6-month hired cooldown. If a document upload fails, make sure the file is a PDF, JPG, PNG, or WEBP under 5 MB. If your password reset email does not arrive, check your mail configuration or use the local test link shown on the Forgot Password page when running on localhost, and make sure you have an internet connection for Google Maps in Alerts and Gov Offices.");
}

// 7. Full website overview.
if (chat_has($normal, ['what can you do', 'help me', 'help', 'features', 'what do you know', 'how to use abeka', 'website', 'overview'])) {
    chat_json("ABEKa brings together everything you need in Pampanga in one place. You can click Home to read our About Us story and browse the most viewed Pampanga cities and towns along with their local news, announcements, and tourist spots. You can also check the News and Announcements tabs, call 911 or look up local emergency hotlines under Alerts, and find directions to nearby government offices under Gov Offices without needing an account. When logged in as an Employee, you can upload your clearances and IDs in My Profile, apply for jobs with Estimated Salary Offers, message employers in your Inbox, and use E-Learnings for Lean Six Sigma, TESDA, free online certifications, our 200-item Civil Service Reviewer, and Employment Development videos.");
}

// 8. Fuel prices.
if (chat_has($normal, ['fuel', 'gasoline', 'diesel', 'petrol', 'lpg', 'kerosene', 'pump price', 'ron91', 'ron95', 'ron 91', 'ron 95', 'caltex', 'shell', 'petron', 'crude'])) {
    $prod = null;
    if (chat_has($normal, ['diesel'])) $prod = 'diesel';
    elseif (chat_has($normal, ['91', 'ron91', 'ron 91', 'unleaded'])) $prod = 'ron91';
    elseif (chat_has($normal, ['95', 'ron95', 'ron 95', 'premium'])) $prod = 'ron95';
    elseif (chat_has($normal, ['kerosene'])) $prod = 'kerosene';
    elseif (chat_has($normal, ['lpg', '11kg', 'tank'])) $prod = 'lpg';

    $brand = null;
    if (chat_has($normal, ['petron'])) $brand = 'petron';
    elseif (chat_has($normal, ['shell'])) $brand = 'shell';
    elseif (chat_has($normal, ['caltex'])) $brand = 'caltex';

    $guess = $hasNumberFollowup && !chat_has($normal, ['91', '95', '11']) ? $numMatch[1] : null;
    chat_set_ctx('fuel', ['product' => $prod, 'brand' => $brand]);
    chat_json(chat_fuel_reply($prod, $brand, $guess));
}

// 9. Forex exchange rates & conversion.
if (chat_has($normal, ['forex', 'exchange rate', 'exchange rates', 'currency', 'usd', 'eur', 'jpy', 'gbp', 'sar', 'aed', 'sgd', 'krw', 'dollar', 'peso', 'convert'])) {
    $data = chat_load_json('cache/forex.json');
    if (empty($data['rates'])) {
        chat_json("I do not have the latest exchange rates cached right now, so please check the Forex section on the Home page in a little while.");
    }
    $rates = $data['rates'];
    $currency = null;
    foreach (array_keys($rates) as $code) {
        if (preg_match('/\b' . preg_quote(mb_strtolower($code), '/') . '\b/u', $normal)) {
            $currency = $code;
            break;
        }
    }
    if (!$currency && chat_has($normal, ['dollar'])) {
        $currency = 'USD';
    }

    chat_set_ctx('forex', ['currency' => $currency]);

    if ($currency && isset($rates[$currency])) {
        $rate = (float)$rates[$currency];
        if (preg_match('/\b(\d+(?:\.\d+)?)\s*' . preg_quote(mb_strtolower($currency), '/') . '\b/u', $normal, $amtMatch) || preg_match('/\b(\d+(?:\.\d+)?)\b/', $normal, $amtMatch)) {
            $amt = (float)$amtMatch[1];
            $converted = $amt * $rate;
            chat_json("{$amt} {$currency} is approximately ₱" . number_format($converted, 2) . " based on the cached rate of 1 {$currency} to ₱" . number_format($rate, $rate < 1 ? 4 : 2) . ", last updated " . ($data['updated'] ?? 'recently') . ".");
        }
        chat_json("Right now, 1 {$currency} is approximately ₱" . number_format($rate, $rate < 1 ? 4 : 2) . ", last updated " . ($data['updated'] ?? 'recently') . ". You can also ask me to convert an amount such as 100 USD to PHP.");
    }

    $parts = [];
    foreach ($rates as $code => $value) {
        $parts[] = '1 ' . $code . ' is ₱' . number_format((float)$value, $value < 1 ? 4 : 2);
    }
    chat_json("The exchange rates currently cached on ABEKa are " . chat_join_list($parts) . ", last updated " . ($data['updated'] ?? 'recently') . ".");
}

// 10. Emergency Alerts & Hotlines.
if (chat_has($normal, ['alert', 'alerts', 'emergency', 'hotline', 'hotlines', '911', 'pnp', 'mdrrmo', 'cdrrmo', 'pdrrmo', 'rescue', 'disaster'])) {
    $allAreas = [
        'mabalacat' => 'Mabalacat City', 'magalang' => 'Magalang',
        'floridablanca' => 'Floridablanca', 'guagua' => 'Guagua', 'lubao' => 'Lubao',
        'porac' => 'Porac', 'santa rita' => 'Santa Rita', 'sta rita' => 'Santa Rita',
        'sasmuan' => 'Sasmuan', 'arayat' => 'Arayat', 'bacolor' => 'Bacolor',
        'san fernando' => 'City of San Fernando', 'csfp' => 'City of San Fernando',
        'mexico' => 'Mexico', 'santa ana' => 'Santa Ana', 'sta ana' => 'Santa Ana',
        'apalit' => 'Apalit', 'candaba' => 'Candaba', 'macabebe' => 'Macabebe',
        'masantol' => 'Masantol', 'minalin' => 'Minalin', 'san luis' => 'San Luis',
        'san simon' => 'San Simon', 'santo tomas' => 'Santo Tomas', 'sto tomas' => 'Santo Tomas',
        'angeles' => 'Angeles City', 'pg-pampanga' => 'PG-Pampanga', 'provincial' => 'PG-Pampanga',
    ];
    $matchedArea = null;
    foreach ($allAreas as $needle => $canonical) {
        if (mb_strpos($normal, $needle, 0, 'UTF-8') !== false) {
            $matchedArea = $canonical;
            break;
        }
    }
    chat_set_ctx('alerts', ['area' => $matchedArea]);
    if ($matchedArea) {
        $rows = chat_rows('SELECT service, office_name, hotline FROM emergency_hotlines WHERE municipality = ? ORDER BY service, id', [$matchedArea]);
        if ($rows) {
            $parts = [];
            foreach ($rows as $r) {
                $parts[] = $r['office_name'] . ' (' . $r['service'] . ') at ' . $r['hotline'];
            }
            chat_json("For urgent emergencies, please call 911 right away. For {$matchedArea}, the local emergency contacts are " . chat_join_list($parts) . ". You can also open the Alerts tab at the top to filter by service or view the map.");
        }
    }
    chat_json("For urgent emergencies, please call 911 immediately. You can open the Alerts tab in the top navigation bar to search LGU, PNP, and DRRMO hotlines across all four Pampanga districts, PG-Pampanga, and Angeles City, or just ask me for a specific town like Guagua.");
}

// 11. E-Learning, Civil Service Reviewer, and Employment Development.
if (chat_has($normal, ['elearning', 'e-learning', 'e learning', 'e-learnings', 'elearnings', 'civil service', 'cse', 'reviewer', 'tesda', 'lean six sigma', 'six sigma', 'coursera', 'microsoft learn', 'google skillshop', 'youtube', 'employment development', 'interview prep', 'dress well', 'study', 'certification', 'certifications'])) {
    chat_set_ctx('elearning');
    if (!$isEmployee) {
        chat_json("Our E-Learnings section includes local Lean Six Sigma programs, TESDA certifications, free Google, Coursera, and Microsoft courses, a 200-item Civil Service Reviewer, and Employment Development YouTube guides. It is available to logged-in Employee accounts, so please log in or sign up as an Employee first, then open Employment and choose E-Learnings.");
    }
    if (chat_has($normal, ['civil service', 'cse', 'reviewer', 'mock exam'])) {
        chat_json("Since you are logged in as an Employee, open Employment and then E-Learnings and click the Civil Service Reviewer tab. You can take the full 200-item shuffled mock exam, try the Quick 10 Items mode, practice by subject, use the optional timer, and review your wrong and correct answers with explanations. Please note that it is a practice reviewer and not the actual Civil Service Commission exam.");
    }
    if (chat_has($normal, ['employment development', 'youtube', 'interview prep', 'dress', 'new hire'])) {
        chat_json("You can open Employment and then E-Learnings and select the Employment Development tab to watch curated YouTube guides on acing a job interview, dressing well for an interview, writing a resume, and succeeding as a new hire.");
    }
    chat_json("Since you are logged in as an Employee, click Employment and then E-Learnings in the top menu. Inside, you will find tabs for local Lean Six Sigma, local TESDA certifications, free online certifications from Google, Coursera, and Microsoft, the 200-item Civil Service Reviewer, and Employment Development YouTube guides.");
}

// 12. Employee Documents & Clearances Upload.
if (chat_has($normal, ['document', 'documents', 'clearance', 'nbi', 'police clearance', 'barangay clearance', 'national id', 'sss', 'umid', 'pag-ibig', 'pagibig', 'philhealth', 'tin', 'psa', 'birth certificate', 'upload', 'check mark', 'checkmark'])) {
    chat_set_ctx('documents');
    chat_json("When logged in as an Employee, you can open My Profile from the person icon at the top right to upload your NBI Clearance, Police Clearance, Barangay Clearance, National ID, SSS or UMID, Pag-IBIG, PhilHealth, TIN ID, and PSA Birth Certificate in PDF, JPG, PNG, or WEBP format up to 5 MB each. Your files stay private and are only visible to you and the employers you apply to, who will see a green checkmark checklist on your application. If you still need to obtain any of these, visit the Gov Offices tab to find the nearest Pampanga branch.");
}

// 13. Hired Notification & Direct Messaging.
if (chat_has($normal, ['hired', 'congratulations', 'message', 'messages', 'messaging', 'inbox', 'reply', 'notification', 'notifications', 'status'])) {
    chat_json("Whenever an employer updates your application status to Hired, you will immediately receive a Congratulations notification. Employers can also send you direct messages with interview or onboarding details, which you can read and reply to inside your Inbox from the envelope icon at the top right. If you are hired by multiple companies, you have 14 days to click Accept Offer in your Inbox before unaccepted offers expire.");
}

// 14. About Us & Policies.
if (chat_has($normal, ['about us', 'about abeka', 'kulitan', 'brand identity', 'mission', 'vision', 'core values', 'goal'])) {
    chat_json("ABEKa stands for a digital platform built for the people and communities of Pampanga, bringing together jobs, local news, announcements, emergency hotlines, government office directions, fuel and forex updates, and e-learning. Our logo lettering is inspired by Kulitan, the traditional Kapampangan writing system. You can click Home and then About Us at the top to read our full introduction, brand identity, goal, mission, vision, and core values.");
}

if (chat_has($normal, ['privacy', 'terms', 'disclosure', 'policy', 'policies', 'ra 10173', 'data privacy'])) {
    chat_json("You can read our Privacy Policy, Terms of Service, and Disclosure Policy anytime from the links centered at the bottom of the page in the footer.");
}

// 14B. Dashboard & Audit Trail / Log History.
if (chat_has($normal, ['dashboard', 'audit', 'audit trail', 'log history', 'activity log', 'logs'])) {
    chat_json("ABEKa includes a role-based Dashboard for every logged-in Employee, Employer, and Admin account showing key summary metrics, quick shortcuts, and recent activity. In addition, Administrators can open the Audit Trail page from the top menu to view, filter, and export the full system log history of logins, registrations, password resets, job applications, document uploads, and administrative updates.");
}

// 15. General LGU lookup (when user mentions a town without specifically asking for tourist spots).
if ($matchedLgu) {
    $slug = $matchedLgu['slug'];
    $spots = $touristSpots[$slug] ?? [];
    chat_set_ctx('lgu', ['lgu' => $matchedLgu['name']]);
    $spotSentence = '';
    if ($spots) {
        $spotNames = array_map(static function ($s) {
            return $s['name'];
        }, $spots);
        $spotSentence = ' Popular tourist spots there include ' . chat_join_list($spotNames) . '.';
    }
    chat_json("{$matchedLgu['name']} is part of our Pampanga places directory on the Home page under Most viewed places and See all, where you can read its local news and announcements.{$spotSentence} You can also check the Alerts and Gov Offices tabs for emergency hotlines and nearby government offices in {$matchedLgu['name']}.");
}

if (chat_has($normal, ['list lgu', 'all lgu', 'all cities', 'municipalities', 'cities in pampanga', 'towns in pampanga', 'what lgu', 'which lgu'])) {
    if (!$lgus) {
        chat_json("Our Pampanga places directory is temporarily unavailable right now, so please check the Home page again in a moment.");
    }
    $names = array_map(static function ($l) {
        return $l['name'];
    }, $lgus);
    chat_json("ABEKa covers all 22 cities and municipalities across Pampanga, namely " . chat_join_list($names) . ". You can click See all under Most viewed places on the Home page to open any of them.");
}

// 16. News & Announcements.
if (chat_has($normal, ['announcement', 'announcements', 'advisory', 'advisories', 'latest update', 'updates'])) {
    $rows = chat_rows('SELECT title, posted FROM announcements ORDER BY posted DESC LIMIT 4');
    if (!$rows) {
        chat_json("There are no new announcements posted right now, so please check the Announcements tab at the top again later.");
    }
    $items = [];
    foreach ($rows as $row) {
        $items[] = $row['title'] . ' (' . date('M j, Y', strtotime($row['posted'])) . ')';
    }
    chat_json("The latest announcements on ABEKa include " . chat_join_list($items) . ". You can click Announcements in the top menu to read more.");
}

if (chat_has($normal, ['news', 'headline', 'headlines', 'article', 'articles'])) {
    $rows = chat_rows('SELECT title, published FROM news ORDER BY published DESC LIMIT 4');
    if (!$rows) {
        chat_json("There are no news headlines loaded at the moment, so please check the News tab in the top menu again shortly.");
    }
    $items = [];
    foreach ($rows as $row) {
        $items[] = $row['title'] . ' (' . date('M j, Y', strtotime($row['published'])) . ')';
    }
    chat_json("Here are the latest Pampanga news headlines on ABEKa: " . chat_join_list($items) . ". Click News in the top menu to browse all stories.");
}

// 17. Jobs & Specific Job Search.
$jobKeywords = array_values(array_filter(
    explode(' ', $normal),
    static function ($w) {
        $stop = ['is','there','any','are','for','job','jobs','hiring','vacancy','vacancies','work','career','careers','looking','find','want','need','open','opening','openings','available','in','at','on','the','a','an','to','can','i','apply','me','show','list','with','employment'];
        return mb_strlen($w, 'UTF-8') >= 2 && !in_array($w, $stop, true);
    }
));

$specificMatches = [];
if ($jobKeywords) {
    $likeConds = [];
    $likeParams = [];
    foreach (array_slice($jobKeywords, 0, 3) as $kw) {
        $likeConds[] = '(j.title LIKE ? OR j.industry LIKE ? OR j.location LIKE ? OR j.description LIKE ?)';
        $term = '%' . $kw . '%';
        array_push($likeParams, $term, $term, $term, $term);
    }
    $specificMatches = chat_rows(
        'SELECT j.id, j.title, j.location, j.industry, j.salary_offer, ep.company
         FROM jobs j
         LEFT JOIN employer_profiles ep ON ep.user_id = j.employer_id
         WHERE ' . implode(' OR ', $likeConds) . '
         ORDER BY j.created DESC LIMIT 4',
        $likeParams
    );
}

if ($specificMatches || chat_has($normal, ['job', 'jobs', 'hiring', 'vacancy', 'vacancies', 'employment', 'work', 'career', 'careers', 'resume', 'profile', 'opening', 'openings'])) {
    if (chat_has($normal, ['apply', 'application', 'how do i', 'how to', 'resume', 'profile']) && !$specificMatches) {
        if ($isEmployee) {
            chat_json("You are already logged in as an Employee. Simply complete My Profile and upload your clearances and IDs, then open Employment and choose Jobs Hiring to browse openings, view Estimated Salary Offers, and submit your application.");
        }
        chat_json("To apply for a job on ABEKa, please log in using an Employee account first from the Log in or Sign up link at the top right. Once you are signed in, complete your profile and head to Employment and then Jobs Hiring to browse vacancies and apply.");
    }

    if (!$isEmployee) {
        $countHint = $specificMatches
            ? "Good news, I found " . count($specificMatches) . " matching job opening on ABEKa. "
            : "";
        chat_json($countHint . "To view full job details, Estimated Salary Offers, and apply on the Jobs Hiring page, please log in using an Employee account first from the Log in or Sign up button at the top right.");
    }

    if ($specificMatches) {
        $items = [];
        foreach ($specificMatches as $row) {
            $detail = $row['title'];
            if (!empty($row['company'])) $detail .= ' at ' . $row['company'];
            if (!empty($row['location'])) $detail .= ' in ' . $row['location'];
            if (!empty($row['salary_offer'])) $detail .= ' with an estimated salary of ' . $row['salary_offer'];
            $items[] = $detail;
        }
        chat_json("I found matching openings for you: " . chat_join_list($items) . ". Open Employment and then Jobs Hiring in the top menu to view the full details and apply.");
    }

    $rows = chat_rows("SELECT j.id, j.title, j.location, j.industry, j.salary_offer, ep.company FROM jobs j LEFT JOIN employer_profiles ep ON ep.user_id = j.employer_id ORDER BY j.created DESC LIMIT 4");
    if (!$rows) {
        chat_json("There are no job openings posted right now, so please check Employment and then Jobs Hiring again soon.");
    }
    $items = [];
    foreach ($rows as $row) {
        $detail = $row['title'];
        if (!empty($row['company'])) $detail .= ' at ' . $row['company'];
        if (!empty($row['location'])) $detail .= ' in ' . $row['location'];
        if (!empty($row['salary_offer'])) $detail .= ' with an estimated salary of ' . $row['salary_offer'];
        $items[] = $detail;
    }
    chat_json("The latest job openings on ABEKa include " . chat_join_list($items) . ". Click Employment and then Jobs Hiring at the top to view full details and apply.");
}

// 18. Account & Password help.
if (chat_has($normal, ['forgot password', 'reset password', 'forgot', 'password', 'recover'])) {
    chat_json("Whether you have an Employee, Employer, or Admin account, you can reset your password by clicking Log in at the top right and choosing Forgot password. Enter your registered email address and we will send you a confirmation link to set a new password.");
}
if (chat_has($normal, ['sign up', 'signup', 'create account', 'register', 'login', 'log in'])) {
    chat_json("You can click Sign up at the top right to create a new Employee or Employer account, or click Log in if you already have one.");
}
if (chat_has($normal, ['employer', 'post a job', 'hire someone', 'hire employees', 'applicant', 'applicants'])) {
    chat_json("Employers can sign in and open Employment to manage Job Vacancies with Estimated Salary Offers, and check Applicants to review resumes, view green-checkmark document checklists, mark applicants as Hired, and send direct messages.");
}

chat_json("I am not sure I understood that question, but I can help you with Pampanga tourist spots like those in Guagua, today's fuel prices, currency conversions, nearest government offices, emergency hotlines, job openings, e-learning, and troubleshooting. Try asking me a specific question and I will be glad to help.");
