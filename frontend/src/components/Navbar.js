import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../App';

const Navbar = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  const getRoleDisplay = (role) => {
    switch (role) {
      case 'admin':
        return 'Admin';
      case 'hr':
        return 'HR';
      case 'manager':
        return 'Manager';
      default:
        return role;
    }
  };

  return (
    <nav className="navbar navbar-expand-lg">
      <div className="container-fluid px-4">
        <NavLink className="navbar-brand" to="/dashboard">
          <strong>🏢 BAT Contract System</strong>
        </NavLink>
        
        <div className="navbar-nav d-flex flex-row align-items-center ms-auto">
          {user && user.role === 'admin' ? (
            <NavLink className="nav-link" to="/users">
              Beranda
            </NavLink>
          ) : (
            <NavLink className="nav-link" to="/dashboard">
              Beranda
            </NavLink>
          )}
          
          {user && (
            <>
              {(user.role === 'hr' || user.role === 'manager') && (
                <NavLink className="nav-link" to="/employees">
                  Karyawan
                </NavLink>
              )}
              
              {user.role === 'hr' && (
                <>
                <NavLink className="nav-link" to="/recommendations">
                  Rekomendasi
                </NavLink>
                  {/* Temporarily hidden - Upload CSV feature */}
                  {/* <NavLink className="nav-link" to="/upload-csv">
                    📁 Upload CSV
                  </NavLink> */}
                </>
              )}
              
              <NavLink className="nav-link" to="/profile">
                Profile
              </NavLink>
            </>
          )}
          
          <button 
            className="btn btn-outline-danger ms-3"
            onClick={handleLogout}
          >
            Keluar
          </button>
        </div>
      </div>
    </nav>
  );
};

export default Navbar; 