<?php
require_once 'includes/config.php';
requireLogin();
$user = currentUser();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$project = $stmt->fetch();
if (!$project) { header('Location: dashboard.php'); exit; }

$stmt = $db->prepare('SELECT * FROM rooms WHERE project_id = ? ORDER BY id');
$stmt->execute([$id]);
$rooms = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM cost_summaries WHERE project_id = ?');
$stmt->execute([$id]);
$summary = $stmt->fetch();

$pageTitle = $project['name'];
require 'includes/header.php';
?>
<style>
.view-toggle{display:inline-flex;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1}
.view-toggle button{padding:6px 16px;font-weight:600;font-size:13px;border:none;background:#f1f5f9;color:#64748b;cursor:pointer}
.view-toggle button.active{background:#10b981;color:#fff}
#view-3d{display:none;width:100%;height:560px;background:#c8d8e8;border-radius:0 0 12px 12px;position:relative;overflow:hidden}
#three-canvas{width:100%;height:100%;display:block}
.furn-item{width:44px;height:44px;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;background:#fff;font-size:18px;transition:all .12s}
.furn-item:hover,.furn-item.active{border-color:#1e40af;background:#dbeafe;transform:scale(1.08)}
.toolbar-btn{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;font-size:12px}
.toolbar-btn:hover{background:#eff6ff;border-color:#93c5fd}
.toolbar-btn.active{background:#1e40af;color:#fff;border-color:#1e40af}
.zoom-controls{position:absolute;bottom:14px;right:14px;display:flex;flex-direction:column;gap:4px;z-index:10}
.zoom-controls button{width:34px;height:34px;border-radius:8px;border:none;background:rgba(255,255,255,.92);box-shadow:0 2px 8px rgba(0,0,0,.15);cursor:pointer;font-size:14px}
.zoom-controls button:hover{background:#1e40af;color:#fff}
.cam-presets{position:absolute;top:12px;left:12px;display:flex;gap:4px;z-index:10;flex-wrap:wrap}
.cam-presets button{padding:4px 8px;border-radius:6px;border:none;background:rgba(255,255,255,.9);box-shadow:0 1px 4px rgba(0,0,0,.12);cursor:pointer;font-size:11px;font-weight:600}
.cam-presets button:hover{background:#1e40af;color:#fff}
.autosave-badge{font-size:11px;color:#94a3b8}.autosave-badge.saved{color:#10b981}
.canvas-wrapper{position:relative}
.tool-group{display:inline-flex;align-items:center;gap:2px;padding:0 4px;border-right:1px solid #e2e8f0;margin-right:2px}
.tool-group:last-child{border-right:none}
#toolbar-3d{display:none}
@media print{
  nav,footer,.no-print,#toolbar-2d,#toolbar-3d,.zoom-controls,.cam-presets,#btn-regen-3d{display:none!important}
  .xl\:col-span-2{width:100%!important;max-width:100%!important}
  #view-2d,#view-3d{break-inside:avoid}
  body{background:#fff}
}
</style>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3 no-print">
    <div>
        <a href="dashboard.php" class="text-sm text-slate-500 hover:text-primary-700"><i class="fas fa-arrow-left me-1"></i> Dashboard</a>
        <h1 class="text-xl font-bold text-slate-800 mt-1"><?= sanitize($project['name']) ?></h1>
        <p class="text-sm text-slate-500"><?= sanitize($project['location'] ?: '') ?> · <?= ucfirst($project['project_type']) ?>
            <span class="autosave-badge ms-2" id="autosave-status"><i class="fas fa-cloud"></i> Auto-save on</span>
        </p>
    </div>
    <div class="flex flex-wrap gap-2 items-center">
        <div class="view-toggle">
            <button type="button" id="btn-2d" class="active">2D</button>
            <button type="button" id="btn-3d">3D</button>
        </div>
        <button id="btn-print" class="btn btn-outline-secondary btn-sm" title="Print design"><i class="fas fa-print me-1"></i> Print</button>
        <button id="btn-calc" class="btn btn-success btn-sm"><i class="fas fa-calculator me-1"></i> Calculate BOQ</button>
        <a href="boq.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-invoice me-1"></i> BOQ</a>
        <a href="api/export-excel.php?id=<?= $id ?>" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel me-1"></i> Excel</a>
        <button id="btn-save-canvas" class="btn btn-primary btn-sm" style="background:#1e40af;"><i class="fas fa-save me-1"></i> Save</button>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <!-- 2D Toolbar -->
            <div class="px-2 py-1.5 border-b border-slate-100 flex flex-wrap items-center gap-0.5 bg-slate-50 no-print" id="toolbar-2d">
                <div class="tool-group">
                    <button class="toolbar-btn active" data-tool="select" title="Select (V)"><i class="fas fa-mouse-pointer"></i></button>
                    <button class="toolbar-btn" data-tool="wall" title="Wall (W)"><i class="fas fa-minus"></i></button>
                    <button class="toolbar-btn" data-tool="rect" title="Room (R)"><i class="fas fa-vector-square"></i></button>
                    <button class="toolbar-btn" data-tool="polyline" title="Polyline wall"><i class="fas fa-draw-polygon"></i></button>
                </div>
                <div class="tool-group">
                    <button class="toolbar-btn" data-tool="door" title="Door"><i class="fas fa-door-open"></i></button>
                    <button class="toolbar-btn" data-tool="window" title="Window"><i class="fas fa-window-maximize"></i></button>
                    <button class="toolbar-btn" data-tool="column" title="Column / Pillar"><i class="fas fa-cube"></i></button>
                    <button class="toolbar-btn" data-tool="stairs" title="Stairs"><i class="fas fa-stairs"></i></button>
                </div>
                <div class="tool-group">
                    <button class="toolbar-btn" data-tool="dim" title="Dimension"><i class="fas fa-ruler-horizontal"></i></button>
                    <button class="toolbar-btn" data-tool="text" title="Text"><i class="fas fa-font"></i></button>
                    <button class="toolbar-btn" data-tool="hatch" title="Floor hatch / fill"><i class="fas fa-fill-drip"></i></button>
                </div>
                <div class="tool-group">
                    <button class="toolbar-btn" id="btn-undo" title="Undo"><i class="fas fa-undo"></i></button>
                    <button class="toolbar-btn" id="btn-redo" title="Redo"><i class="fas fa-redo"></i></button>
                    <button class="toolbar-btn" id="btn-delete" title="Delete"><i class="fas fa-trash text-red-500"></i></button>
                    <button class="toolbar-btn" id="btn-clear" title="Clear"><i class="fas fa-eraser"></i></button>
                </div>
                <div class="tool-group">
                    <label class="text-xs text-slate-500">Scale</label>
                    <select id="scale-select" class="form-select form-select-sm" style="width:auto;font-size:11px;padding:1px 4px;">
                        <option value="0.05">0.05m</option>
                        <option value="0.1" selected>0.1m</option>
                        <option value="0.2">0.2m</option>
                        <option value="0.5">0.5m</option>
                    </select>
                    <label class="text-xs text-slate-500 ms-1">H</label>
                    <input type="number" id="wall-height" class="form-control form-control-sm" value="3.0" step="0.1" min="2" max="8" style="width:48px;font-size:11px;padding:1px 4px;">
                </div>
                <div class="tool-group">
                    <button class="toolbar-btn" id="btn-zoom-in-2d" title="Zoom+"><i class="fas fa-search-plus"></i></button>
                    <button class="toolbar-btn" id="btn-zoom-out-2d" title="Zoom-"><i class="fas fa-search-minus"></i></button>
                    <button class="toolbar-btn" id="btn-zoom-fit-2d" title="Fit"><i class="fas fa-expand"></i></button>
                </div>
            </div>

            <!-- 3D Toolbar (visible in 3D mode) -->
            <div class="px-2 py-1.5 border-b border-slate-100 flex flex-wrap items-center gap-2 bg-slate-50 no-print" id="toolbar-3d">
                <span class="text-xs font-semibold text-slate-600">3D:</span>
                <button class="btn btn-sm btn-outline-primary" id="btn-3d-add-room"><i class="fas fa-plus me-1"></i>Add Room</button>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" id="opt-show-roof">
                    <label class="form-check-label text-xs" for="opt-show-roof">Roof</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" id="opt-show-edges" checked>
                    <label class="form-check-label text-xs" for="opt-show-edges">Edges</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" id="opt-hide-labels" checked>
                    <label class="form-check-label text-xs" for="opt-hide-labels">Hide labels</label>
                </div>
                <label class="text-xs text-slate-500">H</label>
                <input type="number" id="wall-height-3d" class="form-control form-control-sm" value="3.0" step="0.1" min="2" max="8" style="width:50px;font-size:11px;">
                <span class="text-xs text-slate-500 ms-1">Wall</span>
                <input type="color" id="color-wall" value="#ffffff" title="Wall color" style="width:28px;height:28px;padding:0;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;">
                <span class="text-xs text-slate-500">Floor</span>
                <input type="color" id="color-floor" value="#f9a825" title="Floor color" style="width:28px;height:28px;padding:0;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;">
                <span class="text-xs text-slate-500">Roof</span>
                <input type="color" id="color-roof" value="#c62828" title="Roof color" style="width:28px;height:28px;padding:0;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;">
                <span class="text-xs text-slate-500">Column</span>
                <input type="color" id="color-column" value="#90a4ae" title="Column color" style="width:28px;height:28px;padding:0;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;">
                <span class="text-xs text-slate-500">Ground</span>
                <input type="color" id="color-ground" value="#4caf50" title="Ground color" style="width:28px;height:28px;padding:0;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;">
                <button class="btn btn-sm btn-success" id="btn-regen-3d-tb"><i class="fas fa-sync me-1"></i>Rebuild</button>
            </div>

            <div class="canvas-wrapper" id="view-2d">
                <canvas id="fabric-canvas" width="900" height="560"></canvas>
                <div class="zoom-controls no-print">
                    <button id="z-in-2d"><i class="fas fa-plus"></i></button>
                    <button id="z-out-2d"><i class="fas fa-minus"></i></button>
                    <button id="z-fit-2d"><i class="fas fa-compress-arrows-alt"></i></button>
                </div>
            </div>

            <div id="view-3d">
                <canvas id="three-canvas"></canvas>
                <div class="cam-presets no-print">
                    <button data-cam="iso">Iso</button>
                    <button data-cam="front">Front</button>
                    <button data-cam="top">Top</button>
                    <button data-cam="side">Side</button>
                </div>
                <div class="zoom-controls no-print">
                    <button id="z-in-3d"><i class="fas fa-plus"></i></button>
                    <button id="z-out-3d"><i class="fas fa-minus"></i></button>
                    <button id="z-fit-3d"><i class="fas fa-home"></i></button>
                </div>
                <div class="no-print" style="position:absolute;bottom:14px;left:14px;background:rgba(0,0,0,.5);color:#fff;padding:5px 10px;border-radius:6px;font-size:11px;z-index:10;">
                    Drag rotate · Scroll zoom · Right-drag pan
                </div>
            </div>

            <div class="px-3 py-1.5 bg-slate-50 border-t text-xs text-slate-500 flex justify-between no-print">
                <span id="tool-hint"><i class="fas fa-info-circle me-1"></i> Draw in 2D. Roof is optional in 3D (checkbox).</span>
                <span id="canvas-info">Ready</span>
            </div>
        </div>

        <!-- 3D Add Room panel -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 mt-3 p-3 no-print" id="panel-3d-room" style="display:none;">
            <h3 class="font-semibold text-slate-700 text-sm mb-2"><i class="fas fa-cube me-1 text-primary-600"></i>Add Room in 3D</h3>
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label text-xs mb-0">Name</label><input type="text" id="r3d-name" class="form-control form-control-sm" value="Room"></div>
                <div class="col-md-2"><label class="form-label text-xs mb-0">L (m)</label><input type="number" id="r3d-l" class="form-control form-control-sm" step="0.1" value="4" min="1"></div>
                <div class="col-md-2"><label class="form-label text-xs mb-0">W (m)</label><input type="number" id="r3d-w" class="form-control form-control-sm" step="0.1" value="3.5" min="1"></div>
                <div class="col-md-2"><label class="form-label text-xs mb-0">H (m)</label><input type="number" id="r3d-h" class="form-control form-control-sm" step="0.1" value="3" min="2"></div>
                <div class="col-md-3"><button id="btn-3d-room-go" class="btn btn-sm btn-primary w-100" style="background:#1e40af;"><i class="fas fa-plus me-1"></i>Place Room</button></div>
            </div>
            <p class="text-xs text-slate-400 mt-2 mb-0">Room is added to the plan, saved, and 3D rebuilds. Roof only appears if “Show roof” is checked.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 mt-3 p-3 no-print">
            <h3 class="font-semibold text-slate-700 text-sm mb-2"><i class="fas fa-couch me-1 text-primary-600"></i>Furniture & Symbols</h3>
            <div class="flex flex-wrap gap-1.5">
                <div class="furn-item" data-furn="sofa" title="Sofa">🛋️</div>
                <div class="furn-item" data-furn="bed" title="Bed">🛏️</div>
                <div class="furn-item" data-furn="table" title="Table">🪑</div>
                <div class="furn-item" data-furn="chair" title="Chair">💺</div>
                <div class="furn-item" data-furn="toilet" title="Toilet">🚽</div>
                <div class="furn-item" data-furn="sink" title="Sink">🚰</div>
                <div class="furn-item" data-furn="stove" title="Stove">🔥</div>
                <div class="furn-item" data-furn="car" title="Car">🚗</div>
                <div class="furn-item" data-furn="tree" title="Tree">🌳</div>
                <div class="furn-item" data-furn="plant" title="Plant">🪴</div>
                <div class="furn-item" data-furn="tv" title="TV">📺</div>
                <div class="furn-item" data-furn="bath" title="Bath">🛁</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 mt-3 p-3 no-print">
            <h3 class="font-semibold text-slate-700 mb-2 text-sm"><i class="fas fa-plus-square me-1 text-primary-600"></i>Add Room Manually (2D list)</h3>
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label text-xs mb-0">Name</label><input type="text" id="room-name" class="form-control form-control-sm" placeholder="Living Room"></div>
                <div class="col-md-2"><label class="form-label text-xs mb-0">L (m)</label><input type="number" id="room-l" class="form-control form-control-sm" step="0.1" min="0.5" placeholder="5"></div>
                <div class="col-md-2"><label class="form-label text-xs mb-0">W (m)</label><input type="number" id="room-w" class="form-control form-control-sm" step="0.1" min="0.5" placeholder="4"></div>
                <div class="col-md-2"><label class="form-label text-xs mb-0">H (m)</label><input type="number" id="room-h" class="form-control form-control-sm" step="0.1" value="3.0" min="2"></div>
                <div class="col-md-3"><button id="btn-add-room" class="btn btn-sm btn-primary w-100" style="background:#1e40af;"><i class="fas fa-plus me-1"></i> Add</button></div>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <h3 class="font-semibold text-slate-700 mb-2 text-sm"><i class="fas fa-door-open me-1"></i> Rooms (<span id="room-count"><?= count($rooms) ?></span>)</h3>
            <div id="rooms-list" class="space-y-1.5 max-h-56 overflow-y-auto">
                <?php if (empty($rooms)): ?>
                <p class="text-sm text-slate-400">No rooms yet.</p>
                <?php else: foreach ($rooms as $r): ?>
                <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg text-sm">
                    <div>
                        <span class="font-medium"><?= sanitize($r['name']) ?></span>
                        <div class="text-xs text-slate-500"><?= $r['length_m'] ?>×<?= $r['width_m'] ?>×<?= $r['height_m'] ?> m · <?= number_format($r['area_m2'],1) ?> m²</div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger btn-delete-room py-0 px-1 no-print" data-id="<?= $r['id'] ?>"><i class="fas fa-times"></i></button>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <h3 class="font-semibold text-slate-700 mb-2 text-sm"><i class="fas fa-chart-pie me-1 text-emerald-600"></i> Cost Summary</h3>
            <div id="summary-box">
                <?php if ($summary): ?>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Floor Area</span><span class="font-medium"><?= number_format((float)$project['total_area_m2'],1) ?> m²</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Materials</span><span><?= formatMoney((float)$summary['materials_subtotal']) ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Labour</span><span><?= formatMoney((float)$summary['labour_subtotal']) ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Transport</span><span><?= formatMoney((float)$summary['transport_amount']) ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Contingency</span><span><?= formatMoney((float)$summary['contingency_amount']) ?></span></div>
                    <hr class="my-1">
                    <div class="flex justify-between text-base font-bold text-emerald-700"><span>Grand Total</span><span><?= formatMoney((float)$summary['grand_total']) ?></span></div>
                </div>
                <?php else: ?>
                <p class="text-sm text-slate-400">Calculate BOQ after adding rooms.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-900 no-print">
            <strong>Draw walls inside rooms:</strong> Select Wall tool first (objects lock so they won’t move).<br>
            <strong>Columns/doors:</strong> Choose tool → click inside plan (not Select mode).<br>
            <strong>3D:</strong> Rebuild after placing. Roof & trees only if you enable / place them.<br>
            <strong>Move items:</strong> Switch back to Select (V) tool.
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script>
const PROJECT_ID=<?= $id ?>;
const existingCanvas=<?= json_encode($project['canvas_data']?:null) ?>;
const existingRooms=<?= json_encode(array_map(function($r){
    return ['name'=>$r['name'],'length_m'=>(float)$r['length_m'],'width_m'=>(float)$r['width_m'],'height_m'=>(float)$r['height_m']];
},$rooms)) ?>;

document.addEventListener('DOMContentLoaded',function(){
    const canvas=new fabric.Canvas('fabric-canvas',{backgroundColor:'#f1f5f9',selection:true,preserveObjectStacking:true});
    let currentTool='select',isDrawing=false,startX,startY,currentShape,scale=0.1,pendingFurniture=null,zoomLevel=1,dirty=false;
    let saveTimer=null;
    function scheduleSave(){ dirty=true; if(saveTimer) clearTimeout(saveTimer); saveTimer=setTimeout(function(){ if(dirty && typeof doSave==='function') doSave(true); }, 600); }

    let polyPoints=[]; // polyline wall
    const history=[]; let historyIndex=-1,isRestoring=false;

    function saveHistory(){
        if(isRestoring)return;
        if(historyIndex<history.length-1)history.splice(historyIndex+1);
        history.push(JSON.stringify(canvas.toJSON(['excludeFromExport','lengthLabel','isWall','isRoom','isDoor','isWindow','isFurniture','furnType','isColumn','isStairs','isHatch'])));
        if(history.length>60)history.shift();else historyIndex++;
        dirty=true;updateUndoRedo();
        scheduleSave();
    }
    function undo(){if(historyIndex<=0)return;historyIndex--;restoreState(history[historyIndex]);}
    function redo(){if(historyIndex>=history.length-1)return;historyIndex++;restoreState(history[historyIndex]);}
    function restoreState(json){isRestoring=true;canvas.loadFromJSON(json,()=>{canvas.renderAll();isRestoring=false;updateUndoRedo();dirty=true;});}
    function updateUndoRedo(){
        document.getElementById('btn-undo').style.opacity=historyIndex<=0?'0.35':'1';
        document.getElementById('btn-redo').style.opacity=historyIndex>=history.length-1?'0.35':'1';
    }

    document.addEventListener('keydown',e=>{
        if(e.ctrlKey&&e.key==='z'){e.preventDefault();undo();}
        if(e.ctrlKey&&(e.key==='y'||(e.shiftKey&&e.key==='Z'))){e.preventDefault();redo();}
        if((e.key==='Delete'||e.key==='Backspace')&&!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)){e.preventDefault();deleteSelected();}
        if(['INPUT','TEXTAREA'].includes(document.activeElement.tagName))return;
        if(e.key==='v'||e.key==='V')setTool('select');
        if(e.key==='w'||e.key==='W')setTool('wall');
        if(e.key==='r'||e.key==='R')setTool('rect');
        if(e.key==='Escape'){polyPoints=[];setTool('select');}
    });

    function drawGrid(){
        const g=20,w=canvas.width,h=canvas.height;
        for(let i=0;i<(w/g)+1;i++)canvas.add(new fabric.Line([i*g,0,i*g,h],{stroke:'#e2e8f0',selectable:false,evented:false,excludeFromExport:true}));
        for(let i=0;i<(h/g)+1;i++)canvas.add(new fabric.Line([0,i*g,w,i*g],{stroke:'#e2e8f0',selectable:false,evented:false,excludeFromExport:true}));
    }
    drawGrid();
    if(existingCanvas){try{canvas.loadFromJSON(JSON.parse(existingCanvas),()=>{canvas.renderAll();saveHistory();dirty=false;});}catch(e){saveHistory();}}
    else saveHistory();

    function setTool(tool){
        currentTool=tool;pendingFurniture=null;polyPoints=[];
        document.querySelectorAll('[data-tool]').forEach(b=>b.classList.toggle('active',b.dataset.tool===tool));
        document.querySelectorAll('.furn-item').forEach(f=>f.classList.remove('active'));
        const isSelect = tool==='select';
        canvas.selection = isSelect;
        canvas.defaultCursor = isSelect ? 'default' : 'crosshair';
        // When drawing: lock all objects so click places item instead of moving room
        canvas.forEachObject(function(obj){
            if(obj.excludeFromExport) return;
            obj.selectable = isSelect;
            obj.evented = isSelect;
        });
        canvas.discardActiveObject();
        canvas.requestRenderAll();
        const hints={select:'Select / move objects',wall:'Draw wall inside or outside rooms',rect:'Draw room',polyline:'Wall polyline — double-click to finish',door:'Click to place door',window:'Click to place window',column:'Click to place column inside room',stairs:'Place stairs',dim:'Dimension line',text:'Text label',hatch:'Floor hatch'};
        document.getElementById('tool-hint').innerHTML='<i class="fas fa-info-circle me-1"></i>'+(hints[tool]||'');
    }
    document.querySelectorAll('[data-tool]').forEach(btn=>btn.addEventListener('click',()=>setTool(btn.dataset.tool)));
    document.getElementById('scale-select').onchange=e=>{scale=parseFloat(e.target.value);dirty=true;};

    const FURN={sofa:'🛋️',bed:'🛏️',table:'🪑',chair:'💺',toilet:'🚽',sink:'🚰',stove:'🔥',car:'🚗',tree:'🌳',plant:'🪴',tv:'📺',bath:'🛁'};
    document.querySelectorAll('.furn-item').forEach(el=>{
        el.onclick=()=>{
            pendingFurniture=el.dataset.furn;currentTool='furniture';
            document.querySelectorAll('[data-tool]').forEach(b=>b.classList.remove('active'));
            document.querySelectorAll('.furn-item').forEach(f=>f.classList.remove('active'));
            el.classList.add('active');
            canvas.selection=false;
            canvas.defaultCursor='copy';
            canvas.forEachObject(function(obj){ if(!obj.excludeFromExport){ obj.selectable=false; obj.evented=false; }});
            canvas.discardActiveObject(); canvas.requestRenderAll();
        };
    });

    function snap(v){return Math.round(v/10)*10;}
    function placeDoor(x,y){
        canvas.add(new fabric.Group([
            new fabric.Rect({left:0,top:0,width:36,height:7,fill:'#fff',stroke:'#000',strokeWidth:2}),
            new fabric.Path('M 0 3.5 Q 18 -14 36 3.5',{fill:'',stroke:'#000',strokeWidth:1.5})
        ],{left:x-18,top:y-3.5,isDoor:true,selectable:false,evented:false}));saveHistory();
        document.getElementById('canvas-info').textContent='Door placed';
    }
    function placeWindow(x,y){
        canvas.add(new fabric.Group([
            new fabric.Rect({left:0,top:0,width:32,height:9,fill:'#bfdbfe',stroke:'#000',strokeWidth:2}),
            new fabric.Line([16,0,16,9],{stroke:'#000',strokeWidth:1})
        ],{left:x-16,top:y-4.5,isWindow:true,selectable:false,evented:false}));saveHistory();
        document.getElementById('canvas-info').textContent='Window placed';
    }
    function placeColumn(x,y){
        const col=new fabric.Rect({left:x-10,top:y-10,width:20,height:20,fill:'#64748b',stroke:'#000',strokeWidth:2,isColumn:true,selectable:false,evented:false});
        canvas.add(col);saveHistory();
        document.getElementById('canvas-info').textContent='Column placed';
    }
    function placeStairs(x,y){
        const lines=[];
        for(let i=0;i<5;i++) lines.push(new fabric.Line([0,i*8,40,i*8],{stroke:'#000',strokeWidth:1.5}));
        lines.push(new fabric.Rect({left:0,top:0,width:40,height:40,fill:'rgba(0,0,0,0.04)',stroke:'#000',strokeWidth:1}));
        canvas.add(new fabric.Group(lines,{left:x-20,top:y-20,isStairs:true,selectable:false,evented:false}));saveHistory();
        document.getElementById('canvas-info').textContent='Stairs placed';
    }
    function placeFurniture(x,y,type){
        canvas.add(new fabric.Text(FURN[type]||'⬛',{left:x,top:y,fontSize:26,originX:'center',originY:'center',isFurniture:true,furnType:type,selectable:false,evented:false}));
        saveHistory();
        document.getElementById('canvas-info').textContent=(type||'Item')+' placed';
    }
    function placeText(x,y){
        const label=prompt('Label:','Room');if(!label)return;
        canvas.add(new fabric.IText(label,{left:x,top:y,fontSize:13,fill:'#000',fontWeight:'bold'}));saveHistory();
    }

    canvas.on('mouse:down',o=>{
        const p=canvas.getPointer(o.e);
        if(currentTool==='furniture'&&pendingFurniture){placeFurniture(p.x,p.y,pendingFurniture);return;}
        if(currentTool==='door'){placeDoor(p.x,p.y);return;}
        if(currentTool==='window'){placeWindow(p.x,p.y);return;}
        if(currentTool==='column'){placeColumn(p.x,p.y);return;}
        if(currentTool==='stairs'){placeStairs(p.x,p.y);return;}
        if(currentTool==='text'){placeText(p.x,p.y);return;}
        if(currentTool==='polyline'){
            const x=snap(p.x),y=snap(p.y);
            if(o.e.detail===2&&polyPoints.length>=2){
                // finish polyline
                for(let i=0;i<polyPoints.length-1;i++){
                    const a=polyPoints[i],b=polyPoints[i+1];
                    const line=new fabric.Line([a.x,a.y,b.x,b.y],{stroke:'#000',strokeWidth:4,isWall:true,selectable:false,evented:false});
                    canvas.add(line);
                }
                polyPoints=[];saveHistory();return;
            }
            polyPoints.push({x,y});
            if(polyPoints.length>=2){
                const a=polyPoints[polyPoints.length-2],b=polyPoints[polyPoints.length-1];
                canvas.add(new fabric.Line([a.x,a.y,b.x,b.y],{stroke:'#000',strokeWidth:2,selectable:false,evented:false,excludeFromExport:true}));
            }
            document.getElementById('canvas-info').textContent='Polyline points: '+polyPoints.length+' (double-click to finish)';
            return;
        }
        if(!['rect','wall','dim','hatch'].includes(currentTool))return;
        isDrawing=true;startX=snap(p.x);startY=snap(p.y);
        if(currentTool==='rect'||currentTool==='hatch'){
            currentShape=new fabric.Rect({
                left:startX,top:startY,width:0,height:0,
                fill:currentTool==='hatch'?'rgba(148,163,184,0.25)':'rgba(59,130,246,0.08)',
                stroke:'#000',strokeWidth:currentTool==='hatch'?1:2.5,
                isRoom:currentTool==='rect',isHatch:currentTool==='hatch',
                selectable:false, evented:false
            });
        } else {
            currentShape=new fabric.Line([startX,startY,startX,startY],{
                stroke:currentTool==='dim'?'#2563eb':'#000',
                strokeWidth:currentTool==='dim'?1.5:4,
                isWall:currentTool==='wall',
                selectable:false,
                evented:false,
                originX:'center', originY:'center'
            });
        }
        canvas.add(currentShape);
    });
    canvas.on('mouse:move',o=>{
        if(!isDrawing||!currentShape)return;
        const p=canvas.getPointer(o.e);const x=snap(p.x),y=snap(p.y);
        if(currentTool==='rect'||currentTool==='hatch'){
            currentShape.set({width:Math.abs(x-startX),height:Math.abs(y-startY),left:Math.min(startX,x),top:Math.min(startY,y)});
            document.getElementById('canvas-info').textContent=(currentShape.width*scale).toFixed(1)+' × '+(currentShape.height*scale).toFixed(1)+' m';
        } else {
            currentShape.set({x2:x,y2:y});
            const dx=x-startX,dy=y-startY;
            document.getElementById('canvas-info').textContent=(Math.sqrt(dx*dx+dy*dy)*scale).toFixed(2)+' m';
        }
        canvas.renderAll();
    });
    canvas.on('mouse:up',()=>{
        if(!isDrawing||!currentShape)return;isDrawing=false;
        if((currentTool==='rect'||currentTool==='hatch')&&currentShape.width>6&&currentShape.height>6){
            if(currentTool==='rect'){
                const len=(currentShape.width*scale).toFixed(2),wid=(currentShape.height*scale).toFixed(2);
                const label=new fabric.Text(len+'×'+wid+' m',{left:currentShape.left+5,top:currentShape.top+5,fontSize:11,fill:'#000',fontWeight:'600',selectable:false,evented:false,lengthLabel:true});
                currentShape.lengthLabel=label;canvas.add(label);
            }
            saveHistory();
        } else if(['wall','dim'].includes(currentTool)&&currentShape){
            const dx=currentShape.x2-currentShape.x1,dy=currentShape.y2-currentShape.y1,px=Math.sqrt(dx*dx+dy*dy);
            if(px>4){
                if(currentTool==='wall'){
                    currentShape.set({isWall:true, selectable:false, evented:false, stroke:'#000', strokeWidth:4});
                }
                const m=(px*scale).toFixed(2);
                const midX=(currentShape.x1+currentShape.x2)/2 + (currentShape.left||0);
                const midY=(currentShape.y1+currentShape.y2)/2 + (currentShape.top||0);
                // For fabric Line without left offset, mid is from x1/x2
                const lx = (currentShape.x1+currentShape.x2)/2;
                const ly = (currentShape.y1+currentShape.y2)/2;
                const label=new fabric.Text(m+' m',{left:lx+3,top:ly-12,fontSize:10,fill:currentTool==='dim'?'#2563eb':'#000',fontWeight:'600',selectable:false,evented:false,lengthLabel:true});
                currentShape.lengthLabel=label;canvas.add(label);saveHistory();
                document.getElementById('canvas-info').textContent=(currentTool==='wall'?'Wall ':'Dim ')+m+' m';
            } else canvas.remove(currentShape);
        } else if(currentShape) canvas.remove(currentShape);
        currentShape=null;
    });

    function deleteSelected(){
        const a=canvas.getActiveObjects();if(!a.length)return;
        a.forEach(o=>{if(o.lengthLabel)canvas.remove(o.lengthLabel);canvas.remove(o);});
        canvas.discardActiveObject();canvas.renderAll();saveHistory();
    }
    document.getElementById('btn-delete').onclick=deleteSelected;
    document.getElementById('btn-clear').onclick=()=>{if(!confirm('Clear drawing?'))return;canvas.clear();canvas.backgroundColor='#f1f5f9';drawGrid();saveHistory();};
    document.getElementById('btn-undo').onclick=undo;
    document.getElementById('btn-redo').onclick=redo;

    function setZoom(z){zoomLevel=Math.max(0.3,Math.min(4,z));canvas.setZoom(zoomLevel);canvas.renderAll();}
    document.getElementById('btn-zoom-in-2d').onclick=()=>setZoom(zoomLevel+0.15);
    document.getElementById('btn-zoom-out-2d').onclick=()=>setZoom(zoomLevel-0.15);
    document.getElementById('btn-zoom-fit-2d').onclick=()=>setZoom(1);
    document.getElementById('z-in-2d').onclick=()=>setZoom(zoomLevel+0.15);
    document.getElementById('z-out-2d').onclick=()=>setZoom(zoomLevel-0.15);
    document.getElementById('z-fit-2d').onclick=()=>setZoom(1);
    canvas.on('mouse:wheel',opt=>{
        let z=canvas.getZoom()*(0.999**opt.e.deltaY);z=Math.max(0.3,Math.min(4,z));
        canvas.zoomToPoint({x:opt.e.offsetX,y:opt.e.offsetY},z);zoomLevel=z;
        opt.e.preventDefault();opt.e.stopPropagation();
    });

    function extractRoomsFromCanvas(){
        const rooms=[];
        canvas.getObjects('rect').forEach((rect,i)=>{
            if(rect.excludeFromExport||!rect.isRoom)return;
            const l=+(rect.width*scale).toFixed(2),w=+(rect.height*scale).toFixed(2);
            if(l>0.3&&w>0.3)rooms.push({name:'Room '+(i+1),length_m:l,width_m:w,height_m:parseFloat(document.getElementById('wall-height').value)||3,left:rect.left,top:rect.top,widthPx:rect.width,heightPx:rect.height});
        });
        return rooms;
    }

    /** Extract ALL drawable features for 3D (doors, windows, furniture, columns, stairs, walls) */
    function extractAllFeatures(){
        const features={rooms:[],doors:[],windows:[],furniture:[],columns:[],stairs:[],walls:[]};
        const h=parseFloat(document.getElementById('wall-height').value)||3;
        canvas.getObjects().forEach((obj,i)=>{
            if(obj.excludeFromExport) return;
            // Rooms
            if(obj.type==='rect' && obj.isRoom){
                const l=+(obj.width*scale).toFixed(2), w=+(obj.height*scale).toFixed(2);
                if(l>0.3&&w>0.3) features.rooms.push({name:'Room '+(features.rooms.length+1),length_m:l,width_m:w,height_m:h,left:obj.left,top:obj.top,widthPx:obj.width,heightPx:obj.height});
            }
            // Columns
            if(obj.isColumn || (obj.type==='rect' && obj.isColumn)){
                features.columns.push({x:obj.left+(obj.width||16)/2, y:obj.top+(obj.height||16)/2});
            }
            // Doors (group)
            if(obj.isDoor){
                const c=obj.getCenterPoint ? obj.getCenterPoint() : {x:obj.left,y:obj.top};
                features.doors.push({x:c.x, y:c.y, angle:obj.angle||0});
            }
            // Windows
            if(obj.isWindow){
                const c=obj.getCenterPoint ? obj.getCenterPoint() : {x:obj.left,y:obj.top};
                features.windows.push({x:c.x, y:c.y, angle:obj.angle||0});
            }
            // Furniture
            if(obj.isFurniture){
                const c=obj.getCenterPoint ? obj.getCenterPoint() : {x:obj.left,y:obj.top};
                features.furniture.push({x:c.x, y:c.y, type:obj.furnType||'sofa'});
            }
            // Stairs
            if(obj.isStairs){
                const c=obj.getCenterPoint ? obj.getCenterPoint() : {x:obj.left,y:obj.top};
                features.stairs.push({x:c.x, y:c.y});
            }
            // Wall lines — get absolute endpoints
            if(obj.isWall && (obj.type==='line' || obj.type==='Line')){
                let x1,y1,x2,y2;
                try {
                    // Fabric Line: x1,y1,x2,y2 are relative to left/top when origin is center sometimes
                    const pts = obj.calcLinePoints ? obj.calcLinePoints() : null;
                    if(obj.aCoords && obj.aCoords.tl && obj.aCoords.br){
                        // Use bounding corners as approximation OR transform points
                        const t = obj.calcTransformMatrix();
                        const p1 = fabric.util.transformPoint({x:obj.x1,y:obj.y1}, t);
                        const p2 = fabric.util.transformPoint({x:obj.x2,y:obj.y2}, t);
                        x1=p1.x; y1=p1.y; x2=p2.x; y2=p2.y;
                    } else {
                        x1=(obj.left||0)+(obj.x1||0); y1=(obj.top||0)+(obj.y1||0);
                        x2=(obj.left||0)+(obj.x2||0); y2=(obj.top||0)+(obj.y2||0);
                    }
                } catch(e){
                    x1=(obj.left||0)+(obj.x1||0); y1=(obj.top||0)+(obj.y1||0);
                    x2=(obj.left||0)+(obj.x2||0); y2=(obj.top||0)+(obj.y2||0);
                }
                if(Math.hypot(x2-x1,y2-y1)>3) features.walls.push({x1,y1,x2,y2});
            }
        });
        return features;
    }

    async function doSave(silent){
        const roomsData=extractRoomsFromCanvas().map(r=>({name:r.name,length_m:r.length_m,width_m:r.width_m,height_m:r.height_m}));
        const json=JSON.stringify(canvas.toJSON(['excludeFromExport','lengthLabel','isWall','isRoom','isDoor','isWindow','isFurniture','furnType','isColumn','isStairs','isHatch']));
        try{
            const res=await BOQ.post('api/save-project.php',{project_id:PROJECT_ID,canvas_data:json,rooms:roomsData,replace_rooms:true});
            if(res.success){
                dirty=false;
                const badge=document.getElementById('autosave-status');
                badge.innerHTML='<i class="fas fa-check-circle"></i> Saved '+new Date().toLocaleTimeString();
                badge.classList.add('saved');
                if(!silent)BOQ.showToast('Saved!');
                setTimeout(()=>{badge.classList.remove('saved');badge.innerHTML='<i class="fas fa-cloud"></i> Auto-save on';},4000);
            }
        }catch(e){if(!silent)BOQ.showToast('Save failed','error');}
    }
    document.getElementById('btn-save-canvas').onclick=()=>doSave(false);
    setInterval(function(){ if(dirty) doSave(true); }, 20000);

    // Print design
    document.getElementById('btn-print').onclick=()=>{
        window.print();
    };

    document.getElementById('btn-add-room').onclick=async()=>{
        const name=document.getElementById('room-name').value.trim()||'Room';
        const l=parseFloat(document.getElementById('room-l').value),w=parseFloat(document.getElementById('room-w').value),h=parseFloat(document.getElementById('room-h').value)||3;
        if(!l||!w||l<0.5||w<0.5){BOQ.showToast('Invalid dimensions','error');return;}
        const res=await BOQ.post('api/add-room.php',{project_id:PROJECT_ID,name,length_m:l,width_m:w,height_m:h});
        if(res.success){BOQ.showToast('Room added');setTimeout(()=>location.reload(),500);}
    };
    document.querySelectorAll('.btn-delete-room').forEach(btn=>{
        btn.onclick=async()=>{if(!confirm('Delete?'))return;const res=await BOQ.post('api/delete-room.php',{room_id:+btn.dataset.id});if(res.success)location.reload();};
    });

    document.getElementById('btn-calc').onclick=async()=>{
        const btn=document.getElementById('btn-calc');
        btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>...';
        const res=await BOQ.post('api/calculate.php',{project_id:PROJECT_ID});
        btn.disabled=false;btn.innerHTML='<i class="fas fa-calculator me-1"></i> Calculate BOQ';
        if(res.success){
            BOQ.showToast('BOQ calculated!');
            document.getElementById('summary-box').innerHTML=`<div class="space-y-1.5 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Floor Area</span><span class="font-medium">${res.floor_area_m2} m²</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Materials</span><span>${BOQ.formatMoney(res.materials_subtotal)}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Labour</span><span>${BOQ.formatMoney(res.labour_subtotal)}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Transport</span><span>${BOQ.formatMoney(res.transport_amount)}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Contingency</span><span>${BOQ.formatMoney(res.contingency_amount)}</span></div>
                <hr class="my-1"><div class="flex justify-between text-base font-bold text-emerald-700"><span>Grand Total</span><span>${BOQ.formatMoney(res.grand_total)}</span></div></div>`;
        } else BOQ.showToast(res.message||'Failed','error');
    };

    /* ===== 3D ===== */
    let threeScene,threeCamera,threeRenderer,threeControls,threeAnimId;
    let defaultCamPos=new THREE.Vector3(18,14,22);
    // In-memory rooms for 3D add (synced with canvas extract + existing)
    let liveRooms3d=[];

    document.getElementById('btn-2d').onclick=()=>{
        document.getElementById('btn-2d').classList.add('active');
        document.getElementById('btn-3d').classList.remove('active');
        document.getElementById('view-2d').style.display='block';
        document.getElementById('view-3d').style.display='none';
        document.getElementById('toolbar-2d').style.display='flex';
        document.getElementById('toolbar-3d').style.display='none';
        document.getElementById('panel-3d-room').style.display='none';
        if(threeAnimId)cancelAnimationFrame(threeAnimId);
    };
    document.getElementById('btn-3d').onclick=()=>{
        document.getElementById('btn-3d').classList.add('active');
        document.getElementById('btn-2d').classList.remove('active');
        document.getElementById('view-2d').style.display='none';
        document.getElementById('view-3d').style.display='block';
        document.getElementById('toolbar-2d').style.display='none';
        document.getElementById('toolbar-3d').style.display='flex';
        document.getElementById('panel-3d-room').style.display='block';
        const h=document.getElementById('wall-height').value;
        document.getElementById('wall-height-3d').value=h;
        build3D();
    };

    document.getElementById('btn-3d-add-room').onclick=()=>{
        document.getElementById('panel-3d-room').scrollIntoView({behavior:'smooth'});
        document.getElementById('r3d-name').focus();
    };
    document.getElementById('btn-3d-room-go').onclick=async()=>{
        const name=document.getElementById('r3d-name').value.trim()||'Room';
        const l=parseFloat(document.getElementById('r3d-l').value)||4;
        const w=parseFloat(document.getElementById('r3d-w').value)||3.5;
        const h=parseFloat(document.getElementById('r3d-h').value)||3;
        // Add to DB
        const res=await BOQ.post('api/add-room.php',{project_id:PROJECT_ID,name,length_m:l,width_m:w,height_m:h});
        if(res.success){
            // Also draw on 2D canvas so extract works
            const pxL=l/scale, pxW=w/scale;
            const offset=50+liveRooms3d.length*20;
            const rect=new fabric.Rect({
                left:100+offset,top:100+offset,width:pxL,height:pxW,
                fill:'rgba(59,130,246,0.08)',stroke:'#000',strokeWidth:2.5,isRoom:true
            });
            const label=new fabric.Text(l.toFixed(1)+'×'+w.toFixed(1)+' m',{left:105+offset,top:105+offset,fontSize:11,fill:'#000',fontWeight:'600',selectable:false,evented:false,lengthLabel:true});
            rect.lengthLabel=label;canvas.add(rect);canvas.add(label);saveHistory();
            await doSave(true);
            existingRooms.push({name,length_m:l,width_m:w,height_m:h});
            BOQ.showToast('Room added — rebuilding 3D');
            build3D();
            // update room count UI roughly
            const cnt=document.getElementById('room-count');
            if(cnt) cnt.textContent=parseInt(cnt.textContent||'0')+1;
        } else BOQ.showToast(res.message||'Failed','error');
    };

    document.getElementById('btn-regen-3d-tb').onclick=build3D;
    document.getElementById('opt-show-roof').onchange=build3D;
    document.getElementById('opt-show-edges').onchange=build3D;
    const hideEl=document.getElementById('opt-hide-labels');
    if(hideEl) hideEl.onchange=build3D;
    ['color-wall','color-floor','color-roof','color-column','color-ground'].forEach(id=>{
        const el=document.getElementById(id);
        if(el) el.oninput=()=>{ if(document.getElementById('view-3d').style.display!=='none') build3D(); };
    });
    document.getElementById('wall-height-3d').onchange=()=>{
        document.getElementById('wall-height').value=document.getElementById('wall-height-3d').value;
        build3D();
    };

    document.getElementById('z-in-3d').onclick=()=>{if(!threeCamera)return;threeCamera.position.multiplyScalar(0.7);threeControls.update();};
    document.getElementById('z-out-3d').onclick=()=>{if(!threeCamera)return;threeCamera.position.multiplyScalar(1.35);threeControls.update();};
    document.getElementById('z-fit-3d').onclick=()=>{if(!threeCamera||!threeControls)return;threeCamera.position.copy(defaultCamPos);threeControls.target.set(0,1.5,0);threeControls.update();};
    document.querySelectorAll('[data-cam]').forEach(btn=>{
        btn.onclick=()=>{
            if(!threeCamera||!threeControls)return;
            const t=btn.dataset.cam;
            if(t==='iso')threeCamera.position.set(18,14,22);
            if(t==='front')threeCamera.position.set(0,8,28);
            if(t==='top')threeCamera.position.set(0,35,0.1);
            if(t==='side')threeCamera.position.set(28,8,0);
            threeControls.target.set(0,1.5,0);threeControls.update();
        };
    });

    function createGableRoof(L,W,H,roofH,posX,posZ,roofMat,wallMat){
        const group=new THREE.Group();
        const hl=L/2,hw=W/2,oh=0.25;
        const x0=-hl-oh,x1=hl+oh,z0=-hw-oh,z1=hw+oh,ridgeY=H+roofH,eaveY=H;
        function plane(verts,mat){
            const geo=new THREE.BufferGeometry();
            geo.setAttribute('position',new THREE.BufferAttribute(new Float32Array(verts),3));
            geo.computeVertexNormals();
            const mesh=new THREE.Mesh(geo,mat);mesh.castShadow=true;mesh.receiveShadow=true;group.add(mesh);
        }
        plane([posX+x0,eaveY,posZ+z0, posX+x1,eaveY,posZ+z0, posX+x0,ridgeY,posZ+0,
               posX+x1,eaveY,posZ+z0, posX+x1,ridgeY,posZ+0, posX+x0,ridgeY,posZ+0],roofMat);
        plane([posX+x0,eaveY,posZ+z1, posX+x0,ridgeY,posZ+0, posX+x1,eaveY,posZ+z1,
               posX+x1,eaveY,posZ+z1, posX+x0,ridgeY,posZ+0, posX+x1,ridgeY,posZ+0],roofMat);
        const g=wallMat.clone();
        plane([posX-hl,eaveY,posZ-hw, posX-hl,ridgeY,posZ+0, posX-hl,eaveY,posZ+hw],g);
        plane([posX+hl,eaveY,posZ-hw, posX+hl,eaveY,posZ+hw, posX+hl,ridgeY,posZ+0],g);
        return group;
    }

    function build3D(){
        const container=document.getElementById('view-3d');
        const wallH=parseFloat(document.getElementById('wall-height-3d').value)||parseFloat(document.getElementById('wall-height').value)||3;
        const showRoof=document.getElementById('opt-show-roof').checked;
        const showEdges=document.getElementById('opt-show-edges').checked;
        const hideLabels=document.getElementById('opt-hide-labels') ? document.getElementById('opt-hide-labels').checked : true;
        const colWall=document.getElementById('color-wall') ? document.getElementById('color-wall').value : '#ffffff';
        const colFloor=document.getElementById('color-floor') ? document.getElementById('color-floor').value : '#f9a825';
        const colRoof=document.getElementById('color-roof') ? document.getElementById('color-roof').value : '#c62828';
        const colColumn=document.getElementById('color-column') ? document.getElementById('color-column').value : '#90a4ae';
        const colGround=document.getElementById('color-ground') ? document.getElementById('color-ground').value : '#4caf50';
        // Keep scale in sync with 2D dropdown
        scale = parseFloat(document.getElementById('scale-select').value) || scale || 0.1;

        const features=extractAllFeatures();
        let rooms3d=features.rooms;
        if(rooms3d.length===0&&existingRooms.length>0){
            let ox=0;
            existingRooms.forEach(r=>{
                rooms3d.push({name:r.name,length_m:r.length_m,width_m:r.width_m,height_m:r.height_m||wallH,left:ox/scale,top:80,widthPx:r.length_m/scale,heightPx:r.width_m/scale});
                ox+=r.length_m/scale+30;
            });
        }
        liveRooms3d=rooms3d;

        if(threeAnimId)cancelAnimationFrame(threeAnimId);
        if(threeRenderer){threeRenderer.dispose();const old=document.getElementById('three-canvas');if(old)old.remove();}
        const newC=document.createElement('canvas');newC.id='three-canvas';
        container.insertBefore(newC,container.firstChild);

        const width=container.clientWidth||900,height=560;
        const scene=new THREE.Scene();
        scene.background=new THREE.Color(0x87CEEB); // sky blue Unity-style
        // No fog — clear sharp 3D view
        scene.fog=null;

        const camera=new THREE.PerspectiveCamera(50,width/height,0.1,500);
        camera.position.copy(defaultCamPos);
        const renderer=new THREE.WebGLRenderer({canvas:newC,antialias:true});
        renderer.setSize(width,height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
        renderer.shadowMap.enabled=true;
        renderer.shadowMap.type=THREE.PCFSoftShadowMap;

        const controls=new THREE.OrbitControls(camera,renderer.domElement);
        controls.enableDamping=true;controls.dampingFactor=0.06;
        controls.target.set(0,1.2,0);
        controls.minDistance=0.5;   // zoom in very close
        controls.maxDistance=200;  // zoom out far
        controls.maxPolarAngle=Math.PI*0.49;
        controls.zoomSpeed=1.4;
        controls.panSpeed=1.0;
        controls.rotateSpeed=0.9;

        scene.add(new THREE.AmbientLight(0xffffff, 0.65));
        const sun=new THREE.DirectionalLight(0xffffff, 0.95);
        sun.position.set(20, 40, 15); sun.castShadow=true;
        sun.shadow.mapSize.set(2048, 2048);
        sun.shadow.camera.near=1; sun.shadow.camera.far=120;
        sun.shadow.camera.left=-40; sun.shadow.camera.right=40;
        sun.shadow.camera.top=40; sun.shadow.camera.bottom=-40;
        scene.add(sun);
        scene.add(new THREE.HemisphereLight(0xb3e5fc, 0x8bc34a, 0.35));
        // Fill light from opposite side for clarity
        const fill=new THREE.DirectionalLight(0xe3f2fd, 0.35);
        fill.position.set(-15, 20, -10);
        scene.add(fill);

        // Unity-style large green terrain
        const ground=new THREE.Mesh(
            new THREE.PlaneGeometry(200, 200),
            new THREE.MeshStandardMaterial({ color: new THREE.Color(colGround), roughness: 0.95, metalness: 0.0 })
        );
        ground.rotation.x=-Math.PI/2;
        ground.position.y=0;
        ground.receiveShadow=true;
        scene.add(ground);
        // Subtle grid on ground
        const grid=new THREE.GridHelper(80, 40, 0x388e3c, 0x66bb6a);
        grid.position.y=0.02;
        scene.add(grid);

        const wallMat=new THREE.MeshStandardMaterial({color:new THREE.Color(colWall), roughness:0.7, metalness:0.05}); // white walls
        const floorMat=new THREE.MeshStandardMaterial({color:new THREE.Color(colFloor), roughness:0.75, metalness:0.0}); // yellow floor inside rooms
        const roofMat=new THREE.MeshStandardMaterial({color:new THREE.Color(colRoof), roughness:0.6, side:THREE.DoubleSide});
        const edgeMat=new THREE.LineBasicMaterial({color:0x37474f});

        if(rooms3d.length===0){
            rooms3d=[{name:'Demo',length_m:6,width_m:4,height_m:wallH,left:100,top:100,widthPx:60,heightPx:40}];
        }

        let minX=Infinity,minY=Infinity,maxX=-Infinity,maxY=-Infinity;
        rooms3d.forEach(r=>{minX=Math.min(minX,r.left);minY=Math.min(minY,r.top);maxX=Math.max(maxX,r.left+r.widthPx);maxY=Math.max(maxY,r.top+r.heightPx);});
        // Also expand bounds from doors/windows/furniture so they align
        [...features.doors,...features.windows,...features.furniture,...features.columns,...features.stairs].forEach(f=>{
            if(f.x!=null){ minX=Math.min(minX,f.x); maxX=Math.max(maxX,f.x); minY=Math.min(minY,f.y); maxY=Math.max(maxY,f.y); }
        });
        if(!isFinite(minX)){ minX=0; maxX=200; minY=0; maxY=200; }
        const cX=(minX+maxX)/2,cY=(minY+maxY)/2;

        // Layout in REAL METRES — consistent size like Unity architecture view
        rooms3d.forEach(room=>{
            const L=Math.max(0.5, room.length_m);
            const W=Math.max(0.5, room.width_m);
            const H=room.height_m||wallH;
            // Position: canvas centre → metres (same scale as dimensions)
            const posX = ((room.left + room.widthPx/2) - cX) * scale;
            const posZ = ((room.top + room.heightPx/2) - cY) * scale;
            const t = 0.15; // wall thickness metres

            const floor=new THREE.Mesh(new THREE.BoxGeometry(L,0.1,W),floorMat);
            floor.position.set(posX,0.05,posZ);floor.receiveShadow=true;scene.add(floor);

            [{geo:new THREE.BoxGeometry(L+t*2,H,t),x:0,z:-W/2},
             {geo:new THREE.BoxGeometry(L+t*2,H,t),x:0,z:W/2},
             {geo:new THREE.BoxGeometry(t,H,W),x:-L/2,z:0},
             {geo:new THREE.BoxGeometry(t,H,W),x:L/2,z:0}].forEach(wd=>{
                const m=new THREE.Mesh(wd.geo,wallMat);
                m.position.set(posX+wd.x,H/2,posZ+wd.z);m.castShadow=true;m.receiveShadow=true;scene.add(m);
                if(showEdges){
                    const edges=new THREE.LineSegments(new THREE.EdgesGeometry(wd.geo),edgeMat);
                    edges.position.copy(m.position);scene.add(edges);
                }
            });

            // Roof ONLY if checkbox enabled
            let roofH=0;
            if(showRoof){
                roofH=Math.max(1.0,Math.min(L,W)*0.28);
                scene.add(createGableRoof(L,W,H,roofH,posX,posZ,roofMat,wallMat));
            }

            // Text labels hidden by default (like walls/trees — only show if unchecked)
            if(!hideLabels){
                const lc=document.createElement('canvas');lc.width=256;lc.height=48;
                const ctx=lc.getContext('2d');
                ctx.fillStyle='rgba(0,0,0,0.7)';ctx.fillRect(0,0,256,48);
                ctx.fillStyle='#fff';ctx.font='bold 22px system-ui,sans-serif';ctx.textAlign='center';
                ctx.fillText(room.name,128,32);
                const spr=new THREE.Sprite(new THREE.SpriteMaterial({map:new THREE.CanvasTexture(lc)}));
                spr.position.set(posX,H+roofH+0.9,posZ);spr.scale.set(3.5,0.7,1);scene.add(spr);
            }
        });


        // ---- Convert canvas px to 3D metres (same center as rooms) ----
        function to3D(px, py){
            return {
                x: (px - cX) * scale,
                z: (py - cY) * scale
            };
        }

        // Standalone walls (drawn as lines)
        features.walls.forEach(w=>{
            const a=to3D(w.x1,w.y1), b=to3D(w.x2,w.y2);
            const dx=b.x-a.x, dz=b.z-a.z;
            const len=Math.sqrt(dx*dx+dz*dz);
            if(len<0.05) return;
            const midX=(a.x+b.x)/2, midZ=(a.z+b.z)/2;
            const ang=Math.atan2(dz,dx);
            const geo=new THREE.BoxGeometry(len, wallH, 0.22);
            const mesh=new THREE.Mesh(geo, wallMat);
            mesh.position.set(midX, wallH/2, midZ);
            mesh.rotation.y=-ang;
            mesh.castShadow=true; mesh.receiveShadow=true;
            scene.add(mesh);
            // Edges on top of internal walls (same as room walls)
            if(showEdges){
                const edges=new THREE.LineSegments(new THREE.EdgesGeometry(geo), edgeMat);
                edges.position.copy(mesh.position);
                edges.rotation.y=mesh.rotation.y;
                scene.add(edges);
            }
        });

        // Doors — clear frame + open leaf (easy to see)
        const doorMat=new THREE.MeshStandardMaterial({color:0x4e342e, roughness:0.55});
        const doorLeafMat=new THREE.MeshStandardMaterial({color:0x6d4c41, roughness:0.5});
        features.doors.forEach(d=>{
            const p=to3D(d.x,d.y);
            const grp=new THREE.Group();
            // Frame uprights
            const postL=new THREE.Mesh(new THREE.BoxGeometry(0.1, 2.2, 0.14), doorMat);
            postL.position.set(-0.5, 1.1, 0);
            const postR=new THREE.Mesh(new THREE.BoxGeometry(0.1, 2.2, 0.14), doorMat);
            postR.position.set(0.5, 1.1, 0);
            const lintel=new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.12, 0.14), doorMat);
            lintel.position.set(0, 2.15, 0);
            // Door leaf (slightly open)
            const leaf=new THREE.Mesh(new THREE.BoxGeometry(0.95, 2.05, 0.05), doorLeafMat);
            leaf.position.set(0.25, 1.05, 0.35);
            leaf.rotation.y = 0.55;
            grp.add(postL, postR, lintel, leaf);
            grp.position.set(p.x, 0, p.z);
            grp.rotation.y = (d.angle||0)*Math.PI/180;
            grp.traverse(o=>{ if(o.isMesh){ o.castShadow=true; o.receiveShadow=true; }});
            scene.add(grp);
        });

        // Windows — clear glass + frame
        const winMat=new THREE.MeshStandardMaterial({color:0x4fc3f7, transparent:true, opacity:0.45, roughness:0.15});
        const winFrame=new THREE.MeshStandardMaterial({color:0x263238});
        features.windows.forEach(w=>{
            const p=to3D(w.x,w.y);
            const frame=new THREE.Mesh(new THREE.BoxGeometry(1.4, 1.3, 0.12), winFrame);
            frame.position.set(p.x, 1.55, p.z);
            scene.add(frame);
            const glass=new THREE.Mesh(new THREE.BoxGeometry(1.2, 1.1, 0.06), winMat);
            glass.position.set(p.x, 1.55, p.z);
            scene.add(glass);
        });

        // Columns + edges (same as room walls)
        const colMat = new THREE.MeshStandardMaterial({ color: new THREE.Color(colColumn), roughness: 0.65 });
        features.columns.forEach(c=>{
            const p=to3D(c.x,c.y);
            const geo=new THREE.BoxGeometry(0.4, wallH, 0.4);
            const col=new THREE.Mesh(geo, colMat);
            col.position.set(p.x, wallH/2, p.z);
            col.castShadow=true;
            col.receiveShadow=true;
            scene.add(col);
            if(showEdges){
                const edges=new THREE.LineSegments(new THREE.EdgesGeometry(geo), edgeMat);
                edges.position.copy(col.position);
                scene.add(edges);
            }
        });

        // Stairs
        features.stairs.forEach(s=>{
            const p=to3D(s.x,s.y);
            for(let i=0;i<6;i++){
                const step=new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.18, 0.28), new THREE.MeshStandardMaterial({color:0xa1887f}));
                step.position.set(p.x, 0.09+i*0.18, p.z - 0.5 + i*0.28);
                step.castShadow=true;
                scene.add(step);
            }
        });

        // Furniture — simple coloured boxes / markers by type
        const furnColors={sofa:0x3949ab,bed:0x5c6bc0,table:0x8d6e63,chair:0x6d4c41,toilet:0xeceff1,sink:0xb0bec5,stove:0x455a64,car:0xc62828,tree:0x2e7d32,plant:0x66bb6a,tv:0x212121,bath:0x90caf9};
        features.furniture.forEach(f=>{
            const p=to3D(f.x,f.y);
            const col=furnColors[f.type]||0x78909c;
            let mesh;
            if(f.type==='sofa'){
                mesh=new THREE.Mesh(new THREE.BoxGeometry(1.8,0.7,0.8), new THREE.MeshStandardMaterial({color:col}));
                mesh.position.set(p.x,0.35,p.z);
            } else if(f.type==='bed'){
                mesh=new THREE.Mesh(new THREE.BoxGeometry(2.0,0.5,1.4), new THREE.MeshStandardMaterial({color:col}));
                mesh.position.set(p.x,0.25,p.z);
            } else if(f.type==='table'){
                mesh=new THREE.Mesh(new THREE.BoxGeometry(1.2,0.7,0.8), new THREE.MeshStandardMaterial({color:col}));
                mesh.position.set(p.x,0.35,p.z);
            } else if(f.type==='car'){
                mesh=new THREE.Mesh(new THREE.BoxGeometry(4.2,1.4,1.8), new THREE.MeshStandardMaterial({color:col}));
                mesh.position.set(p.x,0.7,p.z);
            } else if(f.type==='tree'||f.type==='plant'){
                const trunk=new THREE.Mesh(new THREE.CylinderGeometry(0.08,0.12,0.8,6), new THREE.MeshStandardMaterial({color:0x5d4037}));
                trunk.position.set(p.x,0.4,p.z); scene.add(trunk);
                mesh=new THREE.Mesh(new THREE.SphereGeometry(0.6,8,6), new THREE.MeshStandardMaterial({color:col}));
                mesh.position.set(p.x,1.1,p.z);
            } else {
                mesh=new THREE.Mesh(new THREE.BoxGeometry(0.6,0.6,0.6), new THREE.MeshStandardMaterial({color:col}));
                mesh.position.set(p.x,0.3,p.z);
            }
            if(mesh){ mesh.castShadow=true; scene.add(mesh); }
        });

        // Auto landscape trees disabled — only user-placed tree/plant furniture appear


        threeScene=scene;threeCamera=camera;threeRenderer=renderer;threeControls=controls;
        // Natural architectural camera (Unity-like orbit view)
        let maxSpan = 8;
        rooms3d.forEach(r => { maxSpan = Math.max(maxSpan, r.length_m, r.width_m); });
        // Distance so building fills ~40% of view — readable size
        const dist = Math.max(15, maxSpan * 1.8);
        defaultCamPos.set(dist * 0.85, Math.max(10, wallH * 2.2 + maxSpan * 0.35), dist);
        camera.position.copy(defaultCamPos);
        controls.target.set(0, wallH * 0.5, 0);
        controls.minDistance = 2;
        controls.maxDistance = Math.max(250, maxSpan * 12);
        controls.update();

        function animate(){threeAnimId=requestAnimationFrame(animate);controls.update();renderer.render(scene,camera);}
        animate();
    }

    window.addEventListener('resize',()=>{
        if(!threeRenderer||document.getElementById('view-3d').style.display==='none')return;
        const w=document.getElementById('view-3d').clientWidth,h=560;
        threeCamera.aspect=w/h;threeCamera.updateProjectionMatrix();threeRenderer.setSize(w,h);
    });
});
</script>
<?php require 'includes/footer.php'; ?>
