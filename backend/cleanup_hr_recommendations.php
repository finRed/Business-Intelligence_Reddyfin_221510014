<?php
require_once __DIR__ . '/config/db.php';

// Script to remove recommendations created by HR users
// HR should not be able to create recommendations, only managers can

$db = new Database();

echo "<html><head><title>Pembersihan Rekomendasi HR</title></head><body>";
echo "<h2>🧹 Pembersihan Rekomendasi yang Dibuat oleh HR</h2>\n";
echo "<p><strong>⚠️ Catatan:</strong> HR tidak seharusnya bisa membuat rekomendasi. Hanya manager yang bisa membuat rekomendasi karyawan.</p>\n";

try {
    // Find recommendations created by HR users
    $find_hr_recommendations_sql = "
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
    
    echo "<p>🔍 Mencari rekomendasi yang dibuat oleh HR...</p>\n";
    
    $result = $db->query($find_hr_recommendations_sql);
    
    if ($result->num_rows === 0) {
        echo "<p style='color: green;'>✅ Tidak ada rekomendasi yang dibuat oleh HR ditemukan.</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ Ditemukan " . $result->num_rows . " rekomendasi yang dibuat oleh HR:</p>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>\n";
        echo "<tr style='background-color: #f0f0f0;'><th>Recommendation ID</th><th>Employee</th><th>HR User</th><th>Type</th><th>Durasi</th><th>Status</th><th>Tanggal</th><th>Action</th></tr>\n";
        
        $total_deleted = 0;
        $hr_recommendations = [];
        
        while ($row = $result->fetch_assoc()) {
            $hr_recommendations[] = $row;
            
            echo "<tr>\n";
            echo "<td>{$row['recommendation_id']}</td>\n";
            echo "<td>{$row['employee_name']}</td>\n";
            echo "<td>{$row['hr_username']} ({$row['role']})</td>\n";
            echo "<td>{$row['recommendation_type']}</td>\n";
            echo "<td>{$row['recommended_duration']} bulan</td>\n";
            echo "<td>{$row['status']}</td>\n";
            echo "<td>{$row['created_at']}</td>\n";
            echo "<td style='color: red;'>🗑️ Akan dihapus</td>\n";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        
        // Delete all HR recommendations
        $delete_hr_recommendations_sql = "
            DELETE r FROM recommendations r
            JOIN users u ON r.recommended_by = u.user_id
            WHERE u.role = 'hr'
        ";
        
        if ($db->query($delete_hr_recommendations_sql)) {
            $total_deleted = $db->affected_rows;
            echo "<p style='background-color: #d4edda; padding: 10px; border-radius: 5px;'><strong>✅ Berhasil menghapus {$total_deleted} rekomendasi yang dibuat oleh HR</strong></p>\n";
        } else {
            echo "<p style='color: red; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>❌ Gagal menghapus rekomendasi HR: " . $db->error . "</p>\n";
        }
    }
    
    // Show remaining pending recommendations (should only be from managers now)
    echo "<h3>📋 Rekomendasi Pending yang Tersisa (Hanya dari Manager)</h3>\n";
    $remaining_sql = "
        SELECT r.recommendation_id, r.eid, e.name as employee_name, 
               r.recommended_by, u.username as manager_name, u.role,
               r.recommendation_type, r.recommended_duration,
               r.created_at
        FROM recommendations r
        JOIN employees e ON r.eid = e.eid
        JOIN users u ON r.recommended_by = u.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
    ";
    
    $remaining_result = $db->query($remaining_sql);
    
    if ($remaining_result->num_rows === 0) {
        echo "<p>Tidak ada rekomendasi pending tersisa.</p>\n";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>\n";
        echo "<tr style='background-color: #e3f2fd;'><th>ID</th><th>Karyawan</th><th>Pembuat</th><th>Role</th><th>Type</th><th>Durasi</th><th>Tanggal</th></tr>\n";
        
        while ($row = $remaining_result->fetch_assoc()) {
            $role_color = $row['role'] === 'manager' ? 'green' : 'red';
            echo "<tr>\n";
            echo "<td>{$row['recommendation_id']}</td>\n";
            echo "<td>{$row['employee_name']}</td>\n";
            echo "<td>{$row['manager_name']}</td>\n";
            echo "<td style='color: {$role_color}; font-weight: bold;'>{$row['role']}</td>\n";
            echo "<td>{$row['recommendation_type']}</td>\n";
            echo "<td>{$row['recommended_duration']} bulan</td>\n";
            echo "<td>{$row['created_at']}</td>\n";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        echo "<p style='background-color: #e3f2fd; padding: 10px; border-radius: 5px;'><strong>Total rekomendasi pending tersisa: " . $remaining_result->num_rows . "</strong></p>\n";
    }
    
    // Show summary
    echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>📊 Ringkasan Pembersihan</h4>\n";
    echo "<ul>\n";
    echo "<li>✅ Rekomendasi HR yang dihapus: <strong>{$total_deleted}</strong></li>\n";
    echo "<li>📋 Rekomendasi manager yang tersisa: <strong>" . $remaining_result->num_rows . "</strong></li>\n";
    echo "<li>🔒 <strong>Sistem sekarang hanya menampilkan rekomendasi dari manager</strong></li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>❌ Error: " . $e->getMessage() . "</p>\n";
}

echo "<p style='margin-top: 30px;'><a href='../frontend/public/index.html' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Kembali ke Dashboard</a></p>\n";
echo "</body></html>";
?> 