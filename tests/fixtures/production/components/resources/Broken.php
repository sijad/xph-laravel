<?php
declare(strict_types=1);
/** @param array{} $props */
return static function (array $props, callable|array|null $slot = null): string {
/** @var array{} $props */
$__xph_buffer = ob_get_level();
try {
/** @param array{} $props
 * @return array{"name": \Closure(): string} */
$run = static function (array $props): array {

return ['name' => static function (): string { throw new RuntimeException('original failure'); }];

};
$server = $run($props);
/** @var array{"name": \Closure(): string} $server */
ob_start();
?><p><?php echo \Xph\escape((\Xph\get($server, 'name', false))());
?></p><?php return ob_get_clean();
} catch (\Throwable $__xph_error) {
while (ob_get_level() > $__xph_buffer) ob_end_clean();
throw new \RuntimeException('Render failed for component resources/Broken.xph', 0, $__xph_error);
}
};
