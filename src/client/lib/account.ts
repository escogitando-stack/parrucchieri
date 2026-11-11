import { useEffect, useState } from "preact/hooks";
import { api } from "../api";

/** Nomi usati nell'interfaccia: cambiano in base al tipo di attività. */
export interface Terms {
  staff: string; // collaboratore
  staffs: string; // Personale
  service: string; // servizio
  services: string; // Servizi
  product: string; // prodotto
  products: string; // Prodotti
  staff_hint: string;
}

export interface AccountInfo {
  email: string;
  business_name: string;
  category: string;
  category_label: string;
  terms: Terms;
  categories: Record<string, string>;
  plan: { state: "trial" | "active" | "expired" | "suspended" | "pending"; days_left: number; ends_at: string };
  is_admin: boolean;
  slug: string;
  shop_url: string;
  booking_enabled: number;
  slot_minutes: number;
  booking_notice_hours: number;
  booking_horizon_days: number;
  shop_phone: string;
  shop_whatsapp: string;
  shop_email: string;
  shop_address: string;
  shop_description: string;
  shop_hours: string;
  has_logo: boolean;
  has_cover: boolean;
  daily_summary: boolean;
  impersonating?: boolean;
}

const DEFAULT_TERMS: Terms = {
  staff: "collaboratore",
  staffs: "Personale",
  service: "servizio",
  services: "Servizi",
  product: "prodotto",
  products: "Prodotti",
  staff_hint: "es. Responsabile",
};

let current: AccountInfo | null = null;
let pending: Promise<void> | null = null;
const listeners = new Set<() => void>();

export function setAccount(account: AccountInfo): void {
  current = account;
  document.title = account.business_name;
  listeners.forEach((notify) => notify());
}

export function loadAccount(): Promise<void> {
  if (!pending) {
    pending = api<{ account: AccountInfo }>("GET", "/api/account")
      .then((data) => setAccount(data.account))
      .finally(() => { pending = null; });
  }
  return pending;
}

/** Dati del negozio (nome, tipo di attività, prova/abbonamento), caricati una volta sola. */
export function useAccount(): AccountInfo | null {
  const [, setTick] = useState(0);
  useEffect(() => {
    const notify = () => setTick((n) => n + 1);
    listeners.add(notify);
    if (!current) loadAccount().catch(() => {});
    return () => { listeners.delete(notify); };
  }, []);
  return current;
}

export function useTerms(): Terms {
  return useAccount()?.terms ?? DEFAULT_TERMS;
}

export function cap(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1);
}
