
<?php
/**
 * DIANE -> CLOAK (Gradio) integration endpoint.
 * Temporary connection test. Does not modify the existing webcam Try-On.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($status, $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function is_sequential_array($value) {
    if (!is_array($value)) return false;
    if (count($value) === 0) return true;

    return array_keys($value) === range(0, count($value) - 1);
}

function cloak_request($url, $method = 'GET', $body = null, $headers = array(), $timeout = 240) {
    $ch = curl_init($url);

    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false
    );

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $options);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Could not contact CLOAK: ' . $error);
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException(
            'CLOAK returned HTTP ' . $status . ': ' . substr($raw, 0, 500)
        );
    }

    return array($raw, $status);
}

/**
 * Find a Gradio uploaded file path in different response formats.
 */
function find_gradio_uploaded_path($value, $depth = 0) {
    if ($depth > 7) return null;

    if (is_string($value)) {
        $value = trim($value);

        if ($value === '') return null;

        // Gradio may return a path directly.
        if (strpos($value, '/') !== false ||
            strpos($value, '\\') !== false) {
            return $value;
        }

        return null;
    }

    if (!is_array($value)) return null;

    if (isset($value['path']) && is_string($value['path'])) {
        if (trim($value['path']) !== '') {
            return trim($value['path']);
        }
    }

    foreach (array('files', 'data', 'file', 'value') as $key) {
        if (isset($value[$key])) {
            $found = find_gradio_uploaded_path(
                $value[$key],
                $depth + 1
            );

            if ($found !== null) return $found;
        }
    }

    foreach ($value as $item) {
        $found = find_gradio_uploaded_path($item, $depth + 1);

        if ($found !== null) return $found;
    }

    return null;
}

/**
 * Upload an image to CLOAK's Gradio upload endpoint.
 */
function upload_to_cloak($base, $field) {
    if (!isset($_FILES[$field])) {
        throw new RuntimeException('Missing upload: ' . $field);
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $field . '.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded file: ' . $field);
    }

    if ($file['size'] > 12 * 1024 * 1024) {
        throw new RuntimeException('Each image must be 12 MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowed = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    );

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Please upload JPG, PNG, or WEBP images only.');
    }

    $upload = array(
        'files' => new CURLFile(
            $file['tmp_name'],
            $mime,
            'diane-' . $field . '.' . $allowed[$mime]
        )
    );

    list($raw) = cloak_request(
        $base . '/gradio_api/upload',
        'POST',
        $upload,
        array(),
        120
    );

    $decoded = json_decode($raw, true);
    $path = find_gradio_uploaded_path($decoded);

    if (!$path) {
        $excerpt = trim(preg_replace('/\s+/', ' ', $raw));

        throw new RuntimeException(
            'Could not read the uploaded file path from CLOAK. ' .
            'Upload response: ' . substr($excerpt, 0, 700)
        );
    }

    return array(
        'path' => $path,
        'meta' => array('_type' => 'gradio.FileData')
    );
}

/* Only accept POST requests. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, array(
        'success' => false,
        'error' => 'POST required.'
    ));
}

/* Check PHP extensions. */
if (!extension_loaded('curl')) {
    respond(500, array(
        'success' => false,
        'error' => 'PHP cURL is not enabled in XAMPP.'
    ));
}

if (!class_exists('finfo')) {
    respond(500, array(
        'success' => false,
        'error' => 'PHP Fileinfo extension is not enabled.'
    ));
}

/*
 * Temporary Colab URL.
 * Update this if Colab generates a new public URL.
 */
$cloakBase = rtrim(
    getenv('CLOAK_API_URL')
        ? getenv('CLOAK_API_URL')
        : 'https://bb74b0a5cfebfb152a.gradio.live',
    '/'
);

