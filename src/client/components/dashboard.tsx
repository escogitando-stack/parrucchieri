import { useApp } from "../context";
import { useTerms } from "@/lib/account";
import { CalendarDays, Users, Clock, DollarSign, Package, AlertTriangle } from "lucide-preact";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { today } from "@/lib/dates";
import { formatEuro } from "@/lib/format";

export function Dashboard() {
  const t = useTerms();
  const { stats, navigate, calendarAppointments } = useApp();

  const todayStr = today();
  const todayAppointments = calendarAppointments
    .filter((a) => a.scheduled_date === todayStr)
    .sort((a, b) => a.start_time.localeCompare(b.start_time));

  const statCards = [
    { label: "Appuntamenti di oggi", value: stats.today_appointments, icon: CalendarDays, color: "text-violet-600 bg-violet-50", onClick: () => navigate("/calendar") },
    { label: "In arrivo", value: stats.upcoming_appointments, icon: Clock, color: "text-blue-600 bg-blue-50", onClick: () => navigate("/appointments") },
    { label: "Clienti", value: stats.clients, icon: Users, color: "text-emerald-600 bg-emerald-50", onClick: () => navigate("/clients") },
    { label: `Incasso ${t.services.toLowerCase()} completati`, value: formatEuro(stats.revenue), icon: DollarSign, color: "text-amber-600 bg-amber-50" },
    { label: t.products, value: stats.products, icon: Package, color: "text-rose-600 bg-rose-50", onClick: () => navigate("/products") },
  ];

  return (
    <div className="space-y-6 p-6">
      <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {statCards.map((stat) => (
          <Card
            key={stat.label}
            className={stat.onClick ? "cursor-pointer transition-shadow hover:shadow-md" : ""}
            onClick={stat.onClick}
          >
            <CardContent className="flex items-center gap-3 p-4">
              <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${stat.color}`}>
                <stat.icon className="h-5 w-5" />
              </div>
              <div>
                <div className="text-xl font-bold">{stat.value}</div>
                <div className="text-xs text-muted-foreground">{stat.label}</div>
              </div>
            </CardContent>
          </Card>
        ))}
        {stats.low_stock_products > 0 && (
          <Card className="cursor-pointer border-amber-200 transition-shadow hover:shadow-md" onClick={() => navigate("/products")}>
            <CardContent className="flex items-center gap-3 p-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                <AlertTriangle className="h-5 w-5" />
              </div>
              <div>
                <div className="text-xl font-bold">{stats.low_stock_products}</div>
                <div className="text-xs text-muted-foreground">Scorta bassa</div>
              </div>
            </CardContent>
          </Card>
        )}
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between pb-3">
          <CardTitle className="text-lg">Programma di oggi</CardTitle>
          <Button variant="outline" size="sm" onClick={() => navigate("/calendar")}>Apri agenda</Button>
        </CardHeader>
        <CardContent>
          {todayAppointments.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">Nessun appuntamento per oggi</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-20">Ora</TableHead>
                  <TableHead>Cliente</TableHead>
                  <TableHead className="w-36">{t.staffs}</TableHead>
                  <TableHead className="w-24 text-right">Prezzo</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {todayAppointments.map((apt) => (
                  <TableRow key={apt.id} className="cursor-pointer" onClick={() => navigate(`/appointments/${apt.id}`)}>
                    <TableCell className="font-medium">{apt.start_time}</TableCell>
                    <TableCell>{apt.client_name}</TableCell>
                    <TableCell>
                      <span className="flex items-center gap-2">
                        {apt.staff_name && (
                          <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ backgroundColor: apt.staff_color || "#7c3aed" }} />
                        )}
                        {apt.staff_name || "—"}
                      </span>
                    </TableCell>
                    <TableCell className="text-right">{formatEuro(apt.total_price)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
