<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=0.8">
  <title>UDHO Census Form</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
       .document-container {
      width: 21cm;
      min-height: 29.7cm;
      margin: 0 auto;
      padding: 1.5cm;
      background-color: white;
      font-family: Arial, sans-serif;
      box-shadow: 0 0 24px 4px rgba(0,0,0,0.25);
    }

    @media print {
      @page {
        size: 8.5in 14in; /* Indian Legal size */
        margin: 0;
      }
      body {
        margin: 0;
        padding: 0;
        background: white;
        width: 100%;
        height: 100%;
      }
      body * {
        visibility: hidden;
      }
      #documentModal, #documentModal * {
        visibility: visible;
      }
      #documentModal {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        background: white;
        overflow: visible;
      }
      #documentModal > div {
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
      }
      .document-container {
        width: 100% !important;
        max-width: 8.5in !important;
        min-height: auto;
        margin: 0 auto !important;
        padding: 0.5in !important;
        box-shadow: none !important;
        transform: none !important;
      }
      .no-print {
        display: none !important;
      }
      .print-content {
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
      }
    }

    /* Rest of your existing styles... */
    .form-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
    }
    .form-table th, .form-table td {
      border: 1px solid #000;
      padding: 4px;
      vertical-align: top;
    }
    .form-table th {
      background-color: #f0f0f0;
      text-align: left;
      font-weight: bold;
    }
    .checkbox-cell {
      width: 20px;
      text-align: center;
    }
    .signature-line {
      border-top: 1px solid #000;
      display: inline-block;
      width: 200px;
      margin-top: 30px;
    }
    .header-border {
      border-top: 2px solid #000;
      border-bottom: 2px solid #000;
      padding: 5px 0;
    }
    .dashed-line {
      border-top: 1px dashed #000;
      margin: 5px 0;
    }
    .modal-content {
      max-height: calc(100vh - 100px);
      overflow-y: auto;
    }
  </style>
