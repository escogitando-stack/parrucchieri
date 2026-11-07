-- Synthetic appointments for the disposable template preview.
INSERT INTO staff (id, name, email, title, color) VALUES
 (1, 'Giulia', 'giulia@example.test', 'Hair stylist', '#3b82f6'),
 (2, 'Marco', 'marco@example.test', 'Barbiere', '#10b981');
INSERT INTO services (id, name, description, duration, price, color, category) VALUES
 (1, 'Taglio donna', 'Shampoo, taglio e piega', 60, 55, '#3b82f6', 'Taglio'),
 (2, 'Piega', 'Shampoo e piega', 30, 30, '#10b981', 'Taglio'),
 (3, 'Colore', 'Ritocco radici e piega', 90, 90, '#8b5cf6', 'Colore');
INSERT INTO clients (id, name, email, notes) VALUES
 (1, 'Chiara Rossi', 'chiara@example.test', 'Preferisce gli appuntamenti al mattino.'),
 (2, 'Luca Bianchi', 'luca@example.test', 'Consulenza prima di cambiare colore.'),
 (3, 'Francesca Verdi', 'francesca@example.test', 'Prenota di solito al mattino.');
INSERT INTO appointments (id, identifier, client_id, staff_id, status, scheduled_date, start_time, end_time, total_price) VALUES
 (1, 'APT-1', 1, 1, 'completed', date('now'), '09:00', '10:00', 55),
 (2, 'APT-2', 2, 2, 'booked', date('now'), '15:00', '16:30', 90),
 (3, 'APT-3', 3, 1, 'booked', date('now', '+1 day'), '10:00', '10:30', 30);
INSERT INTO appointment_services (appointment_id, service_id, price, duration) VALUES
 (1, 1, 55, 60), (2, 3, 90, 90), (3, 2, 30, 30);
INSERT INTO appointment_notes (appointment_id, content) VALUES
 (2, 'Mostrare la cartella colori durante la consulenza.');
INSERT INTO _meta (key, value) VALUES ('appointment_counter', '3'), ('appointment_prefix', 'APT');
