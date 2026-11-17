<?php
// Admin-only Content Manager: edit existing Employment vacancies, Gov Offices, Announcements, and News.
require_once __DIR__ . '/includes/config.php';

$u = need('admin');
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

$tab = (string)($_GET['tab'] ?? $_POST['tab'] ?? 'jobs');
if (!in_array($tab, ['jobs', 'gov', 'announcements', 'news', 'elearning'], true)) {
    $tab = 'jobs';
}

$msg = '';
$err = '';

$reqMeta = [
    'psa'         => ['PSA / Philippine Statistics Authority (Formerly NSO)', 'PSA Birth Certificate'],
    'nbi'         => ['NBI Clearance', 'NBI Clearance'],
    'police'      => ['Police Clearance', 'Police Clearance'],
    'barangay'    => ['Barangay Clearance', 'Barangay Clearance'],
    'national_id' => ['National ID (PhilSys)', 'National ID'],
    'sss'         => ['SSS / UMID', 'SSS / UMID'],
    'pagibig'     => ['Pag-IBIG Fund (HDMF)', 'Pag-IBIG'],
    'philhealth'  => ['PhilHealth', 'PhilHealth'],
    'tin'         => ['TIN ID (BIR)', 'TIN ID'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        die('Invalid security token. Please reload and try again.');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        // 1. Employment (Job Vacancies)
        if ($tab === 'jobs') {
            $jid = (int)($_POST['id'] ?? 0);
            if ($action === 'delete' && $jid > 0) {
                q('DELETE FROM applications WHERE job_id = ?', [$jid]);
                q('DELETE FROM jobs WHERE id = ?', [$jid]);
                audit_log('Admin Job Deleted', 'Deleted job ID ' . $jid);
                $msg = 'Job vacancy deleted.';
            } elseif ($action === 'save' && $jid > 0) {
                q(
                    'UPDATE jobs SET title=?, description=?, qualifications=?, location=?, industry=?, salary_offer=? WHERE id=?',
                    [
                        trim((string)$_POST['title']),
                        trim((string)$_POST['description']),
                        trim((string)$_POST['qualifications']),
                        trim((string)$_POST['location']),
                        trim((string)$_POST['industry']),
                        trim((string)($_POST['salary_offer'] ?? '')) ?: null,
                        $jid,
                    ]
                );
                audit_log('Admin Job Updated', 'Updated job: ' . trim((string)$_POST['title']));
                $msg = 'Job vacancy updated.';
            }

        // 2. Gov Offices
        } elseif ($tab === 'gov') {
            $gid = (int)($_POST['id'] ?? 0);
            if ($action === 'delete' && $gid > 0) {
                q('DELETE FROM gov_offices WHERE id = ?', [$gid]);
                audit_log('Admin Gov Office Deleted', 'Deleted gov office ID ' . $gid);
                $msg = 'Government office deleted.';
            } elseif ($action === 'save') {
                $rk = (string)($_POST['req_key'] ?? 'psa');
                $meta = $reqMeta[$rk] ?? ['Other', 'Other'];
                $officeName = trim((string)($_POST['office_name'] ?? ''));
                $area = trim((string)($_POST['area'] ?? ''));
                $address = trim((string)($_POST['address'] ?? ''));
                $mapQuery = trim((string)($_POST['map_query'] ?? ''));
                $notes = trim((string)($_POST['notes'] ?? ''));
                $serves = trim((string)($_POST['serves'] ?? ''));
                $dyn = !empty($_POST['dynamic_local']) ? 1 : 0;

                if ($officeName === '' || $address === '' || $mapQuery === '') {
                    throw new RuntimeException('Office name, address, and map query are required.');
                }

                if ($gid > 0) {
                    q(
                        'UPDATE gov_offices SET req_key=?, req_label=?, req_short=?, notes=?, office_name=?, area=?, address=?, map_query=?, serves=?, dynamic_local=? WHERE id=?',
                        [$rk, $meta[0], $meta[1], $notes, $officeName, $area, $address, $mapQuery, $serves, $dyn, $gid]
                    );
                    audit_log('Admin Gov Office Updated', 'Updated: ' . $officeName);
                    $msg = 'Government office updated.';
                } else {
                    q(
                        'INSERT INTO gov_offices (req_key, req_label, req_short, notes, office_name, area, address, map_query, serves, dynamic_local) VALUES (?,?,?,?,?,?,?,?,?,?)',
                        [$rk, $meta[0], $meta[1], $notes, $officeName, $area, $address, $mapQuery, $serves, $dyn]
                    );
                    audit_log('Admin Gov Office Added', 'Added: ' . $officeName);
                    $msg = 'Government office added.';
                }
            }

        // 3. Announcements
        } elseif ($tab === 'announcements') {
            $aid = (int)($_POST['id'] ?? 0);
            if ($action === 'delete' && $aid > 0) {
                q('DELETE FROM announcements WHERE id = ?', [$aid]);
                audit_log('Admin Announcement Deleted', 'Deleted announcement ID ' . $aid);
                $msg = 'Announcement deleted.';
            } elseif ($action === 'save') {
                $lguId = (int)($_POST['lgu_id'] ?? 0) ?: null;
                $aTitle = trim((string)($_POST['title'] ?? ''));
                $aBody = trim((string)($_POST['body'] ?? ''));
                $aUrl = trim((string)($_POST['url'] ?? '')) ?: null;

                if ($aTitle === '') {
                    throw new RuntimeException('Announcement title is required.');
                }

                if ($aid > 0) {
                    q(
                        'UPDATE announcements SET lgu_id=?, title=?, body=?, url=? WHERE id=?',
                        [$lguId, $aTitle, $aBody, $aUrl, $aid]
                    );
                    audit_log('Admin Announcement Updated', 'Updated: ' . $aTitle);
                    $msg = 'Announcement updated.';
                } else {
                    q(
                        "INSERT INTO announcements (lgu_id, title, body, url, source) VALUES (?, ?, ?, ?, 'Official')",
                        [$lguId, $aTitle, $aBody, $aUrl]
                    );
                    audit_log('Admin Announcement Posted', 'Posted: ' . $aTitle);
                    $msg = 'Announcement posted.';
                }
            }

        // 4. News
        } elseif ($tab === 'news') {
            $nid = (int)($_POST['id'] ?? 0);
            if ($action === 'delete' && $nid > 0) {
                q('DELETE FROM news WHERE id = ?', [$nid]);
                audit_log('Admin News Deleted', 'Deleted news ID ' . $nid);
                $msg = 'News article deleted.';
            } elseif ($action === 'save') {
                $lguId = (int)($_POST['lgu_id'] ?? 0) ?: null;
                $nTitle = trim((string)($_POST['title'] ?? ''));
                $nUrl = trim((string)($_POST['url'] ?? ''));
                $nSource = trim((string)($_POST['source'] ?? 'Philippine Daily Inquirer'));

                if ($nTitle === '' || $nUrl === '') {
                    throw new RuntimeException('News title and URL are required.');
                }

                if ($nid > 0) {
                    q(
                        'UPDATE news SET lgu_id=?, title=?, url=?, source=? WHERE id=?',
                        [$lguId, $nTitle, $nUrl, $nSource, $nid]
                    );
                    audit_log('Admin News Updated', 'Updated: ' . $nTitle);
                    $msg = 'News article updated.';
                } else {
                    q(
                        'INSERT INTO news (lgu_id, title, url, source) VALUES (?, ?, ?, ?)',
                        [$lguId, $nTitle, $nUrl, $nSource]
                    );
                    audit_log('Admin News Added', 'Added: ' . $nTitle);
                    $msg = 'News article added.';
                }
            }

        // 5. E-Learnings
        } elseif ($tab === 'elearning') {
            $eid = (int)($_POST['id'] ?? 0);
            if ($action === 'delete' && $eid > 0) {
                q('DELETE FROM elearning_links WHERE id = ?', [$eid]);
                audit_log('Admin E-Learning Deleted', 'Deleted e-learning item ID ' . $eid);
                $msg = 'E-Learning item deleted.';
            } elseif ($action === 'save') {
                $tk = (string)($_POST['tab_key'] ?? 'lss');
                $prov = trim((string)($_POST['provider'] ?? ''));
                $et = trim((string)($_POST['title'] ?? ''));
                $eb = trim((string)($_POST['badge'] ?? ''));
                $ed = trim((string)($_POST['description'] ?? ''));
                $eu = trim((string)($_POST['url'] ?? ''));
                $ec = trim((string)($_POST['cta_label'] ?? 'Open course ↗'));

                if ($prov === '' || $et === '' || $eu === '') {
                    throw new RuntimeException('Provider, title, and URL are required.');
                }

                if ($eid > 0) {
                    q(
                        'UPDATE elearning_links SET tab_key=?, provider=?, title=?, badge=?, description=?, url=?, cta_label=? WHERE id=?',
                        [$tk, $prov, $et, $eb, $ed, $eu, $ec, $eid]
                    );
                    audit_log('Admin E-Learning Updated', 'Updated: ' . $et);
                    $msg = 'E-Learning item updated.';
                } else {
                    q(
                        'INSERT INTO elearning_links (tab_key, provider, title, badge, description, url, cta_label) VALUES (?,?,?,?,?,?,?)',
                        [$tk, $prov, $et, $eb, $ed, $eu, $ec]
                    );
                    audit_log('Admin E-Learning Added', 'Added: ' . $et);
                    $msg = 'E-Learning item added.';
                }
            }
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$lgus = q('SELECT id, name FROM lgus ORDER BY name')->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;

if ($tab === 'jobs' && $editId > 0) {
    $editRow = q('SELECT * FROM jobs WHERE id = ?', [$editId])->fetch() ?: null;
} elseif ($tab === 'gov' && $editId > 0) {
    $editRow = q('SELECT * FROM gov_offices WHERE id = ?', [$editId])->fetch() ?: null;
} elseif ($tab === 'announcements' && $editId > 0) {
    $editRow = q('SELECT * FROM announcements WHERE id = ?', [$editId])->fetch() ?: null;
} elseif ($tab === 'news' && $editId > 0) {
    $editRow = q('SELECT * FROM news WHERE id = ?', [$editId])->fetch() ?: null;
} elseif ($tab === 'elearning' && $editId > 0) {
    $editRow = q('SELECT * FROM elearning_links WHERE id = ?', [$editId])->fetch() ?: null;
}

$title = 'Manage Site Content';
require __DIR__ . '/includes/header.php';
?>

<section class="card glass">
    <p class="elearn-kicker">ADMIN CONTENT MANAGER</p>
    <h1>Edit Site Content</h1>
    <p class="muted">Edit or remove current content across Employment, Gov Offices, Announcements, News, E-Learnings, and Alerts.</p>

    <?php if ($msg): ?><p class="alert-admin-success"><?=e($msg)?></p><?php endif; ?>
    <?php if ($err): ?><p class="alert-admin-error"><?=e($err)?></p><?php endif; ?>

    <div class="elearn-tabs">
        <a class="elearn-tab <?=$tab === 'jobs' ? 'active' : ''?>" href="content_admin.php?tab=jobs">Employment</a>
        <a class="elearn-tab <?=$tab === 'gov' ? 'active' : ''?>" href="content_admin.php?tab=gov">Gov Offices</a>
        <a class="elearn-tab <?=$tab === 'announcements' ? 'active' : ''?>" href="content_admin.php?tab=announcements">Announcements</a>
        <a class="elearn-tab <?=$tab === 'news' ? 'active' : ''?>" href="content_admin.php?tab=news">News</a>
        <a class="elearn-tab <?=$tab === 'elearning' ? 'active' : ''?>" href="content_admin.php?tab=elearning">E-Learnings</a>
        <a class="elearn-tab" href="alert_admin.php">Alerts (Hotlines)</a>
    </div>
</section>

<?php if ($tab === 'jobs'):
    $jobRows = q('SELECT j.*, COALESCE(ep.company, u.name) AS company_name FROM jobs j JOIN users u ON u.id = j.employer_id LEFT JOIN employer_profiles ep ON ep.user_id = j.employer_id ORDER BY j.created DESC LIMIT 100')->fetchAll();
?>
    <?php if ($editRow): ?>
        <section class="card glass">
            <h2>Edit Job Vacancy #<?=(int)$editRow['id']?></h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                <input type="hidden" name="tab" value="jobs">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?=(int)$editRow['id']?>">

                <label>Job title <input name="title" value="<?=e($editRow['title'])?>" required></label>
                <label>Description <textarea name="description" rows="4" required><?=e($editRow['description'])?></textarea></label>
                <label>Qualifications <textarea name="qualifications" rows="3" required><?=e($editRow['qualifications'])?></textarea></label>
                <label>Location
                    <select name="location">
                        <?php foreach ($lgus as $l): ?>
                            <option value="<?=e($l['name'])?>" <?=$l['name'] === $editRow['location'] ? 'selected' : ''?>><?=e($l['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Industry <input name="industry" value="<?=e($editRow['industry'])?>" required></label>
                <label>Estimated Salary Offer <input name="salary_offer" value="<?=e($editRow['salary_offer'] ?? '')?>"></label>
                <p>
                    <button class="btn" type="submit">Save Job Changes</button>
                    <a class="btn blue" href="content_admin.php?tab=jobs">Cancel</a>
                </p>
            </form>
        </section>
    <?php endif; ?>

    <section class="card glass">
        <h2>Current Job Vacancies (<?=count($jobRows)?>)</h2>
        <div class="alert-admin-table-wrap">
            <table>
                <thead><tr><th>Title</th><th>Company</th><th>Location</th><th>Est. Salary</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($jobRows as $jr): ?>
                    <tr>
                        <td><?=e($jr['title'])?></td>
                        <td><?=e($jr['company_name'])?></td>
                        <td><?=e($jr['location'])?></td>
                        <td><?=e($jr['salary_offer'] ?: '—')?></td>
                        <td>
                            <a class="btn sm blue" href="content_admin.php?tab=jobs&amp;edit=<?=(int)$jr['id']?>">Edit</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this job vacancy?')">
                                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                                <input type="hidden" name="tab" value="jobs">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?=(int)$jr['id']?>">
                                <button class="btn sm danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$jobRows): ?><tr><td colspan="5" class="muted">No job vacancies found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php elseif ($tab === 'gov'):
    $govRows = q('SELECT * FROM gov_offices ORDER BY req_key, id')->fetchAll();
?>
    <section class="card glass">
        <h2><?=$editRow ? 'Edit Gov Office' : 'Add Gov Office'?></h2>
        <form method="post" class="alert-admin-form">
            <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
            <input type="hidden" name="tab" value="gov">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?=e($editRow['id'] ?? 0)?>">

            <label>Requirement
                <select name="req_key">
                    <?php foreach ($reqMeta as $rk => $rm): ?>
                        <option value="<?=e($rk)?>" <?=($editRow['req_key'] ?? 'psa') === $rk ? 'selected' : ''?>><?=e($rm[0])?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Office Name <input name="office_name" required value="<?=e($editRow['office_name'] ?? '')?>"></label>
            <label>City / Area <input name="area" required value="<?=e($editRow['area'] ?? 'City of San Fernando')?>"></label>
            <label>Address <input name="address" required value="<?=e($editRow['address'] ?? '')?>"></label>
            <label>Google Maps Search Query <input name="map_query" required value="<?=e($editRow['map_query'] ?? '')?>"></label>
            <label>Notes <input name="notes" value="<?=e($editRow['notes'] ?? '')?>"></label>
            <label>Served Areas (comma-separated) <input name="serves" value="<?=e($editRow['serves'] ?? '')?>" placeholder="e.g. City of San Fernando, Mexico, Bacolor"></label>
            <label class="alert-admin-check">
                <input type="checkbox" name="dynamic_local" value="1" <?=!empty($editRow['dynamic_local']) ? 'checked' : ''?>>
                Append selected town to Google Maps query (for local Police/Barangay)
            </label>
            <div class="alert-filter-actions">
                <button class="btn" type="submit"><?=$editRow ? 'Update Office' : 'Add Office'?></button>
                <?php if ($editRow): ?><a class="btn blue" href="content_admin.php?tab=gov">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card glass">
        <h2>Current Gov Offices (<?=count($govRows)?>)</h2>
        <div class="alert-admin-table-wrap">
            <table>
                <thead><tr><th>Requirement</th><th>Office Name</th><th>Area</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($govRows as $gr): ?>
                    <tr>
                        <td><?=e($gr['req_short'])?></td>
                        <td><?=e($gr['office_name'])?><br><small class="muted"><?=e($gr['address'])?></small></td>
                        <td><?=e($gr['area'])?></td>
                        <td>
                            <a class="btn sm blue" href="content_admin.php?tab=gov&amp;edit=<?=(int)$gr['id']?>">Edit</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this office?')">
                                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                                <input type="hidden" name="tab" value="gov">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?=(int)$gr['id']?>">
                                <button class="btn sm danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php elseif ($tab === 'announcements'):
    $annRows = q('SELECT a.*, l.name AS town FROM announcements a LEFT JOIN lgus l ON l.id = a.lgu_id ORDER BY a.posted DESC LIMIT 100')->fetchAll();
?>
    <section class="card glass">
        <h2><?=$editRow ? 'Edit Announcement' : 'Post New Announcement'?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
            <input type="hidden" name="tab" value="announcements">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?=e($editRow['id'] ?? 0)?>">

            <label>Place
                <select name="lgu_id">
                    <option value="0">All of Pampanga (Capitol)</option>
                    <?php foreach ($lgus as $l): ?>
                        <option value="<?=(int)$l['id']?>" <?=(int)($editRow['lgu_id'] ?? 0) === (int)$l['id'] ? 'selected' : ''?>><?=e($l['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Headline <input name="title" required value="<?=e($editRow['title'] ?? '')?>"></label>
            <label>Details <textarea name="body" rows="4"><?=e($editRow['body'] ?? '')?></textarea></label>
            <label>External URL (optional) <input name="url" value="<?=e($editRow['url'] ?? '')?>"></label>
            <p>
                <button class="btn" type="submit"><?=$editRow ? 'Update Announcement' : 'Post Announcement'?></button>
                <?php if ($editRow): ?><a class="btn blue" href="content_admin.php?tab=announcements">Cancel</a><?php endif; ?>
            </p>
        </form>
    </section>

    <section class="card glass">
        <h2>Current Announcements (<?=count($annRows)?>)</h2>
        <div class="alert-admin-table-wrap">
            <table>
                <thead><tr><th>Headline</th><th>Place</th><th>Posted</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($annRows as $ar): ?>
                    <tr>
                        <td><?=e($ar['title'])?></td>
                        <td><?=e($ar['town'] ?: 'Pampanga')?></td>
                        <td><?=e($ar['posted'])?></td>
                        <td>
                            <a class="btn sm blue" href="content_admin.php?tab=announcements&amp;edit=<?=(int)$ar['id']?>">Edit</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this announcement?')">
                                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                                <input type="hidden" name="tab" value="announcements">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?=(int)$ar['id']?>">
                                <button class="btn sm danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php elseif ($tab === 'news'):
    $newsRows = q('SELECT n.*, l.name AS town FROM news n LEFT JOIN lgus l ON l.id = n.lgu_id ORDER BY n.published DESC LIMIT 100')->fetchAll();
?>
    <section class="card glass">
        <h2><?=$editRow ? 'Edit News Article' : 'Add News Article'?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
            <input type="hidden" name="tab" value="news">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?=e($editRow['id'] ?? 0)?>">

            <label>Place
                <select name="lgu_id">
                    <option value="0">All of Pampanga</option>
                    <?php foreach ($lgus as $l): ?>
                        <option value="<?=(int)$l['id']?>" <?=(int)($editRow['lgu_id'] ?? 0) === (int)$l['id'] ? 'selected' : ''?>><?=e($l['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Headline <input name="title" required value="<?=e($editRow['title'] ?? '')?>"></label>
            <label>Article URL <input name="url" required value="<?=e($editRow['url'] ?? '')?>"></label>
            <label>Source <input name="source" value="<?=e($editRow['source'] ?? 'Philippine Daily Inquirer')?>"></label>
            <p>
                <button class="btn" type="submit"><?=$editRow ? 'Update News' : 'Add News'?></button>
                <?php if ($editRow): ?><a class="btn blue" href="content_admin.php?tab=news">Cancel</a><?php endif; ?>
            </p>
        </form>
    </section>

    <section class="card glass">
        <h2>Current News Articles (<?=count($newsRows)?>)</h2>
        <div class="alert-admin-table-wrap">
            <table>
                <thead><tr><th>Headline</th><th>Place</th><th>Source</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($newsRows as $nr): ?>
                    <tr>
                        <td><?=e($nr['title'])?></td>
                        <td><?=e($nr['town'] ?: 'Pampanga')?></td>
                        <td><?=e($nr['source'])?></td>
                        <td>
                            <a class="btn sm blue" href="content_admin.php?tab=news&amp;edit=<?=(int)$nr['id']?>">Edit</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this news article?')">
                                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                                <input type="hidden" name="tab" value="news">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?=(int)$nr['id']?>">
                                <button class="btn sm danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php elseif ($tab === 'elearning'):
    $elRows = q('SELECT * FROM elearning_links ORDER BY tab_key, id')->fetchAll();
    $tabLabels = [
        'lss' => 'Lean Six Sigma (Local)',
        'tesda' => 'TESDA Certifications (Local)',
        'online' => 'Online Certifications (Free)',
        'empdev' => 'Employment Development (YouTube)',
    ];
?>
    <section class="card glass">
        <h2><?=$editRow ? 'Edit E-Learning Item' : 'Add E-Learning Item'?></h2>
        <form method="post" class="alert-admin-form">
            <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
            <input type="hidden" name="tab" value="elearning">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?=e($editRow['id'] ?? 0)?>">

            <label>Category Tab
                <select name="tab_key">
                    <?php foreach ($tabLabels as $tk => $tl): ?>
                        <option value="<?=e($tk)?>" <?=($editRow['tab_key'] ?? 'lss') === $tk ? 'selected' : ''?>><?=e($tl)?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Provider / Group <input name="provider" required value="<?=e($editRow['provider'] ?? '')?>" placeholder="e.g. Google (Free)"></label>
            <label>Course / Video Title <input name="title" required value="<?=e($editRow['title'] ?? '')?>"></label>
            <label>Badge <input name="badge" value="<?=e($editRow['badge'] ?? 'Free · Online')?>"></label>
            <label>URL <input name="url" required value="<?=e($editRow['url'] ?? '')?>"></label>
            <label>Button Text <input name="cta_label" value="<?=e($editRow['cta_label'] ?? 'Open course ↗')?>"></label>
            <label>Description <input name="description" value="<?=e($editRow['description'] ?? '')?>"></label>
            <div class="alert-filter-actions">
                <button class="btn" type="submit"><?=$editRow ? 'Update Item' : 'Add Item'?></button>
                <?php if ($editRow): ?><a class="btn blue" href="content_admin.php?tab=elearning">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card glass">
        <h2>Current E-Learning Courses &amp; Videos (<?=count($elRows)?>)</h2>
        <div class="alert-admin-table-wrap">
            <table>
                <thead><tr><th>Tab</th><th>Provider</th><th>Title</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($elRows as $er): ?>
                    <tr>
                        <td><?=e($tabLabels[$er['tab_key']] ?? $er['tab_key'])?></td>
                        <td><?=e($er['provider'])?></td>
                        <td><?=e($er['title'])?><br><small class="muted"><?=e($er['url'])?></small></td>
                        <td>
                            <a class="btn sm blue" href="content_admin.php?tab=elearning&amp;edit=<?=(int)$er['id']?>">Edit</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this E-Learning item?')">
                                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                                <input type="hidden" name="tab" value="elearning">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?=(int)$er['id']?>">
                                <button class="btn sm danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
