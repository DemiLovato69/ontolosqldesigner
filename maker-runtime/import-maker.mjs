import ts from "typescript";
import * as maker from "@osdk/maker";

const source = await readStdin();
const file = ts.createSourceFile(
  "ontology.mts",
  source,
  ts.ScriptTarget.Latest,
  true,
  ts.ScriptKind.TS,
);

const allowedCalls = new Set([
  "defineCreateObjectAction",
  "defineDeleteObjectAction",
  "defineLink",
  "defineModifyObjectAction",
  "defineObject",
  "defineValueType",
]);

const bindings = new Map();

try {
  validateImports(file.statements);
  const ontologyCall = findOntologyCall(file.statements);
  const ontology = ontologyCall
    ? await runOntologyCall(ontologyCall)
    : await maker.defineOntology("", async () => executeStatements(file.statements));
  process.stdout.write(JSON.stringify(normalizeOntology(ontology)));
} catch (error) {
  const message = error instanceof Error ? error.message : String(error);
  process.stderr.write(`${message}\n`);
  process.exitCode = 1;
}

function validateImports(statements) {
  for (const statement of statements) {
    if (!ts.isImportDeclaration(statement)) continue;
    if (statement.importClause?.isTypeOnly) continue;
    if (!ts.isStringLiteral(statement.moduleSpecifier)
      || statement.moduleSpecifier.text !== "@osdk/maker") {
      fail(statement, "Only imports from @osdk/maker are supported");
    }
    const clause = statement.importClause;
    if (!clause || clause.name || !clause.namedBindings
      || !ts.isNamedImports(clause.namedBindings)) {
      fail(statement, "Maker imports must use named imports");
    }
    for (const element of clause.namedBindings.elements) {
      if (element.isTypeOnly) continue;
      const importedName = element.propertyName?.text ?? element.name.text;
      if (element.name.text !== importedName
        || (importedName !== "defineOntology" && !allowedCalls.has(importedName))) {
        fail(element, `Unsupported Maker import "${importedName}"`);
      }
    }
  }
}

function findOntologyCall(statements) {
  for (const statement of statements) {
    if (!ts.isExpressionStatement(statement)) continue;
    const expression = unwrap(statement.expression);
    if (ts.isCallExpression(expression)
      && ts.isIdentifier(expression.expression)
      && expression.expression.text === "defineOntology") {
      return expression;
    }
  }
  return null;
}

async function runOntologyCall(call) {
  const namespace = call.arguments[0] ? evaluate(call.arguments[0]) : "";
  const callback = call.arguments[1];
  if (typeof namespace !== "string") {
    fail(call, "defineOntology namespace must be a string literal");
  }
  if (!callback || (!ts.isArrowFunction(callback) && !ts.isFunctionExpression(callback))) {
    fail(call, "defineOntology requires an inline callback");
  }

  return maker.defineOntology(namespace, async () => {
    if (ts.isBlock(callback.body)) {
      await executeStatements(callback.body.statements);
      return;
    }
    evaluate(callback.body);
  });
}

async function executeStatements(statements) {
  for (const statement of statements) {
    if (ts.isImportDeclaration(statement)
      || ts.isExportDeclaration(statement)
      || ts.isEmptyStatement(statement)) {
      continue;
    }
    if (ts.isExpressionStatement(statement)
      && findOntologyCall([statement])) {
      continue;
    }
    if (ts.isVariableStatement(statement)) {
      for (const declaration of statement.declarationList.declarations) {
        if (!ts.isIdentifier(declaration.name) || !declaration.initializer) {
          fail(declaration, "Maker definitions must use initialized identifier declarations");
        }
        bindings.set(declaration.name.text, evaluate(declaration.initializer, true));
      }
      continue;
    }
    if (ts.isExpressionStatement(statement)) {
      evaluate(statement.expression, true);
      continue;
    }

    fail(statement, "Unsupported executable TypeScript statement");
  }
}

