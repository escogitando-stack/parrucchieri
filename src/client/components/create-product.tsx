import { useState } from "preact/hooks";
import { useTerms } from "@/lib/account";
import { useApp } from "../context";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import type { Product } from "../types";

function numberOr(value: string, fallback: number): number {
  if (value.trim() === "") return fallback;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

export function CreateProduct({ onClose, product }: { onClose: () => void; product?: Product }) {
  const t = useTerms();
  const { addProduct, updateProduct, setError } = useApp();
  const [name, setName] = useState(product?.name || "");
  const [brand, setBrand] = useState(product?.brand || "");
  const [category, setCategory] = useState(product?.category || "");
  const [sku, setSku] = useState(product?.sku || "");
  const [price, setPrice] = useState(String(product?.price ?? 0));
  const [cost, setCost] = useState(String(product?.cost ?? 0));
  const [stock, setStock] = useState(String(product?.stock ?? 0));
  const [lowStockAlert, setLowStockAlert] = useState(String(product?.low_stock_alert ?? 5));
  const [saving, setSaving] = useState(false);
  const isEditing = product !== undefined;

  const handleSubmit = async () => {
    if (!name.trim()) { setError("Il nome è obbligatorio"); return; }
    setSaving(true);
    try {
      const data = {
        name: name.trim(), brand, category, sku,
        price: numberOr(price, 0),
        cost: numberOr(cost, 0),
        stock: Math.trunc(numberOr(stock, 0)),
        low_stock_alert: Math.trunc(numberOr(lowStockAlert, 5)),
      };
      if (product) await updateProduct(product.id, data);
      else await addProduct(data);
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
          <DialogTitle>{isEditing ? `Modifica ${t.product}` : `Aggiungi ${t.product}`}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="product-name">Name *</Label>
            <Input id="product-name" value={name} onChange={(e) => setName((e.target as HTMLInputElement).value)} placeholder={`Nome ${t.product}`} />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="product-brand">Marca</Label>
              <Input id="product-brand" value={brand} onChange={(e) => setBrand((e.target as HTMLInputElement).value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="product-category">Categoria</Label>
              <Input id="product-category" value={category} onChange={(e) => setCategory((e.target as HTMLInputElement).value)} placeholder="es. Cura capelli" />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="product-sku">Codice</Label>
            <Input id="product-sku" value={sku} onChange={(e) => setSku((e.target as HTMLInputElement).value)} placeholder="Facoltativo" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="product-price">Sell Prezzo (€)</Label>
              <Input id="product-price" type="number" min="0" step="0.01" value={price} onChange={(e) => setPrice((e.target as HTMLInputElement).value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="product-cost">Costo (€)</Label>
              <Input id="product-cost" type="number" min="0" step="0.01" value={cost} onChange={(e) => setCost((e.target as HTMLInputElement).value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="product-stock">Giacenza</Label>
              <Input id="product-stock" type="number" min="0" step="1" value={stock} onChange={(e) => setStock((e.target as HTMLInputElement).value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="product-low-stock-alert">Soglia scorta bassa</Label>
              <Input id="product-low-stock-alert" type="number" min="0" step="1" value={lowStockAlert} onChange={(e) => setLowStockAlert((e.target as HTMLInputElement).value)} />
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Annulla</Button>
          <Button disabled={saving} onClick={handleSubmit}>{saving ? "Salvataggio..." : isEditing ? "Salva modifiche" : `Aggiungi ${t.product}`}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
