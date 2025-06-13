<?php
class CloudinaryUploader {
    private $cloud_name;
    private $api_key;
    private $api_secret;
    private $use_local_fallback = true;
    private $upload_dir = 'uploads/';
    private $debug_mode = true;
    
    public function __construct() {
        
        $this->cloud_name = $this->getCredential('CLOUDINARY_CLOUD_NAME');
        $this->api_key = $this->getCredential('CLOUDINARY_API_KEY');
        $this->api_secret = $this->getCredential('CLOUDINARY_API_SECRET');
        
        
        if ($this->use_local_fallback && !file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    private function getCredential($key) {
        
        if (isset($_ENV[$key]) && !empty($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        
        $value = getenv($key);
        if ($value !== false && !empty($value)) {
            return $value;
        }
        
        
        if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        
        $credentials = [
            'CLOUDINARY_CLOUD_NAME' => '',
            'CLOUDINARY_API_KEY' => '',
            'CLOUDINARY_API_SECRET' => ''
        ];
        
        return $credentials[$key] ?? null;
    }
    
    public function uploadImage($file_path, $public_id = null) {
        
        if ($this->use_local_fallback) {
            return $this->uploadImageLocally($file_path);
        }
        
        try {
            
            if (empty($file_path) || !file_exists($file_path)) {
                return [
                    'success' => false,
                    'error' => 'Invalid file path or file does not exist: ' . $file_path
                ];
            }
            
            
            if (empty($this->cloud_name) || empty($this->api_key) || empty($this->api_secret)) {
                return [
                    'success' => false,
                    'error' => 'Cloudinary credentials not configured. Cloud: ' . $this->cloud_name . ', Key: ' . $this->api_key . ', Secret: ' . (empty($this->api_secret) ? 'empty' : 'set'),
                    'debug' => [
                        'cloud_name' => $this->cloud_name,
                        'api_key' => $this->api_key,
                        'api_secret_set' => !empty($this->api_secret)
                    ]
                ];
            }
            
            
            $timestamp = time();
            
            
            if (!$public_id) {
                $public_id = 'lost_found_' . $timestamp . '_' . uniqid();
            }
            
            
            $params = [
                'public_id' => $public_id,
                'timestamp' => $timestamp,
                'folder' => 'lost_and_found'
            ];
            
            
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->api_key;
            
            
            $upload_result = $this->uploadWithCurl($file_path, $params);
            
            
            if (!$upload_result['success'] && isset($upload_result['curl_error'])) {
                $upload_result = $this->uploadWithFileGetContents($file_path, $params);
            }
            
            
            if (!$upload_result['success']) {
                if ($this->debug_mode) {
                    error_log("All Cloudinary upload methods failed. Falling back to local storage.");
                }
                return $this->uploadImageLocally($file_path);
            }
            
            return $upload_result;
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("Exception in Cloudinary upload: " . $e->getMessage());
            }
            
            
            return $this->uploadImageLocally($file_path);
        }
    }
    
    private function uploadWithCurl($file_path, $params) {
        
        $file_data = [
            'file' => new CURLFile($file_path)
        ];
        
        
        $post_data = array_merge($params, $file_data);
        
        
        $url = "https://api.cloudinary.com/v1_1/{$this->cloud_name}/image/upload";
        
        $ch = curl_init();
        
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (compatible; PHP-Cloudinary-Client)',
            'Accept: application/json'
        ]);
        
        
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        
        $proxy = getenv('HTTP_PROXY') ?: getenv('http_proxy');
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        
        curl_close($ch);
        
        
        if ($curl_errno !== 0) {
            if ($this->debug_mode) {
                error_log("cURL Error ({$curl_errno}): {$curl_error}");
            }
            return [
                'success' => false,
                'error' => 'cURL Error (' . $curl_errno . '): ' . $curl_error,
                'curl_error' => true,
                'debug_info' => [
                    'url' => $url,
                    'curl_errno' => $curl_errno,
                    'curl_error' => $curl_error,
                    'file_exists' => file_exists($file_path),
                    'file_size' => file_exists($file_path) ? filesize($file_path) : 0
                ]
            ];
        }
        
        
        if ($http_code !== 200) {
            if ($this->debug_mode) {
                error_log("HTTP Error: {$http_code}, Response: " . substr($response, 0, 500));
            }
            return [
                'success' => false,
                'error' => 'HTTP Error: ' . $http_code,
                'response' => $response,
                'debug_info' => [
                    'http_code' => $http_code,
                    'url' => $url,
                    'response_preview' => substr($response, 0, 500)
                ]
            ];
        }
        
        
        $result = json_decode($response, true);
        if ($result && isset($result['secure_url'])) {
            return [
                'success' => true,
                'url' => $result['secure_url'],
                'public_id' => $result['public_id']
            ];
        } else {
            if ($this->debug_mode) {
                error_log("Invalid response from Cloudinary: " . substr($response, 0, 500));
            }
            return [
                'success' => false,
                'error' => 'Invalid response from Cloudinary',
                'response' => $response
            ];
        }
    }
    
    private function uploadWithFileGetContents($file_path, $params) {
        try {
            $url = "https://api.cloudinary.com/v1_1/{$this->cloud_name}/image/upload";
            
            
            $boundary = uniqid();
            $delimiter = '-------------' . $boundary;
            
            
            $post_data = $this->buildMultipartBody($params, $file_path, $delimiter);
            
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 
                        "Content-Type: multipart/form-data; boundary=" . $delimiter . "\r\n" .
                        "User-Agent: Mozilla/5.0 (compatible; PHP-Cloudinary-Client)\r\n" .
                        "Accept: application/json\r\n",
                    'content' => $post_data,
                    'timeout' => 60,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                if ($this->debug_mode) {
                    error_log("file_get_contents failed: " . error_get_last()['message']);
                }
                return [
                    'success' => false,
                    'error' => 'file_get_contents failed: ' . error_get_last()['message']
                ];
            }
            
            
            $result = json_decode($response, true);
            if ($result && isset($result['secure_url'])) {
                return [
                    'success' => true,
                    'url' => $result['secure_url'],
                    'public_id' => $result['public_id']
                ];
            } else {
                if ($this->debug_mode) {
                    error_log("Invalid response from Cloudinary (file_get_contents): " . substr($response, 0, 500));
                }
                return [
                    'success' => false,
                    'error' => 'Invalid response from Cloudinary (file_get_contents)',
                    'response' => $response
                ];
            }
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("Exception in file_get_contents upload: " . $e->getMessage());
            }
            return [
                'success' => false,
                'error' => 'Exception in file_get_contents upload: ' . $e->getMessage()
            ];
        }
    }
    
    private function buildMultipartBody($params, $file_path, $delimiter) {
        $data = '';
        $eol = "\r\n";
        
        
        foreach ($params as $name => $content) {
            $data .= "--" . $delimiter . $eol
                . 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol
                . $content . $eol;
        }
        
        
        $data .= "--" . $delimiter . $eol
            . 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . '"' . $eol
            . 'Content-Type: ' . mime_content_type($file_path) . $eol . $eol
            . file_get_contents($file_path) . $eol;
        
        
        $data .= "--" . $delimiter . "--" . $eol;
        
        return $data;
    }
    
    private function uploadImageLocally($file_path) {
        try {
            
            $extension = pathinfo($file_path, PATHINFO_EXTENSION);
            $new_filename = 'local_' . time() . '_' . uniqid() . '.' . $extension;
            $destination = $this->upload_dir . $new_filename;
            
            
            if (!file_exists($this->upload_dir)) {
                mkdir($this->upload_dir, 0755, true);
            }
            
            
            if (copy($file_path, $destination)) {
                return [
                    'success' => true,
                    'url' => $destination,
                    'public_id' => 'local_' . pathinfo($new_filename, PATHINFO_FILENAME),
                    'is_local' => true
                ];
            } else {
                if ($this->debug_mode) {
                    error_log("Failed to copy file to local storage: {$file_path} -> {$destination}");
                }
                return [
                    'success' => false,
                    'error' => 'Failed to copy file to local storage'
                ];
            }
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("Exception in local upload: " . $e->getMessage());
            }
            return [
                'success' => false,
                'error' => 'Exception in local upload: ' . $e->getMessage()
            ];
        }
    }
    
    private function generateSignature($params) {
        
        unset($params['signature']);
        unset($params['api_key']);
        
        
        ksort($params);
        
        
        $query_string = '';
        foreach ($params as $key => $value) {
            $query_string .= $key . '=' . $value . '&';
        }
        
        
        $query_string = rtrim($query_string, '&');
        
        
        $query_string .= $this->api_secret;
        
        
        return sha1($query_string);
    }
    
    public function deleteImage($public_id) {
        
        if (strpos($public_id, 'local_') === 0) {
            return $this->deleteLocalImage($public_id);
        }
        
        try {
            if (empty($public_id)) {
                return [
                    'success' => false,
                    'error' => 'Public ID is required for deletion'
                ];
            }
            
            $timestamp = time();
            
            $params = [
                'public_id' => $public_id,
                'timestamp' => $timestamp
            ];
            
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->api_key;
            
            $url = "https://api.cloudinary.com/v1_1/{$this->cloud_name}/image/destroy";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            curl_close($ch);
            
            if (!empty($curl_error)) {
                if ($this->debug_mode) {
                    error_log("cURL Error in deleteImage: " . $curl_error);
                }
                return [
                    'success' => false,
                    'error' => 'cURL Error: ' . $curl_error
                ];
            }
            
            if ($http_code === 200) {
                $result = json_decode($response, true);
                return [
                    'success' => true,
                    'result' => $result['result'] ?? 'deleted'
                ];
            } else {
                if ($this->debug_mode) {
                    error_log("Delete failed with HTTP code: " . $http_code . ", Response: " . substr($response, 0, 500));
                }
                return [
                    'success' => false,
                    'error' => 'Delete failed with HTTP code: ' . $http_code,
                    'response' => $response
                ];
            }
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("Exception in deleteImage: " . $e->getMessage());
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function deleteLocalImage($public_id) {
        try {
            
            $files = glob($this->upload_dir . $public_id . '*');
            
            if (empty($files)) {
                return [
                    'success' => false,
                    'error' => 'Local file not found for public_id: ' . $public_id
                ];
            }
            
            $deleted = true;
            foreach ($files as $file) {
                if (file_exists($file)) {
                    if (!unlink($file)) {
                        $deleted = false;
                    }
                }
            }
            
            return [
                'success' => $deleted,
                'result' => $deleted ? 'deleted' : 'failed'
            ];
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("Exception in deleteLocalImage: " . $e->getMessage());
            }
            return [
                'success' => false,
                'error' => 'Exception in deleteLocalImage: ' . $e->getMessage()
            ];
        }
    }
    
    
    public function enableLocalFallback($upload_dir = 'uploads/') {
        $this->use_local_fallback = true;
        $this->upload_dir = rtrim($upload_dir, '/') . '/';
        
        
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
        
        return $this;
    }
    
    
    public function testCredentials() {
        return [
            'cloud_name' => $this->cloud_name,
            'api_key' => $this->api_key,
            'api_secret_set' => !empty($this->api_secret),
            'api_secret_length' => strlen($this->api_secret ?? ''),
            'all_set' => !empty($this->cloud_name) && !empty($this->api_key) && !empty($this->api_secret),
            'local_fallback_enabled' => $this->use_local_fallback,
            'upload_dir' => $this->upload_dir
        ];
    }
    
    
    public function testConnection() {
        $results = [];
        
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com");
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $results['curl_basic'] = [
            'success' => empty($curl_error),
            'http_code' => $http_code,
            'error' => $curl_error
        ];
        
        
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $result = @file_get_contents('https://api.cloudinary.com', false, $context);
        $error = error_get_last();
        
        $results['file_get_contents'] = [
            'success' => $result !== false,
            'error' => $error ? $error['message'] : null
        ];
        
        
        $test_file = tempnam(sys_get_temp_dir(), 'cloudinary_test');
        file_put_contents($test_file, 'test');
        
        $this->enableLocalFallback();
        $upload_result = $this->uploadImageLocally($test_file);
        
        $results['local_fallback'] = [
            'success' => $upload_result['success'],
            'path' => $upload_result['success'] ? $upload_result['url'] : null,
            'error' => $upload_result['success'] ? null : $upload_result['error']
        ];
        
        
        if (file_exists($test_file)) {
            unlink($test_file);
        }
        
        if ($upload_result['success'] && file_exists($upload_result['url'])) {
            unlink($upload_result['url']);
        }
        
        return $results;
    }
}
?>
