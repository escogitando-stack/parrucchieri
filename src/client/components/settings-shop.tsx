import { useEffect, useRef, useState } from "preact/hooks";
import { useApp } from "../context";
import { api } from "../api";
import { setAccount, useAccount, type AccountInfo } from "@/lib/account";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const selectClass = "h-11 w-full rounded-md border border-input bg-background px-3 text-sm sm:h-10";
const MAX_LOGO_SIDE = 320;

/** Ridimensiona il logo nel browser, così resta leggero (massimo 320 px). */
function logoToDataUrl(file: File): Promise<string> {
  return resizeToDataUrl(file, MAX_LOGO_SIDE, "image/png");
}

/** Ridimensiona la foto di copertina (massimo 1400 px, JPEG) per tenerla sotto 1,5 MB. */
function coverToDataUrl(file: File): Promise<string> {
  return resizeToDataUrl(file, 1400, "image/jpeg", 0.82);
}

function resizeToDataUrl(file: File, maxSide: number, type: string, quality?: number): Promise<string> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
      const canvas = document.createElement("canvas");
      canvas.width = Math.max(1, Math.round(img.width * scale));
      canvas.height = Math.max(1, Math.round(img.height * scale));
      const ctx = canvas.getContext("2d");
      if (ctx && type === "image/jpeg") {
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
      }
      ctx?.drawImage(img, 0, 0, canvas.width, canvas.height);
      URL.revokeObjectURL(url);
      resolve(canvas.toDataURL(type, quality));
    };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error("Immagine non leggibile")); };
    img.src = url;
  });
}