</head>
<body class="bg-gray-100 p-4">
  <!-- Button to trigger modal -->
  <button onclick="openDocumentModal()" class="bg-blue-600 text-white px-4 py-2 rounded-md mb-4">
    <i class="fas fa-file-alt mr-2"></i> Generate Census Form
  </button>

  <!-- Modal -->
  <div id="documentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl max-h-screen overflow-auto">
      <!-- Modal header -->
      <div class="flex justify-between items-center border-b p-4 no-print">
        <h3 class="text-xl font-bold">UDHO Census Form</h3>
        <div class="flex gap-2">
          <button onclick="printDocument()" class="bg-blue-600 text-white px-3 py-1 rounded">
            <i class="fas fa-print mr-1"></i> Print
          </button>
          <button onclick="downloadPDF()" class="bg-green-600 text-white px-3 py-1 rounded">
            <i class="fas fa-download mr-1"></i> Download
          </button>
          <button onclick="closeDocumentModal()" class="bg-gray-600 text-white px-3 py-1 rounded">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      
      <!-- Document content -->
      <div class="p-4 print-content">
        <div class="document-container">
          <!-- Header -->
          <div class="text-center mb-2">
            <h1 class="font-bold text-sm">Republika ng Pilipinas</h1>
            <h2 class="font-bold text-sm">Lungsod ng Pasay, Kalakhang Maynila</h2>
            <h3 class="font-bold text-base">URBAN DEVELOPMENT AND HOUSING OFFICE</h3>
            <p class="text-xs">Room 209, Pasay City Hall, F.B. Harrison St., Pasay City</p>
          </div>
          
          <!-- Tag Number Section -->
          <div class="header-border my-3">
            <h4 class="font-bold text-sm">TAG NUMBER: <span id="tagNumber" class="font-normal underline">UDHO-2023-001234</span></h4>
            <h4 class="font-bold text-sm">DATE: <span id="censusDate" class="font-normal underline">05/07/2025</span></h4>
            <h4 class="font-bold text-sm">UDCode: <span id="udCode" class="font-normal underline">PSY-01</span></h4>
          </div>
          
          <!-- Section I: Personal Data -->
          <h4 class="font-bold text-sm mt-4">I. PERSONAL DATA</h4>
          <h5 class="font-bold text-xs">Name of Household Head</h5>
          <h5 class="font-bold text-xs">Name of Spouse</h5>
          
          <table class="form-table mt-1 mb-2">
            <thead>
              <tr>
                <th colspan="4" class="text-xs">Household Head</th>
                <th colspan="4" class="text-xs">Spouse</th>
              </tr>
              <tr>
                <th class="text-xs">Surname</th>
                <th class="text-xs">First Name</th>
                <th class="text-xs">Middle Name</th>
                <th class="text-xs">MI</th>
                <th class="text-xs">Surname</th>
                <th class="text-xs">First Name</th>
                <th class="text-xs">Middle Name</th>
                <th class="text-xs">MI</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td id="hhSurname">DELA CRUZ</td>
                <td id="hhFirstName">JUAN</td>
                <td id="hhMiddleName">REYES</td>
                <td id="hhMi">M</td>
                <td id="spouseSurname">DELA CRUZ</td>
                <td id="spouseFirstName">MARIA</td>
                <td id="spouseMiddleName">SANTOS</td>
                <td id="spouseMi">R</td>
              </tr>
            </tbody>
          </table>
          
          <table class="form-table mb-2">
            <tr>
              <td class="text-xs">Sex</td>
              <td id="hhSex" class="text-xs">Male</td>
              <td class="text-xs">Age</td>
              <td id="hhAge" class="text-xs">45</td>
              <td class="text-xs">Birthdate</td>
              <td id="hhBirthdate" class="text-xs">05/15/1980</td>
              <td class="text-xs">Sex</td>
              <td id="spouseSex" class="text-xs">Female</td>
            </tr>
          </table>
          
          <table class="form-table mb-4">
            <tr>
              <td class="text-xs">Civil Status</td>
              <td class="text-xs">Vulnerability</td>
              <td class="text-xs">Civil Status</td>
              <td class="text-xs">Vulnerability</td>
            </tr>
            <tr>
              <td class="text-xs">
                <span id="hhCivilStatus">☑ Married</span><br>
                <span>□ Single</span><br>
                <span>□ Widow/Widower</span>
              </td>
              <td class="text-xs">
                <span>□ Senior Citizen</span><br>
                <span id="hhPwd">☑ PWD</span><br>
                <span>□ Solo Parent</span>
              </td>
              <td class="text-xs">
                <span id="spouseCivilStatus">☑ Married</span><br>
                <span>□ Single</span><br>
                <span>□ Widow/Widower</span>
              </td>
              <td class="text-xs">
                <span>□ Senior Citizen</span><br>
                <span>□ PWD</span><br>
                <span id="spouseSoloParent">☑ Solo Parent</span>
              </td>
            </tr>
          </table>
          
          <!-- Section II: Tenurial Status -->
          <h4 class="font-bold text-sm mt-4">II. TENURIAL STATUS</h4>
          <table class="form-table mb-2">
            <tr>
              <th colspan="8" class="text-xs">Residential Address</th>
            </tr>
            <tr>
              <th class="text-xs">House No.</th>
              <th class="text-xs">Lot No.</th>
              <th class="text-xs">Building</th>
              <th class="text-xs">Block</th>
              <th class="text-xs">Street</th>
              <th class="text-xs">Barangay</th>
              <th class="text-xs">City</th>
              <th class="text-xs">Region</th>
            </tr>
            <tr>
              <td id="houseNo">123</td>
              <td id="lotNo">45</td>
              <td id="building">-</td>
              <td id="block">7</td>
              <td id="street">M. Dela Cruz St.</td>
              <td id="barangay">Barangay 12</td>
              <td id="city">Pasay City</td>
              <td id="region">NCR</td>
            </tr>
          </table>
          
          <table class="form-table mb-2">
            <tr>
              <th colspan="2" class="text-xs">Nature of Land Occupied</th>
              <th class="text-xs">Lot Owner</th>
              <th class="text-xs"></th>
            </tr>
            <tr>
              <td class="text-xs">☑ Private</td>
              <td class="text-xs">□ Government</td>
              <td class="text-xs">□ Yes</td>
              <td class="text-xs">☑ No</td>
            </tr>
          </table>
          
          <table class="form-table mb-4">
            <tr>
              <th colspan="4" class="text-xs">Security of Tenure</th>
            </tr>
            <tr>
              <td class="text-xs">□ Owner</td>
              <td class="text-xs">☑ Renter (< 5 years)</td>
              <td class="text-xs">□ Rent Free Owner</td>
              <td class="text-xs"></td>
            </tr>
            <tr>
              <td class="text-xs">□ Co-Owner</td>
              <td class="text-xs">□ Renter (>5 years)</td>
              <td colspan="2" class="text-xs">Name of Owner (for RFO/Renter): <span id="ownerName">JOSE REYES</span></td>
            </tr>
          </table>
          
          <!-- Section III: Membership -->
          <h4 class="font-bold text-sm mt-4">III. MEMBERSHIP</h4>
          <table class="form-table mb-4">
            <tr>
              <th class="text-xs">Fund</th>
              <th class="text-xs"></th>
              <th class="text-xs">Organization</th>
              <th class="text-xs"></th>
              <th colspan="4" class="text-xs">Name of Organization</th>
            </tr>
            <tr>
              <td class="text-xs">☑ PAG-IBIG</td>
              <td class="checkbox-cell"></td>
              <td class="text-xs">□ CSO</td>
              <td class="checkbox-cell"></td>
              <td colspan="4" id="orgName" class="text-xs">Pasay Homeowners Association</td>
            </tr>
            <tr>
              <td class="text-xs">☑ SSS</td>
              <td class="checkbox-cell"></td>
              <td class="text-xs">☑ HOA</td>
              <td class="checkbox-cell"></td>
              <td colspan="4" class="text-xs"></td>
            </tr>
            <tr>
              <td class="text-xs">□ GSIS</td>
              <td class="checkbox-cell"></td>
              <td class="text-xs">□ Cooperative</td>
              <td class="checkbox-cell"></td>
              <td colspan="4" class="text-xs"></td>
            </tr>
            <tr>
              <td class="text-xs">☑ PhilHealth</td>
              <td class="checkbox-cell"></td>
              <td class="text-xs">□ None</td>
              <td class="checkbox-cell"></td>
              <td colspan="4" class="text-xs"></td>
            </tr>
            <tr>
              <td class="text-xs">□ APS</td>
              <td class="checkbox-cell"></td>
              <td class="text-xs">□ Others</td>
              <td class="checkbox-cell"></td>
              <td colspan="4" class="text-xs"></td>
            </tr>
          </table>
          
          <!-- Section IV: Remarks -->
          <h4 class="font-bold text-sm mt-4">IV. REMARKS</h4>
          <table class="form-table mb-2">
            <thead>
              <tr>
                <th class="text-xs">NAME</th>
                <th class="text-xs">RELATIONSHIP TO THE HEAD</th>
                <th class="text-xs">AGE</th>
                <th class="text-xs">SEX</th>
                <th class="text-xs">BIRTHDATE (MM/DD/YY)</th>
                <th class="text-xs">HIGHEST EDUCATIONAL ATTAINMENT</th>
              </tr>
            </thead>
            <tbody id="householdMembers">
              <!-- Will be populated from database -->
              <tr>
                <td class="text-xs">JUAN DELA CRUZ</td>
                <td class="text-xs">Head</td>
                <td class="text-xs">45</td>
                <td class="text-xs">Male</td>
                <td class="text-xs">05/15/1980</td>
                <td class="text-xs">College Graduate</td>
              </tr>
              <tr>
                <td class="text-xs">MARIA DELA CRUZ</td>
                <td class="text-xs">Spouse</td>
                <td class="text-xs">42</td>
                <td class="text-xs">Female</td>
                <td class="text-xs">08/22/1983</td>
                <td class="text-xs">High School Graduate</td>
              </tr>
              <!-- More members would be added here -->
            </tbody>
          </table>
          
          <table class="form-table mb-2">
            <tr>
              <th class="text-xs">SHELTER NEEDS</th>
              <th class="text-xs">HOUSEHOLD CLASSIFICATION</th>
              <th class="text-xs">CENSUS REMARKS</th>
            </tr>
            <tr>
              <td class="text-xs">□ Tenure Upgrading</td>
              <td class="text-xs">□ Single HH</td>
              <td class="text-xs">□ Out During Census (ODC)</td>
            </tr>
            <tr>
              <td class="text-xs">☑ Shelter Provision</td>
              <td class="text-xs">□ Displaced Unit</td>
              <td class="text-xs">□ Absentee House Owner (AHO)</td>
            </tr>
            <tr>
              <td class="text-xs">□ Structural Upgrading</td>
              <td class="text-xs">☑ Doubled Up HH</td>
              <td class="text-xs">□ Others</td>
            </tr>
            <tr>
              <td class="text-xs">□ Infrastructure Upgrading</td>
              <td class="text-xs">□ Displacement Concern</td>
              <td class="text-xs"></td>
            </tr>
          </table>
          
          <p class="text-xs mb-4">Other Remarks: <span id="otherRemarks">Family requires relocation due to unsafe living conditions</span></p>
          
          <!-- Signatures -->
          <div class="flex justify-between mb-4">
            <div>
              <div class="signature-line"></div>
              <p class="text-xs text-center">Household Head Signature</p>
            </div>
            <div>
              <p class="text-xs">Date Signed: <span id="signDate" class="underline">05/07/2025</span></p>
            </div>
            <div>
              <p class="text-xs">Validator: <span id="validatorName" class="underline">JOHN DOE</span></p>
            </div>
          </div>
          
          <p class="text-xs mb-4">All data furnished to the department will be treated with utmost confidentiality in accordance with Data Privacy Act of 2012.</p>
          
          <!-- Census Stub -->
          <div class="border-t-2 border-black pt-2">
            <h4 class="font-bold text-sm text-center">URBAN DEVELOPMENT AND HOUSING OFFICE CENSUS STUB</h4>
            <div class="dashed-line"></div>
            
            <p class="font-bold text-xs">Tag Number: <span id="stubTagNumber" class="font-normal underline">UDHO-2023-001234</span></p>
            <p class="font-bold text-xs">Date of Census: <span id="stubCensusDate" class="font-normal underline">05/07/2025</span></p>
            
            <table class="form-table mt-2 mb-1">
              <tr>
                <th class="text-xs">Signature Over Printed Name of Household Head</th>
                <th class="text-xs">Date Signed</th>
                <th class="text-xs">Barangay</th>
                <th class="text-xs">Zone</th>
              </tr>
              <tr>
                <td class="text-xs"></td>
                <td class="text-xs"></td>
                <td class="text-xs">Barangay 12</td>
                <td class="text-xs">Zone 5</td>
              </tr>
            </table>
            
            <table class="form-table">
              <tr>
                <th class="text-xs">Signature Over Printed Name of Validator</th>
                <th class="text-xs">Date Signed</th>
                <th class="text-xs"></th>
                <th class="text-xs"></th>
              </tr>
              <tr>
                <td class="text-xs"></td>
                <td class="text-xs"></td>
                <td class="text-xs"></td>
                <td class="text-xs"></td>
              </tr>
            </table>
            
            <p class="text-xs mt-2">This stub shall not be used for any other purpose or in connection of any other matters. Any use of this stub beyond its intended purpose for census is strictly prohibited.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Modal functions
    function openDocumentModal() {
      // In a real implementation, you would fetch data from your database here
      // For demonstration, we're using sample data
      fetchDocumentData();
      document.getElementById('documentModal').classList.remove('hidden');
    }
    
    function closeDocumentModal() {
      document.getElementById('documentModal').classList.add('hidden');
    }
    
     function printDocument() {
      // Ensure modal is visible before printing
      document.getElementById('documentModal').classList.remove('hidden');
      setTimeout(() => {
        window.print();
      }, 100); // slight delay to ensure rendering
    }
    
    function downloadPDF() {
      // In a real implementation, you would use a library like jsPDF or html2pdf
      alert('PDF download would be implemented here. In a real system, this would generate a PDF version of the form.');
    }
    
    // Fetch document data from database (simulated)
    function fetchDocumentData() {
      // This would be an API call to your backend in a real implementation
      // For now, we'll simulate data loading
      console.log("Fetching document data from database...");
      
      // Sample data - in a real app, this would come from your API
      const sampleData = {
        tagNumber: "UDHO-2025-001234",
        censusDate: "05/07/2025",
        udCode: "PSY-01",
        householdHead: {
          surname: "DELA CRUZ",
          firstName: "JUAN",
          middleName: "REYES",
          mi: "M",
          sex: "Male",
          age: "45",
          birthdate: "05/15/1980",
          civilStatus: "Married",
          pwd: true,
          soloParent: false
        },
        spouse: {
          surname: "DELA CRUZ",
          firstName: "MARIA",
          middleName: "SANTOS",
          mi: "R",
          sex: "Female",
          age: "42",
          birthdate: "08/22/1983",
          civilStatus: "Married",
          pwd: false,
          soloParent: true
        },
        address: {
          houseNo: "123",
          lotNo: "45",
          building: "-",
          block: "7",
          street: "M. Dela Cruz St.",
          barangay: "Barangay 12",
          city: "Pasay City",
          region: "NCR"
        },
        landStatus: {
          privateLand: true,
          governmentLand: false,
          lotOwner: false,
          tenure: "Renter (< 5 years)",
          ownerName: "JOSE REYES"
        },
        membership: {
          pagibig: true,
          sss: true,
          gsis: false,
          philhealth: true,
          aps: false,
          hoa: true,
          orgName: "Pasay Homeowners Association"
        },
        householdMembers: [
          {
            name: "JUAN DELA CRUZ",
            relationship: "Head",
            age: "45",
            sex: "Male",
            birthdate: "05/15/1980",
            education: "College Graduate"
          },
          {
            name: "MARIA DELA CRUZ",
            relationship: "Spouse",
            age: "42",
            sex: "Female",
            birthdate: "08/22/1983",
            education: "High School Graduate"
          }
        ],
        remarks: {
          shelterNeeds: "Shelter Provision",
          householdClassification: "Doubled Up HH",
          otherRemarks: "Family requires relocation due to unsafe living conditions"
        },
        validation: {
          signDate: "05/07/2025",
          validatorName: "JOHN DOE"
        }
      };
      
      // Populate the form with the data
      populateForm(sampleData);
    }
    
    // Populate form with data
    function populateForm(data) {
      // Basic info
      document.getElementById('tagNumber').textContent = data.tagNumber;
      document.getElementById('censusDate').textContent = data.censusDate;
      document.getElementById('udCode').textContent = data.udCode;
      document.getElementById('stubTagNumber').textContent = data.tagNumber;
      document.getElementById('stubCensusDate').textContent = data.censusDate;
      
      // Household Head
      const hh = data.householdHead;
      document.getElementById('hhSurname').textContent = hh.surname;
      document.getElementById('hhFirstName').textContent = hh.firstName;
      document.getElementById('hhMiddleName').textContent = hh.middleName;
      document.getElementById('hhMi').textContent = hh.mi;
      document.getElementById('hhSex').textContent = hh.sex;
      document.getElementById('hhAge').textContent = hh.age;
      document.getElementById('hhBirthdate').textContent = hh.birthdate;
      document.getElementById('hhCivilStatus').textContent = hh.civilStatus === "Married" ? "☑ Married" : "□ Married";
      document.getElementById('hhPwd').textContent = hh.pwd ? "☑ PWD" : "□ PWD";
      
      // Spouse
      const spouse = data.spouse;
      document.getElementById('spouseSurname').textContent = spouse.surname;
      document.getElementById('spouseFirstName').textContent = spouse.firstName;
      document.getElementById('spouseMiddleName').textContent = spouse.middleName;
      document.getElementById('spouseMi').textContent = spouse.mi;
      document.getElementById('spouseSex').textContent = spouse.sex;
      document.getElementById('spouseCivilStatus').textContent = spouse.civilStatus === "Married" ? "☑ Married" : "□ Married";
      document.getElementById('spouseSoloParent').textContent = spouse.soloParent ? "☑ Solo Parent" : "□ Solo Parent";
      
      // Address
      const addr = data.address;
      document.getElementById('houseNo').textContent = addr.houseNo;
      document.getElementById('lotNo').textContent = addr.lotNo;
      document.getElementById('building').textContent = addr.building;
      document.getElementById('block').textContent = addr.block;
      document.getElementById('street').textContent = addr.street;
      document.getElementById('barangay').textContent = addr.barangay;
      document.getElementById('city').textContent = addr.city;
      document.getElementById('region').textContent = addr.region;
      
      // Land Status
      document.getElementById('ownerName').textContent = data.landStatus.ownerName;
      
      // Membership
      document.getElementById('orgName').textContent = data.membership.orgName;
      
      // Household Members
      const membersTable = document.getElementById('householdMembers');
      membersTable.innerHTML = '';
      data.householdMembers.forEach(member => {
        membersTable.innerHTML += `
          <tr>
            <td class="text-xs">${member.name}</td>
            <td class="text-xs">${member.relationship}</td>
            <td class="text-xs">${member.age}</td>
            <td class="text-xs">${member.sex}</td>
            <td class="text-xs">${member.birthdate}</td>
            <td class="text-xs">${member.education}</td>
          </tr>
        `;
      });
      
      // Remarks
      document.getElementById('otherRemarks').textContent = data.remarks.otherRemarks;
      
      // Validation
      document.getElementById('signDate').textContent = data.validation.signDate;
      document.getElementById('validatorName').textContent = data.validation.validatorName;
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('documentModal');
      if (event.target === modal) {
        closeDocumentModal();
      }
    }
  </script>
</body>
</html>