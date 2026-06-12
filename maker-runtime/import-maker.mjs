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
  "defineAction",
  "defineCreateInterfaceObjectAction",
  "defineCreateObjectAction",
  "defineCreateOrModifyObjectAction",
  "defineDeleteInterfaceObjectAction",
  "defineDeleteObjectAction",
  "defineInterface",
  "defineInterfaceLinkConstraint",
  "defineLink",
  "defineModifyInterfaceObjectAction",
  "defineModifyObjectAction",
  "defineObject",
  "defineSharedPropertyType",
  "defineValueType",
  "importOntologyEntity",
  "importSharedPropertyType",
]);

const allowedConstants = new Set([
  "CREATE_INTERFACE_OBJECT_PARAMETER",
  "CREATE_OR_MODIFY_OBJECT_PARAMETER",
  "DELETE_OBJECT_PARAMETER",
  "MODIFY_INTERFACE_OBJECT_PARAMETER",
  "MODIFY_OBJECT_PARAMETER",
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
    if (!ts.isStringLiteral(statement.moduleSpecifier)
      || statement.moduleSpecifier.text !== "@osdk/maker") {
      fail(statement, "Only imports from @osdk/maker are supported");
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
    if (allowedConstants.has(node.text)) return maker[node.text];
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
  const objectTypes = Object.entries(ontology.objectTypes ?? {}).map(([key, block]) => {
    const object = block.objectType ?? {};
    const properties = Object.entries(object.propertyTypes ?? {}).map(([propertyKey, property]) => ({
      id: property.apiName ?? propertyKey,
      rid: property.apiName ?? propertyKey,
      apiName: property.apiName ?? propertyKey,
      displayMetadata: property.displayMetadata ?? {},
      baseType: property.type ?? { type: "string" },
      indexedForSearch: property.indexedForSearch ?? false,
      valueType: property.valueType,
      dataConstraints: property.nullability
        ? { nullability: property.nullability.noNulls ? "NO_NULLS" : "NULLABLE" }
        : {},
    }));

    return {
      id: object.apiName ?? key,
      rid: object.apiName ?? key,
      apiName: object.apiName ?? key,
      displayMetadata: object.displayMetadata ?? {},
      titlePropertyId: object.titlePropertyTypeRid,
      primaryKeys: object.primaryKeys ?? [],
      properties,
    };
  });

  const relations = Object.entries(ontology.linkTypes ?? {}).map(([key, block]) => {
    const link = block.linkType ?? {};
    const definition = link.definition ?? {};
    if (definition.type === "oneToMany") {
      const oneToMany = definition.oneToMany ?? {};
      const mapping = {};
      for (const entry of oneToMany.oneSidePrimaryKeyToManySidePropertyMapping ?? []) {
        const from = entry?.from?.apiName;
        const to = entry?.to?.apiName;
        if (from && to) mapping[from] = to;
      }
      return {
        id: link.id ?? key,
        rid: link.id ?? key,
        definition: {
          type: "oneToMany",
          oneToMany: {
            ...oneToMany,
            oneSidePrimaryKeyToManySidePropertyMapping: mapping,
            manySideForeignKeyPropertyId: Object.values(mapping)[0],
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

  return { objectTypes, relations, valueTypes };
}
