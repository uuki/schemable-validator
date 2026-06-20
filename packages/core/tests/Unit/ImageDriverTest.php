<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\SV;
use SchemableValidator\Validation\ImageDriver;
use SchemableValidator\Validation\NativeImageDriver;

/**
 * Tests for ImageDriver and its integration with SV::file() / validateFiles().
 */
final class ImageDriverTest extends TestCase {
  private string $tmpDir;

  protected function setUp(): void {
    $this->tmpDir = sys_get_temp_dir();
  }

  private function makeJpeg(int $width = 100, int $height = 80): string {
    if (!function_exists('imagejpeg')) {
      $this->markTestSkipped('GD extension not available');
    }
    $im   = imagecreatetruecolor($width, $height);
    $path = tempnam($this->tmpDir, 'svimg') . '.jpg';
    imagejpeg($im, $path);
    imagedestroy($im);
    return $path;
  }

  private function makePng(int $width = 200, int $height = 150): string {
    if (!function_exists('imagepng')) {
      $this->markTestSkipped('GD extension not available');
    }
    $im   = imagecreatetruecolor($width, $height);
    $path = tempnam($this->tmpDir, 'svimg') . '.png';
    imagepng($im, $path);
    imagedestroy($im);
    return $path;
  }

  private function fileArray(string $path): array {
    return [
      'name'     => basename($path),
      'type'     => 'image/jpeg',
      'tmp_name' => $path,
      'error'    => UPLOAD_ERR_OK,
      'size'     => filesize($path),
    ];
  }

  // ── NativeImageDriver unit tests ──────────────────────────────

  public function test_valid_image_within_constraints(): void {
    $path   = $this->makeJpeg(100, 80);
    $driver = new NativeImageDriver();

    $r = $driver->validate($this->fileArray($path), [
      'maxWidth'  => 200,
      'maxHeight' => 200,
    ]);
    unlink($path);

    $this->assertTrue($r['is_valid']);
    $this->assertNull($r['errors']);
  }

  public function test_rejects_image_exceeding_maxWidth(): void {
    $path   = $this->makeJpeg(500, 100);
    $driver = new NativeImageDriver();

    $r = $driver->validate($this->fileArray($path), ['maxWidth' => 400]);
    unlink($path);

    $this->assertFalse($r['is_valid']);
    $this->assertStringContainsString('width', $r['errors']);
  }

  public function test_rejects_image_exceeding_maxHeight(): void {
    $path   = $this->makeJpeg(100, 600);
    $driver = new NativeImageDriver();

    $r = $driver->validate($this->fileArray($path), ['maxHeight' => 500]);
    unlink($path);

    $this->assertFalse($r['is_valid']);
    $this->assertStringContainsString('height', $r['errors']);
  }

  public function test_rejects_image_below_minWidth(): void {
    $path   = $this->makeJpeg(50, 100);
    $driver = new NativeImageDriver();

    $r = $driver->validate($this->fileArray($path), ['minWidth' => 100]);
    unlink($path);

    $this->assertFalse($r['is_valid']);
    $this->assertStringContainsString('width', $r['errors']);
  }

  public function test_rejects_image_below_minHeight(): void {
    $path   = $this->makeJpeg(200, 30);
    $driver = new NativeImageDriver();

    $r = $driver->validate($this->fileArray($path), ['minHeight' => 50]);
    unlink($path);

    $this->assertFalse($r['is_valid']);
    $this->assertStringContainsString('height', $r['errors']);
  }

  public function test_rejects_file_exceeding_maxSize(): void {
    $path = $this->makeJpeg(100, 100);
    $driver = new NativeImageDriver();

    $r = $driver->validate($this->fileArray($path), ['maxSize' => 1]); // 1 byte — always fails
    unlink($path);

    $this->assertFalse($r['is_valid']);
    $this->assertStringContainsString('size', $r['errors']);
  }

  public function test_rejects_non_image_file(): void {
    $path = tempnam($this->tmpDir, 'svtxt');
    file_put_contents($path, 'this is not an image');
    $driver = new NativeImageDriver();

    $r = $driver->validate($this->fileArray($path), []);
    unlink($path);

    $this->assertFalse($r['is_valid']);
    $this->assertStringContainsString('image', $r['errors']);
  }

