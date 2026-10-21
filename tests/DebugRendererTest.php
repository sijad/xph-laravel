<?php

namespace Xph\Laravel\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\View\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Exceptions\Renderer\Listener;
use Illuminate\Foundation\Exceptions\Renderer\Mappers\BladeMapper;
use Illuminate\Foundation\Exceptions\Renderer\Renderer as LaravelRenderer;
use Illuminate\Http\Request;
use Illuminate\View\ViewException;
use Orchestra\Testbench\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Xph\Laravel\DebugRenderer;
use Xph\Laravel\Renderer;
use Xph\Laravel\XphServiceProvider;

class DebugRendererTest extends TestCase {
  private string $directory;

  protected function getPackageProviders($app): array {
    return [XphServiceProvider::class];
  }

  protected function defineEnvironment($app): void {
    $app['config']->set('app.debug', true);
  }

  protected function setUp(): void {
    parent::setUp();
    $this->app->register(\Illuminate\Foundation\Providers\FoundationServiceProvider::class, true);
    $this->directory = sys_get_temp_dir() . '/xph-debug-page-' . bin2hex(random_bytes(8));
    $this->app['config']->set('xph.source_path', $this->directory);
    $files = new Filesystem;
    $files->copyDirectory(__DIR__ . '/fixtures/debug', $this->directory . '/build');
    $files->copyDirectory(__DIR__ . '/fixtures/source/resources', $this->directory . '/resources');
    copy(__DIR__ . '/fixtures/runtime.php', $this->directory . '/build/runtime.php');
  }

  protected function tearDown(): void {
    \Xph\enableRuntimeDiagnostics(false);
    (new Filesystem)->deleteDirectory($this->directory);
    parent::tearDown();
  }

  private function failure(): \Throwable {
    try {
      (new Renderer($this->directory . '/build', true))->render('resources/Broken');
    } catch (\Throwable $error) {
      return $error;
    }
    $this->fail('Expected a component failure.');
  }

  private function installRenderer(): void {
    $this->assertInstanceOf(DebugRenderer::class, $this->app->make(LaravelRenderer::class));
    $this->app->instance(LaravelRenderer::class, new DebugRenderer(
      $this->app->make(Factory::class),
      $this->app->make(Listener::class),
      new HtmlErrorRenderer(true),
      $this->app->make(BladeMapper::class),
      $this->directory,
    ));
  }

  public function test_debug_page_shows_original_source_and_preserves_exception(): void {
    $this->installRenderer();
    $error = $this->failure();
    $trace = $error->getTrace();
    $wrapped = new ViewException('Blade wrapper', 0, 1, __FILE__, __LINE__, $error);
    $request = Request::create('/broken');
    $this->app->instance('request', $request);
    $display = null;
    $this->app->make(Factory::class)->composer('laravel-exceptions-renderer::show', function ($view) use (&$display): void {
      $display = $view->getData()['exception'];
    });
    $response = $this->app->make(ExceptionHandler::class)->render($request, $wrapped);
    $html = $response->getContent();
    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringContainsString('resources/Broken.xph', $html);
    $this->assertStringContainsString('original failure', $html);
    $sourceFrame = $display->frames()->first();
    $this->assertSame('resources/Broken.xph', $sourceFrame->file());
    $this->assertSame(5, $sourceFrame->line());
    $this->assertStringContainsString('server.name()', $sourceFrame->snippet());
    $this->assertTrue($display->frames()->contains(fn ($frame) => str_contains($frame->file(), 'build/components/resources/Broken.php')));
    $this->assertSame($trace, $error->getTrace());
    $this->assertSame($error, $wrapped->getPrevious());
    $this->assertSame('original failure', $error->getMessage());
  }

  public function test_throwing_a_string_maps_to_the_resources_source_file(): void {
    $this->installRenderer();
    $files = new Filesystem;
    $files->copyDirectory(__DIR__ . '/fixtures/server-error', $this->directory . '/server-error');
    copy(__DIR__ . '/fixtures/runtime.php', $this->directory . '/server-error/runtime.php');
    copy(__DIR__ . '/fixtures/server-error/component.xph', $this->directory . '/resources/component.xph');
    $this->app['config']->set('xph.source_path', $this->directory . '/resources');
    $error = null;
    try {
      (new Renderer($this->directory . '/server-error', true))->render('component');
    } catch (\Throwable $caught) {
      $error = $caught;
    }
    $this->assertInstanceOf(\Error::class, $error);
    $this->assertSame('Can only throw objects', $error->getMessage());
    $display = null;
    $this->app->make(Factory::class)->composer('laravel-exceptions-renderer::show', function ($view) use (&$display): void {
      $display = $view->getData()['exception'];
    });
    $request = Request::create('/broken');
    $this->app->instance('request', $request);
    $this->app->make(ExceptionHandler::class)->render($request, $error);
    $frame = $display->frames()->first();
    $this->assertSame('resources/component.xph', $frame->file());
    $this->assertSame(2, $frame->line());
    $this->assertStringContainsString("throw 'failure';", $frame->snippet());
  }

  public function test_production_response_does_not_expose_component_source(): void {
    $this->installRenderer();
    $error = $this->failure();
    $this->app['config']->set('app.debug', false);
    $request = Request::create('/broken');
    $this->app->instance('request', $request);
    $response = $this->app->make(ExceptionHandler::class)->render($request, $error);
    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringNotContainsString('Broken.xph', $response->getContent());
    $this->assertStringNotContainsString('original failure', $response->getContent());
  }

  public function test_json_errors_keep_laravels_normal_trace(): void {
    $this->installRenderer();
    $error = $this->failure();
    $request = Request::create('/broken', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
    $this->app->instance('request', $request);
    $response = $this->app->make(ExceptionHandler::class)->render($request, $error);
    $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('original failure', $data['message']);
    $this->assertSame($error->getFile(), $data['file']);
    $this->assertSame($error->getLine(), $data['line']);
  }

  public function test_disabled_diagnostics_leave_the_debug_page_unchanged(): void {
    $this->installRenderer();
    $error = $this->failure();
    \Xph\enableRuntimeDiagnostics(false);
    $html = $this->app->make(LaravelRenderer::class)->render(Request::create('/broken'), $error);
    $this->assertStringContainsString('original failure', $html);
    $this->assertStringNotContainsString('resources/Broken.xph', $html);
  }

  public function test_missing_source_falls_back_to_laravels_normal_page(): void {
    $this->installRenderer();
    $error = $this->failure();
    unlink($this->directory . '/resources/Broken.xph');
    $request = Request::create('/broken');
    $html = $this->app->make(LaravelRenderer::class)->render($request, $error);
    $this->assertStringContainsString('original failure', $html);
    $this->assertStringNotContainsString('resources/Broken.xph', $html);
  }
}
