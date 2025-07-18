import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';
import { useAuth } from '../App';

const EmployeeDetail = () => {
    const { eid } = useParams();
    const navigate = useNavigate();
    const { user } = useAuth();
    const [employee, setEmployee] = useState(null);
    const [contracts, setContracts] = useState([]);
    const [recommendations, setRecommendations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showResignModal, setShowResignModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [resignDate, setResignDate] = useState('');
    const [resignReason, setResignReason] = useState('');
    const [editFormData, setEditFormData] = useState({});

    useEffect(() => {
        fetchEmployeeData();
    }, [eid]);

    // Debug effect to monitor modal state changes (can be removed in production)
    // useEffect(() => {
    //     console.log('🔥 showResignModal state changed to:', showResignModal);
    // }, [showResignModal]);

    // useEffect(() => {
    //     console.log('🔥 showEditModal state changed to:', showEditModal);
    // }, [showEditModal]);

    useEffect(() => {
        if (employee) {
            setEditFormData({
                name: employee.name || '',
                email: employee.email || '',
                phone: employee.phone || '',
                birth_date: employee.birth_date || '',
                address: employee.address || '',
                education_level: employee.education_level || '',
                major: employee.major || '',
                role: employee.role || ''
            });
        }
    }, [employee]);

    const fetchEmployeeData = async () => {
        try {
            setLoading(true);
            const response = await axios.get(`/employees.php?action=detail&eid=${eid}`, {
                withCredentials: true
            });

            if (response.data.success) {
                setEmployee(response.data.employee);
                setContracts(response.data.contracts || []);
                setRecommendations(response.data.recommendations || []);
            } else {
                console.error('Error fetching employee data:', response.data.error);
            }
        } catch (error) {
            console.error('Error fetching employee data:', error);
        } finally {
            setLoading(false);
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('id-ID');
    };

    const getContractTypeColor = (type) => {
        switch (type) {
            case 'Probation': return 'warning';
            case 'Kontrak 1': return 'primary';
            case 'Kontrak 2': return 'info';
            case 'Kontrak 3': return 'secondary';
            case 'Permanent': return 'success';
            default: return 'secondary';
        }
    };

    const getStatusBadge = (status) => {
        switch (status) {
            case 'active':
                return 'status-active';
            case 'probation':
                return 'status-probation';
            case 'resigned':
            case 'terminated':
                return 'status-inactive';
            default:
                return 'status-inactive';
        }
    };

    const getStatusText = (status) => {
        switch (status) {
            case 'active':
                return 'Aktif';
            case 'probation':
                return 'Probation';
            case 'resigned':
                return 'Resign';
            case 'terminated':
                return 'Terminated';
            default:
                return 'Tidak Aktif';
        }
    };

    const getActualContractStatus = (contract) => {
        // Untuk kontrak permanent, selalu active
        if (!contract.end_date || contract.type === 'permanent' || contract.type === 'Permanent') {
            return 'active'; // Permanent contract
        }
        
        const today = new Date();
        const endDate = new Date(contract.end_date);
        
        if (endDate < today) {
            return 'expired'; // Contract has ended
        } else if (contract.status === 'active') {
            return 'active'; // Still active
        } else {
            return contract.status; // Use database status
        }
    };

    const getContractStatusText = (actualStatus) => {
        switch (actualStatus) {
            case 'active':
                return 'Aktif';
            case 'expired':
                return 'Berakhir';
            case 'completed':
                return 'Selesai';
            case 'terminated':
                return 'Dibatalkan';
            default:
                return actualStatus;
        }
    };

    const getContractStatusColor = (actualStatus) => {
        switch (actualStatus) {
            case 'active':
                return 'success';
            case 'expired':
                return 'danger';
            case 'completed':
                return 'secondary';
            case 'terminated':
                return 'warning';
            default:
                return 'secondary';
        }
    };

    const getCapitalizedContractType = (contractType) => {
        if (!contractType) return 'N/A';
        
        const typeMap = {
            'probation': 'Probation',
            'Probation': 'Probation',
            '1': 'Kontrak 1',
            'kontrak1': 'Kontrak 1',
            'Kontrak 1': 'Kontrak 1',
            '2': 'Kontrak 2',
            'kontrak2': 'Kontrak 2',
            'Kontrak 2': 'Kontrak 2',
            '3': 'Kontrak 3',
            'kontrak3': 'Kontrak 3',
            'Kontrak 3': 'Kontrak 3',
            'permanent': 'Permanent',
            'Permanent': 'Permanent'
        };

        return typeMap[contractType] || contractType.charAt(0).toUpperCase() + contractType.slice(1).toLowerCase();
    };

    const handleResign = async () => {
        if (!resignDate) {
            alert('Tanggal resign harus diisi');
            return;
        }

        try {
            const response = await axios.put('/employees.php', {
                eid: employee.eid,
                status: 'resigned',
                resign_date: resignDate,
                resign_reason: resignReason
            }, {
                withCredentials: true
            });

            if (response.data.success) {
                alert('Karyawan berhasil di-resign');
                setShowResignModal(false);
                fetchEmployeeData(); // Refresh data
            } else {
                alert('Error: ' + response.data.error);
            }
        } catch (error) {
            console.error('Error updating employee:', error);
            alert('Terjadi kesalahan saat melakukan resign');
        }
    };

    const handleEditSubmit = async (e) => {
        e.preventDefault();
        
        try {
            const response = await axios.put('/employees.php', {
                eid: employee.eid,
                ...editFormData
            }, {
                withCredentials: true
            });

            if (response.data.success) {
                alert('Data karyawan berhasil diupdate');
                setShowEditModal(false);
                fetchEmployeeData(); // Refresh data
            } else {
                alert('Error: ' + response.data.error);
            }
        } catch (error) {
            console.error('Error updating employee:', error);
            alert('Terjadi kesalahan saat mengupdate data');
        }
    };

    const handleEditInputChange = (e) => {
        const { name, value } = e.target;
        setEditFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    if (loading) {
        return (
            <div className="loading-spinner">
                <div className="spinner"></div>
            </div>
        );
    }

    if (!employee) {
        return (
            <div className="main-container">
                <div className="alert alert-warning">
                    Karyawan tidak ditemukan
                </div>
            </div>
        );
    }

    const handleBackNavigation = () => {
        // Smart back navigation based on user role
        if (user?.role === 'manager') {
            navigate('/dashboard'); // Manager dashboard
        } else {
            navigate('/employees'); // HR employees list
        }
    };

    return (
        <div className="main-container">
            {/* Header dengan tombol aksi */}
            <div className="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <button 
                        className="btn btn-outline-secondary me-3" 
                        onClick={handleBackNavigation}
                    >
                        ← Kembali
                    </button>
                    <h1 className="d-inline">Detail Karyawan</h1>
                </div>
                <div className="action-buttons">
                    {(user?.role === 'hr' || user?.role === 'admin') && (
                        <button 
                            className="btn-action btn-probation me-2"
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                setShowEditModal(true);
                            }}
                            disabled={employee.status === 'resigned' || employee.status === 'terminated'}
                        >
                            Edit Profile
                        </button>
                    )}
                    {user?.role === 'manager' && (
                        <button 
                            className="btn btn-success me-2"
                            onClick={() => navigate(`/manager/recommendation/${employee.eid}`)}
                            disabled={employee.current_contract_type === 'permanent'}
                        >
                            Buat Rekomendasi
                        </button>
                    )}
                </div>
            </div>

            {/* Three Column Layout */}
            <div className="three-column-layout">
                
                {/* Left Column - Employee Profile */}
                <div className="employee-profile-card">
                    <div className="employee-info">
                        <img 
                            src={`https://via.placeholder.com/80x80/007bff/white?text=${employee.name?.charAt(0) || 'N'}`}
                            alt={employee.name}
                            className="employee-avatar"
                        />
                        
                        <div className="info-item">
                            <strong>Nama Lengkap</strong>
                            <div className="info-value">{employee.name || 'N/A'}</div>
                        </div>
                        
                        <div className="info-item">
                            <strong>Tanggal Lahir</strong>
                            <div className="info-value">{formatDate(employee.birth_date)}</div>
                        </div>
                        
                        <div className="info-item">
                            <strong>EID</strong>
                            <div className="info-value">{employee.eid || 'N/A'}</div>
                        </div>
                        
                        <div className="info-item">
                            <strong>No HP</strong>
                            <div className="info-value">{employee.phone || 'N/A'}</div>
                        </div>
                        
                        <div className="info-item">
                            <strong>Divisi</strong>
                            <div className="info-value" style={{ 
                                backgroundColor: '#e3f2fd', 
                                padding: '6px 12px', 
                                borderRadius: '4px',
                                border: '1px solid #1976d2',
                                color: '#1976d2',
                                fontWeight: '500'
                            }}>
                                {employee.division_name || 'N/A'}
                            </div>
                        </div>
                    </div>

                    <div className="cv-section">
                        <div className="cv-placeholder">
                            CV
                        </div>
                    </div>
                </div>

                {/* Center Column - Data Karyawan */}
                <div className="employee-data-card">
                    <div className="card-header">
                        <h2>Data Karyawan</h2>
                    </div>

                    <div className="data-field">
                        <strong>Nama Department:</strong>
                        <div className="field-value">{employee.division_name || 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Alamat:</strong>
                        <div className="field-value">{employee.address || 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Tingkat Pendidikan:</strong>
                        <div className="field-value">{employee.education_level || 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Tanggal Join:</strong>
                        <div className="field-value">{formatDate(employee.join_date)}</div>
                    </div>

                    <div className="data-field">
                        <strong>Role:</strong>
                        <div className="field-value">{employee.role || 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Status Match:</strong>
                        <div className="field-value">
                            <span className={`match-status-badge ${employee.education_job_match === 'Match' ? 'match-badge' : 'unmatch-badge'}`}>
                                {employee.education_job_match === 'Match' ? '✅ Match' : '❌ Unmatch'}
                            </span>
                            {(employee.education_job_match === 'Unmatch' || !employee.education_job_match) && (
                                <div className="match-explanation mt-2">
                                    <small>🔍 <strong>Data Intelligence:</strong> Pendidikan non-IT dengan pekerjaan IT = Unmatch</small>
                                </div>
                            )}
                            {employee.education_job_match === 'Match' && (
                                <div className="match-explanation mt-2">
                                    <small>🎯 <strong>Data Intelligence:</strong> Pendidikan IT dengan pekerjaan IT = Match</small>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Right Column - Detail Probation (hanya informasi) */}
                <div className="recommendation-card">
                    <div className="card-header">
                        <h2>Detail Probation</h2>
                    </div>

                    <div className="probation-info">
                        <div className="info-item">
                            <strong>Tanggal Masuk Probation:</strong>
                            <div className="info-value">{formatDate(employee.join_date) || 'N/A'}</div>
                        </div>
                        
                        <div className="info-item">
                            <strong>Tanggal Selesai Probation:</strong>
                            <div className="info-value">
                                {employee.join_date ? (() => {
                                    const joinDate = new Date(employee.join_date);
                                    const probationEndDate = new Date(joinDate);
                                    probationEndDate.setMonth(probationEndDate.getMonth() + 3);
                                    return probationEndDate.toLocaleDateString('id-ID');
                                })() : 'N/A'}
                            </div>
                        </div>
                        
                        <div className="info-item">
                            <strong>Status:</strong>
                            <div className="info-value">
                                <span className={`status-badge-modern ${getStatusBadge(employee.status)}`}>
                                    {getStatusText(employee.status)}
                                </span>
                            </div>
                        </div>
                        
                        <div className="info-item">
                            <strong>Pendidikan:</strong>
                            <div className="info-value" style={{ 
                                backgroundColor: '#f8f9fa', 
                                padding: '8px', 
                                borderRadius: '4px',
                                border: '1px solid #dee2e6'
                            }}>
                                {(() => {
                                    const educationParts = [];
                                    if (employee.education_level && employee.education_level.trim() !== '') {
                                        educationParts.push(employee.education_level.trim());
                                    }
                                    if (employee.major && employee.major.trim() !== '') {
                                        educationParts.push(employee.major.trim());
                                    }
                                    
                                    return educationParts.length > 0 ? educationParts.join(' - ') : 'Data pendidikan tidak tersedia';
                                })()}
                            </div>
                        </div>
                    </div>

                    {/* Bagian probation actions dengan tombol Resign */}
                    <div className="probation-actions">
                        <div className="action-buttons mt-3">
                            <button 
                                className="btn-action btn-resign"
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setShowResignModal(true);
                                }}
                                disabled={employee.status === 'resigned' || employee.status === 'terminated'}
                            >
                                Resign
                            </button>
                            </div>
                    </div>
                </div>
            </div>

            {/* Detail Kontrak Section */}
            {contracts && contracts.length > 0 && (
                <div className="contract-details-section mt-4">
                    <div className="card">
                        <div className="card-header" style={{ backgroundColor: '#007bff', color: 'white' }}>
                            <h3 className="mb-0">📄 Detail Kontrak</h3>
                        </div>
                        <div className="card-body">
                            <div className="row">
                                {contracts.map((contract, index) => {
                                    const actualStatus = getActualContractStatus(contract);
                                    return (
                                    <div key={contract.contract_id} className="col-md-6 mb-3">
                                        <div className={`contract-card ${actualStatus === 'active' ? 'active-contract' : ''}`} style={{
                                            border: actualStatus === 'active' ? '2px solid #28a745' : actualStatus === 'expired' ? '2px solid #dc3545' : '1px solid #dee2e6',
                                            borderRadius: '8px',
                                            padding: '15px',
                                            backgroundColor: actualStatus === 'active' ? '#f8fff9' : actualStatus === 'expired' ? '#fff5f5' : '#f8f9fa',
                                            marginBottom: '10px'
                                        }}>
                                            <div className="contract-header">
                                                <h5 className="contract-title">
                                                    {getCapitalizedContractType(contract.type)}
                                                    <span className={`badge ms-2 bg-${getContractStatusColor(actualStatus)}`}>
                                                        {getContractStatusText(actualStatus)}
                                                    </span>
                                                </h5>
                                            </div>
                                            <div className="contract-details">
                                                <div className="contract-info-section">
                                                    <div className="detail-row">
                                                    <strong>Periode:</strong>
                                                    <span>{formatDate(contract.start_date)} - {formatDate(contract.end_date)}</span>
                                                </div>
                                                    <div className="detail-row">
                                                    <strong>Durasi:</strong>
                                                    <span>
                                                        {(() => {
                                                                // Untuk kontrak permanent, tampilkan "Permanent"
                                                                if (!contract.end_date || contract.type === 'permanent' || contract.type === 'Permanent') {
                                                                    return 'Permanent';
                                                                }
                                                                
                                                            const start = new Date(contract.start_date);
                                                            const end = new Date(contract.end_date);
                                                            const months = Math.round((end - start) / (1000 * 60 * 60 * 24 * 30));
                                                            return `${months} bulan`;
                                                        })()}
                                                    </span>
                                                </div>
                                                {contract.notes && (
                                                        <div className="detail-row">
                                                        <strong>Catatan:</strong>
                                                        <span className="text-muted">{contract.notes}</span>
                                                    </div>
                                                )}
                                                    {actualStatus === 'active' && contract.end_date && contract.type !== 'permanent' && contract.type !== 'Permanent' && (
                                                        <div className="detail-row">
                                                        <strong>Sisa Waktu:</strong>
                                                        <span className="text-warning">
                                                            {(() => {
                                                                const today = new Date();
                                                                const endDate = new Date(contract.end_date);
                                                                const diffTime = endDate - today;
                                                                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                                                                
                                                                if (diffDays > 0) {
                                                                    return `${diffDays} hari lagi`;
                                                                } else if (diffDays === 0) {
                                                                    return 'Berakhir hari ini';
                                                                } else {
                                                                    return 'Sudah berakhir';
                                                                }
                                                            })()}
                                                        </span>
                                                    </div>
                                                )}
                                                    {actualStatus === 'active' && (!contract.end_date || contract.type === 'permanent' || contract.type === 'Permanent') && (
                                                        <div className="detail-row">
                                                            <strong>Status:</strong>
                                                            <span className="text-success" style={{ color: '#28a745', fontWeight: '600' }}>
                                                                Kontrak Permanent
                                                            </span>
                                                        </div>
                                                    )}
                                                    {(contract.type === 'probation' || contract.type === 'Probation') && (
                                                        <div className="detail-row">
                                                            <strong>Status:</strong>
                                                            <span className={(() => {
                                                                const today = new Date();
                                                                const endDate = new Date(contract.end_date);
                                                                const isExpired = endDate < today;
                                                                return isExpired ? "text-danger" : "text-warning";
                                                            })()} style={{ 
                                                                color: (() => {
                                                                    const today = new Date();
                                                                    const endDate = new Date(contract.end_date);
                                                                    const isExpired = endDate < today;
                                                                    return isExpired ? '#dc3545' : '#f39c12';
                                                                })(), 
                                                                fontWeight: '600' 
                                                            }}>
                                                                {(() => {
                                                                    const today = new Date();
                                                                    const endDate = new Date(contract.end_date);
                                                                    const isExpired = endDate < today;
                                                                    return isExpired ? 'Sudah Berakhir' : 'Sedang Berjalan';
                                                                })()}
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Riwayat Rekomendasi Section */}
            {recommendations && recommendations.length > 0 && (
                <div className="recommendations-section mt-4">
                    <div className="card">
                        <div className="card-header" style={{ backgroundColor: '#28a745', color: 'white' }}>
                            <h3 className="mb-0">📋 Riwayat Rekomendasi Manager</h3>
                        </div>
                        <div className="card-body">
                            <div className="table-responsive">
                                <table className="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jenis</th>
                                            <th>Durasi</th>
                                            <th>Status</th>
                                            <th>Manager</th>
                                            <th>Diproses</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recommendations.map((rec, index) => (
                                            <tr key={rec.recommendation_id}>
                                                <td>{formatDate(rec.created_at)}</td>
                                                <td>
                                                    <span className="badge bg-info">
                                                        {rec.recommendation_type === 'extend' ? 'Perpanjang' : 
                                                         rec.recommendation_type === 'permanent' ? 'Permanent' : 
                                                         rec.recommendation_type}
                                                    </span>
                                                </td>
                                                <td>{rec.recommended_duration} bulan</td>
                                                <td>
                                                    <span className={`badge ${
                                                        rec.status === 'approved' ? 'bg-success' : 
                                                        rec.status === 'extended' ? 'bg-success' : 
                                                        rec.status === 'pending' ? 'bg-warning' : 'bg-danger'
                                                    }`}>
                                                        {rec.status === 'approved' ? '✅ Disetujui' : 
                                                         rec.status === 'extended' ? '✅ Diperpanjang' : 
                                                         rec.status === 'pending' ? '⏳ Pending' : '❌ Ditolak'}
                                                    </span>
                                                </td>
                                                <td>{rec.recommended_by_name || 'N/A'}</td>
                                                <td>{rec.updated_at ? formatDate(rec.updated_at) : '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Resign */}
            {showResignModal && (
                <div className="modal show" style={{ display: 'block', backgroundColor: 'rgba(0,0,0,0.5)' }} onClick={(e) => {
                    if (e.target.classList.contains('modal')) {
                        setShowResignModal(false);
                    }
                }}>
                    <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Resign Karyawan</h5>
                                <button 
                                    type="button" 
                                    className="btn-close"
                                    onClick={() => setShowResignModal(false)}
                                    style={{ 
                                        background: 'none', 
                                        border: 'none', 
                                        fontSize: '24px', 
                                        cursor: 'pointer',
                                        color: '#6c757d'
                                    }}
                                >
                                    ×
                                </button>
                            </div>
                            <div className="modal-body">
                                <p><strong>Karyawan:</strong> {employee.name}</p>
                                <div className="form-group mb-3">
                                    <label className="form-label">Tanggal Resign <span className="text-danger">*</span></label>
                                    <input 
                                        type="date"
                                        className="form-control"
                                        value={resignDate}
                                        onChange={(e) => setResignDate(e.target.value)}
                                        required
                                    />
                                </div>
                                <div className="form-group mb-3">
                                    <label className="form-label">Alasan Resign</label>
                                    <textarea
                                        className="form-control"
                                        rows="3"
                                        value={resignReason}
                                        onChange={(e) => setResignReason(e.target.value)}
                                        placeholder="Masukkan alasan resign (opsional)"
                                    />
                                </div>
                            </div>
                            <div className="modal-footer">
                                <button 
                                    type="button" 
                                    className="btn btn-secondary"
                                    onClick={() => setShowResignModal(false)}
                                >
                                    Batal
                                </button>
                                <button 
                                    type="button" 
                                    className="btn-action btn-resign"
                                    onClick={handleResign}
                                >
                                    Konfirmasi Resign
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Edit Profile */}
            {showEditModal && (
                <div className="modal show" style={{ display: 'block', backgroundColor: 'rgba(0,0,0,0.5)' }} onClick={(e) => {
                    if (e.target.classList.contains('modal')) {
                        setShowEditModal(false);
                    }
                }}>
                    <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Edit Profile Karyawan</h5>
                                <button 
                                    type="button" 
                                    className="btn-close"
                                    onClick={() => setShowEditModal(false)}
                                    style={{ 
                                        background: 'none', 
                                        border: 'none', 
                                        fontSize: '24px', 
                                        cursor: 'pointer',
                                        color: '#6c757d'
                                    }}
                                >
                                    ×
                                </button>
                            </div>
                            <form onSubmit={handleEditSubmit}>
                                <div className="modal-body">
                                    <div className="row">
                                        <div className="col-md-6">
                                            <div className="form-group mb-3">
                                                <label className="form-label">Nama Lengkap <span className="text-danger">*</span></label>
                                                <input
                                                    type="text"
                                                    name="name"
                                                    className="form-control"
                                                    value={editFormData.name}
                                                    onChange={handleEditInputChange}
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div className="col-md-6">
                                            <div className="form-group mb-3">
                                                <label className="form-label">Email <span className="text-danger">*</span></label>
                                                <input
                                                    type="email"
                                                    name="email"
                                                    className="form-control"
                                                    value={editFormData.email}
                                                    onChange={handleEditInputChange}
                                                    required
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="row">
                                        <div className="col-md-6">
                                            <div className="form-group mb-3">
                                                <label className="form-label">No HP</label>
                                                <input
                                                    type="text"
                                                    name="phone"
                                                    className="form-control"
                                                    value={editFormData.phone}
                                                    onChange={handleEditInputChange}
                                                />
                                            </div>
                                        </div>
                                        <div className="col-md-6">
                                            <div className="form-group mb-3">
                                                <label className="form-label">Tanggal Lahir</label>
                                                <input
                                                    type="date"
                                                    name="birth_date"
                                                    className="form-control"
                                                    value={editFormData.birth_date}
                                                    onChange={handleEditInputChange}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="form-group mb-3">
                                        <label className="form-label">Alamat</label>
                                        <textarea
                                            name="address"
                                            className="form-control"
                                            rows="2"
                                            value={editFormData.address}
                                            onChange={handleEditInputChange}
                                        />
                                    </div>
                                    <div className="row">
                                        <div className="col-md-6">
                                            <div className="form-group mb-3">
                                                <label className="form-label">Tingkat Pendidikan <span className="text-danger">*</span></label>
                                                <select
                                                    name="education_level"
                                                    className="form-control"
                                                    value={editFormData.education_level}
                                                    onChange={handleEditInputChange}
                                                    required
                                                >
                                                    <option value="">Pilih Tingkat Pendidikan</option>
                                                    <option value="D3">D3</option>
                                                    <option value="S1">S1</option>
                                                    <option value="S2">S2</option>
                                                    <option value="S3">S3</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div className="col-md-6">
                                            <div className="form-group mb-3">
                                                <label className="form-label">Jurusan <span className="text-danger">*</span></label>
                                                <input
                                                    type="text"
                                                    name="major"
                                                    className="form-control"
                                                    value={editFormData.major}
                                                    onChange={handleEditInputChange}
                                                    required
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="form-group mb-3">
                                        <label className="form-label">Role <span className="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            name="role"
                                            className="form-control"
                                            value={editFormData.role}
                                            onChange={handleEditInputChange}
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="modal-footer">
                                    <button 
                                        type="button" 
                                        className="btn btn-secondary"
                                        onClick={() => setShowEditModal(false)}
                                    >
                                        Batal
                                    </button>
                                    <button 
                                        type="submit" 
                                        className="btn-action btn-aktif"
                                    >
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* Footer */}
            <div className="footer">
                <div className="footer-links">
                                          <a href="#">Tentang BAT Contract</a>
                      <a href="#">Hubungi Kami</a>
                      <a href="#">Panduan Sistem</a>
                      <a href="#">Bantuan & Support</a>
                </div>
                <p>©2025 BAT Contract System. Sistem Manajemen Kontrak Karyawan.</p>
            </div>
        </div>
    );
};

export default EmployeeDetail; 