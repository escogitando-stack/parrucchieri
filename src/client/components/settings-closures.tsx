import { useCallback, useEffect, useState } from "preact/hooks";
import { useApp } from "../context";
import { api } from "../api";
import { setAccount, useAccount, type AccountInfo } from "@/lib/account";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { formatDateIt } from "@/lib/format";
import { today } from "@/lib/dates";

interface Closure {
  from: string;
  to: string;
  reason: string;
}

/** Ferie e giorni di chiusura: in quei giorni nessuno può prenotare online. */
export function Closures() {
  const { setError } = useApp();
  const [items, setItems] = useState<Closure[]>([]);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [reason, setReason] = useState("");
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState("");

  const load = useCallback(async () => {
    try {
      const res = await api<{ closures: Closure[] }>("GET", "/api/closures");
      setItems(res.closures);
    } catch (err) {
      setError((err as Error).message);
    }
  }, [setError]);

  useEffect(() => {
    load();
  }, [load]);

  const add = async () => {
    if (!from) { setError("Scegli il primo giorno di chiusura"); return; }
    setSaving(true);
    setNotice("");
    try {
      const res = await api<{ affected_appointments: number }>("POST", "/api/closures", { from, to: to || from, reason: reason.trim() });
      setFrom("");
      setTo("");
      setReason("");
      setNotice(res.affected_appointments > 0
        ? `Chiusura salvata. Attenzione: in quel periodo hai già ${res.affected_appointments} ${res.affected_appointments === 1 ? "appuntamento" : "appuntamenti"}: controllali dall'elenco.`
        : "Chiusura salvata.");
      await load();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (c: Closure) => {
    try {
      await api("DELETE", `/api/closures?from=${c.from}&to=${c.to}&reason=${encodeURIComponent(c.reason)}`);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  };

  return (
    <Card>
      <CardContent className="space-y-4 p-4 sm:p-6">
        <h2 className="text-lg font-semibold">Ferie e chiusure</h2>
        <p className="text-sm text-muted-foreground">Chiudi uno o più giorni (ferie, festività): la tua pagina non offrirà orari in quel periodo. Gli appuntamenti già presi non vengono toccati.</p>
        <div className="grid gap-3 sm:grid-cols-3">
          <div className="space-y-1.5"><Label htmlFor="cl-from">Dal</Label><Input id="cl-from" type="date" min={today()} className="h-11 sm:h-10" value={from} onInput={(e) => setFrom((e.target as HTMLInputElement).value)} /></div>
          <div className="space-y-1.5"><Label htmlFor="cl-to">Al (facoltativo)</Label><Input id="cl-to" type="date" min={from || today()} className="h-11 sm:h-10" value={to} onInput={(e) => setTo((e.target as HTMLInputElement).value)} /></div>
          <div className="space-y-1.5"><Label htmlFor="cl-reason">Motivo (facoltativo)</Label><Input id="cl-reason" maxLength={100} className="h-11 sm:h-10" placeholder="es. Ferie estive" value={reason} onInput={(e) => setReason((e.target as HTMLInputElement).value)} /></div>
        </div>
        <div className="flex items-center gap-3">
          <Button className="min-h-11 sm:min-h-0" disabled={saving} onClick={add}>{saving ? "Salvataggio..." : "Aggiungi chiusura"}</Button>
          {notice && <span className="text-sm text-emerald-700">{notice}</span>}
        </div>
        {items.length > 0 && (
          <ul className="divide-y rounded-md border">
            {items.map((c) => (
              <li key={`${c.from}-${c.to}-${c.reason}`} className="flex items-center justify-between gap-3 p-3 text-sm">
                <span>
                  {c.from === c.to ? formatDateIt(c.from) : `${formatDateIt(c.from)} → ${formatDateIt(c.to)}`}
                  {c.reason ? <span className="text-muted-foreground"> · {c.reason}</span> : null}
                </span>
                <Button variant="ghost" size="sm" onClick={() => remove(c)}>Rimuovi</Button>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

/** Email automatiche ricevute dal titolare. */
export function Notifications() {
  const { setError } = useApp();
  const account = useAccount();
  const [saving, setSaving] = useState(false);
  if (!account) return null;

  const toggle = async (on: boolean) => {
    setSaving(true);
    try {
      const data = await api<{ account: AccountInfo }>("PUT", "/api/account", { daily_summary: on });
      setAccount(data.account);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Card>
      <CardContent className="space-y-3 p-4 sm:p-6">
        <h2 className="text-lg font-semibold">Email per te</h2>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" disabled={saving} checked={account.daily_summary !== false} onChange={(e) => toggle((e.target as HTMLInputElement).checked)} />
          Ogni sera ricevi il riepilogo degli appuntamenti di domani e delle richieste da approvare
        </label>
      </CardContent>
    </Card>
  );
}
