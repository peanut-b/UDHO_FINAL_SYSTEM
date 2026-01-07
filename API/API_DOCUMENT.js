// Replace the documentData and related functions in your HTML with these API calls

// Get all documents
async function fetchDocuments() {
  try {
    const response = await fetch('http://localhost:3001/api/documents', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('authToken')}`
      }
    });
    if (!response.ok) throw new Error('Failed to fetch documents');
    return await response.json();
  } catch (error) {
    console.error('Error fetching documents:', error);
    return [];
  }
}

// Get document details
async function fetchDocumentDetails(controlNo) {
  try {
    const response = await fetch(`http://localhost:3001/api/documents/${controlNo}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('authToken')}`
      }
    });
    if (!response.ok) throw new Error('Failed to fetch document details');
    return await response.json();
  } catch (error) {
    console.error('Error fetching document details:', error);
    return null;
  }
}

// Add a new document
async function addDocument(documentData) {
  try {
    const response = await fetch('http://localhost:3001/api/documents', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('authToken')}`
      },
      body: JSON.stringify(documentData)
    });
    if (!response.ok) throw new Error('Failed to add document');
    return await response.json();
  } catch (error) {
    console.error('Error adding document:', error);
    return null;
  }
}

// Add an action to a document
async function addDocumentAction(controlNo, actionData) {
  try {
    const response = await fetch(`http://localhost:3001/api/documents/${controlNo}/actions`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('authToken')}`
      },
      body: JSON.stringify(actionData)
    });
    if (!response.ok) throw new Error('Failed to add action');
    return await response.json();
  } catch (error) {
    console.error('Error adding action:', error);
    return null;
  }
}

// Update your existing functions to use these API calls
async function showDocumentDetails(controlNo) {
  const doc = await fetchDocumentDetails(controlNo);
  if (!doc) {
    alert('Failed to load document details');
    return;
  }

  // Update the UI with the document details
  document.getElementById('docControlNo').textContent = doc.controlNo;
  document.getElementById('docType').textContent = doc.docType;
  document.getElementById('docDirection').textContent = doc.direction;
  document.getElementById('docPriority').textContent = doc.priority;
  document.getElementById('docReceived').textContent = doc.received || 'N/A';
  document.getElementById('docReleased').textContent = doc.released || 'N/A';
  document.getElementById('docSubject').textContent = doc.subject;

  updateActionLogTable(doc.actions);

  document.getElementById('documentDetailsPanel').style.display = 'block';
  document.getElementById('docBackdrop').style.display = 'block';
}

// Update your form submission handlers to use the API
document.querySelector('#addActionForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const actionData = {
    date: formData.get('date'),
    from: formData.get('from'),
    to: formData.get('to'),
    requiredAction: formData.get('requiredAction'),
    dueDate: formData.get('dueDate'),
    actionTaken: formData.get('actionTaken')
  };

  const controlNo = document.getElementById('docControlNo').textContent;
  const result = await addDocumentAction(controlNo, actionData);
  
  if (result) {
    alert('Action added successfully');
    // Refresh the action log
    const doc = await fetchDocumentDetails(controlNo);
    updateActionLogTable(doc.actions);
    e.target.reset();
  } else {
    alert('Failed to add action');
  }
});