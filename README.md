# Video API

This is a simple PHP-based API for managing video files. It allows you to upload, list, download, stream, and delete videos. All videos are stored in the `videos/` directory.

## Setup

1.  **Prerequisites**:
    *   PHP (version 7.4 or higher recommended)
    *   A web server (e.g., Apache, Nginx, or PHP's built-in development server)

2.  **Installation**:
    *   Ensure you have PHP installed and added to your system's PATH.
    *   Place `index.php`, `client.php`, and the `videos/` directory in your web server's document root.
    *   Ensure the `videos/` directory has write permissions for the web server. On Linux/macOS, you might use `chmod -R 777 videos/` (use with caution in production) or `chown -R www-data:www-data videos/` (replace `www-data` with your web server user). On Windows, you'll need to adjust folder security permissions via the file explorer.
    *   **Client API Key**: Open the `.env` file and replace `your_secret_api_key_here` with your desired API key. This key will be required for all API interactions with your local PHP server.
    *   **Client Password**: Open the `.env` file and replace `your_client_password_here` with your desired password for accessing the client interface.

3.  **Running with PHP's Built-in Server (for development)**:
    *   Navigate to the project directory in your terminal.
    *   Run the command: `php -S localhost:8000`
    *   Open your browser and go to `http://localhost:8000/client.php` for the full client application.

## API Endpoints

All API interactions are handled through `index.php`. All API requests (except for direct video streaming/downloading and the initial load of `client.php` itself) require an `X-API-Key` header with the secret key defined in your `.env` file.

### 1. List Videos

*   **URL**: `/index.php/list`
*   **Method**: `GET`
*   **Description**: Retrieves a list of all uploaded videos.
*   **Headers**: `X-API-Key: your_secret_api_key_here`
*   **Response (JSON)**:
    ```json
    {
        "status": "success",
        "videos": [
            {
                "name": "video1.mp4",
                "size": 12345678,
                "stream_url": "index.php/video/video1.mp4",
                "download_url": "index.php/video/video1.mp4?action=download"
            }
        ]
    }
    ```

### 2. Upload Video (Chunked Upload)

*   **URL**: `/index.php/upload_chunk`
*   **Method**: `POST`
*   **Description**: Uploads a video file in chunks to your server.
*   **Headers**: `X-API-Key: your_secret_api_key_here`
*   **Request (multipart/form-data)**:
    *   Field Name: `video_chunk` (the binary data of the current chunk)
    *   Field Name: `filename` (original name of the file)
    *   Field Name: `fileSize` (total size of the file in bytes)
    *   Field Name: `chunkIndex` (0-based index of the current chunk)
    *   Field Name: `totalChunks` (total number of chunks for the file)
*   **Response (JSON)**:
    *   For intermediate chunks:
        ```json
        {
            "status": "success",
            "message": "Chunk X of Y uploaded."
        }
        ```
    *   For the final chunk:
        ```json
        {
            "status": "success",
            "message": "Video uploaded successfully.",
            "filename": "uploaded_video.mp4"
        }
        ```
    *   Error Response:
        ```json
        {
            "status": "error",
            "message": "Missing chunk metadata."
        }
        ```
        Or:
        ```json
        {
            "status": "error",
            "message": "Failed to upload chunk."
        }
        ```

### 3. API Status

*   **URL**: `/index.php/status`
*   **Method**: `GET`
*   **Description**: Provides a basic status check for your local API.
*   **Headers**: `X-API-Key: your_secret_api_key_here`
*   **Response (JSON)**:
    ```json
    {
        "status": "success",
        "message": "API is operational."
    }
    ```

### 4. Stream Video

*   **URL**: `/index.php/video/{filename}`
*   **Method**: `GET`
*   **Description**: Streams a video file directly from your server in the browser. **Does NOT require API Key.**
*   **Example**: `http://localhost:8000/index.php/video/my_awesome_video.mp4`

### 5. Download Video

*   **URL**: `/index.php/video/{filename}?action=download`
*   **Method**: `GET`
*   **Description**: Downloads a video file directly from your server. **Does NOT require API Key.**
*   **Example**: `http://localhost:8000/index.php/video/my_awesome_video.mp4?action=download`

### 6. Delete Video

*   **URL**: `/index.php/video/{filename}`
*   **Method**: `DELETE`
*   **Description**: Deletes a video file from your server.
*   **Headers**: `X-API-Key: your_secret_api_key_here`
*   **Response (JSON)**:
    ```json
    {
        "status": "success",
        "message": "Video deleted successfully.",
        "filename": "deleted_video.mp4"
    }
    ```
    *   Error Response:
        ```json
        {
            "status": "error",
            "message": "Video not found."
        }
    ```

## CORS Policy

This API is configured to allow all cross-origin requests (`Access-Control-Allow-Origin: *`) for `GET`, `POST`, `DELETE`, and `OPTIONS` methods. This means you can access the API from any domain.

## Frontend Interfaces

*   **Full Client (`client.php`)**: A comprehensive client application with API key management, client password protection, and an integrated video player.
    *   **Client Password Protection**: Requires a password (set in `.env`) to access the client interface. The password is fetched securely via an API endpoint.
    *   Allows setting and saving the API key in local storage.
    *   Interacts with all API endpoints using the provided API key.
    *   Provides a user-friendly interface for all video management operations.
    *   **Chunked Upload with Progress**: The upload form now supports chunked uploads, displaying a progress bar to indicate the upload status.
    *   **Video Player**: The video player allows streaming of uploaded videos.
