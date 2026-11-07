import { Badge } from "@/components/ui/badge";

const STATUS_CONFIG: Record<string, { label: string; variant: "default" | "secondary" | "destructive" | "outline" }> = {
  booked: { label: "Prenotato", variant: "default" },
  confirmed: { label: "Confermato", variant: "secondary" },
  in_progress: { label: "In corso", variant: "outline" },
  completed: { label: "Completato", variant: "secondary" },
  cancelled: { label: "Annullato", variant: "destructive" },
  no_show: { label: "Non presentato", variant: "destructive" },
};

export function StatusBadge({ status }: { status: string }) {
  const config = STATUS_CONFIG[status] || { label: status, variant: "outline" as const };
  return <Badge variant={config.variant}>{config.label}</Badge>;
}
