import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';
import { useAuth } from '../App';

const Employees = () => {
    const { user } = useAuth();
    const [employees, setEmployees] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showAddModal, setShowAddModal] = useState(false);
    const [newEmployee, setNewEmployee] = useState({
        name: '',
        email: '',
        phone: '',
        birth_date: '',
        address: '',
        education_level: '',
        major: '',
        role: '',
        division_id: '',
        join_date: '', // Ini akan menjadi probation start date
        probation_end_date: '',
        is_active_probation: true,
        contract_type: '',
        contract_start_date: '',
        contract_end_date: '',
        is_contract_active: false
    });
    const [divisions, setDivisions] = useState([]);

    useEffect(() => {
        fetchEmployees();
        fetchDivisions();
    }, []);

    // Debug effect to monitor showAddModal state changes (can be removed in production)
    // useEffect(() => {
    //     console.log('🔥 showAddModal state changed to:', showAddModal);
    // }, [showAddModal]);

    // Debug user state (can be removed in production)
    // console.log('🔍 Employees component - User state:', user);
    // console.log('🔍 User role:', user?.role);
    // console.log('🔍 Can add employee:', user?.role === 'admin' || user?.role === 'hr');

    const fetchEmployees = async () => {
        try {
            const response = await axios.get('/employees.php');
            if (response.data.success) {
                setEmployees(response.data.data);
                
                // Debug logging
                console.log('📊 Employee Debug Info:');
                console.log('Total employees:', response.data.data.length);
                
                response.data.data.forEach(emp => {
                    const status = emp.display_status || emp.status;
                    console.log(`${emp.name}: DB=${emp.status}, Display=${emp.display_status}, Contract=${emp.current_contract_type}, Final=${status}`);
                });
                
                const aktif = response.data.data.filter(emp => emp.current_contract_type && emp.current_contract_type !== 'probation' && (emp.display_status || emp.status) !== 'resigned' && (emp.display_status || emp.status) !== 'terminated').length;
                const probation = response.data.data.filter(emp => emp.current_contract_type === 'probation').length;
                const resign = response.data.data.filter(emp => (emp.display_status || emp.status) === 'resigned' || (emp.display_status || emp.status) === 'terminated').length;
                const lainnya = response.data.data.filter(emp => {
                    const status = emp.display_status || emp.status;
                    const isResigned = status === 'resigned' || status === 'terminated';
                    const hasContract = emp.current_contract_type && emp.current_contract_type !== '';
                    return !isResigned && !hasContract;
                }).length;
                
                console.log(`Breakdown: Aktif=${aktif}, Probation=${probation}, Resign=${resign}, Lainnya=${lainnya}, Total=${aktif+probation+resign+lainnya}`);
            }
        } catch (error) {
            console.error('Error fetching employees:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchDivisions = async () => {
        try {
            const response = await axios.get('/divisions.php');
            console.log('Divisions response:', response.data); // Debug log
            if (response.data.success) {
                setDivisions(response.data.data);
            }
        } catch (error) {
            console.error('Error fetching divisions:', error);
        }
    };

    const handleAddEmployee = async (e) => {
        e.preventDefault();
        try {
            console.log('Adding employee with data:', newEmployee);
            
            const formData = new FormData();
            formData.append('action', 'create');
            Object.keys(newEmployee).forEach(key => {
                formData.append(key, newEmployee[key]);
                console.log(`FormData: ${key} = ${newEmployee[key]}`);
            });

            console.log('Sending request to /employees.php');
            const response = await axios.post('/employees.php', formData);
            console.log('Response received:', response.data);
            
            if (response.data.success) {
                alert('Karyawan berhasil ditambahkan!');
                setShowAddModal(false);
                setNewEmployee({
                    name: '',
                    email: '',
                    phone: '',
                    birth_date: '',
                    address: '',
                    education_level: '',
                    major: '',
                    role: '',
                    division_id: '',
                    join_date: '',
                    probation_end_date: '',
                    is_active_probation: true,
                    contract_type: '',
                    contract_start_date: '',
                    contract_end_date: '',
                    is_contract_active: false
                });
                fetchEmployees();
            } else {
                console.error('Server error:', response.data);
                alert('Error: ' + (response.data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error adding employee:', error);
            console.error('Error response:', error.response?.data);
            alert('Terjadi kesalahan saat menambah karyawan: ' + (error.response?.data?.error || error.message));
        }
    };

    const getStatusBadge = (status) => {
        switch (status) {
            case 'Active':
                return 'status-active';
            case 'Probation':
                return 'status-probation';
            default:
                return 'status-inactive';
        }
    };

    const getStatusText = (status) => {
        switch (status) {
            case 'Active':
                return 'Aktif';
            case 'Probation':
                return 'Probation';
            case 'Inactive':
                return 'Resign';
            default:
                return 'Tidak Aktif';
        }
    };

    const getStatusColor = (status) => {
        switch (status) {
            case 'active':
                return 'status-active-color';
            case 'probation':
                return 'status-probation-color';
            case 'resigned':
            case 'terminated':
                return 'status-inactive-color';
            default:
                return 'status-inactive-color';
        }
    };

    const getContractStatusClass = (contractType) => {
        if (!contractType) return 'status-inactive';
        
        switch (contractType) {
            case 'probation':
                return 'status-probation';
            case '1':
            case 'kontrak1':
            case 'Kontrak 1':
                return 'status-kontrak-1';
            case '2':
            case 'kontrak2':
            case 'Kontrak 2':
                return 'status-kontrak-2';
            case '3':
            case 'kontrak3':
            case 'Kontrak 3':
                return 'status-kontrak-3';
            case 'permanent':
            case 'Permanent':
                return 'status-permanent';
            default:
                return 'status-inactive';
        }
    };

    const getContractStatusText = (contractType) => {
        if (!contractType) return 'TIDAK ADA KONTRAK';
        
        switch (contractType) {
            case 'probation':
                return '⏳ PROBATION';
            case '1':
            case 'kontrak1':
            case 'Kontrak 1':
                return '1️⃣ KONTRAK 1';
            case '2':
            case 'kontrak2':
            case 'Kontrak 2':
                return '2️⃣ KONTRAK 2';
            case '3':
            case 'kontrak3':
            case 'Kontrak 3':
                return '3️⃣ KONTRAK 3';
            case 'permanent':
            case 'Permanent':
                return '🔒 PERMANENT';
            default:
                return contractType.toUpperCase();
        }
    };

    const getContractTypeBadge = (contractType) => {
        if (!contractType) return null;
        
        const contractMap = {
            'probation': { text: 'Probation', class: 'contract-probation', icon: '⏳' },
            'Probation': { text: 'Probation', class: 'contract-probation', icon: '⏳' },
            'kontrak1': { text: 'Kontrak 1', class: 'contract-1', icon: '1️⃣' },
            'Kontrak 1': { text: 'Kontrak 1', class: 'contract-1', icon: '1️⃣' },
            '1': { text: 'Kontrak 1', class: 'contract-1', icon: '1️⃣' },
            'kontrak2': { text: 'Kontrak 2', class: 'contract-2', icon: '2️⃣' },
            'Kontrak 2': { text: 'Kontrak 2', class: 'contract-2', icon: '2️⃣' },
            '2': { text: 'Kontrak 2', class: 'contract-2', icon: '2️⃣' },
            'kontrak3': { text: 'Kontrak 3', class: 'contract-3', icon: '3️⃣' },
            'Kontrak 3': { text: 'Kontrak 3', class: 'contract-3', icon: '3️⃣' },
            '3': { text: 'Kontrak 3', class: 'contract-3', icon: '3️⃣' },
            'permanent': { text: 'Permanent', class: 'contract-permanent', icon: '🔒' },
            'Permanent': { text: 'Permanent', class: 'contract-permanent', icon: '🔒' }
        };

        const contract = contractMap[contractType];
        if (!contract) {
            // Fallback untuk kontrak type yang tidak dikenal - kapitalisasi otomatis
            const capitalizedType = contractType.charAt(0).toUpperCase() + contractType.slice(1).toLowerCase();
            return (
                <span className="contract-type-badge contract-unknown">
                    📄 {capitalizedType}
                </span>
            );
        }

        return (
            <span className={`contract-type-badge ${contract.class}`}>
                {contract.icon} {contract.text}
            </span>
        );
    };

    if (loading) {
        return (
            <div className="loading-spinner">
                <div className="spinner"></div>
            </div>
        );
    }

    return (
        <div className="main-container">
            {/* Header */}
            <div className="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 className="page-title">
                        {user?.role === 'manager' 
                            ? `Karyawan Divisi ${user.division_name || 'Anda'}: ${employees.length}`
                            : `Total Karyawan: ${employees.length}`
                        }
                    </h1>
                    <div className="total-breakdown">
                        <small className="text-muted">
                            Aktif: {employees.filter(emp => (emp.display_status || emp.status) === 'active').length} | 
                                            Probation: {employees.filter(emp => emp.current_contract_type === 'probation').length} |
                            Resign: {employees.filter(emp => (emp.display_status || emp.status) === 'resigned' || (emp.display_status || emp.status) === 'terminated').length} | 
                            Lainnya: {employees.filter(emp => {
                                const status = emp.display_status || emp.status;
                    const isResigned = status === 'resigned' || status === 'terminated';
                    const hasContract = emp.current_contract_type && emp.current_contract_type !== '';
                    return !isResigned && !hasContract;
                            }).length}
                            {user?.role === 'manager' && (
                                <> | <span className="badge bg-primary ms-1">Divisi {user.division_name}</span></>
                            )}
                        </small>
                    </div>
                </div>
                                {(user?.role === 'admin' || user?.role === 'hr') && (
                    <button 
                        className="btn btn-primary btn-add-employee"
                        onClick={() => {
                            setShowAddModal(true);
                        }}
                        style={{ 
                            cursor: 'pointer',
                            pointerEvents: 'auto',
                            position: 'relative',
                            zIndex: 1
                        }}
                    >
                        <i className="fas fa-plus"></i> Tambah Karyawan
                    </button>
                )}
            </div>

            {/* Employee Cards Section */}
            <div className="employee-section">
                <div className="employee-list-horizontal">
                    {/* Add Employee Card */}
                    {(user.role === 'admin' || user.role === 'hr') && (
                        <div className="add-employee-card-section" onClick={() => setShowAddModal(true)}>
                            <div className="add-icon">
                                <i className="fas fa-plus"></i>
                            </div>
                        </div>
                    )}

                    {/* Active & Probation Employees */}
                {employees.filter(emp => (emp.display_status || emp.status) !== 'resigned' && (emp.display_status || emp.status) !== 'terminated').map(employee => (
                        <div key={employee.eid} className="employee-card-horizontal">
                            <div className="employee-content-wrapper">
                                <div className="employee-top-section">
                                    <div className="employee-avatar-container">
                                        <img 
                                            src={`https://ui-avatars.com/api/?name=${employee.name}&background=random&size=60`}
                                            alt={employee.name}
                                            className="employee-avatar-horizontal"
                                        />
                                    </div>
                                    <div className="employee-info-horizontal">
                                        <h4 className="employee-name-horizontal">{employee.name}</h4>
                                        <p className="employee-subtitle-horizontal">{employee.age ? `${employee.age} Tahun` : 'N/A Tahun'}</p>
                                        <p className="employee-email-horizontal">{employee.email}</p>
                                        {/* Contract Type Indicator */}
                                        {employee.current_contract_type && (
                                            <div className="contract-type-indicator">
                                                {getContractTypeBadge(employee.current_contract_type)}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <div className="employee-status-indicator">
                                    <div className="status-badge-container">
                                        <span className={`status-badge-kontrak ${getContractStatusClass(employee.current_contract_type)}`}>
                                            {getContractStatusText(employee.current_contract_type)}
                                        </span>
                                    </div>
                                    <Link 
                                        to={`/employees/${employee.eid}`}
                                        className="btn-details-horizontal"
                                    >
                                        Details
                                    </Link>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Karyawan Resign Section */}
            {employees.filter(emp => (emp.display_status || emp.status) === 'resigned' || (emp.display_status || emp.status) === 'terminated').length > 0 && (
                <div className="employee-section">
                    <h2 className="section-title-main">Karyawan Resign</h2>
                    <p className="section-subtitle-main">Total Karyawan {employees.filter(emp => (emp.display_status || emp.status) === 'resigned' || (emp.display_status || emp.status) === 'terminated').length}</p>
                    
                    <div className="employee-list-horizontal">
                        {employees.filter(emp => (emp.display_status || emp.status) === 'resigned' || (emp.display_status || emp.status) === 'terminated').map(employee => (
                            <div key={employee.eid} className="employee-card-horizontal resigned">
                                <div className="employee-content-wrapper">
                                    <div className="employee-top-section">
                                        <div className="employee-avatar-container">
                                            <img 
                                                src={`https://ui-avatars.com/api/?name=${employee.name}&background=random&size=60`}
                                                alt={employee.name}
                                                className="employee-avatar-horizontal"
                                            />
                                        </div>
                                        <div className="employee-info-horizontal">
                                            <h4 className="employee-name-horizontal">{employee.name}</h4>
                                            <p className="employee-subtitle-horizontal">{employee.age ? `${employee.age} Tahun` : 'N/A Tahun'}</p>
                                            <p className="employee-email-horizontal">{employee.email}</p>
                                        </div>
                                    </div>
                                    <div className="employee-status-indicator">
                                        <div className="status-badge-container">
                                            <span className={`status-badge-kontrak ${(employee.display_status || employee.status) === 'resigned' ? 'status-resign' : (employee.display_status || employee.status) === 'terminated' ? 'status-terminated' : 'status-inactive'}`}>
                                                {(employee.display_status || employee.status) === 'resigned' ? '❌ RESIGN' : (employee.display_status || employee.status) === 'terminated' ? '🚫 TERMINATED' : 'INACTIVE'}
                                            </span>
                                        </div>
                                        <Link 
                                            to={`/employees/${employee.eid}`}
                                            className="btn-details-horizontal"
                                        >
                                            Detail
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}



            {/* Empty State - jika tidak ada karyawan sama sekali */}
            {employees.length === 0 && (
                <div className="empty-state">
                    <div className="empty-icon">
                        <i className="fas fa-users fa-3x"></i>
                    </div>
                    <h3>Belum Ada Data Karyawan</h3>
                    <p className="text-muted">Mulai dengan menambahkan karyawan baru</p>
                    {(user?.role === 'admin' || user?.role === 'hr') && (
                        <button 
                            className="btn btn-primary"
                            onClick={() => {
                                setShowAddModal(true);
                            }}
                            style={{ 
                                cursor: 'pointer',
                                pointerEvents: 'auto',
                                position: 'relative',
                                zIndex: 1
                            }}
                        >
                            <i className="fas fa-plus"></i> Tambah Karyawan Pertama
                        </button>
                    )}
                </div>
            )}

            {/* Add Employee Modal */}
            {showAddModal && (
                <div className="modal fade show" style={{ 
                    display: 'block', 
                    backgroundColor: 'rgba(0,0,0,0.5)',
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    width: '100%',
                    height: '100%',
                    zIndex: 1050
                }} tabIndex="-1" onClick={(e) => {
                    if (e.target.classList.contains('modal')) {
                        setShowAddModal(false);
                    }
                }}>
                    <div className="modal-dialog modal-lg" style={{
                        margin: '1.75rem auto',
                        maxWidth: '800px'
                    }} onClick={(e) => e.stopPropagation()}>
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Tambah Karyawan Baru</h5>
                                <button 
                                    type="button" 
                                    className="btn-close"
                                    onClick={() => setShowAddModal(false)}
                                ></button>
                            </div>
                            <form onSubmit={handleAddEmployee}>
                                <div className="modal-body">
                                    <div className="row">
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                <label htmlFor="employee-name">Nama Lengkap</label>
                                                <input
                                                    type="text"
                                                    id="employee-name"
                                                    name="name"
                                                    className="form-control"
                                                    value={newEmployee.name}
                                                    onChange={(e) => setNewEmployee({...newEmployee, name: e.target.value})}
                                                    autoComplete="name"
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                <label htmlFor="employee-email">Email</label>
                                                <input
                                                    type="email"
                                                    id="employee-email"
                                                    name="email"
                                                    className="form-control"
                                                    value={newEmployee.email}
                                                    onChange={(e) => setNewEmployee({...newEmployee, email: e.target.value})}
                                                    autoComplete="email"
                                                    required
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div className="row">
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                <label htmlFor="employee-phone">No. HP</label>
                                                <input
                                                    type="tel"
                                                    id="employee-phone"
                                                    name="phone"
                                                    className="form-control"
                                                    value={newEmployee.phone}
                                                    onChange={(e) => setNewEmployee({...newEmployee, phone: e.target.value})}
                                                    autoComplete="tel"
                                                />
                                            </div>
                                        </div>
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                <label htmlFor="employee-birth-date">Tanggal Lahir</label>
                                                <input
                                                    type="date"
                                                    id="employee-birth-date"
                                                    name="birth_date"
                                                    className="form-control"
                                                    value={newEmployee.birth_date}
                                                    onChange={(e) => setNewEmployee({...newEmployee, birth_date: e.target.value})}
                                                    autoComplete="bday"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* Probation Section */}
                                    <div className="probation-section">
                                        <h6 className="text-primary mb-3">⏳ Informasi Probation</h6>

                                    <div className="row">
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                    <label htmlFor="employee-join-date">Tanggal Mulai Probation</label>
                                                <input
                                                    type="date"
                                                    id="employee-join-date"
                                                    name="join_date"
                                                    className="form-control"
                                                    value={newEmployee.join_date}
                                                    onChange={(e) => setNewEmployee({...newEmployee, join_date: e.target.value})}
                                                    autoComplete="off"
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                    <label htmlFor="activeProbation">Status Probation</label>
                                                    <div className="form-check mt-2">
                                                <input
                                                            type="checkbox"
                                                            id="activeProbation"
                                                            name="is_active_probation"
                                                            className="form-check-input"
                                                            checked={newEmployee.is_active_probation}
                                                            onChange={(e) => setNewEmployee({
                                                                ...newEmployee, 
                                                                is_active_probation: e.target.checked,
                                                                probation_end_date: e.target.checked ? '' : newEmployee.probation_end_date,
                                                                contract_type: e.target.checked ? 'probation' : newEmployee.contract_type
                                                            })}
                                                            autoComplete="off"
                                                        />
                                                        <label className="form-check-label" htmlFor="activeProbation">
                                                            Masih Aktif Probation
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {!newEmployee.is_active_probation && (
                                            <div className="row">
                                                <div className="col-md-6">
                                                    <div className="form-group">
                                                        <label htmlFor="employee-probation-end-date">Probation End Date</label>
                                                        <input
                                                            type="date"
                                                            id="employee-probation-end-date"
                                                            name="probation_end_date"
                                                            className="form-control"
                                                            value={newEmployee.probation_end_date}
                                                            onChange={(e) => setNewEmployee({...newEmployee, probation_end_date: e.target.value})}
                                                            autoComplete="off"
                                                            required={!newEmployee.is_active_probation}
                                                        />
                                                    </div>
                                                </div>
                                                <div className="col-md-6">
                                                    <div className="alert alert-info small mt-4">
                                                        <i className="fas fa-info-circle"></i>
                                                        Probation sudah selesai, silakan isi tanggal berakhir
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Contract Section - Muncul jika probation sudah berakhir */}
                                    {!newEmployee.is_active_probation && newEmployee.probation_end_date && (
                                        <div className="contract-section">
                                            <hr />
                                            <h6 className="text-primary mb-3">📋 Informasi Kontrak</h6>
                                            
                                            <div className="row">
                                                <div className="col-md-6">
                                                    <div className="form-group">
                                                        <label htmlFor="employee-contract-type">Tipe Kontrak Sekarang</label>
                                                        <select
                                                            id="employee-contract-type"
                                                            name="contract_type"
                                                            className="form-control"
                                                            value={newEmployee.contract_type}
                                                            onChange={(e) => setNewEmployee({
                                                                ...newEmployee, 
                                                                contract_type: e.target.value,
                                                                contract_end_date: (e.target.value === 'permanent') ? '' : newEmployee.contract_end_date,
                                                                is_contract_active: false
                                                            })}
                                                            autoComplete="off"
                                                            required={!newEmployee.is_active_probation && newEmployee.probation_end_date}
                                                        >
                                                            <option value="">Pilih Tipe Kontrak</option>
                                                            <option value="2">Kontrak 2</option>
                                                            <option value="3">Kontrak 3</option>
                                                            <option value="permanent">Permanent</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div className="col-md-6">
                                                    <div className="form-group">
                                                        <label htmlFor="employee-contract-start-date">Contract Start Date</label>
                                                        <input
                                                            type="date"
                                                            id="employee-contract-start-date"
                                                            name="contract_start_date"
                                                            className="form-control"
                                                            value={newEmployee.contract_start_date}
                                                            onChange={(e) => setNewEmployee({...newEmployee, contract_start_date: e.target.value})}
                                                            autoComplete="off"
                                                            required={!newEmployee.is_active_probation && newEmployee.probation_end_date}
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Contract Status - Untuk kontrak 2 dan 3 */}
                                            {(newEmployee.contract_type === '2' || newEmployee.contract_type === '3') && (
                                                <div className="row">
                                                    <div className="col-md-6">
                                                        <div className="form-group">
                                                            <label htmlFor="activeContract">Status Kontrak</label>
                                                            <div className="form-check mt-2">
                                                                <input
                                                                    type="checkbox"
                                                                    id="activeContract"
                                                                    name="is_contract_active"
                                                                    className="form-check-input"
                                                                    checked={newEmployee.is_contract_active}
                                                                    onChange={(e) => setNewEmployee({
                                                                        ...newEmployee, 
                                                                        is_contract_active: e.target.checked,
                                                                        contract_end_date: e.target.checked ? '' : newEmployee.contract_end_date
                                                                    })}
                                                                    autoComplete="off"
                                                                />
                                                                <label className="form-check-label" htmlFor="activeContract">
                                                                    Masih Aktif (belum habis)
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="col-md-6">
                                                        <div className="alert alert-info small mt-4">
                                                            <i className="fas fa-info-circle"></i>
                                                            Centang jika kontrak masih berjalan
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Contract End Date - Hanya muncul jika kontrak tidak aktif dan bukan permanent */}
                                            {newEmployee.contract_type && 
                                             newEmployee.contract_type !== 'permanent' && 
                                             !newEmployee.is_contract_active && (
                                                <div className="row">
                                                    <div className="col-md-6">
                                                        <div className="form-group">
                                                            <label htmlFor="employee-contract-end-date">Contract End Date</label>
                                                            <input
                                                                type="date"
                                                                id="employee-contract-end-date"
                                                                name="contract_end_date"
                                                                className="form-control"
                                                                value={newEmployee.contract_end_date}
                                                                onChange={(e) => setNewEmployee({...newEmployee, contract_end_date: e.target.value})}
                                                                autoComplete="off"
                                                                required={newEmployee.contract_type && newEmployee.contract_type !== 'permanent' && !newEmployee.is_contract_active}
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="col-md-6">
                                                        <div className="alert alert-warning small">
                                                            <i className="fas fa-exclamation-triangle"></i>
                                                            Kontrak {newEmployee.contract_type === '2' ? 'Kedua' : 'Ketiga'} sudah berakhir
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Active Contract Info */}
                                            {newEmployee.is_contract_active && newEmployee.contract_type && (
                                                <div className="row">
                                                    <div className="col-md-12">
                                                        <div className="alert alert-success">
                                                            <i className="fas fa-clock"></i>
                                                            <strong>Kontrak {newEmployee.contract_type === '2' ? 'Kedua' : 'Ketiga'} Masih Aktif</strong> - Tidak perlu tanggal berakhir
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Permanent Contract Info */}
                                            {newEmployee.contract_type === 'permanent' && (
                                                <div className="row">
                                                    <div className="col-md-12">
                                                        <div className="alert alert-success">
                                                            <i className="fas fa-check-circle"></i>
                                                            <strong>Kontrak Permanent</strong> - Tidak memerlukan tanggal berakhir
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    <div className="row">
                                        <div className="col-md-12">
                                            <div className="form-group">
                                                <label htmlFor="employee-address">Alamat</label>
                                                <input
                                                    type="text"
                                                    id="employee-address"
                                                    name="address"
                                                    className="form-control"
                                                    value={newEmployee.address}
                                                    onChange={(e) => setNewEmployee({...newEmployee, address: e.target.value})}
                                                    autoComplete="street-address"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="form-group">
                                        <label htmlFor="employee-education-level">Tingkat Pendidikan</label>
                                        <select
                                            id="employee-education-level"
                                            name="education_level"
                                            className="form-control"
                                            value={newEmployee.education_level}
                                            onChange={(e) => setNewEmployee({...newEmployee, education_level: e.target.value})}
                                            autoComplete="off"
                                            required
                                        >
                                            <option value="">Pilih Tingkat Pendidikan</option>
                                            <option value="SMA/K">SMA/K</option>
                                            <option value="D3">D3</option>
                                            <option value="S1">S1</option>
                                            <option value="S2">S2</option>
                                            <option value="S3">S3</option>
                                        </select>
                                    </div>

                                    <div className="row">
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                <label htmlFor="employee-major">Jurusan</label>
                                                <input
                                                    type="text"
                                                    id="employee-major"
                                                    name="major"
                                                    className="form-control"
                                                    value={newEmployee.major}
                                                    onChange={(e) => setNewEmployee({...newEmployee, major: e.target.value})}
                                                    autoComplete="off"
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                <label htmlFor="employee-role">Role</label>
                                                <input
                                                    type="text"
                                                    id="employee-role"
                                                    name="role"
                                                    className="form-control"
                                                    value={newEmployee.role}
                                                    onChange={(e) => setNewEmployee({...newEmployee, role: e.target.value})}
                                                    autoComplete="off"
                                                    required
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="row">
                                        <div className="col-md-6">
                                            <div className="form-group">
                                                <label htmlFor="employee-division">Divisi</label>
                                                <select
                                                    id="employee-division"
                                                    name="division_id"
                                                    className="form-control"
                                                    value={newEmployee.division_id}
                                                    onChange={(e) => setNewEmployee({...newEmployee, division_id: e.target.value})}
                                                    autoComplete="off"
                                                    required
                                                >
                                                    <option value="">Pilih Divisi</option>
                                                    {divisions.map(division => (
                                                        <option key={division.division_id} value={division.division_id}>
                                                            {division.division_name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="modal-footer">
                                    <button 
                                        type="button" 
                                        className="btn btn-secondary"
                                        onClick={() => setShowAddModal(false)}
                                    >
                                        Batal
                                    </button>
                                    <button type="submit" className="btn btn-primary">
                                        Tambah Karyawan
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

export default Employees; 
