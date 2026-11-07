import { useState } from "preact/hooks";
import { useTerms } from "@/lib/account";
import { useApp } from "../context";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";

const COLORS = ["#3b82f6", "#10b981", "#f59e0b", "#8b5cf6", "#ec4899", "#ef4444", "#14b8a6", "#f97316"];

export function CreateStaff({ onClose }: { onClose: () => void }) {
  const t = useTerms();
  const { addStaff, setError } = useApp();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [title, setTitle] = useState("");
  const [color, setColor] = useState(COLORS[0]);
  const [saving, setSaving] = useState(false);

  const handleSubmit = async () => {
    if (!name.trim()) { setError("Il nome è obbligatorio"); return; }
    setSaving(true);
    try {
      await addStaff({ name: name.trim(), email, phone, title, color });
      onClose();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Aggiungi {t.staff}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label>Nome *</Label>
            <Input value={name} onChange={(e) => setName((e.target as HTMLInputElement).value)} placeholder="Nome e cognome" />
          </div>
          <div className="space-y-1.5">
            <Label>Qualifica / Ruolo</Label>
            <Input value={title} onChange={(e) => setTitle((e.target as HTMLInputElement).value)} placeholder={t.staff_hint} />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label>Email</Label>
              <Input type="email" value={email} onChange={(e) => setEmail((e.target as HTMLInputElement).value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Telefono</Label>
              <Input value={phone} onChange={(e) => setPhone((e.target as HTMLInputElement).value)} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Colore</Label>
            <div className="flex gap-2">
              {COLORS.map((c) => (
                <button
                  key={c}
                  className={`h-8 w-8 rounded-full transition-transform ${color === c ? "scale-110 ring-2 ring-ring ring-offset-2" : "hover:scale-105"}`}
                  style={{ backgroundColor: c }}
                  onClick={() => setColor(c)}
                />
              ))}
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Annulla</Button>
          <Button disabled={saving} onClick={handleSubmit}>{saving ? "Salvataggio..." : "Aggiungi"}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
