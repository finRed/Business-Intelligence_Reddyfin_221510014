<?php
require_once __DIR__ . '/config/db.php';

// Script to verify that all recommendations are now from managers only

$db = new Database();

echo "<html><head><title>Verifikasi Pembersihan Rekomendasi</title></head><body>";
echo "<h2>✅ Verifikasi Pembersihan Rekomendasi</h2>\n";
echo "<p>Memastikan bahwa semua rekomendasi sekarang hanya dibuat oleh manager...</p>\n";

try {
    // Check for any remaining HR recommendations
    $check_hr_sql = "
        SELECT r.recommendation_id, r.eid, e.name as employee_name, 
               r.recommended_by, u.username as hr_username, u.role,
               r.recommendation_type, r.recommended_duration, r.status,
               r.created_at
        FROM recommendations r
        JOIN users u ON r.recommended_by = u.user_id
        JOIN employees e ON r.eid = e.eid
        WHERE u.role = 'hr'
        ORDER BY r.created_at DESC
    ";
    
    echo "<h3>🔍 Cek Rekomendasi dari HR</h3>\n";
    
    $hr_result = $db->query($check_hr_sql);
    
    if ($hr_result->num_rows === 0) {
        echo "<p style='color: green; background-color: #d4edda; padding: 10px; border-radius: 5px;'>✅ <strong>BAGUS!</strong> Tidak ada rekomendasi dari HR yang tersisa.</p>\n";
    } else {
        echo "<p style='color: red; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>❌ <strong>MASALAH!</strong> Masih ada " . $hr_result->num_rows . " rekomendasi dari HR:</p>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>\n";
        echo "<tr style='background-color: #f0f0f0;'><th>ID</th><th>Employee</th><th>HR User</th><th>Type</th><th>Status</th><th>Tanggal</th></tr>\n";
        
        while ($row = $hr_result->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>{$row['recommendation_id']}</td>\n";
            echo "<td>{$row['employee_name']}</td>\n";
            echo "<td>{$row['hr_username']} ({$row['role']})</td>\n";
            echo "<td>{$row['recommendation_type']}</td>\n";
            echo "<td>{$row['status']}</td>\n";
            echo "<td>{$row['created_at']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Show all remaining recommendations with role verification
    echo "<h3>📋 Semua Rekomendasi yang Tersisa</h3>\n";
    $all_recommendations_sql = "
        SELECT r.recommendation_id, r.eid, e.name as employee_name, 
               r.recommended_by, u.username as creator_name, u.role,
               r.recommendation_type, r.recommended_duration, r.status,
               r.created_at
        FROM recommendations r
        JOIN users u ON r.recommended_by = u.user_id
        JOIN employees e ON r.eid = e.eid
        ORDER BY r.created_at DESC
    ";
    
    $all_result = $db->query($all_recommendations_sql);
    
    if ($all_result->num_rows === 0) {
        echo "<p>Tidak ada rekomendasi tersisa dalam sistem.</p>\n";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>\n";
        echo "<tr style='background-color: #e3f2fd;'><th>ID</th><th>Employee</th><th>Pembuat</th><th>Role</th><th>Type</th><th>Durasi</th><th>Status</th><th>Tanggal</th></tr>\n";
        
        $manager_count = 0;
        $hr_count = 0;
        $other_count = 0;
        
        while ($row = $all_result->fetch_assoc()) {
            $role_color = 'black';
            if ($row['role'] === 'manager') {
                $role_color = 'green';
                $manager_count++;
            } elseif ($row['role'] === 'hr') {
                $role_color = 'red';
                $hr_count++;
            } else {
                $role_color = 'orange';
                $other_count++;
            }
            
            echo "<tr>\n";
            echo "<td>{$row['recommendation_id']}</td>\n";
            echo "<td>{$row['employee_name']}</td>\n";
            echo "<td>{$row['creator_name']}</td>\n";
            echo "<td style='color: {$role_color}; font-weight: bold;'>{$row['role']}</td>\n";
            echo "<td>{$row['recommendation_type']}</td>\n";
            echo "<td>{$row['recommended_duration']} bulan</td>\n";
            echo "<td>{$row['status']}</td>\n";
            echo "<td>{$row['created_at']}</td>\n";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        
        // Show summary statistics
        echo "<div style='background-color: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h4>📊 Statistik Rekomendasi</h4>\n";
        echo "<ul>\n";
        echo "<li style='color: green;'>✅ <strong>Manager:</strong> {$manager_count} rekomendasi</li>\n";
        echo "<li style='color: red;'>❌ <strong>HR:</strong> {$hr_count} rekomendasi</li>\n";
        echo "<li style='color: orange;'>⚠️ <strong>Lainnya:</strong> {$other_count} rekomendasi</li>\n";
        echo "<li><strong>Total:</strong> " . $all_result->num_rows . " rekomendasi</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        
        // Final status
        if ($hr_count === 0 && $other_count === 0) {
            echo "<div style='background-color: #d4edda; border: 2px solid #28a745; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
            echo "<h4 style='color: green;'>🎉 PEMBERSIHAN BERHASIL!</h4>\n";
            echo "<p><strong>Semua rekomendasi sekarang hanya dibuat oleh manager. Sistem sudah bersih!</strong></p>\n";
            echo "</div>\n";
        } else {
            echo "<div style='background-color: #f8d7da; border: 2px solid #dc3545; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
            echo "<h4 style='color: red;'>⚠️ MASIH ADA MASALAH!</h4>\n";
            echo "<p><strong>Masih ada rekomendasi dari non-manager. Perlu pembersihan lebih lanjut.</strong></p>\n";
            echo "</div>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>❌ Error: " . $e->getMessage() . "</p>\n";
}

echo "<p style='margin-top: 30px;'><a href='../frontend/public/index.html' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Kembali ke Dashboard</a></p>\n";
echo "</body></html>";
?> 