<?php

namespace SchemableValidator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SchemableValidator\Interfaces\AbstractInterface;

class AbstractInterfaceTest extends TestCase
{
  public function test_getTemplate_returns_stored_template(): void
  {
    $interface = new AbstractInterface(['user' => 'Hello {name}']);

    $this->assertSame('Hello {name}', $interface->getTemplate('user'));
  }

  public function test_getTemplate_returns_empty_string_for_missing_key(): void
  {
    $interface = new AbstractInterface([]);

    $this->assertSame('', $interface->getTemplate('missing'));
  }

  public function test_getAll_returns_all_templates(): void
  {
    $templates = ['user' => 'Hello', 'admin' => 'Hi admin'];
    $interface = new AbstractInterface($templates);

    $this->assertSame($templates, $interface->getAll());
  }
}
