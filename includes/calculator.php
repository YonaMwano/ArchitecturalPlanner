<?php
/**
 * Quantity Takeoff & Cost Estimation Engine
 * Based on Tanzania standard building rates
 */
require_once __DIR__ . '/config.php';

class BOQCalculator {
    private PDO $db;
    private int $projectId;
    private array $rooms = [];
    private array $boqItems = [];
    private float $materialsTotal = 0;
    private float $labourTotal = 0;

    public function __construct(int $projectId) {
        $this->db = getDB();
        $this->projectId = $projectId;
        if ($projectId > 0) {
            $this->loadRooms();
        }
    }

    private function loadRooms(): void {
        $stmt = $this->db->prepare('SELECT * FROM rooms WHERE project_id = ?');
        $stmt->execute([$this->projectId]);
        $this->rooms = $stmt->fetchAll();
    }

    /**
     * Calculate full BOQ from rooms
     */
    public function calculate(): array {
        // Clear previous BOQ
        $this->db->prepare('DELETE FROM boq_items WHERE project_id = ?')->execute([$this->projectId]);
        $this->boqItems = [];
        $this->materialsTotal = 0;
        $this->labourTotal = 0;

        if (empty($this->rooms)) {
            return $this->emptyResult();
        }

        $totalFloorArea = 0;
        $totalWallArea = 0;

        foreach ($this->rooms as $room) {
            $floor = (float)$room['area_m2'];
            $wall  = (float)$room['wall_area_m2'];
            $totalFloorArea += $floor;
            $totalWallArea  += $wall;

            // Per-room items
            $this->addItem('openings', 'Wooden Door', 'piece', 1, $this->getPrice('Wooden Door'), $room['id'], '1 door per room');
            $this->addItem('openings', 'Aluminium Window', 'm2', 1.5, $this->getPrice('Aluminium Window'), $room['id'], 'Avg 1.5m² window');
            $this->addItem('openings', 'Window Grill', 'piece', 1, $this->getPrice('Window Grill'), $room['id']);
            $this->addItem('openings', 'Door Lock Set', 'set', 1, $this->getPrice('Door Lock Set'), $room['id']);
        }

        // Foundation (based on total floor)
        $this->addItem('substructure', 'Cement (50kg bag)', 'bag', $totalFloorArea * 1.5, $this->getPrice('Cement (50kg bag)'), null, 'Foundation concrete');
        $this->addItem('substructure', 'Sand', 'm3', $totalFloorArea * 0.15, $this->getPrice('Sand'), null);
        $this->addItem('substructure', 'Aggregate / Kokoto', 'm3', $totalFloorArea * 0.25, $this->getPrice('Aggregate / Kokoto'), null);
        $this->addItem('substructure', 'Rebar Y12 (12m)', 'bar', max(4, ceil($totalFloorArea / 5)), $this->getPrice('Rebar Y12 (12m)'), null);
        $this->addItem('substructure', 'Hardcore stones', 'm3', $totalFloorArea * 0.3, $this->getPrice('Hardcore stones'), null);
        $this->addItem('substructure', 'DPM Plastic', 'roll', max(1, ceil($totalFloorArea / 50)), $this->getPrice('DPM Plastic'), null);
        $this->addItem('substructure', 'Anti-termite chemical', 'liter', max(5, ceil($totalFloorArea / 10)), $this->getPrice('Anti-termite chemical'), null);

        // Walls
        $blocks = $totalWallArea * 12.5;
        $this->addItem('superstructure', 'Concrete Blocks 6"', 'piece', $blocks, $this->getPrice('Concrete Blocks 6"'), null, '12.5 blocks/m² wall');
        $this->addItem('superstructure', 'Cement (50kg bag)', 'bag', $totalWallArea * 0.40, $this->getPrice('Cement (50kg bag)'), null, 'Mortar 1:4');
        $this->addItem('superstructure', 'Sand', 'm3', $totalWallArea * 0.03, $this->getPrice('Sand'), null);
        $this->addItem('superstructure', 'DPC Roll', 'roll', max(1, ceil($totalFloorArea / 30)), $this->getPrice('DPC Roll'), null);

        // Roof
        $this->addItem('roofing', 'Mabati G28 Aluzinc', 'piece', $totalFloorArea * 1.15, $this->getPrice('Mabati G28 Aluzinc'), null, 'With overlap');
        $this->addItem('roofing', 'Timber 2x3 (12ft)', 'piece', $totalFloorArea * 0.6, $this->getPrice('Timber 2x3 (12ft)'), null);
        $this->addItem('roofing', 'Timber 2x6 (12ft)', 'piece', $totalFloorArea * 0.2, $this->getPrice('Timber 2x6 (12ft)'), null);
        $this->addItem('roofing', 'Roofing Nails', 'kg', max(5, $totalFloorArea * 0.15), $this->getPrice('Roofing Nails'), null);
        $this->addItem('roofing', 'Fascia Board', 'piece', max(4, ceil(sqrt($totalFloorArea) * 4 / 3.6)), $this->getPrice('Fascia Board'), null);
        $this->addItem('roofing', 'Gutter 3m', 'piece', max(2, ceil(sqrt($totalFloorArea) * 2 / 3)), $this->getPrice('Gutter 3m'), null);
        $this->addItem('roofing', 'Ridge Cap', 'piece', max(2, ceil(sqrt($totalFloorArea) / 1.5)), $this->getPrice('Ridge Cap'), null);

        // Finishing
        $this->addItem('finishing', 'Cement (50kg bag)', 'bag', $totalWallArea * 0.25, $this->getPrice('Cement (50kg bag)'), null, 'Plaster both sides');
        $this->addItem('finishing', 'Sand', 'm3', $totalWallArea * 0.02, $this->getPrice('Sand'), null, 'Plaster');
        $this->addItem('finishing', 'Emulsion Paint 20L', 'bucket', $totalWallArea * 0.08, $this->getPrice('Emulsion Paint 20L'), null, 'Interior 2 coats');
        $this->addItem('finishing', 'Weather Guard 20L', 'bucket', $totalWallArea * 0.04, $this->getPrice('Weather Guard 20L'), null, 'External');
        $this->addItem('finishing', 'Floor Tiles 60x60', 'box', $totalFloorArea * 0.70, $this->getPrice('Floor Tiles 60x60'), null);
        $this->addItem('finishing', 'Tile Adhesive 25kg', 'bag', $totalFloorArea * 0.15, $this->getPrice('Tile Adhesive 25kg'), null);
        $this->addItem('finishing', 'Grout', 'pack', max(2, ceil($totalFloorArea / 10)), $this->getPrice('Grout'), null);
        $this->addItem('finishing', 'Gypsum Board', 'board', $totalFloorArea * 0.35, $this->getPrice('Gypsum Board'), null, 'Ceiling');
        $this->addItem('finishing', 'Skirting', 'm', ($totalFloorArea > 0 ? 2 * (sqrt($totalFloorArea) * 4) : 0), $this->getPrice('Skirting'), null);

        // Electrical (rough)
        $this->addItem('electrical', 'Cable 1.5mm 100m', 'roll', max(1, ceil($totalFloorArea / 40)), $this->getPrice('Cable 1.5mm 100m'), null);
        $this->addItem('electrical', 'Cable 2.5mm 100m', 'roll', max(1, ceil($totalFloorArea / 50)), $this->getPrice('Cable 2.5mm 100m'), null);
        $this->addItem('electrical', 'Switch / Socket', 'piece', max(6, count($this->rooms) * 4), $this->getPrice('Switch / Socket'), null);
        $this->addItem('electrical', 'DB Board', 'piece', 1, $this->getPrice('DB Board'), null);
        $this->addItem('electrical', 'LED Bulb', 'piece', max(4, count($this->rooms) * 2), $this->getPrice('LED Bulb'), null);

        // Plumbing
        $this->addItem('plumbing', 'PPR Pipe 4m', 'bar', max(4, count($this->rooms) * 2), $this->getPrice('PPR Pipe 4m'), null);
        $this->addItem('plumbing', 'PVC Pipe 4m', 'bar', max(3, count($this->rooms)), $this->getPrice('PVC Pipe 4m'), null);
        $this->addItem('plumbing', 'Tap', 'piece', max(2, count($this->rooms)), $this->getPrice('Tap'), null);
        $this->addItem('plumbing', 'WC Toilet Set', 'set', max(1, ceil(count($this->rooms) / 3)), $this->getPrice('WC Toilet Set'), null);
        $this->addItem('plumbing', 'Sink', 'piece', max(1, ceil(count($this->rooms) / 2)), $this->getPrice('Sink'), null);

        // Labour
        $this->addItem('labour', 'Structure Labour', 'm2', $totalFloorArea, $this->getPrice('Structure Labour'), null, 'Shell + roofing');
        $this->addItem('labour', 'Plastering Labour', 'm2', $totalWallArea, $this->getPrice('Plastering Labour'), null);
        $this->addItem('labour', 'Tiling Labour', 'm2', $totalFloorArea, $this->getPrice('Tiling Labour'), null);

        // Save items & summary
        $this->saveItems();
        $summary = $this->buildSummary($totalFloorArea, $totalWallArea);
        $this->saveSummary($summary);

        // Update project totals
        $this->db->prepare('UPDATE projects SET total_area_m2 = ?, total_cost_tzs = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$totalFloorArea, $summary['grand_total'], $this->projectId]);

        return $summary;
    }

    private function getPrice(string $name): float {
        static $cache = [];
        if (!isset($cache[$name])) {
            $stmt = $this->db->prepare('SELECT price_avg FROM materials WHERE name = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$name]);
            $row = $stmt->fetch();
            $cache[$name] = $row ? (float)$row['price_avg'] : 0;
        }
        return $cache[$name];
    }

    /**
     * @param string $cat
     * @param string $name
     * @param string $unit
     * @param float $qty
     * @param float $price
     * @param int|null $roomId
     * @param string $notes
     */
    private function addItem(string $cat, string $name, string $unit, float $qty, float $price, ?int $roomId = null, string $notes = ''): void {
        if ($qty <= 0) return;
        $qty = round($qty, 2);
        // Apply 5% waste for materials (not labour)
        $waste = ($cat !== 'labour') ? 1.05 : 1.0;
        $qtyWithWaste = round($qty * $waste, 2);
        $total = round($qtyWithWaste * $price, 2);

        $this->boqItems[] = [
            'category' => $cat,
            'item_name' => $name,
            'unit' => $unit,
            'quantity' => $qtyWithWaste,
            'unit_price' => $price,
            'total_price' => $total,
            'room_id' => $roomId,
            'notes' => $notes
        ];

        if ($cat === 'labour') {
            $this->labourTotal += $total;
        } else {
            $this->materialsTotal += $total;
        }
    }

    private function saveItems(): void {
        $stmt = $this->db->prepare('INSERT INTO boq_items (project_id, room_id, category, item_name, unit, quantity, unit_price, total_price, notes) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($this->boqItems as $item) {
            $stmt->execute([
                $this->projectId,
                $item['room_id'],
                $item['category'],
                $item['item_name'],
                $item['unit'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price'],
                $item['notes']
            ]);
        }
    }

    private function buildSummary(float $floorArea, float $wallArea): array {
        $transport = $this->materialsTotal * 0.10;
        $contingency = ($this->materialsTotal + $this->labourTotal + $transport) * 0.10;
        $grand = $this->materialsTotal + $this->labourTotal + $transport + $contingency;

        return [
            'success' => true,
            'floor_area_m2' => round($floorArea, 2),
            'wall_area_m2' => round($wallArea, 2),
            'rooms_count' => count($this->rooms),
            'materials_subtotal' => round($this->materialsTotal, 2),
            'labour_subtotal' => round($this->labourTotal, 2),
            'transport_percent' => 10,
            'transport_amount' => round($transport, 2),
            'waste_percent' => 5,
            'waste_note' => 'Already included in quantities',
            'contingency_percent' => 10,
            'contingency_amount' => round($contingency, 2),
            'grand_total' => round($grand, 2),
            'items' => $this->boqItems
        ];
    }

    private function saveSummary(array $s): void {
        $this->db->prepare('DELETE FROM cost_summaries WHERE project_id = ?')->execute([$this->projectId]);
        $stmt = $this->db->prepare('INSERT INTO cost_summaries 
            (project_id, materials_subtotal, labour_subtotal, transport_percent, transport_amount, waste_percent, waste_amount, contingency_percent, contingency_amount, grand_total)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $this->projectId,
            $s['materials_subtotal'],
            $s['labour_subtotal'],
            $s['transport_percent'],
            $s['transport_amount'],
            $s['waste_percent'],
            0,
            $s['contingency_percent'],
            $s['contingency_amount'],
            $s['grand_total']
        ]);
    }

    private function emptyResult(): array {
        return [
            'success' => true,
            'floor_area_m2' => 0,
            'wall_area_m2' => 0,
            'rooms_count' => 0,
            'materials_subtotal' => 0,
            'labour_subtotal' => 0,
            'transport_amount' => 0,
            'contingency_amount' => 0,
            'grand_total' => 0,
            'items' => [],
            'message' => 'No rooms defined. Add rooms or draw on canvas first.'
        ];
    }

    /**
     * Manual mode: calculate from L x W x H without rooms table
     */
    public static function calculateManual(float $length, float $width, float $height = 3.0, int $roomsCount = 1): array {
        $calc = new self(0); // dummy project id
        $area = $length * $width;
        $wall = ($length + $width) * 2 * $height;
        $calc->rooms = [['area_m2' => $area, 'wall_area_m2' => $wall, 'id' => null]];
        return $calc->calculateManualInternal($area, $wall, $roomsCount);
    }

    private function calculateManualInternal(float $floor, float $wall, int $rooms): array {
        $this->boqItems = [];
        $this->materialsTotal = 0;
        $this->labourTotal = 0;

        // All calls now pass null for roomId (6th argument) — fixed ArgumentCountError
        $this->addItem('substructure', 'Cement (50kg bag)', 'bag', $floor * 1.5, $this->getPrice('Cement (50kg bag)'), null, 'Foundation concrete');
        $this->addItem('substructure', 'Sand', 'm3', $floor * 0.15, $this->getPrice('Sand'), null);
        $this->addItem('substructure', 'Aggregate / Kokoto', 'm3', $floor * 0.25, $this->getPrice('Aggregate / Kokoto'), null);
        $this->addItem('substructure', 'Rebar Y12 (12m)', 'bar', max(4, ceil($floor / 5)), $this->getPrice('Rebar Y12 (12m)'), null);
        $this->addItem('substructure', 'Hardcore stones', 'm3', $floor * 0.3, $this->getPrice('Hardcore stones'), null);
        $this->addItem('superstructure', 'Concrete Blocks 6"', 'piece', $wall * 12.5, $this->getPrice('Concrete Blocks 6"'), null, '12.5 blocks/m² wall');
        $this->addItem('superstructure', 'Cement (50kg bag)', 'bag', $wall * 0.40, $this->getPrice('Cement (50kg bag)'), null, 'Mortar 1:4');
        $this->addItem('superstructure', 'Sand', 'm3', $wall * 0.03, $this->getPrice('Sand'), null);
        $this->addItem('roofing', 'Mabati G28 Aluzinc', 'piece', $floor * 1.15, $this->getPrice('Mabati G28 Aluzinc'), null, 'With overlap');
        $this->addItem('roofing', 'Timber 2x3 (12ft)', 'piece', $floor * 0.6, $this->getPrice('Timber 2x3 (12ft)'), null);
        $this->addItem('finishing', 'Floor Tiles 60x60', 'box', $floor * 0.70, $this->getPrice('Floor Tiles 60x60'), null);
        $this->addItem('finishing', 'Emulsion Paint 20L', 'bucket', $wall * 0.08, $this->getPrice('Emulsion Paint 20L'), null, 'Interior 2 coats');
        $this->addItem('finishing', 'Cement (50kg bag)', 'bag', $wall * 0.25, $this->getPrice('Cement (50kg bag)'), null, 'Plaster');
        $this->addItem('openings', 'Wooden Door', 'piece', $rooms, $this->getPrice('Wooden Door'), null);
        $this->addItem('openings', 'Aluminium Window', 'm2', $rooms * 1.5, $this->getPrice('Aluminium Window'), null);
        $this->addItem('labour', 'Structure Labour', 'm2', $floor, $this->getPrice('Structure Labour'), null, 'Shell + roofing');
        $this->addItem('labour', 'Plastering Labour', 'm2', $wall, $this->getPrice('Plastering Labour'), null);
        $this->addItem('labour', 'Tiling Labour', 'm2', $floor, $this->getPrice('Tiling Labour'), null);

        return $this->buildSummary($floor, $wall);
    }
}
