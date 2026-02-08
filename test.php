<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/logger.php';
require_once 'test-pagination.php';

requireLogin();

$tokenId = $_SESSION['token_id'];
$tokenCode = $_SESSION['token_code'];

// Cek status token
$tokenData = checkTokenAvailability($tokenCode);
if (!$tokenData) {
    logout();
    header("Location: index.php");
    exit;
}

// Jika test sudah selesai, redirect ke hasil
if (!empty($tokenData['test_completed_at'])) {
    header("Location: result.php");
    exit;
}

// Update waktu mulai jika belum ada
if (empty($tokenData['test_started_at'])) {
    updateTestStartTime($tokenId);
}

// Cek sisa waktu
$remainingTime = getRemainingTime($tokenId);
if ($remainingTime <= 0) {
    calculateAndSaveResult($tokenId);
    header("Location: result.php");
    exit;
}

// Get selected test types
$db = getDBConnection();
$tokenStmt = $db->prepare("SELECT selected_test_types FROM tokens WHERE id = ?");
$tokenStmt->execute([$tokenId]);
$tokenData = $tokenStmt->fetch();
$selectedTests = $tokenData['selected_test_types'] ?? 'HSCL-25';
$testTypes = array_map('trim', explode(',', $selectedTests));

// Get all questions
$placeholders = implode(',', array_fill(0, count($testTypes), '?'));
$questionsStmt = $db->prepare("SELECT * FROM questions WHERE is_active = 1 AND test_type IN ($placeholders) ORDER BY test_type, category, id");
$questionsStmt->execute($testTypes);
$questions = $questionsStmt->fetchAll();

// Initialize pagination
$pagination = new TestPagination($tokenId, $testTypes, $questions);
$userAnswers = getUserAnswers($tokenId);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'save_answer') {
        $questionId = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $selectedOption = isset($_POST['selected_option']) ? strtolower($_POST['selected_option']) : '';
        
        if ($questionId > 0 && !empty($selectedOption)) {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT point_a, point_b, point_c, point_d FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $question = $stmt->fetch();
            
            if ($question) {
                $pointColumn = 'point_' . $selectedOption;
                $points = isset($question[$pointColumn]) ? $question[$pointColumn] : 0;
                saveAnswer($tokenId, $questionId, $selectedOption, $points);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Soal tidak ditemukan']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Parameter tidak valid']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'navigate_page') {
        $direction = isset($_POST['direction']) ? $_POST['direction'] : 'next';
        $pagination->$direction();
        echo json_encode(['success' => true, 'page' => $pagination->getCurrentPage()]);
        exit;
    }
    
    if ($_POST['action'] === 'submit_test') {
        if (ob_get_length()) {
            ob_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $db = getDBConnection();
            $tokenStmt = $db->prepare("SELECT selected_test_types FROM tokens WHERE id = ?");
            $tokenStmt->execute([$tokenId]);
            $tokenData = $tokenStmt->fetch();
            
            if (!$tokenData) {
                throw new Exception("Token not found");
            }
            
            $selectedTests = $tokenData['selected_test_types'] ?? 'HSCL-25';
            $testTypes = array_filter(array_map('trim', explode(',', $selectedTests)));
            
            if (empty($testTypes)) {
                throw new Exception("No test types found for this token");
            }
            
            $placeholders = implode(',', array_fill(0, count($testTypes), '?'));
            $totalQuestionsStmt = $db->prepare("SELECT COUNT(*) as count FROM questions WHERE is_active = 1 AND test_type IN ($placeholders)");
            $totalQuestionsStmt->execute($testTypes);
            $totalQuestionsResult = $totalQuestionsStmt->fetch();
            $totalQuestions = $totalQuestionsResult['count'] ?? 0;
            
            if ($totalQuestions === 0) {
                throw new Exception("No active questions found for test types: " . implode(', ', $testTypes));
            }
            
            $answeredStmt = $db->prepare("SELECT COUNT(DISTINCT question_id) as count FROM user_answers WHERE token_id = ?");
            $answeredStmt->execute([$tokenId]);
            $answeredResult = $answeredStmt->fetch();
            $answeredQuestions = $answeredResult['count'] ?? 0;
            
            if ($answeredQuestions < $totalQuestions) {
                $unansweredStmt = $db->prepare(
                    "SELECT id FROM questions WHERE id NOT IN (SELECT question_id FROM user_answers WHERE token_id = ?) AND is_active = 1 AND test_type IN ($placeholders) LIMIT 1"
                );
                $unansweredParams = array_merge([$tokenId], $testTypes);
                $unansweredStmt->execute($unansweredParams);
                $unanswered = $unansweredStmt->fetch();
                $firstUnansweredId = $unanswered ? $unanswered['id'] : 1;
                echo json_encode([
                    'success' => false,
                    'incomplete' => true,
                    'message' => "Anda belum menjawab semua pertanyaan. Answered: $answeredQuestions, Total: $totalQuestions",
                    'question_id' => $firstUnansweredId
                ]);
                exit;
            }
            
            $result = calculateAndSaveResult($tokenId);
            echo json_encode(['success' => true, 'redirect' => 'result.php']);
            exit;
        } catch (Throwable $e) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false, 
                'error' => $e->getMessage(),
                'debug' => [
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            ]);
            exit;
        }
    }
    
    if ($_POST['action'] === 'get_time') {
        echo json_encode(['remaining' => getRemainingTime($tokenId)]);
        exit;
    }
}

