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
     * Pages structure:
     * - HSCL-25 only: Page 1 (Anxiety 1-10), Page 2 (Depresi 11-25)
     * - VAK only: Page 1 (Visual), Page 2 (Auditory + Kinesthetic)
     * - Both: Page 1 (Anxiety), Page 2 (Depresi), Page 3 (Visual), Page 4 (Auditory+Kinesthetic)
     */
    private function buildPagesMap($allQuestions) {
        $pageNum = 1;
        $this->questionsMap = [];
        
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
                    'icon' => '😰',
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
                    'icon' => '😢',
                    'type' => 'HSCL-25'
                ];
                $pageNum++;
            }
        }
        
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
                    'icon' => '🎨',
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
                    'icon' => '🎧',
                    'type' => 'VAK'
                ];
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
        if ($this->totalPages === 0) {
            return 0;
        }
        return round(($this->currentPage / $this->totalPages) * 100);
    }
}
?>
