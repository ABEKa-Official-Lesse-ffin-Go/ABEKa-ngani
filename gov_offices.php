<?php
// Public Government Offices Locator: nearest Pampanga offices and Google Maps directions for all 9 requirements.
// Public Government Offices Locator for Pampanga (accessible to all users, including guests).
require_once __DIR__ . '/includes/config.php';

$title = 'Government Offices Locator';

// All Pampanga cities and municipalities for origin / area selection.
$pampangaAreas = [
    'Angeles City',
    'Apalit',
    'Arayat',
    'Bacolor',
    'Candaba',
    'City of San Fernando',
    'Floridablanca',
    'Guagua',
    'Lubao',
    'Mabalacat City',
    'Macabebe',
    'Magalang',
    'Masantol',
    'Mexico',
    'Minalin',
    'Porac',
    'San Luis',
    'San Simon',
    'Santa Ana',
    'Santa Rita',
    'Santo Tomas',
    'Sasmuan',
];

// Requirement categories and their nearest Pampanga office recommendations.
$requirements = [
    'psa' => [
        'label' => 'PSA / Philippine Statistics Authority (Formerly NSO)',
        'short' => 'PSA Birth Certificate',
        'notes' => 'For PSA Birth Certificate, Marriage Certificate, CENOMAR, and Death Certificate.',
        'offices' => [
            [
                'name' => 'PSA Civil Registry System (CRS) Outlet — SACOP',
                'area' => 'City of San Fernando',
                'address' => 'SACOP, Brgy. Maimpis, City of San Fernando, Pampanga (near Calcutta / Regional Government Center)',
                'query' => 'PSA CRS Outlet SACOP Maimpis City of San Fernando Pampanga',
                'serves' => ['City of San Fernando', 'Mexico', 'Bacolor', 'Santa Ana', 'Arayat', 'Guagua', 'Lubao', 'Floridablanca', 'Porac', 'Santa Rita', 'Sasmuan', 'Apalit', 'Candaba', 'Macabebe', 'Masantol', 'Minalin', 'San Luis', 'San Simon', 'Santo Tomas', 'Angeles City', 'Mabalacat City', 'Magalang'],
            ],
        ],
    ],
    'nbi' => [
        'label' => 'NBI Clearance',
        'short' => 'NBI Clearance',
        'notes' => 'Register online at clearance.nbi.gov.ph first, then visit your selected NBI Pampanga branch.',
        'offices' => [
            [
                'name' => 'NBI Pampanga District Office (San Fernando)',
                'area' => 'City of San Fernando',
                'address' => 'Capitol Compound / Brgy. Sto. Nino, City of San Fernando, Pampanga',
                'query' => 'NBI Pampanga District Office City of San Fernando Pampanga',
                'serves' => ['City of San Fernando', 'Mexico', 'Bacolor', 'Guagua', 'Lubao', 'Floridablanca', 'Santa Rita', 'Sasmuan', 'Apalit', 'Minalin', 'San Simon', 'Santo Tomas', 'Macabebe', 'Masantol', 'San Luis', 'Candaba', 'Arayat', 'Santa Ana'],
            ],
            [
                'name' => 'NBI Angeles City Clearance Center',
                'area' => 'Angeles City',
                'address' => 'Angeles City, Pampanga',
                'query' => 'NBI Clearance Center Angeles City Pampanga',
                'serves' => ['Angeles City', 'Mabalacat City', 'Magalang', 'Porac'],
            ],
        ],
    ],
    'police' => [
        'label' => 'Police Clearance',
        'short' => 'Police Clearance',
        'notes' => 'Apply online via pnpclearance.ph and appear at your local City or Municipal Police Station.',
        'offices' => [
            [
                'name' => 'Local City / Municipal Police Station (PNP)',
                'area' => 'Selected Municipality / City',
                'address' => 'Your local PNP Municipal or City Police Station in Pampanga',
                'query' => 'PNP Police Station',
                'dynamic_local' => true,
                'serves' => $pampangaAreas,
            ],
        ],
    ],
    'barangay' => [
        'label' => 'Barangay Clearance',
        'short' => 'Barangay Clearance',
        'notes' => 'Issued at the Barangay Hall where you currently reside. Bring a valid ID or proof of residency.',
        'offices' => [
            [
                'name' => 'Your Local Barangay Hall',
                'area' => 'Selected Municipality / City',
                'address' => 'Barangay Hall in your barangay of residence',
                'query' => 'Barangay Hall',
                'dynamic_local' => true,
                'serves' => $pampangaAreas,
            ],
        ],
    ],
    'national_id' => [
        'label' => 'National ID (PhilSys)',
        'short' => 'National ID',
        'notes' => 'Visit PSA PhilSys registration centers or mall-based PhilSys booths in Pampanga.',
        'offices' => [
            [
                'name' => 'PSA PhilSys Registration Center — SACOP / San Fernando',
                'area' => 'City of San Fernando',
                'address' => 'SACOP, Brgy. Maimpis / Robinsons Starmills, City of San Fernando, Pampanga',
                'query' => 'PhilSys National ID Registration Center City of San Fernando Pampanga',
                'serves' => ['City of San Fernando', 'Mexico', 'Bacolor', 'Santa Ana', 'Arayat', 'Guagua', 'Lubao', 'Floridablanca', 'Santa Rita', 'Sasmuan', 'Apalit', 'Candaba', 'Macabebe', 'Masantol', 'Minalin', 'San Luis', 'San Simon', 'Santo Tomas'],
            ],
            [
                'name' => 'PhilSys Registration Center — Angeles City / Mabalacat',
                'area' => 'Angeles City',
                'address' => 'MarQuee Mall / Angeles City, Pampanga',
                'query' => 'PhilSys National ID Registration Center Angeles City Pampanga',
                'serves' => ['Angeles City', 'Mabalacat City', 'Magalang', 'Porac'],
            ],
        ],
    ],
    'sss' => [
        'label' => 'SSS / UMID',
        'short' => 'SSS / UMID',
        'notes' => 'Visit an SSS Pampanga branch for SSS number registration, E-1/E-4 updates, or UMID inquiries.',
        'offices' => [
            [
                'name' => 'SSS San Fernando Branch',
                'area' => 'City of San Fernando',
                'address' => 'SWC Building / MacArthur Highway, City of San Fernando, Pampanga',
                'query' => 'SSS San Fernando Branch Pampanga',
                'serves' => ['City of San Fernando', 'Mexico', 'Bacolor', 'Guagua', 'Lubao', 'Floridablanca', 'Santa Rita', 'Sasmuan', 'Apalit', 'Minalin', 'San Simon', 'Santo Tomas', 'Macabebe', 'Masantol', 'San Luis', 'Candaba', 'Arayat', 'Santa Ana'],
            ],
            [
                'name' => 'SSS Angeles City Branch',
                'area' => 'Angeles City',
                'address' => 'MacArthur Highway, Balibago / MarQuee Mall, Angeles City, Pampanga',
                'query' => 'SSS Angeles City Branch Pampanga',
                'serves' => ['Angeles City', 'Mabalacat City', 'Magalang', 'Porac'],
            ],
        ],
    ],
    'pagibig' => [
        'label' => 'Pag-IBIG Fund (HDMF)',
        'short' => 'Pag-IBIG',
        'notes' => 'For MID number, Loyalty Card Plus, and membership verification.',
        'offices' => [
            [
                'name' => 'Pag-IBIG Fund — Robinsons Starmills Pampanga',
                'area' => 'City of San Fernando',
                'address' => 'Robinsons Starmills Pampanga, Brgy. San Jose, City of San Fernando, Pampanga (near Mexico exit)',
                'query' => 'Pag-IBIG Fund Robinsons Starmills Pampanga',
                'serves' => ['City of San Fernando', 'Mexico', 'Bacolor', 'Santa Ana', 'Arayat', 'Candaba', 'San Luis', 'San Simon', 'Santo Tomas', 'Apalit', 'Minalin', 'Macabebe', 'Masantol', 'Guagua', 'Lubao', 'Floridablanca', 'Santa Rita', 'Sasmuan'],
            ],
            [
                'name' => 'Pag-IBIG Fund — Angeles City / Sub-Branch',
                'area' => 'Angeles City',
                'address' => 'Angeles City, Pampanga',
                'query' => 'Pag-IBIG Fund Angeles City Pampanga',
                'serves' => ['Angeles City', 'Mabalacat City', 'Magalang', 'Porac'],
            ],
        ],
    ],
    'philhealth' => [
        'label' => 'PhilHealth',
        'short' => 'PhilHealth',
        'notes' => 'For PhilHealth ID, Member Data Record (MDR), and registration.',
        'offices' => [
            [
                'name' => 'PhilHealth LHIO San Fernando (Regional Office III)',
                'area' => 'City of San Fernando',
                'address' => 'Lazatin Blvd / San Fernando, Pampanga',
                'query' => 'PhilHealth San Fernando Pampanga',
                'serves' => ['City of San Fernando', 'Mexico', 'Bacolor', 'Guagua', 'Lubao', 'Floridablanca', 'Santa Rita', 'Sasmuan', 'Apalit', 'Minalin', 'San Simon', 'Santo Tomas', 'Macabebe', 'Masantol', 'San Luis', 'Candaba', 'Arayat', 'Santa Ana'],
            ],
            [
                'name' => 'PhilHealth LHIO Angeles City',
                'area' => 'Angeles City',
                'address' => 'Angeles City, Pampanga',
                'query' => 'PhilHealth Angeles City Pampanga',
                'serves' => ['Angeles City', 'Mabalacat City', 'Magalang', 'Porac'],
            ],
        ],
    ],
    'tin' => [
        'label' => 'TIN ID (BIR)',
        'short' => 'TIN ID',
        'notes' => 'Visit the BIR Revenue District Office (RDO) covering your residence or employer.',
        'offices' => [
            [
                'name' => 'BIR RDO 21B — South Pampanga (City of San Fernando)',
                'area' => 'City of San Fernando',
                'address' => 'Sindalan / City of San Fernando, Pampanga',
                'query' => 'BIR RDO 21B City of San Fernando Pampanga',
                'serves' => ['City of San Fernando', 'Mexico', 'Bacolor', 'Guagua', 'Lubao', 'Floridablanca', 'Santa Rita', 'Sasmuan', 'Apalit', 'Minalin', 'San Simon', 'Santo Tomas', 'Macabebe', 'Masantol', 'San Luis', 'Candaba', 'Arayat', 'Santa Ana'],
            ],
            [
                'name' => 'BIR RDO 21A — North Pampanga (Angeles City)',
                'area' => 'Angeles City',
                'address' => 'Angeles City, Pampanga',
                'query' => 'BIR RDO 21A Angeles City Pampanga',
                'serves' => ['Angeles City', 'Mabalacat City', 'Magalang', 'Porac'],
            ],
        ],
    ],
];

