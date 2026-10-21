<?php
declare(strict_types=1);
/** @param array{} $props */
return static function (array $props, callable|array|null $slot = null): string {
/** @var array{} $props */
$__xph_buffer = ob_get_level();
try {
$server = [];
/** @var array{} $server */
ob_start();
?><span data-xph-6cb10cead485="">Child</span><?php return ob_get_clean();
} catch (\Throwable $__xph_error) {
while (ob_get_level() > $__xph_buffer) ob_end_clean();
throw new \RuntimeException('Render failed for component resources/Child.xph', 0, $__xph_error);
}
};