export function ShopSettings() {
  const { setError } = useApp();
  const account = useAccount();
  const [f, setF] = useState<Record<string, string>>({});
  const [enabled, setEnabled] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [logoBusy, setLogoBusy] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);
  const [coverBusy, setCoverBusy] = useState(false);
  const coverRef = useRef<HTMLInputElement>(null);
  const [photoBusy, setPhotoBusy] = useState(false);
  const photoRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!account) return;
    setF({
      slug: account.slug, shop_description: account.shop_description, shop_phone: account.shop_phone,
      shop_whatsapp: account.shop_whatsapp, shop_email: account.shop_email, shop_address: account.shop_address,
      shop_hours: account.shop_hours, review_url: account.review_url ?? "", slot_minutes: String(account.slot_minutes),
      booking_notice_hours: String(account.booking_notice_hours), booking_horizon_days: String(account.booking_horizon_days),
    });
    setEnabled(!!account.booking_enabled);
  }, [account?.slug, account?.shop_url]); // eslint-disable-line react-hooks/exhaustive-deps

  if (!account) return null;
  const set = (k: string) => (e: Event) => setF((p) => ({ ...p, [k]: (e.target as HTMLInputElement).value }));
  const host = account.shop_url.replace(/\/[^/]*$/, "/");

  const save = async () => {
    setSaving(true);
    setSaved(false);
    try {
      const data = await api<{ account: AccountInfo }>("PUT", "/api/account", {
        slug: (f.slug || "").trim().toLowerCase(),
        shop_description: f.shop_description, shop_phone: f.shop_phone, shop_whatsapp: f.shop_whatsapp,
        shop_email: f.shop_email, shop_address: f.shop_address, shop_hours: f.shop_hours,
        review_url: (f.review_url || "").trim(),
        slot_minutes: parseInt(f.slot_minutes) || 30,
        booking_notice_hours: Math.max(0, parseInt(f.booking_notice_hours) || 0),
        booking_horizon_days: Math.max(1, parseInt(f.booking_horizon_days) || 60),
        booking_enabled: enabled ? 1 : 0,
      });
      setAccount(data.account);
      setSaved(true);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSaving(false);
    }
  };

  const uploadLogo = async (file: File | undefined) => {
    if (!file) return;
    setLogoBusy(true);
    try {
      const logo = await logoToDataUrl(file);
      const data = await api<{ account: AccountInfo }>("PUT", "/api/account", { logo });
      setAccount(data.account);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setLogoBusy(false);
      if (fileRef.current) fileRef.current.value = "";
    }
  };

  const removeLogo = async () => {
    setLogoBusy(true);
    try {
      const data = await api<{ account: AccountInfo }>("PUT", "/api/account", { remove_logo: true });
      setAccount(data.account);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setLogoBusy(false);
    }
  };

  const uploadCover = async (file: File | undefined) => {
    if (!file) return;
    setCoverBusy(true);
    try {
      const cover = await coverToDataUrl(file);
      const data = await api<{ account: AccountInfo }>("PUT", "/api/account", { cover });
      setAccount(data.account);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setCoverBusy(false);
      if (coverRef.current) coverRef.current.value = "";
    }
  };

  const addPhotos = async (files: FileList | null | undefined) => {
    if (!files || files.length === 0) return;
    setPhotoBusy(true);
    try {
      for (const file of Array.from(files)) {
        const add_photo = await coverToDataUrl(file);
        const data = await api<{ account: AccountInfo }>("PUT", "/api/account", { add_photo });
        setAccount(data.account);
      }
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setPhotoBusy(false);
      if (photoRef.current) photoRef.current.value = "";
    }
  };

  const removePhoto = async (id: number) => {
    setPhotoBusy(true);
    try {
      const data = await api<{ account: AccountInfo }>("PUT", "/api/account", { remove_photo: id });
      setAccount(data.account);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setPhotoBusy(false);
    }
  };

  const removeCover = async () => {
    setCoverBusy(true);
    try {
      const data = await api<{ account: AccountInfo }>("PUT", "/api/account", { remove_cover: true });
      setAccount(data.account);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setCoverBusy(false);
    }
  };

  return (
    <>
      <Card>
        <CardContent className="space-y-4 p-4 sm:p-6">
          <h2 className="text-lg font-semibold">La tua pagina pubblica</h2>
          <p className="text-sm text-muted-foreground">
            È la pagina dove i tuoi clienti vedono i tuoi contatti e prenotano.{" "}
            <a className="text-primary underline" href={account.shop_url} target="_blank" rel="noopener noreferrer">Aprila</a>
          </p>
          <div className="flex items-center gap-4">
            {account.has_logo ? (
              <img src={`/${account.slug}/logo?v=${Date.now()}`} alt="Logo" className="h-16 w-16 rounded-lg border bg-white object-contain" />
            ) : (
              <div className="flex h-16 w-16 items-center justify-center rounded-lg border bg-muted text-xs text-muted-foreground">Nessun logo</div>
            )}
            <div className="flex flex-wrap gap-2">
              <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp,image/gif" className="hidden" onChange={(e) => uploadLogo((e.target as HTMLInputElement).files?.[0])} />
              <Button variant="outline" size="sm" disabled={logoBusy} onClick={() => fileRef.current?.click()}>{account.has_logo ? "Cambia logo" : "Carica logo"}</Button>
              {account.has_logo && <Button variant="ghost" size="sm" disabled={logoBusy} onClick={removeLogo}>Rimuovi</Button>}
            </div>
          </div>
          <div className="flex items-center gap-4">
            {account.has_cover ? (
              <img src={`/${account.slug}/copertina?v=${Date.now()}`} alt="Copertina" className="h-20 w-16 rounded-t-full rounded-b-md border bg-white object-cover" />
            ) : (
              <div className="flex h-20 w-16 items-center justify-center rounded-t-full rounded-b-md border bg-muted px-1 text-center text-[10px] text-muted-foreground">Nessuna foto</div>
            )}
            <div className="space-y-1">
              <div className="flex flex-wrap gap-2">
                <input ref={coverRef} type="file" accept="image/png,image/jpeg,image/webp" className="hidden" onChange={(e) => uploadCover((e.target as HTMLInputElement).files?.[0])} />
                <Button variant="outline" size="sm" disabled={coverBusy} onClick={() => coverRef.current?.click()}>{account.has_cover ? "Cambia foto di copertina" : "Carica foto di copertina"}</Button>
                {account.has_cover && <Button variant="ghost" size="sm" disabled={coverBusy} onClick={removeCover}>Rimuovi</Button>}
              </div>
              <p className="text-xs text-muted-foreground">Compare in alto nella tua pagina, nella cornice ad arco. Meglio una foto verticale del negozio o di un tuo lavoro.</p>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shop-slug">Indirizzo della pagina</Label>
            <div className="flex items-center gap-1 text-sm">
              <span className="text-muted-foreground">{host}</span>
              <Input id="shop-slug" className="h-11 sm:h-10" value={f.slug ?? ""} onInput={set("slug")} />
            </div>
            <p className="text-xs text-muted-foreground">Solo lettere minuscole, numeri e trattini. Se lo cambi, il vecchio link smette di funzionare.</p>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shop-desc">Descrizione</Label>
            <textarea id="shop-desc" rows={3} maxLength={1000} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={f.shop_description ?? ""} onInput={set("shop_description")} placeholder="Due righe per presentare la tua attività" />
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1.5"><Label htmlFor="shop-phone">Telefono</Label><Input id="shop-phone" className="h-11 sm:h-10" value={f.shop_phone ?? ""} onInput={set("shop_phone")} /></div>
            <div className="space-y-1.5"><Label htmlFor="shop-wa">WhatsApp (con prefisso)</Label><Input id="shop-wa" className="h-11 sm:h-10" placeholder="es. 393331234567" value={f.shop_whatsapp ?? ""} onInput={set("shop_whatsapp")} /></div>
            <div className="space-y-1.5"><Label htmlFor="shop-email">Email per le richieste</Label><Input id="shop-email" type="email" className="h-11 sm:h-10" placeholder={account.email} value={f.shop_email ?? ""} onInput={set("shop_email")} /></div>
            <div className="space-y-1.5"><Label htmlFor="shop-address">Indirizzo</Label><Input id="shop-address" className="h-11 sm:h-10" value={f.shop_address ?? ""} onInput={set("shop_address")} /></div>
          </div>
          <div className="space-y-2">
            <Label>Galleria dei tuoi lavori ({(account.photos ?? []).length}/8)</Label>
            <div className="flex flex-wrap gap-2">
              {(account.photos ?? []).map((id) => (
                <div key={id} className="relative">
                  <img src={`/${account.slug}/foto/${id}`} alt="" className="h-20 w-20 rounded-md border object-cover" />
                  <button type="button" disabled={photoBusy} onClick={() => removePhoto(id)} className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full border bg-white text-xs shadow" aria-label="Togli la foto">✕</button>
                </div>
              ))}
              {(account.photos ?? []).length < 8 && (
                <button type="button" disabled={photoBusy} onClick={() => photoRef.current?.click()} className="flex h-20 w-20 items-center justify-center rounded-md border border-dashed text-xs text-muted-foreground">
                  {photoBusy ? "..." : "+ Aggiungi"}
                </button>
              )}
              <input ref={photoRef} type="file" multiple accept="image/png,image/jpeg,image/webp" className="hidden" onChange={(e) => addPhotos((e.target as HTMLInputElement).files)} />
            </div>
            <p className="text-xs text-muted-foreground">Fino a 8 foto del negozio o dei tuoi lavori: compaiono nella tua pagina sotto i servizi. La prima è la più grande.</p>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shop-review">Link per le recensioni Google</Label>
            <Input id="shop-review" type="url" className="h-11 sm:h-10" placeholder="https://g.page/r/..." value={f.review_url ?? ""} onInput={set("review_url")} />
            <p className="text-xs text-muted-foreground">Lo trovi nel tuo profilo Google: "Chiedi recensioni". Compare nella tua pagina e, il giorno dopo un appuntamento segnato come completato, i clienti ricevono un'email per lasciarti una recensione.</p>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shop-hours">Orari di apertura (note per i clienti)</Label>
            <textarea id="shop-hours" rows={3} maxLength={500} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={f.shop_hours ?? ""} onInput={set("shop_hours")} placeholder={"es. Sabato pomeriggio su appuntamento"} />
            <p className="text-xs text-muted-foreground">Nella pagina i giorni e gli orari si vedono già in automatico dagli orari di lavoro: qui scrivi solo eventuali note.</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="space-y-4 p-4 sm:p-6">
          <h2 className="text-lg font-semibold">Prenotazioni online</h2>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={enabled} onChange={(e) => setEnabled((e.target as HTMLInputElement).checked)} />
            I clienti possono prenotare dalla mia pagina (le richieste restano da approvare)
          </label>
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="space-y-1.5">
              <Label htmlFor="slot">Un orario ogni</Label>
              <select id="slot" className={selectClass} value={f.slot_minutes ?? "30"} onChange={set("slot_minutes")}>
                {[10, 15, 20, 30, 45, 60].map((m) => <option key={m} value={m}>{m} minuti</option>)}
              </select>
            </div>
            <div className="space-y-1.5"><Label htmlFor="notice">Preavviso minimo (ore)</Label><Input id="notice" type="number" min={0} max={168} className="h-11 sm:h-10" value={f.booking_notice_hours ?? ""} onInput={set("booking_notice_hours")} /></div>
            <div className="space-y-1.5"><Label htmlFor="horizon">Prenotabile fino a (giorni)</Label><Input id="horizon" type="number" min={1} max={365} className="h-11 sm:h-10" value={f.booking_horizon_days ?? ""} onInput={set("booking_horizon_days")} /></div>
          </div>
          <p className="text-sm text-muted-foreground">I giorni e gli orari liberi dipendono dagli orari di lavoro qui sotto, dagli appuntamenti già presi e dagli orari bloccati.</p>
          <div className="flex items-center gap-3">
            <Button className="min-h-11 sm:min-h-0" disabled={saving} onClick={save}>{saving ? "Salvataggio..." : "Salva pagina e prenotazioni"}</Button>
            {saved && <span className="text-sm text-emerald-700">Salvato</span>}
          </div>
        </CardContent>
      </Card>
    </>
  );
}
