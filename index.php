<?php

$videoDir = 'videos/';

// Ensure the videos directory exists
if (!is_dir($videoDir)) {
    mkdir($videoDir, 0777, true);
}

// Load .env file
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// API Key authentication
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$requiredApiKey = $_ENV['API_KEY'] ?? 'your_secret_api_key_here'; // Default for development

// Determine if the request is for an API endpoint that requires authentication
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME'];

// Remove script name from request path to get the clean API path
if (strpos($requestPath, $scriptName) === 0) {
    $cleanApiPath = substr($requestPath, strlen($scriptName));
} else {
    $cleanApiPath = $requestPath;
}
$cleanApiPath = trim($cleanApiPath, '/');
$apiPathSegments = explode('/', $cleanApiPath);

$isApiEndpoint = false;
if (!empty($apiPathSegments[0])) {
    $firstSegment = $apiPathSegments[0];
    if (in_array($firstSegment, ['list', 'upload', 'compress', 'upload_chunk', 'get_client_password', 'rendi_command_status'])) { // Added 'rendi_command_status'
        $isApiEndpoint = true;
    }
    // DELETE requests for videos also require authentication
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && ($firstSegment === 'video' || $firstSegment === 'processed')) {
        $isApiEndpoint = true;
    }
}

// Direct access to video files (original or processed) should not require API key
// This check is for URLs like /videos/myvideo.mp4 or /index.php/video/myvideo.mp4
$isDirectVideoAccess = (strpos($requestPath, '/videos/') !== false) ||
                       (strpos($requestPath, '/index.php/video/') !== false);

// If it's an API endpoint and not direct video access, check API key
if ($isApiEndpoint && $apiKey !== $requiredApiKey) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid API Key.']);
    exit();
}

// Allowed origins for CORS from .env
$allowedOrigins = [];
if (isset($_ENV['ALLOWED_ORIGINS'])) {
    $allowedOrigins = explode(',', $_ENV['ALLOWED_ORIGINS']);
    // Trim whitespace from each origin
    $allowedOrigins = array_map('trim', $allowedOrigins);
}

// Set CORS headers
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
    // Fallback for non-allowed origins or direct access, or if no Origin header is sent
    // You might want to restrict this further based on security requirements
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Handle preflight OPTIONS request
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use the cleaned API path segments for routing
$apiPath = $apiPathSegments;

// Helper function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

