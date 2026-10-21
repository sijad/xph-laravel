# Compiler fixtures

Generated with xph commit `cb5c7b9` from the sibling `../xph` checkout.
`source/resources` contains the inputs; `debug` and `production` contain the real
compiler output. `runtime.php` is the unmodified upstream runtime, shared by both
builds when PHPUnit copies them into temporary directories. The upstream MIT
license is included in `LICENSE`.

Normal tests need only PHP and Composer. To refresh these fixtures after building
the sibling compiler, run this from the repository root (Node is needed only for
this maintenance step):

```sh
node --input-type=module <<'JS'
import { build } from '../xph/packages/xph/dist/index.js';
import { copyFile, unlink } from 'node:fs/promises';
import { resolve } from 'node:path';
for (const debug of [false, true]) {
  const outDir = resolve('tests/fixtures', debug ? 'debug' : 'production');
  const result = await build({
    root: resolve('tests/fixtures/source'),
    srcDir: '.', outDir, assetBase: '/xph/', debug,
  });
  if (result.diagnostics.some(d => d.severity === 'error')) {
    throw new Error(JSON.stringify(result.diagnostics));
  }
  await copyFile(`${outDir}/runtime.php`, 'tests/fixtures/runtime.php');
  await unlink(`${outDir}/runtime.php`);
}
JS
```

Review generated changes, update the upstream commit above, and run `composer test`.
`XPH_RUNTIME_PATH` can override the runtime for both PHP test classes. PHP functions
are process-global, so one suite run uses one runtime version.

`server-error` is a standalone `compile()` fixture with `debug: true` and
`scopeId: 'component'`, using its adjacent `component.xph` as input. It reproduces
PHP's `Can only throw objects` error and source IDs relative to `resources`.
Its manifest registers the compiled file as `component` with no assets or dependencies.
