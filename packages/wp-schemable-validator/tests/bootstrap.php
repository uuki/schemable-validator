<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Minimal WP_REST_Response stub — real class is unavailable outside WP.
if (!class_exists('WP_REST_Response')) {
  class WP_REST_Response {
    private array $headers = [];
    private int $status = 200;
    private $data;

    public function header(string $key, string $value): void {
      $this->headers[strtolower($key)] = $value;
    }

    public function set_status(int $status): void {
      $this->status = $status;
    }

    public function set_data($data): void {
      $this->data = $data;
    }

    public function getHeader(string $key): ?string {
      return $this->headers[strtolower($key)] ?? null;
    }

    public function getStatus(): int {
      return $this->status;
    }

    public function getData() {
      return $this->data;
    }
  }
}
