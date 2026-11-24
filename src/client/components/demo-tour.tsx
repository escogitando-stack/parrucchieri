import { useCallback, useEffect, useRef, useState } from "preact/hooks";
import { ChevronLeft, ChevronRight, Pause, Play, RotateCcw, X } from "lucide-preact";
import { useApp } from "../context";
import { useTerms } from "@/lib/account";
import { BASE, stripBase } from "@/lib/demo";
import { OPEN_EVENT, SEEN_KEY, STEP_MS, nextStep, prevStep, tourSteps } from "@/lib/demo-tour";

function seen(): boolean {
  try {
    return sessionStorage.getItem(SEEN_KEY) === "1";
  } catch {
    return false;
  }
}

function markSeen(): void {
  try {
    sessionStorage.setItem(SEEN_KEY, "1");
  } catch {
    /* navigazione privata: il tour ripartirà alla prossima visita */
  }
}

/** Tour guidato automatico della demo: parte da solo alla prima visita e si riapre dal banner. */
export function DemoTour() {
  const { navigate, loading } = useApp();
  const steps = tourSteps(useTerms());
  const [open, setOpen] = useState(false);
  const [index, setIndex] = useState(0);
  const [playing, setPlaying] = useState(true);
  const lastFocus = useRef<Element | null>(null);

  const start = useCallback(() => {
    setIndex(0);
    setPlaying(true);
    setOpen(true);
  }, []);

  const close = useCallback(() => {
    setOpen(false);
    markSeen();
  }, []);

  // Parte da solo alla prima visita, quando i dati sono caricati.
  useEffect(() => {
    if (loading || seen() || stripBase(window.location.pathname) !== "/") return;
    const t = setTimeout(start, 900);
    return () => clearTimeout(t);
  }, [loading, start]);

  // Il pulsante "Guarda il tour" del banner lo riapre.
  useEffect(() => {
    window.addEventListener(OPEN_EVENT, start);
    return () => window.removeEventListener(OPEN_EVENT, start);
  }, [start]);

  // A ogni passo: apre la schermata ed evidenzia l'elemento indicato.
  useEffect(() => {
    if (!open) return;
    const step = steps[index];
    if (stripBase(window.location.pathname) !== step.path) navigate(step.path);
    const t = setTimeout(() => {
      lastFocus.current?.classList.remove("tour-focus");
      lastFocus.current = null;
      const el = step.target ? document.querySelector(`[data-tour="${step.target}"]`) : null;
      if (el) {
        el.classList.add("tour-focus");
        el.scrollIntoView({ behavior: "smooth", block: "nearest" });
        lastFocus.current = el;
      } else {
        document.querySelector("main")?.scrollTo({ top: 0, behavior: "smooth" });
      }
    }, 350);
    return () => clearTimeout(t);
  }, [open, index]); // eslint-disable-line react-hooks/exhaustive-deps

  // Riproduzione automatica: passa al successivo dopo STEP_MS; all'ultimo passo si ferma.
  useEffect(() => {
    if (!open || !playing) return;
    const n = nextStep(index, steps.length);
    if (n === null) {
      setPlaying(false);
      markSeen();
      return;
    }
    const t = setTimeout(() => setIndex(n), STEP_MS);
    return () => clearTimeout(t);
  }, [open, playing, index, steps.length]);

  // Tasti: frecce per spostarsi, Esc per chiudere.
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") close();
      if (e.key === "ArrowRight") setIndex((i) => nextStep(i, steps.length) ?? i);
      if (e.key === "ArrowLeft") setIndex((i) => prevStep(i));
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, close, steps.length]);

  useEffect(() => {
    if (!open) {
      lastFocus.current?.classList.remove("tour-focus");
      lastFocus.current = null;
    }
  }, [open]);

  if (!open) return null;
  const step = steps[index];
  const last = index === steps.length - 1;
  const btn = "inline-flex h-10 min-w-10 items-center justify-center gap-1 rounded-md border border-border bg-background px-3 text-sm font-medium hover:bg-muted disabled:opacity-40";

  return (
    <div
      role="dialog"
      aria-live="polite"
      aria-label="Tour guidato della demo"
      className="fixed inset-x-3 bottom-20 z-50 mx-auto max-w-md rounded-xl border border-border bg-card p-4 text-card-foreground shadow-2xl md:bottom-6"
    >
      <div className="mb-2 flex items-start justify-between gap-3">
        <div>
          <div className="text-xs font-semibold uppercase tracking-wide text-primary">Tour guidato · {index + 1} di {steps.length}</div>
          <h2 className="mt-0.5 text-lg font-semibold leading-snug">{step.title}</h2>
        </div>
        <button type="button" className="-mr-1 -mt-1 rounded-md p-2 text-muted-foreground hover:bg-muted" onClick={close} aria-label="Chiudi il tour">
          <X className="h-4 w-4" />
        </button>
      </div>
      <p className="text-sm text-muted-foreground">{step.text}</p>

      <div className="mt-3 h-1 overflow-hidden rounded-full bg-muted" aria-hidden="true">
        <div
          key={`${index}-${playing}`}
          className="h-full bg-primary"
          style={playing && !last ? { animation: `tour-progress ${STEP_MS}ms linear forwards` } : { width: last ? "100%" : "0%" }}
        />
      </div>

      {last ? (
        <div className="mt-3 flex flex-wrap gap-2">
          <a href={`${BASE}/negozio`} className="inline-flex h-10 flex-1 items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90">
            Vedi la pagina dei clienti
          </a>
          <a href="/registrati" className="inline-flex h-10 flex-1 items-center justify-center rounded-md border border-primary px-4 text-sm font-semibold text-primary hover:bg-primary/10">
            Prova gratis
          </a>
          <button type="button" className={btn} onClick={start}>
            <RotateCcw className="h-4 w-4" /> Ricomincia
          </button>
        </div>
      ) : (
        <div className="mt-3 flex items-center justify-between gap-2">
          <button type="button" className={btn} onClick={() => setIndex(prevStep(index))} disabled={index === 0} aria-label="Passo precedente">
            <ChevronLeft className="h-4 w-4" />
          </button>
          <button type="button" className={btn + " flex-1"} onClick={() => setPlaying(!playing)}>
            {playing ? <><Pause className="h-4 w-4" /> Pausa</> : <><Play className="h-4 w-4" /> Riprendi</>}
          </button>
          <button type="button" className={btn} onClick={() => setIndex(nextStep(index, steps.length) ?? index)} aria-label="Passo successivo">
            Avanti <ChevronRight className="h-4 w-4" />
          </button>
        </div>
      )}
    </div>
  );
}
