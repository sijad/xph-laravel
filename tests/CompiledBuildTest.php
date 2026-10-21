<?php

namespace Xph\Laravel\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Xph\Laravel\Renderer;

class CompiledBuildTest extends TestCase {
  private string $directory;

  protected function setUp(): void {
    $this->directory = sys_get_temp_dir() . '/xph-compiled-' . bin2hex(random_bytes(8));
    mkdir($this->directory);
    $files = new Filesystem;
    foreach (['debug', 'production'] as $mode) {
      $files->copyDirectory(__DIR__ . '/fixtures/' . $mode, $this->directory . '/' . $mode);
      copy(getenv('XPH_RUNTIME_PATH') ?: __DIR__ . '/fixtures/runtime.php', $this->directory . '/' . $mode . '/runtime.php');
    }
  }

  protected function tearDown(): void {
    if (function_exists('Xph\\enableRuntimeDiagnostics')) \Xph\enableRuntimeDiagnostics(false);
    (new Filesystem)->deleteDirectory($this->directory);
  }

  public function test_compiled_components_escape_props_and_inject_dependency_assets_once(): void {
    $renderer = new Renderer($this->directory . '/production');
    $html = $renderer->render('resources/Page', ['title' => '<Hello>']);
    $html = $renderer->inject('<html><head></head><body>' . $html . $html . '</body></html>');
    $this->assertStringContainsString('&lt;Hello&gt;', $html);
    $this->assertStringContainsString('Child', $html);
    $manifest = json_decode(file_get_contents($this->directory . '/production/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $assets = [];
    foreach ($manifest as $entry) {
      foreach ($entry['assets'] as $asset) $assets[$asset['url']] = $asset;
    }
    $this->assertNotEmpty($assets);
    foreach ($assets as $asset) {
      $this->assertSame(1, substr_count($html, $asset['url']));
      $this->assertLessThan(strpos($html, $asset['type'] === 'css' ? '</head>' : '</body>'), strpos($html, $asset['url']));
    }
  }

  public function test_source_diagnostics_require_debug_build_and_runtime_opt_in(): void {
    foreach (['production', 'debug'] as $mode) {
      foreach ([false, true] as $enabled) {
        $renderer = new Renderer($this->directory . '/' . $mode, $enabled);
        $level = ob_get_level();
        ob_start();
        $error = null;
        try {
          $renderer->render('resources/Broken');
        } catch (\Throwable $caught) {
          $error = $caught;
        } finally {
          $output = ob_get_clean();
        }
        $this->assertSame($level, ob_get_level());
        $this->assertSame('', $output);
        $this->assertInstanceOf(\RuntimeException::class, $error);
        $frames = \Xph\runtimeDiagnostics($error);
        $this->assertCount($mode === 'debug' && $enabled ? 1 : 0, $frames);
        if ($mode === 'debug') {
          $this->assertSame('original failure', $error->getMessage());
          $this->assertNull($error->getPrevious());
          if ($enabled) {
            $this->assertSame('resources/Broken.xph', $frames[0]['component']);
            $this->assertGreaterThan(0, $frames[0]['location']['line']);
          }
        } else {
          $this->assertSame('Render failed for component resources/Broken.xph', $error->getMessage());
          $this->assertSame('original failure', $error->getPrevious()?->getMessage());
        }
      }
    }
  }
}
