<?php
require_once __DIR__ . '/../config/database.php';

// Generate token unik dengan format: 4 huruf KAPITAL + 1 angka + 3 digit angka urut
function generateToken($sequence) {
    $letters = '';
    for ($i = 0; $i < 4; $i++) {
        $letters .= chr(rand(65, 90)); // A-Z
    }
    $randomDigit = rand(0, 9);
    $sequenceNumber = str_pad($sequence, 3, '0', STR_PAD_LEFT);
    
    return $letters . $randomDigit . $sequenceNumber;
}

// Validasi format token
function isValidTokenFormat($token) {
    return preg_match('/^[A-Z]{4}[0-9]{4}$/', $token);
}

// Cek ketersediaan token dengan error detail
function checkTokenAvailability($token, $allowUsed = false) {
    $db = getDBConnection();

    // Cek apakah token ada di database
    $stmt = $db->prepare("SELECT id, is_used, user_name, expires_at, test_completed_at FROM tokens WHERE token_code = ?");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();

    // Debug: return array dengan status check
    if (!$tokenData) {
        return ['error' => 'TOKEN_NOT_FOUND'];
    }

    // Hanya cek is_used jika allowUsed = false
    if (!$allowUsed && $tokenData['is_used']) {
        return ['error' => 'TOKEN_ALREADY_USED'];
    }

    if (strtotime($tokenData['expires_at']) < time()) {
        return ['error' => 'TOKEN_EXPIRED'];
    }

    return $tokenData;
}

// Tandai token sebagai digunakan
function markTokenAsUsed($tokenId, $userName, $userEmail) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        UPDATE tokens 
        SET is_used = TRUE, 
            user_name = ?, 
            user_email = ?,
            used_at = NOW(),
            test_started_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$userName, $userEmail, $tokenId]);
}

// Update waktu mulai test
function updateTestStartTime($tokenId) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        UPDATE tokens 
        SET test_started_at = NOW()
        WHERE id = ? AND test_started_at IS NULL
    ");
    return $stmt->execute([$tokenId]);
}

// Simpan jawaban user
function saveAnswer($tokenId, $questionId, $selectedOption, $points) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO user_answers (token_id, question_id, selected_option, points_earned)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            selected_option = VALUES(selected_option),
            points_earned = VALUES(points_earned),
            answered_at = NOW()
    ");
    return $stmt->execute([$tokenId, $questionId, $selectedOption, $points]);
}

// Ambil semua soal test
function getAllQuestions() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM questions WHERE is_active = TRUE ORDER BY id");
    return $stmt->fetchAll();
}

// Ambil jawaban user yang sudah tersimpan
function getUserAnswers($tokenId) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT question_id, selected_option 
        FROM user_answers 
        WHERE token_id = ?
    ");
    $stmt->execute([$tokenId]);
    $answers = [];
    while ($row = $stmt->fetch()) {
        $answers[$row['question_id']] = $row['selected_option'];
    }
    return $answers;
}

