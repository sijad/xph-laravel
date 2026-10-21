<?php

namespace Xph\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Console\Application as Artisan;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class XphServiceProvider extends ServiceProvider {
  public function register(): void {
    $this->mergeConfigFrom(__DIR__ . '/../config/xph.php', 'xph');
    $this->app->extend(\Illuminate\Foundation\Exceptions\Renderer\Renderer::class, function ($renderer, $app) {
      if (get_class($renderer) !== \Illuminate\Foundation\Exceptions\Renderer\Renderer::class) return $renderer;
      return new DebugRenderer(
        $app->make(\Illuminate\Contracts\View\Factory::class),
        $app->make(\Illuminate\Foundation\Exceptions\Renderer\Listener::class),
        new \Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer(true),
        $app->make(\Illuminate\Foundation\Exceptions\Renderer\Mappers\BladeMapper::class),
        $app->basePath(),
      );
    });
    $this->app->scoped(Renderer::class, fn($app) => new Renderer(
      $app['config']->get('xph.build_path'),
      (bool) $app['config']->get('app.debug', false),
      (string) $app['config']->get('xph.component_prefix', ''),
    ));
  }

  public function boot(): void {
    if ($this->app->runningInConsole()) {
      Artisan::starting(function (): void {
        DevCommands::nodeExec('xph dev', 'xph')->green();
      });
    }

    Blade::directive(
      'xph',
      fn($expression) =>
      '<?php echo app(\\Xph\\Laravel\\Renderer::class)->render(' . $expression . '); ?>'
    );
    $this->callAfterResolving(
      Kernel::class,
      fn($kernel) =>
      $kernel->pushMiddleware(InjectAssets::class)
    );
    $this->app->afterResolving(ExceptionHandler::class, function ($handler): void {
      if (!method_exists($handler, 'buildContextUsing')) return;
      $handler->buildContextUsing(function (\Throwable $error): array {
        if (!$this->app['config']->get('app.debug', false) || !function_exists('Xph\\runtimeDiagnostics')) return [];
        $frames = \Xph\runtimeDiagnostics($error);
        return $frames ? ['xph' => $frames] : [];
      });
    });
    $this->publishes([
      __DIR__ . '/../config/xph.php' => config_path('xph.php'),
    ], 'xph-config');
    // Run vendor:publish --tag=xph-assets after each xph build.
    $this->publishes([
      $this->app['config']->get('xph.build_path') . '/assets' => public_path('xph'),
    ], 'xph-assets');
  }
}
