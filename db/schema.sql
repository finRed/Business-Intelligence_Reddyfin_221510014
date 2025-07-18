-- Database Schema untuk Sistem Rekomendasi Kontrak Karyawan
-- Updated Schema - Sesuai dengan implementasi sistem aktual dan revisi logika CSV upload
-- Created for ContractRec System

CREATE DATABASE IF NOT EXISTS contract_rec_db;
USE contract_rec_db;

-- Tabel Users (untuk autentikasi)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hr', 'manager') NOT NULL,
    division_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Tabel Divisions
CREATE TABLE divisions (
    division_id INT PRIMARY KEY AUTO_INCREMENT,
    division_name VARCHAR(100) NOT NULL,
    description TEXT,
    manager_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Employees (disesuaikan dengan implementasi aktual)
CREATE TABLE employees (
    eid INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,  -- Sesuai dengan implementasi aktual
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    birth_date DATE,
    address TEXT,
    status ENUM('active', 'resigned', 'terminated', 'pending') DEFAULT 'active',  -- Removed probation, added pending
    join_date DATE NOT NULL,
    resign_date DATE NULL,
    resign_reason TEXT NULL,  -- Kolom baru yang ditambahkan untuk CSV upload
    education_level ENUM('SMA/K', 'D3', 'S1', 'S2', 'S3') NOT NULL,  -- Added SMA/K
    major VARCHAR(100) NOT NULL,
    last_education_place VARCHAR(200),
    designation VARCHAR(100),
    role VARCHAR(100) NOT NULL,
    division_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (division_id) REFERENCES divisions(division_id)
);

-- Tabel Contracts (disesuaikan dengan revisi logika CSV upload terbaru)
CREATE TABLE contracts (
    contract_id INT PRIMARY KEY AUTO_INCREMENT,
    eid INT NOT NULL,  -- Sesuai dengan implementasi aktual (bukan employee_id)
    type ENUM('probation', '1', '2', '3', 'permanent') NOT NULL,  -- Sesuai dengan implementasi aktual
    start_date DATE NOT NULL,
    end_date DATE NULL,  -- NULL untuk permanent contract
    status ENUM('active', 'completed', 'terminated') DEFAULT 'active',  -- Removed 'extended'
    duration_months INT NULL,  -- Durasi kontrak dalam bulan untuk analisis
    review_date DATE,
    permanent_date DATE NULL,
    salary DECIMAL(15,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eid) REFERENCES employees(eid) ON DELETE CASCADE
);

-- Tabel Recommendations (Updated dengan contract_start_date)
CREATE TABLE recommendations (
    recommendation_id INT PRIMARY KEY AUTO_INCREMENT,
    eid INT NOT NULL,
    recommended_by INT NOT NULL,
    recommendation_type ENUM('extend', 'permanent', 'terminate', 'review', 'kontrak1', 'kontrak2', 'kontrak3', 'probation') NOT NULL,  -- Added probation
    recommended_duration INT NULL, -- dalam bulan, atau NULL untuk permanent
    contract_start_date DATE NULL, -- Tanggal mulai kontrak baru yang direkomendasikan
    reason TEXT, -- Alasan rekomendasi (boleh kosong)
    system_recommendation TEXT, -- Rekomendasi dari sistem AI/Data Intelligence
    status ENUM('pending', 'approved', 'rejected', 'extended', 'resign') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eid) REFERENCES employees(eid) ON DELETE CASCADE,
    FOREIGN KEY (recommended_by) REFERENCES users(user_id)
);

-- Tabel Contract History untuk tracking perubahan
CREATE TABLE contract_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT NOT NULL,
    action ENUM('created', 'extended', 'terminated', 'completed') NOT NULL,
    old_end_date DATE,
    new_end_date DATE,
    reason TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Tabel Analysis Cache untuk menyimpan hasil data intelligence
CREATE TABLE analysis_cache (
    cache_id INT PRIMARY KEY AUTO_INCREMENT,
    eid INT NOT NULL,
    analysis_type ENUM('education_match', 'contract_progression', 'resign_pattern', 'full_intelligence', 'dynamic_intelligence') NOT NULL,  -- Added dynamic_intelligence
    analysis_data JSON, -- Menyimpan hasil analisis dalam format JSON
    confidence_score DECIMAL(3,2), -- Score 0.00 - 1.00
    sample_size INT DEFAULT 0, -- Ukuran sampel data untuk analisis
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL, -- Cache expiration
    FOREIGN KEY (eid) REFERENCES employees(eid) ON DELETE CASCADE,
    INDEX idx_analysis_eid_type (eid, analysis_type),
    INDEX idx_analysis_expires (expires_at)
);

