import { useState } from "preact/hooks";
import { useApp } from "../context";
import { useAccount } from "@/lib/account";
import { Copy, Printer } from "lucide-preact";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";

function esc(s: string): string {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

/** Passi per mettere online la pagina di prenotazione: sparisce quando è tutto fatto. */
export function SetupChecklist() {
  const account = useAccount();
  const { services, navigate } = useApp();
  if (!account) return null;

  const items: { done: boolean; label: string; to: string }[] = [
    { done: !!(account.shop_phone || account.shop_whatsapp), label: "Aggiungi un telefono o WhatsApp", to: "/settings" },
    { done: !!account.shop_address, label: "Aggiungi l'indirizzo", to: "/settings" },
    { done: account.has_logo, label: "Carica il logo", to: "/settings" },
    { done: !!account.shop_description, label: "Scrivi una breve descrizione", to: "/settings" },
    { done: services.some((s) => s.active && s.online), label: "Controlla che i servizi siano prenotabili online", to: "/services" },
  ];
  const doneCount = items.filter((i) => i.done).length;
  if (doneCount === items.length) return null;

  return (
    <Card>
      <CardContent className="space-y-3 p-4">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-lg font-semibold">Completa la tua pagina</h2>
          <span className="text-sm text-muted-foreground">{doneCount} di {items.length}</span>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-muted">
          <div className="h-full rounded-full bg-primary" style={{ width: `${Math.round((doneCount / items.length) * 100)}%` }} />
        </div>
        <ul className="space-y-1.5">
          {items.map((item) => (
            <li key={item.label} className="flex items-center gap-2 text-sm">
              <span className={item.done ? "text-emerald-600" : "text-muted-foreground"}>{item.done ? "✓" : "○"}</span>
              {item.done ? (
                <span className="text-muted-foreground line-through">{item.label}</span>
              ) : (
                <button type="button" className="text-left text-primary underline" onClick={() => navigate(item.to)}>{item.label}</button>
              )}
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

/** Il link della pagina da condividere: copia, WhatsApp, codice QR e cartello da stampare. */
export function ShareCard() {
  const account = useAccount();
  const [copied, setCopied] = useState(false);
  if (!account) return null;

  const url = account.shop_url;
  const qr = `/${account.slug}/qr.svg`;

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      window.prompt("Copia il link:", url);
    }
  };

  const print = () => {
    const w = window.open("", "_blank");
    if (!w) return;
    w.document.write(
      `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>${esc(account.business_name)}</title>` +
      `<style>body{font-family:Arial,sans-serif;text-align:center;padding:48px}h1{font-size:40px;margin:0 0 8px}p{font-size:22px;margin:8px 0}img{width:420px;height:420px;margin:24px auto;display:block}.u{font-size:20px;color:#444;word-break:break-all}</style></head>` +
      `<body><h1>${esc(account.business_name)}</h1><p>Prenota online in pochi secondi</p><img src="${qr}" alt="Codice QR"><p>Inquadra il codice con la fotocamera</p><p class="u">${esc(url)}</p>` +
      `<script>window.onload=function(){setTimeout(function(){window.print()},300)}<\/script></body></html>`,
    );
    w.document.close();
  };

  return (
    <Card>
      <CardContent className="space-y-3 p-4">
        <h2 className="text-lg font-semibold">Fai prenotare i tuoi clienti</h2>
        <p className="text-sm text-muted-foreground">Condividi questo link su WhatsApp, Instagram, Google o stampa il cartello con il codice QR.</p>
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
          <img src={qr} alt="Codice QR della tua pagina" className="h-32 w-32 shrink-0 rounded-md border bg-white" />
          <div className="min-w-0 flex-1 space-y-3">
            <input readOnly value={url} onFocus={(e) => (e.target as HTMLInputElement).select()} className="h-11 w-full rounded-md border border-input bg-background px-3 text-sm sm:h-10" aria-label="Link della tua pagina" />
            <div className="flex flex-wrap gap-2">
              <Button size="sm" className="min-h-11 sm:min-h-0" onClick={copy}>
                <Copy className="mr-1 h-3.5 w-3.5" /> {copied ? "Copiato!" : "Copia link"}
              </Button>
              <Button asChild size="sm" variant="outline" className="min-h-11 sm:min-h-0">
                <a href={`https://wa.me/?text=${encodeURIComponent(`Prenota da ${account.business_name}: ${url}`)}`} target="_blank" rel="noopener noreferrer">Condividi su WhatsApp</a>
              </Button>
              <Button size="sm" variant="outline" className="min-h-11 sm:min-h-0" onClick={print}>
                <Printer className="mr-1 h-3.5 w-3.5" /> Stampa cartello
              </Button>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
