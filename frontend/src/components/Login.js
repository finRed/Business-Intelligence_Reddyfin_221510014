import React, { useState } from 'react';
import { useAuth } from '../App';
import { Link } from 'react-router-dom';

const Login = () => {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [selectedRole, setSelectedRole] = useState('hr');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    
    const { login } = useAuth();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            const result = await login(username, password);
            
            if (!result.success) {
                setError(result.error);
            }
        } catch (error) {
            setError('Login gagal. Silakan coba lagi.');
        } finally {
            setLoading(false);
        }
    };

    const handleRoleChange = (role) => {
        setSelectedRole(role);
        // Clear form when switching roles
        setUsername('');
        setPassword('');
        setError('');
    };

    return (
        <div className="login-page">
            {/* Top Navigation */}
            <nav className="navbar navbar-expand-lg">
                <div className="container">
                    <span className="navbar-brand">
                        <strong>🏢 BAT Contract System</strong>
                    </span>
                    <div className="navbar-nav ms-auto">
                        <Link to="/" className="nav-link">Beranda</Link>
                        <Link to="/login" className="btn btn-primary ms-2">Masuk Sistem</Link>
                    </div>
                </div>
            </nav>

            {/* Main Login Content */}
            <div className="login-main-container">
                <div className="login-content-wrapper">
                    <div className="login-form-container">
                        <div className="login-header-new">
                            <h1>Masuk Sistem Internal</h1>
                        </div>

                        <form onSubmit={handleSubmit} className="login-form-new">
                            {/* Role Selection Tabs */}
                            <div className="role-tabs-new">
                                <button
                                    type="button"
                                    className={`role-tab-new ${selectedRole === 'hr' ? 'active' : ''}`}
                                    onClick={() => handleRoleChange('hr')}
                                >
                                    HR
                                </button>
                                <button
                                    type="button"
                                    className={`role-tab-new ${selectedRole === 'management' ? 'active' : ''}`}
                                    onClick={() => handleRoleChange('management')}
                                >
                                    Management
                                </button>
                                <button
                                    type="button"
                                    className={`role-tab-new ${selectedRole === 'admin' ? 'active' : ''}`}
                                    onClick={() => handleRoleChange('admin')}
                                >
                                    Admin
                                </button>
                            </div>

                            {/* Form Fields */}
                            <div className="form-fields-new">
                                <div className="form-group-new">
                                    <input
                                        type="text"
                                        className="form-control-new"
                                        placeholder="Alamat Email Karyawan BAT"
                                        value={username}
                                        onChange={(e) => setUsername(e.target.value)}
                                        required
                                    />
                                </div>
                                <div className="form-group-new">
                                    <input
                                        type="password"
                                        className="form-control-new"
                                        placeholder="Kata Sandi"
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        required
                                    />
                                </div>
                            </div>

                            {error && (
                                <div className="error-message-new">
                                    {error}
                                </div>
                            )}



                            <button
                                type="submit"
                                className="btn-masuk-new"
                                disabled={loading}
                            >
                                {loading ? 'Masuk...' : 'Masuk'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {/* Footer */}
            <div className="footer-new">
                <div className="footer-links-new">
                    <a href="#">Tentang BAT</a>
                    <a href="#">Kontak IT Support</a>
                    <a href="#">Kebijakan Internal</a>
                    <a href="#">Panduan Sistem</a>
                </div>
                <p>©2025 PT Bumi Armatha Teknologi. Sistem Internal - Hak Cipta Dilindungi.</p>
            </div>
        </div>
    );
};

export default Login; 