<?php
require_once __DIR__ . '/config/db.php';

// Direct database cleanup without session validation
// WARNING: This should only be used for maintenance purposes

$db = new Database();

echo "<html><head><title>Pembersihan Duplikat</title></head><body>";
echo "<h2>🧹 Pembersihan Rekomendasi Duplikat (Direct Mode)</h2>\n";

try {
    // Find duplicate recommendations for the same employee
    $find_duplicates_sql = "
        SELECT eid, recommended_by, COUNT(*) as duplicate_count,
               GROUP_CONCAT(recommendation_id ORDER BY created_at DESC) as recommendation_ids,
               GROUP_CONCAT(created_at ORDER BY created_at DESC) as created_dates
        FROM recommendations 
        WHERE status = 'pending'
        GROUP BY eid, recommended_by
        HAVING COUNT(*) > 1
        ORDER BY duplicate_count DESC
    ";
    
    echo "<p>🔍 Mencari rekomendasi duplikat...</p>\n";
    
    $result = $db->query($find_duplicates_sql);
    
    if ($result->num_rows === 0) {
        echo "<p style='color: green;'>✅ Tidak ada rekomendasi duplikat ditemukan.</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ Ditemukan " . $result->num_rows . " karyawan dengan rekomendasi duplikat:</p>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>\n";
        echo "<tr style='background-color: #f0f0f0;'><th>Employee ID</th><th>Employee Name</th><th>Manager</th><th>Jumlah Duplikat</th><th>Recommendation IDs</th><th>Action</th></tr>\n";
        
        $total_deleted = 0;
        
        while ($row = $result->fetch_assoc()) {
            $eid = $row['eid'];
            $recommended_by = $row['recommended_by'];
            $duplicate_count = $row['duplicate_count'];
            $recommendation_ids = explode(',', $row['recommendation_ids']);
            $created_dates = explode(',', $row['created_dates']);
            
            // Get employee and manager names
            $employee_info_sql = "
                SELECT e.name as employee_name, u.username as manager_name
                FROM employees e, users u
                WHERE e.eid = ? AND u.user_id = ?
            ";
            $stmt = $db->prepare($employee_info_sql);
            $stmt->bind_param('ii', $eid, $recommended_by);
            $stmt->execute();
            $info_result = $stmt->get_result();
            $info = $info_result->fetch_assoc();
            
            echo "<tr>\n";
            echo "<td>{$eid}</td>\n";
            echo "<td>{$info['employee_name']}</td>\n";
            echo "<td>{$info['manager_name']}</td>\n";
            echo "<td style='text-align: center; color: red; font-weight: bold;'>{$duplicate_count}</td>\n";
            echo "<td>" . implode(', ', $recommendation_ids) . "</td>\n";
            
            // Keep the newest recommendation, delete the older ones
            $newest_id = $recommendation_ids[0]; // First one is newest due to ORDER BY created_at DESC
            $ids_to_delete = array_slice($recommendation_ids, 1); // Remove the first (newest) element
            
            if (!empty($ids_to_delete)) {
                $ids_string = implode(',', $ids_to_delete);
                $delete_sql = "DELETE FROM recommendations WHERE recommendation_id IN ($ids_string)";
                
                if ($db->query($delete_sql)) {
                    $deleted_count = count($ids_to_delete);
                    $total_deleted += $deleted_count;
                    echo "<td style='color: green;'>✅ Dihapus {$deleted_count} rekomendasi lama<br>(Dipertahankan ID: {$newest_id})</td>\n";
                } else {
                    echo "<td style='color: red;'>❌ Gagal menghapus: " . $db->error . "</td>\n";
                }
            } else {
                echo "<td>-</td>\n";
            }
            
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        echo "<p style='background-color: #d4edda; padding: 10px; border-radius: 5px;'><strong>📊 Total rekomendasi duplikat yang dihapus: {$total_deleted}</strong></p>\n";
    }
    
    // Show remaining pending recommendations
    echo "<h3>📋 Rekomendasi Pending yang Tersisa</h3>\n";
    $remaining_sql = "
        SELECT r.recommendation_id, r.eid, e.name as employee_name, 
               r.recommended_by, u.username as manager_name,
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
        echo "<tr style='background-color: #e3f2fd;'><th>ID</th><th>Karyawan</th><th>Manager</th><th>Type</th><th>Durasi</th><th>Tanggal</th></tr>\n";
        
        while ($row = $remaining_result->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>{$row['recommendation_id']}</td>\n";
            echo "<td>{$row['employee_name']}</td>\n";
            echo "<td>{$row['manager_name']}</td>\n";
            echo "<td>{$row['recommendation_type']}</td>\n";
            echo "<td>{$row['recommended_duration']} bulan</td>\n";
            echo "<td>{$row['created_at']}</td>\n";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        echo "<p style='background-color: #e3f2fd; padding: 10px; border-radius: 5px;'><strong>Total rekomendasi pending tersisa: " . $remaining_result->num_rows . "</strong></p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>❌ Error: " . $e->getMessage() . "</p>\n";
}

echo "<p style='margin-top: 30px;'><a href='../frontend/public/index.html' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Kembali ke Dashboard</a></p>\n";
echo "</body></html>";
?> 