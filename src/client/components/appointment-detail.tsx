import { useState } from "preact/hooks";
import { useTerms, cap } from "@/lib/account";
import { useApp } from "../context";
import { ArrowLeft, Trash2, Send, Clock, User, DollarSign, Check, X, MessageCircle } from "lucide-preact";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { StatusBadge } from "./status-badge";
import { RescheduleAppointment } from "./reschedule-appointment";
import { conflictsFrom, conflictSentence } from "@/lib/conflicts";
import { formatDateIt, formatEuro } from "@/lib/format";

export function AppointmentDetail() {
  const t = useTerms();
  const {
    selectedAppointment: apt, navigate, updateAppointment, deleteAppointment,
    addAppointmentNote, deleteAppointmentNote, staffLookup, setError, decideAppointment, services,
  } = useApp();
  const [editingSvc, setEditingSvc] = useState(false);
  const [svcSel, setSvcSel] = useState<number[]>([]);
  const [noteText, setNoteText] = useState("");
  const [rescheduling, setRescheduling] = useState(false);
  const [deciding, setDeciding] = useState(false);
  const [outcome, setOutcome] = useState<{ approved: boolean; email_sent: boolean; whatsapp_url: string } | null>(null);

  if (!apt) return null;

  // Reassigning can now be refused, because the new person may already be busy.
  const save = async (patch: Partial<typeof apt>) => {
    try {
      await updateAppointment(apt.id, patch);
    } catch (err) {
      const clashes = conflictsFrom(err);
      setError(clashes
        ? conflictSentence(staffLookup.find((s) => s.id === patch.staff_id)?.name ?? "", clashes)
        : (err as Error).message);
    }
  };

  const handleStatusChange = (status: string) => save({ status } as Partial<typeof apt>);
  const handleStaffChange = (staffId: string) => save({ staff_id: staffId ? parseInt(staffId) : null } as Partial<typeof apt>);

  const decide = async (approve: boolean, allow = false) => {
    setDeciding(true);
    try {
      const res = await decideAppointment(apt.id, approve, allow ? { allow_conflict: true } : {});
      setOutcome({ approved: approve, ...res });
    } catch (err) {
      const clashes = conflictsFrom(err);
      if (clashes && window.confirm(`${conflictSentence(apt.staff_name ?? "", clashes)}\n\nConfermare comunque?`)) {
        await decide(approve, true);
        return;
      }
      if (!clashes) setError((err as Error).message);
    } finally {
      setDeciding(false);
    }
  };

  const startEditSvc = () => {
    setSvcSel((apt.appointment_services ?? []).map((x) => x.service_id));
    setEditingSvc(true);
  };
  const toggleSvc = (id: number) => setSvcSel((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));
  const saveSvc = async (allow = false) => {
    if (svcSel.length === 0) { setError(`Scegli almeno un ${t.service}`); return; }
    try {
      await updateAppointment(apt.id, { service_ids: svcSel, ...(allow ? { allow_conflict: true } : {}) } as Partial<typeof apt> & { allow_conflict?: boolean });
      setEditingSvc(false);
    } catch (err) {
      const clashes = conflictsFrom(err);
      if (clashes && !allow && window.confirm(`${conflictSentence(apt.staff_name ?? "", clashes)}\n\nSalvare comunque?`)) {
        await saveSvc(true);
        return;
      }
      if (!clashes) setError((err as Error).message);
    }
  };

  const handleAddNote = async () => {
    if (!noteText.trim()) return;
    await addAppointmentNote(apt.id, noteText.trim());
    setNoteText("");
  };

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => navigate("/appointments")}>
          <ArrowLeft className="mr-1 h-4 w-4" /> Indietro
        </Button>
        <h1 className="flex-1 text-2xl font-bold">{apt.identifier}</h1>
        <StatusBadge status={apt.status} />
        <Button variant="destructive" size="sm" onClick={() => deleteAppointment(apt.id)}>
          <Trash2 className="mr-1 h-3.5 w-3.5" /> Elimina
        </Button>
      </div>

      {apt.status === "requested" && (
        <Card className="border-amber-300 bg-amber-50">
          <CardContent className="space-y-3 p-4">
            <p className="font-semibold text-amber-900">
              {apt.source === "online_free" ? "Richiesta fuori disponibilità da approvare" : "Richiesta online da approvare"}
            </p>
            <p className="text-sm text-amber-900">
              {apt.client_name} chiede {formatDateIt(apt.scheduled_date)} alle {apt.start_time}.
              {apt.source === "online_free" && " Ha scelto un orario non tra quelli liberi: se confermi, puoi anche spostarla."}
              {" "}Confermando, il cliente riceve un'email{apt.client_email ? ` a ${apt.client_email}` : ""}.
            </p>
            <div className="flex flex-wrap gap-2">
              <Button className="min-h-11 sm:min-h-0" disabled={deciding} onClick={() => decide(true)}><Check className="mr-1 h-4 w-4" /> Approva</Button>
              <Button variant="outline" className="min-h-11 sm:min-h-0" disabled={deciding} onClick={() => decide(false)}><X className="mr-1 h-4 w-4" /> Rifiuta</Button>
            </div>
          </CardContent>
        </Card>
      )}
      {outcome && (
        <Card className="border-emerald-300 bg-emerald-50">
          <CardContent className="space-y-2 p-4 text-sm text-emerald-900">
            <p className="font-semibold">{outcome.approved ? "Prenotazione confermata." : "Richiesta rifiutata."}</p>
            <p>{outcome.email_sent ? "Abbiamo inviato l'email al cliente." : "Il cliente non ha un'email valida: avvisalo tu."}</p>
            {outcome.whatsapp_url && (
              <Button asChild variant="outline" size="sm">
                <a href={outcome.whatsapp_url} target="_blank" rel="noopener noreferrer"><MessageCircle className="mr-1 h-4 w-4" /> Avvisa anche su WhatsApp</a>
              </Button>
            )}
          </CardContent>
        </Card>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>Dettagli prenotazione</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                  <Label className="flex items-center gap-1.5 text-xs text-muted-foreground"><Clock className="h-3 w-3" /> Data e ora</Label>
                  <p className="text-sm font-medium">{formatDateIt(apt.scheduled_date)} alle {apt.start_time} - {apt.end_time}</p>
                  <Button variant="outline" size="sm" className="px-2" onClick={() => setRescheduling(true)}>Sposta</Button>
                </div>
                <div className="space-y-1">
                  <Label className="flex items-center gap-1.5 text-xs text-muted-foreground"><User className="h-3 w-3" /> Client</Label>
                  <button className="text-sm font-medium text-primary hover:underline" onClick={() => navigate(`/clients/${apt.client_id}`)}>
                    {apt.client_name}
                  </button>
                  {apt.client_phone && <p className="text-xs text-muted-foreground">{apt.client_phone}</p>}
                  {apt.client_email && <p className="text-xs text-muted-foreground">{apt.client_email}</p>}
                  {apt.status !== "requested" && apt.whatsapp?.confirm && (
                    <a className="inline-flex items-center gap-1 text-xs text-primary hover:underline" href={apt.whatsapp.confirm} target="_blank" rel="noopener noreferrer">
                      <MessageCircle className="h-3 w-3" /> Scrivi su WhatsApp
                    </a>
                  )}
                </div>
              </div>
              <Separator />
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <Label className="text-xs text-muted-foreground">{t.staffs}</Label>
                  <select className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={apt.staff_id || ""} onChange={(e) => handleStaffChange((e.target as HTMLSelectElement).value)}>
                    <option value="">Non assegnato</option>
                    {staffLookup.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs text-muted-foreground">Stato</Label>
                  <select className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={apt.status} onChange={(e) => handleStatusChange((e.target as HTMLSelectElement).value)}>
                    <option value="requested">Da approvare</option>
                    <option value="booked">Prenotato</option>
                    <option value="confirmed">Confermato</option>
                    <option value="in_progress">In corso</option>
                    <option value="completed">Completato</option>
                    <option value="cancelled">Annullato</option>
                    <option value="no_show">Non presentato</option>
                  </select>
                </div>
              </div>
              <div className="space-y-1">
                <Label className="flex items-center gap-1.5 text-xs text-muted-foreground"><DollarSign className="h-3 w-3" /> Totale</Label>
                <p className="text-lg font-bold">{formatEuro(apt.total_price)}</p>
              </div>
              {apt.notes && (
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">Notes</Label>
                  <p className="text-sm">{apt.notes}</p>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader className="flex-row items-center justify-between pb-3">
              <CardTitle className="text-base">Servizi</CardTitle>
              {!editingSvc && <Button variant="outline" size="sm" className="px-2" onClick={startEditSvc}>Modifica</Button>}
            </CardHeader>
            <CardContent>
              {editingSvc ? (
                <div className="space-y-2">
                  {services.filter((sv) => sv.active !== 0 || svcSel.includes(sv.id)).map((sv) => (
                    <label key={sv.id} className="flex items-center justify-between gap-2 text-sm">
                      <span className="flex items-center gap-2">
                        <input type="checkbox" checked={svcSel.includes(sv.id)} onChange={() => toggleSvc(sv.id)} />
                        {sv.name}
                      </span>
                      <span className="text-xs text-muted-foreground">{sv.duration}min · {formatEuro(sv.price)}</span>
                    </label>
                  ))}
                  <p className="text-xs text-muted-foreground">Durata e totale si aggiornano da soli.</p>
                  <div className="flex gap-2">
                    <Button size="sm" onClick={() => saveSvc()}>Salva</Button>
                    <Button size="sm" variant="outline" onClick={() => setEditingSvc(false)}>Annulla</Button>
                  </div>
                </div>
              ) : (!apt.appointment_services || apt.appointment_services.length === 0) ? (
                <p className="text-sm text-muted-foreground">Nessun {t.service} aggiunto</p>
              ) : (
                <div className="space-y-2">
                  {apt.appointment_services.map((svc) => (
                    <div key={svc.id} className="flex items-center justify-between text-sm">
                      <span>{svc.service_name || `${cap(t.service)} n. ${svc.service_id}`}</span>
                      <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">{svc.duration}min</span>
                        <span className="font-medium">{formatEuro(svc.price)}</span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Attività</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex gap-2">
                <Input
                  placeholder="Aggiungi una nota..."
                  value={noteText}
                  onChange={(e) => setNoteText((e.target as HTMLInputElement).value)}
                  onKeyDown={(e) => e.key === "Enter" && handleAddNote()}
                />
                <Button variant="outline" size="icon" onClick={handleAddNote}>
                  <Send className="h-4 w-4" />
                </Button>
              </div>
              {(!apt.appointment_notes || apt.appointment_notes.length === 0) ? (
                <p className="text-sm text-muted-foreground">Nessuna nota</p>
              ) : (
                <div className="space-y-2">
                  {apt.appointment_notes.map((note) => (
                    <div key={note.id} className="rounded-md border bg-muted/30 p-2.5">
                      <p className="text-sm">{note.content}</p>
                      <div className="mt-1 flex items-center justify-between">
                        <span className="text-[10px] text-muted-foreground">{new Date(note.created_at).toLocaleString("it-IT")}</span>
                        <Button variant="ghost" size="icon" className="h-5 w-5 text-muted-foreground hover:text-destructive" onClick={() => deleteAppointmentNote(note.id)}>
                          <Trash2 className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
      {rescheduling && <RescheduleAppointment key={apt.id} appointment={apt} onClose={() => setRescheduling(false)} />}
    </div>
  );
}