// Hitung dan simpan hasil test
function calculateAndSaveResult($tokenId) {
    $db = getDBConnection();

    // Get token's selected test types
    $tokenStmt = $db->prepare("SELECT selected_test_types FROM tokens WHERE id = ?");
    $tokenStmt->execute([$tokenId]);
    $tokenData = $tokenStmt->fetch();
    $selectedTests = $tokenData['selected_test_types'] ?? 'HSCL-25';
    
    // Parse selected test types
    $testTypes = array_map('trim', explode(',', $selectedTests));
    
    // Create placeholders for selected test types
    $placeholders = implode(',', array_fill(0, count($testTypes), '?'));

    // Hitung total skor - hanya untuk selected test types
    $stmt = $db->prepare("
        SELECT
            SUM(ua.points_earned) as total_score,
            COUNT(*) as answered_questions
        FROM user_answers ua
        JOIN questions q ON ua.question_id = q.id
        WHERE ua.token_id = ? AND q.test_type IN ($placeholders)
    ");
    $params = array_merge([$tokenId], $testTypes);
    $stmt->execute($params);
    $result = $stmt->fetch();

    // Hitung max score - hanya untuk selected test types
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM questions WHERE is_active = TRUE AND test_type IN ($placeholders)");
    $countStmt->execute($testTypes);
    $maxData = $countStmt->fetch();
    $totalQuestions = $maxData['total'];
    $maxScore = $totalQuestions * 4; // Max 4 points per question

    $totalScore = $result['total_score'] ?? 0;
    $answeredQuestions = $result['answered_questions'] ?? 0;
    $percentage = ($maxScore > 0) ? ($totalScore / $maxScore) * 100 : 0;

    // Check if result already exists
    $checkStmt = $db->prepare("SELECT id FROM test_results WHERE token_id = ?");
    $checkStmt->execute([$tokenId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Update existing
        $stmt = $db->prepare("
            UPDATE test_results SET
                total_score = ?,
                max_score = ?,
                percentage = ?,
                total_questions = ?,
                answered_questions = ?,
                test_types_taken = ?,
                completed_at = NOW()
            WHERE token_id = ?
        ");
        $stmt->execute([$totalScore, $maxScore, $percentage, $totalQuestions, $answeredQuestions, $selectedTests, $tokenId]);
    } else {
        // Insert new
        $stmt = $db->prepare("
            INSERT INTO test_results
            (token_id, total_score, max_score, percentage, total_questions, answered_questions, test_types_taken)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tokenId, $totalScore, $maxScore, $percentage, $totalQuestions, $answeredQuestions, $selectedTests]);
    }

    // Update token sebagai completed
    $stmt = $db->prepare("UPDATE tokens SET test_completed_at = NOW() WHERE id = ?");
    $stmt->execute([$tokenId]);
    
    // Log test submission
    if (function_exists('getLogger')) {
        $logger = getLogger($db);
        $tokenStmt = $db->prepare("SELECT token_code, user_name FROM tokens WHERE id = ?");
        $tokenStmt->execute([$tokenId]);
        $tokenInfo = $tokenStmt->fetch();
        if ($tokenInfo) {
            $logger->logTestSubmission(
                $tokenId,
                $tokenInfo['token_code'],
                $tokenInfo['user_name'] ?? 'Unknown',
                $selectedTests,
                $totalScore,
                $maxScore,
                round($percentage, 2)
            );
        }
    }

    return [
        'total_score' => $totalScore,
        'max_score' => $maxScore,
        'percentage' => round($percentage, 2),
        'total_questions' => $totalQuestions,
        'answered_questions' => $answeredQuestions
    ];
}

// Ambil hasil test
function getTestResult($tokenId) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT tr.*, t.user_name, t.user_email, t.completed_at as finished_at
        FROM test_results tr
        JOIN tokens t ON tr.token_id = t.id
        WHERE tr.token_id = ?
        ORDER BY tr.id DESC
        LIMIT 1
    ");
    $stmt->execute([$tokenId]);
    return $stmt->fetch();
}

// Cek sisa waktu test
function getRemainingTime($tokenId) {
    return 999999; // unlimited time - no time limit
}
// Format waktu
function formatTime($seconds) {
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf("%02d:%02d", $minutes, $secs);
}

// Simpan profile user
function saveUserProfile($tokenId, $firstName, $lastName, $dateOfBirth, $gender, $phoneNumber, $religion, $city, $district, $subDistrict, $address, $occupation) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO user_profiles 
        (token_id, first_name, last_name, date_of_birth, gender, phone_number, religion, city, district, sub_district, address, occupation)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            date_of_birth = VALUES(date_of_birth),
            gender = VALUES(gender),
            phone_number = VALUES(phone_number),
            religion = VALUES(religion),
            city = VALUES(city),
            district = VALUES(district),
            sub_district = VALUES(sub_district),
            address = VALUES(address),
            occupation = VALUES(occupation),
            updated_at = NOW()
    ");
    return $stmt->execute([$tokenId, $firstName, $lastName, $dateOfBirth, $gender, $phoneNumber, $religion, $city, $district, $subDistrict, $address, $occupation]);
}

// Ambil profile user
function getUserProfile($tokenId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM user_profiles WHERE token_id = ?");
    $stmt->execute([$tokenId]);
    return $stmt->fetch();
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Admin functions
function createAdminUser($username, $password, $fullName, $email) {
    $db = getDBConnection();

    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return false;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT INTO admin_users (username, password, full_name, email)
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$username, $hashedPassword, $fullName, $email]);
}

function updateAdminUser($userId, $updateData) {
    $db = getDBConnection();

    $fields = [];
    $values = [];

    if (isset($updateData['full_name'])) {
        $fields[] = "full_name = ?";
        $values[] = $updateData['full_name'];
    }

    if (isset($updateData['email'])) {
        $fields[] = "email = ?";
        $values[] = $updateData['email'];
    }

    if (isset($updateData['password'])) {
        $fields[] = "password = ?";
        $values[] = password_hash($updateData['password'], PASSWORD_DEFAULT);
    }

    if (isset($updateData['is_active'])) {
        $fields[] = "is_active = ?";
        $values[] = $updateData['is_active'];
    }

    if (empty($fields)) {
        return false;
    }

    $values[] = $userId;
    $sql = "UPDATE admin_users SET " . implode(", ", $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute($values);
}

function deleteAdminUser($userId) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM admin_users WHERE id = ?");
    return $stmt->execute([$userId]);
}

function getAllAdminUsers() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM admin_users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function getAdminUser($userId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}
?>