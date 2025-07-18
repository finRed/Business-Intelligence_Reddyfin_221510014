import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import axios from 'axios';
import { useAuth } from '../App';

const ContractRecommendation = () => {
    const { eid } = useParams();
    const navigate = useNavigate();
    const { user } = useAuth();
    const [employee, setEmployee] = useState(null);
    const [loading, setLoading] = useState(true);
    const [selectedContract, setSelectedContract] = useState(null);

    useEffect(() => {
        fetchEmployeeData();
    }, [eid]);

    const fetchEmployeeData = async () => {
        try {
            const response = await axios.get(`/employees.php?action=detail&eid=${eid}`, {
                withCredentials: true
            });
            
            if (response.data.success) {
                setEmployee(response.data.employee);
            } else {
                console.error('Failed to fetch employee:', response.data.message);
            }
        } catch (error) {
            console.error('Error fetching employee:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleContractSelection = (contractType, duration) => {
        setSelectedContract({
            type: contractType,
            duration: duration
        });
    };

    const handleSendRecommendation = async () => {
        if (!selectedContract) {
            alert('Mohon pilih jenis kontrak terlebih dahulu');
            return;
        }

        try {
            const response = await axios.post('/employees.php', {
                action: 'recommend',
                eid: employee.eid,
                recommendation_type: 'extend',
                recommended_contract: selectedContract.type,
                recommended_duration: selectedContract.duration,
                reason: `Rekomendasi ${selectedContract.type} berdasarkan evaluasi probation`
            }, {
                withCredentials: true
            });

            if (response.data.success) {
                alert(`Rekomendasi ${selectedContract.type} berhasil dibuat untuk ${employee.name}`);
                // Navigate back to appropriate dashboard based on user role
                if (user?.role === 'manager') {
                    navigate('/dashboard');
                } else {
                    navigate('/dashboard');
                }
            } else {
                alert(response.data.message || 'Gagal membuat rekomendasi');
            }
        } catch (error) {
            console.error('Error creating recommendation:', error);
            alert('Terjadi kesalahan saat membuat rekomendasi');
        }
    };

    const handleBackNavigation = () => {
        // Smart back navigation based on user role
        if (user?.role === 'manager') {
            // For manager, go back to manager employee detail or dashboard
            navigate(`/manager/employee/${eid}`);
        } else {
            // For HR, go to employee detail
            navigate(`/employee/${eid}`);
        }
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

    return (
        <div className="main-container">
            {/* Header */}
            <div className="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <button 
                        className="btn btn-outline-secondary me-3" 
                        onClick={handleBackNavigation}
                    >
                        ← Kembali
                    </button>
                    <h1 className="d-inline">Rekomendasi Kontrak</h1>
                </div>
                <div className="action-buttons">
                    <span className="badge bg-info">
                        {user?.role === 'manager' ? 'Manager' : 'HR'} - {employee.name}
                    </span>
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
                            <div className="info-value">{employee.birth_date ? new Date(employee.birth_date).toLocaleDateString('id-ID') : 'N/A'}</div>
                        </div>
                        
                        <div className="info-item">
                            <strong>EID</strong>
                            <div className="info-value">{employee.eid || 'N/A'}</div>
                        </div>
                        
                        <div className="info-item">
                            <strong>No HP</strong>
                            <div className="info-value">{employee.phone || 'N/A'}</div>
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
                        <strong>Jurusan:</strong>
                        <div className="field-value">{employee.major || 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Tingkat Pendidikan:</strong>
                        <div className="field-value">{employee.education_level || 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Tanggal Join:</strong>
                        <div className="field-value">{employee.join_date ? new Date(employee.join_date).toLocaleDateString('id-ID') : 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Tanggal Mulai Kontrak:</strong>
                        <div className="field-value">{employee.join_date ? new Date(employee.join_date).toLocaleDateString('id-ID') : 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Tanggal Selesai Kontrak:</strong>
                        <div className="field-value">{employee.contract_end_date ? new Date(employee.contract_end_date).toLocaleDateString('id-ID') : 'N/A'}</div>
                    </div>

                    <div className="data-field">
                        <strong>Role:</strong>
                        <div className="field-value">{employee.role || 'N/A'}</div>
                    </div>
                </div>

                {/* Right Column - Kontrak Recommendation */}
                <div className="recommendation-card">
                    <div className="card-header">
                        <h2>Kontrak Recommendation</h2>
                    </div>

                    <div className="contract-recommendations">
                        <div 
                            className={`contract-option ${selectedContract?.type === 'Kontrak 1' ? 'selected' : ''}`}
                            onClick={() => handleContractSelection('Kontrak 1', 6)}
                        >
                            <div className="contract-label">Contract 1: 6 Bulan</div>
                        </div>

                        <div 
                            className={`contract-option ${selectedContract?.type === 'Kontrak 2' ? 'selected' : ''}`}
                            onClick={() => handleContractSelection('Kontrak 2', 12)}
                        >
                            <div className="contract-label">Contract 2: 1 Tahun</div>
                        </div>

                        <div 
                            className={`contract-option ${selectedContract?.type === 'Kontrak 3' ? 'selected' : ''}`}
                            onClick={() => handleContractSelection('Kontrak 3', 24)}
                        >
                            <div className="contract-label">Contract 3: 2 Tahun</div>
                        </div>

                        <div 
                            className={`contract-option ${selectedContract?.type === 'Permanent' ? 'selected' : ''}`}
                            onClick={() => handleContractSelection('Permanent', null)}
                        >
                            <div className="contract-label">Contract Permanent: Permanent</div>
                        </div>
                    </div>

                    <div className="recommendation-actions">
                        <button 
                            className="btn-send-blue"
                            onClick={handleSendRecommendation}
                            disabled={!selectedContract}
                        >
                            Send
                        </button>
                    </div>
                </div>
            </div>

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

export default ContractRecommendation; 