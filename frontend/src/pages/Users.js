import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../App';

const Users = () => {
  const { user } = useAuth();
  const [users, setUsers] = useState([]);
  const [divisions, setDivisions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [showDivisionModal, setShowDivisionModal] = useState(false);
  const [showDivisionDetailModal, setShowDivisionDetailModal] = useState(false);
  const [selectedUser, setSelectedUser] = useState(null);
  const [newUser, setNewUser] = useState({
    username: '',
    password: '',
    email: '',
    role: 'hr',
    division_id: ''
  });
  const [editUser, setEditUser] = useState({
    user_id: '',
    username: '',
    email: '',
    role: '',
    division_id: '',
    status: 'active'
  });
  const [newDivision, setNewDivision] = useState({
    division_name: '',
    description: ''
  });

  useEffect(() => {
    fetchUsers();
    fetchDivisions();
  }, []);

  const fetchUsers = async () => {
    try {
      const response = await axios.get('/auth.php?action=users', {
        withCredentials: true
      });
      if (response.data.success && Array.isArray(response.data.data)) {
        setUsers(response.data.data);
      } else {
        console.error('Error response:', response.data.error);
        setUsers([]);
      }
    } catch (error) {
      console.error('Error fetching users:', error);
      setUsers([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchDivisions = async () => {
    try {
      const response = await axios.get('/divisions.php', {
        withCredentials: true
      });
      if (response.data.success && Array.isArray(response.data.data)) {
        setDivisions(response.data.data);
      } else {
        setDivisions([]);
      }
    } catch (error) {
      console.error('Error fetching divisions:', error);
      setDivisions([]);
    }
  };

  const handleAddUser = async (e) => {
    e.preventDefault();
    try {
      console.log('Adding user with data:', newUser);
      
      const formData = new FormData();
      formData.append('action', 'register');
      Object.keys(newUser).forEach(key => {
        console.log(`Appending ${key}: ${newUser[key]}`);
        formData.append(key, newUser[key]);
      });

      console.log('Sending request to backend...');
      const response = await axios.post('/auth.php', formData, {
        withCredentials: true
      });
      
      console.log('Backend response:', response.data);
      
      if (response.data.success) {
        alert('User berhasil ditambahkan!');
        setShowAddModal(false);
        setNewUser({
          username: '',
          password: '',
          email: '',
          role: 'hr',
          division_id: ''
        });
        fetchUsers();
      } else {
        console.error('Backend error:', response.data.error);
        alert('Error: ' + response.data.error);
      }
    } catch (error) {
      console.error('Error adding user:', error);
      console.error('Error response:', error.response?.data);
      if (error.response && error.response.data && error.response.data.error) {
        alert('Error: ' + error.response.data.error);
      } else {
        alert('Terjadi kesalahan saat menambah user: ' + error.message);
      }
    }
  };

  const handleEditUser = async (e) => {
    e.preventDefault();
    try {
      const formData = new FormData();
      formData.append('action', 'update_user');
      Object.keys(editUser).forEach(key => {
        formData.append(key, editUser[key]);
      });

      const response = await axios.post('/auth.php', formData, {
        withCredentials: true
      });
      if (response.data.success) {
        alert('User berhasil diupdate!');
        setShowEditModal(false);
        fetchUsers();
      } else {
        alert('Error: ' + response.data.error);
      }
    } catch (error) {
      console.error('Error updating user:', error);
      alert('Terjadi kesalahan saat mengupdate user');
    }
  };

  const handleDeleteUser = async (userId) => {
    if (window.confirm('Apakah Anda yakin ingin menghapus user ini?')) {
      try {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);

        const response = await axios.post('/auth.php', formData, {
          withCredentials: true
        });
        if (response.data.success) {
          alert('User berhasil dihapus!');
          fetchUsers();
        } else {
          alert('Error: ' + response.data.error);
        }
      } catch (error) {
        console.error('Error deleting user:', error);
        alert('Terjadi kesalahan saat menghapus user');
      }
    }
  };

  const handleAddDivision = async (e) => {
    e.preventDefault();
    try {
      const divisionData = {
        name: newDivision.division_name,
        description: newDivision.description
      };

      const response = await axios.post('/divisions.php', divisionData, {
        headers: {
          'Content-Type': 'application/json'
        },
        withCredentials: true
      });
      
      if (response.data.success) {
        // Show success message
        alert(`Divisi "${newDivision.division_name}" berhasil ditambahkan!`);
        
        // Close modal and reset form
        setShowDivisionModal(false);
        setNewDivision({
          division_name: '',
          description: ''
        });
        
        // Refresh divisions list to update all dropdowns
        await fetchDivisions();
        
        console.log('Divisi baru berhasil ditambahkan dan list divisi telah diperbarui');
      } else {
        alert('Error: ' + response.data.error);
      }
    } catch (error) {
      console.error('Error adding division:', error);
      if (error.response && error.response.data && error.response.data.error) {
        alert('Error: ' + error.response.data.error);
      } else {
        alert('Terjadi kesalahan saat menambah divisi');
      }
    }
  };

  const openDetailModal = (userItem) => {
    setSelectedUser(userItem);
    setShowDetailModal(true);
  };

  const openEditModal = (userItem) => {
    setEditUser({
      user_id: userItem.id,
      username: userItem.username,
      email: userItem.email,
      role: userItem.role,
      division_id: userItem.division_id || '',
      status: userItem.status
    });
    setShowEditModal(true);
  };

  const getRoleDisplay = (role) => {
    switch (role) {
      case 'admin':
        return 'Administrator';
      case 'hr':
        return 'Human Resource';
      case 'manager':
        return 'Manager';
      default:
        return role;
    }
  };

  const getRoleLocation = (userItem) => {
    if (userItem.role === 'admin') {
      return 'System Admin';
    }
    // For HR and Manager, show their actual division
    return getDivisionName(userItem.division_id);
  };

  const getDivisionName = (divisionId) => {
    if (!divisions || !Array.isArray(divisions) || divisions.length === 0) {
      return 'Loading...';
    }
    const division = divisions.find(d => d.division_id === divisionId);
    return division ? division.division_name : 'Tidak ada divisi';
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
        <div>
          <h1 className="mb-0">Users</h1>
          <small className="text-muted">Total Users {users.length}</small>
        </div>
        <div className="d-flex gap-2">
          <button 
            className="btn btn-info"
            style={{ 
              backgroundColor: '#17a2b8', 
              borderColor: '#17a2b8', 
              color: 'white',
              backgroundImage: 'none'
            }}
            onClick={() => setShowDivisionDetailModal(true)}
          >
            Detail Divisi
          </button>
          <button 
            className="btn btn-success"
            style={{ 
              backgroundColor: '#28a745', 
              borderColor: '#28a745', 
              color: 'white',
              backgroundImage: 'none'
            }}
            onClick={() => setShowDivisionModal(true)}
          >
            Tambah Divisi
          </button>
          <button 
            className="btn btn-primary"
            style={{ 
              backgroundColor: '#007bff', 
              borderColor: '#007bff', 
              color: 'white',
              backgroundImage: 'none'
            }}
            onClick={() => setShowAddModal(true)}
          >
            Tambah User
          </button>
        </div>
      </div>

      {/* Users Grid */}
      <div className="row">
        {users && users.length > 0 ? users.map(userItem => (
          <div key={userItem.id} className="col-md-6 mb-4">
            <div className="user-card">
              <div className="user-card-content">
                <div className="user-logo">
                  {userItem.role === 'hr' ? (
                    <i className="fas fa-users"></i>
                  ) : userItem.role === 'manager' ? (
                    <i className="fas fa-chart-line"></i>
                  ) : (
                    <i className="fas fa-user-shield"></i>
                  )}
                </div>
                <div className="user-info">
                  <h4 className="user-title">{getRoleDisplay(userItem.role)}</h4>
                  <p className="user-location mb-1">{getRoleLocation(userItem)}</p>
                </div>
              </div>
              <div className="user-actions">
                <button 
                  className="btn btn-outline-primary btn-sm"
                  onClick={() => openDetailModal(userItem)}
                >
                  Detail
                </button>
                {userItem.role !== 'admin' && (
                  <>
                    <button 
                      className="btn btn-outline-warning btn-sm"
                      onClick={() => openEditModal(userItem)}
                    >
                      Edit
                    </button>
                    <button 
                      className="btn btn-outline-danger btn-sm"
                      onClick={() => handleDeleteUser(userItem.id)}
                    >
                      Hapus
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        )) : (
          <div className="col-12">
            <div className="alert alert-info text-center">
              <i className="fas fa-info-circle me-2"></i>
              {users === null ? 'Memuat data users...' : 'Tidak ada users yang ditemukan'}
            </div>
          </div>
        )}

        {/* Add User Card */}
        <div className="col-md-6 mb-4">
          <div 
            className="user-card add-user-card" 
            onClick={() => setShowAddModal(true)}
            style={{ cursor: 'pointer' }}
          >
            <div className="user-card-content">
              <div className="add-user-icon">
                <i className="fas fa-plus"></i>
              </div>
              <div className="add-user-info">
                <h4 className="add-user-title">Tambah User Baru</h4>
                <p className="add-user-desc mb-0">
                  <span className="text-muted">Klik untuk menambah user</span>
                </p>
              </div>
              <div className="add-user-action">
                <i className="fas fa-chevron-right text-muted"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Detail Modal */}
      {showDetailModal && selectedUser && (
        <div className="modal active" tabIndex="-1" onClick={() => setShowDetailModal(false)}>
          <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Detail User</h5>
                <button 
                  type="button" 
                  className="btn-close"
                  onClick={() => setShowDetailModal(false)}
                ></button>
              </div>
              <div className="modal-body">
                <div className="row">
                  <div className="col-md-4 text-center">
                    <div className="user-avatar-large">
                      <i className="fas fa-user"></i>
                    </div>
                    <h4 className="mt-3">{selectedUser.username}</h4>
                    <span className={`status-badge ${selectedUser.status === 'active' ? 'status-active' : 'status-inactive'}`}>
                      {selectedUser.status === 'active' ? 'Aktif' : 'Tidak Aktif'}
                    </span>
                  </div>
                  <div className="col-md-8">
                    <table className="table table-borderless">
                      <tbody>
                        <tr>
                          <td><strong>User ID:</strong></td>
                          <td>{selectedUser.id}</td>
                        </tr>
                        <tr>
                          <td><strong>Username:</strong></td>
                          <td>{selectedUser.username}</td>
                        </tr>
                        <tr>
                          <td><strong>Email:</strong></td>
                          <td>{selectedUser.email}</td>
                        </tr>
                        <tr>
                          <td><strong>Role:</strong></td>
                          <td>{getRoleDisplay(selectedUser.role)}</td>
                        </tr>
                        <tr>
                          <td><strong>Divisi:</strong></td>
                          <td>{getDivisionName(selectedUser.division_id)}</td>
                        </tr>
                        <tr>
                          <td><strong>Tanggal Dibuat:</strong></td>
                          <td>{selectedUser.created_at ? new Date(selectedUser.created_at).toLocaleDateString('id-ID') : '-'}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <div className="modal-footer">
                <button 
                  type="button" 
                  className="btn btn-secondary"
                  onClick={() => setShowDetailModal(false)}
                >
                  Tutup
                </button>
                {selectedUser.role !== 'admin' && (
                  <>
                    <button 
                      type="button" 
                      className="btn btn-warning"
                      onClick={() => {
                        setShowDetailModal(false);
                        openEditModal(selectedUser);
                      }}
                    >
                      Edit
                    </button>
                    <button 
                      type="button" 
                      className="btn btn-danger"
                      onClick={() => {
                        setShowDetailModal(false);
                        handleDeleteUser(selectedUser.id);
                      }}
                    >
                      Hapus
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Add User Modal */}
      {showAddModal && (
        <div className="modal active" tabIndex="-1" onClick={() => setShowAddModal(false)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Tambah User Baru</h5>
                <button 
                  type="button" 
                  className="btn-close"
                  onClick={() => setShowAddModal(false)}
                ></button>
              </div>
              <form onSubmit={handleAddUser}>
                <div className="modal-body">
                  <div className="form-group mb-3">
                    <label className="form-label">Username</label>
                    <input
                      type="text"
                      className="form-control"
                      value={newUser.username}
                      onChange={(e) => setNewUser({...newUser, username: e.target.value})}
                      required
                    />
                  </div>
                  
                  <div className="form-group mb-3">
                    <label className="form-label">Email</label>
                    <input
                      type="email"
                      className="form-control"
                      value={newUser.email}
                      onChange={(e) => setNewUser({...newUser, email: e.target.value})}
                      required
                    />
                  </div>

                  <div className="form-group mb-3">
                    <label className="form-label">Password</label>
                    <input
                      type="password"
                      className="form-control"
                      value={newUser.password}
                      onChange={(e) => setNewUser({...newUser, password: e.target.value})}
                      required
                    />
                  </div>

                  <div className="form-group mb-3">
                    <label className="form-label">Role</label>
                    <select
                      className="form-control"
                      value={newUser.role}
                      onChange={(e) => setNewUser({...newUser, role: e.target.value, division_id: ''})}
                      required
                    >
                      <option value="hr">HR</option>
                      <option value="manager">Manager</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>

                  {newUser.role === 'manager' && (
                    <div className="form-group mb-3">
                      <label className="form-label">Divisi</label>
                      <select
                        className="form-control"
                        value={newUser.division_id}
                        onChange={(e) => setNewUser({...newUser, division_id: e.target.value})}
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
                  )}
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

      {/* Edit User Modal */}
      {showEditModal && (
        <div className="modal active" tabIndex="-1" onClick={() => setShowEditModal(false)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Edit User</h5>
                <button 
                  type="button" 
                  className="btn-close"
                  onClick={() => setShowEditModal(false)}
                ></button>
              </div>
              <form onSubmit={handleEditUser}>
                <div className="modal-body">
                  <div className="form-group mb-3">
                    <label className="form-label">Username</label>
                    <input
                      type="text"
                      className="form-control"
                      value={editUser.username}
                      onChange={(e) => setEditUser({...editUser, username: e.target.value})}
                      required
                    />
                  </div>
                  
                  <div className="form-group mb-3">
                    <label className="form-label">Email</label>
                    <input
                      type="email"
                      className="form-control"
                      value={editUser.email}
                      onChange={(e) => setEditUser({...editUser, email: e.target.value})}
                      required
                    />
                  </div>

                  <div className="form-group mb-3">
                    <label className="form-label">Role</label>
                    <select
                      className="form-control"
                      value={editUser.role}
                      onChange={(e) => setEditUser({...editUser, role: e.target.value, division_id: e.target.value !== 'manager' ? '' : editUser.division_id})}
                      required
                    >
                      <option value="hr">HR</option>
                      <option value="manager">Manager</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>

                  {editUser.role === 'manager' && (
                    <div className="form-group mb-3">
                      <label className="form-label">Divisi</label>
                      <select
                        className="form-control"
                        value={editUser.division_id}
                        onChange={(e) => setEditUser({...editUser, division_id: e.target.value})}
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
                  )}

                  <div className="form-group mb-3">
                    <label className="form-label">Status</label>
                    <select
                      className="form-control"
                      value={editUser.status}
                      onChange={(e) => setEditUser({...editUser, status: e.target.value})}
                      required
                    >
                      <option value="active">Aktif</option>
                      <option value="inactive">Tidak Aktif</option>
                    </select>
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
                  <button type="submit" className="btn btn-primary">
                    Simpan Perubahan
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Add Division Modal */}
      {showDivisionModal && (
        <div className="modal active" tabIndex="-1" onClick={() => setShowDivisionModal(false)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Tambah Divisi Baru</h5>
                <button 
                  type="button" 
                  className="btn-close"
                  onClick={() => setShowDivisionModal(false)}
                ></button>
              </div>
              <form onSubmit={handleAddDivision}>
                <div className="modal-body">
                  <div className="form-group mb-3">
                    <label className="form-label">Nama Divisi</label>
                    <input
                      type="text"
                      className="form-control"
                      value={newDivision.division_name}
                      onChange={(e) => setNewDivision({...newDivision, division_name: e.target.value})}
                      required
                    />
                  </div>
                  
                  <div className="form-group mb-3">
                    <label className="form-label">Deskripsi</label>
                    <textarea
                      className="form-control"
                      rows="3"
                      value={newDivision.description}
                      onChange={(e) => setNewDivision({...newDivision, description: e.target.value})}
                    />
                  </div>
                </div>
                <div className="modal-footer">
                  <button 
                    type="button" 
                    className="btn btn-secondary"
                    onClick={() => setShowDivisionModal(false)}
                  >
                    Batal
                  </button>
                  <button type="submit" className="btn btn-success">
                    Tambah
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Division Detail Modal */}
      {showDivisionDetailModal && (
        <div className="modal active" tabIndex="-1" onClick={() => setShowDivisionDetailModal(false)}>
          <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Detail Divisi</h5>
                <button 
                  type="button" 
                  className="btn-close"
                  onClick={() => setShowDivisionDetailModal(false)}
                ></button>
              </div>
              <div className="modal-body">
                <div className="row">
                  {divisions.length > 0 ? (
                    divisions.map(division => (
                      <div key={division.division_id} className="col-md-6 mb-3">
                        <div className="card">
                          <div className="card-body">
                            <h6 className="card-title">
                              <i className="fas fa-building me-2 text-primary"></i>
                              {division.division_name}
                            </h6>
                            <p className="card-text text-muted small">
                              {division.description || 'Tidak ada deskripsi'}
                            </p>
                            <div className="d-flex justify-content-between align-items-center">
                              <small className="text-muted">
                                ID: {division.division_id}
                              </small>
                              <span className="badge bg-primary">
                                {users.filter(u => u.division_id === division.division_id).length} Users
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="col-12">
                      <div className="alert alert-info text-center">
                        <i className="fas fa-info-circle me-2"></i>
                        Belum ada divisi yang terdaftar
                      </div>
                    </div>
                  )}
                </div>
              </div>
              <div className="modal-footer">
                <button 
                  type="button" 
                  className="btn btn-secondary"
                  onClick={() => setShowDivisionDetailModal(false)}
                >
                  Tutup
                </button>
                <button 
                  type="button" 
                  className="btn btn-success"
                  onClick={() => {
                    setShowDivisionDetailModal(false);
                    setShowDivisionModal(true);
                  }}
                >
                  Tambah
                </button>
              </div>
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

export default Users; 