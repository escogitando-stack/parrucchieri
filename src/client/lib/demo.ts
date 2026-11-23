/**
 * Modalità demo (/esempio): la pagina la accende con window.A4M_DEMO = { base: "/esempio" }.
 * Le chiamate all'API e gli indirizzi dell'app prendono quel prefisso; il server usa un database finto.
 */
const flag = (globalThis as { A4M_DEMO?: { base?: string } }).A4M_DEMO;

export const IS_DEMO = !!flag;
export const BASE = flag?.base ?? "";

/** Toglie il prefisso della demo da un percorso del browser. */
export function stripBase(path: string, base = BASE): string {
  if (base && (path === base || path.startsWith(base + "/"))) return path.slice(base.length) || "/";
  return path;
}

/** Aggiunge il prefisso della demo a un percorso dell'app ("/calendar" → "/esempio/calendar"). */
export function withBase(path: string, base = BASE): string {
  if (!base) return path;
  return path === "/" ? base : base + path;
}

/** Indirizzo (relativo) della pagina pubblica del negozio: nella demo è quella di prova. */
export function shopPath(slug: string): string {
  return IS_DEMO ? `${BASE}/negozio` : `/${slug}`;
}
