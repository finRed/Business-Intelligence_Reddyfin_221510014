import React, { useState, useEffect } from 'react';
import { Container, Row, Col, Card, Button, Spinner, Badge, Form, Alert } from 'react-bootstrap';
import { useParams, useNavigate } from 'react-router-dom';

const ContractRecommendationView = () => {
  const { eid } = useParams();
  const navigate = useNavigate();
  const [employeeDetail, setEmployeeDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [recommendationData, setRecommendationData] = useState({
    recommendation_type: '',
    recommended_duration: '',
    reason: ''
  });
  const [alert, setAlert] = useState({ show: false, message: '', type: '' });

  useEffect(() => {
    fetchEmployeeDetail();
  }, [eid]);

  const fetchEmployeeDetail = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/backend/api/employees.php?action=detail&eid=${eid}`, {
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      
      if (result.success) {
        setEmployeeDetail(result.data);
        
        // Pre-fill recommendation based on AI analysis
        if (result.data.dynamic_intelligence) {
          const duration = result.data.dynamic_intelligence.optimal_duration;
          const category = result.data.dynamic_intelligence.match_category;
          
          let suggestedType = '';
          let suggestedDuration = '';
          
          if (category === 'Sudah Permanent') {
            suggestedType = 'permanent';
            suggestedDuration = '0';
          } else if (result.data.employee?.current_contract_type === '3' && 
              (category === 'Siap Permanent' || duration === 0)) {
            suggestedType = 'permanent';
            suggestedDuration = '0';
          } else if (duration === 0) {
            suggestedType = 'permanent';
            suggestedDuration = '0';
          } else if (duration && duration > 0) {
            suggestedType = result.data.employee?.current_contract_type === '2' ? 'kontrak3' : 'kontrak2';
            suggestedDuration = duration.toString();
          }
          
          setRecommendationData(prev => ({
            ...prev,
            recommendation_type: suggestedType,
            recommended_duration: suggestedDuration
          }));
        }
      } else {
        console.error('Failed to fetch employee detail:', result.message);
      }
    } catch (error) {
      console.error('Error fetching employee detail:', error);
    } finally {
      setLoading(false);
    }
  };

  const submitRecommendation = async () => {
    try {
      setSubmitting(true);
      
      const formData = new FormData();
      formData.append('action', 'create');
      formData.append('eid', eid);
      formData.append('recommendation_type', recommendationData.recommendation_type);
      formData.append('recommended_duration', recommendationData.recommended_duration || '0');
      formData.append('reason', recommendationData.reason);
      formData.append('system_recommendation', 'Rekomendasi dari form kontrak');

      const response = await fetch('http://localhost/web_srk_BI/backend/api/recommendations.php', {
        method: 'POST',
        credentials: 'include',
        body: formData
      });

      const result = await response.json();
      
      if (result.success) {
        setAlert({
          show: true,
          message: 'Rekomendasi berhasil dikirim ke HR!',
          type: 'success'
        });
        
        // Navigate back after 2 seconds
        setTimeout(() => {
          navigate(-1);
        }, 2000);
      } else {
        setAlert({
          show: true,
          message: result.message || 'Gagal mengirim rekomendasi',
          type: 'danger'
        });
      }
    } catch (error) {
      console.error('Error submitting recommendation:', error);
      setAlert({
        show: true,
        message: 'Terjadi kesalahan saat mengirim rekomendasi',
        type: 'danger'
      });
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <Container className="py-5">
        <div className="text-center">
          <Spinner animation="border" variant="success" />
          <div className="mt-3">
            <h5>Memuat data untuk rekomendasi...</h5>
            <p className="text-muted">Menganalisis profil dan riwayat karyawan</p>
          </div>
        </div>
      </Container>
    );
  }

  if (!employeeDetail || employeeDetail.error) {
    return (
      <Container className="py-5">
        <div className="text-center">
          <div className="alert alert-danger">
            <h5>❌ Error Loading Data</h5>
            <p>{employeeDetail?.errorMessage || 'Gagal memuat data karyawan'}</p>
          </div>
          <Button variant="secondary" onClick={() => navigate(-1)}>
            <i className="fas fa-arrow-left me-2"></i>
            Kembali
          </Button>
        </div>
      </Container>
    );
  }

  return (
    <Container fluid className="py-4">
      {/* Header */}
      <Row className="mb-4">
        <Col>
          <div className="d-flex justify-content-between align-items-center">
            <div>
              <h2 className="mb-1">
                <i className="fas fa-file-contract me-2 text-success"></i>
                {employeeDetail.employee?.name} - Buat Rekomendasi Kontrak
              </h2>
              <p className="text-muted mb-0">Form rekomendasi kontrak berdasarkan analisis data</p>
            </div>
            <div className="d-flex gap-2">
              <Button 
                variant="primary" 
                onClick={() => navigate(`/manager/employee/${eid}`)}
              >
                <i className="fas fa-user me-2"></i>
                Lihat Detail
              </Button>
              <Button variant="secondary" onClick={() => navigate(-1)}>
                <i className="fas fa-arrow-left me-2"></i>
                Kembali
              </Button>
            </div>
          </div>
        </Col>
      </Row>

      {/* Alert */}
      {alert.show && (
        <Row className="mb-4">
          <Col>
            <Alert variant={alert.type} onClose={() => setAlert({ show: false, message: '', type: '' })} dismissible>
              {alert.message}
            </Alert>
          </Col>
        </Row>
      )}

      <Row className="g-4">
        {/* Left Column - Employee Summary */}
        <Col lg={4}>
          <Card className="border-0 shadow-sm h-100">
            <Card.Header className="bg-primary text-white">
              <h6 className="mb-0">
                <i className="fas fa-user me-2"></i>
                Ringkasan Karyawan
              </h6>
            </Card.Header>
            <Card.Body>
              <div className="mb-3">
                <small className="text-muted d-block">Nama</small>
                <div className="fw-bold">{employeeDetail.employee?.name}</div>
              </div>
              <div className="mb-3">
                <small className="text-muted d-block">Posisi</small>
                <div className="fw-bold">{employeeDetail.employee?.role}</div>
              </div>
              <div className="mb-3">
                <small className="text-muted d-block">Divisi</small>
                <div className="fw-bold">{employeeDetail.employee?.division_name}</div>
              </div>
              <div className="mb-3">
                <small className="text-muted d-block">Tenure</small>
                <div className="fw-bold">{Math.round(employeeDetail.employee?.tenure_months || 0)} bulan</div>
              </div>
              <div className="mb-3">
                <small className="text-muted d-block">Current Contract</small>
                <div>
                  <Badge bg={employeeDetail.employee?.current_contract_type === 'permanent' ? 'success' : 'info'}>
                    {employeeDetail.employee?.current_contract_type || 'N/A'}
                  </Badge>
                </div>
              </div>
              <div className="mb-3">
                <small className="text-muted d-block">Match Status</small>
                <div>
                  {employeeDetail.education_match_analysis?.match_status ? (
                    <Badge bg={employeeDetail.education_match_analysis.match_status === 'Match' ? 'success' : 'warning'}>
                      {employeeDetail.education_match_analysis.match_status}
                    </Badge>
                  ) : (
                    <Badge bg="secondary">N/A</Badge>
                  )}
                </div>
              </div>

              {/* AI Analysis Summary */}
              {employeeDetail.dynamic_intelligence && (
                <div className="mt-4 p-3 bg-light rounded">
                  <h6 className="text-success mb-2">
                    <i className="fas fa-brain me-1"></i>
                    AI Analysis
                  </h6>
                  <div className="mb-2">
                    <small className="text-muted d-block">Kategori</small>
                    <div className="fw-bold small">{employeeDetail.dynamic_intelligence.match_category}</div>
                  </div>
                  <div className="mb-2">
                    <small className="text-muted d-block">Sample Size</small>
                    <div className="fw-bold small">{employeeDetail.dynamic_intelligence.sample_size} profil</div>
                  </div>
                  {employeeDetail.dynamic_intelligence.historical_patterns && (
                    <div className="row text-center mt-2">
                      <div className="col-4">
                        <div className="small fw-bold text-success">{employeeDetail.dynamic_intelligence.historical_patterns.success_rate}%</div>
                        <small className="text-muted" style={{ fontSize: '0.7rem' }}>Aktif</small>
                      </div>
                      <div className="col-4">
                        <div className="small fw-bold text-danger">{employeeDetail.dynamic_intelligence.historical_patterns.resign_rate}%</div>
                        <small className="text-muted" style={{ fontSize: '0.7rem' }}>Resign</small>
                      </div>
                      <div className="col-4">
                        <div className="small fw-bold text-primary">
                          {employeeDetail.dynamic_intelligence.historical_patterns.avg_duration?.toFixed(1) || '0'}
                        </div>
                        <small className="text-muted" style={{ fontSize: '0.7rem' }}>Avg</small>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </Card.Body>
          </Card>
        </Col>

        {/* Right Column - Recommendation Form */}
        <Col lg={8}>
          <Card className="border-0 shadow-sm h-100">
            <Card.Header style={{ background: 'linear-gradient(135deg, #fd7e14 0%, #ffc107 100%)', color: 'white' }}>
              <h6 className="mb-0">
                <i className="fas fa-file-signature me-2"></i>
                Form Rekomendasi Kontrak
              </h6>
            </Card.Header>
            <Card.Body className="p-4">
              {/* AI Suggestion Alert */}
              {employeeDetail?.dynamic_intelligence && (
                <Alert variant="info" className="mb-4">
                  <div className="d-flex align-items-center">
                    <i className="fas fa-brain fa-lg text-info me-3"></i>
                    <div className="flex-grow-1">
                      <h6 className="alert-heading mb-2">
                        <i className="fas fa-lightbulb me-1"></i>
                        AI Recommendation
                      </h6>
                      <Row>
                        <Col md={4}>
                          <small className="text-muted d-block">Kategori</small>
                          <strong>{employeeDetail.dynamic_intelligence?.match_category}</strong>
                        </Col>
                        <Col md={4}>
                          <small className="text-muted d-block">Durasi Optimal</small>
                          <strong className="text-primary">
                            {employeeDetail.dynamic_intelligence?.optimal_duration === 0 ? 'Permanent' : `${employeeDetail.dynamic_intelligence?.optimal_duration} bulan`}
                          </strong>
                        </Col>
                        <Col md={4}>
                          <small className="text-muted d-block">Confidence</small>
                          <strong>{employeeDetail.dynamic_intelligence?.sample_size} data points</strong>
                        </Col>
                      </Row>
                    </div>
                  </div>
                </Alert>
              )}

              {/* Form Fields */}
              <Row>
                <Col md={6}>
                  <Form.Group className="mb-3">
                    <Form.Label htmlFor="recommendationType" className="fw-bold">
                      <i className="fas fa-clipboard-list me-2"></i>
                      Jenis Rekomendasi
                    </Form.Label>
                    <Form.Select 
                      id="recommendationType"
                      name="recommendationType"
                      value={recommendationData.recommendation_type || ''}
                      onChange={(e) => {
                        const newType = e.target.value;
                        setRecommendationData(prev => ({
                          ...prev,
                          recommendation_type: newType,
                          recommended_duration: newType === 'permanent' ? 0 : (newType === 'terminate' ? 0 : prev.recommended_duration)
                        }));
                      }}
                    >
                      <option value="">Pilih Jenis Kontrak</option>
                      <option value="kontrak2">Kontrak 2</option>
                      <option value="kontrak3">Kontrak 3</option>
                      <option value="permanent">Permanent</option>
                      <option value="terminate">Tidak Diperpanjang</option>
                    </Form.Select>
                  </Form.Group>
                </Col>
                <Col md={6}>
                  <Form.Group className="mb-3">
                    <Form.Label htmlFor="recommendationDuration" className="fw-bold">
                      <i className="fas fa-calendar-alt me-2"></i>
                      Durasi (bulan)
                    </Form.Label>
                    <Form.Control
                      id="recommendationDuration"
                      name="recommendationDuration"
                      type="number"
                      value={recommendationData.recommended_duration || ''}
                      onChange={(e) => setRecommendationData(prev => ({
                        ...prev,
                        recommended_duration: e.target.value
                      }))}
                      disabled={recommendationData.recommendation_type === 'permanent' || recommendationData.recommendation_type === 'terminate'}
                      placeholder="Masukkan durasi"
                      min="1"
                      max="36"
                    />
                    {recommendationData.recommendation_type === 'permanent' && (
                      <Form.Text className="text-success">
                        <i className="fas fa-info-circle me-1"></i>
                        Permanent - tidak memerlukan durasi
                      </Form.Text>
                    )}
                    {recommendationData.recommendation_type === 'terminate' && (
                      <Form.Text className="text-danger">
                        <i className="fas fa-exclamation-triangle me-1"></i>
                        Tidak diperpanjang
                      </Form.Text>
                    )}
                  </Form.Group>
                </Col>
              </Row>
              
              <Form.Group className="mb-4">
                <Form.Label htmlFor="recommendationReason" className="fw-bold">
                  <i className="fas fa-comment-alt me-2"></i>
                  Alasan Rekomendasi
                </Form.Label>
                <Form.Control
                  id="recommendationReason"
                  name="recommendationReason"
                  as="textarea"
                  rows={8}
                  value={recommendationData.reason || ''}
                  onChange={(e) => setRecommendationData(prev => ({
                    ...prev,
                    reason: e.target.value
                  }))}
                  placeholder="Jelaskan alasan rekomendasi berdasarkan analisis data, performa karyawan, dan kebutuhan perusahaan..."
                />
                <Form.Text>
                  <i className="fas fa-lightbulb me-1"></i>
                  Tip: Sertakan justifikasi berdasarkan match status, tenure, dan analisis AI
                </Form.Text>
              </Form.Group>

              <div className="text-center">
                <Button 
                  variant="success" 
                  size="lg" 
                  onClick={submitRecommendation}
                  disabled={!recommendationData.recommendation_type || submitting}
                  className="px-5"
                >
                  {submitting ? (
                    <>
                      <Spinner animation="border" size="sm" className="me-2" />
                      Mengirim...
                    </>
                  ) : (
                    <>
                      <i className="fas fa-paper-plane me-2"></i>
                      Kirim Rekomendasi ke HR
                    </>
                  )}
                </Button>
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  );
};

export default ContractRecommendationView;
