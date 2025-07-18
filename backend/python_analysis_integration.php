<?php
// Script untuk integrasi dengan employee_contract_analysis.py
require_once __DIR__ . '/config/db.php';

class PythonAnalysisIntegration {
    private $db;
    private $python_script_path;
    
    public function __construct() {
        $this->db = new Database();
        $this->python_script_path = __DIR__ . '/../../employee_contract_analysis.py';
    }
    
    /**
     * Export data karyawan ke CSV untuk analisis Python
     */
    public function exportEmployeeDataForAnalysis($division_id = null) {
        try {
            $sql = "SELECT e.*, d.division_name, c.type as contract_type, c.start_date as contract_start, c.end_date as contract_end
                    FROM employees e 
                    LEFT JOIN divisions d ON e.division_id = d.division_id
                    LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
                    WHERE e.status = 'active'";
            
            if ($division_id) {
                $sql .= " AND e.division_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $division_id);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $this->db->query($sql);
            }
            
            $csv_file = __DIR__ . '/temp/employee_data_analysis.csv';
            $handle = fopen($csv_file, 'w');
            
            // Header CSV
            $headers = ['eid', 'name', 'email', 'major', 'role', 'designation', 'education_level', 
                       'join_date', 'birth_date', 'division_name', 'contract_type', 'contract_start', 'contract_end'];
            fputcsv($handle, $headers);
            
            // Data rows
            while ($row = $result->fetch_assoc()) {
                fputcsv($handle, [
                    $row['eid'],
                    $row['name'],
                    $row['email'],
                    $row['major'],
                    $row['role'],
                    $row['designation'],
                    $row['education_level'],
                    $row['join_date'],
                    $row['birth_date'],
                    $row['division_name'],
                    $row['contract_type'],
                    $row['contract_start'],
                    $row['contract_end']
                ]);
            }
            
            fclose($handle);
            return $csv_file;
            
        } catch (Exception $e) {
            throw new Exception('Error exporting data: ' . $e->getMessage());
        }
    }
    
