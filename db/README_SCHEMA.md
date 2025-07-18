# Database Schema untuk Sistem Rekomendasi Kontrak Karyawan

## File Schema yang Tersedia

### 1. `schema_fresh.sql` - Fresh Installation
**Gunakan file ini untuk instalasi baru/fresh install**

- ✅ Database kosong kecuali akun admin
- ✅ Struktur tabel sesuai dengan sistem aktual
- ✅ Hanya berisi 1 user: admin
- ✅ Tidak ada data divisions, employees, atau contracts
- ✅ Cocok untuk development atau testing

**Kredensial Login:**
- Username: `admin`
- Password: `password`

### 2. `schema.sql` - Updated Schema
**File schema yang diperbarui sesuai sistem aktual**

- ✅ Struktur tabel lengkap dengan data default
- ✅ Berisi akun admin + default divisions
- ✅ Sesuai dengan implementasi backend yang ada
- ✅ Cocok untuk production dengan data awal

## Cara Import Database

### Opsi 1: Fresh Installation (Kosong)
```bash
# Import schema fresh (database kosong)
mysql -u root -p < schema_fresh.sql
```

### Opsi 2: With Default Data
```bash
# Import schema dengan data default
mysql -u root -p < schema.sql
```

### Opsi 3: Via phpMyAdmin
1. Buka phpMyAdmin
2. Pilih "Import"
3. Upload file `schema_fresh.sql` atau `schema.sql`
4. Klik "Go"

## Struktur Database

### Tabel Utama
1. **users** - User authentication dan role management
2. **divisions** - Divisi/departemen perusahaan
3. **employees** - Data karyawan
4. **contracts** - Kontrak karyawan
5. **recommendations** - Rekomendasi kontrak
6. **contract_history** - History perubahan kontrak

### Perubahan dari Schema Lama
- ✅ Tambah kolom `resign_reason` di tabel employees
- ✅ Tambah status `probation` di enum employees.status
- ✅ Tambah tipe recommendation: `kontrak1`, `kontrak2`, `kontrak3`
- ✅ Perbaiki foreign key: `eid` (bukan `employee_id`)
- ✅ Tambah indexes untuk performa yang lebih baik
- ✅ Tambah Views untuk query yang sering digunakan
- ✅ Tambah Trigger untuk otomatis update contract history

### Views yang Tersedia
1. **active_employees_with_contracts** - Karyawan aktif dengan kontrak
2. **pending_recommendations_view** - Rekomendasi yang pending

### Indexes untuk Performa
- Indexes pada kolom yang sering di-query
- Composite indexes untuk query kompleks
- Foreign key indexes untuk JOIN operations

## Kredensial Default

### Admin User
- **Username:** admin
- **Email:** admin@contractrec.com
- **Password:** password
- **Role:** admin

### Default Divisions (hanya di schema.sql)
- IT - Information Technology Department
- HR - Human Resources Department  
- Finance - Finance Department
- Marketing - Marketing Department
- Operations - Operations Department

## Troubleshooting

### Error: Table already exists
```sql
-- Drop database dan import ulang
DROP DATABASE IF EXISTS contract_rec_db;
-- Kemudian import file schema
```

### Error: Foreign key constraint
```sql
-- Disable foreign key checks sementara
SET FOREIGN_KEY_CHECKS = 0;
-- Import schema
-- Enable kembali
SET FOREIGN_KEY_CHECKS = 1;
```

### Verifikasi Import Berhasil
```sql
USE contract_rec_db;
SHOW TABLES;
SELECT COUNT(*) FROM users; -- Harus ada minimal 1 (admin)
```

## Backup dan Restore

### Backup Database
```bash
mysqldump -u root -p contract_rec_db > backup_$(date +%Y%m%d).sql
```

### Restore dari Backup
```bash
mysql -u root -p contract_rec_db < backup_20241224.sql
```

## Catatan Penting

⚠️ **PERINGATAN:** File `schema_fresh.sql` akan **DROP DATABASE** yang ada. Pastikan backup data penting sebelum import.

✅ **REKOMENDASI:** Gunakan `schema_fresh.sql` untuk development dan `schema.sql` untuk production setup.

📝 **UPDATE:** Schema ini sudah disesuaikan dengan implementasi sistem yang ada di backend PHP. 