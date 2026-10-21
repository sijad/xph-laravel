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
 * @return array{} */
$run = static function (array $props): array {

throw 'failure';

};
$__xph_location = 0;
$server = $run($props);
/** @var array{} $server */
ob_start();
?><p>Unreachable</p><?php return ob_get_clean();
} catch (\Throwable $__xph_error) {
while (ob_get_level() > $__xph_buffer) ob_end_clean();
if (\Xph\runtimeDiagnosticsEnabled()) {
\Xph\recordRuntimeError($__xph_error, 'component.xph', ([[18, 36, 1, 19]])[$__xph_location] ?? [], $__xph_error->getFile() === __FILE__ ? ((['7' => [18, 18, 1, 19], '8' => [19, 35, 2, 1], '9' => [36, 36, 3, 1]])[$__xph_error->getLine() - $__xph_base] ?? null) : null);
}
throw $__xph_error;
}
};
