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
            $error = "Token harus diisi!";
        } elseif (!isValidTokenFormat($tokenCode)) {
            $error = "Format token tidak valid! Format: 4 HURUF + 4 ANGKA (contoh: ABCD0001)";
        } else {
            $tokenData = checkTokenAvailability($tokenCode, true); // Allow used tokens for re-login

            // Check error dari function
            if (is_array($tokenData) && isset($tokenData['error'])) {
                $errorType = $tokenData['error'];
                if ($errorType === 'TOKEN_NOT_FOUND') {
                    $error = "Token '$tokenCode' tidak ditemukan di sistem!";
                } elseif ($errorType === 'TOKEN_ALREADY_USED') {
                    $error = "Token sudah pernah digunakan oleh peserta lain!";
                } elseif ($errorType === 'TOKEN_EXPIRED') {
                    $error = "Token sudah expired dan tidak bisa digunakan lagi!";
                } else {
                    $error = "Token tidak valid!";
                }
            } elseif (!$tokenData) {
                $error = "Token tidak valid atau sudah expired!";
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
            $error = "Session expired, silakan mulai dari awal";
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
                $error = "Nama Depan dan Email harus diisi!";
                $step = 'data';
            } elseif (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                $error = "Format email tidak valid!";
                $step = 'data';
            } else {
                $tokenData = checkTokenAvailability($tokenCode);
                
                if (is_array($tokenData) && isset($tokenData['error'])) {
                    $error = "Token tidak valid, silakan mulai dari awal";
                    $step = 'token';
                    unset($_SESSION['temp_token_code']);
                    unset($_SESSION['temp_token_id']);
                } elseif (!$tokenData) {
                    $error = "Token tidak valid, silakan mulai dari awal";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .input-group-icon {
            position: relative;
        }
        .input-group-icon i {
            position: absolute;
            left: 12px;
            top: 42px; /* Adjust vertical position */
            color: #aaa;
            font-size: 16px;
            pointer-events: none;
        }
        .input-group-icon input, 
        .input-group-icon select {
            padding-left: 38px !important;
        }
        .navbar-brand { gap: 10px; }
        .brand-icon i { font-size: 24px; color: #667eea; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <span class="brand-icon"><i class="fas fa-shapes"></i></span>
                <span class="brand-text">ABLE.ID</span>
            </div>
            <div class="navbar-title"><i class="fas fa-laptop-code"></i> Test Online Platform</div>
        </div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?= $step === 'token' ? '<i class="fas fa-key"></i> Masukkan Token' : '<i class="fas fa-user-edit"></i> Data Diri' ?></h1>
                <p><?= $step === 'token' ? 'Masukkan kode token yang Anda terima' : 'Lengkapi data diri Anda untuk memulai test' ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form" id="loginForm" novalidate>
                <input type="hidden" name="action" id="formAction" value="verify_token">

                <div id="stepToken" class="form-step <?= $step === 'token' ? 'active' : '' ?>" style="display: <?= $step === 'token' ? 'block' : 'none' ?>;">
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-ticket-alt"></i> Token Code</div>
                        
                        <div class="form-group input-group-icon">
                            <label for="token">Token <span class="required">*</span></label>
                            <i class="fas fa-font"></i>
                            <input
                                type="text"
                                id="token"
                                name="token"
                                placeholder="Contoh: ABCD0001"
                                maxlength="8"
                                style="text-transform: uppercase; font-size: 18px; letter-spacing: 2px; font-weight: 600;"
                            >
                            <small><i class="fas fa-info-circle"></i> Format: 4 huruf kapital + 4 angka</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        Lanjut <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <div id="stepData" class="form-step <?= $step === 'data' ? 'active' : '' ?>" style="display: <?= $step === 'data' ? 'block' : 'none' ?>;">
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-id-card"></i> Identitas Diri</div>
                        
                        <div class="form-row">
                            <div class="form-group input-group-icon">
                                <label for="first_name">Nama Depan <span class="required">*</span></label>
                                <i class="fas fa-user"></i>
                                <input type="text" id="first_name" name="first_name" placeholder="Nama depan">
                            </div>
                            <div class="form-group input-group-icon">
                                <label for="last_name">Nama Belakang</label>
                                <i class="fas fa-user"></i>
                                <input type="text" id="last_name" name="last_name" placeholder="Nama belakang">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group input-group-icon">
                                <label for="date_of_birth">Tanggal Lahir</label>
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" id="date_of_birth" name="date_of_birth">
                            </div>
                            <div class="form-group input-group-icon">
                                <label for="gender">Jenis Kelamin</label>
                                <i class="fas fa-venus-mars"></i>
                                <select id="gender" name="gender">
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group input-group-icon">
                                <label for="email">Email <span class="required">*</span></label>
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="email@example.com">
                            </div>
                            <div class="form-group input-group-icon">
                                <label for="phone">Nomor Telepon</label>
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone" name="phone" placeholder="08xxxxxxxxx">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group input-group-icon">
                                <label for="religion">Agama</label>
                                <i class="fas fa-pray"></i>
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
                            <div class="form-group input-group-icon">
                                <label for="occupation">Pekerjaan</label>
                                <i class="fas fa-briefcase"></i>
                                <input type="text" id="occupation" name="occupation" placeholder="Contoh: Mahasiswa">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-map-marker-alt"></i> Alamat</div>
                        
                        <div class="form-row">
                            <div class="form-group input-group-icon">
                                <label for="city">Kota / Kab</label>
                                <i class="fas fa-city"></i>
                                <input type="text" id="city" name="city" placeholder="Kota / Kabupaten">
                            </div>
                            <div class="form-group input-group-icon">
                                <label for="district">Kecamatan</label>
                                <i class="fas fa-map"></i>
                                <input type="text" id="district" name="district" placeholder="Kecamatan">
                            </div>
                            <div class="form-group input-group-icon">
                                <label for="sub_district">Kelurahan</label>
                                <i class="fas fa-map-pin"></i>
                                <input type="text" id="sub_district" name="sub_district" placeholder="Kelurahan">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Alamat Detail</label>
                            <textarea id="address" name="address" placeholder="Masukkan alamat lengkap Anda" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="backToToken()">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </button>
                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('formAction').value = 'submit_data';">
                            Mulai Test <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="login-footer">
                <p><strong><i class="fas fa-info-circle"></i> Ketentuan:</strong></p>
                <ul>
                    <li>Satu token hanya dapat digunakan oleh satu user</li>
                    <li>Token berlaku maksimal 30 hari sejak dibuat</li>
                    <li>Waktu pengerjaan test: <?= TEST_DURATION ?> menit</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?= date('Y') ?> ABLE.ID. All rights reserved.</p>
    </div>

    <script>
        function backToToken() {
            document.getElementById('stepData').classList.remove('active');
            document.getElementById('stepToken').classList.add('active');
            window.scrollTo(0, 0);
        }

        window.addEventListener('load', function() {
            const tokenInput = document.getElementById('token');
            if (tokenInput) {
                tokenInput.focus();
            }
        });

        <?php if ($step === 'data'): ?>
            document.getElementById('stepToken').classList.remove('active');
            document.getElementById('stepData').classList.add('active');
        <?php endif; ?>
    </script>
</body>
</html>