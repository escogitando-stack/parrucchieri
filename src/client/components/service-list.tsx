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
                <TableHead className="w-20">Stato</TableHead>
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
                    <button
                      className={`text-xs font-medium ${svc.active ? "text-emerald-600" : "text-muted-foreground"}`}
                      onClick={() => updateService(svc.id, { active: svc.active ? 0 : 1 })}
                    >
                      {svc.active ? "Attivo" : "Non attivo"}
                    </button>
                    <button
                      className={`mt-1 block text-xs ${svc.online ? "text-primary" : "text-muted-foreground"}`}
                      title="Se attivo, i clienti possono prenotarlo dalla tua pagina pubblica"
                      onClick={() => updateService(svc.id, { online: svc.online ? 0 : 1 })}
                    >
                      {svc.online ? "Prenotabile online" : "Solo interno"}
                    </button>
                    {svc.online ? (
                      <button
                        className={`mt-1 block text-xs ${svc.auto_confirm ? "text-primary" : "text-muted-foreground"}`}
                        title="Se attivo, la prenotazione online è confermata subito (nell'orario libero scelto), senza che tu debba approvarla"
                        onClick={() => updateService(svc.id, { auto_confirm: svc.auto_confirm ? 0 : 1 })}
                      >
                        {svc.auto_confirm ? "Conferma immediata" : "Da approvare"}
                      </button>
                    ) : null}
                  </TableCell>
                  <TableCell className="whitespace-nowrap">
                    <Button variant="ghost" size="icon" className="h-7 w-7 text-muted-foreground" title="Modifica" aria-label="Modifica" onClick={() => setEditing(svc)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7 text-muted-foreground hover:text-destructive" onClick={() => deleteService(svc.id)}>
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