function evaluate(node, allowMakerCall = false) {
  node = unwrap(node);

  if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) {
    return node.text;
  }
  if (ts.isNumericLiteral(node)) return Number(node.text);
  if (node.kind === ts.SyntaxKind.TrueKeyword) return true;
  if (node.kind === ts.SyntaxKind.FalseKeyword) return false;
  if (node.kind === ts.SyntaxKind.NullKeyword) return null;

  if (ts.isPrefixUnaryExpression(node)
    && node.operator === ts.SyntaxKind.MinusToken
    && ts.isNumericLiteral(node.operand)) {
    return -Number(node.operand.text);
  }

  if (ts.isIdentifier(node)) {
    if (node.text === "undefined") return undefined;
    if (bindings.has(node.text)) return bindings.get(node.text);
    fail(node, `Unknown identifier "${node.text}"`);
  }

  if (ts.isArrayLiteralExpression(node)) {
    return node.elements.map((element) => {
      if (ts.isSpreadElement(element)) fail(element, "Array spreads are not supported");
      return evaluate(element);
    });
  }

  if (ts.isObjectLiteralExpression(node)) {
    const result = {};
    for (const property of node.properties) {
      if (ts.isPropertyAssignment(property)) {
        result[propertyName(property.name)] = evaluate(property.initializer);
      } else if (ts.isShorthandPropertyAssignment(property)) {
        result[property.name.text] = evaluate(property.name);
      } else {
        fail(property, "Object methods, accessors, and spreads are not supported");
      }
    }
    return result;
  }

  if (ts.isCallExpression(node)) {
    if (!allowMakerCall
      || !ts.isIdentifier(node.expression)
      || !allowedCalls.has(node.expression.text)) {
      fail(node, "Only top-level declarative @osdk/maker calls are supported");
    }
    const fn = maker[node.expression.text];
    if (typeof fn !== "function") {
      fail(node, `Unsupported Maker function "${node.expression.text}"`);
    }
    return fn(...node.arguments.map((argument) => evaluate(argument)));
  }

  fail(node, "Unsupported TypeScript expression");
}

function unwrap(node) {
  while (ts.isAwaitExpression(node)
    || ts.isParenthesizedExpression(node)
    || ts.isAsExpression(node)
    || ts.isSatisfiesExpression(node)
    || ts.isNonNullExpression(node)
    || ts.isTypeAssertionExpression(node)) {
    node = node.expression;
  }
  return node;
}

function propertyName(name) {
  if (ts.isIdentifier(name) || ts.isStringLiteral(name) || ts.isNumericLiteral(name)) {
    return name.text;
  }
  fail(name, "Computed property names are not supported");
}

function fail(node, message) {
  const position = file.getLineAndCharacterOfPosition(node.getStart(file));
  throw new Error(`${message} at line ${position.line + 1}, column ${position.character + 1}`);
}

async function readStdin() {
  const chunks = [];
  for await (const chunk of process.stdin) chunks.push(chunk);
  return Buffer.concat(chunks).toString("utf8");
}