// Get current page data for display
$currentPageData = $pagination->getCurrentPageData();
?>
<!DOCTYPE html>>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php 
        $testDisplay = '';
        if (count($testTypes) === 1) {
            $testDisplay = $testTypes[0];
        } else {
            $testDisplay = implode(' + ', $testTypes);
        }
        echo $testDisplay . ' Assessment - ' . htmlspecialchars($_SESSION['user_name']);
        ?>
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hscl-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        
        .hscl-table th, .hscl-table td {
            border: 1px solid #e0e0e0;
            padding: 12px;
            text-align: left;
            font-size: 13px;
        }
        
        .hscl-table th {
            background: #f5f7ff;
            font-weight: 600;
            color: #333;
        }
        
        .hscl-table td {
            background: white;
        }
        
        .hscl-item {
            vertical-align: top;
            width: 40%;
        }
        
        .hscl-option {
            width: 15%;
            text-align: center;
        }
        
        .hscl-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .hscl-category-header {
            background: #667eea;
            color: white;
            font-weight: 700;
            text-align: center;
        }
        
        .test-intro {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .intro-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 12px;
        }
        
        .intro-text {
            color: #666;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        
        .intro-text strong {
            color: #333;
        }
        
        .scoring-info {
            background: #f5f7ff;
            border-left: 4px solid #667eea;
            padding: 12px;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <span class="brand-icon">◆</span>
                <span class="brand-text">ABLE.ID</span>
            </div>
            <div class="navbar-title">
                <?php 
                $testDisplay = '';
                if (count($testTypes) === 1) {
                    $testDisplay = $testTypes[0];
                } else {
                    $testDisplay = implode(' + ', $testTypes);
                }
                echo "📝 Assessment: " . $testDisplay;
                ?>
            </div>
        </div>
    </div>

    <div class="test-container">
        <div class="test-header">
            <div class="test-info">
                <h2>📋 HSCL-25 Screening</h2>
                <p>Peserta: <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></p>
            </div>
        </div>

        <!-- Introduction Section -->
        <div class="test-intro">
            <div class="intro-title">📋 Petunjuk Pengisian</div>
            <div class="intro-text">
                Di bawah ini terdapat beberapa pertanyaan yang dirancang untuk mengevaluasi kesehatan mental dan gaya belajar Anda. Silakan baca satu-persatu secara berhati-hati dan jawab semua pertanyaan dengan jujur.
            </div>
            <div class="intro-text">
                Berilah tanda dengan <strong>memilih opsi</strong> yang paling sesuai dengan kondisi atau preferensi Anda.
            </div>
            <div class="scoring-info">
                <strong>📊 Informasi Penilaian:</strong><br>
                <?php if (in_array('HSCL-25', $testTypes)): ?>
                    <strong>HSCL-25 (Mental Health Screening):</strong><br>
                    • Cut-off point: 1.75 (Turnip & Hauff, 2007)<br>
                    • Skor ≥ 1.75 menunjukkan kemungkinan gangguan kesehatan mental<br>
                    • Items 1-10: Indikator Anxiety (Kecemasan)<br>
                    • Items 11-25: Indikator Depresi<br><br>
                <?php endif; ?>
                <?php if (in_array('VAK', $testTypes)): ?>
                    <strong>VAK (Visual, Auditory, Kinesthetic):</strong><br>
                    • Ujian untuk mengidentifikasi gaya belajar preferensi Anda<br>
                    • Visual: Belajar melalui melihat/visual aids<br>
                    • Auditory: Belajar melalui mendengar/diskusi<br>
                    • Kinesthetic: Belajar melalui praktik langsung<br>
                <?php endif; ?>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-label">
                <span class="label">Halaman <?= $pagination->getCurrentPage() ?> dari <?= $pagination->getTotalPages() ?></span>
                <span class="percentage" id="percentage"><?= $pagination->getProgressPercentage() ?>%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="progress" style="width: <?= $pagination->getProgressPercentage() ?>%"></div>
            </div>
        </div>

        <!-- Questions Section -->
        <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 20px; overflow-x: auto;">
            <div style="display: flex; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #f0f0f0;">
                <span style="font-size: 32px; margin-right: 12px;"><?= $currentPageData['icon'] ?></span>
                <div>
                    <h3 style="margin: 0 0 6px 0; color: #333; font-size: 18px; font-weight: 700;">
                        <?= $currentPageData['title'] ?>
                    </h3>
                    <p style="margin: 0; color: #666; font-size: 13px;">
                        <?= $currentPageData['description'] ?>
                    </p>
                </div>
            </div>
            
            <form id="test-form">
                <table class="hscl-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">No.</th>
                            <th class="hscl-item">Gejala / Masalah</th>
                            <th class="hscl-option">Tidak sama sekali (1)</th>
                            <th class="hscl-option">Sedikit (2)</th>
                            <th class="hscl-option">Cukup banyak (3)</th>
                            <th class="hscl-option">Sangat banyak (4)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $questionIndex = 1;
                        foreach ($currentPageData['questions'] as $q): 
                            $isChecked = isset($userAnswers[$q['id']]);
                            $selectedOption = $userAnswers[$q['id']] ?? '';
                        ?>
                        <tr data-question-id="<?= $q['id'] ?>">
                            <td style="text-align: center; font-weight: 600; color: #667eea; width: 5%;"><?= $questionIndex++ ?></td>
                            <td class="hscl-item"><?= htmlspecialchars($q['question_text']) ?></td>
                            <?php 
                            $options = ['a', 'b', 'c', 'd'];
                            foreach ($options as $opt):
                                $isSelectedOpt = $selectedOption === $opt;
                            ?>
                            <td class="hscl-option">
                                <input 
                                    type="radio" 
                                    name="question_<?= $q['id'] ?>" 
                                    value="<?= $opt ?>"
                                    <?= $isSelectedOpt ? 'checked' : '' ?>
                                    onchange="saveAnswer(<?= $q['id'] ?>, '<?= $opt ?>')"
                                >
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- Navigation Buttons -->
        <div class="test-actions">
            <a href="index.php" class="btn btn-secondary btn-large">
                ← Keluar Test
            </a>
            <div style="display: flex; gap: 12px;">
                <?php if (!$pagination->isFirstPage()): ?>
                <button type="button" class="btn btn-secondary btn-large" onclick="previousPage()">
                    ← Halaman Sebelumnya
                </button>
                <?php endif; ?>
                
                <?php if ($pagination->isLastPage()): ?>
                <button type="button" class="btn btn-success btn-large" onclick="submitTest()" style="background: #27ae60;">
                    Selesai & Lihat Hasil
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-primary btn-large" onclick="nextPage()">
                    Halaman Berikutnya →
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let remainingSeconds = <?= $remainingTime ?>;
        let totalQuestions = document.querySelectorAll('table tbody tr[data-question-id]').length || <?= count($currentPageData['questions']) ?>;
        
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        
        function saveAnswer(questionId, option) {
            fetch('test.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=save_answer&question_id=${questionId}&selected_option=${option}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    console.log('Jawaban disimpan untuk soal ' + questionId);
                }
            })
            .catch(e => console.error('Error:', e));
        }
        
        function nextPage() {
            // Check if all questions on current page are answered
            const unanswered = Array.from(document.querySelectorAll('table tbody tr[data-question-id]'))
                .filter(row => {
                    const id = row.dataset.questionId;
                    return !document.querySelector(`input[name="question_${id}"]:checked`);
                });
            
            if (unanswered.length > 0) {
                alert('⚠️ Harap jawab semua pertanyaan pada halaman ini sebelum melanjutkan.');
                unanswered[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                unanswered[0].style.backgroundColor = '#fff59d';
                setTimeout(() => {
                    unanswered[0].style.backgroundColor = '';
                }, 2000);
                return;
            }
            
            // Last page - show submit modal
            if (<?= $pagination->isLastPage() ? 'true' : 'false' ?>) {
                showSubmitModal();
                return;
            }
            
            // Navigate to next page via POST
            fetch('test.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=navigate_page&direction=nextPage'
            })
            .then(r => r.json())
            .then(data => {
                console.log('Next page response:', data);
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Gagal berpindah halaman: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(e => {
                console.error('Error navigating:', e);
                alert('Gagal berpindah halaman: ' + e.message);
            });
        }
        
        function previousPage() {
            fetch('test.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=navigate_page&direction=previousPage'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(e => {
                console.error('Error:', e);
                alert('Gagal berpindah halaman');
            });
        }
        
        function submitTest(autoSubmit = false) {
            if (!autoSubmit) {
                showSubmitModal();
                return;
            }
            performSubmit();
        }

        function showSubmitModal() {
            const modal = document.createElement('div');
            modal.id = 'submit-modal';
            modal.innerHTML = `
                <div class="modal-overlay">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>🎯 Selesai Assessment</h3>
                        </div>
                        <div class="modal-body">
                            <div class="modal-icon">📋</div>
                            <p class="modal-message">
                                Apakah Anda yakin ingin menyelesaikan assessment ini?
                            </p>
                            <div class="modal-warning">
                                <strong>⚠️ Pastikan:</strong>
                                <ul>
                                    <li>Semua pertanyaan sudah dijawab</li>
                                    <li>Jawaban sudah sesuai dengan kondisi Anda</li>
                                    <li>Anda tidak dapat mengubah jawaban setelah menyelesaikan</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button class="btn btn-secondary" onclick="closeSubmitModal()">
                                ❌ Batal
                            </button>
                            <button class="btn btn-success" onclick="performSubmit()">
                                ✅ Ya, Selesaikan
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Add modal styles
            const style = document.createElement('style');
            style.textContent = `
                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    animation: fadeIn 0.3s ease-out;
                }

                .modal-content {
                    background: white;
                    border-radius: 16px;
                    padding: 0;
                    max-width: 450px;
                    width: 90%;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    animation: slideIn 0.3s ease-out;
                    overflow: hidden;
                }

                .modal-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 24px;
                    text-align: center;
                }

                .modal-header h3 {
                    margin: 0;
                    font-size: 20px;
                    font-weight: 700;
                }

                .modal-body {
                    padding: 24px;
                    text-align: center;
                }

                .modal-icon {
                    font-size: 48px;
                    margin-bottom: 16px;
                }

                .modal-message {
                    font-size: 16px;
                    color: #333;
                    margin-bottom: 20px;
                    font-weight: 600;
                }

                .modal-warning {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 8px;
                    padding: 16px;
                    text-align: left;
                    margin-top: 16px;
                }

                .modal-warning ul {
                    margin: 8px 0 0 0;
                    padding-left: 20px;
                }

                .modal-warning li {
                    color: #856404;
                    font-size: 14px;
                    margin-bottom: 4px;
                }

                .modal-actions {
                    padding: 24px;
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    background: #f8f9fa;
                }

                .modal-actions .btn {
                    flex: 1;
                    max-width: 140px;
                    padding: 12px 16px;
                    font-size: 14px;
                    font-weight: 600;
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: scale(0.9) translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: scale(1) translateY(0);
                    }
                }
            `;
            document.head.appendChild(style);
        }

        function closeSubmitModal() {
            const modal = document.getElementById('submit-modal');
            if (modal) {
                modal.remove();
            }
        }

        function performSubmit() {
            closeSubmitModal();

            const submitBtn = document.querySelector('.btn-success');
            const originalText = submitBtn ? submitBtn.textContent : 'Menyelesaikan...';
            if (submitBtn) submitBtn.disabled = true;

            fetch('test.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=submit_test'
            })
            .then(r => {
                if (!r.ok) {
                    throw new Error(`HTTP error! status: ${r.status}`);
                }
                return r.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        throw new Error('Response tidak valid: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else if (data.incomplete) {
                    showIncompleteWarning(data.question_id);
                    if (submitBtn) submitBtn.disabled = false;
                } else {
                    throw new Error(data.message || 'Gagal menyimpan hasil test');
                }
            })
            .catch(e => {
                console.error('Error:', e);
                alert('Terjadi kesalahan saat menyimpan hasil test: ' + e.message);
                if (submitBtn) submitBtn.disabled = false;
            });
        }

        function showIncompleteWarning(questionId) {
            const modal = document.createElement('div');
            modal.id = 'incomplete-modal';
            modal.innerHTML = `
                <div class="modal-overlay">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>⚠️ Pertanyaan Belum Lengkap</h3>
                        </div>
                        <div class="modal-body">
                            <div class="modal-icon" style="font-size: 48px;">📝</div>
                            <p class="modal-message">
                                Anda masih memiliki pertanyaan yang belum dijawab.
                            </p>
                            <div class="modal-warning" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                                <strong>⚠️ Perhatian:</strong>
                                <p style="margin: 8px 0; font-size: 14px;">
                                    Silakan jawab semua pertanyaan sebelum menyelesaikan assessment.
                                </p>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button class="btn btn-primary" onclick="closeIncompleteModal()">
                                ✓ Oke, Kembali Mengerjakan
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function closeIncompleteModal() {
            const modal = document.getElementById('incomplete-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        window.addEventListener('load', () => {
            totalQuestions = document.querySelectorAll('table tbody tr[data-question-id]').length || totalQuestions;
        });
    </script>
</body>
</html>
