import { useEffect, useState } from "preact/hooks";
import { useApp } from "../context";
import { api } from "../api";
import { setAccount, useAccount, type AccountInfo } from "@/lib/account";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const selectClass = "h-11 w-full rounded-md border border-input bg-background px-3 text-sm sm:h-10";

function planText(account: AccountInfo): string {
  const { state, days_left, ends_at } = account.plan;
  if (state === "active") {
    return ends_at ? `Abbonamento attivo, prossimo rinnovo il ${new Date(ends_at.replace(" ", "T")).toLocaleDateString("it-IT")}.` : "Abbonamento attivo.";
  }
  if (state === "trial") {
    return `Prova gratuita: ${days_left} ${days_left === 1 ? "giorno rimasto" : "giorni rimasti"}.`;
  }
  return "Prova gratuita terminata.";
}

export function Settings() {
  const { setError } = useApp();
  const account = useAccount();
  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [savingProfile, setSavingProfile] = useState(false);
  const [profileSaved, setProfileSaved] = useState(false);
  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [savingPassword, setSavingPassword] = useState(false);
  const [passwordSaved, setPasswordSaved] = useState(false);

  useEffect(() => {
    if (account) {
      setName(account.business_name);
      setCategory(account.category);
    }
  }, [account?.business_name, account?.category]); // eslint-disable-line react-hooks/exhaustive-deps

  if (!account) return <div className="p-6 text-muted-foreground">Caricamento...</div>;

  const saveProfile = async () => {
    if (!name.trim()) { setError("Il nome dell'attività è obbligatorio"); return; }
    setSavingProfile(true);
    setProfileSaved(false);
    try {
      const data = await api<{ account: AccountInfo }>("PUT", "/api/account", { business_name: name.trim(), category });
      setAccount(data.account);
      setProfileSaved(true);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSavingProfile(false);
    }
  };

  const savePassword = async () => {
    if (!current || !next) { setError("Inserisci la password attuale e quella nuova"); return; }
    setSavingPassword(true);
    setPasswordSaved(false);
    try {
      await api("POST", "/api/account/password", { current_password: current, new_password: next });
      setCurrent("");
      setNext("");
      setPasswordSaved(true);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSavingPassword(false);
    }
  };

  return (
    <div className="max-w-2xl space-y-4 p-4 sm:p-6">
      <h1 className="text-2xl font-bold tracking-tight">Impostazioni</h1>

      <Card>
        <CardContent className="space-y-4 p-4 sm:p-6">
          <h2 className="text-lg font-semibold">La tua attività</h2>
          <div className="space-y-1.5">
            <Label htmlFor="business-name">Nome dell'attività</Label>
            <Input id="business-name" className="h-11 sm:h-10" value={name} onInput={(e) => setName((e.target as HTMLInputElement).value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="business-category">Tipo di attività</Label>
            <select id="business-category" className={selectClass} value={category} onChange={(e) => setCategory((e.target as HTMLSelectElement).value)}>
              {Object.entries(account.categories).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
            </select>
            <p className="text-sm text-muted-foreground">
              Cambia i nomi usati nell'app (per esempio collaboratore, servizio, prodotto). I servizi che hai già inseriti restano invariati.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <Button className="min-h-11 sm:min-h-0" disabled={savingProfile} onClick={saveProfile}>{savingProfile ? "Salvataggio..." : "Salva"}</Button>
            {profileSaved && <span className="text-sm text-emerald-700">Salvato</span>}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="space-y-3 p-4 sm:p-6">
          <h2 className="text-lg font-semibold">Abbonamento</h2>
          <p className="text-sm text-muted-foreground">{planText(account)}</p>
          <Button asChild variant="outline" className="min-h-11 sm:min-h-0">
            <a href="/abbonamento">{account.plan.state === "active" ? "Gestisci abbonamento" : "Scegli un piano"}</a>
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="space-y-4 p-4 sm:p-6">
          <h2 className="text-lg font-semibold">Password</h2>
          <p className="text-sm text-muted-foreground">Email di accesso: {account.email}</p>
          <div className="space-y-1.5">
            <Label htmlFor="password-current">Password attuale</Label>
            <Input id="password-current" type="password" autoComplete="current-password" className="h-11 sm:h-10" value={current} onInput={(e) => setCurrent((e.target as HTMLInputElement).value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="password-new">Nuova password (almeno 8 caratteri)</Label>
            <Input id="password-new" type="password" autoComplete="new-password" className="h-11 sm:h-10" value={next} onInput={(e) => setNext((e.target as HTMLInputElement).value)} />
          </div>
          <div className="flex items-center gap-3">
            <Button className="min-h-11 sm:min-h-0" disabled={savingPassword} onClick={savePassword}>{savingPassword ? "Salvataggio..." : "Cambia password"}</Button>
            {passwordSaved && <span className="text-sm text-emerald-700">Password aggiornata</span>}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
