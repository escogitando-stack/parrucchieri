import { Badge } from "@/components/ui/badge";

const STATUS_CONFIG: Record<string, { label: string; variant: "default" | "secondary" | "destructive" | "outline" }> = {
  requested: { label: "Da approvare", variant: "outline" },
  booked: { label: "Prenotato", variant: "default" },
  confirmed: { label: "Confermato", variant: "secondary" },
  in_progress: { label: "In corso", variant: "outline" },
  completed: { label: "Completato", variant: "secondary" },
  cancelled: { label: "Annullato", variant: "destructive" },
  no_show: { label: "Non presentato", variant: "destructive" },
};

export function StatusBadge({ status }: { status: string }) {
  const config = STATUS_CONFIG[status] || { label: status, variant: "outline" as const };
  const extra = status === "requested" ? "border-amber-400 bg-amber-50 text-amber-800" : "";
  return <Badge variant={config.variant} className={extra}>{config.label}</Badge>;
}
