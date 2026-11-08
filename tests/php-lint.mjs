import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime } from '@php-wasm/node';
import fs from 'node:fs';

const php = new PHP(await loadNodeRuntime(process.env.EEW_TEST_PHP || '7.4', { emscriptenOptions: { processId: 1 } }));
try {
  for (const file of ['sobelz-embla-carousel-widget.php', ...fs.readdirSync('includes').map(name => 'includes/' + name)]) {
    const source = fs.readFileSync(file).toString('base64');
    const result = await php.run({ code: `<?php token_get_all(base64_decode('${source}'), TOKEN_PARSE); echo 'OK';` });
    if (result.text !== 'OK') throw new Error(file + ': ' + result.errors);
    console.log('PHP syntax OK:', file);
  }
} finally {
  php.exit();
}