// Override with live editable records from gov_offices table when available.
try {
    $dbGov = q('SELECT * FROM gov_offices ORDER BY req_key, id')->fetchAll();
    if ($dbGov) {
        $requirements = [];
        foreach ($dbGov as $row) {
            $rk = $row['req_key'];
            if (!isset($requirements[$rk])) {
                $requirements[$rk] = [
                    'label' => $row['req_label'],
                    'short' => $row['req_short'],
                    'notes' => (string)$row['notes'],
                    'offices' => [],
                ];
            }
            $servesList = array_values(array_filter(array_map('trim', explode(',', (string)$row['serves']))));
            $requirements[$rk]['offices'][] = [
                'name' => $row['office_name'],
                'area' => $row['area'],
                'address' => $row['address'],
                'query' => $row['map_query'],
                'serves' => $servesList ?: $pampangaAreas,
                'dynamic_local' => !empty($row['dynamic_local']),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('ABEKa gov_offices load: ' . $e->getMessage());
}

// Read selected filters from query parameters.
$selectedArea = trim((string)($_GET['area'] ?? 'City of San Fernando'));
if (!in_array($selectedArea, $pampangaAreas, true)) {
    $selectedArea = 'City of San Fernando';
}

$selectedReq = trim((string)($_GET['req'] ?? 'psa'));
if (!isset($requirements[$selectedReq])) {
    $selectedReq = 'psa';
}

$activeReq = $requirements[$selectedReq];

// Pick the best matching office for the selected Pampanga municipality/city.
$bestOffice = $activeReq['offices'][0];
foreach ($activeReq['offices'] as $office) {
    if (in_array($selectedArea, $office['serves'], true)) {
        $bestOffice = $office;
        break;
    }
}

// Build Google Maps search, embed, and turn-by-turn directions URLs.
$destinationQuery = !empty($bestOffice['dynamic_local'])
    ? $bestOffice['query'] . ' ' . $selectedArea . ', Pampanga, Philippines'
    : $bestOffice['query'];

$originQuery = $selectedArea . ', Pampanga, Philippines';
$mapEmbedUrl = 'https://maps.google.com/maps?q=' . rawurlencode($destinationQuery) . '&output=embed';
$directionsFromAreaUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($originQuery) . '&destination=' . rawurlencode($destinationQuery);
$directionsFromGpsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destinationQuery);

require __DIR__ . '/includes/header.php';
?>

<!-- Hero section -->
<section class="card glass gov-hero">
    <p class="elearn-kicker">PAMPANGA PUBLIC SERVICE LOCATOR</p>
    <h1>Nearest Government Offices</h1>
    <p class="muted">
        Find the nearest Pampanga office for your clearances and government IDs, and open Google Maps directions right away—no login required.
    </p>
</section>

<!-- Filter & Google Maps Directions section -->
<section class="card glass alert-section">
    <form method="get" action="gov_offices.php" class="alert-filter-form">
        <label>
            Your City / Municipality in Pampanga
            <select name="area">
                <?php foreach ($pampangaAreas as $areaOption): ?>
                    <option value="<?=e($areaOption)?>" <?=$selectedArea === $areaOption ? 'selected' : ''?>>
                        <?=e($areaOption)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Document / Requirement
            <select name="req">
                <?php foreach ($requirements as $reqKey => $reqData): ?>
                    <option value="<?=e($reqKey)?>" <?=$selectedReq === $reqKey ? 'selected' : ''?>>
                        <?=e($reqData['label'])?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="alert-filter-actions">
            <button class="btn" type="submit">Find Nearest Office</button>
            <a class="btn blue" href="gov_offices.php">Reset</a>
        </div>
    </form>

    <!-- Recommended office banner and Google Maps direction buttons -->
    <div class="gov-highlight">
        <div>
            <span class="elearn-badge">Nearest Match for <?=e($selectedArea)?></span>
            <h2><?=e($bestOffice['name'])?></h2>
            <p class="muted"><?=e($bestOffice['address'])?></p>
            <p><?=e($activeReq['notes'])?></p>
        </div>
        <div class="gov-dir-actions">
            <a class="btn" href="<?=e($directionsFromAreaUrl)?>" target="_blank" rel="noopener noreferrer">
                Directions from <?=e($selectedArea)?> &#8599;
            </a>
            <a class="btn blue" href="<?=e($directionsFromGpsUrl)?>" target="_blank" rel="noopener noreferrer">
                Directions from My Location &#8599;
            </a>
        </div>
    </div>

    <!-- Embedded Google Map -->
    <div class="alert-map-frame">
        <iframe
            id="govMapFrame"
            title="Google Maps nearest government office"
            src="<?=e($mapEmbedUrl)?>"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen
        ></iframe>
    </div>
    <p class="alert-map-note">
        Click either Directions button above to launch Google Maps with turn-by-turn navigation.
    </p>
</section>

<!-- Quick directory of all 9 requirements -->
<section class="card glass alert-section">
    <h2>All 9 Requirement Offices in Pampanga</h2>
    <p class="muted">Select any requirement below to preview its nearest office and open Google Maps directions from <?=e($selectedArea)?>.</p>

    <div class="elearn-grid">
        <?php foreach ($requirements as $reqKey => $reqData):
            $cardOffice = $reqData['offices'][0];
            foreach ($reqData['offices'] as $candidate) {
                if (in_array($selectedArea, $candidate['serves'], true)) {
                    $cardOffice = $candidate;
                    break;
                }
            }
            $cardDest = !empty($cardOffice['dynamic_local'])
                ? $cardOffice['query'] . ' ' . $selectedArea . ', Pampanga, Philippines'
                : $cardOffice['query'];
            $cardDirUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($originQuery) . '&destination=' . rawurlencode($cardDest);
        ?>
            <article class="elearn-card">
                <div>
                    <span class="elearn-badge"><?=e($reqData['short'])?></span>
                    <h4><?=e($cardOffice['name'])?></h4>
                    <p><?=e($cardOffice['address'])?></p>
                </div>
                <div class="gov-card-links">
                    <a class="btn sm" href="gov_offices.php?area=<?=rawurlencode($selectedArea)?>&amp;req=<?=rawurlencode($reqKey)?>">
                        Show on Map
                    </a>
                    <a class="btn sm blue" href="<?=e($cardDirUrl)?>" target="_blank" rel="noopener noreferrer">
                        Directions &#8599;
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
