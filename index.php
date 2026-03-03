<?php
/**
 * eClass - Landing Page & Transparency Board
 * Public — no login required
 */
require_once __DIR__ . '/config/app.php';



// ── Data ────────────────────────────────────────────────────
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$totalSubjects = $pdo->query("SELECT COUNT(*) FROM subjects WHERE status='active'")->fetchColumn();

$funds = $pdo->query("
    SELECT f.*,
           CASE WHEN f.fund_type = 'general' THEN 0
                WHEN f.subject_id IS NOT NULL
                THEN (SELECT COUNT(*) FROM subject_enrollments se JOIN students st ON se.student_id = st.id WHERE se.subject_id = f.subject_id AND st.status = 'active')
                ELSE (SELECT COUNT(*) FROM fund_assignees WHERE fund_id = f.id)
           END as assignee_count,
           COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp WHERE fp.fund_id = f.id), 0) as collected
    FROM funds f
    WHERE f.status = 'active'
    ORDER BY f.created_at DESC
")->fetchAll();

$grandTarget = 0;
$grandCollected = 0;
foreach ($funds as $f) {
    $fType = $f['fund_type'] ?? 'standard';
    if ($fType === 'standard') {
        $grandTarget += $f['amount'] * $f['assignee_count'];
    }
    $grandCollected += $f['collected'];
}
$grandPercent = $grandTarget > 0 ? round(($grandCollected / $grandTarget) * 100) : 0;

// Obfuscate name for public display: "Justin" → "J*****", "Dela" → "D***", "Cruz" → "C***"
function obfuscateName($fullName) {
    $words = explode(' ', $fullName);
    $masked = [];
    foreach ($words as $word) {
        $len = mb_strlen($word);
        $masked[] = mb_strtoupper(mb_substr($word, 0, 1)) . str_repeat('*', $len - 1);
    }
    return implode(' ', $masked);
}

