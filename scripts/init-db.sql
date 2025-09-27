-- Initial database setup for Anjem
-- This script runs when PostgreSQL container first starts

-- Create extensions that might be useful
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "postgis";

-- Insert sample beacon data for campus
INSERT INTO beacons (id, name, description, lat, lng, radius_m, is_active, created_at, updated_at) VALUES
('campus-gate', 'Campus Main Gate', 'Main entrance to campus', -7.7956, 110.3695, 100, true, NOW(), NOW()),
('library-main', 'Central Library', 'Main library building', -7.7960, 110.3700, 150, true, NOW(), NOW()),
('student-center', 'Student Center', 'Student activity center', -7.7965, 110.3690, 120, true, NOW(), NOW()),
('engineering-faculty', 'Engineering Faculty', 'Faculty of Engineering building', -7.7970, 110.3705, 100, true, NOW(), NOW()),
('medical-faculty', 'Medical Faculty', 'Faculty of Medicine building', -7.7950, 110.3685, 100, true, NOW(), NOW()),
('dormitory-a', 'Dormitory Block A', 'Student dormitory block A', -7.7945, 110.3710, 80, true, NOW(), NOW()),
('dormitory-b', 'Dormitory Block B', 'Student dormitory block B', -7.7940, 110.3715, 80, true, NOW(), NOW()),
('sports-complex', 'Sports Complex', 'Campus sports and recreation center', -7.7975, 110.3720, 150, true, NOW(), NOW())
ON CONFLICT (id) DO NOTHING;