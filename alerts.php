<?php
// Public Pampanga Emergency Alert Center: displays 911, Google Maps locator, and district hotlines.
require_once __DIR__ . '/includes/config.php';

$title = 'Pampanga Emergency Alert Center';

$districts = [
    '1st District' => ['Mabalacat City', 'Magalang'],
    '2nd District' => ['Floridablanca', 'Guagua', 'Lubao', 'Porac', 'Santa Rita', 'Sasmuan'],
    '3rd District' => ['Arayat', 'Bacolor', 'City of San Fernando', 'Mexico', 'Santa Ana'],
    '4th District' => ['Apalit', 'Candaba', 'Macabebe', 'Masantol', 'Minalin', 'San Luis', 'San Simon', 'Santo Tomas'],
    'Provincial / Highly Urbanized City' => ['PG-Pampanga', 'Angeles City']
];

$area = trim((string)($_GET['area'] ?? ''));
$service = trim((string)($_GET['service'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$records = [];
$dbError = false;

try {
    $sql = 'SELECT id, district, municipality, service, office_name, hotline, address, verified_at
            FROM emergency_hotlines WHERE 1=1';
    $params = [];

    if ($area !== '') {
        $sql .= ' AND municipality = ?';
        $params[] = $area;
    }
    if ($service !== '') {
        $sql .= ' AND service = ?';
        $params[] = $service;
    }
    if ($search !== '') {
        $sql .= ' AND (municipality LIKE ? OR office_name LIKE ? OR hotline LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term);
    }

    $sql .= " ORDER BY FIELD(district,'1st District','2nd District','3rd District','4th District','Provincial / Highly Urbanized City'), municipality, service, office_name";
    $records = q($sql, $params)->fetchAll();
} catch (Throwable $e) {
    $dbError = true;
    error_log('ABEKa emergency directory: ' . $e->getMessage());
}

$mapQuery = $area !== '' ? $area . ', Pampanga, Philippines' : 'Pampanga, Philippines';
$mapEmbed = 'https://maps.google.com/maps?q=' . rawurlencode('government emergency offices near ' . $mapQuery) . '&output=embed';
$mapSearch = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('government emergency offices near ' . $mapQuery);

require __DIR__ . '/includes/header.php';
?>

<section class="alert-hero card">
    <div class="alert-hero-mark" aria-hidden="true">🚨</div>
    <p class="alert-kicker">ABEKa COMMUNITY SAFETY</p>
    <h1>Pampanga Emergency Hotlines</h1>
    <p>Find emergency contact numbers and locate nearby public safety and disaster-response offices.</p>
    <a class="alert-911" href="tel:911" aria-label="Call the national emergency hotline 911">
        <span>24/7 NATIONAL EMERGENCY HOTLINE</span>
        <strong>CALL 911</strong>
        <small>Tap to call on a supported device</small>
    </a>
</section>

<section class="card alert-section">
    <div class="alert-section-heading">
        <div>
            <span class="alert-eyebrow">FIND HELP NEAR YOU</span>
            <h2>Government Emergency Locator</h2>
        </div>
        <span class="alert-heading-icon" aria-hidden="true">📍</span>
    </div>

    <form method="get" action="alerts.php" class="alert-filter-form">
        <label>
            Municipality / City
            <select name="area">
                <option value="">All Pampanga</option>
                <?php foreach ($districts as $districtName => $places): ?>
                    <optgroup label="<?=e($districtName)?>">
                        <?php foreach ($places as $place): ?>
                            <option value="<?=e($place)?>" <?=$area === $place ? 'selected' : ''?>><?=e($place)?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Emergency service
            <select name="service">
                <option value="">All services</option>
                <?php foreach (['PNP','BFP','PDRRMO','CDRRMO','MDRRMO','LGU','Medical','Rescue'] as $option): ?>
                    <option value="<?=e($option)?>" <?=$service === $option ? 'selected' : ''?>><?=e($option)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="alert-search-field">
            Search office or hotline
            <input type="search" name="search" value="<?=e($search)?>" maxlength="100" placeholder="e.g. Macabebe, PNP, 911">
        </label>
        <div class="alert-filter-actions">
            <button class="btn" type="submit">Search directory</button>
            <a class="btn blue" href="alerts.php">Reset</a>
        </div>
    </form>

    <div class="alert-map-toolbar">
        <div>
            <strong><?=e($area !== '' ? $area . ', Pampanga' : 'Pampanga, Philippines')?></strong>
            <small>Map search area · Confirm the office location before travelling.</small>
        </div>
        <a class="btn blue" href="<?=e($mapSearch)?>" target="_blank" rel="noopener noreferrer">Open Google Maps ↗</a>
    </div>
    <div class="alert-map-frame">
        <iframe title="Google Maps emergency office search" src="<?=e($mapEmbed)?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
    </div>
    <p class="alert-map-note">The map displays a Google Maps search for the selected area; it is not a live dispatch or verified real-time availability service.</p>
</section>

<section class="card alert-section">
    <div class="alert-section-heading">
        <div>
            <span class="alert-eyebrow">LOCAL CONTACT DIRECTORY</span>
            <h2>Emergency Hotlines by District</h2>
            <p class="muted">PNP, BFP, Provincial/City/Municipal DRRMO, LGU, medical and rescue contacts.</p>
        </div>
        <span class="alert-heading-icon" aria-hidden="true">☎️</span>
    </div>

    <?php if ($dbError): ?>
        <div class="alert-empty">
            <strong>Emergency directory is not initialized yet.</strong>
            <p>Ask the website administrator to import <code>sql/update_emergency_alerts.sql</code> into the ABEKa database.</p>
        </div>
    <?php elseif (!$records): ?>
        <div class="alert-empty">
            <strong>No matching local hotline records yet.</strong>
            <p>Try another search, or contact the national emergency hotline at 911 for urgent assistance.</p>
        </div>
    <?php else: ?>
        <?php $activeDistrict = null; ?>
        <?php foreach ($records as $record): ?>
            <?php if ($activeDistrict !== $record['district']): ?>
                <?php $activeDistrict = $record['district']; ?>
                <h3 class="alert-district-heading"><?=e($activeDistrict)?></h3>
            <?php endif; ?>
            <article class="alert-contact">
                <div class="alert-contact-info">
                    <span class="alert-service-tag"><?=e($record['service'])?></span>
                    <h3><?=e($record['municipality'])?></h3>
                    <strong><?=e($record['office_name'])?></strong>
                    <?php if (!empty($record['address'])): ?>
                        <p><?=e($record['address'])?></p>
                    <?php endif; ?>
                    <small class="alert-verification">
                        <?php if (!empty($record['verified_at'])): ?>
                            Verified <?=e(date('M j, Y', strtotime($record['verified_at'])))?>
                        <?php else: ?>
                            Not yet verified by ABEKa
                        <?php endif; ?>
                    </small>
                </div>
                <div class="alert-contact-actions">
                    <strong class="alert-phone"><?=e($record['hotline'])?></strong>
                    <?php if (filter_var($record['hotline'], FILTER_VALIDATE_EMAIL)): ?>
                        <a class="btn alert-call-small" href="mailto:<?=e($record['hotline'])?>">Send email</a>
                    <?php else: ?>
                        <a class="btn alert-call-small" href="tel:<?=e(preg_replace('/[^0-9+]/', '', $record['hotline']))?>">Call number</a>
                    <?php endif; ?>
                    <?php if (!empty($record['address'])): ?>
                        <a class="alert-map-link" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&amp;query=<?=rawurlencode($record['office_name'] . ', ' . $record['address'] . ', Pampanga, Philippines')?>">View location ↗</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="alert-disclaimer">
    <strong>Emergency reminder:</strong> Call 911 for urgent emergencies. Local hotline listings must be confirmed with the relevant LGU or agency. ABEKa is an information directory and does not dispatch emergency responders.
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
