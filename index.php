<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$step = 'token'; // 'token' or 'data'
$tokenData = null;

// Jika sudah login, redirect ke test
if (isUserLoggedIn()) {
    $tokenData = checkTokenAvailability($_SESSION['token_code']);
    if (is_array($tokenData) && !isset($tokenData['error']) && isset($tokenData['test_completed_at']) && $tokenData['test_completed_at']) {
        header("Location: result.php");
    } elseif (is_array($tokenData) && !isset($tokenData['error'])) {
        header("Location: test.php");
    } else {
        // Token tidak valid, logout
        logout();
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'verify_token') {
        // Step 1: Verify Token
        $tokenCode = strtoupper(sanitize($_POST['token'] ?? ''));
        
        if (empty($tokenCode)) {
            $error = "❌ Token harus diisi!";
        } elseif (!isValidTokenFormat($tokenCode)) {
            $error = "❌ Format token tidak valid! Format: 4 HURUF + 4 ANGKA (contoh: ABCD0001)";
        } else {
            $tokenData = checkTokenAvailability($tokenCode, true); // Allow used tokens for re-login

            // Check error dari function
            if (is_array($tokenData) && isset($tokenData['error'])) {
                $errorType = $tokenData['error'];
                if ($errorType === 'TOKEN_NOT_FOUND') {
                    $error = "❌ Token '$tokenCode' tidak ditemukan di sistem!";
                } elseif ($errorType === 'TOKEN_ALREADY_USED') {
                    $error = "❌ Token sudah pernah digunakan oleh peserta lain!";
                } elseif ($errorType === 'TOKEN_EXPIRED') {
                    $error = "❌ Token sudah expired dan tidak bisa digunakan lagi!";
                } else {
                    $error = "❌ Token tidak valid!";
                }
            } elseif (!$tokenData) {
                $error = "❌ Token tidak valid atau sudah expired!";
            } else {
                // Token valid, check if already used
                if ($tokenData['is_used']) {
                    // Re-login: langsung login tanpa isi data lagi
                    loginUser(['id' => $tokenData['id'], 'token_code' => $tokenCode], $tokenData['user_name'], $tokenData['user_email']);
                    if ($tokenData['test_completed_at']) {
                        header("Location: result.php");
                    } else {
                        header("Location: test.php");
                    }
                    exit;
                } else {
                    // First login: lanjut ke data entry
                    $step = 'data';
                    $_SESSION['temp_token_code'] = $tokenCode;
                    $_SESSION['temp_token_id'] = $tokenData['id'];
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'submit_data') {
        // Step 2: Submit Data & Login
        if (!isset($_SESSION['temp_token_code']) || !isset($_SESSION['temp_token_id'])) {
            $error = "❌ Session expired, silakan mulai dari awal";
            $step = 'token';
            unset($_SESSION['temp_token_code']);
            unset($_SESSION['temp_token_id']);
        } else {
            $tokenCode = $_SESSION['temp_token_code'];
            $tokenId = $_SESSION['temp_token_id'];
            $firstName = sanitize($_POST['first_name'] ?? '');
            $lastName = sanitize($_POST['last_name'] ?? '');
            $userEmail = sanitize($_POST['email'] ?? '');
            $dateOfBirth = sanitize($_POST['date_of_birth'] ?? '');
            $gender = sanitize($_POST['gender'] ?? '');
            $phoneNumber = sanitize($_POST['phone'] ?? '');
            $religion = sanitize($_POST['religion'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $district = sanitize($_POST['district'] ?? '');
            $subDistrict = sanitize($_POST['sub_district'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $occupation = sanitize($_POST['occupation'] ?? '');
            
            $userName = $firstName . ' ' . $lastName;
            
            // Validasi
            if (empty($firstName) || empty($userEmail)) {
                $error = "❌ Nama Depan dan Email harus diisi!";
                $step = 'data';
            } elseif (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                $error = "❌ Format email tidak valid!";
                $step = 'data';
            } else {
                $tokenData = checkTokenAvailability($tokenCode);
                
                if (is_array($tokenData) && isset($tokenData['error'])) {
                    $error = "❌ Token tidak valid, silakan mulai dari awal";
                    $step = 'token';
                    unset($_SESSION['temp_token_code']);
                    unset($_SESSION['temp_token_id']);
                } elseif (!$tokenData) {
                    $error = "❌ Token tidak valid, silakan mulai dari awal";
                    $step = 'token';
                    unset($_SESSION['temp_token_code']);
                    unset($_SESSION['temp_token_id']);
                } else {
                    // Save user data
                    if (!$tokenData['is_used']) {
                        markTokenAsUsed($tokenId, $userName, $userEmail);
                    }
                    loginUser(['id' => $tokenId, 'token_code' => $tokenCode], $userName, $userEmail);
                    saveUserProfile($tokenId, $firstName, $lastName, $dateOfBirth, $gender, $phoneNumber, $religion, $city, $district, $subDistrict, $address, $occupation);
                    
                    unset($_SESSION['temp_token_code']);
                    unset($_SESSION['temp_token_id']);
                    
                    header("Location: test.php");
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Test Online ABLE.ID</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <span class="brand-icon">◆</span>
                <span class="brand-text">ABLE.ID</span>
            </div>
            <div class="navbar-title">Test Online Platform</div>
        </div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?= $step === 'token' ? '🔐 Masukkan Token' : '📝 Data Diri' ?></h1>
                <p><?= $step === 'token' ? 'Masukkan kode token yang Anda terima' : 'Lengkapi data diri Anda untuk memulai test' ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                    </svg>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form" id="loginForm" novalidate>
                <input type="hidden" name="action" id="formAction" value="verify_token">

                <!-- STEP 1: Token Input -->
                <div id="stepToken" class="form-step <?= $step === 'token' ? 'active' : '' ?>" style="display: <?= $step === 'token' ? 'block' : 'none' ?>;">
                    <div class="form-section">
                        <div class="section-title">Token Codes</div>
                        
                        <div class="form-group">
                            <label for="token">Token <span class="required">*</span></label>
                            <input
                                type="text"
                                id="token"
                                name="token"
                                placeholder="Contoh: ABCD0001"
                                maxlength="8"
                                style="text-transform: uppercase; font-size: 18px; letter-spacing: 2px; font-weight: 600;"
                            >
                            <small>Format: 4 huruf kapital + 4 angka</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        Lanjut
                    </button>
                </div>

                <!-- STEP 2: Data Diri -->
                <div id="stepData" class="form-step <?= $step === 'data' ? 'active' : '' ?>" style="display: <?= $step === 'data' ? 'block' : 'none' ?>;">
                    <div class="form-section">
                        <div class="section-title">Data Diri</div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">Nama Depan <span class="required">*</span></label>
                                <input
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    placeholder="Nama depan"
                                    novalidate
                                >
                            </div>
                            <div class="form-group">
                                <label for="last_name">Nama Belakang</label>
                                <input
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    placeholder="Nama belakang"
                                    novalidate
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_birth">Tanggal Lahir</label>
                                <input
                                    type="date"
                                    id="date_of_birth"
                                    name="date_of_birth"
                                    novalidate
                                >
                            </div>
                            <div class="form-group">
                                <label for="gender">Jenis Kelamin</label>
                                <select id="gender" name="gender" novalidate>
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email <span class="required">*</span></label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    placeholder="email@example.com"
                                    novalidate
                                >
                            </div>
                            <div class="form-group">
                                <label for="phone">Nomor Telepon</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    placeholder="08xxxxxxxxx"
                                    novalidate
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="religion">Agama</label>
                                <select id="religion" name="religion">
                                    <option value="">Pilih Agama</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katholik">Katholik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                    <option value="Konghucu">Konghucu</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="occupation">Pekerjaan / Profesi</label>
                                <input 
                                    type="text" 
                                    id="occupation" 
                                    name="occupation" 
                                    placeholder="Contoh: Manager, Consultant"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title">Alamat</div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">Kota / Kabupaten</label>
                                <input 
                                    type="text" 
                                    id="city" 
                                    name="city" 
                                    placeholder="Kota / Kabupaten"
                                >
                            </div>
                            <div class="form-group">
                                <label for="district">Kecamatan</label>
                                <input 
                                    type="text" 
                                    id="district" 
                                    name="district" 
                                    placeholder="Kecamatan"
                                >
                            </div>
                            <div class="form-group">
                                <label for="sub_district">Kelurahan</label>
                                <input 
                                    type="text" 
                                    id="sub_district" 
                                    name="sub_district" 
                                    placeholder="Kelurahan"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Alamat Detail</label>
                            <textarea 
                                id="address" 
                                name="address" 
                                placeholder="Masukkan alamat lengkap Anda"
                                rows="3"
                            ></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="backToToken()">
                            Kembali
                        </button>
                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('formAction').value = 'submit_data';">
                            Mulai Test
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="login-footer">
                <p><strong>Ketentuan:</strong></p>
                <ul>
                    <li>Satu token hanya dapat digunakan oleh satu user</li>
                    <li>Token berlaku maksimal 30 hari sejak dibuat</li>
                    <li>Waktu pengerjaan test: <?= TEST_DURATION ?> menit</li>
                    <li>Jika terputus sebelum selesai, Anda dapat login kembali</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2026 ABLE.ID. All rights reserved.</p>
    </div>

    <script>
        function backToToken() {
            document.getElementById('stepData').classList.remove('active');
            document.getElementById('stepToken').classList.add('active');
            window.scrollTo(0, 0);
        }

        // Auto-focus on token input saat halaman load
        window.addEventListener('load', function() {
            const tokenInput = document.getElementById('token');
            if (tokenInput) {
                tokenInput.focus();
            }
        });

        // Show data step jika error terjadi di step 2
        <?php if ($step === 'data'): ?>
            document.getElementById('stepToken').classList.remove('active');
            document.getElementById('stepData').classList.add('active');
        <?php endif; ?>
    </script>
</body>
</html>