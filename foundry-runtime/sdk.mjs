// Real Foundry Platform SDK adapter.
//
// Each exported operation maps to a documented @osdk/foundry Platform API call.
// Errors are normalized to the stable foundry_* codes the PHP layer expects.
//
// Method bindings verified against @osdk/foundry 2.65.x / @osdk/client 2.40.x:
//   Admin.Users.getCurrent(client)
//   Filesystem.Spaces.list(client, { pageSize, pageToken })
//   Filesystem.Folders.children(client, folderRid, { pageSize, pageToken })
//   Filesystem.Resources.getByPath(client, { path })
//   Ontologies.OntologiesV2.list(client)
//   Datasets.Datasets.get(client, datasetRid)
//   Datasets.Datasets.getSchema(client, datasetRid, { branchName, versionId, endTransactionRid })
//   Datasets.Files.list(client, datasetRid, { branchName, pathPrefix, pageSize, pageToken })
//   Datasets.Files.get(client, datasetRid, filePath, { branchName })

import { createPlatformClient } from "@osdk/client";
import { Admin, Datasets, Filesystem, Ontologies } from "@osdk/foundry";

// The SDK only sets a custom "Fetch-User-Agent" header; Node's fetch otherwise
// sends "User-Agent: node", which some Foundry gateways/WAFs reject with HTTP
// 406 before the request reaches the API. Inject a real User-Agent (overridable
// via FOUNDRY_HTTP_USER_AGENT) on every request.
const DEFAULT_USER_AGENT = "OntoloSQL-Designer/1.0 (+https://ontolosql-designer.ondigitalocean.app)";

function platformFetch() {
  const ua = process.env.FOUNDRY_HTTP_USER_AGENT && process.env.FOUNDRY_HTTP_USER_AGENT.length > 0
    ? process.env.FOUNDRY_HTTP_USER_AGENT
    : DEFAULT_USER_AGENT;

  return async (input, init = {}) => {
    const headers = new Headers(init.headers ?? undefined);
    if (typeof Request !== "undefined" && input instanceof Request) {
      for (const [key, value] of input.headers) {
        if (!headers.has(key)) headers.set(key, value);
      }
    }
    if (!headers.has("user-agent")) headers.set("User-Agent", ua);

    // The SDK sets "Content-Type: application/json" on every request, including
    // bodyless GETs. Some gateways reject a GET that declares a JSON content
    // type with a non-standard 406, so drop it when there is no body.
    const method = String(init.method ?? (input instanceof Request ? input.method : "GET")).toUpperCase();
    const hasBody = init.body != null || (input instanceof Request && input.body != null);
    if (!hasBody && (method === "GET" || method === "DELETE")) {
      headers.delete("content-type");
    }

    const response = await fetch(input, { ...init, headers });

    if (!response.ok && (response.status === 406 || response.status === 415 || response.status >= 500)) {
      try {
        const bodyText = (await response.clone().text()).slice(0, 400).replace(/\s+/g, " ");
        const respHeaders = ["content-type", "server", "via", "www-authenticate", "x-akamai-request-id"]
          .map((k) => `${k}=${response.headers.get(k) ?? ""}`)
          .join(" ");
        const sentHeaders = [...headers.keys()].filter((k) => k.toLowerCase() !== "authorization").join(",");
        process.stderr.write(
          `[foundry-runtime] HTTP ${response.status} "${response.statusText}" sent{${sentHeaders}} resp{${respHeaders}} body=${bodyText}\n`,
        );
      } catch { /* ignore diagnostic failures */ }
    }

    return response;
  };
}