// Recent payments (last 5)
$recentPayments = $pdo->query("
    SELECT fp.amount_paid, fp.payment_date, fp.student_id as fp_student_id, fp.notes,
           s.first_name, s.last_name,
           f.fund_name
    FROM fund_payments fp
    LEFT JOIN students s ON fp.student_id = s.id
    JOIN funds f ON fp.fund_id = f.id
    ORDER BY fp.created_at DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" 
      :class="{ 'dark': darkMode }" 
      x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneMAWD — Classroom Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        mono: {
                            50: '#fafafa', 100: '#f5f5f5', 200: '#e5e5e5', 300: '#d4d4d4',
                            400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040',
                            800: '#262626', 900: '#171717', 950: '#0a0a0a',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .gradient-blur { background: radial-gradient(ellipse at 50% 0%, rgba(163,163,163,0.08) 0%, transparent 60%); }
        .dark .gradient-blur { background: radial-gradient(ellipse at 50% 0%, rgba(255,255,255,0.03) 0%, transparent 60%); }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-float-delayed { animation: float 6s ease-in-out 3s infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        .hero-grid { background-image: radial-gradient(circle, #d4d4d4 1px, transparent 1px); background-size: 32px 32px; }
        .dark .hero-grid { background-image: radial-gradient(circle, #404040 1px, transparent 1px); }
        .ring-glow { box-shadow: 0 0 0 1px rgba(0,0,0,0.05), 0 20px 50px -12px rgba(0,0,0,0.08); }
        .dark .ring-glow { box-shadow: 0 0 0 1px rgba(255,255,255,0.05), 0 20px 50px -12px rgba(0,0,0,0.4); }
        /* Page loader */
        .page-loader { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: #fafafa; transition: opacity .3s, visibility .3s; }
        .dark .page-loader { background: #0a0a0a; }
        .page-loader.loaded { opacity: 0; visibility: hidden; pointer-events: none; }
        .loader-spinner { width: 22px; height: 22px; border: 2px solid #e5e5e5; border-top-color: #171717; border-radius: 50%; animation: spin .6s linear infinite; }
        .dark .loader-spinner { border-color: #404040; border-top-color: #e5e5e5; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-mono-50 dark:bg-mono-950 text-mono-900 dark:text-mono-100 min-h-screen transition-colors duration-200 overflow-x-hidden">

<!-- Page Loader -->
<div class="page-loader" id="pageLoader"><div class="loader-spinner"></div></div>
<script>window.addEventListener('load',function(){document.getElementById('pageLoader').classList.add('loaded')});</script>

<!-- ═══════════════════════════════════════════════════════════
     NAVBAR
     ═══════════════════════════════════════════════════════════ -->
<nav class="fixed top-0 inset-x-0 z-50 bg-white/70 dark:bg-mono-950/70 backdrop-blur-xl border-b border-mono-200/60 dark:border-mono-800/60">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <a href="#" class="flex items-center gap-3 group">
            <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="OneMAWD" class="w-9 h-9 object-contain group-hover:scale-105 transition-transform">
            <span class="text-base font-bold tracking-tight">OneMAWD</span>
        </a>
        <div class="flex items-center gap-2">
            <a href="#transparency" class="hidden sm:inline-flex items-center px-3 py-1.5 text-xs font-medium text-mono-500 hover:text-mono-900 dark:hover:text-mono-100 rounded-lg hover:bg-mono-100 dark:hover:bg-mono-800 transition-all">
                Transparency Board
            </a>
            <a href="#developer" class="hidden sm:inline-flex items-center px-3 py-1.5 text-xs font-medium text-mono-500 hover:text-mono-900 dark:hover:text-mono-100 rounded-lg hover:bg-mono-100 dark:hover:bg-mono-800 transition-all">
                Developer
            </a>
            <button @click="darkMode = !darkMode" 
                    class="w-9 h-9 rounded-xl flex items-center justify-center text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 transition-all hover:scale-105">
                <i class="fas fa-moon text-sm" x-show="!darkMode"></i>
                <i class="fas fa-sun text-sm" x-show="darkMode" x-cloak></i>
            </button>
            <a href="<?= BASE_URL ?>/auth/login.php" 
               class="ml-1 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-xs font-semibold hover:bg-mono-800 dark:hover:bg-mono-200 transition-all hover:scale-[1.02] active:scale-[0.98]">
                <i class="fas fa-arrow-right-to-bracket text-[10px]"></i>
                Sign In
            </a>
        </div>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════
     HERO SECTION
     ═══════════════════════════════════════════════════════════ -->
<section class="relative pt-32 pb-20 sm:pt-40 sm:pb-28 gradient-blur">
    <div class="absolute inset-0 hero-grid opacity-40"></div>
    
    <!-- Floating shapes -->
    <div class="absolute top-24 left-[10%] w-20 h-20 rounded-full border border-mono-200 dark:border-mono-800 opacity-40 animate-float"></div>
    <div class="absolute top-40 right-[15%] w-12 h-12 rounded-lg border border-mono-200 dark:border-mono-800 opacity-30 animate-float-delayed rotate-12"></div>
    <div class="absolute bottom-10 left-[20%] w-8 h-8 rounded-full bg-mono-200 dark:bg-mono-800 opacity-20 animate-float-delayed"></div>
    
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 text-center">
        <!-- Status badge -->
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-mono-100 dark:bg-mono-800/80 border border-mono-200 dark:border-mono-700 mb-6">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-[11px] font-medium text-mono-500">Classroom Management System</span>
        </div>

        <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black tracking-tight leading-[0.95] mb-6">
            Manage your<br>
            <span class="text-mono-300 dark:text-mono-600">classroom</span> with<br>
            <span class="relative inline-block">
                transparency
                <svg class="absolute -bottom-1 left-0 w-full" viewBox="0 0 300 12" fill="none"><path d="M2 8 Q75 2 150 8 Q225 14 298 4" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="text-mono-300 dark:text-mono-600"/></svg>
            </span>
        </h1>

        <p class="max-w-lg mx-auto text-sm sm:text-base text-mono-400 leading-relaxed mb-10">
            Track attendance, manage class funds, and keep everyone informed.
            Open and accountable — the way it should be.
        </p>

        <!-- CTA buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="#transparency" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-semibold hover:bg-mono-800 dark:hover:bg-mono-200 transition-all hover:scale-[1.02] active:scale-[0.98] ring-glow">
                <i class="fas fa-eye text-xs"></i>
                View Transparency Board
            </a>
            <a href="<?= BASE_URL ?>/auth/login.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-mono-200 dark:border-mono-700 text-sm font-medium hover:bg-mono-100 dark:hover:bg-mono-800 transition-all">
                <i class="fas fa-lock text-xs text-mono-400"></i>
                Officer Login
            </a>
        </div>

        <!-- Quick stats -->
        <div class="flex items-center justify-center gap-6 sm:gap-10 mt-14">
            <div class="text-center">
                <p class="text-2xl sm:text-3xl font-bold"><?= $totalStudents ?></p>
                <p class="text-[10px] sm:text-xs text-mono-400 mt-0.5">Students</p>
            </div>
            <div class="w-px h-8 bg-mono-200 dark:bg-mono-800"></div>
            <div class="text-center">
                <p class="text-2xl sm:text-3xl font-bold"><?= $totalSubjects ?></p>
                <p class="text-[10px] sm:text-xs text-mono-400 mt-0.5">Subjects</p>
            </div>
            <div class="w-px h-8 bg-mono-200 dark:bg-mono-800"></div>
            <div class="text-center">
                <p class="text-2xl sm:text-3xl font-bold"><?= count($funds) ?></p>
                <p class="text-[10px] sm:text-xs text-mono-400 mt-0.5">Active Funds</p>
            </div>
            <div class="w-px h-8 bg-mono-200 dark:bg-mono-800"></div>
            <div class="text-center">
                <p class="text-2xl sm:text-3xl font-bold"><?= formatMoney($grandCollected) ?></p>
                <p class="text-[10px] sm:text-xs text-mono-400 mt-0.5">Collected</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     TRANSPARENCY BOARD
     ═══════════════════════════════════════════════════════════ -->
<section id="transparency" class="scroll-mt-20 py-16 sm:py-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">

        <!-- Section header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-mono-100 dark:bg-mono-800/80 border border-mono-200 dark:border-mono-700 mb-4">
                <i class="fas fa-shield-halved text-[10px] text-mono-500"></i>
                <span class="text-[11px] font-medium text-mono-500">Full Transparency</span>
            </div>
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-2">Class Funds Overview</h2>
            <p class="text-sm text-mono-400 max-w-md mx-auto">Every peso tracked. Every payment recorded. Open for all to see.</p>
        </div>

        <!-- ═══ GRAND TOTAL CARD ═══ -->
        <div class="bg-white dark:bg-mono-900 rounded-2xl border border-mono-200 dark:border-mono-800 ring-glow p-6 sm:p-8 mb-8">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <!-- Left -->
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                            <i class="fas fa-vault text-mono-400"></i>
                        </div>
                        <p class="text-xs font-semibold text-mono-400 uppercase tracking-wider">Total Collected</p>
                    </div>
                    <h3 class="text-4xl sm:text-5xl font-black tracking-tight mb-1"><?= formatMoney($grandCollected) ?></h3>
                    <p class="text-sm text-mono-400">of <?= formatMoney($grandTarget) ?> total target</p>
                </div>

                <!-- Right: donut + mini stats -->
                <div class="flex items-center gap-8">
                    <div class="relative w-28 h-28 flex-shrink-0">
                        <svg viewBox="0 0 36 36" class="w-28 h-28 -rotate-90">
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                  fill="none" stroke-width="2.5" class="stroke-mono-100 dark:stroke-mono-800" />
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                  fill="none" stroke-width="2.5" stroke-linecap="round"
                                  class="<?= $grandPercent >= 100 ? 'stroke-emerald-500' : 'stroke-mono-900 dark:stroke-mono-100' ?>"
                                  stroke-dasharray="<?= $grandPercent ?>, 100" />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold"><?= $grandPercent ?>%</span>
                            <span class="text-[9px] text-mono-400 -mt-0.5">collected</span>
                        </div>
                    </div>
                    <div class="hidden sm:flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
                                <i class="fas fa-arrow-down text-emerald-500 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold"><?= formatMoney($grandCollected) ?></p>
                                <p class="text-[10px] text-mono-400">Received</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
                                <i class="fas fa-clock text-amber-500 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold"><?= formatMoney(max($grandTarget - $grandCollected, 0)) ?></p>
                                <p class="text-[10px] text-mono-400">Remaining</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="mt-6 pt-6 border-t border-mono-100 dark:border-mono-800">
                <div class="flex items-center justify-between text-xs text-mono-400 mb-2">
                    <span>Overall Progress</span>
                    <span class="font-semibold text-mono-600 dark:text-mono-300"><?= formatMoney($grandCollected) ?> / <?= formatMoney($grandTarget) ?></span>
                </div>
                <div class="w-full bg-mono-100 dark:bg-mono-800 rounded-full h-3 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-700 <?= $grandPercent >= 100 ? 'bg-emerald-500' : 'bg-gradient-to-r from-mono-400 to-mono-900 dark:from-mono-500 dark:to-mono-100' ?>" 
                         style="width: <?= min($grandPercent, 100) ?>%"></div>
                </div>
            </div>
        </div>

        <!-- ═══ FUND CARDS ═══ -->
        <?php if (empty($funds)): ?>
        <div class="bg-white dark:bg-mono-900 rounded-2xl border border-mono-200 dark:border-mono-800 px-6 py-20 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-mono-100 dark:bg-mono-800 flex items-center justify-center mb-4">
                <i class="fas fa-wallet text-2xl text-mono-300 dark:text-mono-600"></i>
            </div>
            <p class="text-sm font-medium text-mono-400">No active funds at the moment</p>
            <p class="text-xs text-mono-300 dark:text-mono-600 mt-1">Check back later for updates.</p>
        </div>
        <?php else: ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-8">
            <?php foreach ($funds as $fund): 
                $fType = $fund['fund_type'] ?? 'standard';
                $fIsGeneral = $fType === 'general';
                $fIsVoluntary = $fType === 'voluntary';
                $target = ($fIsGeneral || $fIsVoluntary) ? 0 : $fund['amount'] * $fund['assignee_count'];
                $collected = $fund['collected'];
                $pct = $target > 0 ? round(($collected / $target) * 100) : 0;
                $remaining = max($target - $collected, 0);
                $paidCount = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM fund_payments WHERE fund_id = ?");
                $paidCount->execute([$fund['id']]);
                $paidStudents = $paidCount->fetchColumn();
                $isComplete = !$fIsGeneral && !$fIsVoluntary && $pct >= 100;
            ?>
            <div class="group bg-white dark:bg-mono-900 rounded-2xl border border-mono-200 dark:border-mono-800 p-5 hover:border-mono-300 dark:hover:border-mono-600 transition-all duration-200">
                <!-- Fund header -->
                <div class="flex items-center gap-2.5 mb-4">
                    <div class="w-8 h-8 rounded-lg <?= $isComplete ? 'bg-emerald-50 dark:bg-emerald-500/10' : ($fIsGeneral ? 'bg-blue-50 dark:bg-blue-500/10' : ($fIsVoluntary ? 'bg-purple-50 dark:bg-purple-500/10' : 'bg-mono-100 dark:bg-mono-800')) ?> flex items-center justify-center flex-shrink-0">
                        <i class="fas <?= $isComplete ? 'fa-check text-emerald-500' : ($fIsGeneral ? 'fa-piggy-bank text-blue-500' : ($fIsVoluntary ? 'fa-hand-holding-heart text-purple-500' : 'fa-coins text-mono-400')) ?> text-xs"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <h3 class="text-sm font-semibold truncate"><?= sanitize($fund['fund_name']) ?></h3>
                            <?php if ($fIsGeneral): ?>
                            <span class="px-1.5 py-0.5 text-[8px] font-semibold rounded bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 uppercase tracking-wider">General</span>
                            <?php elseif ($fIsVoluntary): ?>
                            <span class="px-1.5 py-0.5 text-[8px] font-semibold rounded bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 uppercase tracking-wider">Voluntary</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if ($fIsGeneral): ?>
                            <span class="text-[10px] text-mono-400">Open fund</span>
                            <?php elseif ($fIsVoluntary): ?>
                            <span class="text-[10px] text-mono-400">Voluntary contributions</span>
                            <?php else: ?>
                            <span class="text-[10px] text-mono-400"><?= formatMoney($fund['amount']) ?>/person</span>
                            <?php endif; ?>
                            <?php if ($fund['frequency'] !== 'one-time'): ?>
                            <span class="px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider rounded-full bg-mono-100 dark:bg-mono-800 text-mono-500"><?= $fund['frequency'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Amount -->
                <div class="mb-4">
                    <span class="text-xl font-bold"><?= formatMoney($collected) ?></span>
                    <?php if (!$fIsGeneral && !$fIsVoluntary): ?>
                    <span class="text-xs text-mono-400">/ <?= formatMoney($target) ?></span>
                    <?php else: ?>
                    <span class="text-xs text-mono-400">collected</span>
                    <?php endif; ?>
                </div>

                <?php if ($fIsGeneral || $fIsVoluntary): ?>
                <!-- No progress bar for general/voluntary -->
                <div class="w-full bg-mono-100 dark:bg-mono-800 rounded-full h-2 mb-4 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 bg-mono-900 dark:bg-mono-100" style="width: 100%"></div>
                </div>
                <?php else: ?>
                <!-- Progress bar -->
                <div class="w-full bg-mono-100 dark:bg-mono-800 rounded-full h-2 mb-4 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 <?= $isComplete ? 'bg-emerald-500' : 'bg-mono-900 dark:bg-mono-100' ?>" style="width: <?= min($pct, 100) ?>%"></div>
                </div>
                <?php endif; ?>

                <!-- Bottom row -->
                <div class="flex items-center justify-between">
                    <?php if ($fIsGeneral): ?>
                    <span class="text-[11px] text-mono-400">
                        <i class="fas fa-piggy-bank mr-1 text-[9px]"></i><?= count($recentPayments) > 0 ? $paidStudents . ' deposit(s)' : 'No deposits yet' ?>
                    </span>
                    <span class="text-[11px] font-semibold text-blue-500">Open</span>
                    <?php elseif ($fIsVoluntary): ?>
                    <span class="text-[11px] text-mono-400">
                        <i class="fas fa-user-check mr-1 text-[9px]"></i><?= $paidStudents ?> contributed
                    </span>
                    <span class="text-[11px] font-semibold text-purple-500">Voluntary</span>
                    <?php else: ?>
                    <span class="text-[11px] text-mono-400">
                        <i class="fas fa-user-check mr-1 text-[9px]"></i><?= $paidStudents ?>/<?= $fund['assignee_count'] ?> paid
                    </span>
                    <span class="text-[11px] font-semibold <?= $isComplete ? 'text-emerald-500' : 'text-mono-500' ?>">
                        <?= $isComplete ? 'Complete ✓' : $pct . '%' ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($fund['due_date']): 
                    $daysLeft = (int)((strtotime($fund['due_date']) - time()) / 86400);
                ?>
                <div class="mt-3 pt-3 border-t border-mono-100 dark:border-mono-800 flex items-center justify-between">
                    <span class="text-[10px] text-mono-400">
                        <i class="far fa-calendar mr-1"></i><?= formatDate($fund['due_date']) ?>
                    </span>
                    <?php if ($daysLeft < 0): ?>
                        <span class="text-[10px] font-semibold text-red-500 bg-red-50 dark:bg-red-500/10 px-2 py-0.5 rounded-full"><?= abs($daysLeft) ?>d overdue</span>
                    <?php elseif ($daysLeft <= 7): ?>
                        <span class="text-[10px] font-semibold text-amber-600 bg-amber-50 dark:bg-amber-500/10 px-2 py-0.5 rounded-full"><?= $daysLeft ?>d left</span>
                    <?php else: ?>
                        <span class="text-[10px] text-mono-400"><?= $daysLeft ?>d left</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ═══ RECENT PAYMENTS ═══ -->
        <?php if (!empty($recentPayments)): ?>
        <div class="bg-white dark:bg-mono-900 rounded-2xl border border-mono-200 dark:border-mono-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-mono-100 dark:border-mono-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-stream text-mono-400 text-xs"></i>
                    <h3 class="text-sm font-semibold">Recent Payments</h3>
                </div>
                <span class="text-[10px] text-mono-400 font-medium uppercase tracking-wider">Latest Activity</span>
            </div>
            <div class="divide-y divide-mono-100 dark:divide-mono-800">
                <?php foreach ($recentPayments as $pay): ?>
                <div class="px-6 py-3.5 flex items-center justify-between hover:bg-mono-50 dark:hover:bg-mono-800/50 transition-colors">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-full <?= $pay['fp_student_id'] ? 'bg-mono-100 dark:bg-mono-800' : 'bg-amber-100 dark:bg-amber-900/20' ?> flex items-center justify-center flex-shrink-0">
                            <?php if ($pay['fp_student_id']): ?>
                            <span class="text-[10px] font-bold text-mono-400">**</span>
                            <?php else: ?>
                            <i class="fas fa-piggy-bank text-[10px] text-amber-500"></i>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <?php if ($pay['fp_student_id']): ?>
                            <p class="text-xs font-medium truncate"><?= sanitize(obfuscateName(ucwords($pay['first_name']) . ' ' . ucwords($pay['last_name']))) ?></p>
                            <?php else: ?>
                            <p class="text-xs font-medium truncate text-amber-600 dark:text-amber-400">Deposit<?= $pay['notes'] ? ' — ' . sanitize($pay['notes']) : '' ?></p>
                            <?php endif; ?>
                            <p class="text-[10px] text-mono-400 truncate"><?= sanitize($pay['fund_name']) ?></p>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400">+<?= formatMoney($pay['amount_paid']) ?></p>
                        <p class="text-[10px] text-mono-400"><?= formatDate($pay['payment_date'], 'M d') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timestamp -->
        <div class="mt-8 text-center">
            <p class="text-[11px] text-mono-300 dark:text-mono-600">
                <i class="fas fa-sync-alt mr-1"></i>
                Data as of <?= date('F d, Y — g:i A') ?>
                <span class="mx-2">·</span>
                <a href="" class="hover:text-mono-500 transition-colors underline underline-offset-2">Refresh</a>
            </p>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     FEATURES
     ═══════════════════════════════════════════════════════════ -->
<section class="py-16 sm:py-20 border-t border-mono-100 dark:border-mono-900">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-12">
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight mb-2">Everything you need</h2>
            <p class="text-sm text-mono-400">Built for class officers, open for everyone.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php
            $features = [
                ['fas fa-clipboard-check', 'Attendance', 'Track daily attendance per subject with detailed reports.'],
                ['fas fa-wallet', 'Fund Management', 'Collect, track, and report every fund transparently.'],
                ['fas fa-users', 'Student Records', 'Manage student profiles, sections, and enrollment.'],
                ['fas fa-chart-bar', 'Reports', 'Generate PDF reports for attendance and fund summaries.'],
            ];
            foreach ($features as [$icon, $title, $desc]):
            ?>
            <div class="bg-white dark:bg-mono-900 rounded-2xl border border-mono-200 dark:border-mono-800 p-5 hover:border-mono-300 dark:hover:border-mono-700 transition-all group">
                <div class="w-10 h-10 rounded-xl bg-mono-100 dark:bg-mono-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="<?= $icon ?> text-sm text-mono-400"></i>
                </div>
                <h3 class="text-sm font-semibold mb-1"><?= $title ?></h3>
                <p class="text-xs text-mono-400 leading-relaxed"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     ABOUT THE DEVELOPER
     ═══════════════════════════════════════════════════════════ -->
<section id="developer" class="py-16 sm:py-20 border-t border-mono-100 dark:border-mono-900">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-mono-100 dark:bg-mono-800/80 border border-mono-200 dark:border-mono-700 mb-4">
                <i class="fas fa-code text-[10px] text-mono-500"></i>
                <span class="text-[11px] font-medium text-mono-500">Behind the Project</span>
            </div>
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-2">About the Developer</h2>
            <p class="text-sm text-mono-400 max-w-md mx-auto">The team behind OneMAWD Classroom Management System.</p>
        </div>

        <div class="max-w-3xl mx-auto">
            <div class="bg-white dark:bg-mono-900 rounded-2xl border border-mono-200 dark:border-mono-800 p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                    <!-- Avatar -->
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 rounded-2xl flex items-center justify-center">
                            <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="OneMAWD" class="w-16 h-16 object-contain">
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="text-center sm:text-left flex-1">
                        <h3 class="text-lg font-bold mb-1">Jan Andrei</h3>
                        <p class="text-xs font-medium text-mono-400 mb-4">Developer — STI College San Fernando / University of the Assumption</p>
                        <p class="text-sm text-mono-500 dark:text-mono-400 leading-relaxed mb-5">
                            OneMAWD is a classroom management system developed by <strong class="text-mono-700 dark:text-mono-300">Jan Andrei</strong>, 
                            a student of <strong class="text-mono-700 dark:text-mono-300"></strong> 
                            at STI College and now Student of University of the Assumption. Designed to streamline attendance tracking, fund collection transparency, 
                            and student record management — making classroom administration efficient, accountable, and accessible to everyone.
                        </p>

                        <!-- Tech stack -->
                        <div class="mb-5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-mono-400 mb-2">Built With</p>
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                <?php
                                $techs = ['PHP', 'MySQL', 'Tailwind CSS', 'Alpine.js', 'jsPDF'];
                                foreach ($techs as $tech):
                                ?>
                                <span class="px-2.5 py-1 text-[10px] font-semibold rounded-lg bg-mono-100 dark:bg-mono-800 text-mono-500"><?= $tech ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Project info -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div class="bg-mono-50 dark:bg-mono-800/50 rounded-xl px-3 py-2.5 text-center">
                                <p class="text-sm font-bold">v<?= APP_VERSION ?> - UA Version</p>
                                <p class="text-[10px] text-mono-400">Version</p>
                            </div>
                            <div class="bg-mono-50 dark:bg-mono-800/50 rounded-xl px-3 py-2.5 text-center">
                                <p class="text-sm font-bold"><?= date('Y') ?></p>
                                <p class="text-[10px] text-mono-400">Year</p>
                            </div>
                            <div class="bg-mono-50 dark:bg-mono-800/50 rounded-xl px-3 py-2.5 text-center col-span-2 sm:col-span-1">
                                <p class="text-sm font-bold">STI / UA</p>
                                <p class="text-[10px] text-mono-400">University</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════════════════════ -->
<footer class="border-t border-mono-200 dark:border-mono-800">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="OneMAWD" class="w-7 h-7 object-contain">
            <div>
                <p class="text-xs font-semibold">OneMAWD</p>
                <p class="text-[10px] text-mono-400">&copy; <?= date('Y') ?> Classroom Management System</p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/auth/login.php" class="text-xs text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 transition-colors">
            Officer / Admin Login <i class="fas fa-arrow-right ml-1 text-[10px]"></i>
        </a>
    </div>
</footer>

<!-- Smooth scrolling -->
<script>
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        document.querySelector(a.getAttribute('href'))?.scrollIntoView({ behavior: 'smooth' });
    });
});
</script>
</body>
</html>
