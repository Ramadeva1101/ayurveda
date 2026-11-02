-- LOCAL TESTING ONLY. Fills services and hours so the Book page can be tried.
-- Every value is copied from an existing source; nothing is invented:
--   * Experience + programs (names, durations, prices): src/pages/packages.astro
--     (live site). "1 hr – 1.5 hr" is stored as 90 minutes, the upper bound.
--   * Treatment names: src/content/treatments/*.md. They have no duration or
--     price on the site, so they stay NOT bookable online until the clinic
--     fills those in Admin > Services.
--   * Opening days and start times (Mon–Sat; 09:00, 10:30, 13:00, 14:30, 16:00):
--     the client's booking demo. To be confirmed by the clinic.
--   * Alert email: booking@ayurvedicwellnesshouse.com, from the site footer.
-- Prices on the site are "++" (tax & service added); stored here without it.

INSERT INTO services (type, slug, name, duration_minutes, duration_days, price_idr, bookable_online, active, sort_order) VALUES
  ('experience', 'ayurvedic-javanese-healing-experience', 'Ayurvedic Javanese Healing Experience', 90, NULL, 1500000, 1, 1, 1),
  ('program', '3-day-ayurvedic-javanese-energy-healing-experience', '3-Day Ayurvedic & Javanese Energy Healing Experience', NULL, 3, 5900000, 1, 1, 1),
  ('program', '7-day-ayurvedic-pancha-karma-javanese-energy-healing-experience', '7-Day Ayurvedic Pancha Karma & Javanese Energy Healing Experience', NULL, 7, 15000000, 1, 1, 2),
  ('program', '14-day-ayurvedic-deep-pancha-karma-javanese-energy-healing-experience', '14-Day Ayurvedic Deep Pancha Karma & Javanese Energy Healing Experience', NULL, 14, 29000000, 1, 1, 3),
  ('program', '21-day-complete-ayurvedic-deep-pancha-karma-javanese-energy-healing-experience', '21-Day Complete Ayurvedic Deep Pancha Karma & Javanese Energy Healing Experience', NULL, 21, 43000000, 1, 1, 4),
  ('treatment', 'ayurvedic-one-on-one', 'Ayurvedic One-on-One with Dr. Komang Sudarmi', NULL, NULL, NULL, 0, 1, 1),
  ('treatment', 'abhyanga', 'Abhyanga', NULL, NULL, NULL, 0, 1, 10),
  ('treatment', 'navarakizhi', 'Navarakizhi', NULL, NULL, NULL, 0, 1, 10),
  ('treatment', 'patra-pinda-sveda', 'Patra Pinda Sveda', NULL, NULL, NULL, 0, 1, 10),
  ('treatment', 'pizhichil', 'Pizhichil', NULL, NULL, NULL, 0, 1, 10),
  ('treatment', 'reflexology', 'Reflexology', NULL, NULL, NULL, 0, 1, 10),
  ('treatment', 'svedana', 'Svedana', NULL, NULL, NULL, 0, 1, 10),
  ('treatment', 'udvartana', 'Udvartana', NULL, NULL, NULL, 0, 1, 10),
  ('treatment', 'shirodhara', 'Shirodhara', NULL, NULL, NULL, 0, 1, 20),
  ('treatment', 'takradhara', 'Takradhara', NULL, NULL, NULL, 0, 1, 20),
  ('treatment', 'cakra-basti', 'Cakra Basti', NULL, NULL, NULL, 0, 1, 30),
  ('treatment', 'greeva-basti', 'Greeva Basti', NULL, NULL, NULL, 0, 1, 30),
  ('treatment', 'hrdaya-basti', 'Hrdaya Basti', NULL, NULL, NULL, 0, 1, 30),
  ('treatment', 'janu-basti', 'Janu Basti', NULL, NULL, NULL, 0, 1, 30),
  ('treatment', 'kati-basti', 'Kati Basti', NULL, NULL, NULL, 0, 1, 30),
  ('treatment', 'karna-purana', 'Karna Purana', NULL, NULL, NULL, 0, 0, 40),
  ('treatment', 'nasya', 'Nasya', NULL, NULL, NULL, 0, 0, 40),
  ('treatment', 'netra-tarpana', 'Netra Tarpana', NULL, NULL, NULL, 0, 0, 40);

UPDATE opening_hours SET is_open = 1, slot_times = '09:00,10:30,13:00,14:30,16:00' WHERE weekday BETWEEN 1 AND 6;
UPDATE settings SET value = 'booking@ayurvedicwellnesshouse.com' WHERE key_name = 'notify_email';
UPDATE settings SET value = '1' WHERE key_name = 'booking_enabled';

-- Photos already used on the website (public/images).
UPDATE services SET image = '/images/javanese-energy-healing.webp' WHERE slug = 'ayurvedic-javanese-healing-experience';
UPDATE services SET image = '/images/rice field surrounding_11zon.webp' WHERE slug = '3-day-ayurvedic-javanese-energy-healing-experience';
UPDATE services SET image = '/images/meditation octagon bale_7_11zon.jpg' WHERE slug = '7-day-ayurvedic-pancha-karma-javanese-energy-healing-experience';
UPDATE services SET image = '/images/spectacular view of batukaru_11zon.webp' WHERE slug = '14-day-ayurvedic-deep-pancha-karma-javanese-energy-healing-experience';
UPDATE services SET image = '/images/bali-silent-retreat-3-sisters-mountains.webp' WHERE slug = '21-day-complete-ayurvedic-deep-pancha-karma-javanese-energy-healing-experience';
UPDATE services SET image = '/images/one-on-one.webp' WHERE slug = 'ayurvedic-one-on-one';
UPDATE services SET image = '/images/abhyanga.webp' WHERE slug = 'abhyanga';
UPDATE services SET image = '/images/navarakizhi.webp' WHERE slug = 'navarakizhi';
UPDATE services SET image = '/images/patrapinda.webp' WHERE slug = 'patra-pinda-sveda';
UPDATE services SET image = '/images/pizhichil.webp' WHERE slug = 'pizhichil';
UPDATE services SET image = '/images/reflexiology .webp' WHERE slug = 'reflexology';
UPDATE services SET image = '/images/svedana.webp' WHERE slug = 'svedana';
UPDATE services SET image = '/images/udvartana .webp' WHERE slug = 'udvartana';
UPDATE services SET image = '/images/shirodhara-clinic.webp' WHERE slug = 'shirodhara';
UPDATE services SET image = '/images/takradhara.webp' WHERE slug = 'takradhara';
UPDATE services SET image = '/images/cakra-basti-clinic.webp' WHERE slug = 'cakra-basti';
UPDATE services SET image = '/images/greeva basti .webp' WHERE slug = 'greeva-basti';
UPDATE services SET image = '/images/hrdaya-basti-clinic.webp' WHERE slug = 'hrdaya-basti';
UPDATE services SET image = '/images/janu basti .webp' WHERE slug = 'janu-basti';
UPDATE services SET image = '/images/kati basti.webp' WHERE slug = 'kati-basti';
UPDATE services SET image = '/images/karnapurna.webp' WHERE slug = 'karna-purana';
UPDATE services SET image = '/images/nasya.webp' WHERE slug = 'nasya';
UPDATE services SET image = '/images/netratarpana.webp' WHERE slug = 'netra-tarpana';