-- Insert default admin user
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@contractrec.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert default divisions
INSERT INTO divisions (division_name, description) VALUES 
('Operations', 'Operations Department'),
('PTSA', 'PTSA Department'),
('IT', 'Information Technology Department'),
('Finance', 'Finance Department'),
('HR', 'Human Resources Department');

-- Add foreign key constraints
ALTER TABLE users ADD FOREIGN KEY (division_id) REFERENCES divisions(division_id);
ALTER TABLE divisions ADD FOREIGN KEY (manager_id) REFERENCES users(user_id);

-- Indexes for better performance (diperluas untuk optimasi)
CREATE INDEX idx_employees_status ON employees(status);
CREATE INDEX idx_employees_division ON employees(division_id);
CREATE INDEX idx_employees_join_date ON employees(join_date);
CREATE INDEX idx_employees_resign_date ON employees(resign_date);
CREATE INDEX idx_employees_major ON employees(major);
CREATE INDEX idx_employees_role ON employees(role);
CREATE INDEX idx_employees_education_level ON employees(education_level);
CREATE INDEX idx_contracts_eid ON contracts(eid);
CREATE INDEX idx_contracts_type ON contracts(type);
CREATE INDEX idx_contracts_status ON contracts(status);
CREATE INDEX idx_contracts_start_date ON contracts(start_date);
CREATE INDEX idx_contracts_end_date ON contracts(end_date);
CREATE INDEX idx_contracts_eid_status ON contracts(eid, status);  -- Composite index for better performance
CREATE INDEX idx_recommendations_eid ON recommendations(eid);
CREATE INDEX idx_recommendations_status ON recommendations(status); 
CREATE INDEX idx_recommendations_type ON recommendations(recommendation_type);
CREATE INDEX idx_recommendations_created_at ON recommendations(created_at);
CREATE INDEX idx_recommendations_contract_start_date ON recommendations(contract_start_date);

-- Views untuk kemudahan query (Updated sesuai logika terbaru)
CREATE VIEW active_employees_with_contracts AS
SELECT 
    e.eid,
    e.name,
    e.email,
    e.role,
    e.join_date,
    e.status as employee_status,
    e.division_id,
    e.major,
    e.education_level,
    d.division_name,
    c.contract_id,
    c.type as contract_type,
    c.start_date as contract_start,
    c.end_date as contract_end,
    c.status as contract_status,
    DATEDIFF(CURDATE(), e.join_date) as days_employed,
    TIMESTAMPDIFF(MONTH, e.join_date, CURDATE()) as tenure_months,
    CASE 
        WHEN c.end_date IS NULL THEN NULL
        ELSE DATEDIFF(c.end_date, CURDATE())
    END as days_to_contract_end,
    CASE 
        WHEN e.status = 'resigned' OR e.status = 'terminated' THEN e.status
        WHEN e.status = 'active' AND c.type = 'probation' AND c.status = 'active' THEN 'probation'
        WHEN e.status = 'active' AND c.type IS NOT NULL THEN 'active'
        WHEN e.status = 'active' AND c.type IS NULL THEN 'active'
        ELSE e.status
    END as display_status
FROM employees e
LEFT JOIN divisions d ON e.division_id = d.division_id
LEFT JOIN contracts c ON e.eid = c.eid 
    AND c.contract_id = (
        SELECT MAX(contract_id) 
        FROM contracts c2 
        WHERE c2.eid = e.eid
    )
WHERE e.status IN ('active', 'resigned', 'terminated');

-- View untuk pending recommendations dengan informasi lengkap (Updated)
CREATE VIEW pending_recommendations_view AS
SELECT 
    r.recommendation_id,
    r.eid,
    e.name as employee_name,
    e.email as employee_email,
    e.role as employee_role,
    e.major as employee_major,
    e.education_level,
    d.division_name,
    r.recommendation_type,
    r.recommended_duration,
    r.contract_start_date,
    r.reason,
    r.system_recommendation,
    r.status,
    u.username as recommended_by_name,
    md.division_name as manager_division_name,
    c.type as current_contract_type,
    c.start_date as current_contract_start,
    c.end_date as current_contract_end,
    c.status as current_contract_status,
    CASE 
        WHEN c.end_date IS NOT NULL 
        THEN DATEDIFF(c.end_date, CURDATE())
        ELSE NULL 
    END as days_until_contract_end,
    TIMESTAMPDIFF(MONTH, e.join_date, CURDATE()) as employee_tenure_months,
    r.created_at,
    r.updated_at
