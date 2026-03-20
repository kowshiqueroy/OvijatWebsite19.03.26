<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Knowledge Base';
$activePage = 'faqs';
$aid        = agentId();

// ── Handle save ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId   = (int)($_POST['faq_id'] ?? 0);
    $question = $conn->real_escape_string(trim($_POST['question'] ?? ''));
    $answer   = $conn->real_escape_string(trim($_POST['answer'] ?? ''));
    $category = $conn->real_escape_string(trim($_POST['category'] ?? ''));
    $keywords = $conn->real_escape_string(trim($_POST['keywords'] ?? ''));

    if ($question && $answer) {
        if ($editId) {
            $old = $conn->query("SELECT * FROM faqs WHERE id=$editId LIMIT 1")->fetch_assoc();
            $conn->query("UPDATE faqs SET question='$question',answer='$answer',category='$category',keywords='$keywords',updated_by=$aid WHERE id=$editId");
            foreach (['question','answer','category'] as $f) {
                if ($old[$f] !== $$f) logEdit('faqs', $editId, $f, $old[$f], $$f ?? '');
            }
            logActivity('faq_updated', 'faqs', $editId, "FAQ updated: $question");
        } else {
            $conn->query("INSERT INTO faqs (question,answer,category,keywords,created_by) VALUES ('$question','$answer','$category','$keywords',$aid)");
            $faqId = $conn->insert_id;
            logActivity('faq_created', 'faqs', $faqId, "FAQ created: $question");
        }
    }
    header('Location: faqs.php?saved=1');
    exit;
}

$search   = trim($_GET['q'] ?? '');
$category = trim($_GET['cat'] ?? '');

$where = ['1=1'];
if ($search)   $where[] = "(question LIKE '%" . $conn->real_escape_string($search) . "%' OR answer LIKE '%" . $conn->real_escape_string($search) . "%' OR keywords LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($category) $where[] = "category='" . $conn->real_escape_string($category) . "'";

$faqs = $conn->query("SELECT f.*, a.full_name AS creator_name FROM faqs f LEFT JOIN agents a ON a.id=f.created_by WHERE " . implode(' AND ',$where) . " ORDER BY f.usage_count DESC, f.created_at DESC");

$categories = $conn->query("SELECT DISTINCT category FROM faqs WHERE category != '' ORDER BY category");

require_once 'includes/layout.php';
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success py-2 mb-3"><i class="fas fa-check-circle me-2"></i>Saved.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="text" name="q" class="form-control form-control-sm" style="width:220px"
               placeholder="Search FAQ…" value="<?= e($search) ?>">
        <select name="cat" class="form-select form-select-sm" style="width:auto">
            <option value="">All Categories</option>
            <?php while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= e($c['category']) ?>" <?= $category===$c['category']?'selected':'' ?>><?= e($c['category']) ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        <a href="faqs.php" class="btn btn-outline-secondary btn-sm">Reset</a>
    </form>
    <button class="btn btn-primary btn-sm" onclick="openFaqForm()">
        <i class="fas fa-plus me-1"></i>New FAQ
    </button>
</div>

<div class="row g-3">
<?php while ($f = $faqs->fetch_assoc()): ?>
<div class="col-12">
    <div class="cc-card faq-card">
        <div class="cc-card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1">
                    <div class="faq-question-main" onclick="this.nextElementSibling.classList.toggle('d-none')" style="cursor:pointer">
                        <i class="fas fa-circle-question text-info me-2"></i>
                        <strong><?= e($f['question']) ?></strong>
                        <i class="fas fa-chevron-down ms-2 text-muted small"></i>
                    </div>
                    <div class="faq-answer-main d-none mt-2">
                        <div class="faq-answer-text"><?= nl2br(e($f['answer'])) ?></div>
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyFaq(<?= $f['id'] ?>)">
                                <i class="fas fa-copy me-1"></i>Copy Answer
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="editFaq(<?= j($f) ?>)">
                                <i class="fas fa-pen me-1"></i>Edit
                            </button>
                        </div>
                    </div>
                </div>
                <div class="text-end text-nowrap">
                    <?php if ($f['category']): ?>
                    <span class="badge bg-secondary mb-1"><?= e($f['category']) ?></span><br>
                    <?php endif; ?>
                    <span class="text-muted small">Used <?= $f['usage_count'] ?>x</span><br>
                    <span class="text-muted" style="font-size:.7rem">By: <?= e($f['creator_name'] ?: '—') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endwhile; ?>
</div>

<!-- FAQ Form Modal -->
<div class="modal fade" id="faqModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-circle-question me-2"></i><span id="faqModalTitle">New FAQ</span></h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="faq_id" id="faqId" value="0">
                    <div class="mb-2">
                        <label class="form-label">Question <span class="text-danger">*</span></label>
                        <input type="text" name="question" id="faqQuestion" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Answer <span class="text-danger">*</span></label>
                        <textarea name="answer" id="faqAnswer" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="faqCategory" class="form-control"
                                   placeholder="Billing, Support, Process…">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Keywords (comma separated)</label>
                            <input type="text" name="keywords" id="faqKeywords" class="form-control"
                                   placeholder="refund, payment, balance…">
                        </div>
                    </div>
                    <div class="text-muted small mt-2">Saved by: <strong><?= e(currentAgent()['full_name']) ?></strong></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const APP_URL = '<?= APP_URL ?>';
function openFaqForm() {
    document.getElementById('faqModalTitle').textContent = 'New FAQ';
    document.getElementById('faqId').value = '0';
    ['faqQuestion','faqAnswer','faqCategory','faqKeywords'].forEach(f=>document.getElementById(f).value='');
    new bootstrap.Modal(document.getElementById('faqModal')).show();
    setTimeout(()=>document.getElementById('faqQuestion').focus(),300);
}
function editFaq(f) {
    document.getElementById('faqModalTitle').textContent = 'Edit FAQ';
    document.getElementById('faqId').value       = f.id;
    document.getElementById('faqQuestion').value  = f.question;
    document.getElementById('faqAnswer').value    = f.answer;
    document.getElementById('faqCategory').value  = f.category || '';
    document.getElementById('faqKeywords').value  = f.keywords || '';
    new bootstrap.Modal(document.getElementById('faqModal')).show();
}
function copyFaq(id) {
    const card = event.target.closest('.faq-card');
    const q    = card.querySelector('.faq-question-main strong').textContent;
    const a    = card.querySelector('.faq-answer-text').textContent;
    navigator.clipboard.writeText(q + '\n\n' + a).then(()=>showToast('Copied to clipboard','success'));
    // Increment usage counter
    fetch(APP_URL+'/api/search.php?type=faq&q='+encodeURIComponent(q.slice(0,20)));
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once 'includes/footer.php'; ?>
