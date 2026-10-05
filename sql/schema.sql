-- BOQ-CAD Tanzania Construction Estimator Database Schema
-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS boq_cad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE boq_cad;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    region VARCHAR(50) DEFAULT 'Dar es Salaam',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Projects table
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    location VARCHAR(200),
    project_type ENUM('residential','commercial','industrial','other') DEFAULT 'residential',
    status ENUM('draft','active','completed','archived') DEFAULT 'draft',
    canvas_data LONGTEXT, -- JSON from Fabric.js
    total_area_m2 DECIMAL(12,2) DEFAULT 0,
    total_cost_tzs DECIMAL(18,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Rooms / spaces drawn on canvas
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    length_m DECIMAL(10,2) NOT NULL,
    width_m DECIMAL(10,2) NOT NULL,
    height_m DECIMAL(10,2) DEFAULT 3.00,
    area_m2 DECIMAL(12,2) GENERATED ALWAYS AS (length_m * width_m) STORED,
    wall_area_m2 DECIMAL(12,2) GENERATED ALWAYS AS ((length_m + width_m) * 2 * height_m) STORED,
    room_type VARCHAR(50) DEFAULT 'general',
    fabric_object_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Material prices (Tanzania market - Oct 2026)
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL, -- substructure, superstructure, roofing, finishing, openings, electrical, plumbing, labour
    name VARCHAR(150) NOT NULL,
    unit VARCHAR(30) NOT NULL, -- bag, m3, piece, m2, kg, roll, bar, set, etc.
    price_min DECIMAL(12,2) NOT NULL,
    price_max DECIMAL(12,2) NOT NULL,
    price_avg DECIMAL(12,2) NOT NULL, -- used for calculations
    region VARCHAR(50) DEFAULT 'National',
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Formula rates (how much material per unit area/length)
CREATE TABLE formula_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(200) NOT NULL,
    category VARCHAR(50) NOT NULL,
    unit_basis VARCHAR(50) NOT NULL, -- per_m2_wall, per_m2_floor, per_m_length, per_room, fixed
    material_id INT,
    quantity_per_unit DECIMAL(12,4) NOT NULL, -- e.g. 12.5 blocks per m2 wall
    waste_percent DECIMAL(5,2) DEFAULT 5.00,
    labour_rate_per_unit DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Project BOQ items (calculated quantities)
CREATE TABLE boq_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    room_id INT NULL,
    category VARCHAR(50) NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(14,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Project cost summary
CREATE TABLE cost_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    materials_subtotal DECIMAL(18,2) DEFAULT 0,
    labour_subtotal DECIMAL(18,2) DEFAULT 0,
    transport_percent DECIMAL(5,2) DEFAULT 10.00,
    transport_amount DECIMAL(18,2) DEFAULT 0,
    waste_percent DECIMAL(5,2) DEFAULT 5.00,
    waste_amount DECIMAL(18,2) DEFAULT 0,
    contingency_percent DECIMAL(5,2) DEFAULT 10.00,
    contingency_amount DECIMAL(18,2) DEFAULT 0,
    grand_total DECIMAL(18,2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert default materials (Tanzania prices Oct 2026)
INSERT INTO materials (category, name, unit, price_min, price_max, price_avg, notes) VALUES
-- Substructure
('substructure', 'Cement (50kg bag)', 'bag', 18000, 21500, 19500, 'Twiga / Simba / Dangote'),
('substructure', 'Sand', 'm3', 50000, 55000, 52500, 'River / pit sand'),
('substructure', 'Aggregate / Kokoto', 'm3', 95000, 120000, 107500, 'Gravel'),
('substructure', 'Rebar Y12 (12m)', 'bar', 25300, 25300, 25300, '12mm reinforcement'),
('substructure', 'Rebar Y16 (12m)', 'bar', 44800, 44800, 44800, '16mm reinforcement'),
('substructure', 'Binding wire', 'kg', 5500, 5500, 5500, '15kg roll ~45-48k'),
('substructure', 'Hardcore stones', 'm3', 150000, 180000, 165000, 'Foundation hardcore'),
('substructure', 'DPM Plastic', 'roll', 80000, 80000, 80000, 'Damp proof membrane'),
('substructure', 'Anti-termite chemical', 'liter', 15000, 25000, 20000, 'Soil treatment'),

-- Superstructure
('superstructure', 'Concrete Blocks 6"', 'piece', 1100, 1400, 1250, 'Hydraulically pressed'),
('superstructure', 'Cement (50kg bag)', 'bag', 18000, 21500, 19500, 'Same as substructure'),
('superstructure', 'Sand', 'm3', 50000, 55000, 52500, 'Same as substructure'),
('superstructure', 'DPC Roll', 'roll', 45000, 60000, 52500, 'Damp proof course'),

-- Roofing
('roofing', 'Mabati G28 Aluzinc', 'piece', 22000, 35000, 28500, 'Kiboko / Simba Dumu'),
('roofing', 'Clay/Concrete Roof Tiles', 'piece', 3500, 5000, 4250, 'Per tile'),
('roofing', 'Timber 2x2 (12ft)', 'piece', 3000, 3500, 3250, 'Rafters / purlins'),
('roofing', 'Timber 2x3 (12ft)', 'piece', 4500, 5500, 5000, 'Rafters'),
('roofing', 'Timber 2x6 (12ft)', 'piece', 8500, 10500, 9500, 'Main beams'),
('roofing', 'Roofing Nails', 'kg', 3500, 4500, 4000, 'Misumali'),
('roofing', 'Fascia Board', 'piece', 7000, 9000, 8000, 'Edge board'),
('roofing', 'Gutter 3m', 'piece', 23000, 28000, 25500, 'PVC / metal'),
('roofing', 'Ridge Cap', 'piece', 6000, 8500, 7250, 'Roof ridge'),

-- Finishing
('finishing', 'Gypsum Board', 'board', 16000, 18000, 17000, 'Ceiling'),
('finishing', 'Gypsum Powder', 'bag', 29000, 29000, 29000, 'For jointing'),
('finishing', 'Emulsion Paint 20L', 'bucket', 55000, 85000, 70000, 'Interior standard'),
('finishing', 'Weather Guard 20L', 'bucket', 130000, 180000, 155000, 'Exterior'),
('finishing', 'Floor Tiles 60x60', 'box', 22000, 35000, 28500, 'Porcelain ~1.44m2'),
('finishing', 'Tile Adhesive 25kg', 'bag', 14000, 18000, 16000, 'Cement based'),
('finishing', 'Grout', 'pack', 3000, 3000, 3000, 'Tile joints'),
('finishing', 'Skirting', 'm', 3000, 5000, 4000, 'Linear meter'),

-- Openings
('openings', 'Wooden Door', 'piece', 180000, 450000, 300000, 'Mkongo / Mninga'),
('openings', 'Steel Door', 'piece', 250000, 500000, 375000, 'Security door'),
('openings', 'Aluminium Window', 'm2', 120000, 220000, 170000, 'Incl. glass & frame'),
('openings', 'Window Grill', 'piece', 45000, 85000, 65000, 'Per window'),
('openings', 'Door Lock Set', 'set', 15000, 65000, 35000, 'Handle + lock'),

-- Electrical
('electrical', 'Cable 1.5mm 100m', 'roll', 45000, 60000, 52500, 'Lighting'),
('electrical', 'Cable 2.5mm 100m', 'roll', 75000, 95000, 85000, 'Sockets'),
('electrical', 'Switch / Socket', 'piece', 3500, 12000, 7000, 'Standard'),
('electrical', 'DB Board', 'piece', 45000, 120000, 80000, 'Distribution board'),
('electrical', 'LED Bulb', 'piece', 3000, 8500, 5000, 'Energy saving'),

-- Plumbing
('plumbing', 'PPR Pipe 4m', 'bar', 8500, 14000, 11250, 'Hot/cold water'),
('plumbing', 'PVC Pipe 4m', 'bar', 12000, 28000, 20000, 'Waste 1.5-4"'),
('plumbing', 'Tap', 'piece', 6000, 25000, 15000, 'Basin / sink'),
('plumbing', 'WC Toilet Set', 'set', 110000, 350000, 220000, 'Sitting / squatting'),
('plumbing', 'Sink', 'piece', 45000, 180000, 100000, 'Kitchen / bathroom'),
('plumbing', 'Septic Tank Materials', 'set', 1200000, 2500000, 1850000, 'Standard pit'),

-- Labour
('labour', 'Structure Labour', 'm2', 35000, 55000, 45000, 'Shell + roofing'),
('labour', 'Plastering Labour', 'm2', 8000, 15000, 11500, 'Wall plaster'),
('labour', 'Tiling Labour', 'm2', 7000, 12000, 9500, 'Floor / wall tiles');

-- Formula rates (standard Tanzania building practices)
INSERT INTO formula_rates (item_code, description, category, unit_basis, material_id, quantity_per_unit, waste_percent, labour_rate_per_unit, notes) VALUES
('WALL_BLOCKS', 'Concrete blocks for walls', 'superstructure', 'per_m2_wall', 10, 12.50, 5.00, 0, '12.5 blocks per m² of wall'),
('WALL_CEMENT', 'Cement for blockwork mortar', 'superstructure', 'per_m2_wall', 11, 0.40, 5.00, 0, '0.4 bag per m² wall (1:4 mix)'),
('WALL_SAND', 'Sand for blockwork mortar', 'superstructure', 'per_m2_wall', 12, 0.03, 5.00, 0, '0.03 m³ per m² wall'),
('PLASTER_CEMENT', 'Cement for plaster (both sides)', 'finishing', 'per_m2_wall', 2, 0.25, 5.00, 11500, '0.25 bag/m² (2 sides)'),
('PLASTER_SAND', 'Sand for plaster', 'finishing', 'per_m2_wall', 3, 0.02, 5.00, 0, '0.02 m³ per m²'),
('FLOOR_CEMENT', 'Cement for floor screed', 'finishing', 'per_m2_floor', 2, 0.20, 5.00, 0, 'Screed 1:3'),
('FLOOR_SAND', 'Sand for floor screed', 'finishing', 'per_m2_floor', 3, 0.015, 5.00, 0, ''),
('FOUND_CEMENT', 'Cement for foundation concrete', 'substructure', 'per_m2_floor', 1, 1.50, 5.00, 0, 'Approx for strip foundation'),
('FOUND_SAND', 'Sand for foundation', 'substructure', 'per_m2_floor', 2, 0.15, 5.00, 0, ''),
('FOUND_AGG', 'Aggregate for foundation', 'substructure', 'per_m2_floor', 3, 0.25, 5.00, 0, ''),
('ROOF_MABATI', 'Roofing sheets', 'roofing', 'per_m2_floor', 14, 1.15, 8.00, 0, '1.15 sheets per m² floor (overlap)'),
('ROOF_TIMBER', 'Timber for roof structure', 'roofing', 'per_m2_floor', 16, 0.8, 10.00, 0, 'Approx pieces'),
('PAINT_INT', 'Interior emulsion paint', 'finishing', 'per_m2_wall', 20, 0.08, 5.00, 0, '0.08 bucket per m² (2 coats)'),
('PAINT_EXT', 'Exterior weather guard', 'finishing', 'per_m2_wall', 21, 0.06, 5.00, 0, 'External walls'),
('TILES_FLOOR', 'Floor tiles', 'finishing', 'per_m2_floor', 22, 0.70, 8.00, 9500, '0.7 box per m² (1.44m²/box)'),
('TILE_ADH', 'Tile adhesive', 'finishing', 'per_m2_floor', 23, 0.15, 5.00, 0, ''),
('DOOR_WOOD', 'Wooden doors', 'openings', 'per_room', 26, 1.00, 0, 0, '1 door per room average'),
('WINDOW_ALU', 'Aluminium windows', 'openings', 'per_room', 28, 1.50, 0, 0, '1.5 m² window per room avg'),
('LABOUR_STRUCT', 'Structure labour', 'labour', 'per_m2_floor', 37, 1.00, 0, 45000, 'Shell construction'),
('LABOUR_PLAST', 'Plastering labour', 'labour', 'per_m2_wall', 38, 1.00, 0, 11500, '');
