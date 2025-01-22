<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

function sendResponse($status, $message, $data = null) {
    http_response_code($status === 'success' ? 200 : 400);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    // Get and validate POST data
    $json = file_get_contents('php://input');
    if (!$json) {
        sendResponse('error', 'No data received');
    }
    
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse('error', 'Invalid JSON data: ' . json_last_error_msg());
    }

    if (!isset($data['image_url']) || empty($data['image_url'])) {
        sendResponse('error', 'No image URL provided');
    }

    // API Configuration
    $api_key = 'BMRAIzayAMtMi667ioaHiU9082Bg7sd233A99';
    $model = 'V4';
    
    // Validate image URL and get content
    $image_content = @file_get_contents($data['image_url']);
    if ($image_content === false) {
        sendResponse('error', 'Failed to fetch image from provided URL');
    }
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    'inlineData' => [
                        'mimeType' => 'image/jpeg',
                        'data' => base64_encode($image_content)
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 1,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'text/plain',
            'modal' => 'bmraiv4.89.99',
            'service' => 'app.bmreducation.v4.service',
            'max_timing' => '300ms'
        ]
    ];

    // Initialize cURL with timeout and error handling
    $ch = curl_init('https://www.bmr.org.in/bmr/v4/models/brain_tumor/process?key=' . $api_key);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: BMR-API-Client/1.0'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response) {
        throw new Exception('Empty response received from API');
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response: ' . json_last_error_msg());
    }
    
    if ($httpCode !== 200) {
        $errorMessage = isset($result['error']['message']) 
            ? $result['error']['message'] 
            : 'API request failed with status code: ' . $httpCode;
        throw new Exception($errorMessage);
    }

    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Unexpected API response format');
    }

    $analysis = $result['candidates'][0]['content']['parts'][0]['text'];

    if (stripos($analysis, 'Invalid:') !== false) {
        sendResponse('error', 'The uploaded image is not a brain MRI scan.');
    }

    sendResponse('success', 'Analysis completed', [
        'analysis' => $analysis,
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    error_log("Analysis Error: " . $e->getMessage());
    sendResponse('error', 'An error occurred during analysis: ' . $e->getMessage());
}
?>
