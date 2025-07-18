import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../App';

const Contracts = () => {
  const [contracts, setContracts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const { user } = useAuth();

  useEffect(() => {
    fetchContracts();
  }, []);

  const fetchContracts = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/contracts.php');
      
      if (response.data.success) {
        setContracts(response.data.data);
      } else {
        setError('Gagal memuat data kontrak');
      }
    } catch (error) {
      setError('Terjadi kesalahan saat memuat data');
      console.error('Contracts error:', error);
    } finally {
      setLoading(false);
    }
  };

  const getContractBadge = (type) => {
    const badges = {
      'probation': <span className="badge bg-warning">Probation</span>,
      '1': <span className="badge bg-primary">Kontrak 1</span>,
      '2': <span className="badge bg-success">Kontrak 2</span>,
      '3': <span className="badge bg-info">Kontrak 3</span>,
      'permanent': <span className="badge bg-dark">Permanent</span>
    };
    
    return badges[type] || <span className="badge bg-secondary">{type}</span>;
  };

  const getStatusBadge = (status) => {
    const badges = {
      'active': <span className="badge bg-success">Aktif</span>,
      'completed': <span className="badge bg-info">Selesai</span>,
      'terminated': <span className="badge bg-danger">Diberhentikan</span>,
      'extended': <span className="badge bg-warning">Diperpanjang</span>
    };
    
    return badges[status] || <span className="badge bg-secondary">{status}</span>;
  };

  if (loading) {
    return (
      <div className="container-fluid py-4">
        <div className="d-flex justify-content-center align-items-center" style={{ minHeight: '400px' }}>
          <div className="text-center">
            <div className="spinner-border text-primary mb-3" style={{ width: '3rem', height: '3rem' }} role="status">
              <span className="visually-hidden">Loading...</span>
            </div>
            <p className="text-muted">Memuat data kontrak...</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="container-fluid py-4">
      {/* Header */}
      <div className="row mb-4">
        <div className="col-12">
          <div className="d-flex justify-content-between align-items-center">
            <div>
              <h2 className="mb-1">
                <i className="fas fa-file-contract me-2 text-primary"></i>
                Kontrak
              </h2>
              <p className="text-muted mb-0">Kelola kontrak karyawan</p>
            </div>
          </div>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          <i className="fas fa-exclamation-triangle me-2"></i>
          {error}
        </div>
      )}

      {/* Contracts Table */}
      <div className="dashboard-card">
        <div className="table-responsive">
          <table className="table">
            <thead>
              <tr>
                <th>Karyawan</th>
                <th>Role</th>
                <th>Divisi</th>
                <th>Tipe Kontrak</th>
                <th>Tanggal Mulai</th>
                <th>Tanggal Berakhir</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {contracts.length === 0 ? (
                <tr>
                  <td colSpan="8" className="text-center py-4">
                    <i className="fas fa-file-contract fa-3x text-muted mb-3 d-block"></i>
                    <p className="text-muted">Tidak ada data kontrak</p>
                  </td>
                </tr>
              ) : (
                contracts.map((contract) => (
                  <tr key={contract.contract_id}>
                    <td>
                      <div className="d-flex align-items-center">
                        <div className="employee-avatar me-2" style={{ width: '40px', height: '40px', fontSize: '1rem' }}>
                          {contract.employee_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <div className="fw-semibold">{contract.employee_name}</div>
                          <small className="text-muted">{contract.role}</small>
                        </div>
                      </div>
                    </td>
                    <td>{contract.role}</td>
                    <td>{contract.division_name || '-'}</td>
                    <td>{getContractBadge(contract.type)}</td>
                    <td>{new Date(contract.start_date).toLocaleDateString('id-ID')}</td>
                    <td>
                      {contract.end_date ? 
                        new Date(contract.end_date).toLocaleDateString('id-ID') : 
                        'Tidak ada batas'
                      }
                    </td>
                    <td>{getStatusBadge(contract.status)}</td>
                    <td>
                      <div className="btn-group" role="group">
                        <button className="btn btn-outline-primary btn-sm">
                          <i className="fas fa-eye"></i>
                        </button>
                        {(user.role === 'hr' || user.role === 'admin') && (
                          <button className="btn btn-outline-warning btn-sm">
                            <i className="fas fa-edit"></i>
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default Contracts; 