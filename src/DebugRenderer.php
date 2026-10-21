<?php

namespace Xph\Laravel;

use Illuminate\Foundation\Exceptions\Renderer\Exception;
use Illuminate\Foundation\Exceptions\Renderer\Renderer as LaravelRenderer;
use Illuminate\Http\Request;
use Illuminate\View\ViewException;
use Throwable;

class DebugRenderer extends LaravelRenderer {
  public function render(Request $request, Throwable $throwable) {
    if (!config('app.debug') || !function_exists('Xph\\runtimeDiagnostics')) {
      return parent::render($request, $throwable);
    }
    $frames = \Xph\runtimeDiagnostics($throwable);
    $sources = [];
    foreach ($frames as $frame) {
      $component = $frame['component'] ?? '';
      $location = $frame['origin'] ?? $frame['location'] ?? [];
      $line = $location['line'] ?? null;
      if (!is_string($component) || !is_int($line) || $line < 1) continue;
      $sourcePath = config('xph.source_path', $this->basePath . '/resources');
      $path = realpath($sourcePath . '/' . $component);
      // Only expose existing component sources inside the application.
      if (
        $path === false || !str_starts_with($path, realpath($this->basePath) . DIRECTORY_SEPARATOR)
        || !str_ends_with($path, '.xph') || !is_file($path)
      ) continue;
      $sources[] = ['file' => $path, 'line' => $line];
    }
    if (!$sources) return parent::render($request, $throwable);

    // Work on the display copy; Laravel reports the original throwable unchanged.
    $exception = $this->bladeMapper->map($this->htmlErrorRenderer->render($throwable));
    $original = $throwable;
    while ($original instanceof ViewException && $original->getPrevious() !== null) {
      $original = $original->getPrevious();
    }
    $first = array_shift($sources);
    $exception->setTrace([
      ...$sources,
      ['file' => $original->getFile(), 'line' => $original->getLine()],
      ...$original->getTrace(),
    ], $first['file'], $first['line']);
    $exception = $this->bladeMapper->map($exception);
    $display = new Exception($exception, $request, $this->listener, realpath($this->basePath) ?: $this->basePath);
    $markdown = $this->viewFactory->make('laravel-exceptions-renderer::markdown', [
      'exception' => $display,
    ])->render();
    return $this->viewFactory->make('laravel-exceptions-renderer::show', [
      'exception' => $display,
      'exceptionAsMarkdown' => $markdown,
    ])->render();
  }
}
