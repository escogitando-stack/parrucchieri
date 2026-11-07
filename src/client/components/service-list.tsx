import { useState } from "preact/hooks";
import { useTerms } from "@/lib/account";
import { useApp } from "../context";
import { Plus, Trash2, Pencil } from "lucide-preact";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { CreateService } from "./create-service";
import type { Service } from "../types";
import { formatEuro } from "@/lib/format";

function Switch({ on, label, hint, onChange }: { on: boolean; label: string; hint?: string; onChange: () => void }) {
  return (
    <div title={hint}>
      <button type="button" role="switch" aria-checked={on} onClick={onChange} className="flex items-center gap-2 text-xs">
        <span className={`relative inline-block h-4 w-7 shrink-0 rounded-full transition-colors ${on ? "bg-primary" : "bg-muted-foreground/30"}`}>
          <span className={`absolute top-0.5 h-3 w-3 rounded-full bg-white shadow transition-all ${on ? "left-3.5" : "left-0.5"}`} />
        </span>
        <span className={on ? "font-medium" : "text-muted-foreground"}>{label}</span>
      </button>
      {hint && label === "Conferma immediata" && <p className="ml-9 mt-0.5 text-[11px] leading-tight text-muted-foreground">{hint}</p>}
    </div>
  );
}

export function ServiceList() {
  const t = useTerms();
  const { services, deleteService, updateService } = useApp();
  const [showCreate, setShowCreate] = useState(false);
  const [editing, setEditing] = useState<Service | null>(null);

  const categories = [...new Set(services.map((s) => s.category).filter(Boolean))];

  return (
    <div className="space-y-4 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold tracking-tight">{t.services}</h1>
        <Button size="sm" onClick={() => setShowCreate(true)}>
          <Plus className="mr-1 h-3.5 w-3.5" /> Aggiungi {t.service}
        </Button>
      </div>

      {showCreate && <CreateService onClose={() => setShowCreate(false)} />}
      {editing && <CreateService key={editing.id} service={editing} onClose={() => setEditing(null)} />}

      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-8" />
                <TableHead>Nome</TableHead>
                <TableHead className="w-28">Categoria</TableHead>
                <TableHead className="w-24 text-center">Durata</TableHead>
                <TableHead className="w-20 text-right">Prezzo</TableHead>
                <TableHead className="w-56">Stato</TableHead>
                <TableHead className="w-20" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {services.length === 0 && (
                <TableRow><TableCell colSpan={7} className="py-8 text-center text-muted-foreground">Nessun {t.service}</TableCell></TableRow>
              )}
              {services.map((svc) => (
                <TableRow key={svc.id}>
                  <TableCell>
                    <span className="inline-block h-3 w-3 rounded-full" style={{ backgroundColor: svc.color }} />
                  </TableCell>
                  <TableCell>
                    <button className="text-left font-medium hover:underline" onClick={() => setEditing(svc)}>{svc.name}</button>
                    {svc.description && <div className="text-xs text-muted-foreground">{svc.description}</div>}
                  </TableCell>
                  <TableCell>
                    {svc.category && <Badge variant="outline" className="text-xs">{svc.category}</Badge>}
                  </TableCell>
                  <TableCell className="text-center">{svc.duration} min</TableCell>
                  <TableCell className="text-right font-medium">{formatEuro(svc.price)}</TableCell>
                  <TableCell>
                    <div className="space-y-2">
                      <Switch on={!!svc.active} label="Attivo" onChange={() => updateService(svc.id, { active: svc.active ? 0 : 1 })} />
                      <Switch on={!!svc.online} label="Prenotabile online" hint="I clienti possono prenotarlo dalla tua pagina pubblica" onChange={() => updateService(svc.id, { online: svc.online ? 0 : 1 })} />
                      {svc.online ? (
                        <Switch
                          on={!!svc.auto_confirm}
                          label="Conferma immediata"
                          hint={svc.auto_confirm ? "La prenotazione è confermata subito, senza che tu approvi" : "Ogni richiesta aspetta la tua approvazione"}
                          onChange={() => updateService(svc.id, { auto_confirm: svc.auto_confirm ? 0 : 1 })}
                        />
                      ) : null}
                    </div>
                  </TableCell>
                  <TableCell className="whitespace-nowrap">
                    <Button variant="ghost" size="icon" className="h-7 w-7 text-muted-foreground" title="Modifica" aria-label="Modifica" onClick={() => setEditing(svc)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7 text-muted-foreground hover:text-destructive" onClick={() => { if (window.confirm(`Eliminare "${svc.name}"?\n\nSparirà dalle prenotazioni che lo includono. Se vuoi solo nasconderlo, disattivalo.`)) deleteService(svc.id); }}>
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
