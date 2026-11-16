import type { ComponentChildren } from "preact";
import { useApp } from "../context";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";

interface Section {
  id: string;
  title: string;
  to?: string;
  cta?: string;
  body: ComponentChildren;
}

function Steps({ items }: { items: ComponentChildren[] }) {
  return (
    <ol className="list-decimal space-y-1.5 pl-5">
      {items.map((it, i) => <li key={i}>{it}</li>)}
    </ol>
  );
}

const sections: Section[] = [
  {
    id: "iniziare",
    title: "Per iniziare in 5 minuti",
    to: "/settings",
    cta: "Vai alle impostazioni",
    body: (
      <Steps items={[
        <>In <b>Impostazioni</b> inserisci telefono o WhatsApp, indirizzo, logo e una breve descrizione: sono i dati che i clienti vedono sulla tua pagina.</>,
        <>Imposta gli <b>orari di apertura</b> e, se lavori in più persone, aggiungi i <b>collaboratori</b>.</>,
        <>Controlla i <b>servizi</b>: nome, durata e prezzo. Solo quelli “Prenotabili online” compaiono sulla pagina pubblica.</>,
        <>Dalla <b>Dashboard</b> copia il link di prenotazione (o stampa il QR) e condividilo su Instagram, WhatsApp, Google e in negozio.</>,
      ]} />
    ),
  },
  {
    id: "servizi",
    title: "Servizi: prenotabili online e conferma immediata",
    to: "/services",
    cta: "Apri i servizi",
    body: (
      <>
        <p>Nella lista dei servizi, colonna <b>Stato</b>, trovi tre interruttori:</p>
        <ul className="mt-2 list-disc space-y-1.5 pl-5">
          <li><b>Attivo</b>: se lo spegni il servizio sparisce ovunque, senza essere cancellato.</li>
          <li><b>Prenotabile online</b>: i clienti possono sceglierlo dalla tua pagina pubblica.</li>
          <li><b>Conferma immediata</b>: la prenotazione è confermata subito, senza che tu approvi. Se è spento, ogni richiesta aspetta la tua approvazione.</li>
        </ul>
        <p className="mt-2">Se un cliente sceglie più servizi, basta uno “da approvare” perché tutta la prenotazione lo sia. Con la matita puoi cambiare nome, durata, prezzo, categoria e colore: le prenotazioni già fatte non cambiano.</p>
      </>
    ),
  },
  {
    id: "richieste",
    title: "Richieste da approvare",
    to: "/",
    cta: "Vai alla dashboard",
    body: (
      <>
        <p>Le richieste in attesa compaiono in cima alla <b>Dashboard</b> e ti arrivano anche via email con i pulsanti <b>Approva</b> e <b>Rifiuta</b>: puoi decidere direttamente dal telefono, senza accedere.</p>
        <p className="mt-2">Quando approvi, il cliente riceve la conferma con il link per aggiungere l'appuntamento al calendario. Se rifiuti, riceve un avviso gentile. Se nessun collaboratore è libero in quell'orario, l'app ti avvisa.</p>
      </>
    ),
  },
  {
    id: "clienti-sposta",
    title: "Il cliente può spostare o cambiare i servizi",
    body: (
      <>
        <p>Nell'email di conferma c'è un link <b>Sposta o annulla</b>. Da lì il cliente può scegliere un altro giorno e orario (vede solo quelli liberi) e, se vuole, aggiungere o togliere servizi: gli orari si ricalcolano sulla nuova durata.</p>
        <p className="mt-2">Ricevi un'email a ogni modifica o annullamento. Se i servizi scelti richiedono approvazione, la prenotazione torna “da approvare”.</p>
      </>
    ),
  },
  {
    id: "modifica",
    title: "Modificare una prenotazione dal pannello",
    to: "/appointments",
    cta: "Apri gli appuntamenti",
    body: (
      <Steps items={[
        <>Apri l'appuntamento dall'elenco o dall'agenda.</>,
        <>Nel riquadro <b>Servizi</b> premi <b>Modifica</b>, spunta i servizi giusti e salva: durata e totale si aggiornano da soli.</>,
        <>Per cambiare data, ora o collaboratore usa lo spostamento. Se l'orario è occupato l'app ti avvisa e ti lascia decidere se salvare comunque.</>,
      ]} />
    ),
  },
  {
    id: "orari-chiusure",
    title: "Orari, ferie e chiusure",
    to: "/settings",
    cta: "Apri le impostazioni",
    body: (
      <>
        <p>In <b>Impostazioni → Orari</b> definisci i giorni e le fasce di apertura. Per ferie, festivi o giorni di chiusura straordinaria usa <b>Chiusure</b>: scegli le date e quel giorno non è prenotabile da nessuno, né online né dal pannello.</p>
        <p className="mt-2">Le chiusure non cancellano gli appuntamenti già presi: controllali prima di bloccare un giorno.</p>
      </>
    ),
  },
  {
    id: "notifiche",
    title: "Promemoria e riepilogo serale",
    to: "/settings",
    cta: "Gestisci le notifiche",
    body: (
      <>
        <p>L'app invia da sola un <b>promemoria ai clienti</b> il giorno prima dell'appuntamento, per ridurre i “buchi”. Ogni sera ricevi anche un <b>riepilogo</b> con gli appuntamenti di domani.</p>
        <p className="mt-2">Puoi disattivare il riepilogo da <b>Impostazioni → Notifiche</b>. Il promemoria parte solo per i clienti che hanno lasciato un'email.</p>
      </>
    ),
  },
  {
    id: "condividi",
    title: "Condividere la pagina di prenotazione",
    to: "/",
    cta: "Vai alla dashboard",
    body: (
      <>
        <p>Dalla <b>Dashboard</b>, nel riquadro di condivisione, trovi il link della tua pagina, il pulsante per copiarlo e il <b>QR code</b> da stampare e appendere in negozio o in vetrina.</p>
        <p className="mt-2">Consiglio: mettilo nella biografia di Instagram e nella scheda Google dell'attività. Le prenotazioni online funzionano 24 ore su 24, anche quando sei chiuso.</p>
      </>
    ),
  },
  {
    id: "dati",
    title: "Statistiche ed esportazione",
    to: "/appointments",
    cta: "Apri gli appuntamenti",
    body: (
      <>
        <p>La Dashboard mostra appuntamenti completati, annullati, assenze e prenotazioni online degli ultimi 30 giorni. Dall'elenco appuntamenti puoi <b>esportare tutto in CSV</b> e aprirlo in Excel o Fogli Google.</p>
      </>
    ),
  },
  {
    id: "abbonamento",
    title: "Prova gratuita e abbonamento",
    to: "/settings",
    cta: "Vedi il tuo piano",
    body: (
      <>
        <p>Durante la prova gratuita hai tutte le funzioni. Giorni rimasti e stato del piano sono in <b>Impostazioni</b>. Prima della scadenza ricevi un'email, così non rischi di restare senza agenda.</p>
      </>
    ),
  },
];

