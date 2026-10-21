<?php
declare(strict_types=1);
/** @param array{"title": string} $props */
return static function (array $props, callable|array|null $slot = null): string {
/** @var array{"title": string} $props */
$__xph_base = __LINE__;
$__xph_location = -1;
$__xph_buffer = ob_get_level();
try {
$server = [];
/** @var array{} $server */
ob_start();
?><h1><?php $__xph_location = 0;
echo \Xph\escape(\Xph\get($props, 'title', false));
?></h1><?php $__xph_location = 1;
echo \Xph\render('resources/Child', \Xph\mergeProps(), [
]);
return ob_get_clean();
} catch (\Throwable $__xph_error) {
while (ob_get_level() > $__xph_buffer) ob_end_clean();
if (\Xph\runtimeDiagnosticsEnabled()) {
\Xph\recordRuntimeError($__xph_error, 'resources/Page.xph', ([[87, 98, 5, 6], [104, 113, 5, 23]])[$__xph_location] ?? [], $__xph_error->getFile() === __FILE__ ? (([])[$__xph_error->getLine() - $__xph_base] ?? null) : null);
}
throw $__xph_error;
}
};
