<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\SV;
use SchemableValidator\Validation\FileValidationDriver;
use SchemableValidator\Validation\NativeFileValidator;

/**
 * SV::file() is validated through a dependency-free FileValidationDriver (no
 * Respect), and a custom driver can be injected via SchemaBuilder::toValidator().
 */
final class FileValidationDriverTest extends TestCase {

  private function tmpFileWithContent(string $content): string {
    $path = tempnam(sys_get_temp_dir(), 'svf');
    file_put_contents($path, $content);
    return $path;
  }

  public function test_native_driver_accepts_allowed_mime(): void {
    $path = $this->tmpFileWithContent("%PDF-1.4\n%test\n");
    $file = ['name' => 'd.pdf', 'type' => 'application/pdf', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => 10];

    $r = (new NativeFileValidator())->validate($file, ['accept' => ['application/pdf']]);
    unlink($path);

    $this->assertTrue($r['is_valid']);
    $this->assertNull($r['errors']);
  }

  public function test_native_driver_rejects_disallowed_mime(): void {
    $path = $this->tmpFileWithContent('plain text body');
    $file = ['name' => 'd.txt', 'type' => 'text/plain', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => 10];

    $r = (new NativeFileValidator())->validate($file, ['accept' => ['application/pdf']]);
    unlink($path);

    $this->assertFalse($r['is_valid']);
    $this->assertNotNull($r['errors']);
  }

  public function test_native_driver_rejects_upload_error(): void {
    $file = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
    $r = (new NativeFileValidator())->validate($file, ['accept' => ['application/pdf']]);
    $this->assertFalse($r['is_valid']);
  }

  public function test_empty_accept_allows_any_uploaded_file(): void {
    $path = $this->tmpFileWithContent('anything');
    $file = ['name' => 'x', 'type' => '', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => 5];
    $r = (new NativeFileValidator())->validate($file, ['accept' => []]);
    unlink($path);
    $this->assertTrue($r['is_valid']);
  }

  public function test_schemaBuilder_file_field_uses_native_driver(): void {
    $path = $this->tmpFileWithContent("%PDF-1.4\n");
    $sb   = SV::object(['doc' => SV::file(['application/pdf'])]);
    $files = ['doc' => ['name' => 'd.pdf', 'type' => 'application/pdf', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => 9]];

    $result = $sb->toValidator()->validateFiles($files)->getResult();
    unlink($path);

    $this->assertTrue($result['doc'][0]['is_valid']);
  }

  public function test_custom_driver_injection(): void {
    $alwaysFail = new class implements FileValidationDriver {
      public function validate(array $file, array $config): array {
        return ['value' => $file, 'is_valid' => false, 'errors' => 'rejected by custom driver'];
      }
    };
    $sb    = SV::object(['doc' => SV::file(['application/pdf'])]);
    $files = ['doc' => ['name' => 'd.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK, 'size' => 1]];

    $result = $sb->toValidator(['fileDriver' => $alwaysFail])->validateFiles($files)->getResult();

    $this->assertFalse($result['doc'][0]['is_valid']);
    $this->assertSame('rejected by custom driver', $result['doc'][0]['errors']);
  }
}
