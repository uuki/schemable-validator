<?php
namespace SchemableValidator\Controllers;

final class CurlController {
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
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_TIMEOUT        => 30,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_USERAGENT      => 'CurlController/1.0',
    ];

    $this->options = $defaultOptions + $options;
    curl_setopt_array($this->curl, $this->options);
  }

  /**
   * Perform a GET request.
   */
  public function get(string $url, array $headers = [])
  {
    $this->validateUrl($url);

    $this->setOptions([
      CURLOPT_URL         => $url,
      CURLOPT_HTTPGET     => true,
      CURLOPT_HTTPHEADER  => $this->formatHeaders($headers),
    ]);
    return $this->execute();
  }

  /**
   * Perform a POST request.
   */
  public function post(string $url, array $data = [], array $headers = [])
  {
    $this->validateUrl($url);

    $this->setOptions([
      CURLOPT_URL         => $url,
      CURLOPT_POST        => true,
      CURLOPT_POSTFIELDS  => http_build_query($data),
      CURLOPT_HTTPHEADER  => $this->formatHeaders($headers),
    ]);
    return $this->execute();
  }

  /**
   * Enforce https scheme and block private/reserved IP ranges.
   *
   * Prevents SSRF by ensuring only public HTTPS endpoints are reachable.
   *
   * @throws \InvalidArgumentException
   */
  private function validateUrl(string $url): void
  {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException('Invalid URL provided.');
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'https') {
      throw new \InvalidArgumentException('Only HTTPS URLs are allowed.');
    }

    $host = (string) parse_url($url, PHP_URL_HOST);
    if ($host === '') {
      throw new \InvalidArgumentException('URL has no host.');
    }

    // PHP 7.x returns IPv6 literals with brackets ([::1]); PHP 8.x strips them.
    // Normalise to bracketless form so inet_pton() can parse it.
    if ($host !== '' && $host[0] === '[' && $host[-1] === ']') {
      $host = substr($host, 1, -1);
    }

    // gethostbyname() resolves A records and returns the input unchanged for
    // non-hostname strings (e.g. IPv6 literals) — blockPrivateIp handles both.
    $resolved = gethostbyname($host);
    self::blockPrivateIp($resolved);

    // IPv6: gethostbyname() only resolves A records; check AAAA separately.
    $aaaaRecords = dns_get_record($host, DNS_AAAA) ?: [];
    foreach ($aaaaRecords as $record) {
      $ipv6 = $record['ipv6'] ?? '';
      if ($ipv6 !== '') {
        self::blockPrivateIp($ipv6);
      }
    }
  }

  /**
   * Throws if $addr is any private, reserved, loopback, link-local, multicast, or
   * unspecified IP address (IPv4, IPv6, or mixed IPv4-in-IPv6 notation).
   *
   * PHP's FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE cover only a subset of IPv6
   * non-routable ranges (fc00::/7 ULA and ::ffff:0:0/96 IPv4-mapped).  The
   * remaining ranges — ::1 loopback, :: unspecified, fe80::/10 link-local,
   * ff00::/8 multicast — require explicit byte-level inspection via inet_pton.
   *
   * @throws \InvalidArgumentException
   */
  private static function blockPrivateIp(string $addr): void
  {
    // inet_pton() handles IPv4, pure IPv6 (::1), and mixed notation (::ffff:127.0.0.1).
    // Returns false for hostnames, which are not yet resolved here — safe to skip.
    $packed = @inet_pton($addr);
    if ($packed === false) {
      return;
    }

    $len = strlen($packed);

    if ($len === 4) {
      // IPv4: filter_var correctly covers RFC1918, loopback, link-local, etc.
      if (!filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        throw new \InvalidArgumentException('URL resolves to a private or reserved IP address.');
      }
      return;
    }

    if ($len !== 16) {
      return;
    }

    // IPv6 byte-level checks — PHP filter_var flags have incomplete IPv6 coverage:
    //   NO_PRIV_RANGE → fc00::/7 (ULA) only
    //   NO_RES_RANGE  → ::ffff:0:0/96 only
    // Everything else must be checked explicitly.
    $b0 = ord($packed[0]);
    $b1 = ord($packed[1]);

    $isLoopback    = ($packed === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01");
    $isUnspecified = ($packed === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00");
    $isUla         = ($b0 & 0xFE) === 0xFC;                                // fc00::/7
    $isLinkLocal   = ($b0 === 0xFE) && (($b1 & 0xC0) === 0x80);           // fe80::/10
    $isMulticast   = ($b0 === 0xFF);                                       // ff00::/8

    if ($isLoopback || $isUnspecified || $isUla || $isLinkLocal || $isMulticast) {
      throw new \InvalidArgumentException('URL resolves to a private or reserved IPv6 address.');
    }

    // IPv4-mapped (::ffff:0:0/96): bytes 0–9 are 0x00, bytes 10–11 are 0xFF.
    // Check the embedded IPv4 address separately.
    $isMapped = (
      $packed[0] === "\x00" && $packed[1]  === "\x00" &&
      $packed[2] === "\x00" && $packed[3]  === "\x00" &&
      $packed[4] === "\x00" && $packed[5]  === "\x00" &&
      $packed[6] === "\x00" && $packed[7]  === "\x00" &&
      $packed[8] === "\x00" && $packed[9]  === "\x00" &&
      ord($packed[10]) === 0xFF && ord($packed[11]) === 0xFF
    );
    if ($isMapped) {
      $ipv4 = inet_ntop(substr($packed, 12, 4));
      if ($ipv4 !== false &&
          !filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        throw new \InvalidArgumentException('URL resolves to a private or reserved IPv6 address.');
      }
    }
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
    $response = false;

    // Single retry without sleep — avoids amplification when calling external APIs
    // with attacker-influenced URLs while still tolerating one transient failure.
    for ($i = 0; $i < 2; $i++) {
      $response = curl_exec($this->curl);
      if ($response !== false) {
        break;
      }
    }

    if ($response === false) {
      $error = curl_error($this->curl);
      curl_close($this->curl);
      throw new \Exception("cURL Error: $error");
    }

    $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    curl_close($this->curl);

    return [
      'status_code' => $httpCode,
      'response'    => $response,
    ];
  }

  public function __destruct()
  {
    if (is_resource($this->curl)) {
      curl_close($this->curl);
    }
  }
}
