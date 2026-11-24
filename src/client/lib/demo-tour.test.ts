import assert from "node:assert/strict";
import { test } from "node:test";
import { nextStep, prevStep, tourSteps } from "./demo-tour.ts";

test("tour: passi con percorso, titolo e testo", () => {
  const steps = tourSteps({ services: "Servizi", staffs: "Collaboratori" });
  assert.ok(steps.length >= 6);
  for (const s of steps) {
    assert.ok(s.path.startsWith("/"));
    assert.ok(s.title.length > 3 && s.text.length > 20);
  }
  assert.equal(steps[0].path, "/");
  assert.ok(steps.some((s) => s.path === "/calendar"));
});

test("tour: usa i nomi del tipo di attività", () => {
  const steps = tourSteps({ services: "Interventi", staffs: "Meccanici" });
  assert.ok(steps.some((s) => s.title.includes("Interventi")));
  assert.ok(steps.some((s) => s.text.includes("meccanici")));
});

test("tour: avanti e indietro restano nei limiti", () => {
  assert.equal(nextStep(0, 3), 1);
  assert.equal(nextStep(2, 3), null);
  assert.equal(prevStep(0), 0);
  assert.equal(prevStep(2), 1);
});
