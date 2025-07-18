# 🔍 Manager Dashboard Data Debugging Guide

## ✅ Status Perbaikan yang Sudah Selesai:

1. **ESLint Errors**: ✅ FIXED
   - Removed undefined variables: `setEmployeeDetail`, `setLoadingDetail`, `setShowEmployeeModal`, `setAlert`
   - Fixed import paths for AuthContext
   - Added proper optional chaining

2. **Navigation**: ✅ FIXED
   - Replaced modals with separate pages
   - Added proper routes for `/manager/employee/:eid` dan `/manager/recommendation/:eid`

3. **Backend API**: ✅ WORKING
   - Manager analytics API returns 8 employees
   - Data Intelligence integrated
   - Session management working

4. **Frontend Build**: ✅ SUCCESS
   - Fixed JavaScript errors
   - Updated URLs to use absolute paths

## 🚨 Current Issue: Dashboard shows 0 data

**Possible Causes:**
1. User not logged in as manager in browser
2. CORS issues between React (localhost:3000) and PHP (localhost)
3. Session not shared between frontend and backend

## 🧪 Debug Steps:

### Step 1: Test Backend API Directly
1. Open browser: http://localhost/web_srk_BI/backend/test_browser_api.php
2. Click "🔑 Login as Manager" 
3. Click "🧪 Test Manager Analytics API"
4. Verify you see 8 employees in JSON response

### Step 2: Check Frontend Console
1. Open React app: http://localhost:3000
2. Login as manager (username: Manajer1, any password)
3. Open Browser DevTools (F12) → Console tab
4. Look for console logs from fetchManagerData function:
   - `🚀 Starting fetchManagerData...`
   - `👤 User:` (should show user data)
   - `🏢 Division ID:` (should show division_id: 1)
   - `📡 Fetching URL:` (should show correct URL)
   - `📥 Response status:` (should be 200)
   - `📊 Manager Analytics Result:` (should show JSON with employees)

### Step 3: Check Network Tab
1. In DevTools → Network tab
2. Look for request to `manager_analytics_by_division.php`
3. Check response:
   - Status should be 200
   - Response should contain JSON with employees array

## 🛠️ Quick Fixes to Try:

### If CORS Error:
```php
// Add to backend/manager_analytics_by_division.php (already added)
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
```

### If Session Issue:
1. Make sure user logged in properly in React app
2. Check if session cookies are being sent with requests

### If URL Issue:
Current URLs used:
- `http://localhost/web_srk_BI/backend/manager_analytics_by_division.php`
- `http://localhost/web_srk_BI/backend/api/recommendations.php`

## 📊 Expected Data Structure:

**Backend API Response:**
```json
{
  "success": true,
  "data": {
    "employees": [/* 8 employee objects */],
    "division_stats": {
      "total_employees": 8,
      "match_count": 6,
      "unmatch_count": 2,
      "match_percentage": 75,
      "division_name": "Operation & Delivery"
    }
  }
}
```

**Console Logs Should Show:**
```
🚀 Starting fetchManagerData...
👤 User: {user_id: 3, username: "Manajer1", role: "manager", division_id: 1}
🏢 Division ID: 1
📡 Fetching URL: http://localhost/web_srk_BI/backend/manager_analytics_by_division.php?division_id=1
📥 Response status: 200
📥 Response OK: true
📊 Manager Analytics Result: {success: true, data: {...}}
📊 Success: true
📊 Data: {employees: Array(8), division_stats: {...}}
📊 Employees count: 8
✅ Analytics data set successfully
🏁 fetchManagerData completed
```

## 🔧 Manual Test Commands:

```bash
# Test backend directly
cd backend
php test_manager_analytics.php

# Check users and data
php check_users.php

# Test login simulation
php test_login_manager.php
```

## 📝 Next Steps:

1. Follow debug steps above
2. Share console logs and network response
3. If issue persists, check session sharing between React and PHP 