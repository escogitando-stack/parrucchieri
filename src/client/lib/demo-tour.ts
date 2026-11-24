/**
 * Tour guidato automatico della demo (/esempio): passa da solo da una schermata all'altra
 * spiegando cosa si vede. Qui solo i dati e la logica, il componente è in components/demo-tour.tsx.
 */

export interface TourTerms {
  services: string;
  staffs: string;
}

export interface TourStep {
  /** Percorso dell'app da aprire (senza il prefisso /esempio). */
  path: string;
  /** Elemento da evidenziare: [data-tour="..."] (facoltativo). */
  target?: string;
  title: string;
  text: string;
}

/** Durata di ogni passo in riproduzione automatica. */
export const STEP_MS = 8000;

/** Chiave (sessionStorage) che ricorda che il tour è già stato visto in questa visita. */
export const SEEN_KEY = "a4m_tour_seen";

/** Evento per riaprire il tour da un pulsante qualsiasi. */
export const OPEN_EVENT = "a4m-tour-open";

export function tourSteps(t: TourTerms): TourStep[] {
  const services = t.services.toLowerCase();
  const staffs = t.staffs.toLowerCase();
  return [
    {
      path: "/",
      target: "riepilogo",
      title: "Benvenuto nella tua dashboard",
      text: "Appena entri vedi la giornata: appuntamenti di oggi, incassi e cosa c'è da fare. Il tour va avanti da solo, puoi metterlo in pausa quando vuoi.",
    },
    {
      path: "/",
      target: "richieste",
      title: "Le prenotazioni arrivano da sole",
      text: "I clienti prenotano dalla tua pagina, anche di notte. Le richieste compaiono qui: le approvi o le rifiuti con un tocco e il cliente riceve l'email.",
    },
    {
      path: "/calendar",
      title: "L'agenda a colonne",
      text: `Una colonna per ognuno dei tuoi ${staffs}. Tocchi uno spazio libero per fissare un appuntamento e l'app ti avvisa se due si sovrappongono.`,
    },
    {
      path: "/appointments",
      title: "Tutti gli appuntamenti",
      text: "Cerchi, filtri per stato e apri un appuntamento per confermarlo, spostarlo o segnarlo come completato: l'incasso si registra da solo.",
    },
    {
      path: "/clients",
      title: "I tuoi clienti",
      text: "Ogni cliente ha telefono, email, note e lo storico delle visite. Da qui gli scrivi su WhatsApp con il messaggio già pronto.",
    },
    {
      path: "/services",
      title: `${capitalize(t.services)} già pronti`,
      text: `Trovi i ${services} tipici del tuo mestiere con durata e prezzo. Li cambi, li aggiungi o li togli in un attimo.`,
    },
    {
      path: "/settings",
      title: "Orari, ferie e la tua pagina",
      text: "Imposti gli orari di lavoro, chiudi i giorni di ferie e personalizzi la pagina di prenotazione con logo, colori e contatti.",
    },
    {
      path: "/",
      title: "Ora prova tu",
      text: "Questo era il pannello del negozio. Guarda anche la pagina che vedono i tuoi clienti, oppure inizia la prova gratuita con la tua attività.",
    },
  ];
}

/** Passo successivo; null quando il tour è finito. */
export function nextStep(index: number, total: number): number | null {
  return index + 1 < total ? index + 1 : null;
}

export function prevStep(index: number): number {
  return Math.max(0, index - 1);
}

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1);
}
