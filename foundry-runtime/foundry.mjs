// Foundry Platform SDK bridge.
//
// Reads a JSON request on stdin:
//   { "operation": string, "hostUrl": string, "accessToken": string, "params": object }
//
// Writes a JSON envelope on stdout:
//   { "ok": true, "data": <object> }
//   { "ok": false, "error": { "code": string, "message": string } }
//
// The access token is read from stdin (never argv) and is never echoed back.

const OPERATIONS = new Set([
  "whoami",
  "listSpaces",
  "listFolderChildren",
  "listOntologies",
  "listDatasets",
  "getDataset",
  "getDatasetSchema",
  "listFiles",
  "getFile",
  "search",
]);

main();

async function main() {
  let request;
  try {
    request = JSON.parse(await readStdin());
  } catch {
    return emitError("foundry_upstream_unavailable", "Invalid runtime request payload.");
  }

  const { operation, hostUrl, accessToken } = request ?? {};
  const params = request?.params ?? {};

  if (typeof operation !== "string" || !OPERATIONS.has(operation)) {
    return emitError("foundry_upstream_unavailable", `Unsupported Foundry operation: ${operation}`);
  }
  if (typeof hostUrl !== "string" || hostUrl === "") {
    return emitError("foundry_upstream_unavailable", "A Foundry host URL is required.");
  }
  if (typeof accessToken !== "string" || accessToken === "") {
    return emitError("foundry_connection_required", "A Foundry access token is required.");
  }

  let adapter;
  try {
    adapter = await loadAdapter();
  } catch (error) {
    return emitError("foundry_upstream_unavailable", `Foundry SDK failed to load: ${messageOf(error)}`);
  }

  try {
    const data = await adapter.run({ operation, hostUrl, accessToken, params });
    emit({ ok: true, data: data ?? {} });
  } catch (error) {
    emitError(codeOf(error), messageOf(error));
  }
}

// The SDK adapter is swappable via FOUNDRY_SDK_MODULE so the dispatch contract
// can be tested without network access or real credentials.
async function loadAdapter() {
  const override = process.env.FOUNDRY_SDK_MODULE;
  const specifier = override && override !== "" ? override : "./sdk.mjs";
  return import(specifier);
}

function codeOf(error) {
  const code = error && typeof error.code === "string" ? error.code : "";
  if (code.startsWith("foundry_")) return code;
  return "foundry_upstream_unavailable";
}

function messageOf(error) {
  if (error instanceof Error) return error.message;
  return String(error);
}

function emit(envelope) {
  process.stdout.write(JSON.stringify(envelope));
}

function emitError(code, message) {
  emit({ ok: false, error: { code, message } });
  process.exitCode = 0;
}

function readStdin() {
  return new Promise((resolve, reject) => {
    let data = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => {
      data += chunk;
    });
    process.stdin.on("end", () => resolve(data));
    process.stdin.on("error", reject);
  });
}
