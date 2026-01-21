<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf_token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Location Tracker</title>
    <style>
        body,html {
            height: 100%;
            margin: 0;
        }

        #map {
            height: calc(100% - 60px);
        }

        header{
            padding: 15px;
            background: #f0f0f0;
            font-size: 1.2rem;
        }

        .info {
            position: absolute;
            top: 70px;
            right: 10px;
            background: white;
            padding: 10px;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            z-index: 2000;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #error {
            display: none;
        }
    </style>
</head>
<body>
    <header>Real-Time Location Tracker</header>

    <div id="error"></div>

    <div id="map"></div>

    <div class="info">
        <strong>Active</strong><span id="active-users">0</span><br/>
        <strong>You</strong><span id="your-location">0</span><br/>
        <strong>Status</strong><span id="status">0</span><br/>
    </div>

    <div class="loading">
        <div class="spinner"></div>
        <div>Initializing map...</div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" integrity="sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script>
        window.REVERB_KEY = "{{ env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY')) }}";
        window.REVERB_HOST = "{{ env('VITE_REVERB_HOST', env('REVERB_HOST')) }}";
        window.REVERB_PORT = "{{ env('VITE_REVERB_PORT', env('REVERB_PORT')) }}";
        window.REVERB_SCHEME = "{{ env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'https')) }}";
        window.sessionId = '{{ session()->getId() }}';
    </script>

    @vite(['resources/js/app.js'])

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Hide loading spinner
            document.querySelector('.loading').style.display = 'none';
            
            const map = L.map('map').setView([0, 0], 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            const markers = {};

            const updateMarker = (id, lat, lng) => {
                const isMe = id === window.sessionId;
                
                if(!markers[id]){
                   const color = isMe ? '#667eea' : '#10b981';
                   const icon = L.icon({
                    html: `<div style="background: ${color}; width: 18px; height: 18px; display: block; border-radius: 50%; border: 3px solid #ffffff; box-shadow: 0 1px 4px #000;"></div>`,
                    iconSize: [18, 18],
                    iconAnchor: [9, 9],
                   });
                   markers[id] = L.marker([lat, lng], {icon}).addTo(map)
                   .bindPopup(`${isMe ? 'You' : 'User'}<br>${lat.toFixed(4)}, ${lng.toFixed(4)}`);
                } else {
                    markers[id].setLatLng([lat, lng]);
                    markers[id].getPopup().setContent(`${isMe ? 'You' : 'User'}<br>${lat.toFixed(4)}, ${lng.toFixed(4)}`);
                }

                document.getElementById('active-users').textContent = Object.keys(markers).length;

                if(isMe) {
                    map.setView([lat, lng], 16);
                    document.getElementById('your-location').textContent = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                }
            };

            const showError = (msg) => {
                const e = document.getElementById('error');
                e.textContent = msg;
                e.style.display = 'block';
                e.style.background = '#fee';
                e.style.color = '#c33';
                e.style.padding = '10px';
                e.style.margin = '10px';
                e.style.borderRadius = '4px';
                setTimeout(() => e.style.display = 'none', 5000);
            };

            const sendLocation = (lat, lng) => {
                fetch('{{ route('location.update') }}', {
                    method : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf_token"]').content
                    },
                    body: JSON.stringify({ latitude: lat, longitude: lng })
                }).then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                }).then(data => {
                    console.log('Location sent successfully:', data);
                }).catch(error => {
                    console.error('Error sending location:', error);
                    showError('Failed to send location to server.');
                });
            }

            if(navigator.geolocation) {
                document.getElementById('status').textContent = 'Requesting location...';
                navigator.geolocation.watchPosition(
                    p => {
                        const lat = p.coords.latitude;
                        const lng = p.coords.longitude;
                        console.log('Got location:', lat, lng);
                        
                        sendLocation(lat, lng);
                        updateMarker(window.sessionId, lat, lng);
                        document.getElementById('status').textContent = 'Tracking';
                    },
                    error => {
                        console.error('Geolocation error:', error);
                        showError('Unable to retrieve your location. Please enable location access.');
                        document.getElementById('status').textContent = 'Denied';
                    },
                    { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 }
                );
            } else {
                showError('Geolocation is not supported by this browser.');
                document.getElementById('status').textContent = 'Not supported';
            }

            // Set up real-time updates
            if(window.Echo){
                console.log('Echo is available, setting up real-time updates');
                window.Echo.channel('location-tracking')
                .listen('.location.updated', e => {
                    console.log('Received location update:', e);
                    updateMarker(e.userId, e.latitude, e.longitude);
                });
            } else {
                console.warn('Echo is not available');
                showError('Real-time updates are not available.');
            }
        });
    </script>
</body>
</html>