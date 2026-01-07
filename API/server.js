// In your main server file (e.g., server.js or app.js)
const express = require('express');
const apiLogin = require('./API_LOGIN');
const apiDocument = require('./API_DOCUMENT');

const app = express();

app.use(bodyParser.json());
app.use('/api', apiLogin);
app.use('/api', apiDocument);

// ... rest of your server setup