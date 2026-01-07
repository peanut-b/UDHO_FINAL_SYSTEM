<?php
// File: barangay_map.php
// Description: Displays a KML file of barangay boundaries using Leaflet.js
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pasay Barangay Map Viewer</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        #map {
            width: 100%;
            height: 100vh;
        }
        .map-title {
            position: absolute;
            top: 10px;
            left: 50px;
            z-index: 1000;
            background: white;
            padding: 5px 10px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            font-family: Arial, sans-serif;
        }
        #loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        #error {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            background: #ffdddd;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            color: #ff0000;
            display: none;
        }
    </style>
</head>
<body>
    <div class="map-title">
        <h2>Pasay Barangay Boundary Map</h2>
    </div>
    <div id="loading">Loading map data...</div>
    <div id="error"></div>
    <div id="map"></div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    
    <!-- Using togeojson for KML parsing -->
    <script src="https://unpkg.com/@mapbox/togeojson@0.16.0/togeojson.js"></script>
    
    <script>
        // Initialize the map
        var map = L.map('map').setView([14.5378, 120.9969], 13); // Centered on Pasay
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Show loading message
        document.getElementById('loading').style.display = 'block';
        
        // Load KML file using toGeoJSON
        function loadKml(file) {
            fetch(file)
                .then(res => {
                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    return res.text();
                })
                .then(kmltext => {
                    // Hide loading message
                    document.getElementById('loading').style.display = 'none';
                    
                    // Parse KML to DOM
                    const parser = new DOMParser();
                    const kml = parser.parseFromString(kmltext, 'text/xml');
                    
                    // Check for parse errors
                    const parseErrors = kml.getElementsByTagName("parsererror");
                    if (parseErrors.length > 0) {
                        throw new Error("Invalid KML format: " + parseErrors[0].textContent);
                    }
                    
                    // Convert KML to GeoJSON
                    const geojson = toGeoJSON.kml(kml);
                    
                    // Create Leaflet layer from GeoJSON
                    const barangays = L.geoJSON(geojson, {
                        style: {
                            fillColor: '#3388ff',
                            weight: 2,
                            opacity: 1,
                            color: 'white',
                            dashArray: '3',
                            fillOpacity: 0.5
                        },
                        onEachFeature: function(feature, layer) {
                            // Add popup with feature properties
                            if (feature.properties) {
                                const name = feature.properties.name || 
                                           feature.properties.Name || 
                                           feature.properties.NAME ||
                                           'Barangay';
                                layer.bindPopup(`<b>${name}</b>`);
                            }
                        }
                    }).addTo(map);
                    
                    // Adjust map view to show the data
                    if (barangays.getBounds()) {
                        map.fitBounds(barangays.getBounds());
                    }
                })
                .catch(err => {
                    console.error('Error loading KML file:', err);
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('error').style.display = 'block';
                    document.getElementById('error').innerHTML = 
                        `<strong>Error loading map data:</strong><br>${err.message}<br>
                         <small>Check console for more details (F12 > Console)</small>`;
                });
        }
        
        // Load the KML file
        loadKml('/assets/Pasay_brgy.kml');
        
        // Add controls
        L.control.scale().addTo(map);
        
        // Add debug info
        console.log("Map initialized, loading KML from: /assets/Pasay_brgy.kml");
    </script>
</body>
</html>