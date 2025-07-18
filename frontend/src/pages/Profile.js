import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../App';

const Profile = () => {
  const { user } = useAuth();
  const [profile, setProfile] = useState({
    username: '',
    email: '',
    current_password: '',
    new_password: '',
    confirm_password: ''
  });
  const [loading, setLoading] = useState(false);
  const [divisions, setDivisions] = useState([]);

  useEffect(() => {
    if (user) {
      setProfile({
        username: user.username || '',
        email: user.email || '',
        current_password: '',
        new_password: '',
        confirm_password: ''
      });
    }
    fetchDivisions();
  }, [user]);

  const fetchDivisions = async () => {
    try {
      const response = await axios.get('/divisions.php', {
        withCredentials: true
      });
      if (response.data.success && Array.isArray(response.data.data)) {
        setDivisions(response.data.data);
      }
    } catch (error) {
      console.error('Error fetching divisions:', error);
    }
  };

  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    
    if (profile.new_password && profile.new_password !== profile.confirm_password) {
      alert('Password baru dan konfirmasi password tidak cocok');
      return;
    }

    setLoading(true);
    try {
      const formData = new FormData();
      formData.append('action', 'update_profile');
      formData.append('username', profile.username);
      formData.append('email', profile.email);
      if (profile.current_password) {
        formData.append('current_password', profile.current_password);
      }
      if (profile.new_password) {
        formData.append('new_password', profile.new_password);
      }

      const response = await axios.post('/auth.php', formData, {
        withCredentials: true
      });

      if (response.data.success) {
        alert('Profile berhasil diupdate!');
        setProfile({
          ...profile,
          current_password: '',
          new_password: '',
          confirm_password: ''
        });
      } else {
        alert('Error: ' + response.data.error);
      }
    } catch (error) {
      console.error('Error updating profile:', error);
      alert('Terjadi kesalahan saat mengupdate profile');
    } finally {
      setLoading(false);
    }
  };

  const getDivisionName = (divisionId) => {
    const division = divisions.find(d => d.division_id === divisionId);
    return division ? division.division_name : '-';
  };

  const getRoleDisplay = (role) => {
    switch (role) {
      case 'admin':
        return 'Administrator';
      case 'hr':
        return 'Human Resources';
      case 'manager':
        return 'Manager';
      default:
        return role;
    }
  };

  if (!user) {
    return <div className="main-container">Loading...</div>;
  }

  return (
    <div className="main-container">
      <div className="row">
        <div className="col-md-8 mx-auto">
          <div className="user-card">
            <div className="card-header">
              <h2>Profile Saya</h2>
            </div>
            
            {/* Profile Info */}
            <div className="row mb-4">
              <div className="col-md-4 text-center">
                <div className="user-avatar-large">
                  <i className="fas fa-user"></i>
                </div>
                <h4 className="mt-3">{user.username}</h4>
                <span className="status-badge status-active">Aktif</span>
              </div>
              <div className="col-md-8">
                <table className="table table-borderless">
                  <tbody>
                    <tr>
                      <td><strong>Username:</strong></td>
                      <td>{user.username}</td>
                    </tr>
                    <tr>
                      <td><strong>Email:</strong></td>
                      <td>{user.email}</td>
                    </tr>
                    <tr>
                      <td><strong>Role:</strong></td>
                      <td>{getRoleDisplay(user.role)}</td>
                    </tr>
                    <tr>
                      <td><strong>Divisi:</strong></td>
                      <td>{getDivisionName(user.division_id)}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            {/* Edit Profile Form */}
            <div className="card-header">
              <h3>Edit Profile</h3>
            </div>
            
            <form onSubmit={handleUpdateProfile}>
              <div className="row">
                <div className="col-md-6">
                  <div className="form-group mb-3">
                    <label className="form-label">Username</label>
                    <input
                      type="text"
                      className="form-control"
                      value={profile.username}
                      onChange={(e) => setProfile({...profile, username: e.target.value})}
                      required
                    />
                  </div>
                </div>
                <div className="col-md-6">
                  <div className="form-group mb-3">
                    <label className="form-label">Email</label>
                    <input
                      type="email"
                      className="form-control"
                      value={profile.email}
                      onChange={(e) => setProfile({...profile, email: e.target.value})}
                      required
                    />
                  </div>
                </div>
              </div>

              <hr className="my-4" />
              <h5>Ubah Password</h5>
              <p className="text-muted">Kosongkan jika tidak ingin mengubah password</p>

              <div className="row">
                <div className="col-md-4">
                  <div className="form-group mb-3">
                    <label className="form-label">Password Saat Ini</label>
                    <input
                      type="password"
                      className="form-control"
                      value={profile.current_password}
                      onChange={(e) => setProfile({...profile, current_password: e.target.value})}
                    />
                  </div>
                </div>
                <div className="col-md-4">
                  <div className="form-group mb-3">
                    <label className="form-label">Password Baru</label>
                    <input
                      type="password"
                      className="form-control"
                      value={profile.new_password}
                      onChange={(e) => setProfile({...profile, new_password: e.target.value})}
                    />
                  </div>
                </div>
                <div className="col-md-4">
                  <div className="form-group mb-3">
                    <label className="form-label">Konfirmasi Password Baru</label>
                    <input
                      type="password"
                      className="form-control"
                      value={profile.confirm_password}
                      onChange={(e) => setProfile({...profile, confirm_password: e.target.value})}
                    />
                  </div>
                </div>
              </div>

              <div className="text-end mt-4">
                <button 
                  type="submit" 
                  className="btn btn-primary"
                  disabled={loading}
                >
                  {loading ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
              </div>
            </form>
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

export default Profile; 