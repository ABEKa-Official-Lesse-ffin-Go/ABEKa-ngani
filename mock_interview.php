<?php
require 'includes/config.php';

// ---- Access control: only logged-in EMPLOYEES may use the mock interview ----

$sess = $_SESSION['user'] ?? null;
$uid = $_SESSION['user_id'] ?? $_SESSION['uid'] ?? $_SESSION['id']
    ?? (is_array($sess) ? ($sess['id'] ?? null) : (is_numeric($sess) ? $sess : null));
if (!$uid) { header('Location: login.php'); exit; }

$u = q('SELECT name, role FROM users WHERE id=?', [$uid])->fetch();
if (!$u || ($u['role'] ?? '') !== 'employee') { header('Location: index.php'); exit; }
$name = $u['name'] ?? '';

$title = 'Mock Interview';
require 'includes/header.php';
?>
<style>
  .mi { width: min(760px, 94%); margin: 24px auto; }
  .mi-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
  .mi-bar { height: 8px; background: rgba(128,128,128,.25); border-radius: 99px; overflow: hidden; margin: 10px 0 16px; }
  .mi-bar i { display: block; height: 100%; width: 0; background: #2f7be5; transition: width .3s; }
  .mi-chat { display: flex; flex-direction: column; gap: 10px; max-height: 52vh; overflow-y: auto; padding: 4px; }
  .mi-msg { max-width: 82%; padding: 10px 14px; border-radius: 14px; line-height: 1.45; white-space: pre-wrap; }
  .mi-bot { background: rgba(47,123,229,.14); align-self: flex-start; border-bottom-left-radius: 4px; }
  .mi-me  { background: #2f7be5; color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }
  .mi-form { display: flex; gap: 8px; margin-top: 14px; align-items: flex-end; }
  .mi-form textarea { flex: 1; min-height: 70px; resize: vertical; padding: 10px; font: inherit; }
  .mi-result h2 { margin: 0 0 4px; }
  .mi-score { font-size: 2.4rem; font-weight: 700; }
  .mi-item { border-top: 1px solid rgba(128,128,128,.3); padding: 10px 0; }
  .mi-item small { opacity: .75; display: block; margin-top: 4px; }
  .hidden { display: none; }
</style>

<div class="card glass mi">
  <div class="mi-top">
    <h1 style="margin:0">Mock interview</h1>
    <span id="count" class="muted">Question 1 of 10</span>
  </div>
  <div class="mi-bar"><i id="bar"></i></div>

  <div id="chat" class="mi-chat" aria-live="polite">
    <div class="mi-msg mi-bot">Hi <?= htmlspecialchars($name) ?>, I am your interview coach. I will ask 10 common questions. Answer as you would in a real interview.</div>
    <div class="mi-msg mi-bot">Tell me about yourself.</div>
  </div>
  <p id="miErr" class="hidden" style="color:#b00020;font-size:.9rem"></p>

  <form id="form" class="mi-form" onsubmit="return false">
    <textarea id="answer" placeholder="Type your answer here..." required></textarea>
    <button class="btn blue" id="send">Send</button>
  </form>

  <div id="result" class="mi-result hidden"></div>
</div>

<script>
window.addEventListener('error', function (e) {
  var p = document.getElementById('miErr');
  if (p) { p.textContent = 'Script error: ' + e.message + ' (line ' + e.lineno + ')'; p.classList.remove('hidden'); }
});
</script>
<script>
(function () {
'use strict';
const NAME = <?= json_encode($name) ?>;

// Each question has many keywords a good answer may touch on, plus a coaching tip.
// Keywords are matched as word parts, so "communicat" matches communicate, communication, communicating.
const QUESTIONS = [
  { q: "Tell me about yourself.",
    keys: ["experience","education","studied","study","school","college","university","degree","course","skill","background","passion","currently","graduate","worked","work","goal","i am","my name","years","training","intern","volunteer","project","hobby","interest","love","enjoy","hardworking","motivated","looking for","family","from","learn"],
    tip: "Keep it to a short story: who you are now, what you have done, and what you want next." },
  { q: "What are your strengths and weaknesses?",
    keys: ["strength","strong","weakness","improve","learning","learn","working on","example","reliable","adaptable","communicat","team","hardworking","patient","honest","fast learner","detail","organized","responsible","punctual","creative","focus","sometimes","used to","better","practice","overcome","time","confident","shy"],
    tip: "Pick a real strength with proof, and a real weakness plus what you are doing to improve it." },
  { q: "Why should we hire you?",
    keys: ["skill","value","contribute","result","fit","experience","reliable","solve","company","team","hire","hardworking","dedicated","committed","motivated","quick learner","fast learner","willing","eager","responsible","passion","help","benefit","goal","ability","can do","qualified","best","strong","work","learn","positive","attitude"],
    tip: "Match your skills directly to what the job needs and say what you will deliver." },
  { q: "Why do you want to work for this company?",
    keys: ["company","mission","value","growth","opportunity","culture","product","service","reputation","learn","admire","respect","known","great","good","career","team","environment","industry","job","position","role","interest","excited","passion","grow","develop","believe","impressed","community","customer","name","best"],
    tip: "Mention something specific about the company. Generic answers sound rehearsed." },
  { q: "How do you handle workplace challenges?",
    keys: ["problem","solve","solution","calm","communicate","team","priorit","feedback","plan","learned","learn","challenge","difficult","issue","help","ask","talk","discuss","listen","patient","positive","support","teammate","coworker","supervisor","manager","situation","fix","handle","approach","step","stay","focus","think","work together","resolve"],
    tip: "Describe a real situation: the problem, what you did, and the result." },
  { q: "Where do you see yourself in five years?",
    keys: ["grow","develop","leader","skill","career","contribute","long-term","long term","role","experience","goal","position","promotion","manager","senior","expert","better","improve","learn","company","future","years","plan","achieve","succeed","responsible","knowledge","stable","team","lead","advance","progress"],
    tip: "Show ambition that connects to this kind of role and this company." },
  { q: "What skills can you contribute to the company?",
    keys: ["skill","communicate","technical","teamwork","team","leader","organize","problem","software","computer","customer","time management","good at","able to","can","experience","learn","hardworking","detail","creative","analytic","microsoft","excel","english","typing","research","service","sales","writing","fast","flexible","adapt","reliable","responsible","multitask"],
    tip: "Name 2 or 3 skills and give a quick example of each in use." },
  { q: "How do you handle working under pressure?",
    keys: ["priority","calm","deadline","plan","organize","focus","break down","time","stress","manage","pressure","handle","task","list","schedule","steady","relax","breath","stay","work","finish","complete","busy","help","team","step","one at a time","positive","motivate","challenge","urgent","important","tough","cope"],
    tip: "Explain your method (prioritizing, planning) and give an example of a deadline you met." },
  { q: "Why did you leave your previous job, or why are you looking for your first job?",
    keys: ["opportunity","growth","learn","career","experience","new","challenge","skill","ready","graduate","first job","start","begin","looking for","want","grow","develop","better","contract","ended","relocate","move","change","improve","goal","future","passion","interest","school","study","fresh","eager","excited","apply","job","position"],
    tip: "Stay positive. Never blame a past employer. Focus on what you are moving toward.",
    negative: /\b(hate|hated|terrible|worst|toxic|stupid|lazy)\b/i },
  { q: "Do you have any questions for the interviewer?",
    keys: ["no","team","role","expect","culture","training","growth","next step","success","day-to-day","day to day","opportunity","what","how","when","can you","could you","would you","tell me","benefit","schedule","hours","start","position","company","challenge","goal","responsible","work","process","evaluate","performance","career","advance","hear back","timeline"],
    tip: "Always ask something. Good topics: the team, how success is measured, and next steps.",
    asks: true }
];

// Shows the answer has an example, reason, or result (widened so natural answers match easily).
const STRUCTURE = /(for example|for instance|such as|like when|when i|i once|in my|at my|during|result|achiev|improv|learn|because|so that|so i|which helped|helped|succe|complet|finish|managed|made|built|led|worked on|my experience|last year|before)/i;

const chat = document.getElementById('chat');
const form = document.getElementById('form');
const input = document.getElementById('answer');
const sendBtn = document.getElementById('send');
let idx = 0;
const results = [];

function say(text, who) {
  const d = document.createElement('div');
  d.className = 'mi-msg ' + (who === 'me' ? 'mi-me' : 'mi-bot');
  d.textContent = text;
  chat.appendChild(d);
  chat.scrollTop = chat.scrollHeight;
}

function botSays(text, delay = 500) {
  return new Promise(res => {
    sendBtn.disabled = true;
    setTimeout(() => { say(text, 'bot'); sendBtn.disabled = false; input.focus(); res(); }, delay);
  });
}

function updateProgress() {
  document.getElementById('count').textContent = 'Question ' + Math.min(idx + 1, 10) + ' of 10';
  document.getElementById('bar').style.width = (idx / 10 * 100) + '%';
}

// Friendly scoring: an honest, on-topic answer of 2 or 3 sentences should land around 70-90.
function scoreAnswer(item, text) {
  const lower = text.toLowerCase();
  const words = text.trim().split(/\s+/).filter(Boolean).length;
  let score = 0;
  const notes = [];

  // Effort (10 pts): any real attempt earns a base score.
  if (words >= 5) score += 10;

  // Length (40 pts): full marks at just 25 words.
  score += Math.min(words / 25, 1) * 40;
  if (words < 15) notes.push('A little short. Try adding a sentence or two.');
  else if (words > 300) { score -= 3; notes.push('A bit long. Aim for about a minute of speaking.'); }

  // Relevant keywords (35 pts): only 2 matches needed for full marks.
  const hits = item.keys.filter(k => lower.includes(k)).length;
  score += Math.min(hits / 2, 1) * 35;
  if (hits < 2) notes.push('Try to stay closer to the question and add a few more specific points.');

  // Examples and results (15 pts): partial credit for a longer answer even without a clear example.
  if (STRUCTURE.test(text)) score += 15;
  else {
    if (words >= 30) score += 7;
    notes.push('Adding a real example or result would make it stronger.');
  }

  // Question-specific checks (gentle penalties)
  if (item.negative && item.negative.test(text)) { score -= 10; notes.push('Avoid negative comments about past employers.'); }
  if (item.asks && !(/\?/.test(text) || /\b(what|how|when|can you|could you|would you|will there|is there|do you)\b/i.test(text))) {
    score -= 10; notes.push('Ask at least one real question.');
  }

  // Safety net: a sincere answer (at least 20 words) never scores below 50.
  if (words >= 20) score = Math.max(score, 50);

  score = Math.max(0, Math.min(100, Math.round(score)));
  return { score, notes, words };
}

function showResults() {
  form.classList.add('hidden');
  document.getElementById('bar').style.width = '100%';
  document.getElementById('count').textContent = 'Finished';

  const avg = Math.round(results.reduce((s, r) => s + r.score, 0) / results.length);
  const PASS_MARK = 60;
  const passed = avg >= PASS_MARK;
  let verdict, advice;
  if (passed) { verdict = 'Passed: ready to apply'; advice = 'You scored ' + avg + ', above the pass mark of ' + PASS_MARK + '. You can now apply for jobs.'; }
  else { verdict = 'Not passed yet'; advice = 'You scored ' + avg + '. You need at least ' + PASS_MARK + ' to pass. Read the tips below and try again.'; }

  const box = document.getElementById('result');
  box.classList.remove('hidden');
  box.innerHTML = '';

  const h = document.createElement('div');
  h.innerHTML = '<div class="mi-score"></div><h2></h2><p></p>';
  h.querySelector('.mi-score').textContent = avg + ' / 100';
  h.querySelector('h2').textContent = verdict;
  h.querySelector('p').textContent = advice;
  box.appendChild(h);

  results.forEach((r, i) => {
    const row = document.createElement('div');
    row.className = 'mi-item';
    const t = document.createElement('strong');
    t.textContent = (i + 1) + '. ' + QUESTIONS[i].q + ' (' + r.score + '/100)';
    const n = document.createElement('small');
    n.textContent = (r.notes.length ? r.notes.join(' ') + ' ' : 'Good answer. ') + 'Tip: ' + QUESTIONS[i].tip;
    row.appendChild(t); row.appendChild(n);
    box.appendChild(row);
  });

  const actions = document.createElement('p');
  actions.style.marginTop = '16px';
  actions.innerHTML = passed
    ? '<a class="btn blue" href="login.php">Go to jobs</a>'
    : '<a class="btn blue" href="mock_interview.php">Try again</a>';
  box.appendChild(actions);
}

form.addEventListener('submit', async e => {
  e.preventDefault();
  const text = input.value.trim();
  if (!text) return;

  say(text, 'me');
  input.value = '';
  results.push(scoreAnswer(QUESTIONS[idx], text));
  idx++;
  updateProgress();

  if (idx < QUESTIONS.length) {
    await botSays(QUESTIONS[idx].q);
  } else {
    await botSays('That was the last question. Thank you, ' + NAME + '. Let me review your answers...', 700);
    setTimeout(showResults, 900);
  }
});

// Ctrl+Enter sends the answer
input.addEventListener('keydown', e => {
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) form.requestSubmit();
});

(function start() {
  updateProgress();
  input.focus();
})();

})();
</script>
<?php require 'includes/footer.php'; ?>
