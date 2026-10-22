# xph for Laravel

Composer package `xph/laravel` for Laravel 13 and PHP 8.3+. Render compiled xph components
from Blade with automatic CSS and JavaScript loading.

Run the application setup commands below from your Laravel application's root directory.
Development requires Node.js 22.12+ and Composer. Production needs PHP and the generated
build files; Node.js is only required when building components.

## Install

Install the package from Packagist and the xph compiler from npm:

```sh
composer require xph/laravel:^0.1
npm install --save-dev xph
```

Laravel discovers the service provider automatically. Optionally publish configuration:

```sh
php artisan vendor:publish --tag=xph-config
```

## Create a component

Create `resources/views/xph/component.xph`:

```astro
---
export interface Props {
    title: string;
}
---

<section class="greeting">
    <h1>{props.title}</h1>
    <button type="button" data-greeting>Say hello</button>
</section>

<style>
    .greeting { padding: 1rem; color: navy; }
</style>

<script>
    document.querySelectorAll('[data-greeting]').forEach((button) => {
        button.addEventListener('click', () => alert('Hello from xph!'));
    });
</script>
```

The compiler generates PHP for the markup, scoped CSS, and a JavaScript module.

## Build

Create `xph.config.ts` in the Laravel application root:

```ts
export default {
	srcDir: 'resources',
	outDir: 'storage/framework/xph',
	assetBase: '/xph/',
	debug: process.env.APP_DEBUG === 'true',
};
```

Build and publish assets after each build, including during deployment:

```sh
npx xph build
php artisan vendor:publish --tag=xph-assets --force
```

All `.xph` files under `srcDir` are compiled automatically, including imports.
Keep the generated PHP and manifest in storage; only asset files are published to `public/xph`.
The default asset publication path is `public/xph`; keep it aligned with `assetBase`.
Changing `outDir` also requires changing `build_path` in `config/xph.php`.

## Runtime diagnostics

To show original `.xph` source locations in Laravel's debug page and exception logs, build with
`debug: true` and enable Laravel's `APP_DEBUG`. With the config above, run:

```sh
APP_DEBUG=true npx xph build
```

The build reads `APP_DEBUG` from the shell environment; Laravel reads it from `.env`.
Rebuild components and refresh any cached Laravel configuration after changing it.
Source snippets and editor links require the original component files. If you change
`srcDir`, set `xph.source_path` in `config/xph.php` to the same source directory
(the default is `resource_path()`).
Leave debugging disabled in production.

## Blade

Create `resources/views/welcome.blade.php`:

```blade
<!doctype html>
<html>
<head>
    <title>My page</title>
</head>
<body>
    @xph('component', ['title' => $title])
</body>
</html>
```

Return the view from `routes/web.php`, passing the component's title:

```php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', ['title' => 'Hello from Laravel']);
});
```

Start Laravel with `php artisan serve` and open the URL printed by Artisan. The page
renders the component, loads its stylesheet, and runs its button script automatically.

Component names are relative to `resources/views/xph/`, without the `.xph` extension.
For example, `@xph('nested/Card')` renders `resources/views/xph/nested/Card.xph`.
To use a different location, publish `xph-config` and update `component_prefix`.

Props are passed as a PHP array; map Eloquent models to the arrays/scalars expected by
the component contract. No head directive is needed. Component HTML is output directly;
the xph runtime escapes dynamic text and attributes.

Middleware automatically adds dependency CSS before `</head>` and module scripts before
`</body>`, deduplicated per request. For fragments without those closing tags, CSS is
prepended and scripts appended. Render complete HTML through the normal Laravel response
flow so middleware can collect assets before sending it. JSON, downloads, encoded responses,
and event streams are left untouched. HTML response caches must store the injected result.

## Development and deployment

For local development, link the generated assets so rebuilds are available without
republishing. Add this entry to the existing `links` array in `config/filesystems.php`:

```php
public_path('xph') => storage_path('framework/xph/assets'),
```

If `public/xph` already contains published assets, move that directory aside first.
Create the initial build and the link:

```sh
npx xph build
php artisan storage:link
```

The package automatically registers an `xph` tab with Laravel's development command.
Start the development processes together:

```sh
composer run dev
```

To run the watcher separately:

```sh
npx xph dev
```

It rebuilds components and assets when files change. Refresh the browser to see updates;
no asset publication is needed while using the link. Keep `assetBase: '/xph/'` aligned
with the public link.

For deployment, build and publish assets:

```sh
npx xph build
php artisan vendor:publish --tag=xph-assets --force
```

During deployment, build and publish before serving the new release. Include both
`storage/framework/xph` and `public/xph` in the deployed application. Keep the manifest,
compiled components, runtime, and public assets from the same build together.

## Streamed HTML

For `response()->stream(...)`, explicitly set `Content-Type: text/html` and render the
Blade components inside the stream callback. Asset tags are emitted before the first
component using them, since an already sent head cannot be modified. Late stylesheet
loading can cause layout shifts. Streaming the output of `view(...)->render()` still
buffers that individual Blade view before emitting it.

```php
Route::get('/stream', function () {
    return response()->stream(function () {
        echo '<!doctype html><html><head><title>Page</title></head><body>';
        echo view('partials.greeting', ['title' => 'Hello from Laravel'])->render();
        echo '</body></html>';
    }, 200, ['Content-Type' => 'text/html']);
});
```

Here, `resources/views/partials/greeting.blade.php` contains:

```blade
@xph('component', ['title' => $title])
```

## Troubleshooting

- **Missing xph build:** run `npx xph build` and check that the compiler's `outDir`
  matches Laravel's `xph.build_path` configuration.
- **Unknown xph component:** check the component ID in `storage/framework/xph/manifest.json`.
  The configured `xph.component_prefix` is prepended to the name passed to `@xph`.
  Check that the source file is inside
  `srcDir`, rebuild, and ensure Laravel's `xph.build_path` points to that build.
- **CSS or JavaScript returns 404:** publish assets again and check that `assetBase`
  is `/xph/` when using the default `public/xph` destination.
- **Blade prints an old directive or configuration:** run `php artisan view:clear`
  or `php artisan config:clear` after changing the corresponding cached setup.

## Tests

Run the PHP test suite with Composer; Node and npm are not needed:

```sh
composer install
composer test
```

The suite uses Laravel 13 via Orchestra Testbench and committed xph compiler output
with the real PHP runtime. It covers Blade layouts and includes, compiled view reuse,
dependency assets, request isolation, streaming, response headers, failure cleanup,
and source diagnostics in debug and production builds.

Fixtures live in `tests/fixtures`; see its README for their provenance. To test an
unreleased PHP runtime from a sibling checkout:

```sh
XPH_RUNTIME_PATH=../xph/packages/xph/runtime/runtime.php composer test
```

Application components still use the Node-based xph compiler described above.
