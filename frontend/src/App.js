import React, { useState, useEffect, createContext, useContext } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import axios from 'axios';
import 'bootstrap/dist/css/bootstrap.min.css';

// Components
import Login from './components/Login';
import Navbar from './components/Navbar';
import Landing from './pages/Landing';
import Dashboard from './pages/Dashboard';
import ManagerDashboard from './pages/ManagerDashboard';
import Employees from './pages/Employees';
import EmployeeDetail from './pages/EmployeeDetail';
import ContractRecommendation from './pages/ContractRecommendation';
import Contracts from './pages/Contracts';
import Users from './pages/Users';
import Divisions from './pages/Divisions';
import Profile from './pages/Profile';
import Recommendations from './pages/Recommendations';
import UploadCSV from './pages/UploadCSV';
import './App.css';

// Create Auth Context
export const AuthContext = createContext();
export const useAuth = () => useContext(AuthContext);

// Configure axios defaults
axios.defaults.baseURL = 'http://localhost/web_srk_BI/backend/api';
axios.defaults.withCredentials = true;

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      console.log('🔍 Checking auth status...');
      const response = await axios.get('/auth.php', {
        timeout: 10000 // 10 second timeout
      });
      console.log('🔍 Auth response:', response.data);
      if (response.data.success) {
        setUser(response.data.user);
        console.log('✅ User authenticated:', response.data.user);
      } else {
        console.log('❌ User not authenticated, response:', response.data);
        setUser(null);
      }
    } catch (error) {
      console.log('❌ Auth error:', error.message);
      console.log('❌ Auth error details:', error.response?.data);
      setUser(null);
    } finally {
      console.log('🔄 Setting loading to false');
      setLoading(false);
    }
  };

  const login = async (username, password) => {
    try {
      console.log('Attempting login with:', { username, password: '***' });
      console.log('Axios baseURL:', axios.defaults.baseURL);
      console.log('Full URL will be:', `${axios.defaults.baseURL}/auth.php`);
      
      const formData = new FormData();
      formData.append('action', 'login');
      formData.append('username', username);
      formData.append('password', password);

      console.log('FormData contents:', {
        action: formData.get('action'),
        username: formData.get('username'),
        password: '***'
      });

      const response = await axios.post('/auth.php', formData, {
        withCredentials: true,
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      
      console.log('Login response status:', response.status);
      console.log('Login response data:', response.data);
      
      if (response.data.success) {
        setUser(response.data.user);
        console.log('User set successfully:', response.data.user);
        return { success: true };
      } else {
        console.log('Login failed:', response.data.error);
        return { success: false, error: response.data.error };
      }
    } catch (error) {
      console.error('Login error:', error);
      console.error('Error response status:', error.response?.status);
      console.error('Error response data:', error.response?.data);
      console.error('Error message:', error.message);
      
      let errorMessage = 'Login gagal. Periksa koneksi internet.';
      
      if (error.response?.data?.error) {
        errorMessage = error.response.data.error;
      } else if (error.response?.status === 404) {
        errorMessage = 'Server tidak ditemukan. Pastikan XAMPP berjalan.';
      } else if (error.response?.status >= 500) {
        errorMessage = 'Server error. Silakan coba lagi.';
      }
      
      return { 
        success: false, 
        error: errorMessage
      };
    }
  };

  const logout = async () => {
    try {
      const formData = new FormData();
      formData.append('action', 'logout');
      await axios.post('/auth.php', formData);
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      setUser(null);
    }
  };

  const authContextValue = {
    user,
    login,
    logout,
    isAuthenticated: !!user
  };

  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center vh-100">
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">Loading...</span>
        </div>
      </div>
    );
  }

  return (
    <AuthContext.Provider value={authContextValue}>
      <Router>
        <div className="App">
          {user ? (
            <>
              <Navbar />
              <div className="container-fluid">
                <div className="row">
                  <main className="col-12">
                    <Routes>
                      {user.role === 'admin' ? (
                        <>
                          <Route path="/" element={<Users />} />
                          <Route path="/dashboard" element={<Dashboard />} />
                          <Route path="/users" element={<Users />} />
                          <Route path="/divisions" element={<Divisions />} />
                          <Route path="/profile" element={<Profile />} />
                          <Route path="*" element={<Navigate to="/users" replace />} />
                        </>
                      ) : user.role === 'hr' ? (
                        <>
                          <Route path="/" element={<Dashboard />} />
                          <Route path="/dashboard" element={<Dashboard />} />
                          <Route path="/employees" element={<Employees />} />
                          <Route path="/employees/:eid" element={<EmployeeDetail />} />
                          <Route path="/employee/:eid" element={<EmployeeDetail />} />
                          <Route path="/contract-recommendation/:eid" element={<ContractRecommendation />} />
                          <Route path="/contracts" element={<Contracts />} />
                          <Route path="/recommendations" element={<Recommendations />} />
                          {/* Temporarily hidden - Upload CSV feature */}
                          {/* <Route path="/upload-csv" element={<UploadCSV />} /> */}
                          <Route path="/profile" element={<Profile />} />
                          <Route path="*" element={<Navigate to="/" replace />} />
                        </>
                      ) : (
                        <>
                          <Route path="/" element={<ManagerDashboard />} />
                          <Route path="/dashboard" element={<ManagerDashboard />} />
                          <Route path="/employees" element={<Employees />} />
                          <Route path="/employees/:eid" element={<EmployeeDetail />} />
                          <Route path="/employee/:eid" element={<EmployeeDetail />} />
                          <Route path="/manager/employee/:eid" element={<EmployeeDetail />} />
                          <Route path="/manager/recommendation/:eid" element={<ContractRecommendation />} />
                          <Route path="/contract-recommendation/:eid" element={<ContractRecommendation />} />
                          <Route path="/contracts" element={<Contracts />} />
                          <Route path="/profile" element={<Profile />} />
                          <Route path="*" element={<Navigate to="/dashboard" replace />} />
                        </>
                      )}
                    </Routes>
                  </main>
                </div>
              </div>
            </>
          ) : (
            <Routes>
              <Route path="/" element={<Landing />} />
              <Route path="/login" element={<Login />} />
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          )}
        </div>
      </Router>
    </AuthContext.Provider>
  );
}

export default App; 