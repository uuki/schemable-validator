<?php

namespace SchemableValidator\Interfaces\WordPress\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SchemableValidator\Interfaces\WordPress\Plugin;

class PluginTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    Monkey\setUp();
  }

  protected function tearDown(): void
  {
    Monkey\tearDown();
    parent::tearDown();
  }

  public function test_constructor_registers_admin_init_hook(): void
  {
    $hooks = [];
    Functions\expect('add_action')
      ->twice()
      ->andReturnUsing(function (string $hook) use (&$hooks): void {
        $hooks[] = $hook;
      });

    new Plugin([]);

    $this->assertContains('admin_init', $hooks);
    $this->assertContains('admin_menu', $hooks);
  }

  public function test_register_settings_calls_register_setting_for_each_template(): void
  {
    Functions\expect('add_action')->twice();

    $plugin = new Plugin([
      'user'  => ['title' => 'User',  'description' => ''],
      'admin' => ['title' => 'Admin', 'description' => ''],
    ]);

    $registered = [];
    Functions\expect('register_setting')
      ->twice()
      ->andReturnUsing(function (string $group, string $key) use (&$registered): void {
        $registered[$key] = $group;
      });

    $plugin->registerSettings();

    $this->assertArrayHasKey('SCHV_REPLY_FORMAT_FOR_user',  $registered);
    $this->assertArrayHasKey('SCHV_REPLY_FORMAT_FOR_admin', $registered);
    $this->assertSame('schv_options_group', $registered['SCHV_REPLY_FORMAT_FOR_user']);
  }

  public function test_keys_all_returns_mapped_option_names(): void
  {
    Functions\expect('add_action')->twice();

    $plugin = new Plugin([
      'user'  => ['title' => 'User',  'description' => ''],
      'admin' => ['title' => 'Admin', 'description' => ''],
    ]);

    $keys = $plugin->keysAll();

    $this->assertSame([
      'user'  => 'SCHV_REPLY_FORMAT_FOR_user',
      'admin' => 'SCHV_REPLY_FORMAT_FOR_admin',
    ], $keys);
  }

  public function test_empty_template_list_registers_no_settings(): void
  {
    Functions\expect('add_action')->twice();

    $plugin = new Plugin([]);

    Functions\expect('register_setting')->never();

    $plugin->registerSettings();

    $this->assertSame([], $plugin->keysAll());
  }
}
