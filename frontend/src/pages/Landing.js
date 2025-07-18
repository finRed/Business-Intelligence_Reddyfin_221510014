import React from 'react';
import { Link } from 'react-router-dom';

const Landing = () => {
    return (
        <div className="landing-container">
            {/* Navigation */}
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

            {/* Hero Section */}
            <div className="landing-hero">
                <div className="container">
                    <h1>BAT Contract Recommendation System</h1>
                    <p>Sistem Internal PT Bumi Armatha Teknologi untuk Rekomendasi Kontrak Karyawan yang Cerdas dan Efisien</p>
                    <Link to="/login" className="btn btn-primary btn-lg">
                        Akses Sistem Internal
                    </Link>
                </div>
            </div>

            {/* Features Section */}
            <div className="landing-features">
                <div className="feature-card">
                    <div className="feature-icon">
                        📋
                    </div>
                    <h3>Manajemen Kontrak</h3>
                    <p>Kelola seluruh kontrak karyawan PT Bumi Armatha Teknologi dengan sistem yang terintegrasi dan mudah digunakan.</p>
                </div>

                <div className="feature-card">
                    <div className="feature-icon">
                        🔍
                    </div>
                    <h3>Rekomendasi Cerdas</h3>
                    <p>Dapatkan rekomendasi kontrak yang tepat berdasarkan analisis performa, divisi, dan kebutuhan perusahaan.</p>
                </div>

                <div className="feature-card">
                    <div className="feature-icon">
                        🎯
                    </div>
                    <h3>Efisiensi Optimal</h3>
                    <p>Tingkatkan efisiensi proses HR dengan sistem otomatis yang membantu pengambilan keputusan kontrak karyawan.</p>
                </div>
            </div>

            {/* Footer */}
            <div className="footer">
                <div className="footer-links">
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

export default Landing; 