switch ($method) {
    case 'GET':
        if (empty($apiPath)) {
            // This part is for the HTML interface, not the API.
            // The API list endpoint will be handled by a specific path or header.
            // For now, the HTML is served directly.
            // If it's an API request for listing, it should be handled by a specific path.
            // For simplicity, the HTML part is at the root index.php.
            // The actual API calls will be made from the client-side JS.
            // This block should ideally not be reached by API calls.
        } elseif (isset($apiPath[0]) && $apiPath[0] === 'video' && isset($apiPath[1])) {
            // This endpoint is for direct streaming/downloading of locally stored videos.
            $filename = urldecode($apiPath[1]);
            $filePath = $videoDir . $filename;

            if (!file_exists($filePath)) {
                sendJsonResponse(['status' => 'error', 'message' => 'Video not found.'], 404);
            }

            // Determine if it's a download or stream request
            if (isset($_GET['action']) && $_GET['action'] === 'download') {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filePath));
                // Read and output in chunks to avoid memory exhaustion
                $handle = fopen($filePath, 'rb');
                if ($handle) {
                    while (!feof($handle)) {
                        echo fread($handle, 8192); // Read in 8KB chunks
                        ob_flush();
                        flush();
                    }
                    fclose($handle);
                }
                exit();
            } else {
                // Stream video
                $mimeType = mime_content_type($filePath);
                header('Content-Type: ' . $mimeType);
                header('Accept-Ranges: bytes');

                $file = @fopen($filePath, 'rb');
                if ($file) {
                    $fileSize = filesize($filePath);
                    $start = 0;
                    $end = $fileSize - 1;

                    if (isset($_SERVER['HTTP_RANGE'])) {
                        preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
                        $start = intval($matches[1]);
                        if (isset($matches[2]) && $matches[2] !== '') {
                            $end = intval($matches[2]);
                        }
                        header('HTTP/1.1 206 Partial Content');
                        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
                        header('Content-Length: ' . ($end - $start + 1)); // Set Content-Length for partial content
                    } else {
                        header('Content-Length: ' . $fileSize); // Set Content-Length for full content
                    }

                    $length = $end - $start + 1;
                    fseek($file, $start);
                    // Read and output in chunks to avoid memory exhaustion
                    $bufferSize = 8192; // 8KB buffer
                    while ($length > 0 && !feof($file)) {
                        $readSize = min($length, $bufferSize);
                        echo fread($file, $readSize);
                        $length -= $readSize;
                        ob_flush();
                        flush();
                    }
                    fclose($file);
                } else {
                    sendJsonResponse(['status' => 'error', 'message' => 'Could not open video file.'], 500);
                }
                exit();
            }
        } elseif (isset($apiPath[0]) && $apiPath[0] === 'list') { // API endpoint for listing
            $files = array_diff(scandir($videoDir), array('.', '..'));
            $videoList = [];
            foreach ($files as $file) {
                if (is_file($videoDir . $file)) {
                    $videoList[] = [
                        'name' => $file,
                        'size' => filesize($videoDir . $file),
                        'stream_url' => 'index.php/video/' . urlencode($file),
                        'download_url' => 'index.php/video/' . urlencode($file) . '?action=download',
                        'processed_resolutions' => [] // No processed resolutions without Rendi
                    ];
                }
            }
            sendJsonResponse(['status' => 'success', 'videos' => $videoList]);
        } elseif (isset($apiPath[0]) && $apiPath[0] === 'get_client_password') { // API endpoint to get client password
            $clientPassword = $_ENV['CLIENT_PASSWORD'] ?? 'your_client_password_here';
            sendJsonResponse(['status' => 'success', 'client_password' => $clientPassword]);
        }
        break;

    case 'POST':
        if (isset($apiPath[0]) && $apiPath[0] === 'upload_chunk' && isset($_FILES['video_chunk'])) { // API endpoint for chunked upload
            $chunk = $_FILES['video_chunk'];
            $filename = $_POST['filename'] ?? '';
            $fileSize = $_POST['fileSize'] ?? 0;
            $chunkIndex = $_POST['chunkIndex'] ?? 0;
            $totalChunks = $_POST['totalChunks'] ?? 0;

            if (empty($filename) || $fileSize == 0 || $totalChunks == 0) {
                sendJsonResponse(['status' => 'error', 'message' => 'Missing chunk metadata.'], 400);
            }

            $tempDir = $videoDir . 'temp_chunks/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $tempFilePath = $tempDir . $filename . '.part' . $chunkIndex;

            if (move_uploaded_file($chunk['tmp_name'], $tempFilePath)) {
                if ($chunkIndex == $totalChunks - 1) {
                    // Last chunk received, reassemble the file
                    $finalFilePath = $videoDir . basename($filename);
                    $outputFile = fopen($finalFilePath, 'wb');

                    if (!$outputFile) {
                        sendJsonResponse(['status' => 'error', 'message' => 'Failed to open final file for writing.'], 500);
                    }

                    for ($i = 0; $i < $totalChunks; $i++) {
                        $partFile = $tempDir . $filename . '.part' . $i;
                        if (file_exists($partFile)) {
                            $input = fopen($partFile, 'rb');
                            if ($input) {
                                while (!feof($input)) {
                                    fwrite($outputFile, fread($input, 8192)); // Read and write in 8KB blocks
                                }
                                fclose($input);
                                unlink($partFile); // Delete chunk after appending
                            } else {
                                fclose($outputFile);
                                sendJsonResponse(['status' => 'error', 'message' => 'Failed to open chunk for reading.'], 500);
                            }
                        } else {
                            fclose($outputFile);
                            sendJsonResponse(['status' => 'error', 'message' => 'Missing chunk ' . $i . '.'], 500);
                        }
                    }
                    fclose($outputFile);

                    // Clean up temp directory if empty
                    if (count(array_diff(scandir($tempDir), array('.', '..'))) === 0) {
                        rmdir($tempDir);
                    }

                    sendJsonResponse(['status' => 'success', 'message' => 'Video uploaded successfully.', 'filename' => basename($finalFilePath)]);

                } else {
                    sendJsonResponse(['status' => 'success', 'message' => 'Chunk ' . ($chunkIndex + 1) . ' of ' . $totalChunks . ' uploaded.']);
                }
            } else {
                sendJsonResponse(['status' => 'error', 'message' => 'Failed to upload chunk.'], 500);
            }
        } else {
            sendJsonResponse(['status' => 'error', 'message' => 'Invalid request for POST.'], 400);
        }
        break;

    case 'DELETE':
        if (isset($apiPath[0]) && $apiPath[0] === 'video' && isset($apiPath[1])) {
            $filename = urldecode($apiPath[1]);
            $filePath = $videoDir . $filename;

            if (!file_exists($filePath)) {
                sendJsonResponse(['status' => 'error', 'message' => 'Video not found.'], 404);
            }

            if (unlink($filePath)) {
                // Remove any processed video metadata directory if it exists (though it shouldn't be created now)
                $originalFilenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                $processedDir = $videoDir . 'processed/' . $originalFilenameWithoutExt . '/';
                if (is_dir($processedDir)) {
                    $filesToDelete = array_diff(scandir($processedDir), array('.', '..'));
                    foreach ($filesToDelete as $file) {
                        unlink($processedDir . $file);
                    }
                    rmdir($processedDir);
                }
                sendJsonResponse(['status' => 'success', 'message' => 'Video deleted successfully.', 'filename' => $filename]);
            } else {
                sendJsonResponse(['status' => 'error', 'message' => 'Failed to delete video.'], 500);
            }
        } else {
            sendJsonResponse(['status' => 'error', 'message' => 'Invalid request for DELETE.'], 400);
        }
        break;

    default:
        sendJsonResponse(['status' => 'error', 'message' => 'Method not allowed.'], 405);
        break;
}

// API Status endpoint
if (isset($apiPath[0]) && $apiPath[0] === 'status') {
    sendJsonResponse(['status' => 'success', 'message' => 'API is operational.']);
    exit();
}

?>
