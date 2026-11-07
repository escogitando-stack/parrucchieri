import { parseDate } from "./dates";

const euro = new Intl.NumberFormat("it-IT", { style: "currency", currency: "EUR" });

/** `1234.5` → `1.234,50 €` */
export function formatEuro(value: number | null | undefined): string {
  return euro.format(value ?? 0);
}

/** `2026-11-07` → `07/11/2026`, read off the local calendar (no timezone shift). */
export function formatDateIt(date: string): string {
  if (!date) return "";
  return parseDate(date).toLocaleDateString("it-IT", { day: "2-digit", month: "2-digit", year: "numeric" });
}
