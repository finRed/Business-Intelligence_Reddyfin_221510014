import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../App';

const Divisions = () => {
    const { user } = useAuth();
    const [divisions, setDivisions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showAddModal, setShowAddModal] = useState(false);
    const [newDivision, setNewDivision] = useState({
        name: '',
        description: ''
    });

    useEffect(() => {
        fetchDivisions();
    }, []);

    const fetchDivisions = async () => {
        try {
            const response = await axios.get('/divisions.php');
            if (response.data.success) {
                setDivisions(response.data.data);
            }
        } catch (error) {
            console.error('Error fetching divisions:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleAddDivision = async (e) => {
        e.preventDefault();
        try {
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('name', newDivision.name);
            formData.append('description', newDivision.description);

            const response = await axios.post('/divisions.php', formData);
            if (response.data.success) {
                alert('Divisi berhasil ditambahkan!');
                setShowAddModal(false);
                setNewDivision({ name: '', description: '' });
                fetchDivisions();
            } else {
                alert('Error: ' + response.data.error);
            }
        } catch (error) {
            console.error('Error adding division:', error);
            alert('Terjadi kesalahan saat menambah divisi');
        }
    };

    const handleDeleteDivision = async (id) => {
        if (!window.confirm('Apakah Anda yakin ingin menghapus divisi ini?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            const response = await axios.post('/divisions.php', formData);
            if (response.data.success) {
                alert('Divisi berhasil dihapus!');
                fetchDivisions();
            } else {
                alert('Error: ' + response.data.error);
            }
        } catch (error) {
            console.error('Error deleting division:', error);
            alert('Terjadi kesalahan saat menghapus divisi');
        }
    };

    if (loading) {
        return (
            <div className="loading-spinner">
                <div className="spinner"></div>
            </div>
        );
    }

    // Redirect if not admin
    if (user.role !== 'admin') {
        return (
            <div className="main-container">
                <div className="alert alert-warning">
                    Anda tidak memiliki akses ke halaman ini
                </div>
            </div>
        );
    }

    return (
        <div className="main-container">
            {/* Header */}
            <div className="d-flex justify-content-between align-items-center mb-4">
                <h1>Manajemen Divisi</h1>
                <button 
                    className="btn btn-primary"
                    onClick={() => setShowAddModal(true)}
                >
                    + Tambah Divisi
                </button>
            </div>

            {/* Divisions List */}
            <div className="row">
                {divisions.length > 0 ? (
                    divisions.map(division => (
                        <div key={division.id} className="col-md-6 col-lg-4 mb-4">
                            <div className="user-card">
                                <div className="user-logo">
                                    <i className="fas fa-building"></i>
                                </div>
                                <div className="user-info flex-grow-1">
                                    <h4 className="user-title">{division.name}</h4>
                                    <p className="user-location">{division.description || 'No description'}</p>
                                    <p className="user-salary">
                                        <small className="text-muted">
                                            {division.employee_count || 0} karyawan
                                        </small>
                                    </p>
                                </div>
                                <div className="user-actions">
                                    <button 
                                        className="btn btn-danger btn-sm"
                                        onClick={() => handleDeleteDivision(division.id)}
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="col-12">
                        <div className="text-center py-5">
                            <p className="text-muted">Tidak ada divisi tersedia</p>
                        </div>
                    </div>
                )}
            </div>

            {/* Add Division Modal */}
            {showAddModal && (
                <div className="modal fade show" style={{ display: 'block' }} tabIndex="-1">
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Tambah Divisi Baru</h5>
                                <button 
                                    type="button" 
                                    className="btn-close"
                                    onClick={() => setShowAddModal(false)}
                                ></button>
                            </div>
                            <form onSubmit={handleAddDivision}>
                                <div className="modal-body">
                                    <div className="form-group">
                                        <label>Nama Divisi</label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            value={newDivision.name}
                                            onChange={(e) => setNewDivision({...newDivision, name: e.target.value})}
                                            required
                                        />
                                    </div>
                                    
                                    <div className="form-group">
                                        <label>Deskripsi</label>
                                        <textarea
                                            className="form-control"
                                            rows="3"
                                            value={newDivision.description}
                                            onChange={(e) => setNewDivision({...newDivision, description: e.target.value})}
                                        ></textarea>
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
                                        Tambah
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

export default Divisions; 