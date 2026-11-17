<?php
// Employee-only E-Learning Hub: Lean Six Sigma, TESDA, Free Online Certs, 200-Item CSE Reviewer, and Employment Development.
require_once __DIR__ . '/includes/config.php';
$u = need('employee');

$title = 'E-Learning & Certifications';

$tabs = [
    'lss' => [
        'label' => 'Lean Six Sigma (Local)',
        'eyebrow' => 'LOCAL PROCESS IMPROVEMENT',
        'heading' => 'Lean Six Sigma — Philippines',
        'subtitle' => 'Local Philippine training and belt certification programs (White, Yellow, Green, and Black Belt).',
        'groups' => [
            [
                'provider' => 'Philippine Training Providers',
                'items' => [
                    [
                        'title' => 'Six Sigma PH',
                        'badge' => 'Local · PH',
                        'desc' => 'Philippines-based Lean Six Sigma training and certification tailored for services, BPO, and operations teams.',
                        'url' => 'https://www.6sigmaph.com/'
                    ],
                    [
                        'title' => 'AIM — Lean Six Sigma Yellow Belt',
                        'badge' => 'Local · AIM',
                        'desc' => 'Asian Institute of Management Yellow Belt program with practical process-improvement training and digital PDF certificate.',
                        'url' => 'https://aim.edu/programs/lean-six-sigma-yellow-belt-certification/'
                    ],
                    [
                        'title' => 'Elevate Six Sigma Philippines',
                        'badge' => 'Local · PH',
                        'desc' => 'Offers 16-hour Lean Six Sigma Yellow Belt and Green Belt training and certification in the Philippines.',
                        'url' => 'https://www.elevatesixsigma.com/'
                    ],
                    [
                        'title' => 'Invensis Learning — Green Belt (PH)',
                        'badge' => 'Local · IASSC Aligned',
                        'desc' => 'DMAIC-focused Lean Six Sigma Green Belt training aligned to the IASSC Body of Knowledge for PH professionals.',
                        'url' => 'https://www.invensislearning.com/ph/lean-six-sigma-green-belt-certification-training/'
                    ],
                ],
            ],
        ],
    ],
    'tesda' => [
        'label' => 'TESDA Certifications (Local)',
        'eyebrow' => 'NATIONAL TVET & NC PROGRAMS',
        'heading' => 'TESDA Certifications & Free Online Courses',
        'subtitle' => 'Official Technical Education and Skills Development Authority (TESDA) online courses and assessment portals.',
        'groups' => [
            [
                'provider' => 'TESDA Official Platforms & Courses',
                'items' => [
                    [
                        'title' => 'TESDA Online Program (TOP) — All Courses',
                        'badge' => 'Free · Official',
                        'desc' => 'National e-learning platform offering free self-paced Massive Open Online Courses (MOOCs) for Filipino workers.',
                        'url' => 'https://e-tesda.gov.ph/course/'
                    ],
                    [
                        'title' => 'TESDA Official Portal (Assessment & NC)',
                        'badge' => 'Official · PH',
                        'desc' => 'Official TESDA portal for National Certificate (NC) assessment schedules and accredited assessment centers.',
                        'url' => 'https://www.tesda.gov.ph/'
                    ],
                    [
                        'title' => 'TESDA TOP — Digital Marketing Level III',
                        'badge' => 'Free · Online',
                        'desc' => 'Free TESDA online course covering digital marketing fundamentals and practical campaign skills.',
                        'url' => 'https://e-tesda.gov.ph/course/index.php?categoryid=2004'
                    ],
                    [
                        'title' => 'TESDA TOP — Entrepreneurship',
                        'badge' => 'Free · Online',
                        'desc' => 'Free TESDA module on starting, managing, and growing a small business in the Philippines.',
                        'url' => 'https://e-tesda.gov.ph/course/index.php?categoryid=17'
                    ],
                    [
                        'title' => 'TESDA TOP — Caregiving (Elderly) NC II',
                        'badge' => 'Free · NC II Track',
                        'desc' => 'Free online vocational preparation for Caregiving (Elderly) NC II competency.',
                        'url' => 'https://e-tesda.gov.ph/course/index.php?categoryid=786'
                    ],
                ],
            ],
        ],
    ],
    'online' => [
        'label' => 'Online Certifications (Free)',
        'eyebrow' => 'GLOBAL FREE PLATFORMS',
        'heading' => 'Free Online Certifications — Google, Coursera & Microsoft',
        'subtitle' => 'Free self-paced courses, badges, and certification pathways from Google, Coursera, and Microsoft.',
        'groups' => [
            [
                'provider' => 'Google (Free)',
                'items' => [
                    [
                        'title' => 'Google Skillshop',
                        'badge' => 'Free · Certificate',
                        'desc' => 'Free self-paced training and product certifications for Google Ads, Analytics, and workplace tools.',
                        'url' => 'https://skillshop.withgoogle.com/'
                    ],
                    [
                        'title' => 'Google Digital Garage (via Skillshop)',
                        'badge' => 'Free · Courses',
                        'desc' => 'Accelerate your career with free online digital skills courses from marketing to coding.',
                        'url' => 'https://skillshop.exceedlms.com/student/catalog/list?category_ids=7879-google-digital-garage'
                    ],
                    [
                        'title' => 'Google Skills (Cloud Training)',
                        'badge' => 'Free · Badges',
                        'desc' => 'Free training and hands-on learning paths for Google Cloud beginners and practitioners.',
                        'url' => 'https://www.skills.google/'
                    ],
                    [
                        'title' => 'Grow with Google — AI Training',
                        'badge' => 'Free · AI Skills',
                        'desc' => 'Free AI essentials and career-focused artificial intelligence courses developed by Google.',
                        'url' => 'https://grow.google/ai'
                    ],
                ],
            ],
            [
                'provider' => 'Coursera (Free)',
                'items' => [
                    [
                        'title' => 'Coursera Free Courses Catalog',
                        'badge' => 'Free · Online',
                        'desc' => 'Browse free university and industry courses across tech, data, business, and career skills.',
                        'url' => 'https://www.coursera.org/courses?query=free'
                    ],
                    [
                        'title' => 'Coursera Popular Free Courses',
                        'badge' => 'Free · Curated',
                        'desc' => 'Curated collection of top free courses from leading universities worldwide.',
                        'url' => 'https://www.coursera.org/collections/popular-free-courses/'
                    ],
                    [
                        'title' => 'Coursera Free Business Courses',
                        'badge' => 'Free · Business',
                        'desc' => 'Free online courses covering business strategy, Excel, communication, and workplace productivity.',
                        'url' => 'https://www.coursera.org/courses?query=free&skills=Business'
                    ],
                ],
            ],
            [
                'provider' => 'Microsoft (Free)',
                'items' => [
                    [
                        'title' => 'Microsoft Learn',
                        'badge' => 'Free · Platform',
                        'desc' => 'Free interactive learning paths, modules, and role-based technical training from Microsoft.',
                        'url' => 'https://learn.microsoft.com/'
                    ],
                    [
                        'title' => 'Microsoft Learn — Browse All Training',
                        'badge' => 'Free · Courses',
                        'desc' => 'Self-paced courses on Azure, AI, Power BI, Cybersecurity, Software Development, and Microsoft 365.',
                        'url' => 'https://learn.microsoft.com/en-us/training/browse/'
                    ],
                    [
                        'title' => 'Microsoft Credentials & Certifications',
                        'badge' => 'Credentials',
                        'desc' => 'Explore official Microsoft role-based certifications and free annual credential renewals.',
                        'url' => 'https://learn.microsoft.com/en-us/credentials/'
                    ],
                    [
                        'title' => 'Microsoft Learn Student Hub Credentials',
                        'badge' => 'Free · Student Hub',
                        'desc' => 'Free training, learning resources, and skill assessments with no credit card required.',
                        'url' => 'https://learn.microsoft.com/en-us/training/student-hub/credentials'
                    ],
                ],
            ],
        ],
    ],
    'cse' => [
        'label' => 'Civil Service Reviewer',
        'eyebrow' => 'CAREER SERVICE EXAM PREP',
        'heading' => 'Civil Service Exam (CSE) Mock Reviewer',
        'subtitle' => 'Take the full 200-item shuffled mock exam (50 Verbal, 50 Numerical, 50 Logical, 50 General Information) or practice by subject.',
        'groups' => [],
    ],
    'empdev' => [
        'label' => 'Employment Development',
        'eyebrow' => 'CAREER READINESS VIDEOS',
        'heading' => 'Employment Development (YouTube Guides)',
        'subtitle' => 'Curated YouTube video guides on interview preparation, interview attire, resume writing, and succeeding as a new hire.',
        'groups' => [
            [
                'provider' => 'How to Ace a Job Interview & Interview Prep',
                'items' => [
                    [
                        'title' => '6 Steps to ACE your Job Interview (+ pep talk!)',
                        'badge' => 'YouTube · AdviceWithErin',
                        'desc' => 'Step-by-step interview preparation guide covering research, storytelling, and confidence.',
                        'url' => 'https://www.youtube.com/watch?v=mmQcX6HpCGs',
                        'cta' => 'Watch on YouTube ↗'
                    ],
                    [
                        'title' => 'Top 10 Most Common Job Interview Questions ANSWERED',
                        'badge' => 'YouTube · Cass Thompson',
                        'desc' => 'Practical sample answers for the most frequently asked job interview questions.',
                        'url' => 'https://www.youtube.com/watch?v=U0YdO6zGSHk',
                        'cta' => 'Watch on YouTube ↗'
                    ],
                ],
            ],
            [
                'provider' => 'How to Dress Well for Your Job Interview',
                'items' => [
                    [
                        'title' => 'What to Wear to an Interview: Dress Codes & Outfit Ideas',
                        'badge' => 'YouTube · Emily Durham',
                        'desc' => 'Clear breakdown of interview dress codes, polished outfit ideas, and what to avoid.',
                        'url' => 'https://www.youtube.com/watch?v=jzPAjrrtLxY',
                        'cta' => 'Watch on YouTube ↗'
                    ],
                    [
                        'title' => 'What to Wear to a Job Interview (3 Different Dress Codes)',
                        'badge' => 'YouTube · Brock McGoff',
                        'desc' => 'How to match your attire to casual, business casual, and formal interview settings.',
                        'url' => 'https://www.youtube.com/watch?v=iaQvQ6VuTSY',
                        'cta' => 'Watch on YouTube ↗'
                    ],
                ],
            ],
            [
                'provider' => 'Guide for Resume Writing',
                'items' => [
                    [
                        'title' => 'Write an Incredible Resume: 5 Golden Rules!',
                        'badge' => 'YouTube · Jeff Su',
                        'desc' => 'Actionable resume formatting and bullet-point tips to stand out to recruiters.',
                        'url' => 'https://www.youtube.com/watch?v=Tt08KmFfIYQ',
                        'cta' => 'Watch on YouTube ↗'
                    ],
                    [
                        'title' => 'How to Make A Resume that Stands Out in 2026',
                        'badge' => 'YouTube · Teal',
                        'desc' => 'Modern resume structure and keyword tips from hiring managers.',
                        'url' => 'https://www.youtube.com/watch?v=yRha1slhTJU',
                        'cta' => 'Watch on YouTube ↗'
                    ],
                ],
            ],
            [
                'provider' => 'Tips on Being a New Hire',
                'items' => [
                    [
                        'title' => 'How to Succeed in Your New Job | The Way We Work',
                        'badge' => 'YouTube · TED',
                        'desc' => 'Concise guide on building trust, learning fast, and thriving in a new role.',
                        'url' => 'https://www.youtube.com/watch?v=WgR6mUSsEig',
                        'cta' => 'Watch on YouTube ↗'
                    ],
                    [
                        'title' => '10 TIPS for STARTING A NEW JOB! (First Day at Work)',
                        'badge' => 'YouTube · CareerVidz',
                        'desc' => 'What to do and say during your first day and first week as a new hire.',
                        'url' => 'https://www.youtube.com/watch?v=znku_lZ8mA4',
                        'cta' => 'Watch on YouTube ↗'
                    ],
                ],
            ],
        ],
    ],
];

