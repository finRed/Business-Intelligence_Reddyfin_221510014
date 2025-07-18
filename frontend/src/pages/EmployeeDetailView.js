import React, { useState, useEffect } from 'react';
import { Container, Row, Col, Card, Button, Spinner, Badge, Tab, Nav } from 'react-bootstrap';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../App';

const EmployeeDetailView = () => {
  const { eid } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [employeeDetail, setEmployeeDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('profile');

  useEffect(() => {
    fetchEmployeeDetail();
  }, [eid]);

  const fetchEmployeeDetail = async () => {
    try {
      setLoading(true);
      console.log('Fetching employee detail for EID:', eid);
      
      const response = await fetch(`http://localhost/web_srk_BI/backend/api/employees.php?action=detail&eid=${eid}`, {
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      console.log('Employee detail response:', result);
      
      if (result.success) {
        setEmployeeDetail(result);
      } else {
        console.error('Failed to fetch employee detail:', result.message);
      }
    } catch (error) {
      console.error('Error fetching employee detail:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('id-ID');
  };

  const getContractStatusBadge = (contractType) => {
    const badges = {
      'probation': { variant: 'warning', text: 'Probation' },
      '1': { variant: 'info', text: 'Kontrak 1' },
      '2': { variant: 'primary', text: 'Kontrak 2' },
      '3': { variant: 'secondary', text: 'Kontrak 3' },
      'permanent': { variant: 'success', text: 'Permanent' },
    };
    
    const badge = badges[contractType] || { variant: 'light', text: 'N/A' };
    return <Badge bg={badge.variant}>{badge.text}</Badge>;
  };

  const getMatchStatusBadge = (matchStatus) => {
    if (matchStatus === 'Match') {
      return <Badge bg="success">Match</Badge>;
    }
    return <Badge bg="danger">Unmatch</Badge>;
  };

  if (loading) {
    return (
      <Container className="py-5">
        <div className="text-center">
          <Spinner animation="border" variant="primary" />
          <div className="mt-3">
            <h5>Memuat detail karyawan...</h5>
            <p className="text-muted">Menganalisis data dan riwayat karyawan</p>
          </div>
        </div>
      </Container>
    );
  }

  if (!employeeDetail || !employeeDetail.employee) {
    return (
      <Container className="py-5">
        <div className="text-center">
          <div className="alert alert-warning">
            <h5>❌ Data Tidak Ditemukan</h5>
            <p>Karyawan dengan ID {eid} tidak ditemukan atau tidak memiliki akses.</p>
          </div>
          <Button variant="secondary" onClick={() => navigate('/dashboard')}>
            <i className="fas fa-arrow-left me-2"></i>
            Kembali ke Dashboard
          </Button>
        </div>
      </Container>
    );
  }

  const employee = employeeDetail.employee;

  return (
    <Container fluid className="py-4">
      {/* Header */}
      <Row className="mb-4">
        <Col>
          <div className="d-flex justify-content-between align-items-center">
            <div>
              <h2 className="mb-1">
                <i className="fas fa-user me-2 text-primary"></i>
                Detail Karyawan - {employee.name}
              </h2>
              <p className="text-muted mb-0">
                Analisis lengkap profil dan rekomendasi untuk {employee.role} | {employee.division_name}
              </p>
            </div>
            <div className="d-flex gap-2">
              <Button 
                variant="success" 
                onClick={() => navigate(`/manager/recommendation/${eid}`)}
                disabled={employee.current_contract_type === 'permanent'}
              >
                <i className="fas fa-file-contract me-2"></i>
                Buat Rekomendasi
              </Button>
              <Button variant="secondary" onClick={() => navigate('/dashboard')}>
                <i className="fas fa-arrow-left me-2"></i>
                Kembali
              </Button>
            </div>
          </div>
        </Col>
      </Row>

      {/* Navigation Tabs */}
      <Tab.Container activeKey={activeTab} onSelect={setActiveTab}>
        <Nav variant="tabs" className="mb-4">
          <Nav.Item>
            <Nav.Link eventKey="profile">
              <i className="fas fa-user me-2"></i>
              Profil Karyawan
            </Nav.Link>
          </Nav.Item>
          <Nav.Item>
            <Nav.Link eventKey="analysis">
              <i className="fas fa-chart-line me-2"></i>
              Analisis Data
            </Nav.Link>
          </Nav.Item>
          <Nav.Item>
            <Nav.Link eventKey="contracts">
              <i className="fas fa-file-contract me-2"></i>
              Riwayat Kontrak
            </Nav.Link>
          </Nav.Item>
        </Nav>

        <Tab.Content>
          {/* Profile Tab */}
          <Tab.Pane eventKey="profile">
            <Row className="g-4">
              {/* Basic Info */}
              <Col lg={6}>
                <Card className="border-0 shadow-sm h-100">
                  <Card.Header className="bg-primary text-white">
                    <h6 className="mb-0">
                      <i className="fas fa-id-card me-2"></i>
                      Informasi Dasar
                    </h6>
                  </Card.Header>
                  <Card.Body>
                    <div className="row g-3">
                      <div className="col-6">
                        <small className="text-muted d-block">Employee ID</small>
                        <div className="fw-bold">{employee.eid}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Nama Lengkap</small>
                        <div className="fw-bold">{employee.name}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Email</small>
                        <div>{employee.email || 'N/A'}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">No. Telefon</small>
                        <div>{employee.phone || 'N/A'}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Tanggal Lahir</small>
                        <div>{formatDate(employee.birth_date)}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Status</small>
                        <div>
                          <Badge bg={employee.status === 'active' ? 'success' : 'secondary'}>
                            {employee.status === 'active' ? 'Aktif' : employee.status}
                          </Badge>
                        </div>
                      </div>
                    </div>
                  </Card.Body>
                </Card>
              </Col>

              {/* Work Info */}
              <Col lg={6}>
                <Card className="border-0 shadow-sm h-100">
                  <Card.Header className="bg-info text-white">
                    <h6 className="mb-0">
                      <i className="fas fa-briefcase me-2"></i>
                      Informasi Pekerjaan
                    </h6>
                  </Card.Header>
                  <Card.Body>
                    <div className="row g-3">
                      <div className="col-6">
                        <small className="text-muted d-block">Posisi</small>
                        <div className="fw-bold">{employee.role}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Divisi</small>
                        <div className="fw-bold">{employee.division_name}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Tanggal Bergabung</small>
                        <div>{formatDate(employee.join_date)}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Masa Kerja</small>
                        <div>
                          <Badge bg="info">
                            {Math.round(employee.tenure_months || 0)} bulan
                          </Badge>
                        </div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Kontrak Saat Ini</small>
                        <div>{getContractStatusBadge(employee.current_contract_type)}</div>
                      </div>
                      <div className="col-6">
                        <small className="text-muted d-block">Berakhir Kontrak</small>
                        <div>{formatDate(employee.contract_end_date)}</div>
                      </div>
                    </div>
                  </Card.Body>
                </Card>
              </Col>

              {/* Education */}
              <Col lg={12}>
                <Card className="border-0 shadow-sm">
                  <Card.Header className="bg-warning text-white">
                    <h6 className="mb-0">
                      <i className="fas fa-graduation-cap me-2"></i>
                      Pendidikan & Kecocokan
                    </h6>
                  </Card.Header>
                  <Card.Body>
                    <Row>
                      <Col md={4}>
                        <small className="text-muted d-block">Tingkat Pendidikan</small>
                        <div className="fw-bold">{employee.education_level || 'N/A'}</div>
                      </Col>
                      <Col md={4}>
                        <small className="text-muted d-block">Jurusan</small>
                        <div className="fw-bold">{employee.major || 'N/A'}</div>
                      </Col>
                      <Col md={4}>
                        <small className="text-muted d-block">Status Kecocokan</small>
                        <div>{getMatchStatusBadge(employee.education_job_match)}</div>
                      </Col>
                    </Row>
                  </Card.Body>
                </Card>
              </Col>
            </Row>
          </Tab.Pane>

          {/* Analysis Tab */}
          <Tab.Pane eventKey="analysis">
            <Row className="g-4">
              <Col lg={8}>
                <Card className="border-0 shadow-sm">
                  <Card.Header className="bg-success text-white">
                    <h6 className="mb-0">
                      <i className="fas fa-brain me-2"></i>
                      Data Intelligence Analysis
                    </h6>
                  </Card.Header>
                  <Card.Body>
                    {employee.data_mining_recommendation ? (
                      <div className="row g-3">
                        <div className="col-md-6">
                          <small className="text-muted d-block">Rekomendasi Duration</small>
                          <div className="fw-bold text-success">
                            {employee.data_mining_recommendation.recommended_duration === 0 ? 'Permanent' : 
                             `${employee.data_mining_recommendation.recommended_duration} bulan`}
                          </div>
                        </div>
                        <div className="col-md-6">
                          <small className="text-muted d-block">Risk Level</small>
                          <div>
                            <Badge bg={
                              employee.data_mining_recommendation.risk_level === 'Low' ? 'success' :
                              employee.data_mining_recommendation.risk_level === 'Medium' ? 'warning' : 'danger'
                            }>
                              {employee.data_mining_recommendation.risk_level}
                            </Badge>
                          </div>
                        </div>
                        <div className="col-12">
                          <small className="text-muted d-block">Analisis</small>
                          <div className="border p-3 rounded bg-light">
                            {employee.data_mining_recommendation.recommendation_reason || 'Analisis berdasarkan data historis dan pola karyawan serupa.'}
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="text-center py-4">
                        <i className="fas fa-exclamation-triangle fa-2x text-muted mb-3"></i>
                        <h6>Data Intelligence Belum Tersedia</h6>
                        <p className="text-muted">Analisis AI sedang diproses untuk karyawan ini.</p>
                      </div>
                    )}
                  </Card.Body>
                </Card>
              </Col>

              <Col lg={4}>
                <Card className="border-0 shadow-sm">
                  <Card.Header className="bg-secondary text-white">
                    <h6 className="mb-0">
                      <i className="fas fa-clock me-2"></i>
                      Status Timeline
                    </h6>
                  </Card.Header>
                  <Card.Body>
                    <div className="mb-3">
                      <small className="text-muted d-block">Sisa Kontrak</small>
                      <div className="fw-bold">
                        {employee.days_to_contract_end > 0 ? 
                          `${employee.days_to_contract_end} hari` : 
                          'Kontrak berakhir'
                        }
                      </div>
                    </div>
                    {employee.days_to_contract_end <= 30 && employee.days_to_contract_end > 0 && (
                      <div className="alert alert-warning">
                        <i className="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perlu Review!</strong><br/>
                        Kontrak akan berakhir dalam {employee.days_to_contract_end} hari.
                      </div>
                    )}
                  </Card.Body>
                </Card>
              </Col>
            </Row>
          </Tab.Pane>

          {/* Contracts Tab */}
          <Tab.Pane eventKey="contracts">
            <Card className="border-0 shadow-sm">
              <Card.Header className="bg-dark text-white">
                <h6 className="mb-0">
                  <i className="fas fa-history me-2"></i>
                  Riwayat Kontrak
                </h6>
              </Card.Header>
              <Card.Body>
                {employeeDetail.contracts && employeeDetail.contracts.length > 0 ? (
                  <div className="table-responsive">
                    <table className="table table-hover">
                      <thead>
                        <tr>
                          <th>Jenis Kontrak</th>
                          <th>Tanggal Mulai</th>
                          <th>Tanggal Berakhir</th>
                          <th>Durasi</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        {employeeDetail.contracts.map((contract, index) => (
                          <tr key={index}>
                            <td>{getContractStatusBadge(contract.type)}</td>
                            <td>{formatDate(contract.start_date)}</td>
                            <td>{formatDate(contract.end_date) || 'Permanent'}</td>
                            <td>{contract.duration_months ? `${contract.duration_months} bulan` : 'N/A'}</td>
                            <td>
                              <Badge bg={contract.status === 'active' ? 'success' : 'secondary'}>
                                {contract.status === 'active' ? 'Aktif' : contract.status}
                              </Badge>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <i className="fas fa-file-contract fa-2x text-muted mb-3"></i>
                    <h6>Belum Ada Riwayat Kontrak</h6>
                    <p className="text-muted">Data kontrak akan muncul setelah karyawan memiliki kontrak.</p>
                  </div>
                )}
              </Card.Body>
            </Card>
          </Tab.Pane>
        </Tab.Content>
      </Tab.Container>
    </Container>
  );
};

export default EmployeeDetailView; 