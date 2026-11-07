import { useCallback, useEffect, useState } from "preact/hooks";
import { api } from "../api";
import { useApp } from "../context";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { formatDateIt } from "@/lib/format";

interface PendingRequest {
  id: number;
  client_name: string;
  client_phone: string;
  client_email: string;
  scheduled_date: string;
  start_time: string;
  notes: string;
  services: string;
  source: string;
}

/** Richieste di prenotazione da approvare, con i pulsanti per rispondere senza aprire il dettaglio. */
export function PendingRequests() {
  const { stats, decideAppointment, setError, navigate } = useApp();
  const [items, setItems] = useState<PendingRequest[]>([]);
  const [busy, setBusy] = useState<number | null>(null);
  const [result, setResult] = useState<{ text: string; whatsapp: string } | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await api<{ requests: PendingRequest[] }>("GET", "/api/requests");
      setItems(res.requests);
    } catch {
      setItems([]);
    }
  }, []);

  useEffect(() => {
    load();
  }, [stats.pending_requests, load]);

  const decide = async (req: PendingRequest, approve: boolean) => {
    setBusy(req.id);
    setResult(null);
    try {
      const res = await decideAppointment(req.id, approve);
      const who = req.client_name || "il cliente";
      const text = approve
        ? `Prenotazione di ${who} confermata${res.email_sent ? ": ho avvisato il cliente via email." : "."}`
        : `Richiesta di ${who} rifiutata${res.email_sent ? ": ho avvisato il cliente via email." : "."}`;
      setResult({ text, whatsapp: res.email_sent ? "" : res.whatsapp_url });
      await load();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(null);
    }
  };

  if (items.length === 0 && !result) return null;

  return (
    <Card className="border-amber-300">
      <CardContent className="space-y-3 p-4">
        <h2 className="text-lg font-semibold">Richieste da approvare</h2>
        {result && (
          <p className="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">
            {result.text}{" "}
            {result.whatsapp && (
              <a className="font-medium underline" href={result.whatsapp} target="_blank" rel="noopener noreferrer">Avvisa su WhatsApp</a>
            )}
          </p>
        )}
        <div className="divide-y">
          {items.map((req) => (
            <div key={req.id} className="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="min-w-0">
                <p className="font-medium">
                  {req.client_name || "—"}{" "}
                  <span className="font-normal text-muted-foreground">{formatDateIt(req.scheduled_date)} alle {req.start_time}</span>
                </p>
                <p className="break-words text-sm text-muted-foreground">
                  {[req.services, req.client_phone, req.client_email].filter(Boolean).join(" · ")}
                </p>
                {req.notes && <p className="break-words text-sm">{req.notes}</p>}
              </div>
              <div className="flex shrink-0 flex-wrap gap-2">
                <Button size="sm" className="min-h-11 sm:min-h-0" disabled={busy === req.id} onClick={() => decide(req, true)}>Conferma</Button>
                <Button size="sm" variant="outline" className="min-h-11 sm:min-h-0" disabled={busy === req.id} onClick={() => decide(req, false)}>Rifiuta</Button>
                <Button size="sm" variant="ghost" className="min-h-11 sm:min-h-0" onClick={() => navigate(`/appointments/${req.id}`)}>Dettagli</Button>
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
