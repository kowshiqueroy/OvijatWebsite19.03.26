<?php
require_once __DIR__ . '/../../includes/config/database.php';

$jobId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$favicon = getSetting('company_favicon', '');

// Handle Application Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            throw new Exception("Security token mismatch. Please refresh.");
        }

        $circularId = (int)$_POST['circular_id'];
        $name = sanitize($_POST['applicant_name']);
        $email = sanitize($_POST['applicant_email']);
        $phone = sanitize($_POST['applicant_phone']);
        $coverLetter = sanitize($_POST['cover_letter']);
        
        db()->insert('job_applications', [
            'circular_id' => $circularId,
            'applicant_name' => $name,
            'applicant_email' => $email,
            'applicant_phone' => $phone,
            'resume_path' => null,
            'cover_letter' => $coverLetter,
            'status' => 'pending'
        ]);
        
        $_SESSION['success_message'] = "Application submitted successfully! We will contact you soon.";
        header("Location: ?page=careers&id=$jobId&success=1");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$success = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

// Fetch Data
if ($jobId) {
    $job = db()->selectOne("SELECT * FROM job_circulars WHERE id = ? AND status = 'published'", [$jobId]);
    if (!$job) {
        header('Location: ?page=careers');
        exit;
    }
    $pageTitle = $job['job_title'] . ' | Careers';
} else {
    $jobs = db()->select("SELECT * FROM job_circulars WHERE status = 'published' ORDER BY created_at DESC");
    $pageTitle = 'Careers at SohojWeb';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?php if($favicon): ?><link rel="icon" type="image/*" href="<?= escape($favicon) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: {'50': '#eff6ff', '500': '#3b82f6', '600': '#2563eb'} },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-text { background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="font-sans text-gray-800 bg-slate-50">
    <?php include __DIR__ . '/../../public/header.php'; ?>

<div class="pt-32 pb-16 min-h-screen">
    <div class="max-w-7xl mx-auto px-4">
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline"><?= $success ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?= $error ?></span>
            </div>
        <?php endif; ?>

        <?php if ($jobId && $job): ?>
            <!-- Job Details View -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-aos="fade-up">
                <div class="p-8 border-b border-slate-100 bg-slate-50/50">
                    <a href="?page=careers" class="text-blue-600 hover:underline mb-4 inline-block text-sm font-medium"><i class="fas fa-arrow-left mr-2"></i>Back to Careers</a>
                    <h1 class="text-3xl font-bold text-slate-900 mb-2"><?= escape($job['job_title']) ?></h1>
                    <div class="flex flex-wrap gap-4 text-slate-600 text-sm">
                        <span class="flex items-center gap-2"><i class="fas fa-map-marker-alt text-blue-500"></i><?= escape($job['location']) ?></span>
                        <span class="flex items-center gap-2"><i class="fas fa-clock text-blue-500"></i><?= escape($job['employment_type']) ?></span>
                        <span class="flex items-center gap-2"><i class="fas fa-money-bill-wave text-blue-500"></i><?= escape($job['salary_range']) ?></span>
                        <span class="flex items-center gap-2"><i class="fas fa-calendar-alt text-blue-500"></i>Deadline: <?= date('M d, Y', strtotime($job['apply_deadline'])) ?></span>
                    </div>
                </div>
                
                <div class="grid lg:grid-cols-3 gap-8 p-8">
                    <div class="lg:col-span-2 space-y-8">
                        <div class="prose max-w-none">
                            <h3 class="text-lg font-bold text-slate-900 mb-3 border-l-4 border-blue-500 pl-3">Job Description</h3>
                            <div class="text-slate-600 leading-relaxed">
                                <?= nl2br(escape($job['job_description'])) ?>
                            </div>
                        </div>
                        
                        <?php if ($job['responsibilities']): ?>
                        <div class="prose max-w-none">
                            <h3 class="text-lg font-bold text-slate-900 mb-3 border-l-4 border-blue-500 pl-3">Key Responsibilities</h3>
                            <div class="text-slate-600 leading-relaxed">
                                <?= nl2br(escape($job['responsibilities'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($job['requirements']): ?>
                        <div class="prose max-w-none">
                            <h3 class="text-lg font-bold text-slate-900 mb-3 border-l-4 border-blue-500 pl-3">Requirements</h3>
                            <div class="text-slate-600 leading-relaxed">
                                <?= nl2br(escape($job['requirements'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="lg:col-span-1">
                        <div class="bg-slate-50 rounded-2xl p-6 sticky top-32 border border-slate-200">
                            <h3 class="text-xl font-bold text-slate-900 mb-4">Apply for this position</h3>
                            <form action="?page=careers&id=<?= $jobId ?>" method="POST" class="space-y-4" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerText='Submitting...';">
                                <input type="hidden" name="action" value="apply">
                                <input type="hidden" name="circular_id" value="<?= $jobId ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                                    <input type="text" name="applicant_name" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                                    <input type="email" name="applicant_email" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                                    <input type="text" name="applicant_phone" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Cover Letter / Portfolio Links</label>
                                    <textarea name="cover_letter" rows="6" placeholder="Tell us about yourself or provide links to your work..." required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"></textarea>
                                </div>
                                
                                <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold rounded-xl hover:shadow-lg hover:shadow-blue-500/30 transition-all transform hover:scale-[1.02]">
                                    Submit Application
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Job List View -->
            <div class="text-center mb-16" data-aos="fade-up">
                <h1 class="text-4xl font-bold text-slate-900 mb-4">Join Our <span class="gradient-text">Team</span></h1>
                <p class="text-lg text-slate-600 max-w-2xl mx-auto">Build the future with us. We're always looking for talented individuals to join our growing team.</p>
            </div>
            
            <?php if (empty($jobs)): ?>
                <div class="text-center py-20 bg-white rounded-3xl border-2 border-dashed border-slate-200" data-aos="zoom-in">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-briefcase text-slate-300 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">No Openings Currently</h3>
                    <p class="text-slate-500">Please check back later for new opportunities.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-6">
                    <?php foreach ($jobs as $job): ?>
                    <a href="?page=careers&id=<?= $job['id'] ?>" class="block group" data-aos="fade-up">
                        <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 hover:shadow-xl hover:border-blue-500 transition-all duration-300 transform group-hover:-translate-y-1">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                                <div class="flex-1">
                                    <h3 class="text-2xl font-bold text-slate-900 group-hover:text-blue-600 transition-colors mb-3"><?= escape($job['job_title']) ?></h3>
                                    <div class="flex flex-wrap gap-6 text-slate-500 text-sm font-medium">
                                        <span class="flex items-center gap-2"><i class="fas fa-map-marker-alt text-blue-400"></i> <?= escape($job['location']) ?></span>
                                        <span class="flex items-center gap-2"><i class="fas fa-clock text-blue-400"></i> <?= escape($job['employment_type']) ?></span>
                                        <span class="flex items-center gap-2"><i class="fas fa-money-bill-wave text-blue-400"></i> <?= escape($job['salary_range']) ?></span>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <span class="inline-flex items-center gap-2 px-6 py-3 bg-blue-50 text-blue-600 font-bold rounded-2xl group-hover:bg-blue-600 group-hover:text-white transition-all shadow-sm">
                                        Apply Now <i class="fas fa-arrow-right text-xs"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../public/footer.php'; ?>
<script>AOS.init({ duration: 800, once: true });</script>
</body>
</html>