try {
    $type = strtolower(trim(
        isset($_POST['garment_type'])
            ? $_POST['garment_type']
            : 'top'
    ));

    if (!in_array($type, array('top', 'bottom', 'dress'), true)) {
        throw new RuntimeException('Invalid garment type.');
    }

    // Upload the customer's photo and selected product.
    $person = upload_to_cloak($cloakBase, 'person');
    $garment = upload_to_cloak($cloakBase, 'garment');

    // Prepare the CLOAK inputs.
    $mode = ($type === 'dress')
        ? 'Dress / gown'
        : 'Top + bottom';

    $top = ($type === 'top') ? $garment : null;
    $bottom = ($type === 'bottom') ? $garment : null;
    $dress = ($type === 'dress') ? $garment : null;

    $data = array(
        $person,
        $mode,
        $top,
        $bottom,
        $dress,
        'Balanced',
        42,
        'All'
    );

    // Start CLOAK generation.
    list($raw) = cloak_request(
        $cloakBase . '/gradio_api/call/run_tryon',
        'POST',
        json_encode(
            array('data' => $data),
            JSON_UNESCAPED_SLASHES
        ),
        array('Content-Type: application/json'),
        30
    );
    $start = json_decode($raw, true);

    $eventId = isset($start['event_id'])
        ? $start['event_id']
        : null;

    if (!$eventId) {
        throw new RuntimeException(
            'CLOAK did not return an event ID. Response: ' .
            substr($raw, 0, 500)
        );
    }

    // Wait for the generated result.
    list($stream) = cloak_request(
        $cloakBase . '/gradio_api/call/run_tryon/' . rawurlencode($eventId),
        'GET',
        null,
        array('Accept: text/event-stream'),
        300
    );

    $resultPayload = null;

    $lines = preg_split('/\r?\n/', $stream);

    foreach ($lines as $line) {
        if (strpos($line, 'data:') !== 0) continue;

        $candidate = json_decode(trim(substr($line, 5)), true);

        if (!is_array($candidate)) continue;

        if (isset($candidate['msg']) &&
            $candidate['msg'] === 'process_completed') {

            if (isset($candidate['success']) &&
                $candidate['success'] === false) {
                throw new RuntimeException(
                    isset($candidate['output']['error'])
                        ? $candidate['output']['error']
                        : 'CLOAK generation failed.'
                );
            }

            $resultPayload = isset($candidate['output']['data'])
                ? $candidate['output']['data']
                : null;

        } elseif (is_sequential_array($candidate) &&
                  count($candidate) > 0) {
            $resultPayload = $candidate;
        }
    }

    if (!is_array($resultPayload) ||
        !isset($resultPayload[0]) ||
        !is_array($resultPayload[0])) {
        throw new RuntimeException(
            'CLOAK finished without a readable result image.'
        );
    }

    $image = $resultPayload[0];

    $resultUrl = isset($image['url'])
        ? $image['url']
        : null;

    if ($resultUrl && substr($resultUrl, 0, 1) === '/') {
        $resultUrl = $cloakBase . $resultUrl;
    }

    if (!$resultUrl && !empty($image['path'])) {
        $resultUrl = $cloakBase .
            '/gradio_api/file=' .
            rawurlencode($image['path']);
    }

    if (!$resultUrl) {
        throw new RuntimeException(
            'CLOAK returned a result without an image URL.'
        );
    }

    /*
     * Save the generated image on the DIANE server when possible.
     * This avoids relying on the browser to fetch a temporary Gradio URL
     * later (for example, when the customer downloads the result).
     */
    $localResultUrl = null;
    $resultHost = parse_url($resultUrl, PHP_URL_HOST);
    $cloakHost = parse_url($cloakBase, PHP_URL_HOST);

    if ($resultHost && $cloakHost &&
        strtolower($resultHost) === strtolower($cloakHost)) {
        $download = curl_init($resultUrl);
        curl_setopt_array($download, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120
        ));
        $imageBytes = curl_exec($download);
        $downloadStatus = (int)curl_getinfo($download, CURLINFO_HTTP_CODE);
        $downloadType = (string)curl_getinfo($download, CURLINFO_CONTENT_TYPE);
        curl_close($download);

        if ($imageBytes !== false && $downloadStatus >= 200 &&
            $downloadStatus < 300 && strpos(strtolower($downloadType), 'image/') === 0) {
            $extension = 'png';
            if (stripos($downloadType, 'jpeg') !== false ||
                stripos($downloadType, 'jpg') !== false) {
                $extension = 'jpg';
            } elseif (stripos($downloadType, 'webp') !== false) {
                $extension = 'webp';
            }

            $resultDirectory = __DIR__ . '/assets/tryon-results';
            if (!is_dir($resultDirectory)) {
                @mkdir($resultDirectory, 0755, true);
            }

            if (is_dir($resultDirectory) && is_writable($resultDirectory)) {
                $fileName = 'diane-tryon-' . bin2hex(random_bytes(12)) . '.' . $extension;
                if (@file_put_contents($resultDirectory . '/' . $fileName, $imageBytes) !== false) {
                    $localResultUrl = 'assets/tryon-results/' . $fileName;
                }
            }
        }
    }

    respond(200, array(
        'success' => true,
        'result_url' => $localResultUrl ?: $resultUrl,
        'message' => 'AI Try-On generated successfully.'
    ));

} catch (Throwable $e) {
    respond(502, array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
