import React, { useState, useEffect } from 'react';
import { Container, Row, Col, Card, Table, Badge, Button, Modal, Form, Alert, Tabs, Tab } from 'react-bootstrap';
import { useAuth } from '../App';
import axios from 'axios';

const Recommendations = () => {
  const [recommendations, setRecommendations] = useState([]);
  const [pendingRecommendations, setPendingRecommendations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showProcessModal, setShowProcessModal] = useState(false);
  const [selectedRecommendation, setSelectedRecommendation] = useState(null);
  const [processStatus, setProcessStatus] = useState('');
  const [hrNotes, setHrNotes] = useState('');
  const [resignDate, setResignDate] = useState('');
  const [alert, setAlert] = useState({ show: false, message: '', type: '' });
  const [activeTab, setActiveTab] = useState('pending');
  const { user } = useAuth();

  // Debug logging
  console.log('📊 Recommendations component state:', {
    showProcessModal,
    selectedRecommendation,
    pendingRecommendations: pendingRecommendations.length,
    loading,
    user
  });

  useEffect(() => {
    console.log('📊 Recommendations useEffect - User role:', user?.role);
    if (user?.role === 'hr' || user?.role === 'admin') {
      console.log('📊 User has permission, fetching recommendations...');
      fetchRecommendations();
    } else {
      console.log('📊 User does not have HR/admin permission');
    }
  }, [user]);

  const fetchRecommendations = async () => {
    try {
      setLoading(true);
      
      // Fetch all recommendations
      const allRes = await axios.get('http://localhost/web_srk_BI/backend/api/recommendations.php?action=all', {
        withCredentials: true
      });
      
      // Fetch pending recommendations
      const pendingRes = await axios.get('http://localhost/web_srk_BI/backend/api/recommendations.php?action=pending', {
        withCredentials: true
      });
      
      if (allRes.data.success) {
        setRecommendations(allRes.data.data);
      }
      
      if (pendingRes.data.success) {
        setPendingRecommendations(pendingRes.data.data);
      }
      
    } catch (error) {
      console.error('Error fetching recommendations:', error);
      showAlert('Gagal memuat data rekomendasi', 'danger');
    } finally {
      setLoading(false);
    }
  };

  const handleProcessRecommendation = (recommendation) => {
    console.log('🔧 handleProcessRecommendation called with:', recommendation);
    console.log('🔧 Setting selectedRecommendation...');
    setSelectedRecommendation(recommendation);
    console.log('🔧 Resetting form fields...');
    setProcessStatus('');
    setHrNotes('');
    setResignDate('');
    console.log('🔧 Setting showProcessModal to true...');
    setShowProcessModal(true);
    console.log('🔧 Modal should be shown now, showProcessModal will be:', true);
    
    // Delay check to ensure state is updated
    setTimeout(() => {
      console.log('🔧 State check after 100ms - showProcessModal:', showProcessModal);
    }, 100);
  };

  const submitProcessRecommendation = async () => {
    if (!processStatus) {
      showAlert('Pilih status terlebih dahulu', 'warning');
      return;
    }

    // Validasi untuk resign
    if (processStatus === 'resign' && !resignDate) {
      showAlert('Tanggal resign harus diisi', 'warning');
      return;
    }

    try {
      const formData = new FormData();
      formData.append('action', 'process');
      formData.append('recommendation_id', selectedRecommendation.recommendation_id);
      formData.append('status', processStatus);
      formData.append('hr_notes', hrNotes);
      
      // Tambahkan resign_date jika status adalah resign
      if (processStatus === 'resign' && resignDate) {
        formData.append('resign_date', resignDate);
      }

      const response = await axios.post('http://localhost/web_srk_BI/backend/api/recommendations.php', formData, {
        withCredentials: true
      });

      if (response.data.success) {
        showAlert(response.data.message, 'success');
        setShowProcessModal(false);
        fetchRecommendations(); // Refresh data
      } else {
        showAlert(response.data.error, 'danger');
      }
    } catch (error) {
      console.error('Error processing recommendation:', error);
      console.error('Error response:', error.response?.data);
      console.error('Error status:', error.response?.status);
      console.error('Error headers:', error.response?.headers);
      
      let errorMessage = 'Gagal memproses rekomendasi';
      if (error.response?.data?.error) {
        errorMessage = error.response.data.error;
      } else if (error.response?.data) {
        errorMessage = `Server error: ${JSON.stringify(error.response.data)}`;
      }
      
      showAlert(errorMessage, 'danger');
    }
  };

  const handleMarkExtended = async (recommendation) => {
    if (!window.confirm('Apakah kontrak untuk karyawan ini sudah diperpanjang sesuai rekomendasi?')) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append('action', 'mark_extended');
      formData.append('recommendation_id', recommendation.recommendation_id);

      const response = await axios.post('http://localhost/web_srk_BI/backend/api/recommendations.php', formData, {
        withCredentials: true
      });

      if (response.data.success) {
        showAlert(response.data.message, 'success');
        fetchRecommendations(); // Refresh data
      } else {
        showAlert(response.data.error, 'danger');
      }
    } catch (error) {
      console.error('Error marking contract as extended:', error);
      showAlert('Gagal menandai kontrak sebagai sudah diperpanjang', 'danger');
    }
  };

  const showAlert = (message, type) => {
    setAlert({ show: true, message, type });
    setTimeout(() => setAlert({ show: false, message: '', type: '' }), 5000);
  };

  const getStatusBadge = (status) => {
    const badges = {
      'pending': <Badge bg="warning" text="dark">Menunggu</Badge>,
      'approved': <Badge bg="success">Disetujui</Badge>,
      'rejected': <Badge bg="danger">Ditolak</Badge>,
      'extended': <Badge bg="info">Sudah Diperpanjang</Badge>,
      'resign': <Badge bg="dark">Resign</Badge>
    };
    return badges[status] || <Badge bg="secondary">Unknown</Badge>;
  };

  const getUrgencyBadge = (urgency) => {
    const badges = {
      'high': <Badge bg="danger">Urgent</Badge>,
      'medium': <Badge bg="warning" text="dark">Sedang</Badge>,
      'low': <Badge bg="success">Normal</Badge>
    };
    return badges[urgency] || <Badge bg="secondary">-</Badge>;
  };

  const getRecommendationTypeBadge = (type, recommendation = null) => {
    // Check if this is Non-IT elimination by looking at the reason
    const isNonITElimination = recommendation && recommendation.reason && 
      recommendation.reason.includes('ELIMINASI OTOMATIS') && 
      recommendation.reason.includes('Non-IT');
    
    const badges = {
      'kontrak2': <Badge bg="info">Kontrak 2</Badge>,
      'kontrak3': <Badge bg="success">Kontrak 3</Badge>,
      'permanent': <Badge bg="warning">Permanent</Badge>,
      'terminate': isNonITElimination ? 
        <Badge bg="danger">TIDAK DIREKOMENDASIKAN</Badge> : 
        <Badge bg="warning">Kontrak (Evaluasi)</Badge>,
      // Legacy support for old values
      'kontrak1': <Badge bg="primary">Kontrak 1</Badge>,
      'extend': <Badge bg="primary">Perpanjang</Badge>,
      'review': <Badge bg="info">Review</Badge>
    };
    return badges[type] || <Badge bg="secondary">Unknown</Badge>;
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID');
  };

  const calculateDaysUntilEnd = (endDate) => {
    if (!endDate) return null;
    const today = new Date();
    const end = new Date(endDate);
    const diffTime = end - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
  };

  console.log('📊 Checking user permission:', { userRole: user?.role, isHR: user?.role === 'hr', isAdmin: user?.role === 'admin' });
  
  if (user?.role !== 'hr' && user?.role !== 'admin') {
    console.log('📊 User permission denied, role:', user?.role);
    return (
      <Container className="mt-4">
        <Alert variant="danger">
          <Alert.Heading>Akses Ditolak</Alert.Heading>
          <p>Hanya HR yang dapat mengakses halaman ini.</p>
        </Alert>
      </Container>
    );
  }

  return (
    <Container fluid className="mt-4">
      <Row>
        <Col>
          <div className="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h2 className="mb-1">📋 Manajemen Rekomendasi Kontrak</h2>
              <p className="text-muted">Kelola rekomendasi kontrak dari manager divisi</p>
            </div>
            <div className="text-end">
              <Badge bg="info" className="fs-6 px-3 py-2">
                {pendingRecommendations.length} Rekomendasi Pending
              </Badge>
            </div>
          </div>

          {alert.show && (
            <Alert variant={alert.type} dismissible onClose={() => setAlert({ show: false, message: '', type: '' })}>
              {alert.message}
            </Alert>
          )}

          <Tabs activeKey={activeTab} onSelect={(k) => setActiveTab(k)} className="mb-3">
            <Tab eventKey="pending" title={`Pending (${pendingRecommendations.length})`}>
              <Card>
                <Card.Header className="bg-warning text-dark">
                  <h5 className="mb-0">⏳ Rekomendasi Menunggu Persetujuan</h5>
                </Card.Header>
                <Card.Body className="p-0">
                  {loading ? (
                    <div className="text-center p-4">
                      <div className="spinner-border" role="status">
                        <span className="visually-hidden">Loading...</span>
                      </div>
                    </div>
                  ) : pendingRecommendations.length === 0 ? (
                    <div className="text-center p-4">
                      <p className="text-muted">Tidak ada rekomendasi yang menunggu persetujuan</p>
                    </div>
                  ) : (
                    <Table responsive hover className="mb-0">
                      <thead className="table-light">
                        <tr>
                          <th>Karyawan</th>
                          <th>Divisi</th>
                          <th>Kontrak Saat Ini</th>
                          <th>Rekomendasi</th>
                          <th>Durasi</th>
                          <th>Urgency</th>
                          <th>Manager (Divisi)</th>
                          <th>Tanggal</th>
                          <th>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        {pendingRecommendations.map((rec) => (
                          <tr key={rec.recommendation_id}>
                            <td>
                              <div>
                                <strong>{rec.employee.name}</strong>
                                <br />
                                <small className="text-muted">{rec.employee.role}</small>
                              </div>
                            </td>
                            <td>{rec.employee.division}</td>
                            <td>
                              <div>
                                <Badge bg="secondary" className="mb-1">
                                  {rec.current_contract.type || 'Probation'}
                                </Badge>
                                <br />
                                <small className="text-muted">
                                  Berakhir: {formatDate(rec.current_contract.end_date)}
                                  {rec.current_contract.days_until_end && (
                                    <span className="text-danger">
                                      <br />({rec.current_contract.days_until_end} hari lagi)
                                    </span>
                                  )}
                                </small>
                              </div>
                            </td>
                            <td>{getRecommendationTypeBadge(rec.recommendation_type, rec)}</td>
                            <td>
                              {rec.recommended_duration ? `${rec.recommended_duration} bulan` : '-'}
                            </td>
                            <td>{getUrgencyBadge(rec.urgency)}</td>
                            <td>
                              <div>
                                <strong>{rec.recommended_by}</strong>
                                <br />
                                <small className="text-muted">({rec.manager_division || 'Divisi tidak diketahui'})</small>
                              </div>
                            </td>
                            <td>{formatDate(rec.created_at)}</td>
                            <td>
                              <Button
                                variant="primary"
                                size="sm"
                                onClick={() => {
                                  console.log('🔴 Proses button clicked for rec:', rec);
                                  handleProcessRecommendation(rec);
                                }}
                              >
                                Proses
                              </Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  )}
                </Card.Body>
              </Card>
            </Tab>

            <Tab eventKey="approved" title="Disetujui">
              <Card>
                <Card.Header>
                  <h5 className="mb-0">✅ Rekomendasi yang Disetujui</h5>
                </Card.Header>
                <Card.Body className="p-0">
                  {loading ? (
                    <div className="text-center p-4">
                      <div className="spinner-border" role="status">
                        <span className="visually-hidden">Loading...</span>
                      </div>
                    </div>
                  ) : recommendations.filter(rec => rec.status === 'approved').length === 0 ? (
                    <div className="text-center p-4">
                      <p className="text-muted">Belum ada rekomendasi yang disetujui</p>
                    </div>
                  ) : (
                    <Table responsive hover className="mb-0">
                      <thead className="table-light">
                        <tr>
                          <th>Karyawan</th>
                          <th>Divisi</th>
                          <th>Rekomendasi</th>
                          <th>Durasi</th>
                          <th>Manager (Divisi)</th>
                          <th>Tanggal Disetujui</th>
                          <th>Status Kontrak</th>
                          <th>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        {recommendations.filter(rec => rec.status === 'approved').map((rec) => (
                          <tr key={rec.recommendation_id}>
                            <td>
                              <div>
                                <strong>{rec.employee.name}</strong>
                                <br />
                                <small className="text-muted">{rec.employee.role}</small>
                              </div>
                            </td>
                            <td>{rec.employee.division}</td>
                            <td>{getRecommendationTypeBadge(rec.recommendation_type, rec)}</td>
                            <td>
                              {rec.recommended_duration ? `${rec.recommended_duration} bulan` : '-'}
                            </td>
                            <td>
                              <div>
                                <strong>{rec.recommended_by}</strong>
                                <br />
                                <small className="text-muted">({rec.manager_division || 'Divisi tidak diketahui'})</small>
                              </div>
                            </td>
                            <td>{formatDate(rec.updated_at)}</td>
                            <td>
                              <Badge bg="warning" text="dark">Menunggu Perpanjangan</Badge>
                            </td>
                            <td>
                              <Button
                                variant="success"
                                size="sm"
                                onClick={() => handleMarkExtended(rec)}
                                title="Tandai kontrak sudah diperpanjang"
                              >
                                ✅ Sudah Diperpanjang
                              </Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  )}
                </Card.Body>
              </Card>
            </Tab>

            <Tab eventKey="all" title={`Semua (${recommendations.length})`}>
              <Card>
                <Card.Header>
                  <h5 className="mb-0">📊 Riwayat Semua Rekomendasi</h5>
                </Card.Header>
                <Card.Body className="p-0">
                  {loading ? (
                    <div className="text-center p-4">
                      <div className="spinner-border" role="status">
                        <span className="visually-hidden">Loading...</span>
                      </div>
                    </div>
                  ) : recommendations.length === 0 ? (
                    <div className="text-center p-4">
                      <p className="text-muted">Belum ada rekomendasi</p>
                    </div>
                  ) : (
                    <Table responsive hover className="mb-0">
                      <thead className="table-light">
                        <tr>
                          <th>Karyawan</th>
                          <th>Divisi</th>
                          <th>Rekomendasi</th>
                          <th>Durasi</th>
                          <th>Status</th>
                          <th>Manager (Divisi)</th>
                          <th>Tanggal</th>
                          <th>Update</th>
                        </tr>
                      </thead>
                      <tbody>
                        {recommendations.map((rec) => (
                          <tr key={rec.recommendation_id}>
                            <td>
                              <div>
                                <strong>{rec.employee.name}</strong>
                                <br />
                                <small className="text-muted">{rec.employee.role}</small>
                              </div>
                            </td>
                            <td>{rec.employee.division}</td>
                            <td>{getRecommendationTypeBadge(rec.recommendation_type, rec)}</td>
                            <td>
                              {rec.recommended_duration ? `${rec.recommended_duration} bulan` : '-'}
                            </td>
                            <td>{getStatusBadge(rec.status)}</td>
                            <td>
                              <div>
                                <strong>{rec.recommended_by}</strong>
                                <br />
                                <small className="text-muted">({rec.manager_division || 'Divisi tidak diketahui'})</small>
                              </div>
                            </td>
                            <td>{formatDate(rec.created_at)}</td>
                            <td>{formatDate(rec.updated_at)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  )}
                </Card.Body>
              </Card>
            </Tab>
          </Tabs>
        </Col>
      </Row>

      {/* Process Recommendation Modal */}
      {showProcessModal && (
        <div className="modal active" onClick={() => {
               console.log('🔧 Modal backdrop clicked');
               setShowProcessModal(false);
             }}>
          <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Proses Rekomendasi Kontrak</h5>
                <button type="button" className="btn-close" onClick={() => setShowProcessModal(false)}></button>
              </div>
              <div className="modal-body">
          {selectedRecommendation && (
            <div>
              <Row className="mb-3">
                <Col md={6}>
                  <strong>Karyawan:</strong> {selectedRecommendation.employee.name}
                  <br />
                  <strong>Divisi:</strong> {selectedRecommendation.employee.division}
                  <br />
                  <strong>Role:</strong> {selectedRecommendation.employee.role}
                </Col>
                <Col md={6}>
                  <strong>Rekomendasi:</strong> {getRecommendationTypeBadge(selectedRecommendation.recommendation_type, selectedRecommendation)}
                  <br />
                  <strong>Durasi:</strong> {selectedRecommendation.recommended_duration ? `${selectedRecommendation.recommended_duration} bulan` : '-'}
                  <br />
                  <strong>Manager:</strong> {selectedRecommendation.recommended_by}
                  <br />
                  <strong>Divisi Manager:</strong> {selectedRecommendation.manager_division || 'Tidak diketahui'}
                </Col>
              </Row>

              <div className="mb-3">
                <strong>Alasan Manager:</strong>
                <div className="bg-light p-2 rounded mt-1">
                  {selectedRecommendation.reason}
                </div>
              </div>

              <form>
                <div className="mb-3">
                  <label htmlFor="processStatus" className="form-label"><strong>Keputusan HR *</strong></label>
                  <select 
                    id="processStatus"
                    name="processStatus"
                    className="form-select"
                    value={processStatus} 
                    onChange={(e) => setProcessStatus(e.target.value)}
                    required
                  >
                    <option value="">Pilih keputusan...</option>
                    <option value="approved">Setujui Rekomendasi</option>
                    <option value="rejected">Tolak Rekomendasi</option>
                    {selectedRecommendation?.recommendation_type === 'terminate' && (
                      <option value="resign">Resign Karyawan</option>
                    )}
                  </select>
                </div>

                {processStatus === 'resign' && (
                  <div className="mb-3">
                    <label htmlFor="resignDate" className="form-label"><strong>Tanggal Resign *</strong></label>
                    <input
                      id="resignDate"
                      name="resignDate"
                      type="date"
                      className="form-control"
                      value={resignDate}
                      onChange={(e) => setResignDate(e.target.value)}
                      required
                    />
                    <div className="form-text text-muted">
                      Tanggal efektif pengunduran diri karyawan
                    </div>
                  </div>
                )}

                <div className="mb-3">
                  <label htmlFor="hrNotes" className="form-label">Catatan HR (Opsional)</label>
                  <textarea
                    id="hrNotes"
                    name="hrNotes"
                    className="form-control"
                    rows={3}
                    value={hrNotes}
                    onChange={(e) => setHrNotes(e.target.value)}
                    placeholder="Tambahkan catatan atau alasan keputusan..."
                  />
                </div>
              </form>
            </div>
          )}
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => {
                          console.log('🔧 Batal button clicked');
                          setShowProcessModal(false);
                        }}>
                  Batal
                </button>
                <button type="button" className="btn btn-primary" 
                        onClick={() => {
                          console.log('🔧 Submit button clicked, processStatus:', processStatus);
                          submitProcessRecommendation();
                        }} 
                        disabled={!processStatus}>
                  Proses Rekomendasi
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </Container>
  );
};

export default Recommendations; 