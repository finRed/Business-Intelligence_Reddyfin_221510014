# Update Fitur: Tingkat Turnover per Filter Periode

## Ringkasan Perubahan

Telah ditambahkan card baru di dashboard HR untuk menampilkan "Tingkat Turnover per filter periode" yang memungkinkan HR untuk melihat tingkat turnover berdasarkan periode waktu tertentu.

## Fitur Baru

### 1. Card Tingkat Turnover dengan Filter Periode
- **Lokasi**: Dashboard HR (beranda)
- **Posisi**: Baris kedua, kolom ketiga (mengubah layout dari 2 kolom menjadi 3 kolom)
- **Fitur**: 
  - Menampilkan persentase turnover
  - Dropdown filter periode: Keseluruhan, 1 Bulan Terakhir, 3 Bulan Terakhir, 6 Bulan Terakhir, 1 Tahun Terakhir
  - Data otomatis update ketika filter periode diubah

## Perubahan File

### Frontend (`frontend/src/pages/Dashboard.js`)

1. **State Management Baru**:
   ```javascript
   const [turnoverPeriod, setTurnoverPeriod] = useState('all');
   const [turnoverData, setTurnoverData] = useState(null);
   ```

2. **Fungsi Baru**:
   - `fetchTurnoverData()`: Mengambil data turnover berdasarkan periode
   - `getTurnoverPercentage()`: Menghitung persentase turnover
   - `getPeriodLabel()`: Mengonversi kode periode menjadi label yang user-friendly

3. **Layout Update**:
   - Mengubah layout baris kedua dari `col-lg-6` menjadi `col-lg-4` untuk menampung 3 card
   - Menambahkan card baru dengan styling gradient merah untuk turnover

4. **Card Baru**:
   ```javascript
   <div className="col-lg-4 col-md-12 col-sm-12">
     <div className="dashboard-card h-100">
       <div className="stat-card" style={{background: 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100)', color: 'white'}}>
         <div className="stat-icon">
           <i className="fas fa-chart-area fa-2x"></i>
         </div>
         <div className="stat-content">
           <div className="stat-number">{getTurnoverPercentage()}%</div>
           <div className="stat-label">Tingkat Turnover</div>
           <div className="stat-description">
             <div className="mb-2">Per {getPeriodLabel()}</div>
             <select className="form-select form-select-sm" 
                     value={turnoverPeriod}
                     onChange={(e) => setTurnoverPeriod(e.target.value)}>
               <option value="all">Keseluruhan</option>
               <option value="1month">1 Bulan Terakhir</option>
               <option value="3months">3 Bulan Terakhir</option>
               <option value="6months">6 Bulan Terakhir</option>
               <option value="1year">1 Tahun Terakhir</option>
             </select>
           </div>
         </div>
       </div>
     </div>
   </div>
   ```

### Backend (`backend/api/employees.php`)

1. **Endpoint Baru**: `turnover_analysis`
   - URL: `/employees.php?action=turnover_analysis&period={period}`
   - Method: GET
   - Parameter: `period` (all, 1month, 3months, 6months, 1year)

2. **Fungsi Baru**: `getTurnoverAnalysis($db)`
   - Menghitung total karyawan, karyawan resign, dan karyawan aktif berdasarkan periode
   - Mendukung filter berdasarkan divisi untuk role manager
   - Menambahkan data turnover per divisi untuk admin/HR

3. **Logic Filter Periode**:
   ```php
   switch ($period) {
       case '1month':
           $date_condition = "AND resign_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
           break;
       case '3months':
           $date_condition = "AND resign_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
           break;
       case '6months':
           $date_condition = "AND resign_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
           break;
       case '1year':
           $date_condition = "AND resign_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
           break;
       case 'all':
       default:
           $date_condition = '';
           break;
   }
   ```

### CSS (`frontend/src/App.css`)

1. **Styling Baru untuk Stat Cards**:
   - `.stat-card`: Container utama untuk card statistik
   - `.stat-icon`: Styling untuk icon dengan efek hover
   - `.stat-content`: Area konten card
   - `.stat-number`: Styling untuk angka besar
   - `.stat-label`: Styling untuk label card
   - `.stat-description`: Styling untuk deskripsi card

2. **Gradient Colors**:
   - Primary: Biru gradient
   - Success: Hijau gradient  
   - Danger: Merah gradient
   - Warning: Kuning gradient
   - Info: Teal gradient

3. **Responsive Design**:
   - Menyesuaikan ukuran card untuk mobile
   - Mengatur ulang spacing dan font size

## Cara Penggunaan

1. **Akses Dashboard**: Login sebagai HR atau Admin
2. **Lihat Card Turnover**: Card berada di baris kedua dashboard
3. **Pilih Periode**: Gunakan dropdown untuk memilih periode analisis
4. **Lihat Hasil**: Persentase turnover akan otomatis update

## Fitur Keamanan

- Endpoint hanya dapat diakses oleh user yang sudah login
- Data turnover dibatasi berdasarkan role user (manager hanya bisa lihat divisinya)
- Validasi parameter periode untuk mencegah SQL injection

## Testing

Untuk menguji fitur ini:

1. Pastikan XAMPP/Apache sudah berjalan
2. Akses aplikasi melalui browser
3. Login sebagai HR
4. Lihat dashboard dan coba ubah filter periode pada card "Tingkat Turnover"
5. Verifikasi bahwa data berubah sesuai periode yang dipilih

## Database Requirements

Pastikan tabel `employees` memiliki kolom:
- `status`: untuk membedakan karyawan aktif dan resign
- `resign_date`: untuk filter berdasarkan tanggal resign
- `division_id`: untuk filter berdasarkan divisi

## Troubleshooting

1. **Card tidak muncul**: Pastikan user sudah login dan memiliki role yang tepat
2. **Data tidak update**: Periksa network tab di browser untuk melihat apakah API call berhasil
3. **Error 500**: Periksa log PHP untuk error database atau syntax 