function normalizeOntology(ir) {
  const ontology = ir.ontology ?? {};
  assertEmptyRecord(ontology.sharedPropertyTypes, "Shared property types are not supported");
  assertEmptyRecord(ontology.interfaceTypes, "Interfaces are not supported");
  assertEmptyRecord(ir.importedOntology?.objectTypes, "Imported ontology entities are not supported");
  assertEmptyRecord(ir.importedOntology?.sharedPropertyTypes, "Imported ontology entities are not supported");
  assertEmptyRecord(ir.importedOntology?.interfaceTypes, "Imported ontology entities are not supported");
  assertEmptyRecord(ir.importedOntology?.linkTypes, "Imported ontology entities are not supported");
  assertEmptyRecord(ir.importedOntology?.actionTypes, "Imported ontology entities are not supported");

  const actionsByObject = normalizeActions(ontology.actionTypes ?? {});
  const objectTypes = Object.entries(ontology.objectTypes ?? {}).map(([key, block]) => {
    const object = block.objectType ?? {};
    const objectRid = object.apiName ?? key;
    const properties = Object.entries(object.propertyTypes ?? {}).map(([propertyKey, property]) => {
      const apiName = property.apiName ?? propertyKey;
      return {
        id: apiName,
        rid: propertyRid(objectRid, apiName),
        apiName,
        displayMetadata: property.displayMetadata ?? {},
        baseType: normalizePropertyBaseType(property.type),
        indexedForSearch: property.indexedForSearch ?? false,
        valueType: property.valueType,
        dataConstraints: property.nullability
          ? { nullability: property.nullability.noNulls ? "NO_NULLS" : "NULLABLE" }
          : {},
      };
    });

    return {
      id: objectRid,
      rid: objectRid,
      apiName: objectRid,
      displayMetadata: object.displayMetadata ?? {},
      titlePropertyId: object.titlePropertyTypeRid,
      primaryKeys: object.primaryKeys ?? [],
      properties,
      ontologyActions: actionsByObject.get(objectRid) ?? emptyActions(),
    };
  });

  const relations = Object.entries(ontology.linkTypes ?? {}).map(([key, block]) => {
    const link = block.linkType ?? {};
    const definition = link.definition ?? {};
    if (definition.type === "oneToMany") {
      const oneToMany = definition.oneToMany ?? {};
      const oneRid = oneToMany.objectTypeRidOneSide;
      const manyRid = oneToMany.objectTypeRidManySide;
      const mapping = {};
      let manySideForeignKeyPropertyId = oneToMany.manySideForeignKeyPropertyId;
      for (const entry of oneToMany.oneSidePrimaryKeyToManySidePropertyMapping ?? []) {
        const from = entry?.from?.apiName;
        const to = entry?.to?.apiName;
        if (oneRid && manyRid && from && to) {
          mapping[propertyRid(oneRid, from)] = propertyRid(manyRid, to);
          manySideForeignKeyPropertyId ??= to;
        }
      }
      return {
        id: link.id ?? key,
        rid: link.id ?? key,
        definition: {
          type: "oneToMany",
          oneToMany: {
            ...oneToMany,
            oneSidePrimaryKeyToManySidePropertyMapping: mapping,
            manySideForeignKeyPropertyId,
          },
        },
      };
    }
    return {
      id: link.id ?? key,
      rid: link.id ?? key,
      definition,
    };
  });

  const valueTypes = (ir.valueTypes?.valueTypes ?? []).map((valueType) => {
    const metadata = valueType.metadata ?? {};
    const version = valueType.versions?.[0] ?? {};
    return {
      id: metadata.apiName,
      rid: metadata.apiName,
      apiName: metadata.apiName,
      displayMetadata: metadata.displayMetadata ?? {},
      version: version.version ?? "1.0.0",
      baseType: version.baseType ?? { type: "string" },
      constraints: version.constraints ?? [],
    };
  });

  const normalized = { objectTypes, relations, valueTypes };
  validateNormalizedOntology(normalized);
  return normalized;
}

function normalizeActions(actionTypes) {
  const actions = new Map();
  for (const [key, block] of Object.entries(actionTypes)) {
    const action = block.actionType ?? {};
    const rules = action.actionTypeLogic?.logic?.rules ?? [];
    if (rules.length !== 1) {
      throw new Error(`Unsupported Maker action "${key}"`);
    }
    const rule = rules[0];
    let objectRid;
    let actionName;
    if (rule.type === "addObjectRule") {
      objectRid = rule.addObjectRule?.objectTypeId;
      actionName = "create";
    } else if (rule.type === "modifyObjectRule") {
      objectRid = affectedObject(action);
      actionName = "modify";
    } else if (rule.type === "deleteObjectRule") {
      objectRid = affectedObject(action);
      actionName = "delete";
    } else {
      throw new Error(`Unsupported Maker action "${key}"`);
    }
    if (!objectRid) {
      throw new Error(`Maker action "${key}" does not reference an object type`);
    }
    const objectActions = actions.get(objectRid) ?? emptyActions();
    objectActions[actionName] = true;
    actions.set(objectRid, objectActions);
  }
  return actions;
}

function affectedObject(action) {
  const affected = action.metadata?.entities?.affectedObjectTypes ?? [];
  return affected.length === 1 ? affected[0] : undefined;
}

function emptyActions() {
  return { create: false, modify: false, delete: false };
}

function propertyRid(objectRid, propertyApiName) {
  return `${objectRid}::${propertyApiName}`;
}

function normalizePropertyBaseType(baseType) {
  if (!baseType || typeof baseType !== "object") {
    return { type: "string" };
  }
  if (baseType.type !== "array") {
    return baseType;
  }
  const subType = baseType.array?.subtype ?? baseType.array?.subType;
  return {
    type: "array",
    subType: normalizePropertyBaseType(subType),
  };
}