FROM recommendations r
JOIN employees e ON r.eid = e.eid
LEFT JOIN divisions d ON e.division_id = d.division_id
JOIN users u ON r.recommended_by = u.user_id
LEFT JOIN divisions md ON u.division_id = md.division_id
LEFT JOIN contracts c ON e.eid = c.eid 
    AND c.contract_id = (
        SELECT MAX(contract_id) 
        FROM contracts c2 
        WHERE c2.eid = e.eid
    )
WHERE r.status = 'pending';

-- View untuk statistik dashboard manager (Updated)
CREATE VIEW manager_dashboard_stats AS
SELECT 
    d.division_id,
    d.division_name,
    COUNT(DISTINCT e.eid) as total_employees,
    COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.eid END) as active_employees,
    COUNT(DISTINCT CASE WHEN e.status = 'resigned' THEN e.eid END) as resigned_employees,
    COUNT(DISTINCT CASE WHEN c.type = 'probation' AND c.status = 'active' THEN e.eid END) as probation_employees,
    COUNT(DISTINCT CASE WHEN c.type = 'permanent' THEN e.eid END) as permanent_employees,
    COUNT(DISTINCT CASE WHEN c.end_date IS NOT NULL AND DATEDIFF(c.end_date, CURDATE()) <= 30 AND DATEDIFF(c.end_date, CURDATE()) > 0 THEN e.eid END) as contracts_expiring_soon,
    COUNT(DISTINCT r.recommendation_id) as total_recommendations,
    COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.recommendation_id END) as pending_recommendations,
    COUNT(DISTINCT CASE WHEN r.status = 'approved' THEN r.recommendation_id END) as approved_recommendations
FROM divisions d
LEFT JOIN employees e ON d.division_id = e.division_id
LEFT JOIN contracts c ON e.eid = c.eid 
    AND c.contract_id = (
        SELECT MAX(contract_id) 
        FROM contracts c2 
        WHERE c2.eid = e.eid
    )
LEFT JOIN recommendations r ON e.eid = r.eid
GROUP BY d.division_id, d.division_name;

-- View untuk contract distribution analysis (New)
CREATE VIEW contract_distribution_view AS
SELECT 
    d.division_id,
    d.division_name,
    c.type as contract_type,
    COUNT(*) as contract_count,
    ROUND(AVG(TIMESTAMPDIFF(MONTH, c.start_date, COALESCE(c.end_date, CURDATE()))), 1) as avg_duration_months
FROM contracts c
JOIN employees e ON c.eid = e.eid
JOIN divisions d ON e.division_id = d.division_id
WHERE c.contract_id = (
    SELECT MAX(contract_id) 
    FROM contracts c2 
    WHERE c2.eid = e.eid
)
AND e.status = 'active'
GROUP BY d.division_id, d.division_name, c.type
ORDER BY d.division_name, c.type;

-- View untuk education job match analysis (New)
CREATE VIEW education_match_analysis AS
SELECT 
    e.major,
    e.role,
    e.education_level,
    COUNT(*) as total_employees,
    COUNT(CASE WHEN e.status = 'active' THEN 1 END) as active_count,
    COUNT(CASE WHEN e.status = 'resigned' THEN 1 END) as resigned_count,
    ROUND((COUNT(CASE WHEN e.status = 'active' THEN 1 END) / COUNT(*)) * 100, 1) as retention_rate,
    ROUND(AVG(TIMESTAMPDIFF(MONTH, e.join_date, COALESCE(e.resign_date, CURDATE()))), 1) as avg_tenure_months
FROM employees e
WHERE e.join_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)  -- Data 5 tahun terakhir
GROUP BY e.major, e.role, e.education_level
HAVING total_employees >= 3  -- Minimal 3 data untuk analisis yang meaningful
ORDER BY retention_rate DESC, avg_tenure_months DESC;

