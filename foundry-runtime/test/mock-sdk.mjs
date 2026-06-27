// Test double for the Foundry SDK adapter. Selected via FOUNDRY_SDK_MODULE so
// the dispatcher contract can be tested without network access or credentials.

export async function run({ operation, hostUrl, accessToken, params }) {
  if (params && typeof params.__throwCode === "string") {
    const error = new Error(params.__message ?? "mock failure");
    error.code = params.__throwCode;
    throw error;
  }

  if (params && params.__throwPlain) {
    throw new Error("plain unmapped failure");
  }

  // Echo enough to assert dispatch, but never echo the raw access token.
  return {
    operation,
    hostUrl,
    tokenLength: typeof accessToken === "string" ? accessToken.length : 0,
    params,
  };
}