function assertEmptyRecord(record, message) {
  if (record && Object.keys(record).length > 0) {
    throw new Error(message);
  }
}

function validateNormalizedOntology(ontology) {
  const objectRids = new Set();
  const propertyRids = new Set();
  const propertiesByObject = new Map();

  for (const object of ontology.objectTypes) {
    if (!object.rid || objectRids.has(object.rid)) {
      throw new Error(`Duplicate or missing object type identifier "${object.rid ?? ""}"`);
    }
    objectRids.add(object.rid);
    const localProperties = new Set();
    for (const property of object.properties) {
      if (!property.apiName || localProperties.has(property.apiName)) {
        throw new Error(`Duplicate or missing property "${property.apiName ?? ""}" on object "${object.apiName}"`);
      }
      if (!property.rid || propertyRids.has(property.rid)) {
        throw new Error(`Duplicate property identifier "${property.rid ?? ""}"`);
      }
      localProperties.add(property.apiName);
      propertyRids.add(property.rid);
    }
    propertiesByObject.set(object.rid, localProperties);
    if (object.titlePropertyId && !localProperties.has(object.titlePropertyId)) {
      throw new Error(`Title property "${object.titlePropertyId}" was not found on object "${object.apiName}"`);
    }
    for (const primaryKey of object.primaryKeys) {
      if (!localProperties.has(primaryKey)) {
        throw new Error(`Primary key "${primaryKey}" was not found on object "${object.apiName}"`);
      }
    }
  }

  const relationRids = new Set();
  for (const relation of ontology.relations) {
    if (!relation.rid || relationRids.has(relation.rid)) {
      throw new Error(`Duplicate or missing relation identifier "${relation.rid ?? ""}"`);
    }
    relationRids.add(relation.rid);
    const definition = relation.definition ?? {};
    if (definition.type === "oneToMany") {
      const link = definition.oneToMany ?? {};
      validateObjectReference(objectRids, link.objectTypeRidOneSide, relation.rid);
      validateObjectReference(objectRids, link.objectTypeRidManySide, relation.rid);
      const mapping = link.oneSidePrimaryKeyToManySidePropertyMapping ?? {};
      if (Object.keys(mapping).length === 0) {
        throw new Error(`Relation "${relation.rid}" has no property mapping`);
      }
      for (const [from, to] of Object.entries(mapping)) {
        if (!propertyRids.has(from) || !propertyRids.has(to)) {
          throw new Error(`Relation "${relation.rid}" references an unknown property`);
        }
      }
      if (!propertiesByObject.get(link.objectTypeRidManySide)?.has(link.manySideForeignKeyPropertyId)) {
        throw new Error(`Relation "${relation.rid}" references an unknown foreign key property`);
      }
    } else if (definition.type === "manyToMany") {
      const link = definition.manyToMany ?? {};
      validateObjectReference(objectRids, link.objectTypeRidA, relation.rid);
      validateObjectReference(objectRids, link.objectTypeRidB, relation.rid);
    } else {
      throw new Error(`Unsupported relation type "${definition.type ?? ""}"`);
    }
  }

  const valueTypeNames = new Set();
  for (const valueType of ontology.valueTypes) {
    if (!valueType.apiName || valueTypeNames.has(valueType.apiName)) {
      throw new Error(`Duplicate or missing value type identifier "${valueType.apiName ?? ""}"`);
    }
    valueTypeNames.add(valueType.apiName);
  }

  for (const [objectRid, actions] of actionsByObjectEntries(ontology.objectTypes)) {
    if (!objectRids.has(objectRid)
      || !["create", "modify", "delete"].every((key) => typeof actions[key] === "boolean")) {
      throw new Error(`Invalid actions for object "${objectRid}"`);
    }
  }
}

function validateObjectReference(objectRids, objectRid, relationRid) {
  if (!objectRid || !objectRids.has(objectRid)) {
    throw new Error(`Relation "${relationRid}" references unknown object "${objectRid ?? ""}"`);
  }
}

function actionsByObjectEntries(objectTypes) {
  return objectTypes.map((object) => [object.rid, object.ontologyActions]);
}