-- Trigger untuk otomatis update contract history
DELIMITER //
CREATE TRIGGER contract_history_trigger 
AFTER UPDATE ON contracts
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status OR OLD.end_date != NEW.end_date THEN
        INSERT INTO contract_history (
            contract_id, 
            action, 
            old_end_date, 
            new_end_date, 
            reason, 
            created_at
        ) VALUES (
            NEW.contract_id,
            CASE 
                WHEN NEW.status = 'terminated' THEN 'terminated'
                WHEN NEW.status = 'completed' THEN 'completed'
                WHEN OLD.end_date != NEW.end_date THEN 'extended'
                ELSE 'created'
            END,
            OLD.end_date,
            NEW.end_date,
            CONCAT('Status changed from ', OLD.status, ' to ', NEW.status),
            NOW()
        );
    END IF;
END//
DELIMITER ;

-- Trigger untuk auto-calculate duration_months pada contracts
DELIMITER //
CREATE TRIGGER contract_duration_trigger 
BEFORE INSERT ON contracts
FOR EACH ROW
BEGIN
    IF NEW.end_date IS NOT NULL AND NEW.start_date IS NOT NULL THEN
        SET NEW.duration_months = TIMESTAMPDIFF(MONTH, NEW.start_date, NEW.end_date);
    END IF;
END//
DELIMITER ;

DELIMITER //
CREATE TRIGGER contract_duration_update_trigger 
BEFORE UPDATE ON contracts
FOR EACH ROW
BEGIN
    IF NEW.end_date IS NOT NULL AND NEW.start_date IS NOT NULL THEN
        SET NEW.duration_months = TIMESTAMPDIFF(MONTH, NEW.start_date, NEW.end_date);
    END IF;
END//
DELIMITER ;

-- Event untuk otomatis cleanup cache yang expired
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_expired_cache
ON SCHEDULE EVERY 6 HOUR
DO
BEGIN
    DELETE FROM analysis_cache WHERE expires_at IS NOT NULL AND expires_at < NOW();
END//
DELIMITER ;

-- Event untuk otomatis update contract status yang sudah expired
DELIMITER //
CREATE EVENT IF NOT EXISTS update_expired_contracts
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    UPDATE contracts 
    SET status = 'completed' 
    WHERE status = 'active' 
    AND end_date IS NOT NULL 
    AND end_date < CURDATE();
END//
DELIMITER ;

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- Additional indexes untuk performance optimization
CREATE INDEX idx_employees_major_role ON employees(major, role);
CREATE INDEX idx_employees_education_role ON employees(education_level, role);
CREATE INDEX idx_contracts_type_status ON contracts(type, status);
CREATE INDEX idx_contracts_end_date_status ON contracts(end_date, status);

-- Comments untuk dokumentasi
ALTER TABLE employees 
    COMMENT = 'Tabel utama karyawan dengan status dan informasi personal',
    MODIFY COLUMN status ENUM('active', 'resigned', 'terminated', 'pending') DEFAULT 'active' 
    COMMENT 'Status karyawan: active=aktif bekerja, resigned=mengundurkan diri, terminated=diberhentikan, pending=status sementara';

ALTER TABLE contracts 
    COMMENT = 'Tabel kontrak karyawan dengan logika: probation -> 2 -> 3 -> permanent',
    MODIFY COLUMN type ENUM('probation', '1', '2', '3', 'permanent') NOT NULL 
    COMMENT 'Tipe kontrak: probation=masa percobaan 3 bulan, 1/2/3=kontrak bertahap, permanent=tetap',
    MODIFY COLUMN status ENUM('active', 'completed', 'terminated') DEFAULT 'active'
    COMMENT 'Status kontrak: active=berlangsung, completed=selesai normal, terminated=dihentikan paksa';

-- Dokumentasi sistem
-- PENTING: Logika Sistem Kontrak
-- 1. Setiap karyawan dengan JOIN DATE akan memiliki kontrak probation 3 bulan
-- 2. Jika RESIGN DATE kosong, karyawan aktif mengikuti tipe kontrak terakhir
-- 3. Query mengambil kontrak terakhir (MAX contract_id) bukan hanya yang aktif
-- 4. Status "tidak ada kontrak" tidak akan muncul untuk karyawan aktif
-- 5. Contract progression: probation -> kontrak 2 -> kontrak 3 -> permanent 