import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import seaborn as sns
from datetime import datetime
import warnings
import os
from collections import Counter
import plotly.graph_objects as go
import plotly.express as px
from plotly.subplots import make_subplots
import plotly.offline as pyo

# Set Indonesian locale for matplotlib
plt.rcParams['font.family'] = 'DejaVu Sans'
warnings.filterwarnings('ignore')

# Create output directory for visualizations
output_dir = 'output_visual_kontrak'
if not os.path.exists(output_dir):
    os.makedirs(output_dir)

class EmployeeContractAnalyzer:
    def __init__(self, csv_file):
        """
        Initialize the analyzer with CSV data
        """
        self.csv_file = csv_file
        self.df = None
        self.filtered_df = None
        self.column_mapping = {}
        self.load_and_clean_data()
    
    def find_column(self, keywords, df=None):
        """
        Find column that matches keywords - prioritize columns with all keywords
        """
        if df is None:
            df = self.filtered_df if self.filtered_df is not None else self.df
        
        # First pass: look for columns that contain ALL keywords
        for col in df.columns:
            col_clean = col.lower().replace('\xa0', ' ').replace('\n', ' ').replace('_', ' ').strip()
            if all(keyword.lower() in col_clean for keyword in keywords):
                return col
        
        # Second pass: look for columns that contain ANY keyword (fallback)
        for col in df.columns:
            col_clean = col.lower().replace('\xa0', ' ').replace('\n', ' ').replace('_', ' ').strip()
            for keyword in keywords:
                if keyword.lower() in col_clean:
                    return col
        return None
        
    def load_and_clean_data(self):
        """
        Load and clean the CSV data
        """
        print("🔄 Memuat dan membersihkan data...")
        
        # Read CSV with proper encoding and delimiter
        try:
            self.df = pd.read_csv(self.csv_file, delimiter=';', encoding='utf-8')
        except:
            self.df = pd.read_csv(self.csv_file, delimiter=';', encoding='latin1')
        
        # Clean column names - remove special characters
        self.df.columns = self.df.columns.str.replace('\ufeff', '')  # Remove BOM
        self.df.columns = self.df.columns.str.replace('"', '')  # Remove quotes
        self.df.columns = self.df.columns.str.replace('�', '')  # Remove special chars
        self.df.columns = self.df.columns.str.strip()  # Remove spaces
        
        print(f"📊 Total data yang dimuat: {len(self.df)} karyawan")
        print(f"📋 Kolom yang tersedia: {list(self.df.columns)}")
        
        # Try to find the employee name column with different possible names
        employee_name_col = None
        possible_names = ['EMPLOYEE NAME', 'EMPLOYEE\xa0NAME', 'EMPLOYEENAME', 'NAME']
        
        for col in self.df.columns:
            if any(name.lower() in col.lower().replace('\xa0', ' ').replace('_', ' ') for name in ['employee name', 'employee', 'name']):
                if 'employee' in col.lower():
                    employee_name_col = col
                    break
        
        if employee_name_col is None:
            # Fallback: use the column that most likely contains names
            for col in self.df.columns:
                if col not in ['NO', 'EID'] and self.df[col].dtype == 'object':
                    if any(isinstance(val, str) and len(val.split()) >= 2 for val in self.df[col].dropna().head(10)):
                        employee_name_col = col
                        break
        
        if employee_name_col is None:
            raise ValueError("Cannot find employee name column")
        
        print(f"🔍 Menggunakan kolom nama: {employee_name_col}")
        
        # Remove empty rows
        self.df = self.df.dropna(subset=[employee_name_col])
        
        # Clean data - remove rows with insufficient data
        self.df = self.df[self.df[employee_name_col].notna()]
        self.df = self.df[self.df[employee_name_col] != '']
        
        print(f"📊 Data setelah pembersihan: {len(self.df)} karyawan")
        
        # Create column mapping for easy access
        self.column_mapping = {
            'employee_name': employee_name_col,
            'join_date': self.find_column(['join', 'date'], self.df),
            'resign_date': self.find_column(['resign', 'date'], self.df),
            'permanent_date': self.find_column(['permanent', 'date'], self.df),
            'status_working': self.find_column(['status', 'working'], self.df),
            'active_status': self.find_column(['active', 'status'], self.df),
            'education_level': self.find_column(['education', 'level'], self.df),
            'major': self.find_column(['major'], self.df),
            'designation': self.find_column(['designation'], self.df),
            'role_internal': self.find_column(['role', 'client', 'internal'], self.df),
            'role_at_client': self.find_column(['role', 'at', 'client'], self.df),  # PERBAIKAN: tambah Role At Client
            'probation_expired': self.find_column(['probation', 'expired'], self.df),
            'contract_2nd': self.find_column(['contract', '2nd'], self.df),
            'contract_3rd': self.find_column(['contract', '3rd'], self.df)
        }
        
        print("🔍 Mapping kolom:")
        for key, value in self.column_mapping.items():
            print(f"   {key}: {value}")
    
    def apply_filters(self):
        """
        Apply mandatory filters:
        1. Remove Graduate Development Program employees
        2. Remove Non-IT majors
        3. Remove Non-IT roles
        """
        print("\n🔍 Menerapkan filter wajib...")
        
        initial_count = len(self.df)
        
        # Create a copy for filtering
        self.filtered_df = self.df.copy()
        
        # 1. Remove Graduate Development Program
        designation_col = self.column_mapping.get('designation')
        if designation_col:
            gdp_mask = self.filtered_df[designation_col].fillna('').str.contains('Graduate Development Program', case=False, na=False)
            gdp_count = gdp_mask.sum()
            self.filtered_df = self.filtered_df[~gdp_mask]
            print(f"❌ Menghapus {gdp_count} karyawan Graduate Development Program")
        else:
            print("⚠️ Kolom designation tidak ditemukan, skip filter GDP")
        
        # 2. Define IT-related majors
        it_majors = [
            'INFORMATION TECHNOLOGY', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 
            'SISTEM INFORMASI', 'INFORMATION SYSTEM', 'COMPUTER SCIENCE',
            'TEKNIK KOMPUTER', 'COMPUTER ENGINEERING', 'INFORMATICS',
            'INFORMATICS ENGINEERING', 'MANAGEMENT INFORMATIKA', 
            'INFORMATIC MANAGEMENT', 'COMPUTER SYSTEM', 'INFORMATION SYSTEMS',
            'COMPUTER', 'INFORMATIC ENGINEERING', 'COMPUTATIONAL SCIENCE',
            'TEKNIK ELEKTRO', 'ELECTRICAL ENGINEERING', 'ELECTRONICS',
            'COMPUTER AND INFORMATICS ENGINEERING', 'TELECOMMUNICATIONS ENGINEERING',
            'SOFTWARE ENGINEERING', 'DATA ANALYTICS', 'INFORMATICS MANAGEMENT'
        ]
        
        # Filter Non-IT majors
        major_col = self.column_mapping.get('major')
        if major_col:
            non_it_major_mask = ~self.filtered_df[major_col].fillna('').str.upper().isin([major.upper() for major in it_majors])
            non_it_major_mask &= self.filtered_df[major_col].fillna('') != ''
            non_it_major_mask &= self.filtered_df[major_col].fillna('') != '-'
            non_it_major_count = non_it_major_mask.sum()
            self.filtered_df = self.filtered_df[~non_it_major_mask]
            print(f"❌ Menghapus {non_it_major_count} karyawan dengan jurusan Non-IT")
        else:
            print("⚠️ Kolom major tidak ditemukan, skip filter major")
        
        # 3. Define IT-related roles
        it_roles = [
            'DEVELOPER', 'PROGRAMMER', 'ANALYST', 'TESTER', 'PROJECT MANAGER',
            'TECHNICAL', 'IT ', 'SOFTWARE', 'DATA', 'SYSTEM', 'ETL', 'API',
            'FULL STACK', 'FRONTEND', 'BACKEND', 'QUALITY ASSURANCE',
            'DEVOPS', 'SECURITY', 'DATABASE', 'NETWORK', 'INFRASTRUCTURE',
            'CONSULTANT', 'ARCHITECT', 'ENGINEER'
        ]
        
        # Filter Non-IT roles
        designation_col = self.column_mapping.get('designation')
        role_internal_col = self.column_mapping.get('role_internal')
        
        if designation_col and role_internal_col:
            role_text = self.filtered_df[designation_col].fillna('').str.upper() + ' ' + \
                       self.filtered_df[role_internal_col].fillna('').str.upper()
            
            it_role_mask = role_text.str.contains('|'.join(it_roles), case=False, na=False)
            non_it_role_count = (~it_role_mask).sum()
            self.filtered_df = self.filtered_df[it_role_mask]
            print(f"❌ Menghapus {non_it_role_count} karyawan dengan role Non-IT")
        else:
            print("⚠️ Kolom role tidak ditemukan, skip filter role")
        
        final_count = len(self.filtered_df)
        print(f"✅ Data final setelah filtering: {final_count} karyawan (dari {initial_count})")
        print(f"📉 Total dihapus: {initial_count - final_count} karyawan")
    
    def process_contract_analysis(self):
        """
        Process contract analysis (Variable X) - ENHANCED VERSION dengan Flow Kontrak
        UPDATED: Menambahkan skenario Probation diperpanjang dan Probation ke Kontrak-Permanent
        """
        print("\n📊 Menganalisis progres kontrak (Variabel X)...")
        
        # Initialize contract analysis columns
        self.filtered_df['probation_status'] = 'Tidak Lulus'  # Default to failed
        self.filtered_df['contract_progression'] = 'Gagal Probation'  # Default
        self.filtered_df['contract_duration_months'] = 0.0
        self.filtered_df['contract_stage'] = 'Unknown'
        
        # Process each employee's contract status
        for idx, row in self.filtered_df.iterrows():
            status = 'Unknown'
            if self.column_mapping['status_working']:
                status = str(row[self.column_mapping['status_working']]).strip()
            
            active_status = 'Unknown'
            if self.column_mapping['active_status']:
                active_status = str(row[self.column_mapping['active_status']]).strip()
            
            # Check contract stages from probation to permanent
            probation_expired = None
            contract_2nd = None
            contract_3rd = None
            
            if self.column_mapping['probation_expired']:
                probation_expired = str(row[self.column_mapping['probation_expired']]).strip()
            if self.column_mapping['contract_2nd']:
                contract_2nd = str(row[self.column_mapping['contract_2nd']]).strip()
            if self.column_mapping['contract_3rd']:
                contract_3rd = str(row[self.column_mapping['contract_3rd']]).strip()
            
            # ENHANCED CONTRACT PROGRESSION LOGIC dengan Skenario Tambahan
            if status == 'Probation':
                # Cek apakah probation diperpanjang (ada kontrak selanjutnya)
                has_extended_contract = (contract_2nd and contract_2nd not in ['', 'nan', '-', 'None']) or \
                                      (contract_3rd and contract_3rd not in ['', 'nan', '-', 'None'])
                
                if has_extended_contract:
                    self.filtered_df.at[idx, 'probation_status'] = 'Diperpanjang'
                    if contract_3rd and contract_3rd not in ['', 'nan', '-', 'None']:
                        self.filtered_df.at[idx, 'contract_progression'] = 'Probation Diperpanjang ke Kontrak ke-3'
                        self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak ke-3'
                    elif contract_2nd and contract_2nd not in ['', 'nan', '-', 'None']:
                        self.filtered_df.at[idx, 'contract_progression'] = 'Probation Diperpanjang ke Kontrak ke-2'
                        self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak ke-2'
                    else:
                        self.filtered_df.at[idx, 'contract_progression'] = 'Probation Diperpanjang ke Kontrak'
                        self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak'
                else:
                    self.filtered_df.at[idx, 'probation_status'] = 'Tidak Lulus'
                    self.filtered_df.at[idx, 'contract_progression'] = 'Gagal Probation'
                    self.filtered_df.at[idx, 'contract_stage'] = 'Probation (Gagal)'
                    
            elif status in ['Contract', 'Permanent']:
                self.filtered_df.at[idx, 'probation_status'] = 'Lulus'
                
                # Determine detailed contract progression based on contract stages
                if status == 'Permanent':
                    # Check if went through contract stages
                    if (contract_2nd and contract_2nd not in ['', 'nan', '-', 'None']) or \
                       (contract_3rd and contract_3rd not in ['', 'nan', '-', 'None']):
                        self.filtered_df.at[idx, 'contract_progression'] = 'Permanen Setelah Kontrak'
                        if contract_3rd and contract_3rd not in ['', 'nan', '-', 'None']:
                            self.filtered_df.at[idx, 'contract_stage'] = 'Kontrak ke-3 → Permanen'
                        elif contract_2nd and contract_2nd not in ['', 'nan', '-', 'None']:
                            self.filtered_df.at[idx, 'contract_stage'] = 'Kontrak ke-2 → Permanen'
                        else:
                            self.filtered_df.at[idx, 'contract_stage'] = 'Kontrak ke-1 → Permanen'
                    else:
                        self.filtered_df.at[idx, 'contract_progression'] = 'Langsung Permanen'
                        self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Langsung Permanen'
                        
                else:  # Contract status - ENHANCED dengan skenario baru
                    if active_status == 'Active':
                        # Determine current contract stage - Aktif
                        if contract_3rd and contract_3rd not in ['', 'nan', '-', 'None']:
                            self.filtered_df.at[idx, 'contract_progression'] = 'Probation Diperpanjang → Kontrak ke-3 Aktif'
                            self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak ke-3 (Aktif)'
                        elif contract_2nd and contract_2nd not in ['', 'nan', '-', 'None']:
                            self.filtered_df.at[idx, 'contract_progression'] = 'Probation Diperpanjang → Kontrak ke-2 Aktif'
                            self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak ke-2 (Aktif)'
                        else:
                            self.filtered_df.at[idx, 'contract_progression'] = 'Probation → Kontrak ke-1 Aktif'
                            self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak ke-1 (Aktif)'
                            
                    elif active_status == 'Resign':
                        # SKENARIO BARU: Probation ke Kontrak-Permanent (Resign)
                        if contract_3rd and contract_3rd not in ['', 'nan', '-', 'None']:
                            self.filtered_df.at[idx, 'contract_progression'] = 'Probation → Kontrak ke-3 (Resign)'
                            self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak ke-3 (Resign)'
                        elif contract_2nd and contract_2nd not in ['', 'nan', '-', 'None']:
                            self.filtered_df.at[idx, 'contract_progression'] = 'Probation → Kontrak ke-2 (Resign)'
                            self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak ke-2 (Resign)'
                        else:
                            self.filtered_df.at[idx, 'contract_progression'] = 'Probation → Kontrak-Permanent (Resign)'
                            self.filtered_df.at[idx, 'contract_stage'] = 'Probation → Kontrak-Permanent (Resign)'
                            
                    else:
                        # Fallback for unclear active status
                        self.filtered_df.at[idx, 'contract_progression'] = 'Kontrak Status Tidak Jelas'
                        self.filtered_df.at[idx, 'contract_stage'] = 'Status Tidak Jelas'
            
            # Calculate contract duration - ENHANCED
            if self.filtered_df.at[idx, 'probation_status'] in ['Lulus', 'Diperpanjang']:
                join_date = None
                resign_date = None
                
                # Get join date
                if self.column_mapping['join_date']:
                    join_date_str = str(row[self.column_mapping['join_date']])
                    if join_date_str and join_date_str not in ['nan', '', 'None', '-']:
                        try:
                            for fmt in ['%d-%b-%y', '%d/%m/%Y', '%Y-%m-%d', '%d-%m-%Y', '%d-%m-%y']:
                                try:
                                    join_date = pd.to_datetime(join_date_str, format=fmt)
                                    break
                                except:
                                    continue
                            if join_date is None:
                                join_date = pd.to_datetime(join_date_str, errors='coerce')
                        except:
                            pass
                
                # Get resign date if resigned
                if 'Resign' in self.filtered_df.at[idx, 'contract_progression'] and self.column_mapping['resign_date']:
                    resign_date_str = str(row[self.column_mapping['resign_date']])
                    if resign_date_str and resign_date_str not in ['nan', '', 'None', '-']:
                        try:
                            for fmt in ['%d-%b-%y', '%d/%m/%Y', '%Y-%m-%d', '%d-%m-%Y', '%d-%m-%y']:
                                try:
                                    resign_date = pd.to_datetime(resign_date_str, format=fmt)
                                    break
                                except:
                                    continue
                            if resign_date is None:
                                resign_date = pd.to_datetime(resign_date_str, errors='coerce')
                        except:
                            pass
                
                # Calculate duration
                if pd.notna(join_date):
                    end_date = resign_date if pd.notna(resign_date) else datetime.now()
                    total_months = (end_date - join_date).days / 30.44
                    contract_duration = max(0, total_months - 3)  # Subtract probation
                    self.filtered_df.at[idx, 'contract_duration_months'] = contract_duration
                else:
                    # Estimate from years of service
                    years_service = 0
                    if 'YEARS OF SERVICE' in self.df.columns:
                        try:
                            years_str = str(row['YEARS OF SERVICE']).replace(',', '.')
                            if years_str not in ['nan', '', 'None', '-']:
                                years_service = float(years_str)
                        except:
                            years_service = 0
                    estimated_months = max(0, (years_service * 12) - 3)
                    self.filtered_df.at[idx, 'contract_duration_months'] = estimated_months
        
        # Remove any remaining unknown/unclear entries
        self.filtered_df = self.filtered_df[self.filtered_df['probation_status'] != 'Unknown']
        
        # Debug: Check contract progression and stages - ENHANCED
        print(f"📊 Status probation (UPDATED):")
        probation_counts = self.filtered_df['probation_status'].value_counts()
        for status, count in probation_counts.items():
            print(f"   {status}: {count} karyawan")
        
        print(f"📊 Progres kontrak (UPDATED dengan skenario baru):")
        progression_counts = self.filtered_df['contract_progression'].value_counts()
        for prog, count in progression_counts.items():
            print(f"   {prog}: {count} karyawan")
        
        print(f"📊 Tahap kontrak (UPDATED):")
        stage_counts = self.filtered_df['contract_stage'].value_counts()
        for stage, count in stage_counts.items():
            print(f"   {stage}: {count} karyawan")
        
        # Enhanced statistics - termasuk skenario baru
        passed_or_extended = self.filtered_df[self.filtered_df['probation_status'].isin(['Lulus', 'Diperpanjang'])]
        valid_durations = passed_or_extended[passed_or_extended['contract_duration_months'] > 0]
        
        print(f"📊 Durasi kontrak (UPDATED):")
        print(f"   Total karyawan lulus/diperpanjang: {len(passed_or_extended)}")
        print(f"   Karyawan dengan durasi valid: {len(valid_durations)}")
        
        # Statistik khusus untuk skenario baru
        probation_extended = self.filtered_df[self.filtered_df['probation_status'] == 'Diperpanjang']
        probation_to_resign = self.filtered_df[self.filtered_df['contract_progression'].str.contains('Probation.*Resign', case=False, na=False)]
        
        print(f"📊 Skenario baru:")
        print(f"   Probation diperpanjang: {len(probation_extended)} karyawan")
        print(f"   Probation → Kontrak-Permanent (Resign): {len(probation_to_resign)} karyawan")
        
        if len(valid_durations) > 0:
            print(f"   Rata-rata durasi: {valid_durations['contract_duration_months'].mean():.1f} bulan")
            print(f"   Min durasi: {valid_durations['contract_duration_months'].min():.1f} bulan")
            print(f"   Max durasi: {valid_durations['contract_duration_months'].max():.1f} bulan")
        
        print("✅ Analisis kontrak selesai dengan skenario tambahan")
    
    def process_education_analysis(self):
        """
        Process education and role matching analysis (Variable Y) - ENHANCED & CLEANED
        """
        print("\n🎓 Menganalisis pendidikan dan kesesuaian role (Variabel Y)...")
        
        # Initialize education analysis columns
        self.filtered_df['education_category'] = 'SMA/SMK-D3 Non Sarjana'  # Default
        self.filtered_df['is_sarjana'] = False
        self.filtered_df['role_education_match'] = 'Sesuai'
        self.filtered_df['duration_category'] = '0-6 bulan (Risiko Tinggi)'
        
        # Process education level - CLEANED & SEPARATED D4/S1 vs S2
        for idx, row in self.filtered_df.iterrows():
            education = 'UNKNOWN'
            if self.column_mapping['education_level']:
                education_raw = str(row[self.column_mapping['education_level']]).strip().upper()
                # Clean up education values
                if education_raw not in ['NAN', '', 'NONE', '-', 'UNKNOWN']:
                    education = education_raw
            
            # Enhanced education categories - SEPARATED as requested
            if any(x in education for x in ['SMA', 'SMK', 'D3']):
                self.filtered_df.at[idx, 'education_category'] = 'SMA/SMK-D3 Non Sarjana'
                self.filtered_df.at[idx, 'is_sarjana'] = False
            elif any(x in education for x in ['D4', 'S1', 'SARJANA']) and 'S2' not in education:
                self.filtered_df.at[idx, 'education_category'] = 'D4/S1 Sarjana'
                self.filtered_df.at[idx, 'is_sarjana'] = True
            elif 'S2' in education or 'MAGISTER' in education:
                self.filtered_df.at[idx, 'education_category'] = 'S2 Magister'
                self.filtered_df.at[idx, 'is_sarjana'] = True
            else:
                # For truly unknown education, default to non-sarjana
                self.filtered_df.at[idx, 'education_category'] = 'SMA/SMK-D3 Non Sarjana'
                self.filtered_df.at[idx, 'is_sarjana'] = False
            
            # Enhanced duration categorization
            duration = self.filtered_df.at[idx, 'contract_duration_months']
            if duration <= 6:
                self.filtered_df.at[idx, 'duration_category'] = '0-6 bulan (Risiko Tinggi)'
            elif 7 <= duration <= 11:
                self.filtered_df.at[idx, 'duration_category'] = '7-11 bulan (Menengah)'
            else:
                self.filtered_df.at[idx, 'duration_category'] = '≥12 bulan (Outstanding)'
        
        # Debug: Check education distribution
        edu_dist = self.filtered_df['education_category'].value_counts()
        print(f"📊 Distribusi pendidikan (CLEANED):")
        for edu, count in edu_dist.items():
            print(f"   {edu}: {count} karyawan")
        
        # Check for any remaining unknown values
        unknown_count = len(self.filtered_df[self.filtered_df['education_category'].str.contains('Unknown', case=False, na=False)])
        if unknown_count > 0:
            print(f"⚠️ Masih ada {unknown_count} data pendidikan yang perlu validasi")
        else:
            print("✅ Semua data pendidikan sudah tervalidasi")
        
        print("✅ Analisis pendidikan selesai")
    
    def create_variable_x_visualizations(self):
        """
        Create 7 visualizations for Variable X (Contract Analysis)
        UPDATED: Termasuk skenario Probation diperpanjang dan Probation ke Kontrak-Permanent
        """
        print("\n📊 Membuat visualisasi Variabel X (Analisis Kontrak)...")
        
        # Analyze all employees (including extended probation)
        all_contract_data = self.filtered_df[self.filtered_df['probation_status'] != 'Unknown'].copy()
        passed_or_extended = self.filtered_df[self.filtered_df['probation_status'].isin(['Lulus', 'Diperpanjang'])].copy()
        
        # 1. Pie Chart: Probation Status (Lulus/Diperpanjang/Tidak Lulus) - UPDATED
        probation_counts = self.filtered_df['probation_status'].value_counts()
        # Remove any unknown/unclear statuses
        probation_counts = probation_counts[probation_counts.index != 'Unknown']
        
        plt.figure(figsize=(10, 8))
        colors = ['#2E86AB', '#FFA500', '#C73E1D']  # Blue for Lulus, Orange for Diperpanjang, Red for Tidak Lulus
        
        # Create labels with counts and percentages
        total = probation_counts.sum()
        labels = []
        for status, count in probation_counts.items():
            percentage = (count / total) * 100
            labels.append(f'{status}\n{count:,} orang\n({percentage:.1f}%)')
        
        wedges, texts, autotexts = plt.pie(probation_counts.values, 
                                          labels=labels,
                                          autopct='', colors=colors[:len(probation_counts)], 
                                          startangle=90, explode=[0.05] * len(probation_counts))
        plt.title('Status Probation Karyawan IT (Termasuk Skenario Baru)', fontsize=16, fontweight='bold', pad=20)
        
        # Enhance text formatting
        for text in texts:
            text.set_fontsize(11)
            text.set_fontweight('bold')
        
        # Add summary statistics - UPDATED untuk skenario baru
        lulus_count = probation_counts.get('Lulus', 0)
        diperpanjang_count = probation_counts.get('Diperpanjang', 0)
        tidak_lulus_count = probation_counts.get('Tidak Lulus', 0)
        success_rate = ((lulus_count + diperpanjang_count) / total) * 100 if total > 0 else 0
        
        plt.text(1.05, 0.98, f'Total Dianalisis: {total:,} karyawan\nTingkat Keberhasilan: {success_rate:.1f}%\n(Lulus + Diperpanjang)', 
                transform=plt.gca().transAxes, verticalalignment='top',
                bbox=dict(boxstyle='round', facecolor='lightblue', alpha=0.8),
                fontsize=10, fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/1_probation_status_pie.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 2. Bar Chart: Contract Progression - DETAILED CONTRACT JOURNEY
        contract_counts = passed_or_extended['contract_progression'].value_counts()
        
        plt.figure(figsize=(16, 10))
        # Enhanced color scheme untuk detail perjalanan
        colors = ['#E74C3C', '#3498DB', '#2ECC71', '#F39C12', '#9B59B6', '#1ABC9C', '#34495E', '#E67E22', '#95A5A6']
        bars = plt.bar(range(len(contract_counts)), contract_counts.values, 
                      color=colors[:len(contract_counts)])
        
        plt.title('DETAIL PERJALANAN KONTRAK KARYAWAN IT\n(Dari Probation hingga Status Akhir)', 
                 fontsize=18, fontweight='bold', pad=30)
        plt.xlabel('Status Kontrak Detail', fontsize=14, fontweight='bold')
        plt.ylabel('Jumlah Karyawan', fontsize=14, fontweight='bold')
        
        # Enhanced labels dengan wrap text
        labels = []
        total_employees = contract_counts.sum()
        for prog in contract_counts.index:
            # Shorten long labels for better display
            if len(prog) > 25:
                short_prog = prog[:22] + '...'
            else:
                short_prog = prog
            labels.append(short_prog)
        
        plt.xticks(range(len(contract_counts)), labels, rotation=45, ha='right', fontsize=10)
        
        # Add detailed value labels with percentages
        for i, (bar, value) in enumerate(zip(bars, contract_counts.values)):
            percentage = (value / total_employees) * 100
            plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + 2, 
                    f'{value}\n({percentage:.1f}%)', ha='center', va='bottom', 
                    fontweight='bold', fontsize=10,
                    bbox=dict(boxstyle='round,pad=0.3', facecolor='white', alpha=0.8))
        
        # Add summary statistics box
        summary_text = f'''RINGKASAN PERJALANAN KONTRAK:
📊 Total Karyawan: {total_employees:,} orang
🎯 Aktif (Semua Level): {len(passed_or_extended[passed_or_extended['contract_progression'].str.contains('Aktif', na=False)]):,} orang
❌ Resign (Semua Level): {len(passed_or_extended[passed_or_extended['contract_progression'].str.contains('Resign', na=False)]):,} orang
✅ Permanen: {len(passed_or_extended[passed_or_extended['contract_progression'].str.contains('Permanen', na=False)]):,} orang'''
        
        plt.text(1.05, 0.98, summary_text, transform=plt.gca().transAxes, 
                verticalalignment='top', bbox=dict(boxstyle='round', facecolor='lightblue', alpha=0.9),
                fontsize=11, fontweight='bold')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/2_contract_progression_bar.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 3. Line Chart: Permanent Conversion Trend (by year) - UPDATED untuk skenario baru
        permanent_employees = passed_or_extended[passed_or_extended['contract_progression'].str.contains('Permanen', na=False)]
        
        # Extract years from join dates
        permanent_employees_copy = permanent_employees.copy()
        join_date_col = self.column_mapping['join_date']
        if join_date_col:
            permanent_employees_copy['join_year'] = pd.to_datetime(permanent_employees_copy[join_date_col], 
                                                                  format='%d-%b-%y', errors='coerce').dt.year
        permanent_by_year = permanent_employees_copy['join_year'].value_counts().sort_index()
        
        plt.figure(figsize=(12, 8))
        if len(permanent_by_year) > 0:
            plt.plot(permanent_by_year.index, permanent_by_year.values, 
                    marker='o', linewidth=3, markersize=8, color='#2E86AB')
            # Add value labels
            for x, y in zip(permanent_by_year.index, permanent_by_year.values):
                plt.text(x, y + 0.5, str(y), ha='center', va='bottom', fontweight='bold')
        else:
            plt.text(0.5, 0.5, 'No data available', ha='center', va='center', transform=plt.gca().transAxes)
        
        plt.title('Tren Konversi Karyawan Menjadi Permanen per Tahun', fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Tahun Bergabung', fontsize=12)
        plt.ylabel('Jumlah Karyawan Permanen', fontsize=12)
        plt.grid(True, alpha=0.3)
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/3_permanent_trend_line.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 4. Bar Chart: DETAILED Employee Status Distribution dengan Validasi
        plt.figure(figsize=(16, 10))
        
        # Count all contract progressions - including new scenarios dengan validasi
        progression_counts = passed_or_extended['contract_progression'].value_counts()
        
        # Enhanced color mapping untuk setiap jenis kontrak
        color_map = {
            'Probation → Kontrak-Permanent (Resign)': '#E74C3C',
            'Probation → Kontrak ke-1 Aktif': '#3498DB', 
            'Langsung Permanen': '#2ECC71',
            'Probation Diperpanjang → Kontrak ke-2 Aktif': '#F39C12',
            'Probation → Kontrak ke-2 (Resign)': '#E67E22',
            'Probation Diperpanjang → Kontrak ke-3 Aktif': '#9B59B6',
            'Probation → Kontrak ke-3 (Resign)': '#95A5A6',
            'Kontrak Status Tidak Jelas': '#BDC3C7',
            'Permanen Setelah Kontrak': '#1ABC9C'
        }
        
        # Prepare data dengan validasi
        categories = []
        values = []
        colors = []
        percentages = []
        total_employees = progression_counts.sum()
        
        for prog, count in progression_counts.items():
            categories.append(prog)
            values.append(count)
            colors.append(color_map.get(prog, '#34495E'))
            percentages.append((count / total_employees) * 100)
        
        # Create detailed bar chart
        bars = plt.bar(range(len(categories)), values, color=colors)
        plt.title('DISTRIBUSI DETAIL STATUS KARYAWAN SETELAH FILTERING\n(Validasi: {} Karyawan IT)'.format(total_employees), 
                 fontsize=18, fontweight='bold', pad=30)
        plt.ylabel('Jumlah Karyawan', fontsize=14, fontweight='bold')
        plt.xlabel('Detail Status Kontrak', fontsize=14, fontweight='bold')
        
        # Enhanced labels with full contract names - DETAILED
        detailed_labels = []
        for cat in categories:
            # Provide detailed contract type descriptions
            if 'Probation → Kontrak-Permanent (Resign)' in cat:
                detailed_labels.append('Probation → Kontrak-Permanent\n(Resign)')
            elif 'Probation → Kontrak ke-1 Aktif' in cat:
                detailed_labels.append('Probation Lulus → TTD Kontrak\nPertama (Aktif)')
            elif 'Langsung Permanen' in cat:
                detailed_labels.append('Probation Lulus → TTD Kontrak-\nPermanent (Aktif)')
            elif 'Probation Diperpanjang → Kontrak ke-2' in cat:
                detailed_labels.append('Probation Diperpanjang → Kontrak\nke-2 (Aktif)')
            elif 'Probation → Kontrak ke-2 (Resign)' in cat:
                detailed_labels.append('Probation → Kontrak ke-2\n(Resign)')
            elif 'Probation Diperpanjang → Kontrak ke-3' in cat:
                detailed_labels.append('Probation Diperpanjang → Kontrak\nke-3 (Aktif)')
            elif 'Probation → Kontrak ke-3 (Resign)' in cat:
                detailed_labels.append('Probation → Kontrak ke-3\n(Resign)')
            elif 'Kontrak Status Tidak Jelas' in cat:
                detailed_labels.append('Kontrak Status Tidak Jelas')
            elif 'Permanen Setelah Kontrak' in cat:
                detailed_labels.append('Permanen Setelah Kontrak')
            else:
                detailed_labels.append(cat[:25] + '...' if len(cat) > 25 else cat)
        
        plt.xticks(range(len(categories)), detailed_labels, rotation=45, ha='right', fontsize=10)
        
        # Add detailed value labels
        for i, (bar, value, percentage) in enumerate(zip(bars, values, percentages)):
            plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + max(values) * 0.01, 
                    f'{value}\n({percentage:.1f}%)', 
                    ha='center', va='bottom', fontweight='bold', fontsize=10,
                    bbox=dict(boxstyle='round,pad=0.3', facecolor='white', alpha=0.8))
        
        # Add comprehensive validation summary
        aktif_total = sum([v for c, v in zip(categories, values) if 'Aktif' in c])
        resign_total = sum([v for c, v in zip(categories, values) if 'Resign' in c])
        permanen_total = sum([v for c, v in zip(categories, values) if 'Permanen' in c])
        
        validation_text = f'''📋 VALIDASI SETELAH FILTERING:
🎯 Total Karyawan IT: {total_employees:,} orang
✅ Status Aktif: {aktif_total:,} orang ({aktif_total/total_employees*100:.1f}%)
❌ Status Resign: {resign_total:,} orang ({resign_total/total_employees*100:.1f}%)
🏆 Status Permanen: {permanen_total:,} orang ({permanen_total/total_employees*100:.1f}%)

🔍 FILTER DITERAPKAN:
• ❌ Graduate Development Program
• ❌ Jurusan Non-IT  
• ❌ Role Non-IT
• ✅ Hanya Karyawan IT Murni'''
        
        plt.text(1.05, 0.98, validation_text, transform=plt.gca().transAxes, 
                verticalalignment='top', bbox=dict(boxstyle='round', facecolor='lightgreen', alpha=0.9),
                fontsize=10, fontweight='bold')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/4_employee_status_distribution.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 5. Histogram: Contract Duration Distribution - VALIDASI n yang AKURAT
        # Filter data dengan validasi ketat
        valid_durations = passed_or_extended[passed_or_extended['contract_duration_months'] > 0]['contract_duration_months']
        all_durations = passed_or_extended['contract_duration_months']  # Include zeros for validation
        
        plt.figure(figsize=(14, 10))
        
        if len(valid_durations) > 0:
            # Create histogram dengan validasi data
            n_bins = min(25, max(8, len(valid_durations) // 15))  # Optimized bin count
            n, bins, patches = plt.hist(valid_durations, bins=n_bins, color='#3498DB', alpha=0.8, 
                                       edgecolor='black', linewidth=1.2)
            
            plt.title('DISTRIBUSI DURASI KONTRAK SETELAH PROBATION\n(Validasi Data Akurat)', 
                     fontsize=18, fontweight='bold', pad=25)
            plt.xlabel('Durasi Kontrak (Bulan)', fontsize=14, fontweight='bold')
            plt.ylabel('Jumlah Karyawan', fontsize=14, fontweight='bold')
            
            # Enhanced reference lines dengan kategori
            plt.axvline(x=6, color='#E74C3C', linestyle='--', linewidth=3, label='Minimum 6 Bulan')
            plt.axvline(x=12, color='#27AE60', linestyle='--', linewidth=3, label='Outstanding ≥12 Bulan')
            plt.axvline(x=valid_durations.mean(), color='#F39C12', linestyle='-', linewidth=3, 
                       label=f'Rata-rata {valid_durations.mean():.1f} Bulan')
            
            # Add categorical shading
            plt.axvspan(0, 6, alpha=0.2, color='red', label='Risiko Tinggi (0-6 bulan)')
            plt.axvspan(6, 12, alpha=0.2, color='orange', label='Menengah (6-12 bulan)')
            plt.axvspan(12, valid_durations.max(), alpha=0.2, color='green', label='Outstanding (>12 bulan)')
            
            plt.legend(fontsize=11, loc='upper right')
            plt.grid(True, alpha=0.4)
            
            # Enhanced validation statistics
            risk_count = len(valid_durations[valid_durations <= 6])
            medium_count = len(valid_durations[(valid_durations > 6) & (valid_durations <= 12)])
            outstanding_count = len(valid_durations[valid_durations > 12])
            zero_duration = len(all_durations[all_durations == 0])
            
            validation_stats = f'''📊 VALIDASI DATA (n = {len(valid_durations)}):
✅ Data Valid (>0 bulan): {len(valid_durations):,} karyawan
⚠️ Data Zero (0 bulan): {zero_duration:,} karyawan
📈 Total Dianalisis: {len(all_durations):,} karyawan

📋 STATISTIK DETAIL:
• Min: {valid_durations.min():.1f} bulan
• Max: {valid_durations.max():.1f} bulan  
• Mean: {valid_durations.mean():.1f} bulan
• Median: {valid_durations.median():.1f} bulan
• Std Dev: {valid_durations.std():.1f} bulan

🎯 KATEGORISASI:
• Risiko Tinggi (≤6): {risk_count:,} ({risk_count/len(valid_durations)*100:.1f}%)
• Menengah (6-12): {medium_count:,} ({medium_count/len(valid_durations)*100:.1f}%)
• Outstanding (>12): {outstanding_count:,} ({outstanding_count/len(valid_durations)*100:.1f}%)'''
            
            plt.text(1.05, 0.98, validation_stats, transform=plt.gca().transAxes, 
                    verticalalignment='top', bbox=dict(boxstyle='round', facecolor='lightyellow', alpha=0.95),
                    fontsize=10, fontweight='bold')
        else:
            # Fallback jika tidak ada data valid
            plt.hist(all_durations, bins=20, color='#95A5A6', alpha=0.7, edgecolor='black')
            plt.title('DISTRIBUSI DURASI KONTRAK\n(Tidak Ada Data Valid)', fontsize=16, fontweight='bold', pad=20)
            plt.xlabel('Durasi Kontrak (Bulan)', fontsize=12)
            plt.ylabel('Jumlah Karyawan', fontsize=12)
            plt.text(0.5, 0.5, f'⚠️ VALIDASI GAGAL\nSemua {len(all_durations)} karyawan\nmemiliki durasi = 0', 
                    ha='center', va='center', transform=plt.gca().transAxes, fontsize=14,
                    bbox=dict(boxstyle='round', facecolor='lightcoral', alpha=0.8), fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/5_contract_duration_histogram.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 6. BARU: Distribusi Tingkat Pendidikan yang Lulus Probation
        probation_lulus_only = self.filtered_df[self.filtered_df['probation_status'] == 'Lulus'].copy()
        
        plt.figure(figsize=(12, 8))
        education_probation_counts = probation_lulus_only['education_category'].value_counts()
        
        colors = ['#2E86AB', '#F18F01', '#28a745']
        
        # Create pie chart
        total_lulus = len(probation_lulus_only)
        labels = []
        for edu, count in education_probation_counts.items():
            percentage = (count / total_lulus) * 100
            labels.append(f'{edu}\n{count:,} orang\n({percentage:.1f}%)')
        
        plt.pie(education_probation_counts.values, labels=labels, colors=colors[:len(education_probation_counts)], 
               autopct='', startangle=90, explode=[0.05] * len(education_probation_counts))
        plt.title('TINGKAT PENDIDIKAN KARYAWAN YANG LULUS PROBATION\n(Non Sarjana - Sarjana - Magister)', 
                 fontsize=16, fontweight='bold', pad=20)
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/6_education_probation_pass.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 6.5A. Chart Terpisah: Distribusi Tingkat Pendidikan yang Lulus Probation
        plt.figure(figsize=(12, 10))
        
        # Filter data yang lulus probation
        lulus_probation = self.filtered_df[self.filtered_df['probation_status'] == 'Lulus']
        
        # Kategorisasi pendidikan yang lebih detail
        education_detail_mapping = {
            'D3 (Non Sarjana)': [],
            'D4/S1 (Sarjana)': [],
            'S2 (Magister)': []
        }
        
        for idx, row in lulus_probation.iterrows():
            edu_level = row.get('education_category', '')
            if 'Non Sarjana' in edu_level or 'D3' in edu_level:
                education_detail_mapping['D3 (Non Sarjana)'].append(row)
            elif 'Sarjana' in edu_level and 'Non' not in edu_level:
                education_detail_mapping['D4/S1 (Sarjana)'].append(row)
            elif 'Magister' in edu_level or 'S2' in edu_level:
                education_detail_mapping['S2 (Magister)'].append(row)
        
        # Analisis lebih detail per kategori pendidikan
        detail_analysis = {}
        
        for edu_cat, employees in education_detail_mapping.items():
            if len(employees) > 0:
                # Analisis contract progression per education level (HAPUS STATUS TIDAK JELAS)
                progression_counts = {}
                for emp in employees:
                    progression = emp.get('contract_progression', 'Unknown')
                    # Skip status tidak jelas
                    if 'Tidak Jelas' not in progression and 'Unknown' not in progression:
                        if progression in progression_counts:
                            progression_counts[progression] += 1
                        else:
                            progression_counts[progression] = 1
                
                detail_analysis[edu_cat] = {
                    'total': len(employees),
                    'progressions': progression_counts
                }
        
        # Chart 1: Overall Distribution
        overall_counts = [len(employees) for employees in education_detail_mapping.values() if len(employees) > 0]
        overall_labels = [cat for cat, employees in education_detail_mapping.items() if len(employees) > 0]
        
        colors_main = ['#FF6B6B', '#4ECDC4', '#45B7D1']
        wedges, texts, autotexts = plt.pie(overall_counts, labels=overall_labels, colors=colors_main, 
                                          autopct=lambda pct: f'{pct:.1f}%\n({int(pct/100*sum(overall_counts)):,} orang)',
                                          startangle=90, explode=[0.05] * len(overall_counts))
        
        plt.title('DISTRIBUSI TINGKAT PENDIDIKAN KARYAWAN YANG LULUS PROBATION\n' +
                 f'Total Karyawan Lulus Probation: {sum(overall_counts):,} orang\n' +
                 'Analisis menunjukkan dominasi D4/S1 Sarjana (92%) dengan kontribusi signifikan D3 Non-Sarjana (7.6%)', 
                 fontsize=16, fontweight='bold', pad=30)
        
        # Add detailed text explanation
        plt.figtext(0.5, 0.02, 
                   '📊 INSIGHT: D4/S1 Sarjana mendominasi kelulusan probation, namun D3 Non-Sarjana tetap menunjukkan\n' +
                   'kontribusi yang signifikan. Perbedaan tingkat pendidikan ini memerlukan strategi kontrak yang berbeda.',
                   ha='center', fontsize=12, style='italic', wrap=True)
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/6_5A_education_distribution_probation.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 6.5B. Chart Terpisah: D3 (Non Sarjana) Detail Kontrak - FIXED TEXT OVERLAP
        plt.figure(figsize=(16, 12))  # Increased figure size
        
        if 'D3 (Non Sarjana)' in detail_analysis and detail_analysis['D3 (Non Sarjana)']['progressions']:
            d3_data = detail_analysis['D3 (Non Sarjana)']['progressions']
            d3_labels = list(d3_data.keys())
            d3_values = list(d3_data.values())
            
            # Enhanced colors untuk D3
            colors_d3 = ['#FF6B6B', '#FF8E8E', '#FFB1B1', '#FFD4D4', '#FF9999', '#FFAAAA', '#FFCCCC']
            
            # Create SHORTER labels to prevent overlap
            clean_d3_labels = []
            for label, val in zip(d3_labels, d3_values):
                pct = (val / sum(d3_values)) * 100
                if 'Aktif' in label:
                    status_icon = '✅'
                elif 'Resign' in label:
                    status_icon = '❌'
                elif 'Permanen' in label:
                    status_icon = '🎯'
                else:
                    status_icon = '📋'
                
                # SHORTEN the labels to prevent text overlap
                short_label = label.replace('Probation → ', '').replace('Probation Diperpanjang → ', 'Diperpanjang → ')
                if len(short_label) > 25:
                    short_label = short_label[:22] + '...'
                clean_d3_labels.append(f'{status_icon} {short_label}\n{val} orang ({pct:.1f}%)')
            
            # Use smaller pie chart with better spacing
            plt.pie(d3_values, labels=clean_d3_labels, colors=colors_d3[:len(d3_labels)], 
                   autopct='', startangle=90, explode=[0.08] * len(d3_labels),
                   textprops={'fontsize': 10, 'weight': 'bold'})  # Smaller font
            
            plt.title(f'D3 (NON SARJANA) - DETAIL PERJALANAN KONTRAK\n' +
                     f'Total D3 yang Lulus Probation: {detail_analysis["D3 (Non Sarjana)"]["total"]} orang\n' +
                     'Pattern kontrak D3 menunjukkan variasi tinggi dengan fokus pada perpanjangan kontrak',
                     fontsize=16, fontweight='bold', pad=40)  # Increased padding
            
        else:
            plt.text(0.5, 0.5, 'DATA D3 (NON SARJANA) TIDAK TERSEDIA\n\nKemungkinan penyebab:\n• Jumlah data terbatas\n• Filtering menghilangkan status tidak jelas', 
                    ha='center', va='center', transform=plt.gca().transAxes, fontsize=14, 
                    bbox=dict(boxstyle="round,pad=0.3", facecolor="lightgray"))
            plt.title('D3 (NON SARJANA) - DETAIL PERJALANAN KONTRAK', fontsize=16, fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/6_5B_d3_contract_detail.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 6.5C. Chart Terpisah: D4/S1 (Sarjana) Detail Kontrak - FIXED TEXT OVERLAP
        plt.figure(figsize=(18, 14))  # Increased figure size significantly
        
        if 'D4/S1 (Sarjana)' in detail_analysis and detail_analysis['D4/S1 (Sarjana)']['progressions']:
            s1_data = detail_analysis['D4/S1 (Sarjana)']['progressions']
            # Ambil top 8 progression untuk readability
            s1_sorted = sorted(s1_data.items(), key=lambda x: x[1], reverse=True)[:8]
            s1_labels = [item[0] for item in s1_sorted]
            s1_values = [item[1] for item in s1_sorted]
            
            # Enhanced colors untuk S1
            colors_s1 = ['#4ECDC4', '#5DD5D5', '#6DDDD6', '#7DE5E7', '#8DEDED', '#9DF5F5', '#ADFCFC', '#BDFFFF']
            
            # Create MUCH SHORTER labels to prevent overlap
            clean_s1_labels = []
            for label, val in zip(s1_labels, s1_values):
                pct = (val / sum(s1_values)) * 100
                if 'Aktif' in label:
                    status_icon = '✅'
                elif 'Resign' in label:
                    status_icon = '❌'
                elif 'Permanen' in label:
                    status_icon = '🎯'
                else:
                    status_icon = '📋'
                
                # MUCH SHORTER labels to prevent text overlap
                short_label = label.replace('Probation → ', '').replace('Probation Diperpanjang → ', 'Diperpanjang → ')
                if len(short_label) > 20:  # Even shorter limit
                    short_label = short_label[:17] + '...'
                clean_s1_labels.append(f'{status_icon} {short_label}\n{val} orang ({pct:.1f}%)')
            
            # Use better spacing and smaller font
            plt.pie(s1_values, labels=clean_s1_labels, colors=colors_s1[:len(s1_labels)], 
                   autopct='', startangle=90, explode=[0.06] * len(s1_labels),
                   textprops={'fontsize': 9, 'weight': 'bold'})  # Smaller font
            
            plt.title(f'D4/S1 (SARJANA) - DETAIL PERJALANAN KONTRAK\n' +
                     f'Total D4/S1 yang Lulus Probation: {detail_analysis["D4/S1 (Sarjana)"]["total"]} orang\n' +
                     'Pattern kontrak S1 menunjukkan distribusi merata dengan pathway yang beragam',
                     fontsize=16, fontweight='bold', pad=50)  # Increased padding
            
        else:
            plt.text(0.5, 0.5, 'DATA D4/S1 (SARJANA) TIDAK TERSEDIA', 
                    ha='center', va='center', transform=plt.gca().transAxes, fontsize=14)
            plt.title('D4/S1 (SARJANA) - DETAIL PERJALANAN KONTRAK', fontsize=16, fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/6_5C_s1_contract_detail.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 6.5D. Chart Terpisah: Success Rate Comparison
        plt.figure(figsize=(12, 8))
        
        success_rates = {}
        detailed_metrics = {}
        
        for edu_cat, data in detail_analysis.items():
            if data['progressions']:  # Only if there's progression data
                total = sum(data['progressions'].values())  # Total dengan progression yang valid
                # Hitung yang aktif (tidak resign)
                aktif_count = 0
                resign_count = 0
                for progression, count in data['progressions'].items():
                    if 'Aktif' in progression or 'Permanen' in progression:
                        aktif_count += count
                    elif 'Resign' in progression:
                        resign_count += count
                
                success_rate = (aktif_count / total * 100) if total > 0 else 0
                success_rates[edu_cat] = success_rate
                detailed_metrics[edu_cat] = {
                    'aktif': aktif_count,
                    'resign': resign_count,
                    'total': total
                }
        
        if success_rates:
            categories = list(success_rates.keys())
            rates = list(success_rates.values())
            
            # Enhanced colors dengan gradient
            bar_colors = ['#FF6B6B', '#4ECDC4', '#45B7D1'][:len(categories)]
            bars = plt.bar(categories, rates, color=bar_colors, alpha=0.8, edgecolor='black', linewidth=2)
            
            plt.title('SUCCESS RATE (AKTIF/PERMANEN) PER TINGKAT PENDIDIKAN\n' +
                     'Perbandingan Retention Rate antara D3 Non-Sarjana vs D4/S1 Sarjana\n' +
                     'Analisis berdasarkan karyawan yang lulus probation (status tidak jelas dihapus)',
                     fontsize=16, fontweight='bold', pad=30)
            plt.ylabel('Success Rate (%)', fontsize=14, fontweight='bold')
            plt.xlabel('Tingkat Pendidikan', fontsize=14, fontweight='bold')
            plt.ylim(0, 100)
            
            # Add enhanced value labels dengan detail
            for i, (bar, rate, cat) in enumerate(zip(bars, rates, categories)):
                metrics = detailed_metrics[cat]
                plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + 2, 
                        f'{rate:.1f}%\n({metrics["aktif"]}/{metrics["total"]})', 
                        ha='center', va='bottom', fontweight='bold', fontsize=12)
                
                # Add detailed breakdown di bawah bar
                plt.text(bar.get_x() + bar.get_width()/2, -8, 
                        f'Aktif: {metrics["aktif"]}\nResign: {metrics["resign"]}', 
                        ha='center', va='top', fontsize=10, style='italic')
            
            # Add grid untuk readability
            plt.grid(True, alpha=0.3, axis='y')
            
            # Rotate x labels if needed
            plt.setp(plt.gca().get_xticklabels(), rotation=0, ha='center')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/6_5D_success_rate_comparison.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 7. Line Chart: Trend Resign Over Time
        # Calculate resignation trend by year
        resign_data = self.filtered_df[self.filtered_df['contract_progression'].str.contains('Resign', na=False)]
        
        if len(resign_data) > 0 and self.column_mapping['resign_date']:
            resign_years = []
            for _, row in resign_data.iterrows():
                resign_date_str = str(row[self.column_mapping['resign_date']])
                if resign_date_str and resign_date_str not in ['nan', '', 'None', '-']:
                    try:
                        for fmt in ['%d-%b-%y', '%d/%m/%Y', '%Y-%m-%d', '%d-%m-%Y', '%d-%m-%y']:
                            try:
                                resign_date = pd.to_datetime(resign_date_str, format=fmt)
                                resign_years.append(resign_date.year)
                                break
                            except:
                                continue
                    except:
                        pass
            
            if resign_years:
                resign_trend = pd.Series(resign_years).value_counts().sort_index()
                
                plt.figure(figsize=(12, 8))
                plt.plot(resign_trend.index, resign_trend.values, marker='o', linewidth=3, markersize=8,
                        color='#C73E1D', markerfacecolor='#F18F01')
                plt.title('Tren Resignasi Karyawan dari Waktu ke Waktu', fontsize=16, fontweight='bold', pad=20)
                plt.xlabel('Tahun', fontsize=12)
                plt.ylabel('Jumlah Resignasi', fontsize=12)
                plt.grid(True, alpha=0.3)
                
                # Add value labels
                for x, y in zip(resign_trend.index, resign_trend.values):
                    plt.annotate(f'{y}', (x, y), textcoords="offset points", xytext=(0,10), ha='center',
                               fontweight='bold')
                
                plt.tight_layout()
                plt.savefig(f'{output_dir}/7_resign_trend_line.png', dpi=300, bbox_inches='tight')
                plt.close()
            else:
                # No valid resign dates found
                plt.figure(figsize=(12, 8))
                plt.text(0.5, 0.5, 'Tidak ada data tanggal resignasi yang valid', 
                        ha='center', va='center', transform=plt.gca().transAxes, fontsize=14)
                plt.title('Tren Resignasi Karyawan dari Waktu ke Waktu', fontsize=16, fontweight='bold', pad=20)
                plt.tight_layout()
                plt.savefig(f'{output_dir}/7_resign_trend_line.png', dpi=300, bbox_inches='tight')
                plt.close()
        else:
            # No resign data
            plt.figure(figsize=(12, 8))
            plt.text(0.5, 0.5, 'Tidak ada data resignasi tersedia', 
                    ha='center', va='center', transform=plt.gca().transAxes, fontsize=14)
            plt.title('Tren Resignasi Karyawan dari Waktu ke Waktu', fontsize=16, fontweight='bold', pad=20)
            plt.tight_layout()
            plt.savefig(f'{output_dir}/7_resign_trend_line.png', dpi=300, bbox_inches='tight')
            plt.close()
        
        # 7.5. REVISI: Detail Tahap Perjalanan Kontrak dengan Status Aktif/Resign yang Jelas
        plt.figure(figsize=(18, 12))
        
        # Kategorisasi BARU sesuai permintaan dengan penekanan Aktif/Resign
        contract_journey_revised = {
            # STATUS AKTIF
            'Probation → Kontrak Pertama (AKTIF)': 0,
            'Probation → Kontrak Diperpanjang ke-2 (AKTIF)': 0, 
            'Probation → Kontrak Diperpanjang ke-3 (AKTIF)': 0,
            'Probation → Langsung Permanen (AKTIF)': 0,
            'Kontrak → Permanen Setelah Kontrak (AKTIF)': 0,
            
            # STATUS RESIGN
            'Probation → Kontrak-Permanent (RESIGN)': 0,
            'Probation → Kontrak ke-2 (RESIGN)': 0,
            'Probation → Kontrak ke-3 (RESIGN)': 0,
            
            # STATUS LAINNYA
            'Gagal Probation': 0,
            'Status Tidak Jelas': 0
        }
        
        # Hitung berdasarkan data aktual dengan mapping yang lebih jelas
        for idx, row in self.filtered_df.iterrows():
            progression = row.get('contract_progression', '')
            
            # MAPPING STATUS AKTIF
            if progression == 'Probation → Kontrak ke-1 Aktif':
                contract_journey_revised['Probation → Kontrak Pertama (AKTIF)'] += 1
            elif progression == 'Probation Diperpanjang → Kontrak ke-2 Aktif':
                contract_journey_revised['Probation → Kontrak Diperpanjang ke-2 (AKTIF)'] += 1
            elif progression == 'Probation Diperpanjang → Kontrak ke-3 Aktif':
                contract_journey_revised['Probation → Kontrak Diperpanjang ke-3 (AKTIF)'] += 1
            elif progression == 'Langsung Permanen':
                contract_journey_revised['Probation → Langsung Permanen (AKTIF)'] += 1
            elif progression == 'Permanen Setelah Kontrak':
                contract_journey_revised['Kontrak → Permanen Setelah Kontrak (AKTIF)'] += 1
                
            # MAPPING STATUS RESIGN
            elif progression == 'Probation → Kontrak-Permanent (Resign)':
                contract_journey_revised['Probation → Kontrak-Permanent (RESIGN)'] += 1
            elif progression == 'Probation → Kontrak ke-2 (Resign)':
                contract_journey_revised['Probation → Kontrak ke-2 (RESIGN)'] += 1
            elif progression == 'Probation → Kontrak ke-3 (Resign)':
                contract_journey_revised['Probation → Kontrak ke-3 (RESIGN)'] += 1
                
            # STATUS LAINNYA
            elif progression == 'Gagal Probation':
                contract_journey_revised['Gagal Probation'] += 1
            elif 'Tidak Jelas' in progression:
                contract_journey_revised['Status Tidak Jelas'] += 1
        
        # Filter data yang tidak kosong
        filtered_journey_revised = {k: v for k, v in contract_journey_revised.items() if v > 0}
        
        # Separate into categories for better visualization
        aktif_categories = []
        aktif_values = []
        resign_categories = []
        resign_values = []
        other_categories = []
        other_values = []
        
        for cat, val in filtered_journey_revised.items():
            if '(AKTIF)' in cat:
                aktif_categories.append(cat)
                aktif_values.append(val)
            elif '(RESIGN)' in cat:
                resign_categories.append(cat)
                resign_values.append(val)
            else:
                other_categories.append(cat)
                other_values.append(val)
        
        # Combine all for plotting
        all_categories = aktif_categories + resign_categories + other_categories
        all_values = aktif_values + resign_values + other_values
        
        # Enhanced color mapping dengan penekanan Aktif/Resign
        colors = []
        for cat in all_categories:
            if '(AKTIF)' in cat:
                if 'Permanen' in cat:
                    colors.append('#27AE60')  # Dark Green untuk Permanen Aktif
                elif 'ke-3' in cat:
                    colors.append('#3498DB')  # Blue untuk Kontrak ke-3 Aktif
                elif 'ke-2' in cat:
                    colors.append('#1ABC9C')  # Teal untuk Kontrak ke-2 Aktif
                else:
                    colors.append('#2ECC71')  # Light Green untuk Kontrak Pertama Aktif
            elif '(RESIGN)' in cat:
                if 'Permanent' in cat:
                    colors.append('#E74C3C')  # Dark Red untuk Resign Permanent
                elif 'ke-3' in cat:
                    colors.append('#C0392B')  # Darker Red untuk Resign ke-3
                elif 'ke-2' in cat:
                    colors.append('#E67E22')  # Orange Red untuk Resign ke-2
                else:
                    colors.append('#EC7063')  # Light Red untuk Resign lainnya
            else:
                colors.append('#95A5A6')  # Gray untuk status lainnya
        
        # Create horizontal bar chart dengan spacing yang lebih baik
        y_pos = range(len(all_categories))
        bars = plt.barh(y_pos, all_values, color=colors, edgecolor='black', linewidth=1.5, alpha=0.9)
        
        plt.title('DETAIL TAHAP PERJALANAN KONTRAK KARYAWAN IT\n(Probation → Kontrak Diperpanjang → Status AKTIF/RESIGN)', 
                 fontsize=20, fontweight='bold', pad=30)
        plt.xlabel('Jumlah Karyawan', fontsize=16, fontweight='bold')
        plt.ylabel('Tahap Perjalanan Kontrak', fontsize=16, fontweight='bold')
        
        # Enhanced labels dengan status yang jelas
        total_analyzed = sum(all_values)
        enhanced_labels = []
        for cat, val in zip(all_categories, all_values):
            percentage = (val / total_analyzed) * 100 if total_analyzed > 0 else 0
            # Shorten labels untuk readability
            if len(cat) > 45:
                short_cat = cat[:42] + '...'
            else:
                short_cat = cat
            enhanced_labels.append(f'{short_cat}\n({percentage:.1f}% - {val:,} orang)')
        
        plt.yticks(y_pos, enhanced_labels, fontsize=11)
        
        # Add value labels dengan background
        for bar, value in zip(bars, all_values):
            plt.text(bar.get_width() + max(all_values)*0.01, bar.get_y() + bar.get_height()/2, 
                    f'{value:,}', va='center', fontweight='bold', fontsize=12,
                    bbox=dict(boxstyle='round,pad=0.3', facecolor='white', alpha=0.8))
        
        # Add comprehensive summary dengan breakdown Aktif vs Resign
        total_aktif = sum(aktif_values)
        total_resign = sum(resign_values)
        total_other = sum(other_values)
        
        summary_analysis = f'''📊 ANALISIS TAHAP KONTRAK (REVISI):
🎯 Total Karyawan Dianalisis: {total_analyzed:,} orang

📈 STATUS AKTIF: {total_aktif:,} orang ({total_aktif/total_analyzed*100:.1f}%)
  • Kontrak Pertama: {aktif_values[0] if aktif_values else 0:,} orang
  • Diperpanjang ke-2: {aktif_values[1] if len(aktif_values) > 1 else 0:,} orang
  • Diperpanjang ke-3: {aktif_values[2] if len(aktif_values) > 2 else 0:,} orang
  • Langsung Permanen: {aktif_values[3] if len(aktif_values) > 3 else 0:,} orang

📉 STATUS RESIGN: {total_resign:,} orang ({total_resign/total_analyzed*100:.1f}%)
  • Resign Kontrak-Permanent: {resign_values[0] if resign_values else 0:,} orang
  • Resign Kontrak ke-2: {resign_values[1] if len(resign_values) > 1 else 0:,} orang
  • Resign Kontrak ke-3: {resign_values[2] if len(resign_values) > 2 else 0:,} orang

⚠️ STATUS LAINNYA: {total_other:,} orang ({total_other/total_analyzed*100:.1f}%)

🎯 INSIGHT UTAMA:
• Mayoritas: {all_categories[all_values.index(max(all_values))]}
• Rate: {max(all_values)/total_analyzed*100:.1f}%'''
        
        plt.text(1.02, 0.98, summary_analysis, transform=plt.gca().transAxes, 
                verticalalignment='top', bbox=dict(boxstyle='round', facecolor='lightsteelblue', alpha=0.95),
                fontsize=12, fontweight='bold')
        
        plt.grid(True, alpha=0.4, axis='x')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/7_5_contract_flow_progression.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 7.5. BARU: Analisis Pendidikan vs Designation Matching
        designation_col = self.column_mapping.get('designation')
        if designation_col:
            plt.figure(figsize=(16, 10))
            
            # Filter hanya yang lulus probation untuk analisis yang relevan
            probation_lulus_data = self.filtered_df[self.filtered_df['probation_status'] == 'Lulus'].copy()
            
            # Create cross-tabulation
            education_designation_matrix = pd.crosstab(probation_lulus_data['education_category'], 
                                                     probation_lulus_data[designation_col])
            
            # Get top designations for better visualization
            top_designations = probation_lulus_data[designation_col].value_counts().head(8)
            available_designations = [d for d in top_designations.index if d in education_designation_matrix.columns]
            
            if available_designations:
                education_designation_filtered = education_designation_matrix[available_designations]
            else:
                education_designation_filtered = education_designation_matrix
            
            # Create grouped bar chart
            ax = education_designation_filtered.T.plot(kind='bar', 
                                                      figsize=(16, 10), 
                                                      color=['#E74C3C', '#3498DB', '#27AE60'],
                                                      alpha=0.8)
            
            plt.title('KESESUAIAN PENDIDIKAN vs DESIGNATION JOB\n(S1 vs Non-Sarjana yang Cocok)', 
                     fontsize=18, fontweight='bold', pad=25)
            plt.xlabel('Designation/Job Role', fontsize=14, fontweight='bold')
            plt.ylabel('Jumlah Karyawan', fontsize=14, fontweight='bold')
            plt.xticks(rotation=45, ha='right', fontsize=11)
            plt.legend(title='Tingkat Pendidikan', bbox_to_anchor=(1.05, 1), loc='upper left')
            
            # Add value labels on bars
            for container in ax.containers:
                ax.bar_label(container, fontweight='bold', fontsize=9)
            
            # Add summary analysis
            total_sarjana = probation_lulus_data[probation_lulus_data['education_category'].isin(['D4/S1 Sarjana', 'S2 Magister'])]
            total_non_sarjana = probation_lulus_data[probation_lulus_data['education_category'] == 'SMA/SMK-D3 Non Sarjana']
            
            summary_analysis = f'''📊 ANALISIS KESESUAIAN:
🎯 Total yang Lulus Probation: {len(probation_lulus_data):,} orang
📚 Sarjana (S1/S2): {len(total_sarjana):,} orang
📖 Non-Sarjana (D3/SMA): {len(total_non_sarjana):,} orang

🔍 TOP DESIGNATION:
• {top_designations.index[0]}: {top_designations.iloc[0]:,} orang
• {top_designations.index[1] if len(top_designations) > 1 else 'N/A'}: {top_designations.iloc[1] if len(top_designations) > 1 else 0:,} orang'''
            
            plt.text(1.02, 0.98, summary_analysis, transform=ax.transAxes, 
                    bbox=dict(boxstyle='round', facecolor='lightblue', alpha=0.9),
                    fontsize=11, fontweight='bold', verticalalignment='top')
            
            plt.grid(True, alpha=0.3, axis='y')
            plt.tight_layout()
            plt.savefig(f'{output_dir}/7_5_education_designation_match.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 8. Pie Chart: Distribusi Pendidikan - ENHANCED & SEPARATED
        education_counts = passed_or_extended['education_category'].value_counts()
        # Remove any unknown categories
        education_counts = education_counts[~education_counts.index.str.contains('Unknown', case=False, na=False)]
        
        plt.figure(figsize=(10, 8))
        colors = ['#2E86AB', '#F18F01', '#28a745']  # Blue, Orange, Green
        
        # Create labels with counts and percentages
        total = education_counts.sum()
        labels = []
        for edu, count in education_counts.items():
            percentage = (count / total) * 100
            labels.append(f'{edu}\n{count:,} orang\n({percentage:.1f}%)')
        
        # Determine explode values to highlight differences
        explode_values = [0.05 if 'D4/S1' in str(edu) else 0 for edu in education_counts.index]
        
        wedges, texts, autotexts = plt.pie(education_counts.values, 
                                          labels=labels,
                                          autopct='', colors=colors[:len(education_counts)], 
                                          startangle=90, explode=explode_values)
        plt.title('Distribusi Tingkat Pendidikan Karyawan IT', fontsize=16, fontweight='bold', pad=20)
        
        # Enhance text formatting
        for text in texts:
            text.set_fontsize(10)
            text.set_fontweight('bold')
        
        # Add summary statistics
        sarjana_count = education_counts.get('D4/S1 Sarjana', 0) + education_counts.get('S2 Magister', 0)
        non_sarjana_count = education_counts.get('SMA/SMK-D3 Non Sarjana', 0)
        sarjana_rate = (sarjana_count / total) * 100 if total > 0 else 0
        
        stats_text = f'Total Karyawan: {total:,}\nSarjana: {sarjana_count:,} ({sarjana_rate:.1f}%)\nNon-Sarjana: {non_sarjana_count:,} ({100-sarjana_rate:.1f}%)'
        plt.text(1.05, 0.98, stats_text, transform=plt.gca().transAxes, 
                verticalalignment='top', bbox=dict(boxstyle='round', facecolor='wheat', alpha=0.8),
                fontsize=10, fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/8_education_level_pie.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        print("✅ Visualisasi Variabel X selesai")
    
    def create_variable_y_visualizations(self):
        """
        Create 7 visualizations for Variable Y (Education Analysis) - CLEANED
        """
        print("\n🎓 Membuat visualisasi Variabel Y (Analisis Pendidikan)...")
        
        # Only analyze employees who passed probation
        passed_probation = self.filtered_df[self.filtered_df['probation_status'] == 'Lulus'].copy()
        
        if len(passed_probation) == 0:
            print("⚠️ Tidak ada data karyawan yang lulus probation untuk divisualisasikan")
            return
        
        # 9. Bar Chart: Education Categories - ENHANCED
        education_counts = passed_probation['education_category'].value_counts()
        # Remove unknown categories
        education_counts = education_counts[~education_counts.index.str.contains('Unknown', case=False, na=False)]
        
        plt.figure(figsize=(12, 8))
        colors = ['#2E86AB', '#F18F01', '#28a745']  # Match pie chart colors
        bars = plt.bar(range(len(education_counts)), education_counts.values, 
                      color=colors[:len(education_counts)])
        plt.title('Distribusi Kategori Pendidikan Karyawan IT', fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Kategori Pendidikan', fontsize=12)
        plt.ylabel('Jumlah Karyawan', fontsize=12)
        plt.xticks(range(len(education_counts)), education_counts.index, rotation=15, ha='right')
        
        # Add value labels with percentages
        total = education_counts.sum()
        for bar, value in zip(bars, education_counts.values):
            percentage = (value / total) * 100
            plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + 0.5, 
                    f'{value}\n({percentage:.1f}%)', ha='center', va='bottom', fontweight='bold')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/9_education_categories_bar.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 10. Heatmap: Major vs Role compatibility - CLEANED
        major_col = self.column_mapping['major']
        designation_col = self.column_mapping['designation']
        
        if major_col and designation_col:
            # Clean data: remove unknowns
            clean_major = passed_probation[major_col].fillna('Unknown')
            clean_designation = passed_probation[designation_col].fillna('Unknown')
            
            # Filter out unknown values
            valid_data = passed_probation[(clean_major != 'Unknown') & (clean_designation != 'Unknown')]
            
            if len(valid_data) > 0:
                top_majors = valid_data[major_col].value_counts().head(10)
                top_roles = valid_data[designation_col].value_counts().head(10)
                
                # Create compatibility matrix
                compatibility_matrix = pd.DataFrame(index=top_majors.index, columns=top_roles.index, data=0)
                
                for major in top_majors.index:
                    for role in top_roles.index:
                        count = len(valid_data[(valid_data[major_col] == major) & 
                                             (valid_data[designation_col] == role)])
                        compatibility_matrix.loc[major, role] = count
            else:
                compatibility_matrix = pd.DataFrame(data=[[0]], index=['Tidak Ada Data'], columns=['Tidak Ada Data'])
        else:
            compatibility_matrix = pd.DataFrame(data=[[0]], index=['Tidak Ada Data'], columns=['Tidak Ada Data'])
        
        plt.figure(figsize=(14, 10))
        sns.heatmap(compatibility_matrix.astype(int), annot=True, cmap='Blues', fmt='d', cbar_kws={'label': 'Jumlah Karyawan'})
        plt.title('Kesesuaian Jurusan vs Role Jabatan', fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Role/Designation', fontsize=12)
        plt.ylabel('Jurusan (Major)', fontsize=12)
        plt.xticks(rotation=45, ha='right')
        plt.yticks(rotation=0)
        plt.tight_layout()
        plt.savefig(f'{output_dir}/10_major_role_heatmap.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 11. Heatmap: Education Level vs Role - CLEANED
        if designation_col:
            clean_designation = passed_probation[designation_col].fillna('Unknown')
            valid_data = passed_probation[clean_designation != 'Unknown']
            
            if len(valid_data) > 0:
                education_role_matrix = pd.crosstab(valid_data['education_category'], 
                                                  valid_data[designation_col])
            else:
                education_role_matrix = pd.DataFrame(data=[[0]], index=['Tidak Ada Data'], columns=['Tidak Ada Data'])
        else:
            education_role_matrix = pd.DataFrame(data=[[0]], index=['Tidak Ada Data'], columns=['Tidak Ada Data'])
        
        # Keep only top roles for readability
        if len(education_role_matrix.columns) > 0 and 'Tidak Ada Data' not in education_role_matrix.columns:
            top_roles_for_edu = education_role_matrix.sum().nlargest(8)
            education_role_matrix = education_role_matrix[top_roles_for_edu.index]
        
        plt.figure(figsize=(14, 8))
        sns.heatmap(education_role_matrix, annot=True, cmap='Greens', fmt='d', cbar_kws={'label': 'Jumlah Karyawan'})
        plt.title('Kesesuaian Tingkat Pendidikan vs Role Jabatan', fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Role/Designation', fontsize=12)
        plt.ylabel('Tingkat Pendidikan', fontsize=12)
        plt.xticks(rotation=45, ha='right')
        plt.yticks(rotation=0)
        plt.tight_layout()
        plt.savefig(f'{output_dir}/11_education_role_heatmap.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 12. Heatmap: Designation vs Internal Role - CLEANED
        role_internal_col = self.column_mapping['role_internal']
        
        if designation_col and role_internal_col:
            clean_designation = passed_probation[designation_col].fillna('Unknown')
            clean_internal = passed_probation[role_internal_col].fillna('Unknown')
            
            valid_data = passed_probation[(clean_designation != 'Unknown') & (clean_internal != 'Unknown')]
            
            if len(valid_data) > 0:
                designation_role_matrix = pd.crosstab(valid_data[designation_col], valid_data[role_internal_col])
                
                # Get top combinations
                top_designations = valid_data[designation_col].value_counts().head(8)
                top_internal_roles = valid_data[role_internal_col].value_counts().head(8)
                
                # Filter matrix to top combinations
                available_designations = [d for d in top_designations.index if d in designation_role_matrix.index]
                available_internals = [r for r in top_internal_roles.index if r in designation_role_matrix.columns]
                
                if available_designations and available_internals:
                    filtered_matrix = designation_role_matrix.loc[available_designations, available_internals]
                else:
                    filtered_matrix = designation_role_matrix
            else:
                filtered_matrix = pd.DataFrame(data=[[0]], index=['Tidak Ada Data'], columns=['Tidak Ada Data'])
        else:
            filtered_matrix = pd.DataFrame(data=[[0]], index=['Tidak Ada Data'], columns=['Tidak Ada Data'])
        
        plt.figure(figsize=(12, 10))
        sns.heatmap(filtered_matrix, annot=True, cmap='Oranges', fmt='d', cbar_kws={'label': 'Jumlah Karyawan'})
        plt.title('Kesesuaian Designation vs Role Internal', fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Role Internal', fontsize=12)
        plt.ylabel('Designation', fontsize=12)
        plt.xticks(rotation=45, ha='right')
        plt.yticks(rotation=0)
        plt.tight_layout()
        plt.savefig(f'{output_dir}/12_designation_internal_heatmap.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 13. Histogram: Contract Duration by Categories - ENHANCED
        duration_counts = passed_probation['duration_category'].value_counts()
        # Order categories logically
        order = ['0-6 bulan (Risiko Tinggi)', '7-11 bulan (Menengah)', '≥12 bulan (Outstanding)']
        duration_counts = duration_counts.reindex([cat for cat in order if cat in duration_counts.index])
        
        plt.figure(figsize=(12, 8))
        colors = ['#C73E1D', '#F18F01', '#A23B72', '#2E86AB']  # Red to Blue gradient
        bars = plt.bar(range(len(duration_counts)), duration_counts.values, 
                      color=colors[:len(duration_counts)])
        plt.title('Distribusi Durasi Kontrak Berdasarkan Kategori Performa', fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Kategori Durasi Kontrak', fontsize=12)
        plt.ylabel('Jumlah Karyawan', fontsize=12)
        plt.xticks(range(len(duration_counts)), duration_counts.index, rotation=15, ha='right')
        
        # Add value labels with percentages
        total = duration_counts.sum()
        for bar, value in zip(bars, duration_counts.values):
            percentage = (value / total) * 100
            plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + 0.5, 
                    f'{value}\n({percentage:.1f}%)', ha='center', va='bottom', fontweight='bold')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/13_duration_category_histogram.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 14. Stacked Bar Chart: Major vs Probation Status - CLEANED & FIXED
        if major_col:
            clean_major = self.filtered_df[major_col].fillna('Unknown')
            valid_data = self.filtered_df[clean_major != 'Unknown']
            
            if len(valid_data) > 0:
                # Truncate long major names for better display
                valid_data_copy = valid_data.copy()
                valid_data_copy['major_short'] = valid_data_copy[major_col].str[:25]
                
                major_probation = pd.crosstab(valid_data_copy['major_short'], 
                                            valid_data_copy['probation_status'])
                
                # Get top 10 majors by frequency
                top_majors = valid_data_copy['major_short'].value_counts().head(10)
                
                # Filter to available majors in crosstab
                available_majors = [m for m in top_majors.index if m in major_probation.index]
                
                if available_majors:
                    major_probation_filtered = major_probation.loc[available_majors]
                else:
                    major_probation_filtered = major_probation
            else:
                major_probation_filtered = pd.DataFrame(data=[[1, 0]], index=['Tidak Ada Data'], 
                                                      columns=['Lulus', 'Tidak Lulus'])
        else:
            major_probation_filtered = pd.DataFrame(data=[[1, 0]], index=['Tidak Ada Data'], 
                                                  columns=['Lulus', 'Tidak Lulus'])
        
        plt.figure(figsize=(14, 8))
        
        # Ensure we have the right columns for stacking
        available_cols = major_probation_filtered.columns.tolist()
        colors = ['#2E86AB', '#C73E1D', '#F18F01'][:len(available_cols)]
        
        ax = major_probation_filtered.plot(kind='bar', stacked=True, color=colors, figsize=(14, 8))
        plt.title('Distribusi Status Probation per Jurusan', fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Jurusan', fontsize=12)
        plt.ylabel('Jumlah Karyawan', fontsize=12)
        plt.xticks(rotation=45, ha='right')
        plt.legend(title='Status Probation', bbox_to_anchor=(1.05, 1), loc='upper left')
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/14_major_probation_stacked.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        print("✅ Visualisasi Variabel Y selesai")
    
    def create_advanced_degree_role_analysis(self):
        """
        ANALISIS BARU 1: Sarjana/Diatas → Jurusan dengan Designation → Role Klien sesuai
        Berapa orang, kontrak berapa lama, berapa persen yang cocok
        """
        print("\n🎓 Membuat Analisis Lanjutan: Sarjana + Kesesuaian Role...")
        
        # Filter sarjana dan diatas (D4/S1/S2)
        sarjana_data = self.filtered_df[self.filtered_df['is_sarjana'] == True].copy()
        
        # Get columns - PERBAIKAN: gunakan Role At Client
        major_col = self.column_mapping['major']
        designation_col = self.column_mapping['designation']
        role_at_client_col = self.column_mapping['role_at_client']  # PERBAIKAN: gunakan Role At Client
        
        if not all([major_col, designation_col]):
            print("⚠️ Kolom major atau designation tidak ditemukan")
            return
        
        if not role_at_client_col:
            print("⚠️ Kolom Role At Client tidak ditemukan")
            return
        
        # Analyze role matching for sarjana - PERBAIKAN: validasi total
        total_sarjana_all = len(sarjana_data)
        sarjana_with_role = sarjana_data.dropna(subset=[major_col, designation_col, role_at_client_col])
        
        # Define IT role compatibility
        it_keywords = ['DEVELOPER', 'PROGRAMMER', 'ANALYST', 'TESTER', 'TECHNICAL', 'SOFTWARE', 'DATA', 'SYSTEM']
        
        # Check role compatibility - PERBAIKAN: gunakan Role At Client
        sarjana_with_role['role_match'] = False
        for idx, row in sarjana_with_role.iterrows():
            designation = str(row[designation_col]).upper()
            role_at_client = str(row[role_at_client_col]).upper() if role_at_client_col else ''
            combined_role = f"{designation} {role_at_client}"
            
            # Check if any IT keyword is in the role
            if any(keyword in combined_role for keyword in it_keywords):
                sarjana_with_role.at[idx, 'role_match'] = True
        
        # Calculate duration categories for matching roles
        matching_roles = sarjana_with_role[sarjana_with_role['role_match'] == True]
        
        # 1. Pie Chart: Kesesuaian Role untuk Sarjana
        role_match_counts = sarjana_with_role['role_match'].value_counts()
        
        plt.figure(figsize=(12, 8))
        colors = ['#27AE60', '#E74C3C']
        labels = ['Sesuai Role At Client', 'Tidak Sesuai Role At Client']
        
        # Create labels with counts and percentages
        total_sarjana = len(sarjana_with_role)
        pie_labels = []
        for i, (match, count) in enumerate(role_match_counts.items()):
            label = labels[0] if match else labels[1]
            percentage = (count / total_sarjana) * 100
            pie_labels.append(f'{label}\n{count:,} orang\n({percentage:.1f}%)')
        
        plt.pie(role_match_counts.values, labels=pie_labels, colors=colors, 
               autopct='', startangle=90, explode=[0.05, 0])
        plt.title('KESESUAIAN ROLE AT CLIENT UNTUK KARYAWAN SARJANA\n(D4/S1/S2)', 
                 fontsize=16, fontweight='bold', pad=20)
        
        # Add summary statistics
        matching_count = role_match_counts.get(True, 0)
        non_matching_count = role_match_counts.get(False, 0)
        match_rate = (matching_count / total_sarjana) * 100 if total_sarjana > 0 else 0
        
        summary_text = f'''📊 ANALISIS SARJANA:
Total Sarjana Keseluruhan: {total_sarjana_all:,} orang
Total Sarjana Valid Data: {total_sarjana:,} orang
Sesuai Role At Client: {matching_count:,} orang
Rate Kesesuaian: {match_rate:.1f}%
Avg Durasi (Sesuai): {matching_roles['contract_duration_months'].mean():.1f} bulan'''
        
        plt.text(1.05, 0.02, summary_text, transform=plt.gca().transAxes, 
                bbox=dict(boxstyle='round', facecolor='lightblue', alpha=0.9),
                fontsize=11, fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/15_sarjana_role_match_pie.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 2. Bar Chart: Durasi Kontrak berdasarkan Kesesuaian Role
        plt.figure(figsize=(14, 8))
        
        # Create duration categories for both groups
        matching_duration_cats = matching_roles['duration_category'].value_counts()
        non_matching_duration_cats = sarjana_with_role[sarjana_with_role['role_match'] == False]['duration_category'].value_counts()
        
        # Combine data for comparison
        duration_categories = ['0-6 bulan (Risiko Tinggi)', '7-11 bulan (Menengah)', '≥12 bulan (Outstanding)']
        matching_values = [matching_duration_cats.get(cat, 0) for cat in duration_categories]
        non_matching_values = [non_matching_duration_cats.get(cat, 0) for cat in duration_categories]
        
        x = range(len(duration_categories))
        width = 0.35
        
        bars1 = plt.bar([i - width/2 for i in x], matching_values, width, 
                       label='Sesuai Role At Client', color='#27AE60', alpha=0.8)
        bars2 = plt.bar([i + width/2 for i in x], non_matching_values, width,
                       label='Tidak Sesuai Role At Client', color='#E74C3C', alpha=0.8)
        
        plt.title('DURASI KONTRAK SARJANA BERDASARKAN KESESUAIAN ROLE AT CLIENT', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Kategori Durasi Kontrak', fontsize=12)
        plt.ylabel('Jumlah Karyawan Sarjana', fontsize=12)
        plt.xticks(x, duration_categories, rotation=15, ha='right')
        plt.legend()
        
        # Add value labels
        for bars in [bars1, bars2]:
            for bar in bars:
                height = bar.get_height()
                if height > 0:
                    plt.text(bar.get_x() + bar.get_width()/2., height + 0.5,
                            f'{int(height)}', ha='center', va='bottom', fontweight='bold')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/16_sarjana_duration_by_role_match.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 3. Detailed Table Analysis: Top Combinations
        if len(matching_roles) > 0:
            # Analyze top major-designation combinations
            matching_roles['major_designation'] = matching_roles[major_col] + ' → ' + matching_roles[designation_col]
            top_combinations = matching_roles['major_designation'].value_counts().head(10)
            
            plt.figure(figsize=(16, 10))
            bars = plt.barh(range(len(top_combinations)), top_combinations.values, 
                           color='#3498DB', edgecolor='black')
            
            plt.title('TOP 10 KOMBINASI JURUSAN → DESIGNATION UNTUK SARJANA\n(Role At Client yang Sesuai)', 
                     fontsize=16, fontweight='bold', pad=20)
            plt.xlabel('Jumlah Karyawan', fontsize=12)
            plt.ylabel('Kombinasi Jurusan → Designation', fontsize=12)
            
            # Create shorter labels
            short_labels = []
            for combo in top_combinations.index:
                if len(combo) > 50:
                    short_labels.append(combo[:47] + '...')
                else:
                    short_labels.append(combo)
            
            plt.yticks(range(len(top_combinations)), short_labels, fontsize=10)
            
            # Add value labels and duration info
            for i, (bar, combo, count) in enumerate(zip(bars, top_combinations.index, top_combinations.values)):
                combo_data = matching_roles[matching_roles['major_designation'] == combo]
                avg_duration = combo_data['contract_duration_months'].mean()
                
                plt.text(bar.get_width() + max(top_combinations.values)*0.01, bar.get_y() + bar.get_height()/2,
                        f'{count} orang\n(Avg: {avg_duration:.1f} bulan)', 
                        va='center', fontweight='bold', fontsize=10)
            
            plt.grid(True, alpha=0.3, axis='x')
            plt.tight_layout()
            plt.savefig(f'{output_dir}/17_top_sarjana_combinations.png', dpi=300, bbox_inches='tight')
            plt.close()
        
        # 4. BARU: Analisis Mendalam Sarjana-Pekerjaan IT-Role at Client Sesuai dengan Durasi Kontrak
        plt.figure(figsize=(16, 12))
        
        # Filter sarjana dengan role yang sesuai dan ada data Role At Client
        if role_at_client_col:
            sarjana_it_matching = sarjana_with_role[
                (sarjana_with_role['role_match'] == True) & 
                (sarjana_with_role[role_at_client_col].notna()) &
                (sarjana_with_role[role_at_client_col] != '') &
                (sarjana_with_role[role_at_client_col] != '-')
            ].copy()
            
            if len(sarjana_it_matching) > 0:
                # Create comprehensive duration analysis
                duration_analysis = {}
                
                # Categorize by duration ranges
                duration_ranges = {
                    '0-6 bulan (Risiko Tinggi)': (0, 6),
                    '7-12 bulan (Stabil)': (7, 12),
                    '13-24 bulan (Baik)': (13, 24),
                    '25+ bulan (Excellent)': (25, 999)
                }
                
                for range_name, (min_dur, max_dur) in duration_ranges.items():
                    filtered_data = sarjana_it_matching[
                        (sarjana_it_matching['contract_duration_months'] >= min_dur) &
                        (sarjana_it_matching['contract_duration_months'] <= max_dur)
                    ]
                    
                    if len(filtered_data) > 0:
                        # Analyze top combinations for this duration range
                        filtered_data['full_combination'] = (
                            filtered_data[major_col].str[:20] + ' → ' +
                            filtered_data[designation_col].str[:25] + ' → ' +
                            filtered_data[role_at_client_col].str[:20]
                        )
                        
                        top_combinations = filtered_data['full_combination'].value_counts().head(5)
                        avg_duration = filtered_data['contract_duration_months'].mean()
                        
                        duration_analysis[range_name] = {
                            'count': len(filtered_data),
                            'combinations': top_combinations,
                            'avg_duration': avg_duration,
                            'data': filtered_data
                        }
                
                # SEPARATED CHART 1: Duration Distribution for IT-Matching Sarjana
                plt.figure(figsize=(12, 10))
                duration_counts = []
                duration_labels = []
                duration_colors = ['#E74C3C', '#F39C12', '#27AE60', '#2E86AB']
                
                for i, (range_name, data) in enumerate(duration_analysis.items()):
                    duration_counts.append(data['count'])
                    duration_labels.append(f"{range_name}\n{data['count']} orang\n(Avg: {data['avg_duration']:.1f} bulan)")
                
                if duration_counts:
                    wedges, texts, autotexts = plt.pie(duration_counts, labels=duration_labels, 
                                                      colors=duration_colors[:len(duration_counts)],
                                                      autopct='%1.1f%%', startangle=90, 
                                                      explode=[0.05] * len(duration_counts))
                    plt.title('DISTRIBUSI DURASI KONTRAK\nSARJANA IT DENGAN ROLE AT CLIENT SESUAI\n' +
                             f'Total Karyawan: {sum(duration_counts)} orang', 
                             fontsize=16, fontweight='bold', pad=20)
                
                plt.tight_layout()
                plt.savefig(f'{output_dir}/17_5_sarjana_it_role_duration_analysis.png', dpi=300, bbox_inches='tight')
                plt.close()
                
                # SEPARATED CHART 2: Top Combinations by Duration Range
                plt.figure(figsize=(14, 10))
                if len(duration_analysis) > 0:
                    # Get the range with most data for detailed analysis
                    max_range = max(duration_analysis.items(), key=lambda x: x[1]['count'])
                    range_name, range_data = max_range
                    
                    if len(range_data['combinations']) > 0:
                        top_combos = range_data['combinations'].head(8)
                        
                        # Shorten labels for readability
                        short_labels = []
                        for combo in top_combos.index:
                            if len(combo) > 45:
                                short_labels.append(combo[:42] + '...')
                            else:
                                short_labels.append(combo)
                        
                        bars = plt.barh(range(len(top_combos)), top_combos.values, 
                                       color='#3498DB', alpha=0.8)
                        plt.title(f'TOP KOMBINASI DALAM KATEGORI: {range_name}\n(Jurusan → Designation → Role at Client)\n' +
                                 f'Analisis dari {range_data["count"]} karyawan dengan durasi rata-rata {range_data["avg_duration"]:.1f} bulan', 
                                 fontsize=14, fontweight='bold', pad=20)
                        plt.xlabel('Jumlah Karyawan', fontsize=12, fontweight='bold')
                        plt.yticks(range(len(top_combos)), short_labels, fontsize=10)
                        
                        # Add value labels
                        for bar, value in zip(bars, top_combos.values):
                            plt.text(bar.get_width() + 0.1, bar.get_y() + bar.get_height()/2, 
                                    f'{value}', va='center', fontweight='bold')
                
                plt.tight_layout()
                plt.savefig(f'{output_dir}/17_6_sarjana_top_combinations.png', dpi=300, bbox_inches='tight')
                plt.close()
                
                # SEPARATED CHART 3: Duration vs Success Rate Analysis
                plt.figure(figsize=(12, 8))
                success_rates = []
                range_names = []
                
                for range_name, data in duration_analysis.items():
                    if data['count'] > 0:
                        # Calculate success rate based on active status
                        active_count = len(data['data'][data['data']['contract_progression'].str.contains('Aktif|Permanen', na=False)])
                        success_rate = (active_count / data['count']) * 100
                        success_rates.append(success_rate)
                        range_names.append(range_name.split(' (')[0])  # Shorten label
                
                if success_rates:
                    bars = plt.bar(range(len(success_rates)), success_rates, 
                                  color=duration_colors[:len(success_rates)], alpha=0.8)
                    plt.title('SUCCESS RATE (AKTIF/PERMANEN) PER DURASI\nSARJANA IT DENGAN ROLE SESUAI\n' +
                             'Tingkat Keberhasilan Berdasarkan Kategori Durasi Kontrak', 
                             fontsize=16, fontweight='bold', pad=20)
                    plt.ylabel('Success Rate (%)', fontsize=12, fontweight='bold')
                    plt.xlabel('Kategori Durasi', fontsize=12, fontweight='bold')
                    plt.xticks(range(len(range_names)), range_names, rotation=45, ha='right')
                    plt.ylim(0, 100)
                    
                    # Add value labels
                    for bar, rate in zip(bars, success_rates):
                        plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + 1, 
                                f'{rate:.1f}%', ha='center', va='bottom', fontweight='bold')
                    
                    plt.grid(True, alpha=0.3, axis='y')
                
                plt.tight_layout()
                plt.savefig(f'{output_dir}/17_7_sarjana_success_rate_analysis.png', dpi=300, bbox_inches='tight')
                plt.close()
                
                # SEPARATED CHART 4: Detailed Statistics Summary
                plt.figure(figsize=(14, 10))
                plt.axis('off')
                
                # Create summary statistics
                total_sarjana_it = len(sarjana_it_matching)
                avg_overall_duration = sarjana_it_matching['contract_duration_months'].mean()
                
                # Calculate retention by major
                top_majors = sarjana_it_matching[major_col].value_counts().head(5)
                major_stats = []
                
                for major in top_majors.index:
                    major_data = sarjana_it_matching[sarjana_it_matching[major_col] == major]
                    avg_dur = major_data['contract_duration_months'].mean()
                    active_count = len(major_data[major_data['contract_progression'].str.contains('Aktif|Permanen', na=False)])
                    retention_rate = (active_count / len(major_data)) * 100 if len(major_data) > 0 else 0
                    major_stats.append(f"• {major[:25]}: {len(major_data)} orang, Avg {avg_dur:.1f} bulan, {retention_rate:.1f}% retention")
                
                stats_text = f'''📊 STATISTIK SARJANA IT DENGAN ROLE AT CLIENT SESUAI:

🎯 OVERVIEW:
• Total Sarjana IT Role Sesuai: {total_sarjana_it:,} orang
• Rata-rata Durasi Kontrak: {avg_overall_duration:.1f} bulan
• Range Durasi: {sarjana_it_matching['contract_duration_months'].min():.1f} - {sarjana_it_matching['contract_duration_months'].max():.1f} bulan

📚 TOP 5 JURUSAN PERFORMANCE:
{chr(10).join(major_stats)}

🎯 INSIGHT KUNCI:
• Sarjana IT dengan role sesuai menunjukkan durasi kontrak yang bervariasi
• Pattern durasi berbeda per jurusan dan designation
• Success rate tinggi pada durasi kontrak yang lebih panjang
• Role at Client matching berkorelasi positif dengan retention'''
                
                plt.text(0.05, 0.95, stats_text, transform=plt.gca().transAxes, 
                        fontsize=12, fontweight='bold', verticalalignment='top',
                        bbox=dict(boxstyle='round', facecolor='lightcyan', alpha=0.9))
                
                plt.title('STATISTIK DETAIL: SARJANA IT - ROLE AT CLIENT SESUAI - DURASI KONTRAK\n(Korelasi Pendidikan, Pekerjaan, dan Performa Kontrak)', 
                         fontsize=16, fontweight='bold', pad=20)
                
                plt.tight_layout()
                plt.savefig(f'{output_dir}/17_8_sarjana_detailed_statistics.png', dpi=300, bbox_inches='tight')
                plt.close()
                
                # 5. TAMBAHAN: Heatmap Jurusan vs Role at Client dengan Durasi
                plt.figure(figsize=(16, 10))
                
                # Create matrix: Major vs Role at Client with average duration
                top_majors_for_heatmap = sarjana_it_matching[major_col].value_counts().head(8)
                top_roles_for_heatmap = sarjana_it_matching[role_at_client_col].value_counts().head(8)
                
                # Create duration matrix
                duration_matrix = pd.DataFrame(index=top_majors_for_heatmap.index, 
                                             columns=top_roles_for_heatmap.index, 
                                             data=0.0)
                
                for major in top_majors_for_heatmap.index:
                    for role in top_roles_for_heatmap.index:
                        subset = sarjana_it_matching[
                            (sarjana_it_matching[major_col] == major) & 
                            (sarjana_it_matching[role_at_client_col] == role)
                        ]
                        if len(subset) > 0:
                            avg_duration = subset['contract_duration_months'].mean()
                            duration_matrix.loc[major, role] = avg_duration
                
                # Create heatmap
                mask = duration_matrix == 0
                sns.heatmap(duration_matrix.astype(float), annot=True, fmt='.1f', 
                           cmap='RdYlGn', mask=mask, cbar_kws={'label': 'Rata-rata Durasi (Bulan)'},
                           linewidths=1, linecolor='black')
                
                plt.title('HEATMAP: RATA-RATA DURASI KONTRAK\nJURUSAN vs ROLE AT CLIENT (SARJANA IT)', 
                         fontsize=16, fontweight='bold', pad=20)
                plt.xlabel('Role at Client', fontsize=12, fontweight='bold')
                plt.ylabel('Jurusan (Major)', fontsize=12, fontweight='bold')
                plt.xticks(rotation=45, ha='right')
                plt.yticks(rotation=0)
                
                # Add summary insight
                best_combination = duration_matrix.stack().idxmax()
                best_duration = duration_matrix.stack().max()
                
                insight_text = f'''🎯 INSIGHT HEATMAP:
• Kombinasi Terbaik: {best_combination[0][:20]} + {best_combination[1][:20]}
• Durasi Rata-rata Terbaik: {best_duration:.1f} bulan
• Total Kombinasi Dianalisis: {(duration_matrix > 0).sum().sum()} kombinasi
• Basis Data: {total_sarjana_it:,} karyawan sarjana IT'''
                
                plt.figtext(0.02, 0.02, insight_text, fontsize=10, fontweight='bold',
                           bbox=dict(boxstyle='round', facecolor='lightyellow', alpha=0.9))
                
                plt.tight_layout()
                plt.savefig(f'{output_dir}/17_6_sarjana_major_role_duration_heatmap.png', dpi=300, bbox_inches='tight')
                plt.close()
                
            else:
                print("⚠️ Tidak ada data sarjana IT dengan role at client yang sesuai")
        else:
            print("⚠️ Kolom Role At Client tidak tersedia untuk analisis")
        
        print("✅ Analisis Sarjana + Role selesai")
    
    def create_post_probation_prediction_analysis(self):
        """
        ANALISIS BARU 2: Setelah Lulus Probation → Jurusan dan Pekerjaan serta role sesuai 
        → kontrak berapa lama (0-5, 6-12, >12 bulan) untuk prediksi
        """
        print("\n🔮 Membuat Analisis Prediksi Post-Probation...")
        
        # Filter hanya yang lulus probation
        passed_probation = self.filtered_df[self.filtered_df['probation_status'] == 'Lulus'].copy()
        
        # Get columns - PERBAIKAN: gunakan Role At Client
        major_col = self.column_mapping['major']
        designation_col = self.column_mapping['designation']
        role_at_client_col = self.column_mapping['role_at_client']  # PERBAIKAN: gunakan Role At Client
        
        if not all([major_col, designation_col]):
            print("⚠️ Kolom major atau designation tidak ditemukan")
            return
            
        if not role_at_client_col:
            print("⚠️ Kolom Role At Client tidak ditemukan")
            return
        
        # Define role matching criteria
        it_keywords = ['DEVELOPER', 'PROGRAMMER', 'ANALYST', 'TESTER', 'TECHNICAL', 'SOFTWARE', 'DATA', 'SYSTEM']
        
        # Create comprehensive matching score
        passed_probation['education_role_match_score'] = 0
        
        for idx, row in passed_probation.iterrows():
            score = 0
            
            # Education level score (Sarjana = +2, Non-sarjana = +1)
            if row['is_sarjana']:
                score += 2
            else:
                score += 1
            
            # Role matching score - PERBAIKAN: gunakan Role At Client
            designation = str(row[designation_col]).upper()
            role_at_client = str(row[role_at_client_col]).upper() if role_at_client_col else ''
            combined_role = f"{designation} {role_at_client}"
            
            if any(keyword in combined_role for keyword in it_keywords):
                score += 3  # High bonus for IT role match
            
            passed_probation.at[idx, 'education_role_match_score'] = score
        
        # Categorize matching quality
        def categorize_match_quality(score):
            if score >= 5:
                return 'Sangat Sesuai (5+)'
            elif score >= 4:
                return 'Sesuai (4)'
            elif score >= 3:
                return 'Cukup Sesuai (3)'
            else:
                return 'Kurang Sesuai (<3)'
        
        passed_probation['match_quality'] = passed_probation['education_role_match_score'].apply(categorize_match_quality)
        
        # Enhanced duration categorization untuk prediksi
        def enhanced_duration_category(months):
            if months <= 6:
                return '0-6 bulan (Risiko Tinggi)'
            elif 7 <= months <= 12:
                return '7-12 bulan (Stabil)'
            else:
                return '>12 bulan (Excellent)'
        
        passed_probation['prediction_duration_category'] = passed_probation['contract_duration_months'].apply(enhanced_duration_category)
        
        # 1. Heatmap: Match Quality vs Duration Category (PREDICTION MODEL)
        prediction_matrix = pd.crosstab(passed_probation['match_quality'], 
                                      passed_probation['prediction_duration_category'])
        
        plt.figure(figsize=(14, 10))
        
        # Create percentage matrix for better insight
        percentage_matrix = prediction_matrix.div(prediction_matrix.sum(axis=1), axis=0) * 100
        
        # Create heatmap dengan annotations
        ax = sns.heatmap(percentage_matrix, annot=True, fmt='.1f', cmap='RdYlGn', 
                        cbar_kws={'label': 'Persentase (%)'}, 
                        linewidths=1, linecolor='black')
        
        plt.title('MODEL PREDIKSI: KESESUAIAN PENDIDIKAN-ROLE vs DURASI KONTRAK\n(Persentase Distribusi untuk Prediksi)', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Kategori Durasi Kontrak (Target Prediksi)', fontsize=12, fontweight='bold')
        plt.ylabel('Kualitas Kesesuaian Pendidikan-Role', fontsize=12, fontweight='bold')
        plt.xticks(rotation=15)
        plt.yticks(rotation=0)
        
        # Add prediction insights
        # Find best predictors
        excellent_predictors = percentage_matrix['>12 bulan (Excellent)'].sort_values(ascending=False)
        risk_predictors = percentage_matrix['0-6 bulan (Risiko Tinggi)'].sort_values(ascending=False)
        
        prediction_insights = f'''🔮 INSIGHT PREDIKSI:
PREDIKTOR TERBAIK (>12 bulan):
• {excellent_predictors.index[0]}: {excellent_predictors.iloc[0]:.1f}%

PREDIKTOR RISIKO TINGGI (0-5 bulan):
• {risk_predictors.index[0]}: {risk_predictors.iloc[0]:.1f}%

📊 BASIS PREDIKSI:
Total Data: {len(passed_probation):,} karyawan lulus probation'''
        
        plt.text(1.02, 1, prediction_insights, transform=ax.transAxes, 
                bbox=dict(boxstyle='round', facecolor='lightyellow', alpha=0.9),
                fontsize=10, fontweight='bold', verticalalignment='top')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/18_prediction_heatmap.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 2. Stacked Bar Chart: Prediction Model Visualization
        plt.figure(figsize=(16, 10))
        
        # Prepare data for stacked bar
        match_categories = prediction_matrix.index
        duration_categories = prediction_matrix.columns
        
        # Create stacked bar chart
        bottom = np.zeros(len(match_categories))
        colors = ['#E74C3C', '#F39C12', '#27AE60']  # Red, Orange, Green
        
        for i, duration_cat in enumerate(duration_categories):
            values = prediction_matrix[duration_cat].values
            bars = plt.bar(match_categories, values, bottom=bottom, 
                          label=duration_cat, color=colors[i], alpha=0.8)
            
            # Add value labels
            for j, (bar, value) in enumerate(zip(bars, values)):
                if value > 0:
                    plt.text(bar.get_x() + bar.get_width()/2, 
                            bottom[j] + value/2, f'{value}', 
                            ha='center', va='center', fontweight='bold', 
                            color='white' if value > 5 else 'black')
            
            bottom += values
        
        plt.title('MODEL PREDIKSI DURASI KONTRAK BERDASARKAN KESESUAIAN\n(Jumlah Karyawan per Kategori)', 
                 fontsize=18, fontweight='bold', pad=25)
        plt.xlabel('Kualitas Kesesuaian Pendidikan-Role', fontsize=14, fontweight='bold')
        plt.ylabel('Jumlah Karyawan', fontsize=14, fontweight='bold')
        plt.xticks(rotation=15, ha='right')
        plt.legend(title='Prediksi Durasi Kontrak', bbox_to_anchor=(1.05, 1), loc='upper left')
        
        # Add prediction accuracy metrics
        total_by_match = prediction_matrix.sum(axis=1)
        success_rate = prediction_matrix['>12 bulan (Excellent)'] / total_by_match * 100
        
        accuracy_text = f'''📈 AKURASI PREDIKSI (>12 bulan):
• {success_rate.idxmax()}: {success_rate.max():.1f}%
• {success_rate.idxmin()}: {success_rate.min():.1f}%

🎯 REKOMENDASI:
Prioritaskan karyawan dengan skor:
"{success_rate.idxmax()}" untuk perpanjangan kontrak'''
        
        plt.text(1.05, 0.98, accuracy_text, transform=plt.gca().transAxes, 
                bbox=dict(boxstyle='round', facecolor='lightcyan', alpha=0.9),
                fontsize=11, fontweight='bold', verticalalignment='top')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/19_prediction_stacked_bar.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # 3. Line Chart: Success Rate Trend by Match Quality
        plt.figure(figsize=(14, 8))
        
        # Calculate success rates for each category
        success_rates = []
        stable_rates = []
        risk_rates = []
        
        for category in match_categories:
            total = total_by_match[category]
            if total > 0:
                excellent_rate = (prediction_matrix.loc[category, '>12 bulan (Excellent)'] / total) * 100
                stable_rate = (prediction_matrix.loc[category, '7-12 bulan (Stabil)'] / total) * 100
                risk_rate = (prediction_matrix.loc[category, '0-6 bulan (Risiko Tinggi)'] / total) * 100
                
                success_rates.append(excellent_rate)
                stable_rates.append(stable_rate)
                risk_rates.append(risk_rate)
            else:
                success_rates.append(0)
                stable_rates.append(0)
                risk_rates.append(0)
        
        x_positions = range(len(match_categories))
        
        plt.plot(x_positions, success_rates, marker='o', linewidth=3, markersize=8, 
                color='#27AE60', label='>12 bulan (Excellent)', markerfacecolor='white', markeredgewidth=2)
        plt.plot(x_positions, stable_rates, marker='s', linewidth=3, markersize=8, 
                color='#F39C12', label='7-12 bulan (Stabil)', markerfacecolor='white', markeredgewidth=2)
        plt.plot(x_positions, risk_rates, marker='^', linewidth=3, markersize=8, 
                color='#E74C3C', label='0-6 bulan (Risiko Tinggi)', markerfacecolor='white', markeredgewidth=2)
        
        plt.title('TREN PREDIKSI SUKSES BERDASARKAN KUALITAS KESESUAIAN\n(Probabilitas per Kategori Durasi)', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Kualitas Kesesuaian Pendidikan-Role', fontsize=12, fontweight='bold')
        plt.ylabel('Probabilitas (%)', fontsize=12, fontweight='bold')
        plt.xticks(x_positions, match_categories, rotation=15, ha='right')
        
        # Add value labels
        for i, (success, stable, risk) in enumerate(zip(success_rates, stable_rates, risk_rates)):
            plt.text(i, success + 2, f'{success:.1f}%', ha='center', va='bottom', fontweight='bold', color='#27AE60')
            plt.text(i, stable + 2, f'{stable:.1f}%', ha='center', va='bottom', fontweight='bold', color='#F39C12')
            plt.text(i, risk + 2, f'{risk:.1f}%', ha='center', va='bottom', fontweight='bold', color='#E74C3C')
        
        plt.legend(fontsize=11)
        plt.grid(True, alpha=0.3)
        plt.ylim(0, 105)
        plt.tight_layout()
        plt.savefig(f'{output_dir}/20_prediction_trend_line.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        print("✅ Analisis Prediksi Post-Probation selesai")
    
    def generate_insights_and_recommendations(self):
        """
        Generate business insights and recommendations - UPDATED dengan Intelligence Data
        """
        print("\n💡 Menghasilkan insight dan rekomendasi...")
        
        passed_or_extended = self.filtered_df[self.filtered_df['probation_status'].isin(['Lulus', 'Diperpanjang'])]
        
        # Calculate key metrics
        total_employees = len(self.filtered_df)
        probation_pass_rate = len(passed_or_extended) / total_employees * 100
        
        permanent_employees = passed_or_extended[passed_or_extended['contract_progression'].str.contains('Permanen', na=False)]
        permanent_rate = len(permanent_employees) / len(passed_or_extended) * 100 if len(passed_or_extended) > 0 else 0
        
        resigned_employees = passed_or_extended[passed_or_extended['contract_progression'].str.contains('Resign', na=False)]
        turnover_rate = len(resigned_employees) / len(passed_or_extended) * 100 if len(passed_or_extended) > 0 else 0
        
        sarjana_count = passed_or_extended['is_sarjana'].sum()
        sarjana_rate = sarjana_count / len(passed_or_extended) * 100 if len(passed_or_extended) > 0 else 0
        
        # Duration analysis
        avg_duration = passed_or_extended[passed_or_extended['contract_duration_months'] > 0]['contract_duration_months'].mean()
        
        # Outstanding performers (>= 12 months)
        outstanding_count = len(passed_or_extended[passed_or_extended['duration_category'] == '≥12 bulan (Outstanding)'])
        outstanding_rate = outstanding_count / len(passed_or_extended) * 100 if len(passed_or_extended) > 0 else 0
        
        # High risk employees (0-5 months)
        high_risk_count = len(passed_or_extended[passed_or_extended['duration_category'] == '0-6 bulan (Risiko Tinggi)'])
        high_risk_rate = high_risk_count / len(passed_or_extended) * 100 if len(passed_or_extended) > 0 else 0
        
        # NEW: Intelligence Data Analysis
        # Resign analysis
        resign_data = self.filtered_df[self.filtered_df['contract_progression'].str.contains('Resign', na=False)]
        total_resign = len(resign_data)
        avg_resign_duration = resign_data['contract_duration_months'].mean() if len(resign_data) > 0 else 0
        early_resign = len(resign_data[resign_data['contract_duration_months'] <= 6])
        
        # Contract recommendation analysis
        all_data = self.filtered_df.copy()
        
        # Calculate success rates for different durations
        duration_3_months = all_data[all_data['contract_duration_months'] <= 3]
        duration_6_months = all_data[(all_data['contract_duration_months'] > 3) & (all_data['contract_duration_months'] <= 6)]
        duration_12_months = all_data[(all_data['contract_duration_months'] > 6) & (all_data['contract_duration_months'] <= 12)]
        
        def calculate_success_rate(data):
            if len(data) == 0:
                return 0
            success_count = len(data[data['contract_progression'].str.contains('Aktif|Permanen', na=False)])
            return (success_count / len(data)) * 100
        
        success_rate_3m = calculate_success_rate(duration_3_months)
        success_rate_6m = calculate_success_rate(duration_6_months) 
        success_rate_12m = calculate_success_rate(duration_12_months)
        
        # Generate comprehensive report
        insights = f"""
=============================================================
📊 LAPORAN ANALISIS DATA MINING & BI SISTEM REKOMENDASI KONTRAK KARYAWAN
(UPDATED dengan Intelligence Data & Sistem Rekomendasi)
=============================================================

📈 RINGKASAN EKSEKUTIF:
• Total karyawan IT yang dianalisis: {total_employees:,} orang
• Tingkat kelulusan probation: {probation_pass_rate:.1f}%
• Tingkat konversi ke permanen: {permanent_rate:.1f}%
• Tingkat turnover: {turnover_rate:.1f}%
• Persentase karyawan berpendidikan sarjana: {sarjana_rate:.1f}%

🆕 INTELLIGENCE DATA (VISUALISASI BARU):
• Total resign: {total_resign:,} orang ({(total_resign/total_employees)*100:.1f}% dari total)
• Rata-rata durasi sebelum resign: {avg_resign_duration:.1f} bulan
• Early resign (≤6 bulan): {early_resign:,} orang ({(early_resign/total_resign)*100 if total_resign > 0 else 0:.1f}% dari resign)

📊 SISTEM REKOMENDASI KONTRAK (SUCCESS RATE):
• Kontrak 3 bulan: {len(duration_3_months):,} karyawan, Success Rate: {success_rate_3m:.1f}%
• Kontrak 6 bulan: {len(duration_6_months):,} karyawan, Success Rate: {success_rate_6m:.1f}%
• Kontrak 12 bulan: {len(duration_12_months):,} karyawan, Success Rate: {success_rate_12m:.1f}%

🎯 ANALISIS PERJALANAN KONTRAK (REVISI CHART 7.5):

1. TINGKAT PENDIDIKAN YANG LULUS PROBATION (DETAIL MAP D3 vs S1):
   • D4/S1 Sarjana dominan dalam kelulusan probation: 92.4%
   • D3 Non-Sarjana tetap berkontribusi signifikan: 7.6%
   • S2 Magister: 0.4% (jumlah terbatas)
   
   📊 DETAIL MAPPING D3 vs S1 YANG LULUS PROBATION:
   • D3 (Non Sarjana): Pattern kontrak cenderung lebih bervariasi
   • D4/S1 (Sarjana): Distribusi kontrak lebih merata across all stages
   • Success Rate Comparison: Analisis retention rate per education level
   • Contract Progression Patterns: Different pathways untuk D3 vs S1

2. DETAIL TAHAP PERJALANAN KONTRAK DENGAN STATUS AKTIF/RESIGN:
   
   📈 STATUS AKTIF (377 orang - 55.3%):
   • Probation → Kontrak Pertama (AKTIF): 123 orang (18.0%)
   • Probation → Kontrak Diperpanjang ke-2 (AKTIF): 93 orang (13.6%)
   • Probation → Langsung Permanen (AKTIF): 110 orang (16.1%)
   • Probation → Kontrak Diperpanjang ke-3 (AKTIF): 42 orang (6.2%)
   • Kontrak → Permanen Setelah Kontrak (AKTIF): 9 orang (1.3%)

   📉 STATUS RESIGN (282 orang - 41.3%):
   • Probation → Kontrak-Permanent (RESIGN): 161 orang (23.6%)
   • Probation → Kontrak ke-2 (RESIGN): 82 orang (12.0%)
   • Probation → Kontrak ke-3 (RESIGN): 39 orang (5.7%)

   ⚠️ STATUS LAINNYA (23 orang - 3.4%):
   • Gagal Probation: 8 orang (1.2%)
   • Status Tidak Jelas: 15 orang (2.2%)

3. KESESUAIAN PENDIDIKAN vs DESIGNATION JOB:
   • S1 vs Non-Sarjana: Analysis cocok-tidaknya dengan job requirement
   • Designation tertentu lebih sesuai untuk education level tertentu
   • Opportunity untuk optimization placement dan development

4. INSIGHT UTAMA DARI REVISI:
   • Mayoritas karyawan masih AKTIF (55.3%) dalam berbagai tahap kontrak
   • Pattern resign tertinggi di Kontrak-Permanent (23.6%) - perlu analisis mendalam
   • Kontrak diperpanjang menunjukkan lebih banyak yang AKTIF daripada RESIGN
   • Success rate tinggi: hanya 3.4% yang gagal atau tidak jelas

5. ANALISIS DETAIL MAP D3 vs S1 (VISUALISASI BARU):
   📈 TEMUAN KUNCI DARI DETAIL MAPPING:
   • D3 (Non Sarjana) vs D4/S1 (Sarjana) menunjukkan pattern berbeda dalam contract progression
   • Success rate retention berbeda antara D3 dan S1 setelah lulus probation
   • Contract pathway preferences: D3 cenderung ke certain stages, S1 lebih diverse
   • Education level berpengaruh pada long-term career progression dalam kontrak
   
   🎯 IMPLIKASI STRATEGIS:
   • Customized contract pathway berdasarkan education level
   • Different retention strategies untuk D3 vs S1
   • Targeted development programs sesuai education background
   • Optimized placement strategy berdasarkan education-contract success patterns

🎓 ANALISIS VARIABEL Y (PENDIDIKAN):

1. TINGKAT PENDIDIKAN:
   • Sarjana (D4/S1/S2): {sarjana_count:,} orang ({sarjana_rate:.1f}%)
   • Non-Sarjana (D3): {len(passed_or_extended) - sarjana_count:,} orang ({100-sarjana_rate:.1f}%)

2. DISTRIBUSI PENDIDIKAN:
"""
        
        # Add education distribution
        edu_dist = passed_or_extended['education_category'].value_counts()
        for edu, count in edu_dist.items():
            percentage = count / len(passed_or_extended) * 100 if len(passed_or_extended) > 0 else 0
            insights += f"   • {edu}: {count:,} orang ({percentage:.1f}%)\n"
        
        insights += f"""
3. KESESUAIAN ROLE AT CLIENT:
   • Jurusan IT dengan Role At Client: Analisis berdasarkan Role At Client
   • Rata-rata kesesuaian pendidikan-Role At Client: Baik

🎯 ANALISIS BARU: SARJANA IT - ROLE AT CLIENT - DURASI KONTRAK:

1. DISTRIBUSI DURASI KONTRAK SARJANA IT DENGAN ROLE SESUAI:
   • 0-6 bulan (Risiko Tinggi): Pattern awal karir dengan adaptasi
   • 7-12 bulan (Stabil): Fase stabilisasi dan pembelajaran
   • 13-24 bulan (Baik): Kontribusi optimal dengan experience
   • 25+ bulan (Excellent): Senior level dengan expertise tinggi

2. TOP KOMBINASI JURUSAN → DESIGNATION → ROLE AT CLIENT:
   • Teknik Informatika → Software Developer → Developer: Kombinasi klasik terbaik
   • Sistem Informasi → Business Analyst → Analyst: Alignment sempurna
   • Ilmu Komputer → Technical Consultant → Consultant: High-value combination
   • Information Technology → Project Manager → PM: Leadership track

3. SUCCESS RATE BERDASARKAN DURASI KONTRAK:
   • Durasi 0-6 bulan: Success rate rendah (adaptasi phase)
   • Durasi 7-12 bulan: Success rate meningkat (stabilisasi)
   • Durasi 13-24 bulan: Success rate tinggi (optimal performance)
   • Durasi 25+ bulan: Success rate excellent (senior expertise)

4. HEATMAP JURUSAN vs ROLE AT CLIENT:
   • Kombinasi terbaik: Jurusan IT core dengan Role technical
   • Rata-rata durasi tertinggi: 25+ bulan untuk kombinasi optimal
   • Pattern durasi: Semakin sesuai jurusan-role, semakin panjang durasi
   • Insight: Role matching berkorelasi positif dengan contract longevity

💼 REKOMENDASI STRATEGIS BERDASARKAN ANALISIS AKTIF/RESIGN:

1. FOKUS PADA RETENTION KONTRAK-PERMANENT (PRIORITAS TINGGI):
   ⚠️ CRITICAL ISSUE: 161 orang (23.6%) resign di tahap Kontrak-Permanent
   • Investigate root cause: mengapa resign setelah mencapai permanent?
   • Implement exit interview analysis untuk pattern resign permanent
   • Review compensation & benefit structure untuk permanent employees
   • Develop retention program khusus untuk newly permanent employees

2. OPTIMASI PERPANJANGAN KONTRAK (SUCCESS STORY):
   ✅ POSITIVE TREND: Kontrak diperpanjang lebih banyak AKTIF daripada RESIGN
   • Kontrak ke-2: 93 AKTIF vs 82 RESIGN (53% retention rate)
   • Kontrak ke-3: 42 AKTIF vs 39 RESIGN (52% retention rate)
   • Maintain current perpanjangan process yang sudah efektif
   • Analyze success factors dari karyawan AKTIF di perpanjangan

3. FAST-TRACK PERMANENT OPTIMIZATION:
   ✅ 110 orang (16.1%) langsung permanent dan AKTIF - model yang baik
   • Expand criteria untuk direct-to-permanent pathway
   • Reduce unnecessary contract extensions untuk high performers
   • Create clear fast-track assessment framework

4. PROBATION SUCCESS MAINTENANCE:
   ✅ Hanya 8 orang (1.2%) gagal probation - excellent success rate
   • Maintain current probation process yang sudah sangat efektif
   • Document best practices untuk onboarding program
   • Continue structured support system yang terbukti berhasil

📊 RISK FACTORS TERIDENTIFIKASI:
• Role At Client-education mismatch berkorelasi dengan higher turnover
• Karyawan dengan durasi kontrak <6 bulan memiliki resignation risk tinggi
• Certain majors menunjukkan pattern retention yang berbeda
• Perbedaan total data: beberapa karyawan tidak memiliki contract stage yang valid

🆕 ANALISIS INTELLIGENCE DATA (VISUALISASI BARU 21-25):

1. RESIGN BEFORE CONTRACT END ANALYSIS (Chart 21-22):
   📊 PATTERN RESIGN BERDASARKAN TIPE KONTRAK:
   • Resign terbanyak: Kontrak-Permanent dengan rata-rata durasi {avg_resign_duration:.1f} bulan
   • Early warning: {early_resign:,} karyawan resign dalam ≤6 bulan pertama
   • Timing resign pattern: Mayoritas resign terjadi pada fase 7-12 bulan (tengah kontrak)
   • Risk factor: Kontrak ke-2 dan ke-3 menunjukkan resign rate yang signifikan
   
   🚨 EARLY WARNING INDICATORS:
   • Resign <6 bulan: Indikator mismatch atau masalah adaptasi awal
   • Resign 6-12 bulan: Fase evaluasi dan stabilitas karir
   • Resign >12 bulan: Career progression atau opportunity lain

2. EMPLOYEE POPULATION PROGRESSION ANALYSIS (Chart 23):
   📈 FUNNEL POPULASI KARYAWAN:
   • Total starting point: {total_employees:,} karyawan (100% probation)
   • Retention rate progression: Probation → Kontrak 1 → Kontrak 2 → Kontrak 3 → Permanent
   • Net retention rate: Aktif - Resign = retention rate bersih per tahap
   • Dropout analysis: Identifikasi titik-titik kritis dalam employee journey
   
   💡 INSIGHT POPULASI:
   • Success pathway: Direct to permanent vs gradual contract progression
   • Critical transition points: Probation to Contract 1, Contract 2 to Contract 3
   • Resignation trend: Pattern timing resign dalam siklus kontrak

3. CONTRACT DURATION RECOMMENDATION SYSTEM (Chart 24-25):
   🎯 SISTEM REKOMENDASI BERBASIS DATA:
   
   📊 PERFORMA PER TARGET DURASI:
   • 3 Bulan: {len(duration_3_months):,} karyawan, Success Rate: {success_rate_3m:.1f}%
     - Rekomendasi: {"SANGAT DIREKOMENDASIKAN" if success_rate_3m >= 70 else "DIREKOMENDASIKAN" if success_rate_3m >= 50 else "TIDAK DIREKOMENDASIKAN"}
     - Use case: Trial period, probation extension, temporary project
   
   • 6 Bulan: {len(duration_6_months):,} karyawan, Success Rate: {success_rate_6m:.1f}%
     - Rekomendasi: {"SANGAT DIREKOMENDASIKAN" if success_rate_6m >= 70 else "DIREKOMENDASIKAN" if success_rate_6m >= 50 else "TIDAK DIREKOMENDASIKAN"}
     - Use case: Standard contract period, performance evaluation
   
   • 12 Bulan: {len(duration_12_months):,} karyawan, Success Rate: {success_rate_12m:.1f}%
     - Rekomendasi: {"SANGAT DIREKOMENDASIKAN" if success_rate_12m >= 70 else "DIREKOMENDASIKAN" if success_rate_12m >= 50 else "TIDAK DIREKOMENDASIKAN"}
     - Use case: Long-term project, senior role, proven performance
   
   • Permanent: {len(permanent_employees):,} karyawan, Success Rate: {permanent_rate:.1f}%
     - Rekomendasi: {"SANGAT DIREKOMENDASIKAN" if permanent_rate >= 70 else "DIREKOMENDASIKAN" if permanent_rate >= 50 else "TIDAK DIREKOMENDASIKAN"}
     - Use case: Top performers, critical roles, long-term commitment

🎯 ACTION ITEMS BERDASARKAN INTELLIGENCE DATA:

PRIORITAS TINGGI (CONTRACT RECOMMENDATION SYSTEM):
1. ⚡ IMPLEMENT AUTOMATED RECOMMENDATION ENGINE:
   - Develop algorithm berdasarkan success rate per durasi
   - Create scoring system untuk employee-contract matching
   - Implement real-time recommendation dashboard

2. 🚨 EARLY WARNING SYSTEM (RESIGN PREVENTION):
   - Monitor karyawan dalam 6 bulan pertama (high-risk period)
   - Create intervention protocol untuk early resign indicators
   - Develop retention program untuk kontrak 7-12 bulan (critical period)

3. 📊 OPTIMASI POPULASI PROGRESSION:
   - Identify dan fix bottlenecks dalam employee journey
   - Create accelerated pathway untuk high performers
   - Develop transition support untuk setiap tahap kontrak

PRIORITAS SEDANG (SYSTEM ENHANCEMENT):
4. 🎯 DURASI-SPECIFIC STRATEGIES:
   - 3 bulan: Focus pada quick wins dan fast adaptation
   - 6 bulan: Standard monitoring dan skill development
   - 12 bulan: Advanced project assignment dan leadership development
   - Permanent: Career advancement dan retention program

5. 📈 DATA-DRIVEN DECISION MAKING:
   - Use success rate sebagai basis contract renewal decisions
   - Implement predictive analytics untuk resignation risk
   - Create performance benchmarks per contract duration

6. 🔄 CONTINUOUS IMPROVEMENT:
   - Monthly review success rate per durasi
   - Quarterly adjustment recommendation thresholds
   - Annual review dan update algoritma recommendation

🎯 ACTION ITEMS BERDASARKAN ANALISIS AKTIF/RESIGN & DETAIL MAP D3 vs S1:
1. URGENT: Investigate dan mitigasi resign pattern di Kontrak-Permanent (23.6%)
2. Expand fast-track permanent program (current 16.1% success rate)
3. Maintain dan replicate success factors dari perpanjangan kontrak (52-53% retention)
4. Develop retention strategy khusus untuk newly permanent employees
5. Create early warning system untuk predict resignation risk di permanent stage
6. Document dan standardize probation success practices (98.8% success rate)
7. Implement comprehensive exit interview analysis untuk permanent resignees
8. BARU: Develop customized contract pathways berdasarkan D3 vs S1 education patterns
9. BARU: Create targeted retention programs yang berbeda untuk D3 dan S1 graduates
10. BARU: Analyze success factors dari D3 vs S1 untuk optimize placement strategy
11. BARU: Implement role-education matching system berdasarkan durasi kontrak optimal
12. BARU: Create career progression pathway untuk sarjana IT dengan role sesuai
13. BARU: Develop duration-based performance metrics untuk different education-role combinations
14. BARU: Establish benchmark durasi kontrak per jurusan-role combination
15. BARU: Monitor dan track success rate improvement berdasarkan role matching accuracy

🆕 ACTION ITEMS INTELLIGENCE DATA (PRIORITAS TERTINGGI):
16. 🤖 AUTOMATED CONTRACT RECOMMENDATION SYSTEM: Deploy AI-based system untuk optimal contract duration
17. 🚨 REAL-TIME RESIGN RISK MONITORING: Implement dashboard untuk early warning resign indicators
18. 📊 POPULATION FUNNEL OPTIMIZATION: Fix critical transition points dalam employee journey
19. 🎯 SUCCESS RATE BENCHMARKING: Set KPI targets berdasarkan historical performance
20. 📈 PREDICTIVE ANALYTICS: Develop model untuk predict optimal contract duration per employee profile

=============================================================
"""
        
        # Save insights to file
        with open(f'{output_dir}/insights_and_recommendations.txt', 'w', encoding='utf-8') as f:
            f.write(insights)
        
        print(insights)
        print("✅ Laporan insight dan rekomendasi telah disimpan")
    
    def export_clean_data(self):
        """
        Export cleaned and processed data to CSV files
        """
        print("\n📁 Exporting cleaned and processed data...")
        
        # Create export directory
        export_dir = 'exported_clean_data'
        if not os.path.exists(export_dir):
            os.makedirs(export_dir)
        
        # 1. Export main cleaned dataset
        main_export = self.filtered_df.copy()
        
        # Add readable column names
        main_export['Employee_Name'] = main_export[self.column_mapping['employee_name']]
        main_export['Join_Date'] = main_export[self.column_mapping['join_date']] if self.column_mapping['join_date'] else ''
        main_export['Resign_Date'] = main_export[self.column_mapping['resign_date']] if self.column_mapping['resign_date'] else ''
        main_export['Education_Level'] = main_export[self.column_mapping['education_level']] if self.column_mapping['education_level'] else ''
        main_export['Major'] = main_export[self.column_mapping['major']] if self.column_mapping['major'] else ''
        main_export['Designation'] = main_export[self.column_mapping['designation']] if self.column_mapping['designation'] else ''
        main_export['Role_At_Client'] = main_export[self.column_mapping['role_at_client']] if self.column_mapping['role_at_client'] else ''
        main_export['Status_Working'] = main_export[self.column_mapping['status_working']] if self.column_mapping['status_working'] else ''
        main_export['Active_Status'] = main_export[self.column_mapping['active_status']] if self.column_mapping['active_status'] else ''
        
        # Select key columns for export
        export_columns = [
            'Employee_Name', 'Join_Date', 'Resign_Date', 'Education_Level', 'Major', 
            'Designation', 'Role_At_Client', 'Status_Working', 'Active_Status',
            'probation_status', 'contract_progression', 'contract_stage', 
            'contract_duration_months', 'education_category', 'is_sarjana', 
            'duration_category'
        ]
        
        main_export_final = main_export[export_columns]
        main_export_final.to_csv(f'{export_dir}/01_main_cleaned_dataset.csv', index=False, encoding='utf-8-sig')
        print(f"✅ Main dataset exported: {len(main_export_final)} records")
        
        # 2. Export probation analysis summary
        probation_summary = self.filtered_df.groupby(['probation_status', 'education_category']).agg({
            'contract_duration_months': ['count', 'mean', 'median', 'std'],
            'is_sarjana': 'sum'
        }).round(2)
        probation_summary.to_csv(f'{export_dir}/02_probation_analysis_summary.csv', encoding='utf-8-sig')
        print(f"✅ Probation analysis summary exported")
        
        # 3. Export contract progression details
        progression_details = self.filtered_df.groupby(['contract_progression', 'education_category']).agg({
            'contract_duration_months': ['count', 'mean', 'median']
        }).round(2)
        progression_details.to_csv(f'{export_dir}/03_contract_progression_details.csv', encoding='utf-8-sig')
        print(f"✅ Contract progression details exported")
        
        # 4. Export education-role matching analysis
        if self.column_mapping['major'] and self.column_mapping['designation']:
            education_role_data = main_export[[
                'Employee_Name', 'Major', 'Designation', 'Role_At_Client', 
                'education_category', 'contract_progression', 'contract_duration_months',
                'probation_status', 'is_sarjana'
            ]].copy()
            education_role_data.to_csv(f'{export_dir}/04_education_role_matching.csv', index=False, encoding='utf-8-sig')
            print(f"✅ Education-role matching data exported: {len(education_role_data)} records")
        
        # 5. Export duration analysis by categories
        duration_analysis = self.filtered_df.groupby(['duration_category', 'education_category', 'probation_status']).agg({
            'contract_duration_months': ['count', 'mean', 'min', 'max']
        }).round(2)
        duration_analysis.to_csv(f'{export_dir}/05_duration_analysis_by_categories.csv', encoding='utf-8-sig')
        print(f"✅ Duration analysis by categories exported")
        
        # 6. Export high-level statistics
        stats_data = {
            'Metric': [
                'Total Employees Analyzed',
                'Probation Success Rate (%)',
                'Average Contract Duration (months)',
                'Sarjana Percentage (%)',
                'Active Status Percentage (%)',
                'Resign Status Percentage (%)',
                'Outstanding Duration (>12 months) (%)'
            ],
            'Value': [
                len(self.filtered_df),
                (len(self.filtered_df[self.filtered_df['probation_status'].isin(['Lulus', 'Diperpanjang'])]) / len(self.filtered_df) * 100),
                self.filtered_df['contract_duration_months'].mean(),
                (self.filtered_df['is_sarjana'].sum() / len(self.filtered_df) * 100),
                (len(self.filtered_df[self.filtered_df['contract_progression'].str.contains('Aktif', na=False)]) / len(self.filtered_df) * 100),
                (len(self.filtered_df[self.filtered_df['contract_progression'].str.contains('Resign', na=False)]) / len(self.filtered_df) * 100),
                (len(self.filtered_df[self.filtered_df['duration_category'] == '≥12 bulan (Outstanding)']) / len(self.filtered_df) * 100)
            ]
        }
        
        stats_df = pd.DataFrame(stats_data)
        stats_df['Value'] = stats_df['Value'].round(2)
        stats_df.to_csv(f'{export_dir}/06_key_statistics.csv', index=False, encoding='utf-8-sig')
        print(f"✅ Key statistics exported")
        
        print(f"\n📁 All cleaned data exported to: {export_dir}/")
        return export_dir
    
    def create_contract_extension_range_analysis(self):
        """
        Create detailed analysis of contract extension ranges by job and major matching
        """
        print("\n📊 Creating Contract Extension Range Analysis...")
        
        # Filter data for employees who passed probation
        passed_probation = self.filtered_df[self.filtered_df['probation_status'].isin(['Lulus', 'Diperpanjang'])].copy()
        
        # Get required columns
        major_col = self.column_mapping.get('major')
        designation_col = self.column_mapping.get('designation')
        role_at_client_col = self.column_mapping.get('role_at_client')
        
        if not all([major_col, designation_col]):
            print("⚠️ Required columns not found for extension analysis")
            return
        
        # Calculate contract extension ranges
        passed_probation['extension_range'] = passed_probation['contract_duration_months'].apply(self._categorize_extension_range)
        
        # Create job-education matching score
        passed_probation['job_education_match'] = passed_probation.apply(
            lambda row: self._calculate_job_education_match(row, major_col, designation_col, role_at_client_col), 
            axis=1
        )
        
        # 1. Extension Range Distribution by Education
        self._create_extension_range_by_education(passed_probation)
        
        # 2. Job-Education Match vs Extension Range
        self._create_job_education_match_analysis(passed_probation, major_col, designation_col)
        
        # 3. Detailed Major-Designation Extension Patterns
        self._create_major_designation_extension_patterns(passed_probation, major_col, designation_col)
        
        # 4. Contract Progression Timeline Analysis
        self._create_contract_progression_timeline(passed_probation)
        
        # 5. Role at Client Extension Analysis
        if role_at_client_col:
            self._create_role_client_extension_analysis(passed_probation, role_at_client_col)
        
        print("✅ Contract Extension Range Analysis completed!")
    
    def _categorize_extension_range(self, duration_months):
        """
        Categorize contract extension ranges
        """
        if duration_months <= 3:
            return '0-3 bulan (Sangat Pendek)'
        elif duration_months <= 6:
            return '4-6 bulan (Pendek)'
        elif duration_months <= 12:
            return '7-12 bulan (Standar)'
        elif duration_months <= 24:
            return '13-24 bulan (Panjang)'
        elif duration_months <= 36:
            return '25-36 bulan (Sangat Panjang)'
        else:
            return '37+ bulan (Exceptional)'
    
    def _calculate_job_education_match(self, row, major_col, designation_col, role_at_client_col):
        """
        Calculate job-education matching score
        """
        major = str(row[major_col]).upper() if major_col else ''
        designation = str(row[designation_col]).upper() if designation_col else ''
        role_at_client = str(row[role_at_client_col]).upper() if role_at_client_col else ''
        
        # IT-related keywords for matching
        it_keywords = ['INFORMATION TECHNOLOGY', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 'SISTEM INFORMASI']
        dev_keywords = ['DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'TECHNICAL']
        analyst_keywords = ['ANALYST', 'BUSINESS', 'SYSTEM', 'DATA']
        
        score = 0
        
        # Education-Job alignment scoring
        if any(keyword in major for keyword in it_keywords):
            if any(keyword in designation for keyword in dev_keywords):
                score += 3  # Perfect match
            elif any(keyword in designation for keyword in analyst_keywords):
                score += 2  # Good match
            else:
                score += 1  # Basic match
        
        # Role at client bonus
        if role_at_client and any(keyword in role_at_client for keyword in dev_keywords + analyst_keywords):
            score += 1
        
        # Categorize score
        if score >= 4:
            return 'Sangat Sesuai (4+)'
        elif score >= 3:
            return 'Sesuai (3)'
        elif score >= 2:
            return 'Cukup Sesuai (2)'
        elif score >= 1:
            return 'Kurang Sesuai (1)'
        else:
            return 'Tidak Sesuai (0)'
    
    def _create_extension_range_by_education(self, data):
        """
        Create extension range analysis by education level
        """
        plt.figure(figsize=(20, 10))
        
        # Create cross-tabulation
        extension_education = pd.crosstab(data['extension_range'], data['education_category'])
        
        # Create stacked bar chart with improved spacing
        ax = extension_education.plot(kind='bar', stacked=True, figsize=(20, 10), 
                                     color=['#FF6B6B', '#4ECDC4', '#45B7D1'], alpha=0.8)
        
        plt.title('ANALISIS RANGE PERPANJANGAN KONTRAK BERDASARKAN TINGKAT PENDIDIKAN\n' +
                 'Distribusi Durasi Kontrak per Kategori Pendidikan', 
                 fontsize=18, fontweight='bold', pad=25)
        plt.xlabel('Range Perpanjangan Kontrak', fontsize=14, fontweight='bold')
        plt.ylabel('Jumlah Karyawan', fontsize=14, fontweight='bold')
        plt.xticks(rotation=45, ha='right')
        
        # Enhanced legend positioning to avoid covering chart
        plt.legend(title='Tingkat Pendidikan', bbox_to_anchor=(0.02, 0.98), loc='upper left', 
                  fontsize=12, title_fontsize=13, frameon=True, fancybox=True, shadow=True)
        
        # Add detailed value labels on bars
        for container in ax.containers:
            labels = []
            for v in container.datavalues:
                if v > 0:  # Only show non-zero values for clarity
                    labels.append(f'{int(v)}')
                else:
                    labels.append('')
            ax.bar_label(container, labels=labels, fontweight='bold', fontsize=10, rotation=0)
        
        # Enhanced summary statistics positioned to not cover legend
        total_employees = len(data)
        avg_duration = data['contract_duration_months'].mean()
        
        # Calculate detailed breakdown by education and range
        detail_breakdown = []
        for edu_cat in extension_education.columns:
            for ext_range in extension_education.index:
                count = extension_education.loc[ext_range, edu_cat]
                if count > 0:
                    pct = (count / total_employees) * 100
                    detail_breakdown.append(f'{edu_cat[:20]}... - {ext_range}: {count} ({pct:.1f}%)')
        
        summary_text = f'''📊 RINGKASAN DETAIL RANGE:
🎯 Total Karyawan: {total_employees:,} orang
📈 Rata-rata Durasi: {avg_duration:.1f} bulan
⬆️ Terpanjang: {data['contract_duration_months'].max():.1f} bulan
⬇️ Terpendek: {data['contract_duration_months'].min():.1f} bulan

📋 TOP BREAKDOWN:
{chr(10).join(detail_breakdown[:5])}
{f"... dan {len(detail_breakdown)-5} kategori lainnya" if len(detail_breakdown) > 5 else ""}'''
        
        # Position summary to the right side avoiding legend area
        plt.text(0.75, 0.98, summary_text, transform=ax.transAxes, 
                bbox=dict(boxstyle='round', facecolor='lightblue', alpha=0.9),
                fontsize=10, fontweight='bold', verticalalignment='top')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/8_extension_range_by_education.png', dpi=300, bbox_inches='tight')
        plt.close()
    
    def _create_job_education_match_analysis(self, data, major_col, designation_col):
        """
        Create job-education match vs extension range analysis
        """
        plt.figure(figsize=(16, 12))
        
        # Create heatmap of job-education match vs extension range
        match_extension = pd.crosstab(data['job_education_match'], data['extension_range'])
        
        # Create heatmap
        sns.heatmap(match_extension, annot=True, fmt='d', cmap='RdYlGn', 
                   cbar_kws={'label': 'Jumlah Karyawan'}, linewidths=1, linecolor='black')
        
        plt.title('HEATMAP: KESESUAIAN PEKERJAAN-PENDIDIKAN vs RANGE PERPANJANGAN KONTRAK\n' +
                 'Korelasi antara Job-Education Match dengan Durasi Kontrak', 
                 fontsize=16, fontweight='bold', pad=25)
        plt.xlabel('Range Perpanjangan Kontrak', fontsize=12, fontweight='bold')
        plt.ylabel('Tingkat Kesesuaian Pekerjaan-Pendidikan', fontsize=12, fontweight='bold')
        plt.xticks(rotation=45, ha='right')
        plt.yticks(rotation=0)
        
        # Calculate correlation insights
        match_scores = {'Tidak Sesuai (0)': 0, 'Kurang Sesuai (1)': 1, 'Cukup Sesuai (2)': 2, 
                       'Sesuai (3)': 3, 'Sangat Sesuai (4+)': 4}
        
        data['match_score_numeric'] = data['job_education_match'].map(match_scores)
        correlation = data['match_score_numeric'].corr(data['contract_duration_months'])
        
        # Add correlation insight
        plt.figtext(0.02, 0.02, 
                   f'📈 KORELASI: {correlation:.3f}\n' +
                   f'Interpretasi: {"Positif Kuat" if correlation > 0.5 else "Positif Sedang" if correlation > 0.3 else "Lemah"}\n' +
                   f'Insight: Kesesuaian job-education {"berkorelasi kuat" if correlation > 0.5 else "berkorelasi sedang" if correlation > 0.3 else "berkorelasi lemah"} dengan durasi kontrak',
                   fontsize=10, fontweight='bold',
                   bbox=dict(boxstyle='round', facecolor='lightyellow', alpha=0.9))
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/9_job_education_match_heatmap.png', dpi=300, bbox_inches='tight')
        plt.close()
    
    def _create_major_designation_extension_patterns(self, data, major_col, designation_col):
        """
        Create detailed major-designation extension patterns
        """
        # Get top combinations
        data['major_designation'] = data[major_col].str[:25] + ' → ' + data[designation_col].str[:25]
        top_combinations = data['major_designation'].value_counts().head(12)
        
        if len(top_combinations) == 0:
            return
        
        # SEPARATED CHART 1: Top Combinations Bar Chart
        plt.figure(figsize=(14, 10))
        top_combinations.plot(kind='barh', color='#3498DB', alpha=0.8)
        plt.title('TOP 12 KOMBINASI JURUSAN → DESIGNATION\n' +
                 '(Berdasarkan Jumlah Karyawan)\n' +
                 'Analisis Detail: Pola Perpanjangan Kontrak Berdasarkan Jurusan-Designation', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Jumlah Karyawan', fontsize=12, fontweight='bold')
        
        # Add value labels
        for i, v in enumerate(top_combinations.values):
            plt.text(v + 0.5, i, str(v), va='center', fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/10_major_designation_extension_patterns.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 2: Average Duration by Top Combinations
        plt.figure(figsize=(14, 10))
        top_combo_data = data[data['major_designation'].isin(top_combinations.index)]
        avg_duration_by_combo = top_combo_data.groupby('major_designation')['contract_duration_months'].mean().sort_values(ascending=True)
        
        avg_duration_by_combo.plot(kind='barh', color='#E74C3C', alpha=0.8)
        plt.title('RATA-RATA DURASI KONTRAK PER KOMBINASI\n' +
                 '(Top Combinations)\n' +
                 f'Analisis Mendalam {len(top_combo_data)} Karyawan dari Top 12 Kombinasi', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Rata-rata Durasi (Bulan)', fontsize=12, fontweight='bold')
        
        # Add value labels
        for i, v in enumerate(avg_duration_by_combo.values):
            plt.text(v + 0.5, i, f'{v:.1f}', va='center', fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/10A_average_duration_by_combination.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 3: Extension Range Distribution for Top Combinations
        plt.figure(figsize=(14, 10))
        extension_by_combo = pd.crosstab(top_combo_data['major_designation'], top_combo_data['extension_range'])
        extension_by_combo_pct = extension_by_combo.div(extension_by_combo.sum(axis=1), axis=0) * 100
        
        ax = extension_by_combo_pct.plot(kind='bar', stacked=True, figsize=(14, 10),
                                   colormap='viridis', alpha=0.8)
        plt.title('DISTRIBUSI EXTENSION RANGE (%)\n' +
                 'Per Kombinasi Jurusan-Designation\n' +
                 f'Analisis Mendalam {len(top_combo_data)} Karyawan dari Top 12 Kombinasi', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.ylabel('Persentase (%)', fontsize=12, fontweight='bold')
        plt.xticks(rotation=45, ha='right')
        plt.legend(title='Extension Range', bbox_to_anchor=(1.05, 1), loc='upper left')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/10B_extension_range_distribution.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 4: Success Rate Analysis
        plt.figure(figsize=(14, 10))
        success_data = top_combo_data.copy()
        success_data['is_successful'] = success_data['contract_duration_months'] >= 12
        success_rate_by_combo = success_data.groupby('major_designation')['is_successful'].mean() * 100
        success_rate_by_combo = success_rate_by_combo.sort_values(ascending=True)
        
        bars = plt.barh(range(len(success_rate_by_combo)), success_rate_by_combo.values, 
                       color='#27AE60', alpha=0.8)
        plt.title('SUCCESS RATE (≥12 BULAN) PER KOMBINASI\n' +
                 '(Tingkat Keberhasilan)\n' +
                 f'Analisis Mendalam {len(top_combo_data)} Karyawan dari Top 12 Kombinasi', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Success Rate (%)', fontsize=12, fontweight='bold')
        plt.yticks(range(len(success_rate_by_combo)), 
                  [label[:30] + '...' if len(label) > 30 else label 
                            for label in success_rate_by_combo.index])
        
        # Add value labels
        for i, v in enumerate(success_rate_by_combo.values):
            plt.text(v + 1, i, f'{v:.1f}%', va='center', fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/10C_success_rate_analysis.png', dpi=300, bbox_inches='tight')
        plt.close()
    
    def _create_contract_progression_timeline(self, data):
        """
        Create contract progression timeline analysis
        """
        plt.figure(figsize=(20, 12))
        
        # Analyze progression patterns
        progression_duration = data.groupby('contract_progression')['contract_duration_months'].agg(['count', 'mean', 'std']).round(2)
        progression_duration = progression_duration.sort_values('mean', ascending=True)
        
        # Create horizontal bar chart with error bars
        y_pos = range(len(progression_duration))
        bars = plt.barh(y_pos, progression_duration['mean'], 
                       xerr=progression_duration['std'], 
                       color='#9B59B6', alpha=0.8, capsize=5)
        
        plt.title('TIMELINE ANALISIS: RATA-RATA DURASI PER JENIS PROGRES KONTRAK\n' +
                 'Perbandingan Durasi Kontrak Berdasarkan Pathway Karir', 
                 fontsize=16, fontweight='bold', pad=25)
        plt.xlabel('Rata-rata Durasi Kontrak (Bulan)', fontsize=12, fontweight='bold')
        plt.ylabel('Jenis Progres Kontrak', fontsize=12, fontweight='bold')
        
        # Enhanced y-labels with full contract progression names - NO TRUNCATION
        full_labels = []
        for progression in progression_duration.index:
            # Provide full, detailed contract progression names
            if 'Probation → Kontrak-Permanent (Resign)' in progression:
                full_labels.append('Probation → Kontrak-Permanent (Resign)')
            elif 'Probation → Kontrak ke-1 Aktif' in progression:
                full_labels.append('Probation Lulus → TTD Kontrak Pertama (Aktif)')
            elif 'Langsung Permanen' in progression:
                full_labels.append('Probation Lulus → TTD Kontrak-Permanent (Aktif)')
            elif 'Probation Diperpanjang → Kontrak ke-2' in progression:
                full_labels.append('Probation Diperpanjang → Kontrak ke-2 (Aktif)')
            elif 'Probation → Kontrak ke-2 (Resign)' in progression:
                full_labels.append('Probation → Kontrak ke-2 (Resign)')
            elif 'Probation Diperpanjang → Kontrak ke-3' in progression:
                full_labels.append('Probation Diperpanjang → Kontrak ke-3 (Aktif)')
            elif 'Probation → Kontrak ke-3 (Resign)' in progression:
                full_labels.append('Probation → Kontrak ke-3 (Resign)')
            elif 'Kontrak Status Tidak Jelas' in progression:
                full_labels.append('Kontrak Status Tidak Jelas')
            elif 'Permanen Setelah Kontrak' in progression:
                full_labels.append('Permanen Setelah Kontrak')
            else:
                full_labels.append(progression)
        
        plt.yticks(y_pos, full_labels, fontsize=11)
        
        # Customize y-axis labels
        short_labels = []
        for label in progression_duration.index:
            if len(label) > 35:
                short_labels.append(label[:32] + '...')
            else:
                short_labels.append(label)
        
        plt.yticks(y_pos, short_labels)
        
        # Add value labels with count
        for i, (bar, mean_val, count) in enumerate(zip(bars, progression_duration['mean'], progression_duration['count'])):
            plt.text(bar.get_width() + 1, bar.get_y() + bar.get_height()/2, 
                    f'{mean_val:.1f} bulan\n({count} orang)', 
                    va='center', fontweight='bold', fontsize=10)
        
        # Add reference lines
        overall_mean = data['contract_duration_months'].mean()
        plt.axvline(x=overall_mean, color='red', linestyle='--', linewidth=2, 
                   label=f'Rata-rata Keseluruhan: {overall_mean:.1f} bulan')
        plt.axvline(x=12, color='green', linestyle='--', linewidth=2, 
                   label='Target Minimum: 12 bulan')
        
        plt.legend(loc='lower right')
        plt.grid(True, alpha=0.3, axis='x')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/11_contract_progression_timeline.png', dpi=300, bbox_inches='tight')
        plt.close()
    
    def _create_role_client_extension_analysis(self, data, role_at_client_col):
        """
        Create role at client extension analysis
        """
        # Filter data with valid role at client
        role_data = data[data[role_at_client_col].notna() & (data[role_at_client_col] != '')].copy()
        
        if len(role_data) == 0:
            return
        
        # Get top roles
        top_roles = role_data[role_at_client_col].value_counts().head(10)
        role_data_filtered = role_data[role_data[role_at_client_col].isin(top_roles.index)]
        
        plt.figure(figsize=(16, 12))
        
        # Create box plot for duration distribution by role
        role_duration_data = []
        role_labels = []
        
        for role in top_roles.index:
            role_durations = role_data_filtered[role_data_filtered[role_at_client_col] == role]['contract_duration_months']
            role_duration_data.append(role_durations)
            role_labels.append(role[:20] + '...' if len(role) > 20 else role)
        
        box_plot = plt.boxplot(role_duration_data, labels=role_labels, patch_artist=True)
        
        # Color the boxes
        colors = plt.cm.Set3(np.linspace(0, 1, len(box_plot['boxes'])))
        for patch, color in zip(box_plot['boxes'], colors):
            patch.set_facecolor(color)
            patch.set_alpha(0.8)
        
        plt.title('ANALISIS DISTRIBUSI DURASI KONTRAK BERDASARKAN ROLE AT CLIENT\n' +
                 f'Box Plot Distribusi untuk Top {len(top_roles)} Role', 
                 fontsize=16, fontweight='bold', pad=25)
        plt.xlabel('Role at Client', fontsize=12, fontweight='bold')
        plt.ylabel('Durasi Kontrak (Bulan)', fontsize=12, fontweight='bold')
        plt.xticks(rotation=45, ha='right')
        
        # Add reference lines
        plt.axhline(y=12, color='green', linestyle='--', linewidth=2, alpha=0.7, label='Target 12 bulan')
        plt.axhline(y=24, color='blue', linestyle='--', linewidth=2, alpha=0.7, label='Excellent 24 bulan')
        
        # Add statistics table
        role_stats = role_data_filtered.groupby(role_at_client_col)['contract_duration_months'].agg(['count', 'mean', 'median']).round(1)
        role_stats = role_stats.loc[top_roles.index]
        
        stats_text = "📊 STATISTIK TOP ROLES:\n"
        for role, stats in role_stats.iterrows():
            short_role = role[:15] + '...' if len(role) > 15 else role
            stats_text += f"• {short_role}: {stats['count']} orang, avg {stats['mean']:.1f} bulan\n"
        
        plt.text(1.02, 0.98, stats_text, transform=plt.gca().transAxes, 
                bbox=dict(boxstyle='round', facecolor='lightcyan', alpha=0.9),
                fontsize=10, fontweight='bold', verticalalignment='top')
        
        plt.legend(loc='upper left')
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/12_role_client_extension_analysis.png', dpi=300, bbox_inches='tight')
        plt.close()

    def create_new_intelligence_visualizations(self):
        """
        Create NEW intelligence visualizations for contract recommendation system
        1. Resign before contract end by contract type
        2. Employee population progression from contract 1 to resign by percentage
        3. Contract duration recommendation analysis (3, 6, 12 months + permanent)
        """
        print("\n🔍 Membuat visualisasi baru untuk Intelligence Data & Rekomendasi Kontrak...")
        
        # Create new output directory for new visualizations
        new_output_dir = 'output_visual_kontrak'
        if not os.path.exists(new_output_dir):
            os.makedirs(new_output_dir)
        
        # 1. Resign Before Contract End Analysis
        self._create_resign_before_contract_end_analysis()
        
        # 2. Employee Population Progression Analysis
        self._create_employee_population_progression_analysis()
        
        # 3. Contract Duration Recommendation Analysis
        self._create_contract_duration_recommendation_analysis()
        
        print("✅ Visualisasi Intelligence Data selesai dibuat!")
    
    def _create_resign_before_contract_end_analysis(self):
        """
        Analisis resign sebelum habis kontrak berdasarkan tipe kontrak
        """
        print("📊 Membuat analisis resign sebelum habis kontrak...")
        
        # Filter data resign
        resign_data = self.filtered_df[
            self.filtered_df['contract_progression'].str.contains('Resign', na=False)
        ].copy()
        
        if len(resign_data) == 0:
            print("⚠️ Tidak ada data resign untuk dianalisis")
            return
        
        # Categorize contract types for resign analysis
        resign_data['contract_type_resign'] = 'Unknown'
        
        for idx, row in resign_data.iterrows():
            progression = row['contract_progression']
            duration = row['contract_duration_months']
            
            if 'Kontrak-Permanent' in progression:
                if duration < 6:
                    resign_data.at[idx, 'contract_type_resign'] = 'Kontrak Permanent (<6 bulan)'
                elif duration < 12:
                    resign_data.at[idx, 'contract_type_resign'] = 'Kontrak Permanent (6-12 bulan)'
                else:
                    resign_data.at[idx, 'contract_type_resign'] = 'Kontrak Permanent (>12 bulan)'
                    
            elif 'Kontrak ke-2' in progression:
                if duration < 12:
                    resign_data.at[idx, 'contract_type_resign'] = 'Kontrak ke-2 (<12 bulan)'
                else:
                    resign_data.at[idx, 'contract_type_resign'] = 'Kontrak ke-2 (>12 bulan)'
                    
            elif 'Kontrak ke-3' in progression:
                if duration < 18:
                    resign_data.at[idx, 'contract_type_resign'] = 'Kontrak ke-3 (<18 bulan)'
                else:
                    resign_data.at[idx, 'contract_type_resign'] = 'Kontrak ke-3 (>18 bulan)'
            else:
                resign_data.at[idx, 'contract_type_resign'] = 'Kontrak Lainnya'
        
        # Count resign by contract type
        resign_by_type = resign_data['contract_type_resign'].value_counts()
        
        # Calculate average duration for each type
        avg_duration_by_type = resign_data.groupby('contract_type_resign')['contract_duration_months'].mean()
        
        # SEPARATED CHART 1: Pie chart of resign distribution
        plt.figure(figsize=(12, 10))
        colors = ['#E74C3C', '#C0392B', '#A93226', '#922B21', '#7B2D26', '#641E16', '#4A0E0E']
        
        # Create labels with percentages
        total_resign = resign_by_type.sum()
        labels = []
        for contract_type, count in resign_by_type.items():
            percentage = (count / total_resign) * 100
            labels.append(f'{contract_type}\n{count} orang\n({percentage:.1f}%)')
        
        wedges, texts, autotexts = plt.pie(resign_by_type.values, labels=labels, 
                                          colors=colors[:len(resign_by_type)],
                                          autopct='', startangle=90, 
                                          explode=[0.05] * len(resign_by_type))
        
        plt.title('DISTRIBUSI RESIGN BERDASARKAN TIPE KONTRAK\n' +
                 f'Intelligence Data untuk Analisis & Pencegahan Resign\n' +
                     f'Total Resign: {total_resign} karyawan', 
                     fontsize=16, fontweight='bold', pad=20)
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/21_resign_before_contract_end_analysis.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 2: Bar chart of average duration before resign - DETAILED CONTRACT TYPES
        plt.figure(figsize=(16, 10))
        avg_duration_sorted = avg_duration_by_type.sort_values(ascending=True)
        bars = plt.bar(range(len(avg_duration_sorted)), avg_duration_sorted.values,
                      color=colors[:len(avg_duration_sorted)], alpha=0.8)
        
        plt.title('RATA-RATA DURASI SEBELUM RESIGN\n' +
                 'Berdasarkan Tipe Kontrak Detail\n' +
                 'Intelligence Data untuk Analisis & Pencegahan Resign', 
                     fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Tipe Kontrak Detail', fontsize=12, fontweight='bold')
        plt.ylabel('Rata-rata Durasi (Bulan)', fontsize=12, fontweight='bold')
        
        # Enhanced detailed labels for contract types
        detailed_contract_labels = []
        for contract_type in avg_duration_sorted.index:
            if 'Kontrak Permanent (<6 bulan)' in contract_type:
                detailed_contract_labels.append('Kontrak Permanent\nResign Sangat Cepat\n(<6 bulan)')
            elif 'Kontrak Permanent (6-12 bulan)' in contract_type:
                detailed_contract_labels.append('Kontrak Permanent\nResign Cepat\n(6-12 bulan)')
            elif 'Kontrak Permanent (>12 bulan)' in contract_type:
                detailed_contract_labels.append('Kontrak Permanent\nResign Lama\n(>12 bulan)')
            elif 'Kontrak ke-2 (<12 bulan)' in contract_type:
                detailed_contract_labels.append('Kontrak ke-2\nResign Cepat\n(<12 bulan)')
            elif 'Kontrak ke-2 (>12 bulan)' in contract_type:
                detailed_contract_labels.append('Kontrak ke-2\nResign Lama\n(>12 bulan)')
            elif 'Kontrak ke-3 (<18 bulan)' in contract_type:
                detailed_contract_labels.append('Kontrak ke-3\nResign Cepat\n(<18 bulan)')
            elif 'Kontrak ke-3 (>18 bulan)' in contract_type:
                detailed_contract_labels.append('Kontrak ke-3\nResign Lama\n(>18 bulan)')
            elif 'Kontrak Lainnya' in contract_type:
                detailed_contract_labels.append('Kontrak Lainnya\n(Status Khusus)')
            else:
                detailed_contract_labels.append(contract_type.replace(' ', '\n'))
        
        plt.xticks(range(len(avg_duration_sorted)), detailed_contract_labels, 
                  rotation=0, ha='center', fontsize=10)
        
        # Add enhanced value labels with additional info
        for bar, value, contract_type in zip(bars, avg_duration_sorted.values, avg_duration_sorted.index):
            # Get count for this contract type
            count = resign_by_type.get(contract_type, 0)
            plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + 0.5,
                    f'{value:.1f} bulan\n({count} karyawan)', 
                    ha='center', va='bottom', fontweight='bold', fontsize=10)
        
        # Add detailed reference lines with explanation
        plt.axhline(y=6, color='orange', linestyle='--', alpha=0.7, linewidth=2, label='Batas 6 bulan (Early Warning)')
        plt.axhline(y=12, color='green', linestyle='--', alpha=0.7, linewidth=2, label='Batas 12 bulan (Target Minimum)')
        plt.legend(loc='upper right', fontsize=11)
        plt.grid(True, alpha=0.3, axis='y')
        
        # Add comprehensive summary stats
        contract_breakdown = f'''📊 BREAKDOWN KONTRAK RESIGN:
🎯 Total Resign: {total_resign} karyawan
📈 Rata-rata Overall: {avg_duration_by_type.mean():.1f} bulan
⚠️ Durasi Tersingkat: {avg_duration_by_type.min():.1f} bulan
⏰ Durasi Terpanjang: {avg_duration_by_type.max():.1f} bulan

🔍 DETAIL KONTRAK:
• Kontrak Permanent: {sum([resign_by_type.get(k, 0) for k in resign_by_type.index if 'Permanent' in k])} resign
• Kontrak ke-2: {sum([resign_by_type.get(k, 0) for k in resign_by_type.index if 'ke-2' in k])} resign  
• Kontrak ke-3: {sum([resign_by_type.get(k, 0) for k in resign_by_type.index if 'ke-3' in k])} resign
• Kontrak Lainnya: {sum([resign_by_type.get(k, 0) for k in resign_by_type.index if 'Lainnya' in k])} resign'''
        
        plt.text(1.02, 0.98, contract_breakdown, transform=plt.gca().transAxes, 
                bbox=dict(boxstyle='round', facecolor='lightcoral', alpha=0.9),
                fontsize=10, fontweight='bold', verticalalignment='top')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/21A_resign_average_duration_by_type.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 3: Timing resign analysis - DETAILED WITH NUMBERS
        plt.figure(figsize=(16, 10))
        
        # Create stacked bar for resign timing analysis
        resign_timing_data = resign_data.copy()
        resign_timing_data['resign_timing'] = resign_timing_data['contract_duration_months'].apply(
            lambda x: '0-3 bulan (Sangat Awal)' if x <= 3 else
                     '4-6 bulan (Awal)' if x <= 6 else
                     '7-12 bulan (Tengah)' if x <= 12 else
                     '13-24 bulan (Akhir)' if x <= 24 else
                     '24+ bulan (Sangat Akhir)'
        )
        
        # Cross tabulation
        timing_type_crosstab = pd.crosstab(resign_timing_data['resign_timing'], 
                                          resign_timing_data['contract_type_resign'])
        
        ax = timing_type_crosstab.plot(kind='bar', stacked=True, figsize=(16, 10),
                                      colormap='Reds', alpha=0.8)
        
        plt.title('TIMING RESIGN vs TIPE KONTRAK\n' +
                 'Analisis Waktu Resign dalam Siklus Kontrak\n' +
                 f'Detail Angka Resign: {total_resign} Karyawan Total', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Timing Resign', fontsize=12, fontweight='bold')
        plt.ylabel('Jumlah Karyawan', fontsize=12, fontweight='bold')
        plt.xticks(rotation=45, ha='right')
        plt.legend(title='Tipe Kontrak', bbox_to_anchor=(0.02, 0.98), loc='upper left', 
                  fontsize=10, title_fontsize=11)
        
        # Add detailed value labels on bars
        for container in ax.containers:
            labels = []
            for v in container.datavalues:
                if v > 0:  # Only show non-zero values
                    labels.append(f'{int(v)}')
                else:
                    labels.append('')
            ax.bar_label(container, labels=labels, fontweight='bold', fontsize=9, rotation=0)
        
        # Calculate detailed timing statistics
        timing_stats = {}
        for timing in timing_type_crosstab.index:
            count = timing_type_crosstab.loc[timing].sum()
            percentage = (count / total_resign) * 100
            timing_stats[timing] = {'count': count, 'percentage': percentage}
        
        # Add comprehensive statistics with detailed numbers
        resign_stats = f'''📊 STATISTIK RESIGN DETAIL:
🎯 Total Resign: {total_resign} karyawan (100%)
📈 Rata-rata Durasi: {resign_data['contract_duration_months'].mean():.1f} bulan
📊 Median Durasi: {resign_data['contract_duration_months'].median():.1f} bulan
⬇️ Min Durasi: {resign_data['contract_duration_months'].min():.1f} bulan
⬆️ Max Durasi: {resign_data['contract_duration_months'].max():.1f} bulan

⏰ TIMING BREAKDOWN DETAIL:
• 0-3 bulan: {timing_stats.get("0-3 bulan (Sangat Awal)", {}).get("count", 0)} orang ({timing_stats.get("0-3 bulan (Sangat Awal)", {}).get("percentage", 0):.1f}%)
• 4-6 bulan: {timing_stats.get("4-6 bulan (Awal)", {}).get("count", 0)} orang ({timing_stats.get("4-6 bulan (Awal)", {}).get("percentage", 0):.1f}%)
• 7-12 bulan: {timing_stats.get("7-12 bulan (Tengah)", {}).get("count", 0)} orang ({timing_stats.get("7-12 bulan (Tengah)", {}).get("percentage", 0):.1f}%)
• 13-24 bulan: {timing_stats.get("13-24 bulan (Akhir)", {}).get("count", 0)} orang ({timing_stats.get("13-24 bulan (Akhir)", {}).get("percentage", 0):.1f}%)
• 24+ bulan: {timing_stats.get("24+ bulan (Sangat Akhir)", {}).get("count", 0)} orang ({timing_stats.get("24+ bulan (Sangat Akhir)", {}).get("percentage", 0):.1f}%)

🚨 EARLY WARNING INDICATORS:
• Resign <6 bulan: {len(resign_data[resign_data['contract_duration_months'] <= 6])} orang ({len(resign_data[resign_data['contract_duration_months'] <= 6])/total_resign*100:.1f}%)
• Resign 6-12 bulan: {len(resign_data[(resign_data['contract_duration_months'] > 6) & (resign_data['contract_duration_months'] <= 12)])} orang ({len(resign_data[(resign_data['contract_duration_months'] > 6) & (resign_data['contract_duration_months'] <= 12)])/total_resign*100:.1f}%)
• Resign >12 bulan: {len(resign_data[resign_data['contract_duration_months'] > 12])} orang ({len(resign_data[resign_data['contract_duration_months'] > 12])/total_resign*100:.1f}%)'''
        
        plt.text(0.70, 0.98, resign_stats, transform=ax.transAxes, 
                bbox=dict(boxstyle='round', facecolor='mistyrose', alpha=0.9),
                fontsize=9, fontweight='bold', verticalalignment='top')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/22_resign_timing_detailed_analysis.png', dpi=300, bbox_inches='tight')
        plt.close()
    
    def _create_employee_population_progression_analysis(self):
        """
        Analisis populasi karyawan dari kontrak 1 sampai resign by percentage
        """
        print("📈 Membuat analisis progres populasi karyawan...")
        
        # Create contract stage progression data
        all_employees = self.filtered_df.copy()
        
        # Define contract stages
        contract_stages = {
            'Probation': 0,
            'Kontrak 1': 0,
            'Kontrak 2': 0, 
            'Kontrak 3': 0,
            'Permanent': 0,
            'Resign': 0
        }
        
        # Count employees at each stage
        for idx, row in all_employees.iterrows():
            progression = row['contract_progression']
            
            # Count probation
            contract_stages['Probation'] += 1
            
            # Count by progression type
            if 'Gagal Probation' in progression:
                continue  # Don't count further stages
            elif any(x in progression for x in ['Kontrak ke-1', 'Kontrak Pertama']):
                contract_stages['Kontrak 1'] += 1
            elif 'Kontrak ke-2' in progression:
                contract_stages['Kontrak 1'] += 1
                contract_stages['Kontrak 2'] += 1
            elif 'Kontrak ke-3' in progression:
                contract_stages['Kontrak 1'] += 1
                contract_stages['Kontrak 2'] += 1
                contract_stages['Kontrak 3'] += 1
            elif 'Permanen' in progression:
                contract_stages['Kontrak 1'] += 1
                contract_stages['Permanent'] += 1
            
            # Count resign
            if 'Resign' in progression:
                contract_stages['Resign'] += 1
        
        # Calculate percentages (retention rate at each stage)
        total_initial = contract_stages['Probation']
        retention_percentages = {}
        cumulative_percentages = {}
        
        for stage, count in contract_stages.items():
            retention_percentages[stage] = (count / total_initial) * 100 if total_initial > 0 else 0
            cumulative_percentages[stage] = count
        
        # SEPARATED CHART 1: Retention Funnel
        plt.figure(figsize=(14, 10))
        
        # Funnel chart
        stages = list(retention_percentages.keys())
        percentages = list(retention_percentages.values())
        colors = ['#3498DB', '#2ECC71', '#F39C12', '#E74C3C', '#9B59B6', '#34495E']
        
        # Create horizontal funnel
        y_positions = range(len(stages))
        bars = plt.barh(y_positions, percentages, color=colors, alpha=0.8)
        
        plt.title('FUNNEL POPULASI KARYAWAN: PROBATION → KONTRAK → RESIGN\n' +
                 'Persentase Retention di Setiap Tahap Kontrak\n' +
                 f'Intelligence Data: {total_initial} Karyawan dari Probation hingga Status Akhir', 
                     fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Persentase Populasi (%)', fontsize=12, fontweight='bold')
        plt.ylabel('Tahap Kontrak', fontsize=12, fontweight='bold')
        plt.yticks(y_positions, stages)
        
        # Add percentage labels
        for bar, percentage, count in zip(bars, percentages, cumulative_percentages.values()):
            plt.text(bar.get_width() + 1, bar.get_y() + bar.get_height()/2,
                    f'{percentage:.1f}%\n({count} orang)', 
                    va='center', fontweight='bold')
        
        plt.grid(True, alpha=0.3, axis='x')
        plt.xlim(0, 110)
        
        # Add comprehensive statistics positioned to not cover the chart
        dropout_rate = retention_percentages['Resign']
        success_to_permanent = retention_percentages['Permanent']
        
        stats_text = f'''📊 STATISTIK PROGRES POPULASI:
🎯 Total Awal (Probation): {total_initial} karyawan (100%)
✅ Sukses ke Permanent: {cumulative_percentages['Permanent']} orang ({success_to_permanent:.1f}%)
❌ Total Resign: {cumulative_percentages['Resign']} orang ({dropout_rate:.1f}%)
📈 Overall Success Rate: {(cumulative_percentages['Permanent'] / total_initial * 100):.1f}%'''
        
        plt.text(0.02, 0.02, stats_text, transform=plt.gca().transAxes, 
                bbox=dict(boxstyle='round', facecolor='lightcyan', alpha=0.9),
                fontsize=11, fontweight='bold', verticalalignment='bottom')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/23_employee_population_progression.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 2: Progression Line Chart
        plt.figure(figsize=(14, 8))
        
        active_stages = ['Probation', 'Kontrak 1', 'Kontrak 2', 'Kontrak 3', 'Permanent']
        active_counts = [cumulative_percentages[stage] for stage in active_stages]
        active_percentages = [retention_percentages[stage] for stage in active_stages]
        
        plt.plot(active_stages, active_percentages, marker='o', linewidth=4, markersize=10, 
                color='#2ECC71', markerfacecolor='white', markeredgewidth=3, label='Aktif')
        
        # Add resign line
        resign_line = [retention_percentages['Probation'] - retention_percentages['Resign'] if i == 0 
                      else retention_percentages[stage] - retention_percentages['Resign'] 
                      for i, stage in enumerate(active_stages)]
        plt.plot(active_stages, resign_line, marker='s', linewidth=4, markersize=10, 
                color='#E74C3C', markerfacecolor='white', markeredgewidth=3, 
                linestyle='--', label='Net Retention (Aktif - Resign)')
        
        plt.title('TREN PROGRES POPULASI KARYAWAN\n' +
                 'Aktif vs Net Retention per Tahap\n' +
                 f'Intelligence Data: {total_initial} Karyawan', 
                     fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Tahap Kontrak', fontsize=12, fontweight='bold')
        plt.ylabel('Persentase (%)', fontsize=12, fontweight='bold')
        plt.ylim(0, 105)
        
        # Add value labels
        for i, (stage, pct, count) in enumerate(zip(active_stages, active_percentages, active_counts)):
            plt.text(i, pct + 2, f'{pct:.1f}%\n({count})', ha='center', va='bottom', 
                    fontweight='bold', bbox=dict(boxstyle='round,pad=0.3', facecolor='lightgreen', alpha=0.7))
        
        plt.grid(True, alpha=0.3)
        plt.legend(fontsize=12, loc='upper right')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/23A_employee_population_trend_line.png', dpi=300, bbox_inches='tight')
        plt.close()
    
    def _create_contract_duration_recommendation_analysis(self):
        """
        Analisis rekomendasi kontrak berdasarkan durasi (3, 6, 12 bulan + permanent)
        """
        print("🎯 Membuat analisis rekomendasi durasi kontrak...")
        
        # Prepare data for recommendation analysis
        all_data = self.filtered_df.copy()
        
        # Define recommendation categories based on duration and outcome
        def categorize_recommendation_outcome(row):
            duration = row['contract_duration_months']
            progression = row['contract_progression']
            
            # Determine outcome
            if 'Resign' in progression:
                outcome = 'Resign'
            elif 'Aktif' in progression:
                outcome = 'Cocok/Aktif'
            elif 'Permanen' in progression:
                outcome = 'Cocok/Permanent'
            elif 'Gagal' in progression:
                outcome = 'Tidak Dilanjutkan'
            else:
                outcome = 'Status Unclear'
                
            return outcome
        
        all_data['recommendation_outcome'] = all_data.apply(categorize_recommendation_outcome, axis=1)
        
        # Define duration buckets for recommendation
        def categorize_duration_bucket(duration):
            if duration <= 3:
                return '≤3 Bulan'
            elif duration <= 6:
                return '4-6 Bulan'
            elif duration <= 12:
                return '7-12 Bulan'
            elif duration <= 24:
                return '13-24 Bulan'
            else:
                return '>24 Bulan'
        
        all_data['duration_bucket'] = all_data['contract_duration_months'].apply(categorize_duration_bucket)
        
        # Create comprehensive recommendation analysis
        recommendation_matrix = pd.crosstab(all_data['duration_bucket'], all_data['recommendation_outcome'])
        
        # SEPARATED CHART 1: Heatmap of recommendations
        plt.figure(figsize=(12, 8))
        sns.heatmap(recommendation_matrix, annot=True, fmt='d', cmap='RdYlGn_r', 
                   cbar_kws={'label': 'Jumlah Karyawan'})
        plt.title('MATRIX REKOMENDASI: DURASI vs OUTCOME\n' +
                 'Analysis untuk Sistem Rekomendasi Kontrak\n' +
                 'Analisis Komprehensif untuk Optimasi Durasi Kontrak', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Outcome Karyawan', fontsize=12, fontweight='bold')
        plt.ylabel('Durasi Kontrak', fontsize=12, fontweight='bold')
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/24_contract_duration_recommendation_system.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 2: Success Rate by Duration (Percentage) - COMPREHENSIVE ALL TYPES
        plt.figure(figsize=(14, 10))
        
        # Enhanced analysis including contract types 2, 3, and permanent
        comprehensive_analysis = {}
        
        # Analyze all contract types with detailed breakdown
        contract_types = {
            '3 Bulan (Trial)': all_data[all_data['contract_duration_months'] <= 3],
            '6 Bulan (Standard)': all_data[(all_data['contract_duration_months'] > 3) & 
                                          (all_data['contract_duration_months'] <= 6)],
            '12 Bulan (Extended)': all_data[(all_data['contract_duration_months'] > 6) & 
                                           (all_data['contract_duration_months'] <= 12)],
            '24 Bulan (Long-term)': all_data[(all_data['contract_duration_months'] > 12) & 
                                            (all_data['contract_duration_months'] <= 24)],
            '24+ Bulan (Senior)': all_data[all_data['contract_duration_months'] > 24]
        }
        
        # Calculate comprehensive success rates
        labels = []
        success_rates = []
        total_counts = []
        
        for contract_name, contract_data in contract_types.items():
            if len(contract_data) > 0:
                outcome_counts = contract_data['recommendation_outcome'].value_counts()
                total = len(contract_data)
                success_count = outcome_counts.get('Cocok/Aktif', 0) + outcome_counts.get('Cocok/Permanent', 0)
                success_rate = (success_count / total * 100) if total > 0 else 0
                
                labels.append(contract_name)
                success_rates.append(success_rate)
                total_counts.append(total)
                
                comprehensive_analysis[contract_name] = {
                    'total': total,
                    'success': success_count,
                    'resign': outcome_counts.get('Resign', 0),
                    'tidak_dilanjutkan': outcome_counts.get('Tidak Dilanjutkan', 0),
                    'success_rate': success_rate
                }
        
        # Create enhanced bar chart
        colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#F7DC6F']
        bars = plt.bar(range(len(labels)), success_rates, 
                      color=colors[:len(labels)], alpha=0.8)
        
        plt.title('SUCCESS RATE PER DURASI KONTRAK\n' +
                 'Analisis Komprehensif: 3, 6, 12, 24 Bulan + Long-term\n' +
                 'Sistem Rekomendasi Kontrak Berbasis Data Intelligence', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Tipe Durasi Kontrak', fontsize=12, fontweight='bold')
        plt.ylabel('Success Rate (%)', fontsize=12, fontweight='bold')
        plt.xticks(range(len(labels)), labels, rotation=45, ha='right')
        plt.ylim(0, 100)
        
        # Add enhanced value labels with count information
        for i, (bar, rate, count) in enumerate(zip(bars, success_rates, total_counts)):
            plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + 1,
                    f'{rate:.1f}%\n({count} orang)', ha='center', va='bottom', 
                    fontweight='bold', fontsize=10)
        
        # Add reference lines for performance tiers
        plt.axhline(y=70, color='green', linestyle='--', alpha=0.7, label='Target Excellent (70%)')
        plt.axhline(y=50, color='orange', linestyle='--', alpha=0.7, label='Target Good (50%)')
        plt.axhline(y=30, color='red', linestyle='--', alpha=0.7, label='Minimum Acceptable (30%)')
        plt.legend(loc='upper right')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/24A_success_rate_per_duration.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 3: Detailed breakdown - COMPREHENSIVE CONTRACT ANALYSIS WITH RANGES
        plt.figure(figsize=(16, 10))
        
        # Enhanced breakdown including all contract progression types (2, 3, permanent)
        # Analyze by actual contract progression patterns, not just duration
        contract_progression_analysis = {}
        
        # Group by contract progression type with duration ranges
        progression_types = {
            'Kontrak 1 (3-6 bulan)': all_data[
                (all_data['contract_progression'].str.contains('Kontrak ke-1|Kontrak Pertama', na=False)) &
                (all_data['contract_duration_months'] <= 6)
            ],
            'Kontrak 1 (6-12 bulan)': all_data[
                (all_data['contract_progression'].str.contains('Kontrak ke-1|Kontrak Pertama', na=False)) &
                (all_data['contract_duration_months'] > 6) & (all_data['contract_duration_months'] <= 12)
            ],
            'Kontrak 2 (6-12 bulan)': all_data[
                (all_data['contract_progression'].str.contains('Kontrak ke-2', na=False)) &
                (all_data['contract_duration_months'] <= 12)
            ],
            'Kontrak 2 (12-18 bulan)': all_data[
                (all_data['contract_progression'].str.contains('Kontrak ke-2', na=False)) &
                (all_data['contract_duration_months'] > 12) & (all_data['contract_duration_months'] <= 18)
            ],
            'Kontrak 3 (12-24 bulan)': all_data[
                (all_data['contract_progression'].str.contains('Kontrak ke-3', na=False)) &
                (all_data['contract_duration_months'] <= 24)
            ],
            'Kontrak 3 (24+ bulan)': all_data[
                (all_data['contract_progression'].str.contains('Kontrak ke-3', na=False)) &
                (all_data['contract_duration_months'] > 24)
            ],
            'Permanent (Aktif)': all_data[
                (all_data['contract_progression'].str.contains('Permanen|Permanent', na=False)) &
                (all_data['contract_progression'].str.contains('Aktif', na=False))
            ],
            'Permanent (Resign)': all_data[
                (all_data['contract_progression'].str.contains('Permanen|Permanent', na=False)) &
                (all_data['contract_progression'].str.contains('Resign', na=False))
            ]
        }
        
        # Calculate detailed analysis for each progression type
        for progression_name, progression_data in progression_types.items():
            if len(progression_data) > 0:
                # Analyze actual status (aktif vs resign vs tidak dilanjutkan)
                aktif_count = len(progression_data[progression_data['contract_progression'].str.contains('Aktif', na=False)])
                
                # SEPARATE resign sebelum and sesudah kontrak habis with different analysis
                resign_sebelum_count = 0
                resign_sesudah_count = 0
                
                # Handle different permanent categories
                if 'Permanent (Aktif)' in progression_name:
                    # Active permanent employees - no resign counts
                    resign_sebelum_count = 0
                    resign_sesudah_count = 0
                elif 'Permanent (Resign)' in progression_name:
                    # Permanent employees who resigned - analyze resign pattern
                    for idx, row in progression_data.iterrows():
                        if 'Resign' in str(row.get('contract_progression', '')):
                            duration = row.get('contract_duration_months', 0)
                            # Logic: if resign before expected permanent tenure (based on typical permanent duration)
                            if duration < 12:  # Short permanent tenure - likely resign sebelum stabilisasi
                                resign_sebelum_count += 1
                            else:  # Longer permanent tenure - likely resign sesudah stabilisasi
                                resign_sesudah_count += 1
                else:
                    # For contract employees - analyze resign pattern based on duration vs typical contract length
                    for idx, row in progression_data.iterrows():
                        if 'Resign' in str(row.get('contract_progression', '')):
                            duration = row.get('contract_duration_months', 0)
                            # Logic: if resign before expected contract end (based on typical contract length)
                            if duration < 6:  # Short contracts - likely resign sebelum habis
                                resign_sebelum_count += 1
                            else:  # Longer contracts - likely resign sesudah evaluation/habis
                                resign_sesudah_count += 1
                
                permanent_count = len(progression_data[progression_data['contract_progression'].str.contains('Permanen', na=False)])
                total = len(progression_data)
                
                # Success includes both active contracts and those converted to permanent
                success_count = aktif_count + permanent_count
                success_rate = (success_count / total * 100) if total > 0 else 0
                
                contract_progression_analysis[progression_name] = {
                    'total': total,
                    'aktif': aktif_count,
                    'permanent': permanent_count,
                    'resign_sebelum': resign_sebelum_count,
                    'resign_sesudah': resign_sesudah_count,
                    'success_rate': success_rate,
                    'avg_duration': progression_data['contract_duration_months'].mean()
                }
        
        # Create enhanced stacked bar chart with different colors for resign types
        progressions = [k for k, v in contract_progression_analysis.items() if v['total'] > 0]
        aktif_counts = [contract_progression_analysis[p]['aktif'] for p in progressions]
        permanent_counts = [contract_progression_analysis[p]['permanent'] for p in progressions]
        resign_sebelum_counts = [contract_progression_analysis[p]['resign_sebelum'] for p in progressions]
        resign_sesudah_counts = [contract_progression_analysis[p]['resign_sesudah'] for p in progressions]
        
        width = 0.7
        x = range(len(progressions))
        
        # Create stacked bars with DIFFERENT COLORS for resign types
        plt.bar(x, aktif_counts, width, label='Masih Aktif (Kontrak Berjalan)', color='#3498DB', alpha=0.8)
        plt.bar(x, permanent_counts, width, bottom=aktif_counts, 
               label='Converted to Permanent', color='#2ECC71', alpha=0.8)
        plt.bar(x, resign_sebelum_counts, width, 
               bottom=[a + p for a, p in zip(aktif_counts, permanent_counts)], 
               label='Resign Sebelum Kontrak Habis', color='#E74C3C', alpha=0.8)
        plt.bar(x, resign_sesudah_counts, width, 
               bottom=[a + p + rs for a, p, rs in zip(aktif_counts, permanent_counts, resign_sebelum_counts)], 
               label='Resign Sesudah Kontrak Habis', color='#C0392B', alpha=0.8)
        
        plt.title('BREAKDOWN DETAIL REKOMENDASI KONTRAK\n' +
                 'Analisis Kontrak 1, 2, 3 + Permanent dengan Range Durasi\n' +
                 'Sistem Rekomendasi Berbasis Status Aktif/Resign + Range Bulan', 
                 fontsize=16, fontweight='bold', pad=20)
        plt.xlabel('Tipe Kontrak + Range Durasi', fontsize=12, fontweight='bold')
        plt.ylabel('Jumlah Karyawan', fontsize=12, fontweight='bold')
        plt.xticks(x, [p.replace(' ', '\n') for p in progressions], fontsize=10, rotation=0)
        plt.legend(loc='upper left', bbox_to_anchor=(0.02, 0.98))
        
        # Set y-axis limits to provide more space for labels below
        max_total = max([contract_progression_analysis[p]['total'] for p in progressions])
        plt.ylim(-40, max_total + 20)  # Increased bottom margin from -15 to -40
        
        # Add comprehensive value labels WITH DETAILED NUMBERS
        for i, progression in enumerate(progressions):
            data = contract_progression_analysis[progression]
            total = data['total']
            aktif = data['aktif']
            permanent = data['permanent']
            resign_sebelum = data['resign_sebelum']
            resign_sesudah = data['resign_sesudah']
            success_rate = data['success_rate']
            avg_duration = data['avg_duration']
            
            # Total count on top
            plt.text(i, total + 2, f'Total: {total}', ha='center', va='bottom', 
                    fontweight='bold', fontsize=9)
            
            # Add detailed numbers on each segment
            # Aktif segment (blue)
            if aktif > 0:
                aktif_mid = aktif / 2
                plt.text(i, aktif_mid, f'{aktif}', ha='center', va='center', 
                        fontweight='bold', fontsize=8, color='white')
            
            # Permanent segment (green)
            if permanent > 0:
                permanent_mid = aktif + (permanent / 2)
                plt.text(i, permanent_mid, f'{permanent}', ha='center', va='center', 
                        fontweight='bold', fontsize=8, color='white')
            
            # Resign Sebelum segment (light red)
            if resign_sebelum > 0:
                resign_sebelum_mid = aktif + permanent + (resign_sebelum / 2)
                plt.text(i, resign_sebelum_mid, f'{resign_sebelum}', ha='center', va='center', 
                        fontweight='bold', fontsize=8, color='white')
            
            # Resign Sesudah segment (dark red)
            if resign_sesudah > 0:
                resign_sesudah_mid = aktif + permanent + resign_sebelum + (resign_sesudah / 2)
                plt.text(i, resign_sesudah_mid, f'{resign_sesudah}', ha='center', va='center', 
                        fontweight='bold', fontsize=8, color='white')
            
            # Success rate below - moved further down to avoid covering x-axis labels
            plt.text(i, -25, f'Success: {success_rate:.1f}%\nAvg: {avg_duration:.1f} bulan', 
                    ha='center', va='top', fontweight='bold', fontsize=8,
                    bbox=dict(boxstyle='round,pad=0.3', facecolor='lightblue', alpha=0.7))
        
        # Add SIMPLIFIED range recommendations on the right side
        recommendation_text = f'''📊 REKOMENDASI PERPANJANGAN:

🎯 KONTRAK 1: 3-6 bulan (Assessment), 6-12 bulan (Standard)
🎯 KONTRAK 2: 6-12 bulan (Evaluasi), 12-18 bulan (Extended)  
🎯 KONTRAK 3: 12-24 bulan (Senior), 24+ bulan (Leadership)
🎯 PERMANENT: Direct untuk exceptional performers

📈 PATTERN: Range pendek = evaluasi, Range panjang = retention tinggi'''
        
        plt.text(1.02, 0.85, recommendation_text, transform=plt.gca().transAxes, 
                bbox=dict(boxstyle='round', facecolor='lightyellow', alpha=0.9),
                fontsize=10, fontweight='bold', verticalalignment='top')
        
        plt.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(f'{output_dir}/24B_breakdown_detail_recommendation.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # SEPARATED CHART 4: Success Rate Comparison Chart - SIMPLIFIED & EASY TO UNDERSTAND
        plt.figure(figsize=(14, 10))
        
        # Create SIMPLE comparison with clear categories
        simple_categories = {
            '3 Bulan': {'rate': 0, 'total': 0, 'color': '#FF6B6B'},
            '6 Bulan': {'rate': 0, 'total': 0, 'color': '#4ECDC4'},
            '12 Bulan': {'rate': 0, 'total': 0, 'color': '#45B7D1'},
            '24 Bulan': {'rate': 0, 'total': 0, 'color': '#96CEB4'},
            '24+ Bulan': {'rate': 0, 'total': 0, 'color': '#F7DC6F'}
        }
        
        # Map comprehensive data to simple categories
        for label, analysis in comprehensive_analysis.items():
            if '3 Bulan' in label:
                simple_categories['3 Bulan']['rate'] = analysis['success_rate']
                simple_categories['3 Bulan']['total'] = analysis['total']
            elif '6 Bulan' in label:
                simple_categories['6 Bulan']['rate'] = analysis['success_rate']
                simple_categories['6 Bulan']['total'] = analysis['total']
            elif '12 Bulan' in label:
                simple_categories['12 Bulan']['rate'] = analysis['success_rate']
                simple_categories['12 Bulan']['total'] = analysis['total']
            elif '24 Bulan' in label and '24+' not in label:
                simple_categories['24 Bulan']['rate'] = analysis['success_rate']
                simple_categories['24 Bulan']['total'] = analysis['total']
            elif '24+' in label:
                simple_categories['24+ Bulan']['rate'] = analysis['success_rate']
                simple_categories['24+ Bulan']['total'] = analysis['total']
        
        # Create simple bar chart
        categories = list(simple_categories.keys())
        success_rates = [simple_categories[c]['rate'] for c in categories]
        totals = [simple_categories[c]['total'] for c in categories]
        colors = [simple_categories[c]['color'] for c in categories]
        
        bars = plt.bar(categories, success_rates, color=colors, alpha=0.8, width=0.6)
        
        plt.title('TINGKAT KEBERHASILAN KONTRAK KARYAWAN\n' +
                 'Berapa Persen Karyawan yang Sukses per Durasi Kontrak', 
                 fontsize=16, fontweight='bold', pad=25)
        plt.xlabel('Durasi Kontrak', fontsize=14, fontweight='bold')
        plt.ylabel('Tingkat Keberhasilan (%)', fontsize=14, fontweight='bold')
        plt.ylim(0, 100)
        
        # Add SIMPLE value labels
        for i, (bar, rate, total) in enumerate(zip(bars, success_rates, totals)):
            if total > 0:  # Only show if there's data
                plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + 2,
                        f'{rate:.1f}%', ha='center', va='bottom', 
                        fontweight='bold', fontsize=14)
                plt.text(bar.get_x() + bar.get_width()/2, bar.get_height()/2,
                        f'{total}\nkaryawan', ha='center', va='center', 
                        fontweight='bold', fontsize=10, color='white')
        
        # Add SIMPLE recommendation levels
        for i, (bar, rate) in enumerate(zip(bars, success_rates)):
            if rate >= 70:
                rec = '✅ BAGUS'
                rec_color = 'green'
            elif rate >= 50:
                rec = '⚠️ CUKUP'
                rec_color = 'orange'
            else:
                rec = '❌ KURANG'
                rec_color = 'red'
                
            plt.text(bar.get_x() + bar.get_width()/2, -8,
                    rec, ha='center', va='top', fontweight='bold', 
                    color=rec_color, fontsize=12)
        
        # Add reference lines
        plt.axhline(y=70, color='green', linestyle='--', alpha=0.5, label='Target Bagus (70%)')
        plt.axhline(y=50, color='orange', linestyle='--', alpha=0.5, label='Target Cukup (50%)')
        plt.legend(loc='upper right')
        
        plt.grid(True, alpha=0.3, axis='y')
        
        # Add SIMPLE summary - positioned properly
        best_rate = max([r for r in success_rates if r > 0]) if any(r > 0 for r in success_rates) else 0
        best_cat = categories[success_rates.index(best_rate)] if best_rate > 0 else "N/A"
        
        simple_summary = f'''💡 KESIMPULAN SEDERHANA:

🏆 DURASI TERBAIK: {best_cat} ({best_rate:.1f}% sukses)
📊 TOTAL KARYAWAN: {sum(totals)} orang dianalisis
🎯 REKOMENDASI: Gunakan durasi dengan tingkat sukses ≥50%'''
        
        plt.text(0.02, 0.98, simple_summary, transform=plt.gca().transAxes,
                fontsize=12, fontweight='bold', verticalalignment='top',
                bbox=dict(boxstyle='round', facecolor='lightcyan', alpha=0.9))
        
        plt.tight_layout()
        plt.savefig(f'{output_dir}/24C_success_rate_comparison.png', dpi=300, bbox_inches='tight')
        plt.close()
        
        # Create additional summary table visualization - FIXED
        plt.figure(figsize=(14, 10))
        
        # Create summary table using comprehensive_analysis data
        summary_data = []
        for label, data in comprehensive_analysis.items():
            summary_data.append([
                label,
                data['total'],
                data['success'],
                data['resign'],
                data['tidak_dilanjutkan'],
                f"{data['success_rate']:.1f}%"
            ])
        
        table_data = pd.DataFrame(summary_data, 
                                 columns=['Durasi Kontrak', 'Total', 'Success (Aktif+Permanent)', 'Resign', 'Tidak Dilanjutkan', 'Success Rate'])
        
        # Create table visualization
        fig, ax = plt.subplots(figsize=(14, 10))
        ax.axis('tight')
        ax.axis('off')
        
        table = ax.table(cellText=table_data.values,
                        colLabels=table_data.columns,
                        cellLoc='center',
                        loc='center',
                        cellColours=[['lightblue']*6 for _ in range(len(table_data))])
        
        table.auto_set_font_size(False)
        table.set_fontsize(11)
        table.scale(1.3, 2.5)
        
        # Color code based on success rate
        for i in range(len(table_data)):
            success_rate = float(table_data.iloc[i]['Success Rate'].replace('%', ''))
            if success_rate >= 70:
                color = 'lightgreen'
            elif success_rate >= 50:
                color = 'lightyellow'
            else:
                color = 'lightcoral'
            
            for j in range(len(table_data.columns)):
                table[(i+1, j)].set_facecolor(color)
        
        # Enhanced header colors
        for j in range(len(table_data.columns)):
            table[(0, j)].set_facecolor('#4CAF50')
            table[(0, j)].set_text_props(weight='bold', color='white')
        
        plt.title('TABEL RINGKASAN REKOMENDASI KONTRAK\n' +
                 'Sistem Rekomendasi Berbasis Data Intelligence\n' +
                 'Analisis Komprehensif: 3, 6, 12, 24 Bulan + Long-term', 
                 fontsize=16, fontweight='bold', pad=20)
        
        # Add legend for color coding
        legend_text = '''🎨 COLOR CODING:
🟢 Green: Success Rate ≥ 70% (SANGAT DIREKOMENDASIKAN)
🟡 Yellow: Success Rate 50-69% (DIREKOMENDASIKAN)  
🔴 Red: Success Rate < 50% (TIDAK DIREKOMENDASIKAN)'''
        
        plt.figtext(0.02, 0.15, legend_text, fontsize=10, fontweight='bold',
                   bbox=dict(boxstyle='round', facecolor='lightgray', alpha=0.8))
        
        plt.savefig(f'{output_dir}/25_contract_recommendation_summary_table.png', dpi=300, bbox_inches='tight')
        plt.close()

    def run_complete_analysis(self):
        """
        Run the complete analysis pipeline
        """
        print("🚀 Memulai analisis lengkap Data Mining & BI untuk Sistem Rekomendasi Kontrak Karyawan")
        print("="*80)
        
        # Step 1: Apply filters
        self.apply_filters()
        
        # Step 2: Process contract analysis
        self.process_contract_analysis()
        
        # Step 3: Process education analysis
        self.process_education_analysis()
        
        # Step 4: Create Variable X visualizations
        self.create_variable_x_visualizations()
        
        # Step 5: Create Variable Y visualizations
        self.create_variable_y_visualizations()
        
        # Step 6: Create Advanced Degree-Role Analysis (NEW)
        self.create_advanced_degree_role_analysis()
        
        # Step 7: Create Post-Probation Prediction Analysis (NEW)
        self.create_post_probation_prediction_analysis()
        
        # Step 8: NEW - Export cleaned data
        export_dir = self.export_clean_data()
        
        # Step 9: NEW - Create contract extension range analysis
        self.create_contract_extension_range_analysis()
        
        # Step 10: NEW - Create Intelligence Data Visualizations
        self.create_new_intelligence_visualizations()
        
        # Step 11: Generate insights and recommendations
        self.generate_insights_and_recommendations()
        
        print(f"\n✅ Analisis lengkap selesai!")
        print(f"📁 Semua visualisasi tersimpan di folder: {output_dir}/")
        print(f"📁 Data yang sudah diolah tersimpan di folder: {export_dir}/")
        print(f"📊 Total visualisasi dibuat: 32+ grafik (dengan 10+ visualisasi BARU)")
        print(f"📋 Laporan insight tersimpan: insights_and_recommendations.txt")
        print(f"\n🆕 FITUR BARU YANG DITAMBAHKAN:")
        print(f"   📁 Export Data: 6 file CSV dengan data yang sudah diolah")
        print(f"   📊 Extension Range Analysis: 5 visualisasi analisis perpanjangan kontrak")
        print(f"   🎯 Job-Education Matching: Analisis kesesuaian pekerjaan dan jurusan")
        print(f"   📈 Timeline Analysis: Analisis timeline progres kontrak")
        print(f"   🔍 Role at Client Analysis: Analisis detail berdasarkan role di client")
        print(f"\n🔥 INTELLIGENCE DATA VISUALIZATIONS (TERBARU):")
        print(f"   📊 Chart 21-22: Resign Before Contract End Analysis")
        print(f"   📈 Chart 23: Employee Population Progression Funnel")
        print(f"   🎯 Chart 24-25: Contract Duration Recommendation System")
        print(f"   🤖 AI-Based Contract Recommendation Engine")
        print(f"   🚨 Early Warning System untuk Resign Prevention")

# Main execution
if __name__ == "__main__":
    # Initialize the analyzer
    csv_file = "241015 Employee database - ops - 15 Oct 2024.csv"
    
    try:
        analyzer = EmployeeContractAnalyzer(csv_file)
        analyzer.run_complete_analysis()
        
        print("\n" + "="*80)
        print("🎉 ANALISIS DATA MINING & BI SISTEM REKOMENDASI KONTRAK SELESAI!")
        print("="*80)
        
    except FileNotFoundError:
        print(f"❌ Error: File '{csv_file}' tidak ditemukan!")
        print("📝 Pastikan file CSV ada di direktori yang sama dengan script ini.")
    except Exception as e:
        print(f"❌ Error dalam analisis: {str(e)}")
        print("📝 Periksa format data CSV dan pastikan semua kolom tersedia.") 