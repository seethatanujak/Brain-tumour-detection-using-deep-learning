<?php
session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

date_default_timezone_set('Asia/Kolkata');
session_regenerate_id(true);

function sendResponse($status, $message, $data = null) {
    $response = [
        'status' => $status,
        'message' => $message,
        'data' => $data
    ];
    echo json_encode($response);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse('error', 'Invalid request method');
    }

    if (!isset($_FILES['image'])) {
        sendResponse('error', 'No file uploaded');
    }

    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        sendResponse('error', $errorMessages[$_FILES['image']['error']] ?? 'Unknown upload error');
    }

    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            sendResponse('error', 'Failed to create upload directory');
        }
    }

    $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $uniqueName = uniqid('img_', true) . '.' . $fileExtension;
    $uploadFile = $uploadDir . $uniqueName;

    $check = getimagesize($_FILES['image']['tmp_name']);
    if ($check === false) {
        sendResponse('error', 'File is not an image');
    }

    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        sendResponse('error', 'File size too large. Maximum size is 5MB');
    }
    
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($fileExtension, $allowedTypes)) {
        sendResponse('error', 'Only JPG, JPEG, PNG & GIF files are allowed');
    }
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $fullUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $uploadFile;
        
        sendResponse('success', 'File uploaded successfully', [
            'filename' => $uniqueName,
            'original_name' => htmlspecialchars($_FILES['image']['name']),
            'file_size' => $_FILES['image']['size'],
            'file_type' => $fileExtension,
            'file_path' => $uploadFile,
            'full_url' => $fullUrl
        ]);
    } else {
        sendResponse('error', 'Failed to move uploaded file');
    }

} catch (Exception $e) {
    error_log("Upload Error: " . $e->getMessage());
    sendResponse('error', 'An unexpected error occurred');
}
?>