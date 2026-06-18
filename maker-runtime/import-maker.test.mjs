import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import test from "node:test";

const importer = new URL("./import-maker.mjs", import.meta.url);

function convert(source) {
  return spawnSync(process.execPath, [importer.pathname], {
    input: source,
    encoding: "utf8",
  });
}

test("creates globally unique property IDs and preserves supported actions", () => {
  const result = convert(`
    import {
      defineCreateObjectAction,
      defineDeleteObjectAction,
      defineLink,
      defineModifyObjectAction,
      defineObject,
      defineValueType,
    } from "@osdk/maker";

    const emailAddress = defineValueType({
      apiName: "emailAddress",
      displayName: "Email Address",
      type: { type: "string", constraints: [] },
      version: "1.0.0",
    });
    const users = defineObject({
      apiName: "users",
      displayName: "Users",
      pluralDisplayName: "Users",
      titlePropertyApiName: "name",
      primaryKeyPropertyApiName: "id",
      properties: {
        id: { type: "string" },
        name: { type: "string" },
        email: { type: "string", valueType: emailAddress },
      },
    });
    const posts = defineObject({
      apiName: "posts",
      displayName: "Posts",
      pluralDisplayName: "Posts",
      titlePropertyApiName: "name",
      primaryKeyPropertyApiName: "id",
      properties: {
        id: { type: "string" },
        name: { type: "string" },
        userId: { type: "string" },
      },
    });
    defineLink({
      apiName: "userPosts",
      one: {
        object: users,
        metadata: { apiName: "posts", displayName: "Post", pluralDisplayName: "Posts" },
      },
      toMany: {
        object: posts,
        metadata: { apiName: "user", displayName: "User", pluralDisplayName: "Users" },
      },
      manyForeignKeyProperty: "userId",
      cardinality: "OneToOne",
    });
    defineCreateObjectAction({ objectType: users });
    defineModifyObjectAction({ objectType: users });
    defineDeleteObjectAction({ objectType: users });
  `);

  assert.equal(result.status, 0, result.stderr);
  const ontology = JSON.parse(result.stdout);
  const propertyIds = ontology.objectTypes.flatMap((object) =>
    object.properties.map((property) => property.rid));
  assert.equal(new Set(propertyIds).size, propertyIds.length);
  assert.deepEqual(propertyIds, [
    "users::id",
    "users::name",
    "users::email",
    "posts::id",
    "posts::name",
    "posts::userId",
  ]);
  assert.deepEqual(ontology.objectTypes[0].ontologyActions, {
    create: true,
    modify: true,
    delete: true,
  });
  assert.deepEqual(
    ontology.relations[0].definition.oneToMany.oneSidePrimaryKeyToManySidePropertyMapping,
    { "users::id": "posts::userId" },
  );
  assert.equal(ontology.valueTypes[0].apiName, "emailAddress");
  assert.equal(
    ontology.objectTypes[0].properties[2].valueType.apiName,
    "emailAddress",
  );
  assert.equal(
    ontology.relations[0].definition.oneToMany.cardinalityHint,
    "ONE_TO_ONE",
  );
});

test("normalizes namespaces and many-to-many links", () => {
  const result = convert(`
    import { defineLink, defineObject, defineOntology } from "@osdk/maker";

    await defineOntology("com.example.", async () => {
      const people = defineObject({
        apiName: "people",
        displayName: "People",
        pluralDisplayName: "People",
        titlePropertyApiName: "id",
        primaryKeyPropertyApiName: "id",
        properties: { id: { type: "string" } },
      });
      const groups = defineObject({
        apiName: "groups",
        displayName: "Groups",
        pluralDisplayName: "Groups",
        titlePropertyApiName: "id",
        primaryKeyPropertyApiName: "id",
        properties: { id: { type: "string" } },
      });
      defineLink({
        apiName: "memberships",
        many: {
          object: people,
          metadata: { apiName: "groups", displayName: "Group", pluralDisplayName: "Groups" },
        },
        toMany: {
          object: groups,
          metadata: { apiName: "people", displayName: "Person", pluralDisplayName: "People" },
        },
      });
    });
  `);

  assert.equal(result.status, 0, result.stderr);
  const ontology = JSON.parse(result.stdout);
  assert.deepEqual(
    ontology.objectTypes.map((object) => object.rid),
    ["com.example.people", "com.example.groups"],
  );
  assert.equal(ontology.relations[0].definition.type, "manyToMany");
});

test("preserves edit-only properties as user edits", () => {
  const result = convert(`
    import { defineObject } from "@osdk/maker";

    defineObject({
      apiName: "customers",
      displayName: "Customers",
      pluralDisplayName: "Customers",
      titlePropertyApiName: "name",
      primaryKeyPropertyApiName: "id",
      editsEnabled: true,
      properties: {
        id: { type: "string" },
        name: { type: "string" },
        reviewerNotes: { type: "string", editOnly: true },
      },
    });
  `);

  assert.equal(result.status, 0, result.stderr);
  const ontology = JSON.parse(result.stdout);
  const notes = ontology.objectTypes[0].properties.find((property) => property.apiName === "reviewerNotes");
  const id = ontology.objectTypes[0].properties.find((property) => property.apiName === "id");
  assert.equal(notes.userEdits, true);
  assert.equal(id.userEdits, false);
});

test("preserves interface definitions", () => {
  const result = convert(`
    import { defineInterface } from "@osdk/maker";
    defineInterface({
      apiName: "Person",
      displayName: "Person",
      properties: { name: { type: "string" } },
    });
  `);

  assert.equal(result.status, 0, result.stderr);
  const ontology = JSON.parse(result.stdout);
  assert.equal(ontology.interfaceTypes.length, 1);
  assert.equal(ontology.interfaceTypes[0].apiName, "Person");
});

test("allows erased type-only imports", () => {
  const result = convert(`
    import type { ExternalDefinition } from "./types.js";
    import { defineObject, type ObjectDefinition } from "@osdk/maker";

    defineObject({
      apiName: "people",
      displayName: "People",
      pluralDisplayName: "People",
      titlePropertyApiName: "id",
      primaryKeyPropertyApiName: "id",
      properties: { id: { type: "string" } },
    });
  `);

  assert.equal(result.status, 0, result.stderr);
  assert.equal(JSON.parse(result.stdout).objectTypes.length, 1);
});