// Load live editable E-Learning items from elearning_links when available.
try {
    $dbEl = q('SELECT * FROM elearning_links ORDER BY tab_key, id')->fetchAll();
    if ($dbEl) {
        foreach (['lss', 'tesda', 'online', 'empdev'] as $tk) {
            if (isset($tabs[$tk])) {
                $tabs[$tk]['groups'] = [];
            }
        }
        $grouped = [];
        foreach ($dbEl as $row) {
            $tk = $row['tab_key'];
            $prov = $row['provider'];
            $grouped[$tk][$prov][] = [
                'title' => $row['title'],
                'badge' => $row['badge'],
                'desc'  => $row['description'],
                'url'   => $row['url'],
                'cta'   => $row['cta_label'] ?: 'Open course ↗',
            ];
        }
        foreach ($grouped as $tk => $provMap) {
            if (!isset($tabs[$tk])) continue;
            foreach ($provMap as $provName => $itemsList) {
                $tabs[$tk]['groups'][] = [
                    'provider' => $provName,
                    'items' => $itemsList,
                ];
            }
        }
    }
} catch (Throwable $e) {
    error_log('ABEKa elearning_links load: ' . $e->getMessage());
}

$activeTab = (string)($_GET['tab'] ?? 'lss');
if (!isset($tabs[$activeTab])) {
    $activeTab = 'lss';
}