  public function test_rejects_script_with_jpg_name_claim(): void {
    // Extension spoofing: a shell script uploaded with name "shell.jpg" and
    // Content-Type "image/jpeg".  NativeImageDriver must reject by reading the
    // actual file content (finfo magic bytes), ignoring $file['name'] and
    // $file['type'] — both are attacker-controlled and cannot be trusted.
    $path = tempnam($this->tmpDir, 'svscript');
    file_put_contents($path, "#!/bin/bash\nrm -rf /tmp/pwned\n");
    $driver = new NativeImageDriver();

    $r = $driver->validate([
      'name'     => 'shell.jpg',   // attacker-supplied filename
      'type'     => 'image/jpeg',  // attacker-supplied Content-Type
      'tmp_name' => $path,         // PHP temp path — no user extension, system-controlled
      'error'    => UPLOAD_ERR_OK,
      'size'     => filesize($path),
    ], []);
    unlink($path);

    $this->assertFalse($r['is_valid'], 'Script file with image name/type claim must be rejected');
    $this->assertStringContainsString('image', $r['errors']);
  }

  public function test_rejects_html_with_png_name_claim(): void {
    $path = tempnam($this->tmpDir, 'svhtml');
    file_put_contents($path, '<html><script>alert(1)</script></html>');
    $driver = new NativeImageDriver();

    $r = $driver->validate([
      'name'     => 'xss.png',
      'type'     => 'image/png',
      'tmp_name' => $path,
      'error'    => UPLOAD_ERR_OK,
      'size'     => filesize($path),
    ], []);
    unlink($path);

    $this->assertFalse($r['is_valid'], 'HTML file with image name/type claim must be rejected');
  }

  public function test_rejects_missing_tmp_name(): void {
    $driver = new NativeImageDriver();
    $r = $driver->validate(['name' => 'x', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 0], []);
    $this->assertFalse($r['is_valid']);
  }

  public function test_no_constraints_accepts_any_valid_image(): void {
    $path   = $this->makePng(1000, 1000);
    $driver = new NativeImageDriver();

    $r = $driver->validate($this->fileArray($path), []);
    unlink($path);

    $this->assertTrue($r['is_valid']);
  }

  // ── Integration with SchemaBuilder ───────────────────────────

  public function test_imageDriver_invoked_via_toValidator(): void {
    $path = $this->makeJpeg(100, 80);

    $alwaysReject = new class implements ImageDriver {
      public function validate(array $file, array $config): array {
        return ['is_valid' => false, 'errors' => 'rejected by custom image driver'];
      }
    };

    $schema = SV::object([
      'photo' => SV::file(['image/jpeg'], ['maxWidth' => 4096]),
    ]);

    $files = [
      'photo' => [
        'name'     => 'p.jpg',
        'type'     => 'image/jpeg',
        'tmp_name' => $path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($path),
      ],
    ];

    $result = $schema
      ->toValidator(['imageDriver' => $alwaysReject])
      ->validateFiles($files)
      ->getResult();

    unlink($path);

    $this->assertFalse($result['photo'][0]['is_valid']);
    $this->assertSame('rejected by custom image driver', $result['photo'][0]['errors']);
  }

  public function test_imageDriver_not_called_when_file_driver_rejects(): void {
    $callLog     = new \stdClass();
    $callLog->called = false;
    $imageDriver = new class($callLog) implements ImageDriver {
      private \stdClass $log;
      public function __construct(\stdClass $log) { $this->log = $log; }
      public function validate(array $file, array $config): array {
        $this->log->called = true;
        return ['is_valid' => true, 'errors' => null];
      }
    };

    $schema = SV::object([
      'photo' => SV::file(['application/pdf'], ['maxWidth' => 4096]),
    ]);
    $files = [
      'photo' => [
        'name'     => 'bad.txt',
        'type'     => 'text/plain',
        'tmp_name' => __FILE__, // real file, but wrong MIME
        'error'    => UPLOAD_ERR_OK,
        'size'     => 100,
      ],
    ];

    $schema
      ->toValidator(['imageDriver' => $imageDriver])
      ->validateFiles($files)
      ->getResult();

    $this->assertFalse($callLog->called, 'ImageDriver must not run when FileValidationDriver rejected the file');
  }

  public function test_imageDriver_not_called_when_no_image_constraints(): void {
    $callLog     = new \stdClass();
    $callLog->called = false;
    $imageDriver = new class($callLog) implements ImageDriver {
      private \stdClass $log;
      public function __construct(\stdClass $log) { $this->log = $log; }
      public function validate(array $file, array $config): array {
        $this->log->called = true;
        return ['is_valid' => true, 'errors' => null];
      }
    };

    $path   = $this->makeJpeg();
    $schema = SV::object([
      'photo' => SV::file(['image/jpeg']), // no image constraints
    ]);
    $files = [
      'photo' => [
        'name'     => 'p.jpg',
        'type'     => 'image/jpeg',
        'tmp_name' => $path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($path),
      ],
    ];

    $schema
      ->toValidator(['imageDriver' => $imageDriver])
      ->validateFiles($files)
      ->getResult();

    unlink($path);

    $this->assertFalse($callLog->called, 'ImageDriver must not run when the field declares no image constraints');
  }
}
