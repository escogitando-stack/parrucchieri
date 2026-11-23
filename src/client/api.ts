/** A request the server refused, with the status and body so callers can react to the reason. */
export class ApiError extends Error {
  constructor(message: string, readonly status: number, readonly body: unknown) {
    super(message);
    this.name = "ApiError";
  }
}

import { BASE, IS_DEMO } from "./lib/demo";

export async function api<T>(method: string, path: string, body?: unknown): Promise<T> {
  const opts: RequestInit = { method, headers: {} };
  if (body) {
    (opts.headers as Record<string, string>)["Content-Type"] = "application/json";
    opts.body = JSON.stringify(body);
  }
  const r = await fetch(BASE + path, opts);
  if (r.status === 401 && IS_DEMO) {
    // Demo scaduta: si ricarica e ne parte una nuova.
    window.location.href = BASE;
    throw new Error("Demo scaduta");
  }
  if (r.status === 401) {
    // Sessione scaduta: si torna alla pagina di accesso.
    window.location.href = "/login";
    throw new Error("Sessione scaduta");
  }
  if (r.status === 402) {
    // Prova gratuita terminata: si va alla pagina dell'abbonamento.
    window.location.href = "/abbonamento";
    throw new Error("Abbonamento necessario");
  }
  const text = await r.text();
  let data: unknown;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error(`Server error: ${r.status} ${r.statusText}`);
  }
  if (!r.ok) throw new ApiError((data as { error?: string }).error || "Request failed", r.status, data);
  return data as T;
}
