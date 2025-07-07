<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video API Client</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        form { margin-bottom: 25px; padding: 20px; border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="text"], input[type="file"], button {
            padding: 12px;
            margin-right: 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="text"] { width: calc(100% - 150px); }
        input[type="file"] { width: auto; }
        button {
            background-color: #007bff;
            color: white;
            cursor: pointer;
            border: none;
            transition: background-color 0.3s ease;
            min-width: 120px;
        }
        button:hover { background-color: #0056b3; }
        ul { list-style: none; padding: 0; }
        li {
            background: #fff;
            margin-bottom: 12px;
            padding: 15px;
            border: 1px solid #e9e9e9;
            border-radius: 6px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 5px rgba(0,0,0,0.05);
        }
        li span { flex-grow: 1; margin-right: 10px; font-size: 1.1em; color: #444; }
        li .actions { display: flex; gap: 10px; }
        li a { text-decoration: none; color: #007bff; padding: 8px 12px; border: 1px solid #007bff; border-radius: 4px; transition: all 0.3s ease; }
        li a:hover { background-color: #007bff; color: white; }
        .delete-btn { background-color: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; transition: background-color 0.3s ease; }
        .delete-btn:hover { background-color: #c82333; }
        .message { margin-top: 25px; padding: 15px; border-radius: 5px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .api-key-section { margin-bottom: 20px; }
        .api-key-section input { width: 300px; }
        .resolution-btn {
            background-color: #6c757d;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            border: none;
            transition: background-color 0.3s ease;
        }
        .resolution-btn:hover {
            background-color: #5a6268;
        }
        .resolution-btn.active {
            background-color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Video API Client</h1>

        <div id="message" class="message" style="display:none;"></div>

        <div id="loginSection">
            <h2>Client Login</h2>
            <div style="margin-bottom: 15px;">
                <label for="clientPasswordInput">Password:</label>
                <input type="password" id="clientPasswordInput" placeholder="Enter client password">
                <button onclick="loginClient()">Login</button>
            </div>
        </div>

        <div id="mainContent" style="display:none;">
            <div class="api-key-section">
                <label for="apiKeyInput">API Key:</label>
                <input type="text" id="apiKeyInput" placeholder="Enter your API Key" value="your_secret_api_key_here">
                <button onclick="saveApiKey()">Save API Key</button>
            </div>

            <h2>Upload Video</h2>
            <form id="uploadForm" enctype="multipart/form-data">
            <input type="file" name="video" id="videoFile" accept="video/*" required>
            <button type="submit">Upload</button>
            <div id="uploadProgressContainer" style="display:none; margin-top: 15px;">
                <div style="width: 100%; background-color: #e0e0e0; border-radius: 5px;">
                    <div id="uploadProgressBar" style="width: 0%; height: 25px; background-color: #4CAF50; text-align: center; line-height: 25px; color: white; border-radius: 5px;">0%</div>
                </div>
            </div>
        </form>

        <h2>Video Player</h2>
        <div class="video-player-container">
            <video id="videoPlayer" controls preload="auto" width="100%" height="auto" style="background-color: black;"></video>
            <p id="nowPlaying">No video selected.</p>
            <div id="resolutionSelector" style="margin-top: 10px;">
                <!-- Resolution buttons will be added here -->
            </div>
        </div>

        <h2>Video List</h2>
        <ul id="videoList">
            <!-- Videos will be loaded here -->
        </ul>
    </div>

    <script>
        const API_BASE_URL = 'index.php'; // Assuming index.php is in the same directory
        const uploadForm = document.getElementById('uploadForm');
        const videoListElement = document.getElementById('videoList');
        const messageElement = document.getElementById('message');
        const apiKeyInput = document.getElementById('apiKeyInput');
        const videoPlayer = document.getElementById('videoPlayer');
        const nowPlaying = document.getElementById('nowPlaying');
        const resolutionSelector = document.getElementById('resolutionSelector');
        const loginSection = document.getElementById('loginSection');
        const mainContent = document.getElementById('mainContent');
        const clientPasswordInput = document.getElementById('clientPasswordInput');

        let apiKey = localStorage.getItem('api_key') || 'your_secret_api_key_here';
        apiKeyInput.value = apiKey;

        let correctClientPassword = ''; // This will be fetched from the server

        async function fetchClientPassword() {
            try {
                const response = await fetch(`${API_BASE_URL}/get_client_password`, {
                    headers: {
                        'X-API-Key': apiKey
                    }
                });
                const data = await response.json();
                if (data.status === 'success') {
                    correctClientPassword = data.client_password;
                } else {
                    console.error('Failed to fetch client password:', data.message);
                    showMessage('Failed to fetch client password. Check API Key.', 'error');
                }
            } catch (error) {
                console.error('Error fetching client password:', error);
                showMessage('Error fetching client password. Check console.', 'error');
            }
        }

        function saveApiKey() {
            apiKey = apiKeyInput.value;
            localStorage.setItem('api_key', apiKey);
            showMessage('API Key saved!', 'success');
            fetchVideos(); // Refresh list with new API key
            fetchClientPassword(); // Re-fetch client password with new API key
        }

        async function loginClient() {
            if (!correctClientPassword) {
                await fetchClientPassword(); // Ensure password is fetched before attempting login
            }

            if (clientPasswordInput.value === correctClientPassword) {
                sessionStorage.setItem('client_authenticated', 'true');
                loginSection.style.display = 'none';
                mainContent.style.display = 'block';
                showMessage('Login successful!', 'success');
                fetchVideos();
            } else {
                showMessage('Invalid client password.', 'error');
            }
        }

        function checkAuthentication() {
            if (sessionStorage.getItem('client_authenticated') === 'true') {
                loginSection.style.display = 'none';
                mainContent.style.display = 'block';
                fetchVideos();
            } else {
                loginSection.style.display = 'block';
                mainContent.style.display = 'none';
                fetchClientPassword(); // Fetch password on page load if not authenticated
            }
        }

        // Initial check on page load
        checkAuthentication();

        function showMessage(msg, type) {
            messageElement.textContent = msg;
            messageElement.className = `message ${type}`;
            messageElement.style.display = 'block';
            setTimeout(() => {
                messageElement.style.display = 'none';
            }, 5000);
        }

        async function fetchVideos() {
            try {
                const response = await fetch(`${API_BASE_URL}/list`, {
                    headers: {
                        'X-API-Key': apiKey
                    }
                });
                const data = await response.json();
                videoListElement.innerHTML = '';
                if (data.status === 'success' && data.videos.length > 0) {
                    data.videos.forEach(video => {
                        const li = document.createElement('li');
                        li.innerHTML = `
                            <span>${video.name} (${(video.size / (1024 * 1024)).toFixed(2)} MB)</span>
                            <div class="actions">
                                <button class="stream-btn" data-video-name="${video.name}" data-stream-url="${video.stream_url}" data-original="true">Stream</button>
                                <a href="${video.download_url}" download="${video.name}" class="download-link">Download</a>
                                <button class="delete-btn" data-filename="${video.name}">Delete</button>
                            </div>
                        `;
                        videoListElement.appendChild(li);
                    });
                } else {
                    videoListElement.innerHTML = '<li>No videos found.</li>';
                }
            } catch (error) {
                console.error('Error fetching videos:', error);
                showMessage('Failed to load videos. Check console for details and ensure API Key is correct.', 'error');
            }
        }

        // Helper function to get filename without extension
        function pathinfo(path, type) {
            const basename = path.split('/').pop();
            const dotIndex = basename.lastIndexOf('.');
            if (dotIndex === -1) {
                return type === 'filename' ? basename : '';
            }
            const filename = basename.substring(0, dotIndex);
            const extension = basename.substring(dotIndex + 1);
            if (type === 'filename') return filename;
            if (type === 'extension') return extension;
            return basename; // Default
        }

        const videoFile = document.getElementById('videoFile');
        const uploadProgressContainer = document.getElementById('uploadProgressContainer');
        const uploadProgressBar = document.getElementById('uploadProgressBar');

        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const file = videoFile.files[0];
            if (!file) {
                showMessage('Please select a file to upload.', 'error');
                return;
            }

            const chunkSize = 1024 * 1024 * 5; // 5MB chunks
            const totalChunks = Math.ceil(file.size / chunkSize);
            let currentChunk = 0;

            uploadProgressContainer.style.display = 'block';
            uploadProgressBar.style.width = '0%';
            uploadProgressBar.textContent = '0%';

            showMessage('Starting upload...', 'success');

            while (currentChunk < totalChunks) {
                const offset = currentChunk * chunkSize;
                const chunk = file.slice(offset, offset + chunkSize);

                const formData = new FormData();
                formData.append('video_chunk', chunk);
                formData.append('filename', file.name);
                formData.append('fileSize', file.size);
                formData.append('chunkIndex', currentChunk);
                formData.append('totalChunks', totalChunks);

                try {
                    const response = await fetch(`${API_BASE_URL}/upload_chunk`, {
                        method: 'POST',
                        headers: {
                            'X-API-Key': apiKey
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        const progress = ((currentChunk + 1) / totalChunks) * 100;
                        uploadProgressBar.style.width = `${progress}%`;
                        uploadProgressBar.textContent = `${Math.round(progress)}%`;

                        if (currentChunk + 1 === totalChunks) {
                            showMessage(data.message, 'success');
                            uploadForm.reset();
                            uploadProgressContainer.style.display = 'none';
                            fetchVideos();
                        }
                    } else {
                        showMessage(data.message, 'error');
                        uploadProgressContainer.style.display = 'none';
                        break; // Stop upload on error
                    }
                } catch (error) {
                    console.error('Error uploading chunk:', error);
                    showMessage('An error occurred during chunk upload. Check console for details.', 'error');
                    uploadProgressContainer.style.display = 'none';
                    break; // Stop upload on error
                }
                currentChunk++;
            }
        });

        videoListElement.addEventListener('click', async (e) => {
            if (e.target.classList.contains('delete-btn')) {
                const filename = e.target.dataset.filename;
                if (confirm(`Are you sure you want to delete "${filename}"?`)) {
                    try {
                        const response = await fetch(`${API_BASE_URL}/video/${encodeURIComponent(filename)}`, {
                            method: 'DELETE',
                            headers: {
                                'X-API-Key': apiKey
                            }
                        });
                        const data = await response.json();
                        if (data.status === 'success') {
                            showMessage(data.message, 'success');
                            fetchVideos();
                        } else {
                            showMessage(data.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error deleting video:', error);
                        showMessage('An error occurred during deletion. Check console for details.', 'error');
                    }
                }
            } else if (e.target.classList.contains('stream-btn')) {
                const streamUrl = e.target.dataset.streamUrl;
                const videoName = e.target.dataset.videoName; // Original video name

                videoPlayer.src = streamUrl;
                videoPlayer.load();
                videoPlayer.play();
                nowPlaying.textContent = `Now Playing: ${videoName}`;

                // Populate resolution selector - now only shows original
                resolutionSelector.innerHTML = '';
                const originalBtn = document.createElement('button');
                originalBtn.textContent = 'Original';
                originalBtn.classList.add('resolution-btn');
                originalBtn.classList.add('active'); // Original is always active now
                originalBtn.onclick = () => {
                    videoPlayer.src = streamUrl; // Already playing original
                    videoPlayer.load();
                    videoPlayer.play();
                    nowPlaying.textContent = `Now Playing: ${videoName} (Original)`;
                    updateResolutionButtons(originalBtn);
                };
                resolutionSelector.appendChild(originalBtn);
            }
        });

        function updateResolutionButtons(activeButton) {
            document.querySelectorAll('.resolution-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            activeButton.classList.add('active');
        }

    </script>
</body>
</html>