export async function run({ operation, hostUrl, accessToken, params }) {
  const client = createPlatformClient(hostUrl, async () => accessToken, undefined, platformFetch());

  try {
    switch (operation) {
      case "whoami":
        return await Admin.Users.getCurrent(client);

      case "listSpaces":
        return await Filesystem.Spaces.list(
          client,
          queryParams({ pageSize: params.pageSize, pageToken: params.pageToken }),
        );

      case "listFolderChildren":
        return await Filesystem.Folders.children(
          client,
          requireParam(params.folderRid, "folderRid"),
          queryParams({ pageSize: params.pageSize, pageToken: params.pageToken }),
        );

      case "listOntologies":
        return await Ontologies.OntologiesV2.list(client);

      case "getDataset":
        return await Datasets.Datasets.get(client, requireParam(params.datasetRid, "datasetRid"));

      case "getDatasetSchema":
        return await Datasets.Datasets.getSchema(
          client,
          requireParam(params.datasetRid, "datasetRid"),
          queryParams({
            branchName: params.branch ?? params.branchName,
            versionId: params.versionId,
            endTransactionRid: params.endTransactionRid,
          }),
        );

      case "listFiles":
        return await Datasets.Files.list(
          client,
          requireParam(params.datasetRid, "datasetRid"),
          queryParams({
            branchName: params.branch ?? params.branchName,
            pathPrefix: params.pathPrefix,
            pageSize: params.pageSize,
            pageToken: params.pageToken,
          }),
        );

      case "getFile":
        return await Datasets.Files.get(
          client,
          requireParam(params.datasetRid, "datasetRid"),
          requireParam(params.filePath, "filePath"),
          queryParams({ branchName: params.branch ?? params.branchName }),
        );

      case "listDatasets":
        return await Filesystem.Folders.children(
          client,
          requireParam(params.folderRid, "folderRid"),
          queryParams({ pageSize: params.pageSize, pageToken: params.pageToken }),
        );

      case "search":
        return await Filesystem.Resources.getByPath(client, {
          path: requireParam(params.path, "path"),
        });

      default:
        throw coded("foundry_upstream_unavailable", `Unsupported Foundry operation: ${operation}`);
    }
  } catch (error) {
    // Surface the underlying SDK error on stderr (no token is ever included) so
    // the PHP layer can log it for diagnosis. stdout stays the JSON envelope.
    try {
      process.stderr.write(`[foundry-runtime] ${operation} failed: ${describeError(error)}\n`);
    } catch { /* ignore */ }
    throw mapError(error);
  }
}

function describeError(error) {
  const parts = [];
  const status = error?.statusCode ?? error?.status ?? error?.response?.status;
  if (status) parts.push(`status=${status}`);
  if (error?.errorCode) parts.push(`errorCode=${error.errorCode}`);
  if (error?.errorName) parts.push(`errorName=${error.errorName}`);
  if (error?.errorInstanceId) parts.push(`errorInstanceId=${error.errorInstanceId}`);
  if (error?.message) parts.push(`message=${error.message}`);
  if (!parts.length) parts.push(String(error));
  return parts.join(" ");
}

function requireParam(value, name) {
  if (typeof value !== "string" || value === "") {
    throw coded("foundry_resource_not_found", `Missing required parameter: ${name}`);
  }
  return value;
}

function queryParams(values) {
  const result = {};
  for (const [key, value] of Object.entries(values)) {
    if (value !== undefined && value !== null && value !== "") {
      result[key] = value;
    }
  }
  return result;
}

function coded(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

function mapError(error) {
  if (error && typeof error.code === "string" && error.code.startsWith("foundry_")) {
    return error;
  }

  const status = error?.statusCode ?? error?.status ?? error?.response?.status;
  const name = error?.errorName ?? error?.errorCode;
  const message = error?.message ?? "Foundry request failed.";
  const detail = name && !String(message).includes(name) ? `${name}: ${message}` : message;

  if (status === 401 || status === 403) {
    return coded(
      "foundry_access_denied",
      `Foundry denied access${name ? ` (${name})` : ""}. The connection may be missing the required scopes/permissions.`,
    );
  }
  if (status === 404) {
    return coded("foundry_resource_not_found", "The requested Foundry resource was not found.");
  }
  if (status === 429) {
    return coded("foundry_rate_limited", "Foundry rate limit reached.");
  }

  return coded("foundry_upstream_unavailable", String(detail));
}
