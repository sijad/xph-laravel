<?php
declare(strict_types=1);
/** @param array{"title": string} $props */
return static function (array $props, callable|array|null $slot = null): string {
/** @var array{"title": string} $props */
$__xph_buffer = ob_get_level();
try {
$server = [];
/** @var array{} $server */
ob_start();
?><h1><?php echo \Xph\escape(\Xph\get($props, 'title', false));
?></h1><?php echo \Xph\render('resources/Child', \Xph\mergeProps(), [
]);
return ob_get_clean();
} catch (\Throwable $__xph_error) {
while (ob_get_level() > $__xph_buffer) ob_end_clean();
throw new \RuntimeException('Render failed for component resources/Page.xph', 0, $__xph_error);
}
};
