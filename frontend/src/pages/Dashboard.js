import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
} from 'chart.js';
import { Bar, Doughnut } from 'react-chartjs-2';
import { useAuth } from '../App';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement
);

const Dashboard = () => {
  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [turnoverPeriod, setTurnoverPeriod] = useState('all');
  const [turnoverData, setTurnoverData] = useState(null);
  const [retentionPeriod, setRetentionPeriod] = useState('all');
  const [retentionData, setRetentionData] = useState(null);
  const { user } = useAuth();

  useEffect(() => {
    fetchDashboardData();
  }, []);

  useEffect(() => {
    fetchTurnoverData();
  }, [turnoverPeriod]);

  useEffect(() => {
    fetchRetentionData();
  }, [retentionPeriod]);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      setError('');
      
      const response = await axios.get('/employees.php?action=dashboard', {
        withCredentials: true,
        timeout: 10000
      });
      
      if (response.data.success) {
        setDashboardData(response.data.data);
      } else {
        setError(response.data.error || 'Gagal memuat data dashboard');
      }
    } catch (error) {
      console.error('Dashboard error:', error);
      
      if (error.response) {
        // Server responded with error status
        setError(`Error ${error.response.status}: ${error.response.data?.error || 'Gagal memuat data dari server'}`);
      } else if (error.request) {
        // Request was made but no response received
        setError('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
      } else {
        // Something else happened
        setError('Terjadi kesalahan saat memuat data dashboard');
      }
    } finally {
      setLoading(false);
    }
  };

  const fetchTurnoverData = async () => {
    try {
      const response = await axios.get(`/employees.php?action=turnover_analysis&period=${turnoverPeriod}`, {
        withCredentials: true,
        timeout: 10000
      });
      
      if (response.data.success) {
        setTurnoverData(response.data.data);
      }
    } catch (error) {
      console.error('Turnover data error:', error);
      setTurnoverData(null);
    }
  };

  const fetchRetentionData = async () => {
    try {
      const response = await axios.get(`/employees.php?action=retention_analysis&period=${retentionPeriod}`, {
        withCredentials: true,
        timeout: 10000
      });
      
      if (response.data.success) {
        setRetentionData(response.data.data);
      }
    } catch (error) {
      console.error('Retention data error:', error);
      setRetentionData(null);
    }
  };

  const getTurnoverPercentage = () => {
    if (!turnoverData) return 0;
    
    const totalEmployees = parseInt(turnoverData.total_employees) || 0;
    const resignedEmployees = parseInt(turnoverData.resigned_employees) || 0;
    
    return totalEmployees > 0 ? Math.round((resignedEmployees / totalEmployees) * 100) : 0;
  };

  const getRetentionPercentage = () => {
    if (!retentionData) {
      // Fallback ke data dashboard jika retention data belum ada
      const totalEmployees = parseInt(dashboardData?.total_employees) || 0;
      const activeEmployees = parseInt(dashboardData?.active_employees) || 0;
      return totalEmployees > 0 ? Math.round((activeEmployees / totalEmployees) * 100) : 0;
    }
    
    const totalEmployees = parseInt(retentionData.total_employees) || 0;
    const activeEmployees = parseInt(retentionData.active_employees) || 0;
    
    return totalEmployees > 0 ? Math.round((activeEmployees / totalEmployees) * 100) : 0;
  };

  const getPeriodLabel = (period) => {
    switch(period) {
      case '1month': return '1 Bulan Terakhir';
      case '3months': return '3 Bulan Terakhir';
      case '6months': return '6 Bulan Terakhir';
      case '1year': return '1 Tahun Terakhir';
      default: return 'Keseluruhan';
    }
  };

  const getContractChartData = () => {
    if (!dashboardData?.contract_distribution || dashboardData.contract_distribution.length === 0) return null;

    const labels = dashboardData.contract_distribution.map(item => {
      switch (item.type) {
        case 'probation': 
        case 'Probation': return 'Probation';
        case '1': return 'Kontrak 1';
        case '2': return 'Kontrak 2';
        case '3': return 'Kontrak 3';
        case 'permanent': 
        case 'Permanent': return 'Permanent';
        default: return item.type;
      }
    });

    const data = dashboardData.contract_distribution.map(item => parseInt(item.count) || 0);

    return {
      labels,
      datasets: [
        {
          label: 'Jumlah Karyawan',
          data,
          backgroundColor: [
            '#ffc107',  // probation - warning
            '#007bff',  // contract 1 - primary
            '#28a745',  // contract 2 - success
            '#6c757d',  // contract 3 - secondary
            '#17a2b8',  // permanent - info
          ],
          borderColor: [
            '#ffc107',
            '#007bff',
            '#28a745',
            '#6c757d',
            '#17a2b8',
          ],
          borderWidth: 1,
        },
      ],
    };
  };

  const getEducationChartData = () => {
    if (!dashboardData?.education_distribution || dashboardData.education_distribution.length === 0) return null;

    const labels = dashboardData.education_distribution.map(item => item.education_level);
    const data = dashboardData.education_distribution.map(item => parseInt(item.count) || 0);

    return {
      labels,
      datasets: [
        {
          data,
          backgroundColor: [
            '#FF6384',
            '#36A2EB',
            '#FFCE56',
            '#4BC0C0',
            '#9966FF',
            '#FF9F40'
          ],
          hoverBackgroundColor: [
            '#FF6384',
            '#36A2EB',
            '#FFCE56',
            '#4BC0C0',
            '#9966FF',
            '#FF9F40'
          ],
        },
      ],
    };
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
      },
      title: {
        display: true,
        text: 'Distribusi Kontrak Karyawan',
      },
    },
  };

  const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
      },
      title: {
        display: true,
        text: 'Distribusi Pendidikan',
      },
    },
  };

  if (loading) {
    return (
      <div className="container-fluid py-4">
        <div className="d-flex justify-content-center align-items-center" style={{ minHeight: '400px' }}>
          <div className="text-center">
            <div className="spinner-border text-primary mb-3" style={{ width: '3rem', height: '3rem' }} role="status">
              <span className="visually-hidden">Loading...</span>
            </div>
            <p className="text-muted">Memuat dashboard...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="container-fluid py-4">
        <div className="alert alert-danger" role="alert">
          <div className="d-flex justify-content-between align-items-center">
            <div>
          <i className="fas fa-exclamation-triangle me-2"></i>
          {error}
            </div>
            <button 
              className="btn btn-outline-danger btn-sm"
              onClick={() => fetchDashboardData()}
            >
              <i className="fas fa-sync-alt me-1"></i>
              Coba Lagi
            </button>
          </div>
        </div>
        
        {/* Fallback data display */}
        <div className="row">
          <div className="col-12">
            <div className="card">
              <div className="card-body text-center">
                <i className="fas fa-chart-line fa-3x text-muted mb-3"></i>
                <h5 className="text-muted">Dashboard HR</h5>
                <p className="text-muted">
                  Data dashboard tidak tersedia saat ini. 
                  Silakan refresh halaman atau hubungi administrator sistem.
                </p>
                <div className="alert alert-info mt-3">
                  <strong>Info:</strong> Jika Anda baru membuka sistem, pastikan Anda sudah login terlebih dahulu.
                  <br />
                  <small>Sistem akan otomatis mengarahkan ke halaman login jika session expired.</small>
                </div>
                <button 
                  className="btn btn-primary me-2"
                  onClick={() => window.location.reload()}
                >
                  <i className="fas fa-refresh me-1"></i>
                  Refresh Halaman
                </button>
                <button 
                  className="btn btn-outline-secondary"
                  onClick={() => window.location.href = '/login'}
                >
                  <i className="fas fa-sign-in-alt me-1"></i>
                  Login Ulang
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="container-fluid py-4">
      {/* Welcome Section */}
      <div className="row mb-4">
        <div className="col-12">
          <div className="dashboard-card">
            <div className="d-flex align-items-center">
              <div className="me-3">
                <i className="fas fa-chart-line fa-3x text-primary"></i>
              </div>
              <div>
                <h2 className="mb-1">Selamat Datang, {user.username}!</h2>
                <p className="text-muted mb-0">
                  Dashboard Sistem Rekomendasi Kontrak Karyawan
                </p>
                <small className="text-muted">
                  Role: <span className="badge bg-primary">{user.role.toUpperCase()}</span>
                  {user.division_name && (
                    <> | Divisi: <span className="badge bg-secondary ms-1">{user.division_name}</span></>
                  )}
                </small>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Statistics Cards - Compact Layout */}
      <div className="row mb-4 g-3">
        <div className="col-lg-3 col-md-6 col-sm-12">
          <div className="dashboard-card h-100">
            <div className="stat-card primary">
              <div className="stat-icon">
                <i className="fas fa-users fa-2x"></i>
              </div>
              <div className="stat-content">
                <div className="stat-number">{parseInt(dashboardData?.total_employees) || 0}</div>
                <div className="stat-label">Total Karyawan</div>
                <div className="stat-description">Seluruh karyawan di sistem</div>
              </div>
            </div>
          </div>
        </div>
        
        <div className="col-lg-3 col-md-6 col-sm-12">
          <div className="dashboard-card h-100">
            <div className="stat-card success">
              <div className="stat-icon">
                <i className="fas fa-user-check fa-2x"></i>
              </div>
              <div className="stat-content">
                <div className="stat-number">{parseInt(dashboardData?.active_employees) || 0}</div>
                <div className="stat-label">Karyawan Aktif</div>
                <div className="stat-description">Karyawan yang masih bekerja</div>
              </div>
            </div>
          </div>
        </div>
        
        <div className="col-lg-3 col-md-6 col-sm-12">
          <div className="dashboard-card h-100">
            <div className="stat-card danger">
              <div className="stat-icon">
                <i className="fas fa-user-times fa-2x"></i>
              </div>
              <div className="stat-content">
                <div className="stat-number">{parseInt(dashboardData?.resigned_employees) || 0}</div>
                <div className="stat-label">Karyawan Resign</div>
                <div className="stat-description">Karyawan yang sudah keluar</div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-3 col-md-6 col-sm-12">
          <div className="dashboard-card h-100">
            <div className="stat-card info" style={{background: 'linear-gradient(135deg, #17a2b8 0%, #138496 100)', color: 'white'}}>
              <div className="stat-icon">
                <i className="fas fa-chart-line-down fa-2x"></i>
              </div>
              <div className="stat-content">
                <div className="stat-number">
                  {parseInt(dashboardData?.total_employees) > 0 
                    ? Math.round((parseInt(dashboardData.resigned_employees) / parseInt(dashboardData.total_employees)) * 100)
                    : 0}%
                </div>
                <div className="stat-label">Tingkat Turnover</div>
                <div className="stat-description">Persentase karyawan resign</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Second Row - Additional Stats */}
      <div className="row mb-4 g-3 analytics-cards-row">
        <div className="col-lg-6 col-md-6 col-sm-12">
          <div className="dashboard-card h-100">
            <div className="retention-filter-card">
              <div className="card-header-simple">
                <div className="d-flex align-items-center mb-3">
                  <i className="fas fa-chart-line me-2 text-warning" style={{fontSize: '1.5rem'}}></i>
                  <h5 className="mb-0 text-dark">Tingkat Retensi</h5>
                </div>
              </div>
              <div className="card-body-simple">
                <div className="retention-display mb-3">
                  <div className="retention-percentage">
                    <span className="percentage-number">{getRetentionPercentage()}</span>
                    <span className="percentage-symbol">%</span>
                  </div>
                  <div className="retention-subtitle">
                    Per {getPeriodLabel(retentionPeriod)}
                  </div>
                </div>
                <div className="filter-section">
                  <label className="form-label small text-muted mb-2">Filter Periode:</label>
                  <select 
                    className="form-select form-select-sm" 
                    value={retentionPeriod}
                    onChange={(e) => setRetentionPeriod(e.target.value)}
                    style={{
                      fontSize: '0.875rem',
                      borderRadius: '8px',
                      border: '1px solid #dee2e6',
                      padding: '8px 12px'
                    }}
                  >
                    <option value="all">Keseluruhan</option>
                    <option value="1month">1 Bulan Terakhir</option>
                    <option value="3months">3 Bulan Terakhir</option>
                    <option value="6months">6 Bulan Terakhir</option>
                    <option value="1year">1 Tahun Terakhir</option>
                  </select>
                </div>
                {retentionData && (
                  <div className="retention-details mt-3">
                    <div className="row text-center">
                      <div className="col-6">
                        <div className="detail-item">
                          <div className="detail-number text-success">{retentionData.active_employees || 0}</div>
                          <div className="detail-label">Aktif</div>
                        </div>
                      </div>
                      <div className="col-6">
                        <div className="detail-item">
                          <div className="detail-number text-muted">{retentionData.total_employees || 0}</div>
                          <div className="detail-label">Total</div>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-6 col-md-6 col-sm-12">
          <div className="dashboard-card h-100">
            <div className="turnover-filter-card">
              <div className="card-header-simple">
                <div className="d-flex align-items-center mb-3">
                  <i className="fas fa-chart-area me-2 text-danger" style={{fontSize: '1.5rem'}}></i>
                  <h5 className="mb-0 text-dark">Tingkat Turnover</h5>
                </div>
              </div>
              <div className="card-body-simple">
                <div className="turnover-display mb-3">
                  <div className="turnover-percentage">
                    <span className="percentage-number">{getTurnoverPercentage()}</span>
                    <span className="percentage-symbol">%</span>
                  </div>
                  <div className="turnover-subtitle">
                    Per {getPeriodLabel(turnoverPeriod)}
                  </div>
                </div>
                <div className="filter-section">
                  <label className="form-label small text-muted mb-2">Filter Periode:</label>
                  <select 
                    className="form-select form-select-sm" 
                    value={turnoverPeriod}
                    onChange={(e) => setTurnoverPeriod(e.target.value)}
                    style={{
                      fontSize: '0.875rem',
                      borderRadius: '8px',
                      border: '1px solid #dee2e6',
                      padding: '8px 12px'
                    }}
                  >
                    <option value="all">Keseluruhan</option>
                    <option value="1month">1 Bulan Terakhir</option>
                    <option value="3months">3 Bulan Terakhir</option>
                    <option value="6months">6 Bulan Terakhir</option>
                    <option value="1year">1 Tahun Terakhir</option>
                  </select>
                </div>
                {turnoverData && (
                  <div className="turnover-details mt-3">
                    <div className="row text-center">
                      <div className="col-6">
                        <div className="detail-item">
                          <div className="detail-number text-success">{turnoverData.active_employees || 0}</div>
                          <div className="detail-label">Aktif</div>
                        </div>
                      </div>
                      <div className="col-6">
                        <div className="detail-item">
                          <div className="detail-number text-danger">{turnoverData.resigned_employees || 0}</div>
                          <div className="detail-label">Resign</div>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Charts Row */}
      <div className="row mb-4 g-3">
        <div className="col-lg-8 col-md-12">
          <div className="dashboard-card h-100">
            <h4 className="mb-3">
              <i className="fas fa-chart-bar me-2"></i>
              Distribusi Kontrak Karyawan
            </h4>
            <div className="chart-container">
              {getContractChartData() ? (
                <Bar data={getContractChartData()} options={chartOptions} />
              ) : (
                <div className="d-flex justify-content-center align-items-center h-100">
                  <p className="text-muted">Tidak ada data kontrak</p>
                </div>
              )}
            </div>
          </div>
        </div>
        
        <div className="col-lg-4 col-md-12">
          <div className="dashboard-card h-100">
            <h4 className="mb-3">
              <i className="fas fa-chart-pie me-2"></i>
              Distribusi Pendidikan
            </h4>
            <div className="chart-container">
              {getEducationChartData() ? (
                <Doughnut data={getEducationChartData()} options={doughnutOptions} />
              ) : (
                <div className="d-flex justify-content-center align-items-center h-100">
                  <p className="text-muted">Tidak ada data pendidikan</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Division Distribution (Only for Admin/HR) */}
      {(user.role === 'admin' || user.role === 'hr') && dashboardData?.division_distribution && (
        <div className="row mb-4">
          <div className="col-12">
            <div className="dashboard-card">
              <h4 className="mb-3">
                <i className="fas fa-building me-2"></i>
                Distribusi Divisi
              </h4>
              <div className="row">
                {dashboardData.division_distribution.map((division, index) => (
                  <div key={index} className="col-md-3 mb-3">
                    <div className="card border-0 bg-light">
                      <div className="card-body text-center">
                        <h5 className="card-title text-primary">{division.count}</h5>
                        <p className="card-text text-muted small">{division.division_name}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Quick Actions */}
      <div className="row">
        <div className="col-12">
          <div className="dashboard-card">
            <h4 className="mb-3">
              <i className="fas fa-bolt me-2"></i>
              Aksi Cepat
            </h4>
            <div className="row g-3">
              {(user.role === 'hr' || user.role === 'admin') && (
                <div className="col-lg-4 col-md-6">
                  <div className="card border-primary h-100">
                    <div className="card-body text-center d-flex flex-column">
                      <i className="fas fa-user-plus fa-2x text-primary mb-3"></i>
                      <h6 className="card-title">Tambah Karyawan</h6>
                      <p className="card-text small text-muted flex-grow-1">
                        Daftarkan karyawan baru ke sistem
                      </p>
                      <a href="/employees" className="btn btn-primary btn-sm mt-auto">
                        <i className="fas fa-plus me-1"></i>
                        Tambah
                      </a>
                    </div>
                  </div>
                </div>
              )}
              
              <div className="col-lg-4 col-md-6">
                <div className="card border-success h-100">
                  <div className="card-body text-center d-flex flex-column">
                    <i className="fas fa-chart-bar fa-2x text-success mb-3"></i>
                    <h6 className="card-title">Lihat Rekomendasi</h6>
                    <p className="card-text small text-muted flex-grow-1">
                      Cek rekomendasi kontrak sistem
                    </p>
                    <a href="/recommendations" className="btn btn-success btn-sm mt-auto">
                      <i className="fas fa-eye me-1"></i>
                      Lihat
                    </a>
                  </div>
                </div>
              </div>
              
              {user.role === 'admin' && (
                <div className="col-lg-4 col-md-6">
                  <div className="card border-warning h-100">
                    <div className="card-body text-center d-flex flex-column">
                      <i className="fas fa-users-cog fa-2x text-warning mb-3"></i>
                      <h6 className="card-title">Kelola User</h6>
                      <p className="card-text small text-muted flex-grow-1">
                        Tambah dan kelola user sistem
                      </p>
                      <a href="/users" className="btn btn-warning btn-sm mt-auto">
                        <i className="fas fa-cog me-1"></i>
                        Kelola
                      </a>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard; 