-- Run once in phpMyAdmin ONLY if 002_services_from_website.sql was imported
-- before November 2026. It renames "Nusantara" to "Javanese Energy Healing"
-- and switches four services to real clinic photos. Safe to run twice.

UPDATE services SET slug = '3-day-ayurvedic-javanese-energy-healing-experience',
  name = '3-Day Ayurvedic & Javanese Energy Healing Experience'
  WHERE slug = '3-day-ayurvedic-nusantara-experience';
UPDATE services SET slug = '7-day-ayurvedic-pancha-karma-javanese-energy-healing-experience',
  name = '7-Day Ayurvedic Pancha Karma & Javanese Energy Healing Experience'
  WHERE slug = '7-day-ayurvedic-pancha-karma-nusantara-experience';
UPDATE services SET slug = '14-day-ayurvedic-deep-pancha-karma-javanese-energy-healing-experience',
  name = '14-Day Ayurvedic Deep Pancha Karma & Javanese Energy Healing Experience'
  WHERE slug = '14-day-ayurvedic-deep-pancha-karma-nusantara-experience';
UPDATE services SET slug = '21-day-complete-ayurvedic-deep-pancha-karma-javanese-energy-healing-experience',
  name = '21-Day Complete Ayurvedic Deep Pancha Karma & Javanese Energy Healing Experience'
  WHERE slug = '21-day-complete-ayurvedic-deep-pancha-karma-nusantara-experience';

UPDATE services SET image = '/images/javanese-energy-healing.webp' WHERE image = '/images/nusantara-experience.webp';
UPDATE services SET image = '/images/shirodhara-clinic.webp' WHERE slug = 'shirodhara' AND image = '/images/shirodhara.webp';
UPDATE services SET image = '/images/hrdaya-basti-clinic.webp' WHERE slug = 'hrdaya-basti' AND image = '/images/hrdaya basti .webp';
UPDATE services SET image = '/images/cakra-basti-clinic.webp' WHERE slug = 'cakra-basti' AND image = '/images/cakra basti.webp';
