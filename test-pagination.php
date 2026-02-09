<?php
/**
 * Test Pagination Handler
 * Manage question pages and navigation
 */

class TestPagination {
    private $tokenId;
    private $testTypes;
    private $questionsMap = [];
    private $totalPages = 0;
    private $currentPage = 1;
    
    public function __construct($tokenId, $testTypes, $allQuestions) {
        $this->tokenId = $tokenId;
        $this->testTypes = $testTypes;
        $this->buildPagesMap($allQuestions);
        $this->loadCurrentPage();
    }
    
    /**
     * Build the page structure based on test types and questions
     */
    private function buildPagesMap($allQuestions) {
        $pageNum = 1;
        $this->questionsMap = [];
        
        // --- LOGIKA HSCL-25 ---
        if (in_array('HSCL-25', $this->testTypes)) {
            // Page 1: Anxiety (HSCL Items 1-10)
            $anxietyQ = array_filter($allQuestions, function($q) {
                return $q['category'] === 'Anxiety';
            });
            if (!empty($anxietyQ)) {
                $this->questionsMap[$pageNum] = [
                    'title' => 'ANXIETY (Kecemasan) - Items 1-10',
                    'description' => 'HSCL-25: Kelompok pertanyaan tentang kecemasan dan kekhawatiran',
                    'questions' => array_values($anxietyQ),
                    'icon' => 'fas fa-head-side-virus',
                    'type' => 'HSCL-25'
                ];
                $pageNum++;
            }
            
            // Page 2: Depression (HSCL Items 11-25)
            $depressionQ = array_filter($allQuestions, function($q) {
                return $q['category'] === 'Depresi';
            });
            if (!empty($depressionQ)) {
                $this->questionsMap[$pageNum] = [
                    'title' => 'DEPRESI (Kesedihan) - Items 11-25',
                    'description' => 'HSCL-25: Kelompok pertanyaan tentang depresi dan kesedihan',
                    'questions' => array_values($depressionQ),
                    'icon' => 'fas fa-cloud-rain',
                    'type' => 'HSCL-25'
                ];
                $pageNum++;
            }
        }
        
        // --- LOGIKA VAK ---
        if (in_array('VAK', $this->testTypes)) {
            // Page N: Visual
            $visualQ = array_filter($allQuestions, function($q) {
                return $q['category'] === 'Visual';
            });
            if (!empty($visualQ)) {
                $this->questionsMap[$pageNum] = [
                    'title' => 'VISUAL (Pembelajaran Visual)',
                    'description' => 'VAK: Pertanyaan tentang preferensi belajar visual',
                    'questions' => array_values($visualQ),
                    'icon' => 'fas fa-eye',
                    'type' => 'VAK'
                ];
                $pageNum++;
            }
            
            // Page N+1: Auditory + Kinesthetic
            $auditorQ = array_filter($allQuestions, function($q) {
                return $q['category'] === 'Auditory';
            });
            $kinestheticQ = array_filter($allQuestions, function($q) {
                return $q['category'] === 'Kinesthetic';
            });
            if (!empty($auditorQ) || !empty($kinestheticQ)) {
                $combinedQ = array_merge(
                    array_values($auditorQ),
                    array_values($kinestheticQ)
                );
                $this->questionsMap[$pageNum] = [
                    'title' => 'AUDITORY & KINESTHETIC',
                    'description' => 'VAK: Pertanyaan tentang preferensi belajar auditori & kinestik',
                    'questions' => $combinedQ,
                    'icon' => 'fas fa-headphones',
                    'type' => 'VAK'
                ];
                $pageNum++; // Jangan lupa increment pageNum
            }
        }

        // --- LOGIKA DISC (BARU DITAMBAHKAN) ---
        if (in_array('DISC', $this->testTypes)) {
            // Ambil semua soal yang test_type-nya 'DISC'
            $discQ = array_filter($allQuestions, function($q) {
                return isset($q['test_type']) && $q['test_type'] === 'DISC';
            });

            if (!empty($discQ)) {
                $this->questionsMap[$pageNum] = [
                    'title' => 'DISC PERSONALITY TEST',
                    'description' => 'Pilihlah jawaban yang paling menggambarkan diri Anda (Skala 1-4: 1 = Paling Tidak Mirip, 4 = Paling Mirip)',
                    'questions' => array_values($discQ),
                    'icon' => 'fas fa-users', // Ikon user group
                    'type' => 'DISC'
                ];
                $pageNum++;
            }
        }
        
        $this->totalPages = count($this->questionsMap);
    }
    
    private function loadCurrentPage() {
        if (isset($_SESSION['test_page'])) {
            $this->currentPage = intval($_SESSION['test_page']);
            if ($this->currentPage < 1 || $this->currentPage > $this->totalPages) {
                $this->currentPage = 1;
            }
        }
        $_SESSION['test_page'] = $this->currentPage;
    }
    
    public function getCurrentPage() {
        return $this->currentPage;
    }
    
    public function getTotalPages() {
        return $this->totalPages;
    }
    
    public function getCurrentPageData() {
        if (!isset($this->questionsMap[$this->currentPage])) {
            return null;
        }
        return $this->questionsMap[$this->currentPage];
    }
    
    public function nextPage() {
        if ($this->currentPage < $this->totalPages) {
            $this->currentPage++;
            $_SESSION['test_page'] = $this->currentPage;
            return true;
        }
        return false;
    }
    
    public function previousPage() {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $_SESSION['test_page'] = $this->currentPage;
            return true;
        }
        return false;
    }
    
    public function isLastPage() {
        return $this->currentPage === $this->totalPages;
    }
    
    public function isFirstPage() {
        return $this->currentPage === 1;
    }
    
    public function getProgressPercentage() {
        // Get total number of questions across all pages
        $totalQuestions = 0;
        foreach ($this->questionsMap as $page) {
            $totalQuestions += count($page['questions']);
        }
        
        if ($totalQuestions === 0) {
            return 0;
        }
        
        // Get number of answered questions
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as answered FROM user_answers WHERE token_id = ?");
        $stmt->execute([$this->tokenId]);
        $result = $stmt->fetch();
        $answeredQuestions = $result['answered'] ?? 0;
        
        return round(($answeredQuestions / $totalQuestions) * 100);
    }
    
    public function getTotalQuestions() {
        $total = 0;
        foreach ($this->questionsMap as $page) {
            $total += count($page['questions']);
        }
        return $total;
    }
    
    public function getAnsweredQuestions() {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as answered FROM user_answers WHERE token_id = ?");
        $stmt->execute([$this->tokenId]);
        $result = $stmt->fetch();
        return $result['answered'] ?? 0;
    }
}
?>