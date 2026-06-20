<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Infrastructure\FormController;

class FormControllerTest extends TestCase
{
  /**
   * @runInSeparateProcess
   */
  public function test_save_and_get_returns_stored_data(): void
  {
    $controller = new FormController();
    $data = ['name' => ['value' => 'Alice', 'is_valid' => true, 'errors' => null]];

    $controller->save($data);

    $this->assertSame($data, $controller->get());
  }

  /**
   * @runInSeparateProcess
   */
  public function test_get_returns_null_when_empty(): void
  {
    $controller = new FormController();

    $this->assertNull($controller->get());
  }

  /**
   * @runInSeparateProcess
   */
  public function test_clear_removes_stored_data(): void
  {
    $controller = new FormController();
    $controller->save(['name' => ['value' => 'Alice', 'is_valid' => true, 'errors' => null]]);
    $controller->clear();

    $this->assertNull($controller->get());
  }

  /**
   * @runInSeparateProcess
   */
  public function test_session_starts_automatically(): void
  {
    $controller = new FormController();
    $controller->get();

    $this->assertSame(PHP_SESSION_ACTIVE, session_status());
  }
}
