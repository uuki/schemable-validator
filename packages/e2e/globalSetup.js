const { execSync, spawn } = require('child_process');
const path = require('path');

const BASE = 'http://127.0.0.1:9400';
const PLAYGROUND_DIR = path.join(__dirname, '../../playground');
const PLUGIN_DIR = path.join(__dirname, '../../packages/wp-schemable-validator');

async function waitForReady(proc, timeoutMs = 180_000) {
  await new Promise((resolve, reject) => {
    let buf = '';
    const timer = setTimeout(() => {
      cleanup();
      reject(new Error('Timed out waiting for WP Playground "Ready!"'));
    }, timeoutMs);

    const onData = chunk => {
      const text = chunk.toString();
      process.stdout.write(text);
      buf += text;
      if (buf.includes('Ready!')) {
        cleanup();
        resolve();
      }
    };
    const onExit = code => {
      cleanup();
      reject(new Error(`wp-playground-cli exited early (code ${code})`));
    };
    function cleanup() {
      clearTimeout(timer);
      proc.stdout.off('data', onData);
      proc.off('exit', onExit);
    }

    proc.stdout.on('data', onData);
    proc.once('exit', onExit);
  });
}

async function pollPage(url, predicate, timeoutMs = 180_000, intervalMs = 2000) {
  const start = Date.now();
  let lastErr = new Error('not attempted');
  while (Date.now() - start < timeoutMs) {
    try {
      const res = await fetch(url, { signal: AbortSignal.timeout(10_000) });
      const text = await res.text();
      if (predicate(res, text)) return text;
      lastErr = new Error(`Unexpected response ${res.status}: ${text.slice(0, 200)}`);
    } catch (e) {
      lastErr = e;
    }
    await new Promise(r => setTimeout(r, intervalMs));
  }
  throw new Error(`Timed out polling ${url}: ${lastErr}`);
}

module.exports = async function globalSetup() {
  // Step 1: sync core files and install composer deps
  execSync('pnpm run sync-core', { cwd: PLAYGROUND_DIR, stdio: 'pipe' });
  execSync('composer install --no-dev', { cwd: PLUGIN_DIR, stdio: 'pipe' });

  // Step 2: start the playground. Pipe stdout only (to detect "Ready!"),
  //         inherit stderr directly so the Int8Array/WebStreams noise from
  //         @wp-playground/cli@3.1.38 never touches a Node.js Readable stream.
  const proc = spawn(
    'node_modules/.bin/wp-playground-cli',
    [
      'start',
      '--path', PLUGIN_DIR,
      '--port', '9400',
      '--blueprint', 'blueprint.json',
      '--reset',
      '--skip-browser',
    ],
    { cwd: PLAYGROUND_DIR, stdio: ['inherit', 'pipe', 'inherit'] }
  );

  try {
    // Step 3: wait for the "Ready!" banner, then let PHP workers settle
    await waitForReady(proc);
    await new Promise(r => setTimeout(r, 3000));

    // Step 4: trigger the first frontend visit so setup.php (init hook) runs
    //         and creates the example pages
    await pollPage(`${BASE}/`, (res, text) => res.ok && !text.includes('not ready'));

    // Step 5: wait until the example pages are actually available
    await pollPage(`${BASE}/schv-validate/`, (res, text) => res.ok && text.includes('Example'));
  } catch (e) {
    if (!proc.killed) proc.kill('SIGTERM');
    throw e;
  }

  return function globalTeardown() {
    if (!proc.killed) proc.kill('SIGTERM');
  };
};