require __DIR__ . '/includes/header.php';
?>

<section class="card elearn-hero">
    <p class="elearn-kicker">ABEKa EMPLOYEE UPSKILLING</p>
    <h1>E-Learning &amp; Certifications</h1>
    <p class="muted">Build job-ready credentials through local Lean Six Sigma, TESDA programs, and free online certifications from Google, Coursera, and Microsoft.</p>
</section>

<div class="elearn-tabs" role="tablist" aria-label="E-Learning categories">
    <?php foreach ($tabs as $key => $tab): ?>
        <button
            type="button"
            role="tab"
            class="elearn-tab <?=$activeTab === $key ? 'active' : ''?>"
            data-tab="<?=e($key)?>"
            aria-selected="<?=$activeTab === $key ? 'true' : 'false'?>"
        >
            <?=e($tab['label'])?>
        </button>
    <?php endforeach; ?>
</div>

<?php foreach ($tabs as $key => $tab): ?>
    <section
        class="card elearn-panel <?=$activeTab === $key ? 'active' : ''?>"
        data-panel="<?=e($key)?>"
        role="tabpanel"
        <?=$activeTab === $key ? '' : 'hidden'?>
    >
        <div class="elearn-panel-head">
            <span class="elearn-eyebrow"><?=e($tab['eyebrow'])?></span>
            <h2><?=e($tab['heading'])?></h2>
            <p class="muted"><?=e($tab['subtitle'])?></p>
        </div>

        <?php foreach ($tab['groups'] as $group): ?>
            <h3 class="elearn-group-title"><?=e($group['provider'])?></h3>
            <div class="elearn-grid">
                <?php foreach ($group['items'] as $item): ?>
                    <article class="elearn-card">
                        <div class="elearn-card-top">
                            <span class="elearn-badge"><?=e($item['badge'])?></span>
                            <h4><?=e($item['title'])?></h4>
                            <p><?=e($item['desc'])?></p>
                        </div>
                        <a class="btn sm" href="<?=e($item['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($item['cta'] ?? 'Open course ↗')?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($key === 'cse'): ?>
            <div class="alert-disclaimer cse-disclaimer">
                <strong>Disclaimer:</strong> This is only a practice reviewer for the Civil Service Exam and is not the actual Civil Service Commission (CSC) examination.
            </div>
            <div class="cse-toolbar">
                <div class="cse-modes" role="group" aria-label="Reviewer mode">
                    <button type="button" class="btn sm cse-mode active" data-mode="all">Full Mock (200 Shuffled)</button>
                    <button type="button" class="btn sm blue cse-mode" data-mode="quick10">Quick 10 Items</button>
                    <button type="button" class="btn sm blue cse-mode" data-mode="Verbal">Verbal (50)</button>
                    <button type="button" class="btn sm blue cse-mode" data-mode="Numerical">Numerical (50)</button>
                    <button type="button" class="btn sm blue cse-mode" data-mode="Logical">Logical (50)</button>
                    <button type="button" class="btn sm blue cse-mode" data-mode="General Information">General Info (50)</button>
                </div>
                <div class="cse-meta">
                    <span id="cseProgress" class="cse-pill">Answered: 0 / 200</span>
                    <span id="cseTimer" class="cse-pill">Timer: 03:10:00</span>
                    <button type="button" class="btn sm blue" id="cseTimerToggle">Start Timer</button>
                    <button type="button" class="btn sm blue" id="cseShuffleBtn">Reshuffle</button>
                </div>
            </div>

            <div id="cseResultBox" class="cse-result-box" hidden></div>
            <div id="cseQuiz" class="cse-quiz"></div>

            <div class="cse-quiz-actions">
                <button type="button" class="btn" id="cseCheckBtn">Submit &amp; Check Answers</button>
                <button type="button" class="btn blue" id="cseResetBtn">Reset &amp; Reshuffle</button>
                <strong id="cseScore" class="cse-score"></strong>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<script>
