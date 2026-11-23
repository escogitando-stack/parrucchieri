import assert from "node:assert/strict";
import { test } from "node:test";
import { stripBase, withBase } from "./demo.ts";

test("demo: senza prefisso i percorsi restano uguali", () => {
  assert.equal(stripBase("/calendar", ""), "/calendar");
  assert.equal(withBase("/calendar", ""), "/calendar");
});

test("demo: il prefisso /esempio si toglie e si rimette", () => {
  assert.equal(stripBase("/esempio", "/esempio"), "/");
  assert.equal(stripBase("/esempio/clients/3", "/esempio"), "/clients/3");
  assert.equal(stripBase("/esempiox", "/esempio"), "/esempiox");
  assert.equal(withBase("/", "/esempio"), "/esempio");
  assert.equal(withBase("/calendar", "/esempio"), "/esempio/calendar");
});
