<?php
declare(strict_types=1);
/** @param array{} $props */
return static function (array $props, callable|array|null $slot = null): string {
/** @var array{} $props */
$__xph_base = __LINE__;
$__xph_location = -1;
$__xph_buffer = ob_get_level();
try {
/** @param array{} $props
 * @return array{"name": \Closure(): string} */
$run = static function (array $props): array {

return ['name' => static function (): string { throw new RuntimeException('original failure'); }];

};
$__xph_location = 0;
$server = $run($props);
/** @var array{"name": \Closure(): string} $server */
ob_start();
?><p><?php $__xph_location = 1;
echo \Xph\escape((\Xph\get($server, 'name', false))());
?></p><?php return ob_get_clean();
} catch (\Throwable $__xph_error) {
while (ob_get_level() > $__xph_buffer) ob_end_clean();
if (\Xph\runtimeDiagnosticsEnabled()) {
\Xph\recordRuntimeError($__xph_error, 'resources/Broken.xph', ([[69, 169, 4, 19], [183, 196, 7, 5]])[$__xph_location] ?? [], $__xph_error->getFile() === __FILE__ ? ((['7' => [69, 69, 4, 19], '8' => [70, 168, 5, 1], '9' => [169, 169, 6, 1]])[$__xph_error->getLine() - $__xph_base] ?? null) : null);
}
throw $__xph_error;
}
};