(function () {
    const buttons = document.querySelectorAll('.elearn-tab');
    const panels = document.querySelectorAll('.elearn-panel');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = btn.getAttribute('data-tab');
            buttons.forEach(function (b) {
                const on = b.getAttribute('data-tab') === target;
                b.classList.toggle('active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function (p) {
                const on = p.getAttribute('data-panel') === target;
                p.classList.toggle('active', on);
                p.hidden = !on;
            });
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', 'elearning.php?tab=' + encodeURIComponent(target));
            }
        });
    });

    let allQuestions = [];
    let activeQuestions = [];
    let currentMode = "all";
    let timerSeconds = 190 * 60;
    let timerHandle = null;

    const quizWrap = document.getElementById("cseQuiz");
    const checkBtn = document.getElementById("cseCheckBtn");
    const resetBtn = document.getElementById("cseResetBtn");
    const shuffleBtn = document.getElementById("cseShuffleBtn");
    const progressEl = document.getElementById("cseProgress");
    const timerEl = document.getElementById("cseTimer");
    const timerToggle = document.getElementById("cseTimerToggle");
    const resultBox = document.getElementById("cseResultBox");
    const scoreEl = document.getElementById("cseScore");
    const modeButtons = document.querySelectorAll(".cse-mode");

    function shuffleArray(arr) {
        const copy = arr.slice();
        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            const tmp = copy[i];
            copy[i] = copy[j];
            copy[j] = tmp;
        }
        return copy;
    }

    function formatTime(sec) {
        const h = String(Math.floor(sec / 3600)).padStart(2, "0");
        const m = String(Math.floor((sec % 3600) / 60)).padStart(2, "0");
        const s = String(sec % 60).padStart(2, "0");
        return h + ":" + m + ":" + s;
    }

    function stopTimer() {
        if (timerHandle) {
            clearInterval(timerHandle);
            timerHandle = null;
        }
        if (timerToggle) timerToggle.textContent = "Start Timer";
    }

    function resetTimer() {
        stopTimer();
        timerSeconds = currentMode === "all" ? 190 * 60 : (currentMode === "quick10" ? 10 * 60 : 45 * 60);
        if (timerEl) timerEl.textContent = "Timer: " + formatTime(timerSeconds);
    }

    function updateProgress() {
        if (!progressEl) return;
        const answered = quizWrap ? quizWrap.querySelectorAll('input[type="radio"]:checked').length : 0;
        progressEl.textContent = "Answered: " + answered + " / " + activeQuestions.length;
    }

    function buildActiveSet() {
        if (currentMode === "quick10") {
            activeQuestions = shuffleArray(allQuestions).slice(0, 10);
        } else {
            const pool = currentMode === "all"
                ? allQuestions
                : allQuestions.filter(function (item) { return item.category === currentMode; });
            activeQuestions = shuffleArray(pool);
        }
        renderQuiz();
        resetTimer();
    }

    function renderQuiz() {
        if (!quizWrap) return;
        quizWrap.innerHTML = "";
        scoreEl.textContent = "";
        if (resultBox) {
            resultBox.hidden = true;
            resultBox.innerHTML = "";
        }

        const frag = document.createDocumentFragment();
        activeQuestions.forEach(function (item, idx) {
            const card = document.createElement("div");
            card.className = "cse-q-card";

            const head = document.createElement("div");
            head.className = "cse-q-head";

            const badge = document.createElement("span");
            badge.className = "elearn-badge";
            badge.textContent = item.category;
            head.appendChild(badge);

            const num = document.createElement("small");
            num.className = "muted";
            num.textContent = "Item " + (idx + 1) + " of " + activeQuestions.length;
            head.appendChild(num);
            card.appendChild(head);

            const title = document.createElement("p");
            title.className = "cse-q-title";
            title.textContent = item.q;
            card.appendChild(title);

            const letters = ["A", "B", "C", "D"];
            item.choices.forEach(function (choice, cIdx) {
                const label = document.createElement("label");
                label.className = "cse-choice";
                const radio = document.createElement("input");
                radio.type = "radio";
                radio.name = "cse_q_" + idx;
                radio.value = String(cIdx);
                radio.addEventListener("change", updateProgress);
                const letterSpan = document.createElement("strong");
                letterSpan.className = "cse-letter";
                letterSpan.textContent = (letters[cIdx] || String(cIdx + 1)) + ".";
                const textSpan = document.createElement("span");
                textSpan.textContent = choice;
                label.appendChild(radio);
                label.appendChild(letterSpan);
                label.appendChild(textSpan);
                card.appendChild(label);
            });

            const fb = document.createElement("div");
            fb.className = "cse-fb";
            fb.id = "cse_fb_" + idx;
            card.appendChild(fb);
            frag.appendChild(card);
        });

        quizWrap.appendChild(frag);
        updateProgress();
    }

    function filterReview(mode) {
        if (!quizWrap) return;
        const cards = quizWrap.querySelectorAll(".cse-q-card");
        cards.forEach(function (card) {
            if (mode === "all") {
                card.hidden = false;
            } else if (mode === "wrong") {
                card.hidden = !card.classList.contains("cse-card-miss");
            } else if (mode === "correct") {
                card.hidden = !card.classList.contains("cse-card-ok");
            }
        });
    }

    function gradeQuiz() {
        stopTimer();
        let score = 0;
        let wrongCount = 0;
        let unansweredCount = 0;
        const letters = ["A", "B", "C", "D"];
        const catTotals = {};
        const catCorrect = {};

        const cards = quizWrap ? quizWrap.querySelectorAll(".cse-q-card") : [];
        activeQuestions.forEach(function (item, idx) {
            catTotals[item.category] = (catTotals[item.category] || 0) + 1;
            catCorrect[item.category] = catCorrect[item.category] || 0;

            const card = cards[idx];
            if (card) {
                card.classList.remove("cse-card-ok", "cse-card-miss");
                card.hidden = false;
                card.querySelectorAll(".cse-choice").forEach(function (lbl, cIdx) {
                    lbl.classList.remove("cse-choice-correct", "cse-choice-wrong");
                    if (cIdx === item.ans) {
                        lbl.classList.add("cse-choice-correct");
                    }
                });
            }

            const chosen = document.querySelector('input[name="cse_q_' + idx + '"]:checked');
            const fb = document.getElementById("cse_fb_" + idx);
            const correctLabel = (letters[item.ans] || "") + ". " + item.choices[item.ans];

            if (!chosen) {
                unansweredCount++;
                if (card) card.classList.add("cse-card-miss");
                fb.className = "cse-fb cse-fb-miss";
                fb.textContent = "Unanswered. Correct answer: " + correctLabel + " — " + item.exp;
                return;
            }

            const chosenIdx = Number(chosen.value);
            if (chosenIdx === item.ans) {
                score++;
                catCorrect[item.category]++;
                if (card) card.classList.add("cse-card-ok");
                fb.className = "cse-fb cse-fb-ok";
                fb.textContent = "Correct (" + correctLabel + ")! " + item.exp;
            } else {
                wrongCount++;
                if (card) {
                    card.classList.add("cse-card-miss");
                    const chosenLbl = card.querySelectorAll(".cse-choice")[chosenIdx];
                    if (chosenLbl) chosenLbl.classList.add("cse-choice-wrong");
                }
                const yourLabel = (letters[chosenIdx] || "") + ". " + item.choices[chosenIdx];
                fb.className = "cse-fb cse-fb-miss";
                fb.textContent = "Wrong (You chose: " + yourLabel + "). Correct answer: " + correctLabel + " — " + item.exp;
            }
        });

        const pct = activeQuestions.length ? ((score / activeQuestions.length) * 100).toFixed(1) : "0.0";
        const passed = Number(pct) >= 80;
        scoreEl.textContent = "Score: " + score + " / " + activeQuestions.length + " (" + pct + "%)";

        if (resultBox) {
            const breakdown = Object.keys(catTotals).map(function (cat) {
                return "<li><strong>" + cat + ":</strong> " + catCorrect[cat] + " / " + catTotals[cat] + "</li>";
            }).join("");
            resultBox.innerHTML =
                "<strong>Final Result: " + score + " / " + activeQuestions.length + " (" + pct + "%) — " +
                (passed ? "Passing Rate Reached (80%+)" : "Keep Practicing (80% Target)") +
                "</strong>" +
                "<p class=\"cse-summary-counts\">Correct: <b>" + score + "</b> · Wrong: <b>" + wrongCount + "</b> · Unanswered: <b>" + unansweredCount + "</b></p>" +
                "<ul>" + breakdown + "</ul>" +
                "<div class=\"cse-review-filters\">" +
                "<button type=\"button\" class=\"btn sm\" data-review=\"all\">Show All (" + activeQuestions.length + ")</button> " +
                "<button type=\"button\" class=\"btn sm blue\" data-review=\"wrong\">Wrong / Unanswered (" + (wrongCount + unansweredCount) + ")</button> " +
                "<button type=\"button\" class=\"btn sm blue\" data-review=\"correct\">Correct Only (" + score + ")</button>" +
                "</div>";
            resultBox.hidden = false;
            resultBox.querySelectorAll("[data-review]").forEach(function (rBtn) {
                rBtn.addEventListener("click", function () {
                    filterReview(rBtn.getAttribute("data-review"));
                });
            });
            resultBox.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
    }

    modeButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            currentMode = btn.getAttribute("data-mode");
            modeButtons.forEach(function (b) {
                const on = b === btn;
                b.classList.toggle("active", on);
                b.classList.toggle("blue", !on);
            });
            buildActiveSet();
        });
    });

    if (timerToggle) {
        timerToggle.addEventListener("click", function () {
            if (timerHandle) {
                stopTimer();
            } else {
                timerToggle.textContent = "Pause Timer";
                timerHandle = setInterval(function () {
                    if (timerSeconds > 0) {
                        timerSeconds--;
                        timerEl.textContent = "Timer: " + formatTime(timerSeconds);
                    } else {
                        stopTimer();
                        gradeQuiz();
                    }
                }, 1000);
            }
        });
    }

    if (checkBtn) checkBtn.addEventListener("click", gradeQuiz);
    if (resetBtn) resetBtn.addEventListener("click", buildActiveSet);
    if (shuffleBtn) shuffleBtn.addEventListener("click", buildActiveSet);

    if (quizWrap) {
        fetch("assets/data/cse_200.json")
            .then(function (res) { return res.json(); })
            .then(function (data) {
                allQuestions = Array.isArray(data) ? data : [];
                buildActiveSet();
            })
            .catch(function () {
                quizWrap.innerHTML = '<p class="muted">Unable to load the 200-item reviewer bank.</p>';
            });
    }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
