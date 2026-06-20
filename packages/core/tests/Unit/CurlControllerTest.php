<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Infrastructure\CurlController;

/**
 * Verifies that CurlController::validateUrl() blocks every private / reserved /
 * injection-capable URL before any HTTP connection is established.
 *
 * All tests expect an exception — no actual network calls are made.
 */
class CurlControllerTest extends TestCase
{
  private function curl(): CurlController
  {
    return new CurlController();
  }

  // ── Scheme enforcement ───────────────────────────────────────────────────────

  public function test_http_scheme_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('http://www.google.com/', []);
  }

  public function test_ftp_scheme_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('ftp://files.example.com/', []);
  }

  public function test_non_url_string_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('not-a-url', []);
  }

  // ── IPv4 private / reserved range ────────────────────────────────────────────

  public function test_ipv4_loopback_127_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://127.0.0.1/', []);
  }

  public function test_ipv4_rfc1918_192_168_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://192.168.1.1/', []);
  }

  public function test_ipv4_rfc1918_10_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://10.0.0.1/', []);
  }

  public function test_ipv4_rfc1918_172_16_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://172.16.0.1/', []);
  }

  /** AWS/GCP/Azure IMDS endpoint */
  public function test_ipv4_link_local_metadata_endpoint_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://169.254.169.254/', []);
  }

  // ── IPv6 private / reserved range ────────────────────────────────────────────

  public function test_ipv6_loopback_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://[::1]/', []);
  }

  public function test_ipv6_ula_fc00_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://[fc00::1]/', []);
  }

  public function test_ipv6_ula_fd00_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://[fd00::1]/', []);
  }

  /** fe80::/10 — link-local, not covered by FILTER_FLAG_NO_PRIV_RANGE */
  public function test_ipv6_link_local_fe80_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://[fe80::1]/', []);
  }

  /** ff02::/8 — multicast, not covered by FILTER_FLAG_NO_PRIV_RANGE */
  public function test_ipv6_multicast_ff02_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://[ff02::1]/', []);
  }

  /** ::ffff:127.0.0.1 — IPv4-mapped IPv6 loopback */
  public function test_ipv6_ipv4_mapped_loopback_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://[::ffff:127.0.0.1]/', []);
  }

  /** ::ffff:192.168.1.1 — IPv4-mapped private range */
  public function test_ipv6_ipv4_mapped_private_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post('https://[::ffff:192.168.1.1]/', []);
  }

  // ── Injection characters in URL ───────────────────────────────────────────────

  /** Null byte — classic path-truncation / bypass attempt */
  public function test_null_byte_in_url_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post("https://evil.com/path\x00", []);
  }

  /** CRLF injection — HTTP response-splitting or header injection via redirect */
  public function test_crlf_in_url_host_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post("https://evil.com\r\nX-Injected: header/path", []);
  }

  /** LF only in URL */
  public function test_lf_in_url_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post("https://evil.com\nX-Injected: header", []);
  }

  /** Tab character in host */
  public function test_tab_in_url_host_is_rejected(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post("https://evil\t.com/", []);
  }

  // ── reCAPTCHA prefix bypass attempts ─────────────────────────────────────────

  /**
   * Null byte after valid prefix — prefix strncmp passes, but validateUrl() must
   * reject via FILTER_VALIDATE_URL before any curl connection is made.
   */
  public function test_null_byte_after_valid_recaptcha_prefix_is_rejected_by_url_validation(): void
  {
    // This URL would pass the strncmp prefix check but fail FILTER_VALIDATE_URL
    $url = "https://www.google.com/recaptcha/api/siteverify\x00.evil.com";
    $this->expectException(\InvalidArgumentException::class);
    $this->curl()->post($url, []);
  }
}
