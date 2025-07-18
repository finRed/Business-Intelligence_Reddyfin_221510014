import React, { useState, useRef } from 'react';
import { Card, Table, Button, Alert, ProgressBar, Badge, Modal, Row, Col, Form } from 'react-bootstrap';
import axios from 'axios';

const UploadCSV = () => {
  const [file, setFile] = useState(null);
  const [uploadStatus, setUploadStatus] = useState('');
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [uploadResults, setUploadResults] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState(null);
  const [validationErrors, setValidationErrors] = useState([]);
  const [previewData, setPreviewData] = useState([]);
  const [showPreview, setShowPreview] = useState(false);
  const fileInputRef = useRef(null);

  const handleFileSelect = (event) => {
    const selectedFile = event.target.files[0];
    if (selectedFile && selectedFile.type === 'text/csv') {
      setFile(selectedFile);
      setUploadStatus('');
      setValidationErrors([]);
      setUploadResults(null);
      setShowPreview(false);
      
      // Preview first few rows
      const reader = new FileReader();
      reader.onload = (e) => {
        const text = e.target.result;
        const lines = text.split('\n').slice(0, 6); // Header + 5 data rows
        const preview = lines.map((line, index) => {
          const columns = line.split(';');
          return {
            rowIndex: index,
            data: columns,
            isHeader: index === 0
          };
        });
        setPreviewData(preview);
        setShowPreview(true);
      };
      reader.readAsText(selectedFile);
    } else {
      setUploadStatus('error');
      setValidationErrors(['Please select a valid CSV file']);
    }
  };

  const handleUpload = async () => {
    if (!file) {
      setUploadStatus('error');
      setValidationErrors(['No file selected']);
      return;
    }

    const formData = new FormData();
    formData.append('csv_file', file);

    setIsUploading(true);
    setUploadProgress(0);
    setUploadStatus('uploading');

    try {
      const response = await axios.post(
        'http://localhost/web_srk_BI/backend/api/csv_upload_endpoint.php',
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
          withCredentials: true,
          onUploadProgress: (progressEvent) => {
            const percentCompleted = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
            setUploadProgress(percentCompleted);
          },
        }
      );

      if (response.data.success) {
        setUploadStatus('success');
        setUploadResults(response.data.results);
        setValidationErrors([]);
      } else {
        setUploadStatus('error');
        setValidationErrors(response.data.errors || ['Upload failed']);
        setUploadResults(null);
      }
    } catch (error) {
      console.error('Upload error:', error);
      console.error('Response status:', error.response?.status);
      console.error('Response data:', error.response?.data);
      
      setUploadStatus('error');
      
      let errorMessages = [];
      
      if (error.response?.data?.error) {
        errorMessages.push(error.response.data.error);
        
        // Add debug info if available
        if (error.response.data.debug) {
          errorMessages.push(`Debug: ${error.response.data.debug.file}:${error.response.data.debug.line}`);
        }
      } else if (error.response?.status === 401) {
        errorMessages.push('Unauthorized - Please login again');
      } else if (error.response?.status === 403) {
        errorMessages.push('Forbidden - Only HR can upload CSV files');
      } else if (error.response?.status === 500) {
        errorMessages.push('Server error - Check server logs');
      } else if (error.message === 'Network Error') {
        errorMessages.push('Network error - Check if server is running');
      } else {
        errorMessages.push(error.message || 'Unknown error occurred');
      }
      
      setValidationErrors(errorMessages);
      setUploadResults(null);
    } finally {
      setIsUploading(false);
      setUploadProgress(0);
    }
  };

  const resetUpload = () => {
    setFile(null);
    setUploadStatus('');
    setUploadResults(null);
    setValidationErrors([]);
    setPreviewData([]);
    setShowPreview(false);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const viewEmployeeDetail = (employee) => {
    setSelectedEmployee(employee);
    setShowModal(true);
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'success':
        return <Badge bg="success">✅ Berhasil</Badge>;
      case 'warning':
        return <Badge bg="warning">⚠️ Warning</Badge>;
      case 'error':
        return <Badge bg="danger">❌ Error</Badge>;
      case 'skipped':
        return <Badge bg="secondary">⏭️ Dilewati</Badge>;
      default:
        return <Badge bg="light">-</Badge>;
    }
  };

  const getResignStatusBadge = (isEarlyResign, resignDate) => {
    if (!resignDate) return <Badge bg="success">Active</Badge>;
    if (isEarlyResign) return <Badge bg="warning">Early Resign</Badge>;
    return <Badge bg="info">Normal Resign</Badge>;
  };

  return (
    <div className="container-fluid mt-4">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2>📁 Upload Data Karyawan CSV</h2>
        <Button variant="outline-secondary" onClick={resetUpload}>
          🔄 Reset
        </Button>
      </div>

      {/* Upload Section */}
      <Card className="mb-4">
        <Card.Header style={{ backgroundColor: '#007bff', color: 'white' }}>
          <h5 className="mb-0">📤 Upload File CSV</h5>
        </Card.Header>
        <Card.Body>
          <Row>
            <Col md={8}>
              <Form.Group className="mb-3">
                <Form.Label>Pilih File CSV</Form.Label>
                <Form.Control
                  type="file"
                  accept=".csv"
                  onChange={handleFileSelect}
                  ref={fileInputRef}
                />
                <Form.Text className="text-muted">
                  Format: CSV dengan separator semicolon (;). Maksimal 50MB.
                </Form.Text>
              </Form.Group>
            </Col>
            <Col md={4} className="d-flex align-items-end">
              <Button
                variant="primary"
                onClick={handleUpload}
                disabled={!file || isUploading}
                className="w-100"
              >
                {isUploading ? '📡 Uploading...' : '📤 Upload CSV'}
              </Button>
            </Col>
          </Row>

          {/* Upload Progress */}
          {isUploading && (
            <div className="mt-3">
              <div className="d-flex justify-content-between mb-2">
                <span>Progress Upload:</span>
                <span>{uploadProgress}%</span>
              </div>
              <ProgressBar now={uploadProgress} animated />
            </div>
          )}

          {/* Status Messages */}
          {uploadStatus === 'success' && (
            <Alert variant="success" className="mt-3">
              ✅ <strong>Upload berhasil!</strong> Data karyawan telah diproses.
            </Alert>
          )}

          {uploadStatus === 'error' && (
            <Alert variant="danger" className="mt-3">
              ❌ <strong>Upload gagal!</strong>
              <ul className="mb-0 mt-2">
                {validationErrors.map((error, index) => (
                  <li key={index}>{error}</li>
                ))}
              </ul>
            </Alert>
          )}
        </Card.Body>
      </Card>

      {/* CSV Preview */}
      {showPreview && previewData.length > 0 && (
        <Card className="mb-4">
          <Card.Header style={{ backgroundColor: '#28a745', color: 'white' }}>
            <h5 className="mb-0">👁️ Preview Data CSV</h5>
          </Card.Header>
          <Card.Body>
            <Table responsive bordered size="sm">
              <tbody>
                {previewData.map((row, index) => (
                  <tr key={index} className={row.isHeader ? 'table-primary' : ''}>
                    <td width="50px">
                      <strong>{row.isHeader ? 'Header' : `Row ${index}`}</strong>
                    </td>
                    {row.data.slice(0, 10).map((cell, cellIndex) => (
                      <td key={cellIndex} className="text-truncate" style={{ maxWidth: '150px' }}>
                        {cell || '-'}
                      </td>
                    ))}
                    {row.data.length > 10 && (
                      <td className="text-muted">... +{row.data.length - 10} kolom lagi</td>
                    )}
                  </tr>
                ))}
              </tbody>
            </Table>
          </Card.Body>
        </Card>
      )}

      {/* Upload Results */}
      {uploadResults && (
        <Card>
          <Card.Header style={{ backgroundColor: '#17a2b8', color: 'white' }}>
            <h5 className="mb-0">📊 Hasil Upload</h5>
          </Card.Header>
          <Card.Body>
            {/* Summary Stats */}
            <Row className="mb-4">
              <Col md={2}>
                <div className="text-center">
                  <h4 className="text-primary">{uploadResults.summary?.total_processed || 0}</h4>
                  <small className="text-muted">Total Diproses</small>
                </div>
              </Col>
              <Col md={2}>
                <div className="text-center">
                  <h4 className="text-success">{uploadResults.summary?.successful || 0}</h4>
                  <small className="text-muted">Berhasil</small>
                </div>
              </Col>
              <Col md={2}>
                <div className="text-center">
                  <h4 className="text-warning">{uploadResults.summary?.warnings || 0}</h4>
                  <small className="text-muted">Warning</small>
                </div>
              </Col>
              <Col md={2}>
                <div className="text-center">
                  <h4 className="text-danger">{uploadResults.summary?.errors || 0}</h4>
                  <small className="text-muted">Error</small>
                </div>
              </Col>
              <Col md={2}>
                <div className="text-center">
                  <h4 className="text-secondary">{uploadResults.summary?.skipped || 0}</h4>
                  <small className="text-muted">Dilewati</small>
                </div>
              </Col>
            </Row>

            {/* Detailed Results Table */}
            {uploadResults.details && uploadResults.details.length > 0 && (
              <Table responsive striped hover>
                <thead style={{ backgroundColor: '#17a2b8', color: 'white' }}>
                  <tr>
                    <th>No</th>
                    <th>EID</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Kontrak</th>
                    <th>Resign Status</th>
                    <th>Divisi</th>
                    <th>Pesan</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {uploadResults.details.map((result, index) => (
                    <tr key={index}>
                      <td>{index + 1}</td>
                      <td>
                        <strong>{result.eid || result.original_eid}</strong>
                      </td>
                      <td>{result.name}</td>
                      <td>{getStatusBadge(result.status)}</td>
                      <td>
                        {result.contract_info && (
                          <div>
                            <Badge bg={result.contract_info.current_type === 'probation' ? 'warning' : 'info'}>
                              {result.contract_info.current_type}
                            </Badge>
                            {result.contract_info.total_contracts && (
                              <small className="d-block text-muted">
                                {result.contract_info.total_contracts} kontrak
                              </small>
                            )}
                          </div>
                        )}
                      </td>
                      <td>
                        {getResignStatusBadge(result.early_resign, result.resign_date)}
                      </td>
                      <td>
                        <div>{result.division}</div>
                        {result.division_created && (
                          <small className="text-success">✨ Divisi baru</small>
                        )}
                      </td>
                      <td>
                        <small>{result.message}</small>
                        {result.warnings && result.warnings.length > 0 && (
                          <div>
                            {result.warnings.map((warning, wIndex) => (
                              <small key={wIndex} className="d-block text-warning">
                                ⚠️ {warning}
                              </small>
                            ))}
                          </div>
                        )}
                      </td>
                      <td>
                        <Button
                          size="sm"
                          variant="outline-primary"
                          onClick={() => viewEmployeeDetail(result)}
                        >
                          👁️ Detail
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}

            {/* Processing Logs */}
            {uploadResults.logs && uploadResults.logs.length > 0 && (
              <div className="mt-4">
                <h6>📋 Log Pemrosesan</h6>
                <div className="bg-light p-3 rounded" style={{ maxHeight: '300px', overflowY: 'auto' }}>
                  {uploadResults.logs.map((log, index) => (
                    <div key={index} className="mb-1">
                      <small className={`text-${log.type === 'error' ? 'danger' : log.type === 'warning' ? 'warning' : 'muted'}`}>
                        [{log.timestamp}] {log.message}
                      </small>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </Card.Body>
        </Card>
      )}

      {/* Employee Detail Modal */}
      <Modal show={showModal} onHide={() => setShowModal(false)} size="lg">
        <Modal.Header closeButton>
          <Modal.Title>👤 Detail Karyawan</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          {selectedEmployee && (
            <Row>
              <Col md={6}>
                <h6>📋 Informasi Dasar</h6>
                <table className="table table-sm">
                  <tbody>
                    <tr>
                      <td><strong>EID:</strong></td>
                      <td>{selectedEmployee.eid}</td>
                    </tr>
                    <tr>
                      <td><strong>Nama:</strong></td>
                      <td>{selectedEmployee.name}</td>
                    </tr>
                    <tr>
                      <td><strong>Posisi:</strong></td>
                      <td>{selectedEmployee.role}</td>
                    </tr>
                    <tr>
                      <td><strong>Divisi:</strong></td>
                      <td>{selectedEmployee.division}</td>
                    </tr>
                    <tr>
                      <td><strong>Join Date:</strong></td>
                      <td>{selectedEmployee.join_date}</td>
                    </tr>
                    <tr>
                      <td><strong>Resign Date:</strong></td>
                      <td>{selectedEmployee.resign_date || 'Active'}</td>
                    </tr>
                  </tbody>
                </table>
              </Col>
              <Col md={6}>
                <h6>📝 Detail Kontrak</h6>
                {selectedEmployee.contract_info && (
                  <div>
                    <p><strong>Tipe Saat Ini:</strong> {selectedEmployee.contract_info.current_type}</p>
                    <p><strong>Total Kontrak:</strong> {selectedEmployee.contract_info.total_contracts}</p>
                    {selectedEmployee.early_resign && (
                      <Alert variant="warning" className="p-2">
                        ⚠️ <strong>Early Resign Detected</strong><br/>
                        Karyawan resign sebelum kontrak berakhir
                      </Alert>
                    )}
                  </div>
                )}
                
                <h6 className="mt-3">📊 Status Pemrosesan</h6>
                <p><strong>Status:</strong> {getStatusBadge(selectedEmployee.status)}</p>
                <p><strong>Pesan:</strong> {selectedEmployee.message}</p>
              </Col>
            </Row>
          )}
        </Modal.Body>
        <Modal.Footer>
          <Button variant="secondary" onClick={() => setShowModal(false)}>
            Tutup
          </Button>
        </Modal.Footer>
      </Modal>

      {/* Instructions */}
      <Card className="mt-4">
        <Card.Header style={{ backgroundColor: '#6f42c1', color: 'white' }}>
          <h5 className="mb-0">📋 Panduan Upload CSV</h5>
        </Card.Header>
        <Card.Body>
          <Row>
            <Col md={6}>
              <h6>📊 Format CSV yang Diharapkan:</h6>
              <ul>
                <li>Separator: Semicolon (;)</li>
                <li>Encoding: UTF-8</li>
                <li>EID akan di-generate otomatis</li>
                <li>Join Date = Probation Start</li>
                <li>Probation End = Join Date + 3 bulan</li>
                <li>Designation akan di-mapping ke Role</li>
                <li>Major akan di-mapping ke Jurusan</li>
              </ul>
            </Col>
            <Col md={6}>
              <h6>🔍 Deteksi Otomatis:</h6>
              <ul>
                <li>Early Resign: Resign sebelum kontrak berakhir</li>
                <li>Tipe Kontrak terakhir dari kolom CONTRACT EXPIRED</li>
                <li>Auto-create Divisi jika belum ada</li>
                <li>Validasi format Birth Date</li>
                <li>Validasi format Email</li>
                <li>Mapping Gender (Male/Female)</li>
                <li>Filter otomatis: Graduate Development Program dilewati</li>
              </ul>
            </Col>
          </Row>
        </Card.Body>
      </Card>
    </div>
  );
};

export default UploadCSV; 