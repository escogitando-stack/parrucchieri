import { useEffect, useState } from "preact/hooks";
import { useApp } from "../context";
import { api } from "../api";
import { useTerms, cap } from "@/lib/account";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

const DAYS = ["Lunedì", "Martedì", "Mercoledì", "Giovedì", "Venerdì", "Sabato", "Domenica"];

/** Per ogni giorno due fasce: [da1, a1, da2, a2]. Vuoto = chiuso. */
type DayRow = [string, string, string, string];
type Week = DayRow[];
type Range = { weekday: number; start_time: string; end_time: string };

function emptyWeek(): Week {
  return DAYS.map(() => ["", "", "", ""] as DayRow);
}

function toWeek(ranges: Range[]): Week {
  const week = emptyWeek();
  for (let d = 1; d <= 7; d++) {
    const list = ranges.filter((r) => r.weekday === d).sort((a, b) => a.start_time.localeCompare(b.start_time)).slice(0, 2);
    list.forEach((r, i) => { week[d - 1][i * 2] = r.start_time; week[d - 1][i * 2 + 1] = r.end_time; });
  }
  return week;
}

function toRanges(week: Week): Range[] {
  const out: Range[] = [];
  week.forEach((row, i) => {
    for (let k = 0; k < 2; k++) {
      const from = row[k * 2];
      const to = row[k * 2 + 1];
      if (from && to) out.push({ weekday: i + 1, start_time: from, end_time: to });
    }
  });
  return out;
}

export function WorkingHours() {
  const t = useTerms();
  const { staffLookup, setError } = useApp();
  const [weeks, setWeeks] = useState<Record<number, Week>>({});
  const [loaded, setLoaded] = useState(false);
  const [savingId, setSavingId] = useState<number | null>(null);
  const [savedId, setSavedId] = useState<number | null>(null);

  useEffect(() => {
    api<{ hours: Record<string, Range[]> }>("GET", "/api/working-hours")
      .then((data) => {
        const next: Record<number, Week> = {};
        staffLookup.forEach((s) => { next[s.id] = toWeek(data.hours[String(s.id)] ?? []); });
        setWeeks(next);
        setLoaded(true);
      })
      .catch((err) => setError((err as Error).message));
  }, [staffLookup.map((s) => s.id).join(",")]); // eslint-disable-line react-hooks/exhaustive-deps

  const setCell = (staffId: number, day: number, idx: number, value: string) => {
    setWeeks((prev) => {
      const week = (prev[staffId] ?? emptyWeek()).map((r) => [...r] as DayRow);
      week[day][idx] = value;
      return { ...prev, [staffId]: week };
    });
    setSavedId(null);
  };

  const save = async (staffId: number) => {
    setSavingId(staffId);
    try {
      await api("PUT", `/api/working-hours/${staffId}`, { hours: toRanges(weeks[staffId] ?? emptyWeek()) });
      setSavedId(staffId);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSavingId(null);
    }
  };

  if (staffLookup.length === 0) return null;

  return (
    <Card>
      <CardContent className="space-y-5 p-4 sm:p-6">
        <div>
          <h2 className="text-lg font-semibold">Orari di lavoro</h2>
          <p className="text-sm text-muted-foreground">
            Quando ogni {t.staff} è disponibile per le prenotazioni online. Per ogni giorno puoi indicare due fasce (per esempio mattina e pomeriggio): lascia vuoto per il giorno di chiusura.
          </p>
        </div>
        {!loaded && <p className="text-sm text-muted-foreground">Caricamento...</p>}
        {loaded && staffLookup.map((s) => (
          <div key={s.id} className="space-y-2 rounded-lg border p-3">
            <div className="flex items-center gap-2 font-medium">
              <span className="inline-block h-3 w-3 rounded-full" style={{ backgroundColor: s.color }} /> {cap(s.name)}
            </div>
            <div className="space-y-1.5">
              {DAYS.map((label, d) => (
                <div key={label} className="grid grid-cols-[88px_1fr] items-center gap-2 text-sm sm:grid-cols-[110px_1fr]">
                  <span>{label}</span>
                  <div className="flex flex-wrap items-center gap-1.5">
                    {[0, 2].map((k) => (
                      <span key={k} className="flex items-center gap-1">
                        <Input type="time" className="h-9 w-[104px] px-2 text-sm" aria-label={`${label} da`} value={weeks[s.id]?.[d][k] ?? ""} onChange={(e) => setCell(s.id, d, k, (e.target as HTMLInputElement).value)} />
                        <span>–</span>
                        <Input type="time" className="h-9 w-[104px] px-2 text-sm" aria-label={`${label} a`} value={weeks[s.id]?.[d][k + 1] ?? ""} onChange={(e) => setCell(s.id, d, k + 1, (e.target as HTMLInputElement).value)} />
                      </span>
                    ))}
                  </div>
                </div>
              ))}
            </div>
            <div className="flex items-center gap-3 pt-1">
              <Button size="sm" className="min-h-11 sm:min-h-0" disabled={savingId === s.id} onClick={() => save(s.id)}>{savingId === s.id ? "Salvataggio..." : "Salva orari"}</Button>
              {savedId === s.id && <span className="text-sm text-emerald-700">Salvato</span>}
            </div>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
