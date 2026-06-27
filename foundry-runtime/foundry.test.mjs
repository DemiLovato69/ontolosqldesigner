import { strict as assert } from "node:assert";
import { spawn } from "node:child_process";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { test } from "node:test";

const here = dirname(fileURLToPath(import.meta.url));
const dispatcher = join(here, "foundry.mjs");
const mockSdk = join(here, "test", "mock-sdk.mjs");
const SECRET_TOKEN = "super-secret-access-token-value";

function invoke(request) {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [dispatcher], {
      env: { ...process.env, FOUNDRY_SDK_MODULE: mockSdk },
    });

    let stdout = "";
    let stderr = "";
    child.stdout.on("data", (d) => (stdout += d));
    child.stderr.on("data", (d) => (stderr += d));
    child.on("error", reject);
    child.on("close", () => resolve({ stdout, stderr }));

    child.stdin.write(JSON.stringify(request));
    child.stdin.end();
  });
}

test("returns a success envelope and never echoes the access token", async () => {
  const { stdout } = await invoke({
    operation: "getDataset",
    hostUrl: "https://example.palantirfoundry.com",
    accessToken: SECRET_TOKEN,
    params: { datasetRid: "ri.foundry.main.dataset.abc" },
  });

  const envelope = JSON.parse(stdout);
  assert.equal(envelope.ok, true);
  assert.equal(envelope.data.operation, "getDataset");
  assert.equal(envelope.data.params.datasetRid, "ri.foundry.main.dataset.abc");
  assert.equal(envelope.data.tokenLength, SECRET_TOKEN.length);
  assert.ok(!stdout.includes(SECRET_TOKEN), "access token must not appear in output");
});

test("rejects an unsupported operation", async () => {
  const { stdout } = await invoke({
    operation: "deleteEverything",
    hostUrl: "https://example.palantirfoundry.com",
    accessToken: SECRET_TOKEN,
    params: {},
  });

  const envelope = JSON.parse(stdout);
  assert.equal(envelope.ok, false);
  assert.equal(envelope.error.code, "foundry_upstream_unavailable");
});

test("requires an access token", async () => {
  const { stdout } = await invoke({
    operation: "getDataset",
    hostUrl: "https://example.palantirfoundry.com",
    accessToken: "",
    params: { datasetRid: "ri.x" },
  });

  const envelope = JSON.parse(stdout);
  assert.equal(envelope.ok, false);
  assert.equal(envelope.error.code, "foundry_connection_required");
});

test("passes through coded SDK errors", async () => {
  const { stdout } = await invoke({
    operation: "getDataset",
    hostUrl: "https://example.palantirfoundry.com",
    accessToken: SECRET_TOKEN,
    params: { datasetRid: "ri.x", __throwCode: "foundry_resource_not_found" },
  });

  const envelope = JSON.parse(stdout);
  assert.equal(envelope.ok, false);
  assert.equal(envelope.error.code, "foundry_resource_not_found");
});

test("maps unknown thrown errors to an upstream failure", async () => {
  const { stdout } = await invoke({
    operation: "getDataset",
    hostUrl: "https://example.palantirfoundry.com",
    accessToken: SECRET_TOKEN,
    params: { datasetRid: "ri.x", __throwPlain: true },
  });

  const envelope = JSON.parse(stdout);
  assert.equal(envelope.ok, false);
  assert.equal(envelope.error.code, "foundry_upstream_unavailable");
});

test("fails cleanly on invalid stdin", async () => {
  const result = await new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [dispatcher], {
      env: { ...process.env, FOUNDRY_SDK_MODULE: mockSdk },
    });
    let stdout = "";
    child.stdout.on("data", (d) => (stdout += d));
    child.on("error", reject);
    child.on("close", () => resolve(stdout));
    child.stdin.write("not json");
    child.stdin.end();
  });

  const envelope = JSON.parse(result);
  assert.equal(envelope.ok, false);
  assert.equal(envelope.error.code, "foundry_upstream_unavailable");
});
