import { get, run } from "./db.js";

/**
 * Sample data + the appointment counter rows.
 *
 * These used to live at the bottom of schema.sql, but Clawnify applies that
 * file as DDL only — a single INSERT there fails the entire deploy. They run
 * from the app instead, on the first request an isolate serves.
 */

type StaffSeed = [id: number, name: string, email: string, title: string, color: string];
type ServiceSeed = [id: number, name: string, description: string, duration: number, price: number, color: string, category: string];
type ClientSeed = [id: number, name: string, email: string, phone: string];
type ProductSeed = [id: number, name: string, brand: string, category: string, price: number, cost: number, stock: number];

const STAFF: StaffSeed[] = [
  [1, "Giulia", "giulia@example.com", "Titolare / Hair stylist", "#ec4899"],
  [2, "Marco", "marco@example.com", "Barbiere", "#3b82f6"],
  [3, "Sara", "sara@example.com", "Colorista", "#f59e0b"],
  [4, "Elena", "elena@example.com", "Apprendista", "#8b5cf6"],
];

// Servizi tipici di un salone di parrucchieri (modificabili dall'app).
const SERVICES: ServiceSeed[] = [
  [1, "Taglio donna", "Shampoo, taglio e piega", 60, 45, "#ec4899", "Taglio"],
  [2, "Taglio uomo", "Taglio con rifinitura", 30, 20, "#3b82f6", "Taglio"],
  [3, "Piega", "Shampoo e piega", 45, 25, "#10b981", "Styling"],
  [4, "Colore", "Colorazione completa", 90, 60, "#f59e0b", "Colore"],
  [5, "Colpi di sole / Balayage", "Schiariture con tecnica a mano libera", 150, 120, "#8b5cf6", "Colore"],
  [6, "Trattamento ristrutturante", "Maschera e trattamento per capelli sfibrati", 30, 25, "#14b8a6", "Trattamento"],
  [7, "Barba", "Rifinitura barba con panno caldo", 20, 12, "#6b7280", "Taglio"],
  [8, "Acconciatura sposa", "Prova e acconciatura il giorno del matrimonio", 120, 150, "#f43f5e", "Styling"],
];

const CLIENTS: ClientSeed[] = [
  [1, "Chiara Rossi", "chiara@example.com", "333 1234567"],
  [2, "Luca Bianchi", "luca@example.com", "347 7654321"],
  [3, "Francesca Verdi", "francesca@example.com", "320 1112233"],
  [4, "Paolo Neri", "paolo@example.com", "339 4445566"],
];

const PRODUCTS: ProductSeed[] = [
  [1, "Shampoo professionale", "Salonline", "Cura capelli", 14.9, 7.0, 25],
  [2, "Maschera nutriente", "Salonline", "Cura capelli", 18.5, 9.0, 18],
  [3, "Lacca extra forte", "StylePro", "Styling", 11.9, 5.5, 30],
  [4, "Cera modellante uomo", "StylePro", "Styling", 12.5, 6.0, 20],
];

let seeded = false;

/** Insert `rows` into `table` only while it is still empty. */
async function seedIfEmpty(table: string, columns: string[], rows: readonly unknown[][]): Promise<void> {
  const existing = await get<{ count: number }>(`SELECT COUNT(*) as count FROM ${table}`);
  if ((existing?.count ?? 0) > 0) return;
  const placeholders = rows.map(() => `(${columns.map(() => "?").join(", ")})`).join(", ");
  await run(
    `INSERT OR IGNORE INTO ${table} (${columns.join(", ")}) VALUES ${placeholders}`,
    rows.flat(),
  );
}

/**
 * Idempotent. The demo tables are only seeded while still empty, so a redeploy
 * never resurrects a row the user deleted; the _meta rows are INSERT OR IGNORE
 * so an existing counter is never reset.
 */
export async function ensureSeeded(): Promise<void> {
  if (seeded) return;
  try {
    await run("INSERT OR IGNORE INTO _meta (key, value) VALUES ('appointment_counter', '0')");
    await run("INSERT OR IGNORE INTO _meta (key, value) VALUES ('appointment_prefix', 'APT')");
    await seedIfEmpty("staff", ["id", "name", "email", "title", "color"], STAFF);
    await seedIfEmpty("services", ["id", "name", "description", "duration", "price", "color", "category"], SERVICES);
    await seedIfEmpty("clients", ["id", "name", "email", "phone"], CLIENTS);
    await seedIfEmpty("products", ["id", "name", "brand", "category", "price", "cost", "stock"], PRODUCTS);
    seeded = true;
  } catch {
    // Sample data must never fail a request; retry on the next one.
    seeded = false;
  }
}