export function Guide() {
  const { navigate } = useApp();
  return (
    <div className="mx-auto max-w-3xl space-y-5 p-4 md:p-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Guida</h1>
        <p className="mt-1 text-sm text-muted-foreground">Tutto quello che serve per usare Appuntamenti4me, passo per passo. Tocca una voce per aprirla.</p>
      </div>
      <div className="space-y-3">
        {sections.map((s, i) => (
          <Card key={s.id}>
            <CardContent className="p-0">
              <details className="group" open={i === 0}>
                <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-semibold">
                  <span className="flex items-center gap-3">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">{i + 1}</span>
                    {s.title}
                  </span>
                  <span className="text-muted-foreground transition-transform group-open:rotate-90">›</span>
                </summary>
                <div className="space-y-3 border-t px-4 py-4 text-sm leading-relaxed text-muted-foreground [&_b]:text-foreground">
                  {s.body}
                  {s.to && (
                    <Button variant="outline" size="sm" onClick={() => navigate(s.to!)}>{s.cta ?? "Apri"}</Button>
                  )}
                </div>
              </details>
            </CardContent>
          </Card>
        ))}
      </div>
      <p className="text-center text-xs text-muted-foreground">
        Non trovi una risposta? Scrivici dalla pagina <a className="underline" href="/contatti">Contatti</a>.
      </p>
    </div>
  );
}