    /**
     * Menjalankan analisis Python dan mengambil hasilnya
     */
    public function runPythonAnalysis($division_id = null) {
        try {
            // Export data ke CSV
            $csv_file = $this->exportEmployeeDataForAnalysis($division_id);
            
            // Jalankan script Python
            $python_command = "python3 {$this->python_script_path} --input {$csv_file} --output-dir " . __DIR__ . "/temp/analysis_output";
            $output = [];
            $return_code = 0;
            
            exec($python_command, $output, $return_code);
            
            if ($return_code !== 0) {
                throw new Exception('Python analysis failed with code: ' . $return_code);
            }
            
            // Baca hasil analisis
            $results = $this->parseAnalysisResults();
            
            // Cleanup temporary files
            unlink($csv_file);
            
            return $results;
            
        } catch (Exception $e) {
            throw new Exception('Error running Python analysis: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse hasil analisis dari file output Python
     */
    private function parseAnalysisResults() {
        $output_dir = __DIR__ . "/temp/analysis_output";
        $results = [];
        
        try {
            // Baca file JSON hasil analisis jika ada
            $json_files = glob($output_dir . "/*.json");
            foreach ($json_files as $json_file) {
                $content = file_get_contents($json_file);
                $data = json_decode($content, true);
                $results[basename($json_file, '.json')] = $data;
            }
            
            return $results;
            
        } catch (Exception $e) {
            // Jika file JSON tidak ada, return hasil default
            return $this->getDefaultAnalysisResults();
        }
    }
    
    /**
     * Hasil analisis default jika Python script tidak tersedia
     */
    private function getDefaultAnalysisResults() {
        return [
            'education_job_match' => $this->calculateEducationJobMatchPHP(),
            'contract_recommendations' => $this->generateContractRecommendationsPHP(),
            'performance_predictions' => $this->generatePerformancePredictionsPHP()
        ];
    }
    
    /**
     * Implementasi PHP untuk perhitungan kecocokan pendidikan-pekerjaan
     */
    public function calculateEducationJobMatchPHP($division_id = null) {
        $sql = "SELECT e.*, d.division_name FROM employees e 
                LEFT JOIN divisions d ON e.division_id = d.division_id 
                WHERE e.status = 'active'";
        
        if ($division_id) {
            $sql .= " AND e.division_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('i', $division_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->db->query($sql);
        }
        
        $matches = [];
        while ($row = $result->fetch_assoc()) {
            $match_score = $this->calculateAdvancedEducationJobMatch($row);
            $matches[] = [
                'eid' => $row['eid'],
                'name' => $row['name'],
                'major' => $row['major'],
                'role' => $row['role'],
                'match_score' => $match_score['score'],
                'match_level' => $this->getMatchLevel($match_score['percentage']),
                'recommendation' => $this->getRecommendationFromScore($match_score['score']),
                'details' => $match_score['details'],
                'education_match' => $match_score['education_match'],
                'role_match' => $match_score['role_match']
            ];
        }
        
        return $matches;
    }
    
    /**
     * Generate rekomendasi kontrak berdasarkan analisis
     */
    public function generateContractRecommendationsPHP($division_id = null) {
        $sql = "SELECT e.*, c.type as current_contract, c.start_date, c.end_date,
                       TIMESTAMPDIFF(MONTH, e.join_date, CURDATE()) as tenure_months,
                       DATEDIFF(c.end_date, CURDATE()) as days_to_end
                FROM employees e 
                LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
                WHERE e.status = 'active'";
        
        if ($division_id) {
            $sql .= " AND e.division_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('i', $division_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->db->query($sql);
        }
        
        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $match_data = $this->calculateAdvancedEducationJobMatch($row);
            $performance_data = $this->generatePerformancePredictionsPHP($row['division_id'] ?? null);
            $recommendations[] = [
                'eid' => $row['eid'],
                'name' => $row['name'],
                'current_contract' => $row['current_contract'],
                'tenure_months' => $row['tenure_months'],
                'days_to_end' => $row['days_to_end'],
                'match_score' => $match_data['score'],
                'recommendation' => $this->generateContractRecommendation($row, $match_data, $performance_data),
                'priority' => $this->getPriority($row['days_to_end']),
                'match_level' => $this->getMatchLevel($match_data['percentage']),
                'details' => $match_data['details'],
                'education_match' => $match_data['education_match'],
                'role_match' => $match_data['role_match']
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Generate prediksi performa
     */
    public function generatePerformancePredictionsPHP($division_id = null) {
        $matches = $this->calculateEducationJobMatchPHP($division_id);
        $predictions = [];
        
        foreach ($matches as $match) {
            $success_probability = ($match['match_score'] * 0.6) + 20; // Base 20% + match score influence
            $success_probability = min($success_probability, 95); // Cap at 95%
            
            $predictions[] = [
                'eid' => $match['eid'],
                'name' => $match['name'],
                'match_score' => $match['match_score'],
                'success_probability' => round($success_probability, 1),
                'risk_level' => $success_probability >= 70 ? 'Low' : ($success_probability >= 50 ? 'Medium' : 'High'),
                'development_areas' => $this->identifyDevelopmentAreas($match['match_score']),
                'strengths' => $this->identifyStrengths($match['match_score'])
            ];
        }
        
        return $predictions;
    }
    
    /**
     * Hitung skor kecocokan berdasarkan major, role, dan designation
     */
    private function calculateAdvancedEducationJobMatch($employee) {
        // UNIFIED COMPREHENSIVE EDUCATION KEYWORDS - matching ALL other files
        $it_education_keywords = [
            'INFORMATION TECHNOLOGY', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 'SISTEM INFORMASI',
            'COMPUTER SCIENCE', 'SOFTWARE ENGINEERING', 'INFORMATIKA', 'TEKNIK KOMPUTER',
            'KOMPUTER', 'TEKNOLOGI INFORMASI', 'INFORMATION SYSTEM', 'INFORMATICS ENGINEERING',
            'INFORMATIC ENGINEERING', 'COMPUTER ENGINEERING', 'INFORMATION MANAGEMENT',
            'INFORMATICS MANAGEMENT', 'MANAGEMENT INFORMATIKA', 'SISTEM KOMPUTER',
            'KOMPUTERISASI AKUNTANASI', 'COMPUTERIZED ACCOUNTING', 'COMPUTATIONAL SCIENCE',
            'INFORMATICS', 'INFORMATICS TECHNOLOGY', 'COMPUTER AND INFORMATICS ENGINEERING',
            'ENGINEERING INFORMATIC', 'INDUSTRIAL ENGINEERING_INFORMATIC',
            'STATISTICS', 'TECHNOLOGY MANAGEMENT', 'ELECTRICAL ENGINEERING', 'ELECTRONICS ENGINEERING',
            'MASTER IN INFORMATICS', 'ICT', 'COMPUTER SCIENCE & ELECTRONICS', 'COMPUTER TELECOMMUNICATION',
            'TELECOMMUNICATION ENGINEERING', 'TELECOMMUNICATIONS ENGINEERING', 'TELECOMMUNICATION & MEDIA',
            'TEKNIK ELEKTRO', 'COMPUTER SCINCE', 'COMPUTER SCIENCES & ENGINEERING', 'COMPUTER SYSTEM',
            'COMPUTER SIENCE', 'COMPUTER TECHNOLOGY', 'INFORMASTION SYSTEM',
            'INFORMATICS TECHNIQUE', 'INFORMATIOCS', 'INFORMATICS TECHNOLOGY', 'TEKNIK KOMPUTER & JARINGAN',
            'INFORMATION ENGINEERING', 'SOFTWARE ENGINEERING TECHNOLOGY', 'DIGITAL MEDIA TECHNOLOGY',
            'INFORMATIC ENGINEETING', 'INFORMATION SYTEM', 'MATERIALS ENGINEERING',
            'NETWORK MANAGEMENT', 'INFORMATICS SYSTEM', 'BUSINESS INFORMATION SYSTEM',
            'PHYSICS ENGINEERING', 'ELECTRICAL ENGINEERING_ELECTRONICS', 'ELECTRONIC ENGINEERING',
            'ELECTRONICS & COMPUTER ENGINEERING', 'TELECOMMUNICATION ENGINEERING_ELECTRONICS',
            // CRITICAL FIX: Add missing simple keywords that should be included
            'INFORMATION', 'TECHNOLOGY', 'TECHONOLOGY', 'SYSTEM', 'SISTEM',
            // Additional typo variants found in CSV data
            'TECHONOLOGY INFORMATION', 'TECHNOLOGY INFORMATION', 'INFORMATION TECHONOLOGY',
            // Add more comprehensive education patterns
            'COMPUTER', 'KOMPUTER', 'PROGRAMMING', 'CODING', 'SCIENCE', 'MATH', 'MATHEMATICS', 
            'DATA', 'NETWORK', 'DIGITAL', 'CYBER', 'ELEKTRONIK', 'ELECTRONIC', 'TELEKOMUNIKASI', 'TELECOMMUNICATION'
        ];
        
        // UNIFIED COMPREHENSIVE ROLE KEYWORDS - matching ALL other files
        $it_role_keywords = [
            'DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'TECHNICAL', 'FRONTEND', 'BACKEND', 
            'FULLSTACK', 'FULL STACK', 'JAVA', 'NET', '.NET', 'API', 'ETL', 'MOBILE',
            'ANDROID', 'WEB', 'UI/UX', 'FRONT END', 'BACK END', 'PEGA', 'RPA',
            'ANALYST', 'BUSINESS ANALYST', 'SYSTEM ANALYST', 'DATA ANALYST', 'DATA SCIENTIST',
            'QUALITY ASSURANCE', 'QA', 'TESTER', 'TEST ENGINEER', 'CONSULTANT',
            'IT CONSULTANT', 'TECHNOLOGY CONSULTANT', 'TECHNICAL CONSULTANT',
            'PRODUCT OWNER', 'SCRUM MASTER', 'PMO', 'IT OPERATION', 'DEVOPS',
            'SYSTEM ADMINISTRATOR', 'DATABASE ADMINISTRATOR', 'NETWORK',
            'BI DEVELOPER', 'BI COGNOS DEVELOPER', 'POWER BI DEVELOPER', 'ETL DEVELOPER',
            'DATA ENGINEER', 'DATABASE ADMINISTRATOR', 'DBA', 'DEVOPS ENGINEER', 'CLOUD ENGINEER',
            'INFRASTRUCTURE ENGINEER', 'SECURITY ENGINEER', 'CYBERSECURITY', 'NETWORK ENGINEER',
            'NETWORK ADMINISTRATOR', 'IT SUPPORT', 'TECHNICAL SUPPORT', 'HELP DESK',
            'SYSTEM ADMINISTRATOR', 'SOFTWARE ENGINEER', 'SOFTWARE ARCHITECT', 'SOLUTIONS ARCHITECT',
            'MOBILE DEVELOPER', 'IOS DEVELOPER', 'ANDROID DEVELOPER', 'REACT DEVELOPER',
            'ANGULAR DEVELOPER', 'VUE DEVELOPER', 'NODE.JS DEVELOPER', 'PYTHON DEVELOPER',
            'JAVA DEVELOPER', 'C# DEVELOPER', 'PHP DEVELOPER', 'GOLANG DEVELOPER',
            'PROJECT MANAGER', 'IT PROJECT MANAGER', 'TECHNICAL PROJECT MANAGER',
            'DELIVERY MANAGER', 'PRODUCT MANAGER', 'PROGRAM MANAGER', 'SCRUM MASTER',
            'AGILE COACH', 'TEAM LEAD', 'TECH LEAD', 'ENGINEERING MANAGER', 'IT MANAGER',
            'DEVELOPMENT MANAGER', 'BUSINESS INTELLIGENCE ANALYST', 'BI ANALYST', 'REPORT DESIGNER',
            'REPORTING ANALYST', 'PROCESS ANALYST', 'FUNCTIONAL ANALYST', 'TECHNICAL ANALYST',
            'PERFORMANCE ANALYST', 'IT QUALITY ASSURANCE', 'TESTING ENGINEER', 'TESTER SUPPORT',
            'JR TESTER', 'IT TESTER', 'TESTING SPECIALIST', 'TESTING COORDINATOR', 'QUALITY TESTER',
            'JR QUALITY ASSURANCE', 'QUALITY ASSURANCE TESTER', 'TESTING ENGINEER SPECIALIST',
            'APPRENTICE TESTER', 'JR TESTER LEVEL 1', 'LEAD QA', 'LEAD BACKEND DEVELOPER', 'MANUAL',
            // Add basic keywords for broader matching
            'ENGINEER', 'ADMIN', 'DESIGNER', 'MANAGER', 'COORDINATOR', 'SPECIALIST', 'TECHNICIAN',
            'ARCHITECT', 'LEAD', 'SENIOR', 'JUNIOR'
        ];

        $major = strtoupper($employee['major'] ?? '');
        $role = strtoupper($employee['role'] ?? '');
        
        // Check if education is IT-related
        $education_match = false;
        foreach ($it_education_keywords as $keyword) {
            if (strpos($major, $keyword) !== false) {
                $education_match = true;
                break;
            }
        }
        
        // Check if role is IT-related
        $role_match = false;
        foreach ($it_role_keywords as $keyword) {
            if (strpos($role, $keyword) !== false) {
                $role_match = true;
                break;
            }
        }
        
        // UNIFIED SCORING LOGIC - match all other files exactly
        $score = 0;
        if ($education_match && $role_match) {
            $score = 3; // Perfect match
        } elseif ($education_match || $role_match) {
            $score = 2; // Good match
        } else {
            $score = 1; // Basic match
        }
        
        // Convert to percentage for compatibility with existing code
        $percentage = ($score / 3) * 100;
        
        return [
            'score' => $score,
            'percentage' => $percentage,
            'education_match' => $education_match,
            'role_match' => $role_match,
            'details' => [
                'education_field' => $major,
                'role_field' => $role
            ]
        ];
    }
    
    private function getMatchLevel($score) {
        if ($score >= 70) return 'Tinggi';
        if ($score >= 50) return 'Sedang';
        return 'Rendah';
    }
    
    private function getRecommendationFromScore($score) {
        if ($score >= 70) return 'Kandidat sangat cocok untuk perpanjangan kontrak';
        if ($score >= 50) return 'Kandidat cocok dengan training tambahan';
        return 'Perlu evaluasi mendalam sebelum perpanjangan';
    }
    
    private function generateContractRecommendation($employee, $match_data, $performance_data) {
        $match_score = $match_data['percentage'];
        $performance_score = $performance_data['performance_score'] ?? 50;
        $tenure_months = $employee['tenure_months'] ?? 0;
        
        // Enhanced recommendation logic
        $recommendation = [
            'type' => 'probation',
            'duration_months' => 6,
            'reason' => 'Default probation period',
            'confidence' => 50,
            'priority' => 'medium',
            'action_required' => false
        ];
        
        // High performers with good match
        if ($match_score >= 75 && $performance_score >= 70) {
            if ($tenure_months >= 3) {
                $recommendation = [
                    'type' => '24_month_contract',
                    'duration_months' => 24,
                    'reason' => 'Excellent education-job match with strong performance',
                    'confidence' => 90,
                    'priority' => 'high',
                    'action_required' => true
                ];
            } else {
                $recommendation = [
                    'type' => '18_month_contract',
                    'duration_months' => 18,
                    'reason' => 'High potential candidate, extended initial contract',
                    'confidence' => 85,
                    'priority' => 'high',
                    'action_required' => true
                ];
            }
        } 
        // Good performers 
        elseif ($match_score >= 50 && $performance_score >= 60) {
            if ($tenure_months >= 6) {
                $recommendation = [
                    'type' => '18_month_contract',
                    'duration_months' => 18,
                    'reason' => 'Good match and performance, standard contract extension',
                    'confidence' => 75,
                    'priority' => 'medium',
                    'action_required' => true
                ];
            } else {
                $recommendation = [
                    'type' => '12_month_contract',
                    'duration_months' => 12,
                    'reason' => 'Satisfactory performance, standard contract',
                    'confidence' => 70,
                    'priority' => 'medium',
                    'action_required' => false
                ];
            }
        }
        // Below average performers
        elseif ($match_score < 50 || $performance_score < 50) {
            if ($tenure_months >= 3) {
                $recommendation = [
                    'type' => '6_month_review',
                    'duration_months' => 6,
                    'reason' => 'Performance concerns, short-term contract with review',
                    'confidence' => 60,
                    'priority' => 'high',
                    'action_required' => true
                ];
            } else {
                $recommendation = [
                    'type' => 'extended_probation',
                    'duration_months' => 6,
                    'reason' => 'Extended probation needed for evaluation',
                    'confidence' => 55,
                    'priority' => 'high',
                    'action_required' => true
                ];
            }
        }
        
        // Special case for long tenure employees
        if ($tenure_months >= 36 && $match_score >= 60) {
            $recommendation = [
                'type' => 'permanent_consideration',
                'duration_months' => 0,
                'reason' => 'Long tenure with good performance, consider permanent position',
                'confidence' => 95,
                'priority' => 'high',
                'action_required' => true
            ];
        }
        
        return $recommendation;
    }
    
    private function getPriority($days_to_end) {
        if ($days_to_end <= 7) return 'High';
        if ($days_to_end <= 30) return 'Medium';
        return 'Low';
    }
    
    private function identifyDevelopmentAreas($match_score) {
        $areas = [];
        if ($match_score < 50) {
            $areas[] = 'Pelatihan teknis sesuai bidang pendidikan';
            $areas[] = 'Mentoring intensif';
        } elseif ($match_score < 70) {
            $areas[] = 'Skill enhancement program';
        }
        return $areas;
    }
    
    private function identifyStrengths($match_score) {
        $strengths = [];
        if ($match_score >= 70) {
            $strengths[] = 'Kesesuaian pendidikan dengan pekerjaan sangat baik';
            $strengths[] = 'Potensi high performer';
        } elseif ($match_score >= 50) {
            $strengths[] = 'Dasar pendidikan yang solid';
        }
        return $strengths;
    }
    
    /**
     * Public method untuk mendapatkan analisis lengkap
     */
    public function getCompleteAnalysis($division_id = null) {
        return [
            'education_job_matches' => $this->calculateEducationJobMatchPHP($division_id),
            'contract_recommendations' => $this->generateContractRecommendationsPHP($division_id),
            'performance_predictions' => $this->generatePerformancePredictionsPHP($division_id)
        ];
    }
}

// Buat directory temp jika belum ada
if (!file_exists(__DIR__ . '/temp')) {
    mkdir(__DIR__ . '/temp', 0755, true);
}

if (!file_exists(__DIR__ . '/temp/analysis_output')) {
    mkdir(__DIR__ . '/temp/analysis_output', 0755, true);
}
?> 