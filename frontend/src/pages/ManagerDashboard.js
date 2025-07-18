import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  PointElement,
  LineElement,
  Filler,
} from 'chart.js';
import { Bar, Doughnut } from 'react-chartjs-2';
import { useAuth } from '../App';
import { Card, Container, Row, Col, Nav, Tab, Table, Badge, Button, Modal, Alert, Spinner, ProgressBar, Form } from 'react-bootstrap';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler
);

const ManagerDashboard = () => {
  const [analytics, setAnalytics] = useState(null);
  const [educationAnalysis, setEducationAnalysis] = useState(null);
  const [divisionIntelligence, setDivisionIntelligence] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('overview');
  const { user } = useAuth();
  const [showEmployeeModal, setShowEmployeeModal] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState(null);
  const [employeeDetail, setEmployeeDetail] = useState(null);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [autoRefresh, setAutoRefresh] = useState(false); // Auto-refresh disabled by default
  const [refreshInterval, setRefreshInterval] = useState(null);
  const [lastRefresh, setLastRefresh] = useState(null);

  const [showAllProfiles, setShowAllProfiles] = useState(false);
  
  // Reset showAllProfiles when selectedEmployee changes
  useEffect(() => {
    setShowAllProfiles(false);
  }, [selectedEmployee]);

  const [recommendationData, setRecommendationData] = useState({
    recommendation_type: 'extend',
    recommended_duration: '',
    contract_start_date: '',
    reason: ''
  });
  const [alert, setAlert] = useState({ show: false, message: '', type: '' });
  const [processedRecommendations, setProcessedRecommendations] = useState([]);

  // Handler for closing modal with data refresh
  const handleCloseModal = () => {
    console.log('🔄 Closing modal and refreshing data...');
    setShowEmployeeModal(false);
    // Refresh data immediately to ensure latest contract status
    setTimeout(() => {
      fetchManagerData();
      fetchProcessedRecommendations();
    }, 500); // Small delay to ensure any backend processing is complete
  };

  useEffect(() => {
    if (user?.role === 'manager') {
      fetchManagerData();
      fetchProcessedRecommendations();
    } else if (user?.role === 'hr') {
      showAlert('HR tidak dapat membuat rekomendasi. Hanya manager yang dapat membuat rekomendasi karyawan.', 'warning');
    }
  }, [user]);

  // Separate useEffect for auto-refresh management
  useEffect(() => {
    if (autoRefresh && user?.role === 'manager') {
      const interval = setInterval(() => {
        console.log('🔄 Auto-refreshing manager data and processed recommendations...');
        fetchManagerData();
        fetchProcessedRecommendations();
      }, 30000); // 30 seconds
      
      setRefreshInterval(interval);
      
      return () => {
        if (interval) {
          clearInterval(interval);
        }
      };
    } else if (refreshInterval) {
      clearInterval(refreshInterval);
      setRefreshInterval(null);
    }
  }, [autoRefresh, user]);

  // Cleanup on component unmount
  useEffect(() => {
    return () => {
      if (refreshInterval) {
        clearInterval(refreshInterval);
      }
    };
  }, []);

  // Toggle auto-refresh function
  const toggleAutoRefresh = () => {
    setAutoRefresh(!autoRefresh);
    if (autoRefresh) {
      console.log('🛑 Auto-refresh disabled');
    } else {
      console.log('▶️ Auto-refresh enabled (30 seconds)');
    }
  };

  // Manual refresh function  
  const handleManualRefresh = () => {
    console.log('🔄 Manual refresh clicked');
    fetchManagerData();
    fetchProcessedRecommendations();
  };

  // Update recommendation form when employee detail is loaded
  useEffect(() => {
    if (employeeDetail && employeeDetail.dynamic_intelligence && selectedEmployee) {
      const intelligence = employeeDetail.dynamic_intelligence;
      const duration = intelligence.optimal_duration;
      const category = intelligence.match_category;
      
      // PROPER FORM DEFAULTS based on dynamic intelligence
      let defaultType = 'kontrak2';
      let defaultDuration = duration || 6;
      
      // Check for Non-IT major elimination first
      const nonItMajors = [
        'kedokteran', 'farmasi', 'hukum', 'akuntansi', 'manajemen', 'ekonomi',
        'psikologi', 'bahasa', 'sastra', 'pendidikan', 'sejarah', 'geografi',
        'biologi', 'kimia', 'fisika', 'matematika', 'pertanian', 'kehutanan',
        'perikanan', 'kedokteran hewan', 'arsitektur', 'sipil', 'mesin',
        'elektro', 'industri', 'teknik', 'seni', 'desain', 'komunikasi',
        'jurnalistik', 'sosiologi', 'antropologi', 'ilmu politik',
        'hubungan internasional', 'administrasi', 'keperawatan', 'kebidanan',
        'gizi', 'kesehatan masyarakat', 'olahraga', 'keolahragaan'
      ];
      
      const employeeMajor = selectedEmployee.major ? selectedEmployee.major.toLowerCase().trim() : '';
      const isNonIT = nonItMajors.some(nonItMajor => employeeMajor.includes(nonItMajor));
      
      if (isNonIT) {
        // Automatic elimination for Non-IT majors
        defaultType = 'terminate';
        defaultDuration = 0;
      }
      // Untuk MATCH employees, ikuti dynamic intelligence
      else if (selectedEmployee.education_job_match === 'Match') {
        if (category === 'Siap Permanent' || duration === 0) {
          defaultType = 'permanent';
          defaultDuration = 0;
        } else if (category === 'Berpotensi Kontrak 3' || duration >= 18) {
          defaultType = 'kontrak3';
          defaultDuration = duration || 18;
        } else if (category === 'Berpotensi Kontrak 2' || duration >= 6) {
          defaultType = 'kontrak2';
          defaultDuration = duration || 12;
        } else {
          defaultType = 'kontrak2';
          defaultDuration = 6;
        }
      } 
      // UNMATCH employees - POLICY: Tetap evaluasi kontrak, tidak langsung permanent
      else if (selectedEmployee.education_job_match === 'Unmatch') {
        // UNMATCH - determine evaluation contract based on current contract
        // KONSISTEN dengan getRecommendationStatusBadge di tabel
        if (selectedEmployee.current_contract_type === 'probation') {
          defaultType = 'kontrak2';
          defaultDuration = 6;
        } else if (selectedEmployee.current_contract_type === '1') {
          defaultType = 'kontrak2';
          defaultDuration = 6;
        } else if (selectedEmployee.current_contract_type === '2') {
          defaultType = 'kontrak3';
          defaultDuration = 6;
        } else if (selectedEmployee.current_contract_type === '3') {
          // POLICY: Kontrak 3 unmatch tetap evaluasi kontrak 3, tidak langsung permanent
          // Meskipun dynamic intelligence suggest permanent, kita tetap konservatif
          // untuk unmatch employees karena tingginya risk resign
          defaultType = 'kontrak3';
          defaultDuration = 6;
        } else {
          defaultType = 'kontrak2';
          defaultDuration = 6;
        }
      }
      
      // Special handling untuk reasoning text - kosong sesuai permintaan user
      let reasonPrefix = '';
      
      setRecommendationData({
        eid: selectedEmployee.eid,
        employee_name: selectedEmployee.name,
        recommendation_type: defaultType,
        recommended_duration: defaultDuration,
        reason: ''
      });
    }
  }, [employeeDetail, selectedEmployee]);

  const fetchManagerData = async () => {
    try {
      setLoading(true);
      
      // Use the simplified API endpoint that works without dynamic intelligence
      const timestamp = new Date().getTime();
      const analyticsRes = await axios.get(`/manager_analytics_simple.php?t=${timestamp}`, {
        withCredentials: true
      });

      console.log('🔥 NEW API Response:', analyticsRes.data);
      
      if (analyticsRes.data.success) {
        const rawData = analyticsRes.data.data;
        
        // Transform the data structure to match what the frontend expects
        const transformedData = {
          ...rawData,
          division_stats: {
            total_employees: rawData.statistics?.total_employees || 0,
            data_mining_summary: {
              match_employees: rawData.statistics?.match_employees || 0,
              recommended_extensions: rawData.statistics?.extension_employees || 0,
              high_risk_employees: rawData.statistics?.high_risk_employees || 0
            },

          }
        };
        
        setAnalytics(transformedData);
        console.log('✅ Analytics data set:', transformedData);
        
        // Debug contract end dates
        console.log('📅 Contract End Dates Debug:', rawData.employees?.slice(0, 3).map(emp => ({
          name: emp.name,
          contract_type: emp.current_contract_type,
          contract_end: emp.contract_end,
          days_to_end: emp.days_to_contract_end
        })));
        

        
        // Use the same analytics data for education analysis
        if (rawData.employees) {
          const employees = rawData.employees;
          const matchDistribution = {
            'Match': employees.filter(emp => emp.education_job_match === 'Match').length,
            'Unmatch': employees.filter(emp => emp.education_job_match === 'Unmatch').length
          };
          
          setEducationAnalysis({
            education_analysis: employees,
            match_distribution: matchDistribution,
            contract_progression: employees.filter(emp => 
              emp.contract_status === 'Ready for Review' || 
              emp.tenure_months >= 3
            ).map(emp => ({
              name: emp.name,
              status: emp.contract_status,
              recommendation: emp.data_mining_recommendation?.reason || 'Standard review'
            }))
          });
          
          setDivisionIntelligence({
            performance_predictions: employees.map(emp => ({
              name: emp.name,
              success_probability: emp.intelligence_data?.success_probability || 75,
              risk_level: emp.intelligence_data?.risk_level || 'Low',
              contract_recommendation: emp.data_mining_recommendation
            })),
            urgent_contract_decisions: employees.filter(emp => 
              emp.intelligence_data?.risk_level === 'High' ||
              emp.contract_status === 'Ready for Review'
            )
          });
        }
        setLastRefresh(new Date()); // Add this line to track last refresh
      }
      
    } catch (error) {
      console.error('Error fetching manager data:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchProcessedRecommendations = async () => {
    try {
      const response = await axios.get('http://localhost/web_srk_BI/backend/api/recommendations.php?action=processed', {
        withCredentials: true
      });

      if (response.data.success) {
        setProcessedRecommendations(response.data.data);
        console.log('✅ Processed recommendations loaded:', response.data.data);
        
        // Debug: Check mapping structure
        console.log('🔍 Processed recommendations mapping debug:', response.data.data.map(rec => ({
          rec_id: rec.recommendation_id,
          employee_name: rec.employee?.name,
          employee_eid: rec.employee?.eid,
          status: rec.status,
          duration: rec.recommended_duration
        })));
        
        // Debug: Check for resign status
        const resignRecs = response.data.data.filter(rec => rec.status === 'resign');
        if (resignRecs.length > 0) {
          console.log('🔍 Found resign recommendations:', resignRecs);
        }
      } else {
        console.error('Failed to fetch processed recommendations:', response.data.error);
      }
    } catch (error) {
      console.error('Error fetching processed recommendations:', error);
    }
  };

  const fetchEmployeeDetail = async (eid) => {
    setLoadingDetail(true);
    try {
      console.log('Fetching employee detail for eid:', eid);
      const response = await fetch(`http://localhost/web_srk_BI/backend/api/employees.php?action=employee_data_mining_detail&eid=${eid}`, {
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
        }
      });
      
      console.log('Response status:', response.status);
      console.log('Response OK:', response.ok);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const responseText = await response.text();
      console.log('Raw response text (first 200 chars):', responseText.substring(0, 200));
      
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (jsonError) {
        console.error('JSON Parse Error:', jsonError);
        console.log('Full response text:', responseText);
        throw new Error('Server returned invalid JSON. Check browser console for details.');
      }
      
      console.log('Employee detail API Response:', result);
      
      if (result.success) {
        setEmployeeDetail(result.data);
        console.log('Employee detail loaded successfully');
        return result.data; // Return the data for await
      } else {
        console.error('API Error:', result.error);
        setEmployeeDetail(null);
        throw new Error(result.error || 'Failed to load employee data');
      }
    } catch (error) {
      console.error('Network/Parse error:', error);
      setEmployeeDetail(null);
      throw error; // Re-throw for handleEmployeeClick to catch
    } finally {
      setLoadingDetail(false);
    }
  };

  const handleEmployeeClick = async (employee) => {
    console.log('🔍 handleEmployeeClick called for:', employee.name, 'EID:', employee.eid);
    setSelectedEmployee(employee);
    setLoadingDetail(true);
    
    // Always show the modal first (will show loading state)
    setShowEmployeeModal(true);
    
    // Fetch fresh employee detail data
    try {
      console.log('⏳ Fetching employee detail...');
      const employeeData = await fetchEmployeeDetail(employee.eid);
      console.log('✅ Employee data loaded, setting state');
      console.log('   - Employee Contract Type:', employeeData?.employee?.current_contract_type);
      console.log('   - Dynamic Intelligence:', employeeData?.dynamic_intelligence);
      console.log('   - Match Category:', employeeData?.dynamic_intelligence?.match_category);
      console.log('   - Optimal Duration:', employeeData?.dynamic_intelligence?.optimal_duration);
      
      // Set the employee detail state
      setEmployeeDetail(employeeData);
      setLoadingDetail(false);
    } catch (error) {
      console.error('❌ Failed to fetch employee detail:', error);
      setLoadingDetail(false);
      // Don't close modal, show error in modal instead
      setEmployeeDetail({
        error: true,
        errorMessage: error.message,
        employee: {
          name: employee.name,
          eid: employee.eid,
          current_contract_type: employee.current_contract_type
        }
      });
    }
    
    // Initialize recommendation data based on employee status - will be updated with dynamic intelligence later
    const defaultType = employee.education_job_match === 'Unmatch' ? 'kontrak2' : 'kontrak2';
    setRecommendationData({
      eid: employee.eid,
      employee_name: employee.name,
      recommendation_type: defaultType,
      recommended_duration: 6,
      reason: 'Rekomendasi akan diupdate berdasarkan dynamic intelligence analysis...'
    });
  };

  const handleCreateRecommendation = async (employee) => {
    console.log('🔧 handleCreateRecommendation called for:', employee.name, 'Contract:', employee.current_contract_type);
    
    // Call the same function as handleEmployeeClick to open detail modal with form
    await handleEmployeeClick(employee);
  };

  const submitRecommendation = async () => {
    try {
      // Enhanced validation with fallback
      const employeeEid = employeeDetail?.employee?.eid || selectedEmployee?.eid;
      const employeeName = employeeDetail?.employee?.name || selectedEmployee?.name;
      
      if (!employeeEid) {
        showAlert('Data karyawan tidak lengkap. Silakan muat ulang halaman.', 'danger');
        console.error('Employee EID not found. Employee detail:', employeeDetail, 'Selected employee:', selectedEmployee);
        return;
      }

      if (!recommendationData.recommendation_type) {
        showAlert('Pilih jenis rekomendasi terlebih dahulu', 'warning');
        return;
      }

      // Validasi reason dihapus - reason boleh kosong sesuai permintaan user

      // Validate duration for contracts that require it
      if (['kontrak2', 'kontrak3'].includes(recommendationData.recommendation_type) && 
          (!recommendationData.recommended_duration || recommendationData.recommended_duration === '0')) {
        showAlert('Durasi kontrak harus diisi untuk jenis kontrak yang dipilih', 'warning');
        return;
      }

      // Show loading state
      showAlert('Sedang mengirim rekomendasi...', 'info');

      const formData = new FormData();
      formData.append('action', 'create');
      formData.append('eid', employeeEid);
      formData.append('recommendation_type', recommendationData.recommendation_type);
      formData.append('recommended_duration', recommendationData.recommended_duration || '0');
      formData.append('contract_start_date', recommendationData.contract_start_date || '');
      formData.append('reason', recommendationData.reason || '');
      
      // Enhanced system recommendation with dynamic intelligence
      const systemRec = employeeDetail?.dynamic_intelligence ? 
        `Rekomendasi sistem: ${employeeDetail.dynamic_intelligence.match_category || 'Analysis'} (${employeeDetail.dynamic_intelligence.optimal_duration || 'N/A'} bulan), Confidence: ${employeeDetail.dynamic_intelligence.confidence_level || 'Medium'}, Sample Size: ${employeeDetail.dynamic_intelligence.sample_size || 0}` :
        `Rekomendasi sistem: ${recommendationData.recommendation_type} kontrak ${recommendationData.recommended_duration || 'N/A'} bulan berdasarkan analisis data mining`;
      
      formData.append('system_recommendation', systemRec);

      console.log('=== SENDING RECOMMENDATION ===');
      console.log('Employee EID:', employeeEid);
      console.log('Employee Name:', employeeName);
      console.log('Recommendation Type:', recommendationData.recommendation_type);
      console.log('Recommended Duration:', recommendationData.recommended_duration);
      console.log('Reason:', recommendationData.reason);
      console.log('FormData contents:');
      for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
      }
      console.log('Employee detail structure:', employeeDetail);
      console.log('Selected employee:', selectedEmployee);

      const response = await axios.post('http://localhost/web_srk_BI/backend/api/recommendations.php', formData, {
        withCredentials: true,
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        timeout: 30000 // 30 second timeout
      });

      console.log('=== RESPONSE RECEIVED ===');
      console.log('Response status:', response.status);
      console.log('Response data:', response.data);

      if (response.data.success) {
        showAlert('Rekomendasi berhasil dibuat dan dikirim ke HR', 'success');
        handleCloseModal();
        // Reset form
        setRecommendationData({
          recommendation_type: '',
          recommended_duration: '',
          contract_start_date: '',
          reason: ''
        });
        // Refresh data after creating recommendation
        fetchManagerData();
        fetchProcessedRecommendations();
      } else {
        console.error('Server error response:', response.data);
        showAlert(response.data.error || 'Gagal membuat rekomendasi', 'danger');
      }
    } catch (error) {
      console.error('Error creating recommendation:', error);
      
      // Enhanced error handling
      if (error.response) {
        // Server responded with error status
        console.error('Server error response:', error.response.data);
        console.error('Server error status:', error.response.status);
        const errorMsg = error.response.data?.error || error.response.data?.message || 'Server error';
        showAlert(`Gagal membuat rekomendasi: ${errorMsg}`, 'danger');
      } else if (error.request) {
        // Request was made but no response received
        console.error('Network error - no response received:', error.request);
        showAlert('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.', 'danger');
      } else {
        // Something happened in setting up the request
        console.error('Request setup error:', error.message);
        showAlert('Terjadi kesalahan saat mengirim rekomendasi. Silakan coba lagi.', 'danger');
      }
    }
  };

  const showAlert = (message, type) => {
    setAlert({ show: true, message, type });
    setTimeout(() => setAlert({ show: false, message: '', type: '' }), 5000);
  };

  const getMatchDistributionChart = () => {
    if (!educationAnalysis?.match_distribution) {
      return {
        labels: ['Match', 'Unmatch'],
        datasets: [{
          label: 'Distribusi Kecocokan',
          data: [0, 0],
          backgroundColor: ['#28a745', '#dc3545'],
          borderWidth: 1
        }]
      };
    }

    const data = educationAnalysis.match_distribution;
    return {
      labels: ['Match', 'Unmatch'],
      datasets: [{
        label: 'Distribusi Kecocokan',
        data: [data.Match || 0, data.Unmatch || 0],
        backgroundColor: ['#28a745', '#dc3545'],
        borderWidth: 1
      }]
    };
  };



  const getRiskLevelChart = () => {
    if (!analytics?.employees || analytics.employees.length === 0) {
      return {
        labels: ['Low Risk', 'Medium Risk', 'High Risk'],
        datasets: [{
          label: 'Distribusi Risk Level',
          data: [0, 0, 0],
          backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
          borderWidth: 1
        }]
      };
    }

    const employees = analytics.employees;
    const lowRisk = employees.filter(emp => emp.intelligence_data?.risk_level === 'Low').length;
    const mediumRisk = employees.filter(emp => emp.intelligence_data?.risk_level === 'Medium').length;
    const highRisk = employees.filter(emp => emp.intelligence_data?.risk_level === 'High').length;
    
    console.log('🔍 Risk Level Debug:', { 
      totalEmployees: employees.length,
      lowRisk, 
      mediumRisk, 
      highRisk,
      sampleData: employees.slice(0, 3).map(emp => ({
        name: emp.name,
        risk_level: emp.intelligence_data?.risk_level
      }))
    });

    return {
      labels: ['Low Risk', 'Medium Risk', 'High Risk'],
      datasets: [{
        label: 'Distribusi Risk Level',
        data: [lowRisk, mediumRisk, highRisk],
        backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
        borderWidth: 1
      }]
    };
  };

  const getContractRecommendationChart = () => {
    if (!analytics?.employees || analytics.employees.length === 0) {
      return {
        labels: ['6 bulan', '12 bulan', '18 bulan', '24 bulan', '36 bulan'],
        datasets: [{
          label: 'Jumlah Rekomendasi',
          data: [0, 0, 0, 0, 0],
          backgroundColor: 'rgba(0, 123, 255, 0.8)',
          borderColor: '#007bff',
          borderWidth: 2
        }]
      };
    }

    const employees = analytics.employees;
    const duration6 = employees.filter(emp => emp.intelligence_data?.recommended_duration === 6).length;
    const duration12 = employees.filter(emp => emp.intelligence_data?.recommended_duration === 12).length;
    const duration18 = employees.filter(emp => emp.intelligence_data?.recommended_duration === 18).length;
    const duration24 = employees.filter(emp => emp.intelligence_data?.recommended_duration === 24).length;
    const duration36 = employees.filter(emp => emp.intelligence_data?.recommended_duration === 36).length;

    return {
      labels: ['6 bulan', '12 bulan', '18 bulan', '24 bulan', '36 bulan'],
      datasets: [{
        label: 'Jumlah Rekomendasi',
        data: [duration6, duration12, duration18, duration24, duration36],
        backgroundColor: 'rgba(0, 123, 255, 0.8)',
        borderColor: '#007bff',
        borderWidth: 2
      }]
    };
  };

  // Fungsi untuk mendapatkan rekomendasi kontrak berdasarkan data mining
  const getDataMiningRecommendation = (employee) => {
    // Gunakan data mining recommendation dari backend
    if (employee.data_mining_recommendation) {
      return {
        type: 'data_mining',
        duration: `${employee.data_mining_recommendation.recommended_duration} bulan`,
        risk_level: employee.data_mining_recommendation.risk_level,
        confidence: employee.data_mining_recommendation.confidence_level,
        reasoning: employee.data_mining_recommendation.reasoning || []
      };
    }

    // Fallback untuk backward compatibility
    const matchStatus = employee.education_job_match || 'Unmatch';
    const tenure = employee.tenure_months || 0;
    const currentContract = employee.current_contract_type;

    if (currentContract === 'probation' && tenure >= 3) {
      if (matchStatus === 'Match') {
        return {
          type: 'permanent',
          reason: 'Good education-job match',
          duration: 'Permanent',
          confidence: 'High'
        };
      } else {
        return {
          type: 'review',
          reason: 'No education-job match, need performance evaluation',
          duration: 'Review diperlukan',
          confidence: 'Low'
        };
      }
    }

    if (tenure >= 12 && currentContract !== 'permanent') {
      return {
        type: 'extension',
        reason: 'Long tenure with good performance',
        duration: 'Extension recommended',
        confidence: 'High'
      };
    }

    return {
      type: 'monitor',
      reason: 'Continue monitoring',
      duration: 'Monitoring berkelanjutan',
      confidence: 'Medium'
    };
  };

  // Function to calculate match status locally (same logic as detail modal)
  const calculateMatchStatusLocal = (employee) => {
    const major = (employee.major || '').toUpperCase();
    const role = (employee.role || '').toUpperCase();
    const designation = (employee.designation || '').toUpperCase();
    
    // IT Education Keywords (Updated to match backend)
    const itEducationKeywords = [
      'INFORMATION TECHNOLOGY', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 'SISTEM INFORMASI',
      'COMPUTER SCIENCE', 'SOFTWARE ENGINEERING', 'INFORMATIKA', 'TEKNIK KOMPUTER',
      'KOMPUTER', 'TEKNOLOGI INFORMASI', 'INFORMATION SYSTEM', 'INFORMATICS ENGINEERING',
      'INFORMATIC ENGINEERING', 'COMPUTER ENGINEERING', 'INFORMATION MANAGEMENT',
      'INFORMATICS MANAGEMENT', 'MANAGEMENT INFORMATIKA', 'SISTEM KOMPUTER',
      'KOMPUTERISASI AKUNTANASI', 'COMPUTERIZED ACCOUNTING', 'COMPUTATIONAL SCIENCE',
      'INFORMATICS', 'INFORMATICS TECHNOLOGY', 'COMPUTER AND INFORMATICS ENGINEERING',
      'ENGINEERING INFORMATIC', 'INDUSTRIAL ENGINEERING_INFORMATIC',
      // New IT Education Majors from CSV Analysis
      'STATISTICS', 'TECHNOLOGY MANAGEMENT', 'ELECTRICAL ENGINEERING', 'ELECTRONICS ENGINEERING',
      'MASTER IN INFORMATICS', 'ICT', 'COMPUTER SCIENCE & ELECTRONICS', 'COMPUTER TELECOMMUNICATION',
      'TELECOMMUNICATION ENGINEERING', 'TELECOMMUNICATIONS ENGINEERING', 'TELECOMMUNICATION & MEDIA',
      'TEKNIK ELEKTRO', 'COMPUTER SCINCE', 'COMPUTER SCIENCES & ENGINEERING', 'COMPUTER SYSTEM',
      'COMPUTER SIENCE', 'COMPUTER TECHNOLOGY', 'COMPUTER SCINCE', 'INFORMASTION SYSTEM',
      'INFORMATICS TECHNIQUE', 'INFORMATIOCS', 'INFORMATICS TECHNOLOGY', 'TEKNIK KOMPUTER & JARINGAN',
      'INFORMATION ENGINEERING', 'SOFTWARE ENGINEERING TECHNOLOGY', 'DIGITAL MEDIA TECHNOLOGY',
      'INFORMATIC ENGINEETING', 'INFORMATION SYTEM', 'INFORMASTION SYSTEM', 'MATERIALS ENGINEERING',
      'NETWORK MANAGEMENT', 'INFORMATICS SYSTEM', 'BUSINESS INFORMATION SYSTEM', 'PHYSICS ENGINEERING',
      'MANAGEMENT INFORMATION SYSTEM', 'TELECOMUNICATION ENGINEERING'
    ];
    
    // IT Job Keywords (Updated to match backend)
    const itJobKeywords = [
      'DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'TECHNICAL', 'FRONTEND', 'BACKEND', 
      'FULLSTACK', 'FULL STACK', 'JAVA', 'NET', '.NET', 'API', 'ETL', 'MOBILE',
      'ANDROID', 'WEB', 'UI/UX', 'FRONT END', 'BACK END', 'PEGA', 'RPA',
      'ANALYST', 'BUSINESS ANALYST', 'SYSTEM ANALYST', 'DATA ANALYST', 'DATA SCIENTIST',
      'QUALITY ASSURANCE', 'QA', 'TESTER', 'TEST ENGINEER', 'CONSULTANT',
      'IT CONSULTANT', 'TECHNOLOGY CONSULTANT', 'TECHNICAL CONSULTANT',
      'PRODUCT OWNER', 'SCRUM MASTER', 'PMO', 'IT OPERATION', 'DEVOPS',
      'SYSTEM ADMINISTRATOR', 'DATABASE ADMINISTRATOR', 'NETWORK',
      'IT QUALITY ASSURANCE', 'TESTING ENGINEER', 'TESTER SUPPORT', 'JR TESTER',
      'IT TESTER', 'TESTING SPECIALIST', 'TESTING COORDINATOR', 'QUALITY TESTER',
      'JR QUALITY ASSURANCE', 'QUALITY ASSURANCE TESTER', 'TESTING ENGINEER SPECIALIST',
      'APPRENTICE TESTER', 'JR TESTER LEVEL 1', 'LEAD QA', 'LEAD BACKEND DEVELOPER',
      // New IT Roles from CSV Analysis
      'PROJECT MANAGER', 'IT PROJECT MANAGER', 'TECHNICAL PROJECT MANAGER', 'ASSISTANT PROJECT MANAGER',
      'BI DEVELOPER', 'BI COGNOS DEVELOPER', 'POWER BI DEVELOPER', 'SR. BI DEVELOPER',
      'DATA MODELER', 'DATABASE REPORT ENGINEER', 'REPORT DESIGNER',
      'MIDDLEWARE DEVELOPER', 'DATASTAGE DEVELOPER', 'ETL DATASTAGE DEVELOPER', 'IBM DATASTAGE',
      'ODOO DEVELOPER', 'SR. ODOO DEVELOPER', 'TALEND CONSULTANT', 'MFT CONSULTANT',
      'CYBER SECURITY OPERATION', 'IT SECURITY OPERATIONS', 'SECURITY ENGINEER', 'SENIOR SECURITY ENGINEER',
      'CYBER SECURITY POLICY', 'IT INFRA & SECURITY OFFICER', 'INFRASTRUCTURE',
      'LEAD BACKEND DEVELOPER', 'LEAD FRONTEND DEVELOPER', 'SR. TECHNOLOGY CONSULTANT',
      'GRADUATE DEVELOPMENT PROGRAM', 'JUNIOR CONSULTANT', 'JR. CONSULTANT', 'JR CONSULTANT',
      'TECHNICAL LEAD', 'TECH LEAD', 'SOLUTION ANALYST', 'DATA ENGINEER',
      'IT SUPPORT', 'SYSTEM SUPPORT', 'PRODUCTION SUPPORT', 'HELPDESK',
      'TECHNOLOGY CONSULTANT', 'SR. ETL DEVELOPER', 'JR. ETL CONSULTANT',
      'TECHNICAL RESEARCH & DEVELOPMENT CONSULTANT', 'PRESALES', 'IT TRAINER',
      'TECHNICAL TRAINER', 'ASSISTANT TRAINER', 'JAVA TRAINER',
      'MOBILE DEVELOPER', 'ANDROID DEVELOPER', 'IOS DEVELOPER', 'FRONT END MOBILE DEVELOPER',
      'WEB FRONT END DEVELOPER', 'FRONETEND DEVELOPER', 'FRONT END DEVELOPER',
      'SR .NET DEVELOPER', 'JR .NET DEVELOPER', '.NET DEVELOPER', 'SR. .NET DEVELOPER',
      'FULL STACK DEVELOPER', 'FULLSTACK DEVELOPER', 'UI/UX DEVELOPER',
      'RPA DEVELOPER', 'RPA TRAINEE', 'PEGA DEVELOPER', 'SR. PEGA DEVELOPER',
      // Additional keywords to match backend logic
      'ADMIN', 'ADMINISTRATOR', 'USER', 'END', 'HELP', 'DESK', 'SUPPORT', 'SERVICE'
    ];
    
    // Check IT Education
    const isItEducation = itEducationKeywords.some(keyword => major.includes(keyword));
    
    // Check IT Job (check both role and designation)
    const isItJob = itJobKeywords.some(keyword => 
      role.includes(keyword) || designation.includes(keyword)
    );
    
    return (isItEducation && isItJob) ? 'Match' : 'Unmatch';
  };

  const getMatchLevelBadge = (employee) => {
    // Use local calculation instead of backend data to ensure consistency
    const matchStatus = calculateMatchStatusLocal(employee);
    if (matchStatus === 'Match') return <Badge bg="success">Match</Badge>;
    return <Badge bg="danger">Unmatch</Badge>;
  };

  const getRiskLevelBadge = (riskLevel) => {
    if (riskLevel === 'Low') return <Badge bg="success">Risiko Rendah</Badge>;
    if (riskLevel === 'Medium') return <Badge bg="warning">Risiko Sedang</Badge>;
    return <Badge bg="danger">Risiko Tinggi</Badge>;
  };

  const getContractStatusBadge = (employee) => {
    const contractType = employee.current_contract_type;
    const contractEnd = employee.contract_end;
    const daysToEnd = employee.days_to_contract_end;
    
    const badges = {
      'probation': { variant: 'warning', text: 'Probation' },
      '1': { variant: 'info', text: 'Kontrak 1' },
      '2': { variant: 'primary', text: 'Kontrak 2' },
      '3': { variant: 'secondary', text: 'Kontrak 3' },
      'permanent': { variant: 'success', text: 'Permanent' },
      null: { variant: 'light', text: 'N/A' }
    };

    const badge = badges[contractType] || badges[null];
    
    // Format tanggal berakhir kontrak
    let endDateText = '';
    if (contractEnd && contractType !== 'permanent') {
      const endDate = new Date(contractEnd);
      endDateText = ` (berakhir: ${endDate.toLocaleDateString('id-ID')})`;
      
      // Tambahkan peringatan jika kontrak akan berakhir dalam 30 hari
      if (daysToEnd <= 30 && daysToEnd > 0) {
        endDateText += ` - ${daysToEnd} hari lagi`;
      }
    }
    
    if (contractType === 'probation' && daysToEnd >= 90) {
      return (
        <div>
          <Badge bg="warning" className="border border-danger">
            Probation (Ready for Review)
          </Badge>
          {endDateText && <small className="text-muted d-block mt-1">{endDateText}</small>}
        </div>
      );
    }

    return (
      <div>
        <Badge bg={badge.variant}>{badge.text}</Badge>
        {endDateText && <small className="text-muted d-block mt-1">{endDateText}</small>}
      </div>
    );
  };

  // Function to get consistent duration recommendation for both sections
  const getConsistentDurationRecommendation = (employee) => {
    if (!employee) return '6 bulan';
    
    const duration = employee?.intelligence_data?.recommended_duration;
    const category = employee?.intelligence_data?.match_category;
    
    // Check for Non-IT elimination first
    const nonItMajors = [
      'kedokteran', 'farmasi', 'hukum', 'akuntansi', 'manajemen', 'ekonomi',
      'psikologi', 'bahasa', 'sastra', 'pendidikan', 'sejarah', 'geografi',
      'biologi', 'kimia', 'fisika', 'matematika', 'pertanian', 'kehutanan',
      'perikanan', 'kedokteran hewan', 'arsitektur', 'sipil', 'mesin',
      'elektro', 'industri', 'teknik', 'seni', 'desain', 'komunikasi',
      'jurnalistik', 'sosiologi', 'antropologi', 'ilmu politik',
      'hubungan internasional', 'administrasi', 'keperawatan', 'kebidanan',
      'gizi', 'kesehatan masyarakat', 'olahraga', 'keolahragaan'
    ];
    
    const employeeMajor = employee.major ? employee.major.toLowerCase().trim() : '';
    const isNonIT = nonItMajors.some(nonItMajor => employeeMajor.includes(nonItMajor));
    
    if (isNonIT || category === 'Tidak Direkomendasikan') {
      return 'Tidak Direkomendasikan';
    } else if (category === 'Sudah Permanent') {
      return 'Sudah Permanent';
    }
    // UNMATCH employees - evaluation contract
    else if (employee.education_job_match === 'Unmatch') {
      if (employee.current_contract_type === '3') {
        return 'Kontrak 3 (Evaluasi)';
      } else if (employee.current_contract_type === '2') {
        return 'Kontrak 3 (Evaluasi)';
      } else {
        return 'Kontrak 2 (Evaluasi)';
      }
    }
    // MATCH employees - follow dynamic intelligence
    else if (employee.education_job_match === 'Match') {
      if (employee.current_contract_type === '3' && 
          (category === 'Siap Permanent' || duration === 0)) {
        return 'Permanent';
      } else if (duration === 0 && category !== 'Tidak Direkomendasikan') {
        return 'Permanent';
      } else if (duration && duration > 0) {
        return `${duration} bulan`;
      } else {
        return '6 bulan'; // Default evaluation duration - matching the image
      }
    } else {
      return '6 bulan'; // Default fallback duration - matching the image
    }
  };

  // Function to calculate consistent statistics for both Data Intelligence & Dynamic Intelligence sections
  const getConsistentStatistics = (employee, employeeDetail) => {
    // Use actual historical patterns data from backend dynamic_intelligence.php
    const dynamicIntelligence = employeeDetail?.dynamic_intelligence;
    const historicalPatterns = dynamicIntelligence?.historical_patterns;
    

    
          if (historicalPatterns && historicalPatterns.sample_size > 0) {
        // Use EXACT data from backend calculations
        const masihAktif = parseFloat(historicalPatterns.success_rate) || 0;
        const sudahResign = parseFloat(historicalPatterns.resign_rate) || 0;
        const earlyResignRate = parseFloat(historicalPatterns.early_resign_rate) || 0;
        const avgTenure = parseFloat(historicalPatterns.avg_duration) || 0;
        const sampleSize = parseInt(historicalPatterns.sample_size) || parseInt(historicalPatterns.total_similar) || 0;
        
        // Get Early Resign Risk from resign analysis data
        const resignAnalysis = dynamicIntelligence?.resign_analysis;
        let earlyResignRisk = 0;
        
        // Priority 1: Use optimal duration recommendation resign rate (but only if it's reasonable)
        if (resignAnalysis?.optimal_duration_recommendation?.early_resign_rate) {
          const optimalRisk = parseFloat(resignAnalysis.optimal_duration_recommendation.early_resign_rate);
          // Only use if it's not the unreliable 100% default
          if (optimalRisk < 90) {
            earlyResignRisk = optimalRisk;
          }
        } 
        
        // Priority 2: Use current contract analysis
        if (earlyResignRisk === 0 && resignAnalysis?.current_contract_analysis?.early_resign_rate) {
          earlyResignRisk = parseFloat(resignAnalysis.current_contract_analysis.early_resign_rate);
        }
        
        // Priority 3: Use data insights early resign analysis
        if (earlyResignRisk === 0 && historicalPatterns.data_insights?.early_resign_analysis?.rate) {
          earlyResignRisk = parseFloat(historicalPatterns.data_insights.early_resign_analysis.rate);
        }
        
        // Priority 4: Use actual early resign rate from historical patterns
        if (earlyResignRisk === 0 && earlyResignRate > 0) {
          earlyResignRisk = earlyResignRate;
        }
        
        // Fallback: Use reasonable estimate based on profile success rate
        if (earlyResignRisk === 0) {
          // Lower success rate = higher early resign risk
          earlyResignRisk = Math.max(5, Math.min(25, (100 - masihAktif) * 0.3));
        }
        

        
        return {
          masihAktif: Math.round(masihAktif * 10) / 10,
          sudahResign: Math.round(sudahResign * 10) / 10,
          earlyResignRate: Math.round(earlyResignRate * 10) / 10,
          earlyResignRisk: Math.round(earlyResignRisk * 10) / 10,
          avgTenure: Math.round(avgTenure * 10) / 10,
          sampleSize: sampleSize
        };
      }
    
    // Fallback when no historical patterns data available
    if (!employee) {

      return {
        masihAktif: 70,
        sudahResign: 30,
        earlyResignRate: 25,
        earlyResignRisk: 33.8,
        avgTenure: 17.6,
        sampleSize: 15
      };
    }

    // Fallback calculation based on employee characteristics
    const educationScore = employee.intelligence_data?.education_score || 0;
    const tenure = employee.tenure_months || 0;
    const contractType = employee.current_contract_type;
    const sampleSize = employee.intelligence_data?.sample_size || 15;
    
    let masihAktif = educationScore >= 3 ? 70 : educationScore >= 2 ? 70 : 45;
    if (contractType === 'permanent') masihAktif = 95;
    else if (contractType === '2') masihAktif = 70;
    
    const sudahResign = 100 - masihAktif;
    const earlyResignRate = contractType === '2' ? 25 : (contractType === 'permanent' ? 3 : 25);
    const earlyResignRisk = contractType === '2' ? 33.8 : (contractType === 'permanent' ? 8.2 : 33.8);
    const avgTenure = contractType === '2' ? 17.6 : (contractType === 'permanent' ? 36.2 : 17.6);
    

    
    return {
      masihAktif: Math.min(95, Math.max(30, masihAktif)),
      sudahResign: Math.min(70, Math.max(5, sudahResign)),
      earlyResignRate: Math.min(50, Math.max(3, earlyResignRate)),
      earlyResignRisk: Math.min(65.0, Math.max(5.0, earlyResignRisk)),
      avgTenure: Math.max(8.0, avgTenure),
      sampleSize: sampleSize
    };
  };

  // Function to get consistent contract recommendation (same as table)
  const getConsistentContractRecommendation = (employee) => {
    if (!employee) return 'Loading...';
    
    // Use the same logic as getRecommendationStatusBadge but return text only
    if (employee.current_contract_type === 'permanent') {
      return 'Permanent';
    }
    
    // Check for Non-IT major elimination
    const nonItMajors = [
      'kedokteran', 'farmasi', 'hukum', 'akuntansi', 'manajemen', 'ekonomi',
      'psikologi', 'bahasa', 'sastra', 'pendidikan', 'sejarah', 'geografi',
      'biologi', 'kimia', 'fisika', 'matematika', 'pertanian', 'kehutanan',
      'perikanan', 'kedokteran hewan', 'arsitektur', 'sipil', 'mesin',
      'elektro', 'industri', 'teknik', 'seni', 'desain', 'komunikasi',
      'jurnalistik', 'sosiologi', 'antropologi', 'ilmu politik',
      'hubungan internasional', 'administrasi', 'keperawatan', 'kebidanan',
      'gizi', 'kesehatan masyarakat', 'olahraga', 'keolahragaan'
    ];
    
    const employeeMajor = employee.major ? employee.major.toLowerCase().trim() : '';
    const isNonIT = nonItMajors.some(nonItMajor => employeeMajor.includes(nonItMajor));
    
    if (isNonIT) {
      return 'Tidak Direkomendasikan';
    }
    
    // If contract still has long time (>30 days), show waiting status
    if (employee.days_to_contract_end > 30) {
      return 'Kontrak Masih Berlaku';
    }
    
    // If has pending recommendation
    if (employee.has_pending_recommendation) {
      return 'Menunggu Persetujuan HR';
    }
    
    // For Unmatch employees (education score < 2) - evaluation contract
    const educationScore = employee.intelligence_data?.education_score || 0;
    if (educationScore < 2) {
      // Determine evaluation contract type based on current contract
      let contractType = '';
      switch (employee.current_contract_type) {
        case 'probation':
          contractType = 'Kontrak 2 (Evaluasi)';
          break;
        case '1':
          contractType = 'Kontrak 2 (Evaluasi)';
          break;
        case '2':
          contractType = 'Kontrak 3 (Evaluasi)';
          break;
        case '3':
          contractType = 'Kontrak 3 (Evaluasi)';
          break;
        default:
          contractType = 'Probation (Evaluasi)';
          break;
      }
      return contractType;
    }
    
    // For Match employees - show contract recommendation based on progression
    const duration = employee.intelligence_data?.recommended_duration;
    
    // For Contract 3, duration 0 means Permanent
    if ((employee.current_contract_type === '3' || employee.current_contract_type === 'kontrak3') && duration === 0) {
      return 'Permanent';
    }
              // For probation employees - progression logic
     else if (employee.current_contract_type === 'probation') {
       // From probation, next contract should be Kontrak 2 (12 months) or Kontrak 3 based on evaluation
       const educationScore = employee.intelligence_data?.education_score || 0;
       if (educationScore >= 2) {
         return 'Kontrak 2'; // Good match - standard 12 month contract
       } else {
         return 'Kontrak 3 (Evaluasi)'; // Poor match - evaluation contract
       }
     } else if (employee.current_contract_type === '1' || employee.current_contract_type === '2') {
       const recommendedType = employee.intelligence_data?.match_category || 'Kontrak 3';
       return recommendedType;
     } else if (employee.current_contract_type === '3') {
       return 'Permanent';
     }
    
    // Default fallback
    return employee.intelligence_data?.match_category || 'Kontrak 2';
  };

  // Function to get recommended contract type based on dynamic intelligence
  const getRecommendedContractType = (employeeDetail) => {
    const employee = employeeDetail.employee;
    const dynamicIntelligence = employeeDetail.dynamic_intelligence;
    
    // If we have dynamic intelligence data
    if (dynamicIntelligence && dynamicIntelligence.optimal_duration !== undefined) {
      const duration = dynamicIntelligence.optimal_duration;
      const matchCategory = dynamicIntelligence.match_category;
      
      // If employee is not recommended
      if (matchCategory === 'Tidak Direkomendasikan') {
        return '❌ Tidak Direkomendasikan';
      }
      
      // Based on current contract and recommended duration
      const currentContract = employee.current_contract_type;
      
      // If permanent is recommended (duration = 0)
      if (duration === 0) {
        return 'Permanent';
      }
      
      // If currently on probation
      if (currentContract === 'probation') {
        if (duration <= 6) {
          return 'Kontrak 1';
        } else if (duration <= 12) {
          return 'Kontrak 2';
        } else {
          return 'Kontrak 3';
        }
      }
      
      // If currently on Kontrak 1 or 2
      if (currentContract === '1' || currentContract === '2') {
        if (duration === 0) {
          return 'Permanent';
        } else {
          return 'Kontrak 3';
        }
      }
      
      // If currently on Kontrak 3
      if (currentContract === '3') {
        return 'Permanent';
      }
      
      // Default based on duration
      if (duration <= 6) {
        return 'Kontrak 1';
      } else if (duration <= 12) {
        return 'Kontrak 2';
      } else if (duration <= 24) {
        return 'Kontrak 3';
      } else {
        return 'Permanent';
      }
    }
    
    // Fallback - use match category to determine recommendation
    const matchStatus = employee.education_job_match || 'Unmatch';
    const currentContract = employee.current_contract_type;
    
    if (matchStatus === 'Match') {
      // Good match - recommend progression
      if (currentContract === 'probation') {
        return 'Kontrak 2';
      } else if (currentContract === '1' || currentContract === '2') {
        return 'Kontrak 3';
      } else if (currentContract === '3') {
        return 'Permanent';
      }
      return '2️⃣ Kontrak 2';
    } else {
      // Poor match - recommend review or short contract
      return '🔍 Review Diperlukan';
    }
  };

  const getRecommendationStatusBadge = (employee) => {
    if (employee.current_contract_type === 'permanent') {
      return <Badge bg="secondary">-</Badge>;
    } 
    
    // Check for Non-IT major elimination
    const nonItMajors = [
      'kedokteran', 'farmasi', 'hukum', 'akuntansi', 'manajemen', 'ekonomi',
      'psikologi', 'bahasa', 'sastra', 'pendidikan', 'sejarah', 'geografi',
      'biologi', 'kimia', 'fisika', 'matematika', 'pertanian', 'kehutanan',
      'perikanan', 'kedokteran hewan', 'arsitektur', 'sipil', 'mesin',
      'elektro', 'industri', 'teknik', 'seni', 'desain', 'komunikasi',
      'jurnalistik', 'sosiologi', 'antropologi', 'ilmu politik',
      'hubungan internasional', 'administrasi', 'keperawatan', 'kebidanan',
      'gizi', 'kesehatan masyarakat', 'olahraga', 'keolahragaan'
    ];
    
    const employeeMajor = employee.major ? employee.major.toLowerCase().trim() : '';
    const isNonIT = nonItMajors.some(nonItMajor => employeeMajor.includes(nonItMajor));
    
    if (isNonIT) {
      return <Badge bg="danger">TIDAK DIREKOMENDASIKAN</Badge>;
    }
    
    // PRIORITAS: Tampilkan rekomendasi kontrak ke depannya, bukan status approval
    
    // Jika kontrak masih berlaku lama (>30 hari), tampilkan status tunggu
    if (employee.days_to_contract_end > 30) {
      return <Badge bg="light" className="text-muted">⏳ Kontrak Masih Berlaku</Badge>;
    }
    
    // Jika ada pending recommendation, tampilkan status pending
    if (employee.has_pending_recommendation) {
      return <Badge bg="warning">🔄 Menunggu Persetujuan HR</Badge>;
    }
    
              // Untuk Unmatch employees (education score < 2) - evaluasi kontrak
    const educationScore = employee.intelligence_data?.education_score || 0;
    if (educationScore < 2) {
        // POLICY: Unmatch employees tetap evaluasi kontrak, tidak langsung permanent
        // meskipun dynamic intelligence mengatakan "Siap Permanent"
        // Ini untuk memitigasi risk tingginya resign rate pada unmatch employees
        
        const duration = employee.intelligence_data?.recommended_duration || 6;
      
      // Tentukan jenis kontrak evaluasi berdasarkan kontrak saat ini
      let contractType = '';
      switch (employee.current_contract_type) {
        case 'probation':
          contractType = 'Kontrak 2 (Evaluasi)';
          break;
        case '1':
          contractType = 'Kontrak 2 (Evaluasi)';
          break;
        case '2':
          contractType = 'Kontrak 3 (Evaluasi)';
          break;
        case '3':
          contractType = 'Kontrak 3 (Evaluasi)';
          break;
        default:
          contractType = 'Probation (Evaluasi)';
          break;
      }
      return (
        <div>
          <Badge bg="warning">{contractType}</Badge>
          <small className="text-muted d-block mt-1">Durasi: ({duration} bulan)</small>
        </div>
      );
    } 
    
    // Untuk Match employees - tampilkan rekomendasi kontrak berdasarkan progression
    const duration = employee.intelligence_data?.recommended_duration;
    let durationText = '';
    
    if (duration && duration > 0) {
      durationText = `(${duration} bulan)`;
    }
    
    // Untuk Kontrak 3, duration 0 berarti Permanent
    if ((employee.current_contract_type === '3' || employee.current_contract_type === 'kontrak3') && duration === 0) {
      return (
        <div>
          <Badge bg="success">PERMANENT</Badge>
          <small className="text-muted d-block mt-1">Rekomendasi: Kontrak Tetap</small>
        </div>
      );
    }
    // Determine contract type based on backend recommendation (fixed consistency)
    else if (employee.current_contract_type === 'probation') {
      // From probation, progress to Kontrak 2 or Kontrak 3 (Evaluasi) - never Kontrak 1
      const educationScore = employee.intelligence_data?.education_score || 0;
      let recommendedType, badgeVariant;
      
      if (educationScore >= 2) {
        recommendedType = 'Kontrak 2'; // Good match - standard 12 month contract
        badgeVariant = 'info';
      } else {
        recommendedType = 'Kontrak 3 (Evaluasi)'; // Poor match - evaluation contract
        badgeVariant = 'warning';
      }
      
      return (
        <div>
          <Badge bg={badgeVariant}>{recommendedType}</Badge>
          {durationText && <small className="text-muted d-block mt-1">Durasi: {durationText}</small>}
        </div>
      );
    } else if (employee.current_contract_type === '1' || employee.current_contract_type === '2') {
      // Use backend recommendation for progression contracts
      const recommendedType = employee.intelligence_data?.match_category || 'Kontrak 3';
      return (
        <div>
          <Badge bg="primary">{recommendedType}</Badge>
          {durationText && <small className="text-muted d-block mt-1">Durasi: {durationText}</small>}
        </div>
      );
    } else if (employee.current_contract_type === '3') {
      return (
        <div>
          <Badge bg="success">PERMANENT</Badge>
          <small className="text-muted d-block mt-1">Rekomendasi: Kontrak Tetap</small>
        </div>
      );
    } else {
      // Use backend recommendation for default cases
      const recommendedType = employee.data_mining_recommendation?.category || 'Kontrak 1';
      const badgeVariant = recommendedType.includes('Kontrak 1') ? 'primary' : 
                          recommendedType.includes('Probation') ? 'warning' : 'secondary';
      return (
        <div>
          <Badge bg={badgeVariant}>{recommendedType}</Badge>
          {durationText && <small className="text-muted d-block mt-1">Durasi: {durationText}</small>}
        </div>
      );
    }
  };

  const getApprovalStatusBadge = (employee) => {
    // Debug logging for specific employee
    if (employee.name === 'Ahmad Hamdi') {
      console.log('🔍 Debug Ahmad Hamdi approval status:');
      console.log('Employee EID:', employee.eid);
      console.log('Processed recommendations:', processedRecommendations);
      console.log('Matching recs:', processedRecommendations.filter(rec => rec.employee?.eid === employee.eid));
    }
    
    // Check if employee has recent processed recommendation
    const recentProcessedRec = processedRecommendations.find(rec => 
      (rec.employee?.eid === employee.eid)
    );
    
    if (recentProcessedRec) {
      // Show processed recommendation status with duration info
      const duration = recentProcessedRec.recommended_duration;
      let durationText = '';
      
      if (duration && duration > 0) {
        durationText = `(${duration} bulan)`;
      } else if (recentProcessedRec.recommendation_type === 'permanent') {
        durationText = '(Permanent)';
      }
      
      switch (recentProcessedRec.status) {
        case 'approved':
        case 'extended':
          return (
            <div>
              <Badge bg="success">✅ Disetujui</Badge>
              {durationText && <small className="text-muted d-block mt-1">{durationText}</small>}
            </div>
          );
        case 'rejected':
          return (
            <div>
              <Badge bg="danger">❌ Ditolak</Badge>
              {durationText && <small className="text-muted d-block mt-1">Durasi yang ditolak: {durationText}</small>}
            </div>
          );
        case 'resign':
          return (
            <div>
              <Badge bg="dark">👋 Resign</Badge>
              <small className="text-muted d-block mt-1">Karyawan mengundurkan diri</small>
            </div>
          );
        default:
          return (
            <div>
              <Badge bg="info">🔄 Diproses</Badge>
              {durationText && <small className="text-muted d-block mt-1">{durationText}</small>}
            </div>
          );
      }
    }
    
    // Jika ada pending recommendation
    if (employee.has_pending_recommendation) {
      return <Badge bg="warning">⏳ Menunggu HR</Badge>;
    }
    
    // Jika belum ada recommendation
    return <Badge bg="light" className="text-muted">-</Badge>;
  };

  if (loading) {
    return (
      <div className="container-fluid mt-4">
        <div className="text-center">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Loading...</span>
          </div>
          <p className="mt-2">Memuat data intelligence...</p>
        </div>
      </div>
    );
  }

  return (
    <Container fluid className="mt-3">
      {alert.show && (
        <Alert variant={alert.type} dismissible onClose={() => setAlert({ show: false, message: '', type: '' })}>
          {alert.message}
        </Alert>
      )}
      
      {/* Header dengan tema biru konsisten */}
      <div className="mb-4 p-4 rounded" style={{
        background: 'linear-gradient(135deg, #007bff 0%, #0056b3 100%)',
        color: 'white'
      }}>
        <Row className="align-items-center">
          <Col>
            <h2>Manager Dashboard</h2>
            <p className="mb-0">Manager Divisi {user.division_name}</p>
            <div className="d-flex align-items-center gap-2">
              <small>Analisis Kecocokan Pendidikan & Rekomendasi Kontrak</small>
              {autoRefresh && (
                <Badge bg="warning" className="d-flex align-items-center gap-1">
                  <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                  Auto-refresh aktif
                </Badge>
              )}
              {lastRefresh && (
                <small className="text-light opacity-75">
                  Terakhir diperbarui: {lastRefresh.toLocaleTimeString('id-ID')}
                </small>
              )}
            </div>
          </Col>
          <Col xs="auto">
            <div className="d-flex flex-column align-items-end gap-2">
              <div className="text-end">
                <h3 className="mb-0">{analytics?.division_stats?.total_employees || 0}</h3>
                <small>Karyawan Aktif</small>
              </div>
              <div className="d-flex gap-2">
                <Button 
                  variant="outline-light" 
                  size="sm" 
                  onClick={handleManualRefresh}
                >
                  🔄 Refresh
                </Button>
                <Button 
                  variant={autoRefresh ? "warning" : "outline-light"} 
                  size="sm" 
                  onClick={toggleAutoRefresh}
                  title={autoRefresh ? "Auto-refresh aktif (30 detik)" : "Auto-refresh tidak aktif"}
                >
                  {autoRefresh ? "⏸️ Stop Auto" : "▶️ Auto"}
                </Button>
              </div>
            </div>
          </Col>
        </Row>
      </div>

      {/* Navigation Tabs dengan tema biru */}
      <Tab.Container activeKey={activeTab} onSelect={setActiveTab}>
        <Nav variant="tabs" className="mb-4">
          <Nav.Item>
            <Nav.Link eventKey="overview" style={{
              color: activeTab === 'overview' ? '#fff' : '#007bff',
              backgroundColor: activeTab === 'overview' ? '#007bff' : 'transparent',
              borderColor: '#007bff'
            }}>
              Overview
            </Nav.Link>
          </Nav.Item>
          <Nav.Item>
            <Nav.Link eventKey="education" style={{
              color: activeTab === 'education' ? '#fff' : '#007bff',
              backgroundColor: activeTab === 'education' ? '#007bff' : 'transparent',
              borderColor: '#007bff'
            }}>
              Analisis Pendidikan
            </Nav.Link>
          </Nav.Item>

          <Nav.Item>
            <Nav.Link eventKey="employees" style={{
              color: activeTab === 'employees' ? '#fff' : '#007bff',
              backgroundColor: activeTab === 'employees' ? '#007bff' : 'transparent',
              borderColor: '#007bff'
            }}>
              Detail Karyawan
            </Nav.Link>
          </Nav.Item>
          <Nav.Item>
            <Nav.Link eventKey="processed" style={{
              color: activeTab === 'processed' ? '#fff' : '#007bff',
              backgroundColor: activeTab === 'processed' ? '#007bff' : 'transparent',
              borderColor: '#007bff'
            }}>
              Rekomendasi Diproses ({processedRecommendations.length})
            </Nav.Link>
          </Nav.Item>
        </Nav>

        <Tab.Content>
          {/* Overview Tab */}
          <Tab.Pane eventKey="overview">
            <Row className="mb-4">
              {/* Key Metrics Cards dengan gradient biru */}
              <Col md={4} className="mb-3">
                <Card className="h-100" style={{
                  background: 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)',
                  color: 'white'
                }}>
                  <Card.Body className="text-center">
                    <h3>{analytics?.division_stats?.data_mining_summary?.match_employees || 0}</h3>
                    <small>Match Employees</small>
                  </Card.Body>
                </Card>
              </Col>
              <Col md={4} className="mb-3">
                <Card className="h-100" style={{
                  background: 'linear-gradient(135deg, #ffc107 0%, #e0a800 100%)',
                  color: 'white'
                }}>
                  <Card.Body className="text-center">
                    <h3>{analytics?.division_stats?.data_mining_summary?.recommended_extensions || 0}</h3>
                    <small>Rekomendasi Perpanjangan</small>
                  </Card.Body>
                </Card>
              </Col>
              <Col md={4} className="mb-3">
                <Card className="h-100" style={{
                  background: 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)',
                  color: 'white'
                }}>
                  <Card.Body className="text-center">
                    <h3>{analytics?.division_stats?.data_mining_summary?.high_risk_employees || 0}</h3>
                    <small>High Risk Employees</small>
                  </Card.Body>
                </Card>
              </Col>
            </Row>

            <Row>
              <Col md={6} className="mb-4">
                <Card>
                  <Card.Header style={{ backgroundColor: '#007bff', color: 'white' }}>
                    <h5 className="mb-0">📊 Distribusi Tingkat Risiko</h5>
                  </Card.Header>
                  <Card.Body>
                    <Doughnut data={getRiskLevelChart()} options={{
                      responsive: true,
                      plugins: {
                        legend: { position: 'bottom' }
                      }
                    }} />
                  </Card.Body>
                </Card>
              </Col>
              <Col md={6} className="mb-4">
                <Card>
                  <Card.Header style={{ backgroundColor: '#007bff', color: 'white' }}>
                    <h5 className="mb-0">📈 Rekomendasi Durasi Kontrak</h5>
                  </Card.Header>
                  <Card.Body>
                    <Bar data={getContractRecommendationChart()} options={{
                      responsive: true,
                      plugins: {
                        legend: { display: false }
                      },
                      scales: {
                        y: { beginAtZero: true }
                      }
                    }} />
                  </Card.Body>
                </Card>
              </Col>
            </Row>


          </Tab.Pane>

          {/* Education Analysis Tab */}
          <Tab.Pane eventKey="education">
            <Card>
              <Card.Header style={{ backgroundColor: '#007bff', color: 'white' }}>
                <h5 className="mb-0">🎓 Analisis Kecocokan Pendidikan-Pekerjaan (Data Mining)</h5>
              </Card.Header>
              <Card.Body>
                <Table responsive striped>
                  <thead style={{ backgroundColor: '#007bff', color: 'white' }}>
                    <tr>
                      <th>Nama</th>
                      <th>Pendidikan</th>
                      <th>Posisi</th>
                      <th>Status Match</th>
                      <th>Rekomendasi Durasi</th>
                    </tr>
                  </thead>
                  <tbody>
                    {analytics?.employees?.map((emp, index) => (
                      <tr key={index}>
                        <td>
                          <strong>{emp.name}</strong>
                          <br />
                          <small className="text-muted">{emp.tenure_months} bulan</small>
                        </td>
                        <td>
                          <div>{emp.major}</div>
                          <small className="text-muted">{emp.education_level}</small>
                        </td>
                        <td>{emp.role}</td>
                        <td>
                          <div className="d-flex align-items-center">
                            {getMatchLevelBadge(emp)}
                          </div>
                        </td>
                        <td>
                          {/* FIX: Proper recommendation display with Non-IT check */}
                          {emp.current_contract_type === 'permanent' ? (
                            <span>-</span>
                          ) : (() => {
                            // Check for Non-IT elimination first
                            const nonItMajors = [
                              'kedokteran', 'farmasi', 'hukum', 'akuntansi', 'manajemen', 'ekonomi',
                              'psikologi', 'bahasa', 'sastra', 'pendidikan', 'sejarah', 'geografi',
                              'biologi', 'kimia', 'fisika', 'matematika', 'pertanian', 'kehutanan',
                              'perikanan', 'kedokteran hewan', 'arsitektur', 'sipil', 'mesin',
                              'elektro', 'industri', 'teknik', 'seni', 'desain', 'komunikasi',
                              'jurnalistik', 'sosiologi', 'antropologi', 'ilmu politik',
                              'hubungan internasional', 'administrasi', 'keperawatan', 'kebidanan',
                              'gizi', 'kesehatan masyarakat', 'olahraga', 'keolahragaan'
                            ];
                            const employeeMajor = (emp.major || '').toLowerCase().trim();
                            const isNonIT = nonItMajors.some(nonItMajor => employeeMajor.includes(nonItMajor));
                            
                            if (isNonIT) {
                              return <Badge bg="danger">TIDAK DIREKOMENDASIKAN</Badge>;
                            } else if (emp.education_job_match === 'Unmatch') {
                              // Unmatch - show contract type based on current contract
                              switch (emp.current_contract_type) {
                                case 'probation':
                                  return <Badge bg="warning">Kontrak 2 (Evaluasi)</Badge>;
                                case '1':
                                  return <Badge bg="warning">Kontrak 2 (Evaluasi)</Badge>;
                                case '2':
                                  return <Badge bg="warning">Kontrak 3 (Evaluasi)</Badge>;
                                case '3':
                                  return <Badge bg="warning">Kontrak 3 (Evaluasi)</Badge>;
                                default:
                                  return <Badge bg="warning">Probation (Evaluasi)</Badge>;
                              }
                            } else {
                              // Match - use intelligent recommendation based on profile and current contract
                              let recommendedType = 'Kontrak 1';
                              let badgeVariant = 'primary';
                              
                              // Get employee's education score and current contract type
                              const educationScore = emp.intelligence_data?.education_score || 0;
                              const currentContract = emp.current_contract_type;
                              const major = (emp.major || '').toLowerCase();
                              const role = (emp.role || '').toLowerCase();
                              
                              // Check if IT-related major
                              const isItMajor = major.includes('information') || major.includes('computer') || 
                                              major.includes('informatic') || major.includes('teknik informatika') ||
                                              major.includes('system') || major.includes('software');
                              
                              // Check role level
                              const isManager = role.includes('manager') || role.includes('head') || role.includes('director');
                              const isSenior = role.includes('senior') || role.includes('sr.');
                              const isJunior = role.includes('junior') || role.includes('jr.') || role.includes('trainee');
                              
                              // Determine recommendation based on profile and progression
                              if (currentContract === 'probation') {
                                if (isManager || (isSenior && isItMajor)) {
                                  recommendedType = 'Kontrak 3';
                                  badgeVariant = 'secondary';
                                } else if (isItMajor && educationScore >= 2) {
                                  recommendedType = 'Kontrak 2';
                                  badgeVariant = 'info';
                                } else {
                                  recommendedType = 'Kontrak 1';
                                  badgeVariant = 'primary';
                                }
                              } else if (currentContract === '1') {
                                if (isManager || (isSenior && isItMajor)) {
                                  recommendedType = 'PERMANENT';
                                  badgeVariant = 'success';
                                } else {
                                  recommendedType = 'Kontrak 2';
                                  badgeVariant = 'info';
                                }
                              } else if (currentContract === '2') {
                                if (isJunior && !isItMajor) {
                                  recommendedType = 'Kontrak 3';
                                  badgeVariant = 'secondary';
                                } else {
                                  recommendedType = 'PERMANENT';
                                  badgeVariant = 'success';
                                }
                              } else if (currentContract === '3') {
                                recommendedType = 'PERMANENT';
                                badgeVariant = 'success';
                              }
                              
                              return <Badge bg={badgeVariant}>{recommendedType}</Badge>;
                            }
                          })()}
                        </td>
                      </tr>
                    )) || []}
                  </tbody>
                </Table>
              </Card.Body>
            </Card>
          </Tab.Pane>



          {/* Employee Details Tab */}
          <Tab.Pane eventKey="employees">
            <Card>
              <Card.Header style={{ backgroundColor: '#007bff', color: 'white' }} className="d-flex justify-content-between align-items-center">
                <h5 className="mb-0">👥 Detail Karyawan Divisi</h5>
                <div className="d-flex gap-2">
                  <Button 
                    variant="outline-light" 
                    size="sm" 
                    onClick={handleManualRefresh}
                  >
                    🔄 Refresh Data
                  </Button>
                  <Button 
                    variant={autoRefresh ? "warning" : "outline-light"} 
                    size="sm" 
                    onClick={toggleAutoRefresh}
                    title={autoRefresh ? "Auto-refresh aktif (30 detik)" : "Auto-refresh tidak aktif"}
                  >
                    {autoRefresh ? "⏸️ Stop Auto" : "▶️ Auto Refresh"}
                  </Button>
                </div>
              </Card.Header>
              <Card.Body>
                <Table responsive hover>
                  <thead style={{ backgroundColor: '#e3f2fd' }}>
                    <tr>
                      <th>Nama & Departemen</th>
                      <th>Pendidikan</th>
                      <th>Join Date</th>
                                              <th>Years Of Service</th>
                      <th>Status Kontrak</th>
                      <th>Status Match</th>
                      <th>Rekomendasi Kontrak</th>
                      <th>Status Approval</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {analytics?.employees.map((emp, index) => (
                      <tr key={index}>
                        <td>
                          <div>
                            <strong>{emp.name}</strong>
                            <>
                              <br />
                              <small className="text-muted">{emp.role}</small>
                            </>
                          </div>
                        </td>
                        <td>
                          {emp.major}
                          <>
                            <br />
                            <small className="text-muted">{emp.education_level}</small>
                          </>
                        </td>
                        <td>{new Date(emp.join_date).toLocaleDateString('id-ID')}</td>
                        <td>
                          <Badge bg="info">{emp.tenure_months} bulan</Badge>
                        </td>
                        <td>
                          {getContractStatusBadge(emp)}
                        </td>
                        <td>
                          <div className="d-flex align-items-center">
                            {getMatchLevelBadge(emp)}
                          </div>
                        </td>
                        <td>
                          {getRecommendationStatusBadge(emp)}
                        </td>
                        <td>
                          {getApprovalStatusBadge(emp)}
                        </td>
                        <td>
                          <div className="d-flex gap-2">
                            {/* Only permanent employees cannot be recommended */}
                            {emp.current_contract_type === 'permanent' ? (
                              <div className="d-flex flex-column gap-1">
                                <Button 
                                  size="sm" 
                                  variant="outline-primary"
                                  onClick={() => handleEmployeeClick(emp)}
                                >
                                  Detail
                                </Button>
                                <Button 
                                  size="sm" 
                                  variant="outline-danger"
                                  disabled
                                  title="Karyawan memiliki kontrak permanent"
                                  style={{ 
                                    backgroundColor: '#f8f9fa',
                                    borderColor: '#dc3545',
                                    color: '#dc3545',
                                    cursor: 'not-allowed',
                                    opacity: 0.7,
                                    fontSize: '0.7rem',
                                    padding: '0.25rem 0.4rem',
                                    lineHeight: '1.2'
                                  }}
                                >
                                  ❌ Tidak Dapat Direkomendasikan
                                </Button>
                              </div>
                            ) : emp.has_pending_recommendation ? (
                              /* Employee has pending recommendation */
                              <div className="d-flex flex-column gap-1">
                                <Button 
                                  size="sm" 
                                  variant="outline-primary"
                                  onClick={() => handleEmployeeClick(emp)}
                                >
                                  Detail
                                </Button>
                                <Button 
                                  size="sm" 
                                  variant="outline-warning"
                                  disabled
                                  title="Rekomendasi sedang menunggu persetujuan HR. Tidak dapat membuat rekomendasi baru hingga HR memproses yang sebelumnya."
                                  style={{ 
                                    backgroundColor: '#fff3cd',
                                    borderColor: '#ffc107',
                                    color: '#856404',
                                    cursor: 'not-allowed',
                                    fontSize: '0.7rem',
                                    padding: '0.25rem 0.4rem',
                                    lineHeight: '1.2'
                                  }}
                                >
                                  ⏳ Menunggu HR
                                </Button>
                              </div>
                            ) : (
                              /* Normal case - can create recommendation */
                              <Button 
                                size="sm" 
                                variant="success"
                                onClick={() => handleCreateRecommendation(emp)}
                                title="Lihat detail dan buat rekomendasi untuk karyawan ini"
                              >
                                Detail & Rekomendasi
                              </Button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </Card.Body>
            </Card>
          </Tab.Pane>

          {/* Processed Recommendations Tab */}
          <Tab.Pane eventKey="processed">
            <Card>
              <Card.Header style={{ backgroundColor: '#28a745', color: 'white' }} className="d-flex justify-content-between align-items-center">
                <div>
                  <h5 className="mb-0">✅ Rekomendasi yang Sudah Diproses HR</h5>
                  <small>Lihat status rekomendasi Anda dan kontrak yang telah diperpanjang</small>
                </div>
                <Button 
                  variant="outline-light" 
                  size="sm" 
                  onClick={fetchProcessedRecommendations}
                  className="ms-2"
                >
                  🔄 Refresh
                </Button>
              </Card.Header>
              <Card.Body>
                {processedRecommendations.length > 0 ? (
                  <Table responsive hover>
                    <thead style={{ backgroundColor: '#e8f5e8' }}>
                      <tr>
                        <th>Karyawan</th>
                        <th>Rekomendasi Anda</th>
                        <th>Status HR</th>
                        <th>Kontrak Baru</th>
                        <th>Tanggal Diproses</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {processedRecommendations.map((rec, index) => (
                        <tr key={index}>
                          <td>
                            <div>
                              <strong>{rec.employee.name}</strong>
                              <br />
                              <small className="text-muted">{rec.employee.role}</small>
                            </div>
                          </td>
                          <td>
                            <Badge bg="primary" className="me-1">
                              {rec.recommendation_type === 'kontrak1' ? 'Kontrak 1' :
                               rec.recommendation_type === 'kontrak2' ? 'Kontrak 2' :
                               rec.recommendation_type === 'kontrak3' ? 'Kontrak 3' : rec.recommendation_type}
                            </Badge>
                            <br />
                            <small className="text-muted">{rec.recommended_duration} bulan</small>
                          </td>
                          <td>
                            {rec.status === 'approved' ? (
                              <Badge bg="success">✅ Disetujui</Badge>
                            ) : rec.status === 'extended' ? (
                              <Badge bg="success">✅ Sudah Diperpanjang</Badge>
                            ) : rec.status === 'resign' ? (
                              <Badge bg="dark">👋 Resign</Badge>
                            ) : (
                              <Badge bg="danger">❌ Ditolak</Badge>
                            )}
                          </td>
                                                     <td>
                             {rec.status === 'resign' ? (
                               <Badge bg="dark">👋 Resign</Badge>
                             ) : (rec.status === 'approved' || rec.status === 'extended') && rec.new_contract?.is_created ? (
                               <div>
                                 <Badge bg="success" className="mb-1">Kontrak {rec.new_contract.type}</Badge>
                                 <br />
                                 <small className="text-success">
                                   <strong>✅ Kontrak Aktif:</strong><br />
                                   {rec.new_contract.end_date ? 
                                     `${new Date(rec.new_contract.start_date).toLocaleDateString('id-ID')} - ${new Date(rec.new_contract.end_date).toLocaleDateString('id-ID')}` : 
                                     `Mulai: ${new Date(rec.new_contract.start_date).toLocaleDateString('id-ID')} (Permanent)`
                                   }
                                   <br />
                                   <span className="text-muted">Durasi: {rec.recommended_duration === 'permanent' ? 'Permanent' : `${rec.recommended_duration} bulan`}</span>
                                 </small>
                               </div>
                             ) : (rec.status === 'approved' || rec.status === 'extended') ? (
                               <Badge bg="warning">⏳ Kontrak sedang dibuat</Badge>
                             ) : (
                               <small className="text-muted">Tidak ada kontrak baru</small>
                             )}
                           </td>
                          <td>
                            {new Date(rec.updated_at).toLocaleDateString('id-ID')}
                          </td>
                                                     <td>
                             {rec.status === 'resign' ? (
                               <Badge bg="secondary">🚫 Tidak Dilanjutkan</Badge>
                             ) : (rec.status === 'approved' || rec.status === 'extended') && rec.new_contract?.is_created ? (
                               <Badge bg="success">🎉 Kontrak Berhasil Diperpanjang</Badge>
                             ) : (rec.status === 'approved' || rec.status === 'extended') ? (
                               <Badge bg="warning">⏳ Sedang Memproses Kontrak</Badge>
                             ) : (
                               <Badge bg="danger">❌ Rekomendasi Ditolak</Badge>
                             )}
                           </td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                ) : (
                  <div className="text-center py-5">
                    <div className="display-1 mb-3">📋</div>
                    <h5>Belum Ada Rekomendasi yang Diproses</h5>
                    <p className="text-muted">
                      Rekomendasi yang Anda buat akan muncul di sini setelah diproses oleh HR.
                    </p>
                  </div>
                )}
              </Card.Body>
            </Card>
          </Tab.Pane>
        </Tab.Content>
      </Tab.Container>

      {/* Employee Detail Modal */}
      {showEmployeeModal && selectedEmployee && (
                          <div className="modal active" onClick={() => handleCloseModal()}>
          <div className="modal-dialog modal-xl" style={{ maxWidth: '90vw' }} onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header py-3" style={{ background: 'linear-gradient(135deg, #007bff 0%, #6610f2 100%)', color: 'white' }}>
                <h5 className="modal-title">
                  <i className="fas fa-user-circle me-2"></i>
                  {selectedEmployee.name} - Detail dan Rekomendasi
                </h5>
                <button 
                  type="button" 
                  className="btn-close btn-close-white"
                  onClick={() => setShowEmployeeModal(false)}
                ></button>
              </div>
              
              <div className="modal-body p-3" style={{ maxHeight: '80vh', overflowY: 'auto' }}>
                {loadingDetail ? (
                  <div className="text-center py-5">
                    <Spinner animation="border" variant="primary" />
                    <div className="mt-3">Memuat detail karyawan...</div>
                  </div>
                ) : employeeDetail && !employeeDetail.error ? (
                  <div className="container-fluid p-0">
                    {/* TOP ROW - Basic Info + Key Intelligence + Sample Profiles */}
                    <div className="row mb-4">
                      {/* Basic Info */}
                      <div className="col-lg-4">
                        <div className="card h-100 border-0 shadow-sm">
                          <div className="card-header py-3 bg-primary text-white">
                            <h6 className="mb-0">
                              <i className="fas fa-user me-2"></i>
                              Informasi Karyawan
                            </h6>
                          </div>
                          <div className="card-body p-3">
                            <div className="mb-3">
                              <small className="text-muted d-block">Nama</small>
                              <div className="fw-bold text-dark">{employeeDetail.employee.name}</div>
                            </div>
                            <div className="mb-3">
                              <small className="text-muted d-block">Posisi</small>
                              <div className="fw-bold text-dark">{employeeDetail.employee.role}</div>
                            </div>
                            <div className="mb-3">
                              <small className="text-muted d-block">Divisi</small>
                              <div className="fw-bold text-dark">{employeeDetail.employee.division_name}</div>
                            </div>
                            <div className="mb-3">
                              <small className="text-muted d-block">Tenure</small>
                              <div className="fw-bold text-dark">{Math.round(employeeDetail.employee.tenure_months)} bulan</div>
                            </div>
                            <div className="mb-3">
                              <small className="text-muted d-block">Pendidikan</small>
                              <div className="fw-bold text-dark">{employeeDetail.employee.education_level} - {employeeDetail.employee.major}</div>
                            </div>
                            <div className="mb-3">
                              <small className="text-muted d-block">Match Status</small>
                              <div>
                                {(() => {
                                  const educationScore = selectedEmployee?.intelligence_data?.education_score || 0;
                                  const matchStatus = educationScore >= 2 ? 'Match' : 'Unmatch';
                                  const badgeClass = matchStatus === 'Match' ? 'bg-success' : 'bg-warning';
                                  
                                  return (
                                    <span className={`badge ${badgeClass}`}>
                                      {matchStatus}
                                    </span>
                                  );
                                })()}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      {/* Key Intelligence */}
                      <div className="col-lg-4">
                        <div className="card h-100 border-0 shadow-sm">
                          <div className="card-header py-3 bg-success text-white">
                            <h6 className="mb-0">
                              <i className="fas fa-brain me-2"></i>
                              Data Intelligence & Statistics
                            </h6>
                          </div>
                          <div className="card-body p-3">
                            <div className="row">
                              <div className="col-6">
                                <div className="mb-3">
                                  <small className="text-muted d-block">Tipe Kontrak</small>
                                  <div className="fw-bold text-success">{getConsistentContractRecommendation(selectedEmployee)}</div>
                                </div>
                                <div className="mb-3">
                                  <small className="text-muted d-block">Sample Size</small>
                                  <div className="fw-bold text-dark">
                                    {(() => {
                                      const stats = getConsistentStatistics(selectedEmployee, employeeDetail);
                                      return `${stats.sampleSize} profil`;
                                    })()}
                                  </div>
                                </div>
                                                                  <div className="mb-3">
                                  <small className="text-muted d-block">Rekomendasi</small>
                                  <div className="fw-bold text-primary">
                                    {getConsistentDurationRecommendation(selectedEmployee)}
                                  </div>
                                </div>
                              </div>
                              <div className="col-6">
                                {/* Combined Historical & Resign Intelligence Data */}
                                {/* Use consistent statistics based on employee data */}
                                                                  {selectedEmployee ? (
                                  <div>
                                    {(() => {
                                      const stats = getConsistentStatistics(selectedEmployee, employeeDetail);
                                      return (
                                        <>
                                          <div className="mb-2">
                                            <small className="text-muted d-block">Masih Aktif</small>
                                            <div className="fw-bold text-success">{stats.masihAktif}%</div>
                                          </div>
                                          <div className="mb-2">
                                            <small className="text-muted d-block">Sudah Resign</small>
                                            <div className="fw-bold text-danger">{stats.sudahResign}%</div>
                                          </div>

                                          <div className="mb-2">
                                            <small className="text-muted d-block">Early Resign Risk</small>
                                            <div className="fw-bold text-warning">{stats.earlyResignRisk}%</div>
                                          </div>
                                          <div className="mb-2">
                                            <small className="text-muted d-block">Avg Tenure</small>
                                            <div className="fw-bold text-primary">{stats.avgTenure} bulan</div>
                                          </div>
                                          
                                          {/* Show contract matching info if available */}
                                          {employeeDetail?.dynamic_intelligence?.historical_patterns?.data_insights?.matching_criteria && (
                                            <div className="mb-2">
                                              <small className="text-muted d-block">Contract Matches</small>
                                              <div className="fw-bold text-info">
                                                {employeeDetail.dynamic_intelligence.historical_patterns.data_insights.matching_criteria.contract_matches}/
                                                {employeeDetail.dynamic_intelligence.historical_patterns.sample_size}
                                              </div>
                                            </div>
                                          )}
                                        </>
                                      );
                                    })()}
                                  </div>
                                ) : (
                                  /* Fallback statistics */
                                  <div>
                                    <div className="mb-2">
                                      <small className="text-muted d-block">Masih Aktif</small>
                                      <div className="fw-bold text-success">60%</div>
                                    </div>
                                    <div className="mb-2">
                                      <small className="text-muted d-block">Sudah Resign</small>
                                      <div className="fw-bold text-danger">40%</div>
                                    </div>

                                    <div className="mb-2">
                                      <small className="text-muted d-block">Early Resign Risk</small>
                                      <div className="fw-bold text-warning">30.0%</div>
                                    </div>
                                    <div className="mb-2">
                                      <small className="text-muted d-block">Avg Tenure</small>
                                      <div className="fw-bold text-primary">15.0 bulan</div>
                                    </div>
                                  </div>
                                )}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      {/* Sample Profiles - COMPACT & EFFICIENT */}
                      <div className="col-lg-4">
                        <div className="card h-100 border-0 shadow-sm">
                          <div className="card-header py-3" style={{ background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', color: 'white' }}>
                            <h6 className="mb-0">
                              <i className="fas fa-users me-2"></i>
                              Profil Serupa ({(() => {
                                const stats = getConsistentStatistics(selectedEmployee, employeeDetail);
                                return stats.sampleSize;
                              })()})
                            </h6>
                            {/* Show contract matching information */}
                            {employeeDetail?.dynamic_intelligence?.historical_patterns?.data_insights?.recommended_contract_type && (
                              <small className="opacity-75 d-block mt-1">
                                🎯 Targeting: {employeeDetail.dynamic_intelligence.historical_patterns.data_insights.recommended_contract_type} contract employees
                                <br />
                                ✅ Found: {employeeDetail.dynamic_intelligence.historical_patterns.data_insights.matching_criteria?.contract_matches || 0} matching profiles
                              </small>
                            )}
                          </div>
                          <div className="card-body p-2" style={{ backgroundColor: '#f8f9fa' }}>
                            {employeeDetail.dynamic_intelligence?.historical_patterns?.profiles?.length > 0 ? (
                              (() => {
                                const profiles = employeeDetail.dynamic_intelligence.historical_patterns.profiles;
                                const displayProfiles = showAllProfiles ? profiles : profiles.slice(0, 8);
                                
                                return (
                                  <div>
                                    <div className="d-flex flex-column gap-1" style={{ maxHeight: showAllProfiles ? 'none' : '300px', overflowY: showAllProfiles ? 'visible' : 'auto' }}>
                                      {displayProfiles
                                        .sort((a, b) => {
                                          // Sort by status (Active/Permanent first), then by tenure
                                          const aIsActive = a.outcome_status === 'Currently_Active' || a.outcome_status === 'Reached_Permanent';
                                          const bIsActive = b.outcome_status === 'Currently_Active' || b.outcome_status === 'Reached_Permanent';
                                          
                                          if (aIsActive && !bIsActive) return -1;
                                          if (!aIsActive && bIsActive) return 1;
                                          return (b.total_tenure_months || 0) - (a.total_tenure_months || 0);
                                        })
                                        .map((profile, idx) => {
                                          // Check if this profile matches the recommended contract type
                                          const recommendedContract = employeeDetail?.dynamic_intelligence?.historical_patterns?.data_insights?.recommended_contract_type;
                                          const isRecommendedMatch = profile.contract_match || 
                                            (recommendedContract && profile.current_contract_type === recommendedContract) ||
                                            (recommendedContract === '2' && profile.current_contract_type === 'kontrak2') ||
                                            (recommendedContract === 'kontrak2' && profile.current_contract_type === '2');
                                          
                                          return (
                                                                                         <div key={profile.eid} className={`card border-0 shadow-sm ${
                                               isRecommendedMatch ? 'border-start border-primary border-3' :
                                               (profile.outcome_status === 'Currently_Active' || profile.outcome_status === 'Reached_Permanent') ? 'border-start border-success border-3' : 'border-start border-danger border-3'
                                             }`} style={{ 
                                               backgroundColor: isRecommendedMatch ? '#f8f9ff' : 'white', 
                                               borderRadius: '4px' 
                                             }}>
                                              <div className="card-body p-2">
                                                <div className="d-flex justify-content-between align-items-center">
                                                  <div className="flex-grow-1">
                                                    <div className="d-flex align-items-center gap-1">
                                                      <div className="fw-bold text-truncate" style={{ fontSize: '0.75rem', color: (profile.outcome_status === 'Currently_Active' || profile.outcome_status === 'Reached_Permanent') ? '#198754' : '#dc3545' }}>
                                                        {profile.name}
                                                      </div>
                                                      {isRecommendedMatch && (
                                                        <span className="badge bg-primary" style={{ fontSize: '0.5rem' }}>🎯</span>
                                                      )}
                                                    </div>
                                                    <div className="text-muted" style={{ fontSize: '0.7rem' }}>
                                                      {profile.major || 'N/A'} - {profile.role || 'N/A'}
                                                    </div>
                                                    <div className="text-muted" style={{ fontSize: '0.7rem' }}>
                                                                                                             <span className={isRecommendedMatch ? 'text-primary fw-bold' : ''}>
                                                         {profile.current_contract_type || 'N/A'}
                                                       </span> • {Math.round(profile.total_tenure_months || 0)}m
                                                    </div>
                                                  </div>
                                                  <div className={`badge ${(profile.outcome_status === 'Currently_Active' || profile.outcome_status === 'Reached_Permanent') ? 'bg-success' : 'bg-danger'}`} style={{ fontSize: '0.6rem' }}>
                                                    {profile.outcome_status === 'Currently_Active' ? 'Aktif' : 
                                                     profile.outcome_status === 'Reached_Permanent' ? 'Permanent' :
                                                     profile.outcome_status === 'Resigned' ? 'Resign' :
                                                     profile.outcome_status === 'Terminated' ? 'Terminated' : 'Unknown'}
                                                  </div>
                                                </div>
                                              </div>
                                                                                          </div>
                                            );
                                          })}
                                      </div>
                                    {profiles.length > 8 && (
                                      <div className="text-center mt-2">
                                        <button 
                                          className="btn btn-sm btn-outline-primary"
                                          onClick={() => setShowAllProfiles(!showAllProfiles)}
                                          style={{ fontSize: '0.75rem' }}
                                        >
                                          {showAllProfiles ? (
                                            <>
                                              <i className="fas fa-chevron-up me-1"></i>
                                              Tampilkan Lebih Sedikit
                                            </>
                                          ) : (
                                            <>
                                              <i className="fas fa-chevron-down me-1"></i>
                                              Tampilkan Semua ({profiles.length} profil)
                                            </>
                                          )}
                                        </button>
                                      </div>
                                    )}
                                  </div>
                                );
                              })()
                            ) : (
                              <div className="text-center py-3">
                                <i className="fas fa-users fa-2x text-muted mb-2"></i>
                                <div className="text-muted small">Tidak ada profil serupa</div>
                              </div>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  
                    {/* FORM REKOMENDASI KONTRAK - MOVED HERE */}
                    <div className="row">
                      <div className="col-12">
                        <div className="card border-0 shadow-sm">
                          <div className="card-header py-3" style={{ background: 'linear-gradient(135deg, #fd7e14 0%, #ffc107 100%)', color: 'white' }}>
                            <h6 className="mb-0">
                              <i className="fas fa-file-signature me-2"></i>
                              Form Rekomendasi Kontrak
                            </h6>
                            <small className="opacity-75">Pilih tipe kontrak dan durasi yang sesuai berdasarkan analisis data</small>
                          </div>
                          <div className="card-body p-4">
                            {/* Dynamic Intelligence Suggestion */}
                            {employeeDetail.dynamic_intelligence && (
                              <div className="alert alert-info border-0 shadow-sm mb-4">
                                <div className="d-flex align-items-center">
                                  <i className="fas fa-brain fa-2x text-info me-3"></i>
                                  <div>
                                    <h6 className="alert-heading mb-2">
                                      <i className="fas fa-lightbulb me-1"></i>
                                      Dynamic Intelligence Suggestion
                                    </h6>
                                    <div className="row">
                                      <div className="col-md-4">
                                        <small className="text-muted d-block">Tipe Kontrak Direkomendasikan</small>
                                        <strong>{getConsistentContractRecommendation(selectedEmployee)}</strong>
                                      </div>
                                      <div className="col-md-4">
                                        <small className="text-muted d-block">Durasi Optimal</small>
                                                                <strong className="text-primary">
                          {getConsistentDurationRecommendation(selectedEmployee)}
                        </strong>
                                      </div>
                                      <div className="col-md-4">
                                        <small className="text-muted d-block">Sample Size</small>
                                        <strong>
                                          {(() => {
                                            const stats = getConsistentStatistics(selectedEmployee, employeeDetail);
                                            const recommendedContract = employeeDetail?.dynamic_intelligence?.historical_patterns?.data_insights?.recommended_contract_type;
                                            const contractMatches = employeeDetail?.dynamic_intelligence?.historical_patterns?.data_insights?.matching_criteria?.contract_matches;
                                            
                                            if (recommendedContract && contractMatches) {
                                              return `${stats.sampleSize} profil (${contractMatches} match ${recommendedContract})`;
                                            }
                                            return `${stats.sampleSize} profil serupa`;
                                          })()}
                                        </strong>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            )}

                            <div className="row">
                              <div className="col-md-6">
                                <div className="mb-4">
                                  <label htmlFor="recommendation_type" className="form-label fw-bold">
                                    <i className="fas fa-clipboard-list me-2"></i>
                                    Jenis Rekomendasi
                                  </label>
                                  <select 
                                    id="recommendation_type"
                                    name="recommendation_type"
                                    className="form-select form-select-lg"
                                    value={recommendationData.recommendation_type || ''}
                                    onChange={(e) => {
                                      const newType = e.target.value;
                                      setRecommendationData(prev => ({
                                        ...prev,
                                        recommendation_type: newType,
                                        // Reset duration when changing contract type
                                        recommended_duration: newType === 'permanent' ? 0 : (newType === 'terminate' ? 0 : '')
                                      }));
                                    }}
                                  >
                                    <option value="">Pilih Jenis Kontrak</option>
                                    <option value="kontrak2">Kontrak 2</option>
                                    <option value="kontrak3">Kontrak 3</option>
                                    <option value="permanent">Permanent</option>
                                    <option value="terminate">
                                      {recommendationData.reason && recommendationData.reason.includes('ELIMINASI OTOMATIS') 
                                        ? 'TIDAK DIREKOMENDASIKAN' 
                                        : 'Tidak Diperpanjang'}
                                    </option>
                                  </select>
                                </div>
                              </div>
                              <div className="col-md-6">
                                <div className="mb-4">
                                  <label htmlFor="recommended_duration_select" className="form-label fw-bold">
                                    <i className="fas fa-calendar-alt me-2"></i>
                                    Durasi Optimal (bulan)
                                  </label>
                                  
                                  {/* Show dropdown for Kontrak 2 and Kontrak 3 */}
                                  {(recommendationData.recommendation_type === 'kontrak2' || recommendationData.recommendation_type === 'kontrak3') ? (
                                    <select
                                      id="recommended_duration_select"
                                      name="recommended_duration_select"
                                      className="form-select form-select-lg"
                                      value={recommendationData.recommended_duration || ''}
                                      onChange={(e) => setRecommendationData(prev => ({
                                        ...prev,
                                        recommended_duration: e.target.value
                                      }))}
                                    >
                                      <option value="">Pilih durasi kontrak</option>
                                      <option value="6">6 Bulan</option>
                                      <option value="12">12 Bulan</option>
                                      <option value="18">18 Bulan</option>
                                      <option value="24">24 Bulan</option>
                                    </select>
                                  ) : (
                                    <input
                                      id="recommended_duration"
                                      name="recommended_duration"
                                      type="number"
                                      className="form-control form-control-lg"
                                      value={recommendationData.recommended_duration || ''}
                                      onChange={(e) => setRecommendationData(prev => ({
                                        ...prev,
                                        recommended_duration: e.target.value
                                      }))}
                                      disabled={recommendationData.recommendation_type === 'permanent' || recommendationData.recommendation_type === 'terminate'}
                                      placeholder="Masukkan durasi dalam bulan"
                                      min="1"
                                      max="36"
                                    />
                                  )}
                                  
                                  {/* Helper text for different contract types */}
                                  {(recommendationData.recommendation_type === 'kontrak2' || recommendationData.recommendation_type === 'kontrak3') && (
                                    <div className="mt-2">
                                      <small className="text-info">
                                        <i className="fas fa-info-circle me-1"></i>
                                        Pilih durasi yang sesuai berdasarkan rekomendasi sistem dan performa karyawan
                                      </small>
                                    </div>
                                  )}
                                  {recommendationData.recommendation_type === 'permanent' && (
                                    <div className="mt-2">
                                      <small className="text-success">
                                        <i className="fas fa-info-circle me-1"></i>
                                        Permanent - tidak memerlukan durasi
                                      </small>
                                    </div>
                                  )}
                                  {recommendationData.recommendation_type === 'terminate' && (
                                    <div className="mt-2">
                                      <small className="text-danger">
                                        <i className="fas fa-exclamation-triangle me-1"></i>
                                        {recommendationData.reason && recommendationData.reason.includes('ELIMINASI OTOMATIS') 
                                          ? 'ELIMINASI OTOMATIS - Jurusan Non-IT tidak direkomendasikan' 
                                          : 'Tidak diperpanjang - kontrak berakhir'}
                                      </small>
                                    </div>
                                  )}
                                </div>
                              </div>
                            </div>
                            
                            {/* Contract Start Date Field - Only show for extension types */}
                            {(recommendationData.recommendation_type === 'kontrak2' || 
                              recommendationData.recommendation_type === 'kontrak3' || 
                              recommendationData.recommendation_type === 'permanent') && (
                              <div className="mb-4">
                                <label htmlFor="contract_start_date" className="form-label fw-bold">
                                  <i className="fas fa-calendar-check me-2"></i>
                                  Tanggal Mulai Kontrak Baru
                                </label>
                                <input
                                  id="contract_start_date"
                                  name="contract_start_date"
                                  type="date"
                                  className="form-control form-control-lg"
                                  value={recommendationData.contract_start_date || ''}
                                  onChange={(e) => setRecommendationData(prev => ({
                                    ...prev,
                                    contract_start_date: e.target.value
                                  }))}
                                  min={new Date().toISOString().split('T')[0]} // Minimum today
                                />
                                <div className="form-text">
                                  <i className="fas fa-info-circle me-1"></i>
                                  Tentukan kapan kontrak baru akan mulai berlaku. Membantu HR dalam perencanaan dan data intelligence yang lebih akurat.
                                </div>
                              </div>
                            )}
                            
                            <div className="mb-4">
                              <label htmlFor="recommendationReason" className="form-label fw-bold">
                                <i className="fas fa-comment-alt me-2"></i>
                                Alasan Rekomendasi
                              </label>
                              <textarea
                                id="recommendationReason"
                                name="recommendationReason"
                                className="form-control"
                                rows="4"
                                value={recommendationData.reason || ''}
                                onChange={(e) => setRecommendationData(prev => ({
                                  ...prev,
                                  reason: e.target.value
                                }))}
                                placeholder="Jelaskan alasan rekomendasi berdasarkan analisis data, performa karyawan, dan kebutuhan perusahaan..."
                                style={{ resize: 'vertical' }}
                              />
                              <div className="form-text">
                                <i className="fas fa-lightbulb me-1"></i>
                                Tip: Sertakan justifikasi berdasarkan match status, tenure, dan analisis data mining
                              </div>
                            </div>

                            <div className="text-center">
                              <button 
                                type="button" 
                                className="btn btn-success btn-lg px-5"
                                onClick={submitRecommendation}
                                                                disabled={
                                  !(employeeDetail?.employee?.eid || selectedEmployee?.eid) || 
                                  !recommendationData.recommendation_type || 
                                  // Validate duration for kontrak2 and kontrak3
                                  (['kontrak2', 'kontrak3'].includes(recommendationData.recommendation_type) && 
                                   (!recommendationData.recommended_duration || recommendationData.recommended_duration === '0')) ||
                                  // Validate contract start date for extension types
                                  (['kontrak2', 'kontrak3', 'permanent'].includes(recommendationData.recommendation_type) && 
                                   !recommendationData.contract_start_date)
                                }
                                title={
                                  !(employeeDetail?.employee?.eid || selectedEmployee?.eid) ? 'Data karyawan tidak lengkap' :
                                  !recommendationData.recommendation_type ? 'Pilih jenis rekomendasi' :
                                  (['kontrak2', 'kontrak3'].includes(recommendationData.recommendation_type) && 
                                   (!recommendationData.recommended_duration || recommendationData.recommended_duration === '0')) ? 'Pilih durasi kontrak' :
                                  (['kontrak2', 'kontrak3', 'permanent'].includes(recommendationData.recommendation_type) && 
                                   !recommendationData.contract_start_date) ? 'Pilih tanggal mulai kontrak' :
                                  'Kirim rekomendasi ke HR'
                                }
                              >
                                <i className="fas fa-paper-plane me-2"></i>
                                Kirim Rekomendasi ke HR
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="alert alert-danger border-0 shadow-sm">
                    <h4 className="alert-heading">
                      <i className="fas fa-exclamation-triangle me-2"></i>
                      Gagal memuat detail karyawan
                    </h4>
                    {employeeDetail?.error ? (
                      <div>
                        <p><strong>Error:</strong> {employeeDetail.errorMessage}</p>
                        <details>
                          <summary>Debug Information</summary>
                          <div className="mt-2">
                            <p><strong>Employee:</strong> {employeeDetail.employee?.name} (EID: {employeeDetail.employee?.eid})</p>
                            <p><strong>Contract:</strong> {employeeDetail.employee?.current_contract_type}</p>
                            <p><strong>Possible causes:</strong></p>
                            <ul>
                              <li>Session expired - try refreshing the page</li>
                              <li>Network connection issues</li>
                              <li>Server configuration problems</li>
                            </ul>
                            <p className="small text-muted">Check browser console for more details (F12 → Console)</p>
                          </div>
                        </details>
                      </div>
                    ) : (
                      <p>Terjadi kesalahan saat mengambil data analisis karyawan. Silakan coba lagi atau hubungi administrator sistem.</p>
                    )}
                  </div>
                )}
              </div>
              <div className="modal-footer py-3 bg-light">
                <button 
                  type="button" 
                  className="btn btn-secondary px-4"
                  onClick={() => setShowEmployeeModal(false)}
                >
                  <i className="fas fa-times me-2"></i>
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </Container>
  );
};

export default ManagerDashboard; 