<?php
namespace SchemableValidator\Controllers;

class CurlController {
  private $curl;
  private $options = [];

  public function __construct()
  {
    $this->curl = curl_init();
  }

  /**
   * Set basic cURL options with security considerations.
   */
  private function setOptions(array $options = [])
  {
    $defaultOptions = [
      CURLOPT_RETURNTRANSFER => true,         // Return response as a string
      CURLOPT_HEADER => false,                // Exclude headers from output
      CURLOPT_TIMEOUT => 30,                  // Request timeout (e.g., 30 seconds)
      CURLOPT_SSL_VERIFYPEER => true,         // Verify SSL certificate
      CURLOPT_SSL_VERIFYHOST => 2,            // Check the SSL host
      CURLOPT_USERAGENT => 'CurlController/1.0', // Set a default User-Agent
    ];

    $this->options = $defaultOptions + $options;
    curl_setopt_array($this->curl, $this->options);
  }

  /**
   * Perform a GET request with sanitized URL.
   */
  public function get(string $url, array $headers = [])
  {
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new Exception("Invalid URL provided.");
    }

    $this->setOptions([
      CURLOPT_URL => $url,
      CURLOPT_HTTPGET => true,
      CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
    ]);
    return $this->execute();
  }

  /**
   * Perform a POST request with sanitized data.
   */
  public function post(string $url, array $data = [], array $headers = [])
  {
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception("Invalid URL provided.");
    }

    $this->setOptions([
      CURLOPT_URL => $url,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query($data),
      CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
    ]);
    return $this->execute();
  }

  /**
   * Format headers as required by cURL.
   */
  private function formatHeaders(array $headers)
  {
    $formattedHeaders = [];
    foreach ($headers as $key => $value) {
        $formattedHeaders[] = "$key: $value";
    }
    return $formattedHeaders;
  }

  /**
   * Execute the cURL request with error handling and retry logic.
   */
  private function execute()
  {
    $retryCount = 3;
    $response = false;

    for ($i = 0; $i < $retryCount; $i++) {
      $response = curl_exec($this->curl);

      if ($response !== false) {
        break; // Successful response, exit retry loop
      }
      sleep(1); // Wait before retrying
    }

    if ($response === false) {
      $error = curl_error($this->curl);
      curl_close($this->curl);
      throw new Exception("cURL Error: $error");
    }

    $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    curl_close($this->curl);

    return [
      'status_code' => $httpCode,
      'response' => $response,
    ];
  }

  /**
   * Destructor to close cURL resource if not already closed.
   */
  public function __destruct()
  {
    if (is_resource($this->curl)) {
      curl_close($this->curl);
    }
  }
}
