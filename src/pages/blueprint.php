<?php
require_once '../includes/bootstrap.php';
$current_section = 'kits';
$current_page = 'backlog';
$page_title = 'Blueprint Editor';

$backlogid = (int)($_GET['backlogid'] ?? 0);
if (!$backlogid) {
    die("Invalid Backlog ID");
}

$blueprint = get_blueprint_by_backlog($conn, $backlogid);
$saved_data = $blueprint['canvas_data'] ?? 'null';

require_once '../includes/functions/paint.php';
require_once '../includes/functions/mixing_recipe.php';

// Fetch the user's paint inventory and custom recipes
$all_paints = get_paint_inventory($conn);
$all_recipes = get_recipes($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blueprint Editor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <script async src="/assets/js/opencv.js" onload="onOpenCvReady();" onerror="onOpenCvError();" type="text/javascript"></script>
    <style>
        body { margin: 0; overflow: hidden; background: #f3f4f6; font-family: sans-serif; }
        #desktop-ui { display: flex; flex-direction: column; height: 100vh; }
        #mobile-warning { display: none; text-align: center; padding: 50px; font-size: 1.5rem; color: #374151; }
        #canvas-container { flex-grow: 1; display: flex; justify-content: center; align-items: center; background: #e5e7eb; overflow: hidden; }
        .canvas-container { background: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .canvas-container.show-grid {
            background-image: radial-gradient(#9ca3af 1.5px, transparent 1.5px);
            background-size: 24px 24px;
            background-color: #f9fafb;
        }
        .toolbar { padding: 10px; background: #1f2937; color: white; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .tool-group { display: flex; gap: 5px; align-items: center; background: #374151; padding: 5px 10px; border-radius: 6px; border: 1px solid #4b5563; }
        .btn { padding: 5px 15px; background: #3b82f6; border-radius: 4px; cursor: pointer; color: white; border: none; font-weight: bold; }
        .btn:hover { background: #2563eb; }
        .btn.danger { background: #ef4444; }
        .btn.danger:hover { background: #dc2626; }
        .btn.save { background: #10b981; margin-left: auto; }
        .btn.save:hover { background: #059669; }

        @media (max-width: 768px) {
            #desktop-ui { display: none; }
            #mobile-warning { display: block; }
        }
    </style>
</head>
<body>

<div id="mobile-warning">
    <p>🚫</p>
    <p>The Blueprint Editor requires a larger screen.<br>Please use a Desktop or Tablet.</p>
    <br><a href="/backlog" class="btn">Go Back</a>
</div>

<div id="desktop-ui">
    <div class="toolbar">
        <div class="tool-group">
            <a href="/backlog" class="btn" style="background:#4b5563;">⬅ Back</a>
        </div>
        <div class="tool-group">
            <button class="btn" id="btn-select">↖️ Select</button>
            <button class="btn" id="btn-lasso" style="background:#f97316;">✂️ Lasso Snip</button>
            <button class="btn" id="btn-draw">🖌️ Free Draw</button>
            <button class="btn" id="btn-arrow">↗️ Arrow</button>
            <button class="btn" id="btn-text">📝 Text</button>
            <button class="btn" style="background:#eab308; color:black;" id="btn-sticky">🟨 Sticky</button>
        </div>
        <div class="tool-group">
            <button class="btn" id="btn-rect">🟦 Box</button>
            <button class="btn" id="btn-circle">🔵 Circle</button>
            <button class="btn" id="btn-triangle">🔺 Triangle</button>
        </div>
        <div class="tool-group">
            <input type="color" id="brush-color" value="#000000" class="h-8 w-10 border-0 cursor-pointer" title="Brush Color">
            <input type="range" id="brush-size" min="1" max="50" value="5" class="w-24 cursor-pointer" title="Brush Size">
            <label class="text-white text-sm ml-2 flex items-center gap-1 cursor-pointer">
                <input type="checkbox" id="fill-toggle" checked> Fill
            </label>
        </div>
        <div class="tool-group">
            <button class="btn" id="btn-image" style="background:#8b5cf6;">🖼️ Attach Image</button>
            <button class="btn" id="btn-paint-plan" style="background:#ec4899;">🎨 Paint Plan</button>
            <input type="file" id="image-upload" accept="image/*" style="display:none;">
            <button class="btn" id="btn-lineart" style="display:none; background:#ec4899;">🪄 Generate Lineart (Beta)</button>
        </div>
        <div class="tool-group" id="group-transform" style="display:none;">
            <button class="btn" id="btn-flip-x">↔️ Flip X</button>
            <button class="btn" id="btn-flip-y">↕️ Flip Y</button>
        </div>
        <div class="tool-group" id="group-text-format" style="display:none;">
            <button class="btn" id="btn-text-bold" style="font-weight:bold;">B</button>
            <button class="btn" id="btn-text-italic" style="font-style:italic; font-family:serif;">I</button>
            <button class="btn" id="btn-text-underline" style="text-decoration:underline;">U</button>
            <button class="btn" id="btn-text-strike" style="text-decoration:line-through;">S</button>
        </div>
        <div class="tool-group">
            <button class="btn" id="btn-forward" title="Bring Forward">⏫</button>
            <button class="btn" id="btn-backward" title="Send Backward">⏬</button>
            <button class="btn" id="btn-grid" title="Toggle Grid">🎛️ Grid</button>
            <button class="btn danger" id="btn-delete">🗑️ Delete</button>
        </div>
        <div class="tool-group" style="margin-left: auto;">
            <button class="btn" id="btn-undo" title="Ctrl+Z">↩️</button>
            <button class="btn" id="btn-redo" title="Ctrl+Y">↪️</button>
            <button class="btn danger" id="btn-clear">💣 Clear</button>
            <button class="btn" id="btn-download" style="background:#14b8a6;">⬇️ PNG</button>
            <button class="btn save" id="btn-save">💾 Save Blueprint</button>
        </div>
    </div>

    <!-- Floating Lasso Controls -->
    <div id="lasso-controls" style="display:none; position:absolute; top:80px; left:50%; transform:translateX(-50%); background:#1f2937; padding:10px; border-radius:8px; border:2px solid #f97316; z-index:100; gap:10px; align-items:center;">
        <span style="color:white; font-weight:bold;">✂️ Click points around the part to snip</span>
        <label style="color:white; font-size:14px; cursor:pointer;"><input type="checkbox" id="lasso-freehand-toggle"> Freehand Mode</label>
        <label style="color:white; font-size:14px; cursor:pointer;"><input type="checkbox" id="lasso-ai-toggle" checked> Run AI</label>
        <button class="btn" id="btn-lasso-undo" style="background:#8b5cf6;">↩️ Undo Point</button>
        <button class="btn save" id="btn-lasso-finish">✅ Finish Snip</button>
        <button class="btn danger" id="btn-lasso-cancel">❌ Cancel</button>
    </div>
    
    <!-- Paint Plan Sidebar -->
    <div id="paint-sidebar" style="display:none; position:absolute; top:60px; right:0; width:300px; height:calc(100vh - 60px); background:#1f2937; border-left:1px solid #374151; z-index:200; flex-direction:column; box-shadow: -4px 0 15px rgba(0,0,0,0.3);">
        <div style="padding:15px; border-bottom:1px solid #374151; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="color:white; font-size:18px; font-weight:bold;">🎨 Paint Inventory</h2>
            <button id="btn-close-paints" style="background:transparent; border:none; color:white; font-size:20px; cursor:pointer;">✖</button>
        </div>
        <div style="padding:10px 15px; border-bottom:1px solid #374151;">
            <input type="text" id="paint-search" placeholder="Search paints..." style="width:100%; padding:8px; border-radius:4px; border:1px solid #4b5563; background:#374151; color:white; outline:none; margin-bottom: 8px;">
            <button class="btn" id="btn-smart-match" style="width:100%; background:#14b8a6; display:none;">✨ Smart Match Color</button>
        </div>
        <div style="flex-grow:1; overflow-y:auto; padding:15px; display:flex; flex-direction:column; gap:10px;">
            
            <h3 style="color:#9ca3af; font-size:14px; text-transform:uppercase;">Custom Recipes</h3>
            <?php foreach ($all_recipes as $recipe): ?>
                <div class="paint-chip" style="background:#374151; padding:10px; border-radius:6px; cursor:pointer; border:1px solid #4b5563; transition: background 0.2s;" 
                     onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#374151'"
                     onclick="addPaintLabelToCanvas('Recipe: <?= htmlspecialchars(addslashes($recipe['name'])) ?>', '#eab308')">
                    <div style="color:white; font-weight:bold; font-size:14px;"><?= htmlspecialchars($recipe['name']) ?></div>
                    <div style="color:#9ca3af; font-size:12px;">Mixing Recipe</div>
                </div>
            <?php endforeach; ?>

            <h3 style="color:#9ca3af; font-size:14px; text-transform:uppercase; margin-top:10px;">Single Paints</h3>
            <?php foreach ($all_paints as $paint): ?>
                <?php 
                    $hex = !empty($paint['color_hex']) ? $paint['color_hex'] : '#374151'; 
                    $textColor = (hexdec(substr($hex, 1, 2)) * 0.299 + hexdec(substr($hex, 3, 2)) * 0.587 + hexdec(substr($hex, 5, 2)) * 0.114) > 186 ? '#000000' : '#ffffff';
                ?>
                <div class="paint-chip" style="background:<?= $hex ?>; padding:10px; border-radius:6px; cursor:pointer; border:1px solid #4b5563; transition: transform 0.2s; display:flex; align-items:center; gap:10px;" 
                     onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'"
                     onclick="addPaintLabelToCanvas('<?= htmlspecialchars(addslashes($paint['brand'] ?? 'Unknown')) ?>: <?= htmlspecialchars(addslashes($paint['name'])) ?>', '<?= $hex ?>', '<?= htmlspecialchars(addslashes($paint['imagepath'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($paint['finish'] ?? '')) ?>')">
                    <?php if (!empty($paint['imagepath'])): ?>
                        <img src="<?= htmlspecialchars($paint['imagepath']) ?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:1px solid rgba(255,255,255,0.3);">
                    <?php else: ?>
                        <div style="width:30px; height:30px; border-radius:50%; border:1px solid rgba(255,255,255,0.3); background:<?= $hex ?>;"></div>
                    <?php endif; ?>
                    <div style="flex:1;">
                        <div style="color:<?= $textColor ?>; font-weight:bold; font-size:14px;"><?= htmlspecialchars($paint['name']) ?></div>
                        <div style="color:<?= $textColor ?>; font-size:12px; opacity:0.8;"><?= htmlspecialchars($paint['brand'] ?? 'Unknown Brand') ?> <?= !empty($paint['finish']) ? ' | ' . htmlspecialchars($paint['finish']) : '' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($all_paints) && empty($all_recipes)): ?>
                <div style="color:#9ca3af; font-size:14px; text-align:center; margin-top:20px;">Your paint inventory is empty!</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="canvas-container">
        <canvas id="c"></canvas>
    </div>
<div id="tuning-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:white; padding:20px; border-radius:8px; display:flex; gap:20px;">
        <div>
            <!-- Live Preview Canvas -->
            <canvas id="tuning-canvas" style="max-width:500px; max-height:500px; border:1px solid #ccc;"></canvas>
        </div>
        <div style="display:flex; flex-direction:column; gap:15px; width:250px;">
            <h3 class="text-xl font-bold">Lineart Tuning</h3>
            <label>Flattening (Bilateral): <span id="val-blur">9</span>
                <input type="range" id="tune-blur" min="1" max="25" step="2" value="9" class="w-full cursor-pointer">
            </label>
            <label>Detail (Canny Threshold): <span id="val-thresh">50</span>
                <input type="range" id="tune-thresh" min="10" max="250" value="50" class="w-full cursor-pointer">
            </label>
            <label>Line Healing (Closing): <span id="val-heal">2</span>
                <input type="range" id="tune-heal" min="0" max="10" value="2" class="w-full cursor-pointer">
            </label>
            <label>Ink Thickness: <span id="val-thick">1</span>
                <input type="range" id="tune-thick" min="1" max="5" value="1" class="w-full cursor-pointer">
            </label>
            <div style="margin-top:auto; display:flex; gap:10px;">
                <button class="btn danger flex-1" onclick="document.getElementById('tuning-modal').style.display='none'">Cancel</button>
                <button class="btn save flex-1" id="btn-apply-tune">Apply</button>
            </div>
        </div>
    </div>
</div>

<div id="install-terminal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:2000; justify-content:center; align-items:center; flex-direction:column;">
    <div style="width: 600px; max-width: 90%; background: #121212; border: 1px solid #333; border-radius: 4px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); overflow: hidden; font-family: monospace;">
        <div style="background: #252526; padding: 8px 15px; border-bottom: 1px solid #111; display: flex; align-items: center; justify-content: space-between;">
            <div style="color: #999; font-size: 12px; font-weight: bold; letter-spacing: 1px;">>_ SYSTEM TERMINAL</div>
            <div style="color: #666; font-size: 11px;">OpenCV Installer</div>
        </div>
        <div id="terminal-output" style="padding: 20px; color: #0f0; min-height: 200px; white-space: pre-wrap; font-size: 14px; line-height: 1.5;"></div>
    </div>
</div>

<script>
    const ALL_PAINTS = <?= json_encode($all_paints); ?>;
    let cvReady = false;
    let cvError = false;
    function onOpenCvReady() { cvReady = true; }
    function onOpenCvError() { cvError = true; }

    // Initialize Canvas
    const container = document.getElementById('canvas-container');
    const canvas = new fabric.Canvas('c', {
        width: container.clientWidth - 40,
        height: container.clientHeight - 40,
        isDrawingMode: false,
        backgroundColor: '',
        fireMiddleClick: true,
        preserveObjectStacking: true
    });

    // Resize handling
    window.addEventListener('resize', () => {
        canvas.setWidth(container.clientWidth - 40);
        canvas.setHeight(container.clientHeight - 40);
        canvas.renderAll();
    });

    // Undo / Redo History
    let history = [];
    let historyIndex = -1;
    let isHistoryProcessing = false;
    
    // Unsaved Changes tracking
    let hasUnsavedChanges = false;
    let lastSavedHistoryIndex = 0;

    function checkUnsavedChanges() {
        hasUnsavedChanges = (historyIndex !== lastSavedHistoryIndex);
    }

    window.addEventListener('beforeunload', function (e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    let isLassoOperation = false;

    function saveHistory() {
        if (isHistoryProcessing || isLassoOperation) return;
        if (historyIndex < history.length - 1) {
            history = history.slice(0, historyIndex + 1);
            if (lastSavedHistoryIndex > historyIndex) {
                lastSavedHistoryIndex = -1; // Last saved state is overwritten
            }
        }
        history.push(JSON.stringify(canvas));
        historyIndex++;
        checkUnsavedChanges();
    }

    function undo() {
        if (historyIndex > 0) {
            isHistoryProcessing = true;
            historyIndex--;
            canvas.loadFromJSON(history[historyIndex], () => {
                canvas.renderAll();
                isHistoryProcessing = false;
                checkUnsavedChanges();
            });
        }
    }

    function redo() {
        if (historyIndex < history.length - 1) {
            isHistoryProcessing = true;
            historyIndex++;
            canvas.loadFromJSON(history[historyIndex], () => {
                canvas.renderAll();
                isHistoryProcessing = false;
                checkUnsavedChanges();
            });
        }
    }

    document.getElementById('btn-undo').onclick = undo;
    document.getElementById('btn-redo').onclick = redo;

    canvas.on('object:added', saveHistory);
    canvas.on('object:modified', saveHistory);
    canvas.on('object:removed', saveHistory);

    document.getElementById('btn-lasso-undo').onclick = () => {
        if (lassoPoints.length > 0) {
            lassoPoints.pop();
            let circle = lassoCircles.pop();
            canvas.remove(circle);
            if (lassoLines.length > 0) {
                let line = lassoLines.pop();
                canvas.remove(line);
            }
            canvas.renderAll();
        }
    };

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            if (isLassoMode) {
                document.getElementById('btn-lasso-undo').click();
            } else {
                undo();
            }
        }
        if (e.ctrlKey && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            if (!isLassoMode) redo();
        }
    });

    // Load Existing Data
    const existingData = <?= $saved_data ?: 'null' ?>;
    if (existingData) {
        isHistoryProcessing = true;
        canvas.loadFromJSON(existingData, () => {
            canvas.backgroundColor = ''; // Force clear any saved background color
            canvas.renderAll();
            isHistoryProcessing = false;
            saveHistory();
        });
    } else {
        saveHistory();
    }

    // Tools

    document.getElementById('btn-select').onclick = (e) => {
        canvas.isDrawingMode = false;
        canvas.selection = true;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        e.target.style.background = '#fbbf24';
    };

    document.getElementById('btn-draw').onclick = (e) => {
        canvas.isDrawingMode = !canvas.isDrawingMode;
        e.target.style.background = canvas.isDrawingMode ? '#fbbf24' : '#3b82f6';
        document.getElementById('btn-select').style.background = '#3b82f6';
        
        canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);
        canvas.freeDrawingBrush.color = document.getElementById('brush-color').value;
        canvas.freeDrawingBrush.width = parseInt(document.getElementById('brush-size').value, 10);
    };


    function syncGridToCamera() {
        const wrapper = document.querySelector('.canvas-container');
        if (!wrapper.classList.contains('show-grid')) return;
        
        const zoom = canvas.getZoom();
        const vpt = canvas.viewportTransform;
        
        const dotSize = 1.5 * zoom;
        const spacing = 24 * zoom;
        
        wrapper.style.backgroundImage = `radial-gradient(#9ca3af ${dotSize}px, transparent ${dotSize}px)`;
        wrapper.style.backgroundSize = `${spacing}px ${spacing}px`;
        wrapper.style.backgroundPosition = `${vpt[4]}px ${vpt[5]}px`;
    }

    document.getElementById('btn-grid').onclick = (e) => {
        const wrapper = document.querySelector('.canvas-container');
        wrapper.classList.toggle('show-grid');
        if (wrapper.classList.contains('show-grid')) {
            e.target.style.background = '#fbbf24';
            syncGridToCamera();
        } else {
            e.target.style.background = '#3b82f6';
            wrapper.style.backgroundImage = '';
            wrapper.style.backgroundSize = '';
            wrapper.style.backgroundPosition = '';
        }
    };
    
    // Enable grid by default
    setTimeout(() => {
        document.querySelector('.canvas-container').classList.add('show-grid');
        document.getElementById('btn-grid').style.background = '#fbbf24';
        syncGridToCamera();
    }, 100);

    document.getElementById('btn-forward').onclick = () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj) {
            canvas.bringForward(activeObj);
            canvas.renderAll();
            saveHistory();
        }
    };

    document.getElementById('btn-backward').onclick = () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj) {
            canvas.sendBackwards(activeObj);
            canvas.renderAll();
            saveHistory();
        }
    };

    document.getElementById('btn-clear').onclick = () => {
        if (confirm('Are you sure you want to completely clear the blueprint? This cannot be undone.')) {
            canvas.clear();
            saveHistory();
        }
    };

    document.getElementById('btn-download').onclick = () => {
        canvas.backgroundColor = 'white';
        const dataURL = canvas.toDataURL({ format: 'png', quality: 1 });
        canvas.backgroundColor = '';
        canvas.renderAll();

        const link = document.createElement('a');
        link.download = 'gunpla_blueprint.png';
        link.href = dataURL;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    document.getElementById('brush-color').addEventListener('input', function() {
        const color = this.value;
        canvas.freeDrawingBrush.color = color;
        
        const activeObj = canvas.getActiveObject();
        if (activeObj) {
            if (activeObj.type === 'i-text' || activeObj.type === 'textbox') {
                activeObj.set('backgroundColor', color);
            } else {
                activeObj.set('fill', color);
            }
            canvas.renderAll();
        }
    });

    // Auto-sync the color picker when you click on a shape
    function syncColorPicker() {
        const activeObj = canvas.getActiveObject();
        if (activeObj) {
            let color = null;
            if ((activeObj.type === 'i-text' || activeObj.type === 'textbox') && activeObj.backgroundColor) {
                color = activeObj.backgroundColor;
            } else if (activeObj.fill && typeof activeObj.fill === 'string') {
                color = activeObj.fill;
            }
            // Only update if it's a valid hex code
            if (color && color.startsWith('#') && color.length === 7) {
                document.getElementById('brush-color').value = color;
                canvas.freeDrawingBrush.color = color;
            }
        }
    }

    canvas.on('selection:created', syncColorPicker);
    canvas.on('selection:updated', syncColorPicker);
    document.getElementById('brush-size').addEventListener('input', function() {
        canvas.freeDrawingBrush.width = parseInt(this.value, 10);
    });
    
    // Set initial brush settings
    canvas.freeDrawingBrush.color = '#000000';
    canvas.freeDrawingBrush.width = 5;

    document.getElementById('btn-text').onclick = () => {
        canvas.isDrawingMode = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        document.getElementById('btn-select').style.background = '#3b82f6';
        
        const text = new fabric.IText('Text', { 
            left: 100, 
            top: 100, 
            fontSize: 20,
            fontFamily: 'sans-serif'
        });
        canvas.add(text);
        canvas.setActiveObject(text);
    };

    document.getElementById('btn-sticky').onclick = () => {
        canvas.isDrawingMode = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        document.getElementById('btn-select').style.background = '#3b82f6';
        
        const sticky = new fabric.Textbox('Double click to edit\n\n\n', { 
            left: 100, 
            top: 100, 
            width: 150,
            fontSize: 20,
            fontFamily: 'sans-serif',
            backgroundColor: '#fef08a', // Tailwind yellow-200
            padding: 15,
            splitByGrapheme: false
        });
        canvas.add(sticky);
        canvas.setActiveObject(sticky);
    };

    document.getElementById('fill-toggle').onchange = (e) => {
        const activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.type !== 'i-text' && activeObj.type !== 'textbox' && activeObj.type !== 'image' && activeObj.type !== 'group') {
            if (e.target.checked) {
                activeObj.set({ fill: activeObj.stroke });
            } else {
                activeObj.set({ fill: 'transparent' });
            }
            canvas.renderAll();
            saveHistory();
        }
    };

    function getShapeColors() {
        const color = document.getElementById('brush-color').value;
        const isFilled = document.getElementById('fill-toggle').checked;
        return {
            fill: isFilled ? color : 'transparent',
            stroke: color
        };
    }

    document.getElementById('btn-rect').onclick = () => {
        canvas.isDrawingMode = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        document.getElementById('btn-select').style.background = '#3b82f6';
        const colors = getShapeColors();
        const rect = new fabric.Rect({ 
            left: 100, top: 100, 
            fill: colors.fill, 
            stroke: colors.stroke, 
            strokeWidth: parseInt(document.getElementById('brush-size').value, 10) || 3, 
            width: 100, height: 100 
        });
        canvas.add(rect);
        canvas.setActiveObject(rect);
    };

    document.getElementById('btn-circle').onclick = () => {
        canvas.isDrawingMode = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        document.getElementById('btn-select').style.background = '#3b82f6';
        const colors = getShapeColors();
        const circle = new fabric.Circle({ 
            left: 100, top: 100, radius: 50, 
            fill: colors.fill, 
            stroke: colors.stroke, 
            strokeWidth: parseInt(document.getElementById('brush-size').value, 10) || 3
        });
        canvas.add(circle);
        canvas.setActiveObject(circle);
    };

    document.getElementById('btn-triangle').onclick = () => {
        canvas.isDrawingMode = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        document.getElementById('btn-select').style.background = '#3b82f6';
        const colors = getShapeColors();
        const triangle = new fabric.Triangle({ 
            left: 100, top: 100, width: 100, height: 100, 
            fill: colors.fill, 
            stroke: colors.stroke, 
            strokeWidth: parseInt(document.getElementById('brush-size').value, 10) || 3
        });
        canvas.add(triangle);
        canvas.setActiveObject(triangle);
    };

    document.getElementById('btn-image').onclick = () => {
        canvas.isDrawingMode = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        document.getElementById('btn-select').style.background = '#3b82f6';
        document.getElementById('image-upload').click();
    };

    document.getElementById('image-upload').onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(f) {
            const data = f.target.result;
            fabric.Image.fromURL(data, function(img) {
                if (img.width > 800) img.scaleToWidth(800);
                canvas.add(img);
                canvas.setActiveObject(img);
                document.getElementById('image-upload').value = ''; 
            });
        };
        reader.readAsDataURL(file);
    };

    document.getElementById('btn-delete').onclick = () => {
        const active = canvas.getActiveObjects();
        if (active.length) {
            canvas.discardActiveObject();
            active.forEach((obj) => canvas.remove(obj));
        }
    };

    // OpenCV Lineart Logic
    let tuneSrcMat = null;
    let targetActiveObj = null;

    document.getElementById('btn-lineart').onclick = async function() {
        if (cvError || !cvReady) {
            if (confirm("The Lineart Engine (OpenCV) is not installed. It is an optional 8MB download. Would you like to install it now?")) {
                const btn = document.getElementById('btn-lineart');
                btn.innerHTML = '⏳ Downloading Engine...';
                btn.disabled = true;
                
                const term = document.getElementById('install-terminal');
                const out = document.getElementById('terminal-output');
                term.style.display = 'flex';
                out.innerHTML = '';
                
                const log = (msg) => { 
                    out.innerHTML = out.innerHTML.replace(/<span class="spinner">.*?<\/span>/g, '... [OK]');
                    out.innerHTML += '> ' + msg + ' <span class="spinner">|</span>\n'; 
                };
                
                const frames = ['|', '/', '-', '\\'];
                let fIdx = 0;
                const spinnerInterval = setInterval(() => {
                    const spans = out.getElementsByClassName('spinner');
                    if (spans.length > 0) {
                        spans[spans.length - 1].innerText = frames[fIdx];
                        fIdx = (fIdx + 1) % frames.length;
                    }
                }, 100);
                
                log('Initializing OpenCV Installer');
                await new Promise(r => setTimeout(r, 800));
                
                log('Establishing connection to docs.opencv.org');
                await new Promise(r => setTimeout(r, 600));
                
                log('Downloading opencv.js (8.3 MB)');
                out.innerHTML += '  (This may take several seconds depending on your connection)\n';
                
                // We let the actual API do the heavy lifting in the background
                try {
                    const res = await fetch('/api/install_opencv.php');
                    const data = await res.json();
                    
                    clearInterval(spinnerInterval);
                    out.innerHTML = out.innerHTML.replace(/<span class="spinner">.*?<\/span>/g, '... [OK]');
                    
                    if (data.success) {
                        out.innerHTML += '\n> ✅ Download complete!\n';
                        out.innerHTML += '> Writing to local filesystem at /assets/js/opencv.js...\n';
                        await new Promise(r => setTimeout(r, 800));
                        out.innerHTML += '> Installation successful! Reloading engine...\n';
                        await new Promise(r => setTimeout(r, 1000));
                        location.reload();
                    } else {
                        out.innerHTML += '\n> ❌ Installation failed: ' + data.message + '\n';
                        btn.innerHTML = '🪄 Generate Lineart';
                        btn.disabled = false;
                        setTimeout(() => { term.style.display = 'none'; out.innerHTML = ''; }, 3000);
                    }
                } catch (e) {
                    clearInterval(spinnerInterval);
                    out.innerHTML = out.innerHTML.replace(/<span class="spinner">.*?<\/span>/g, '... [FAIL]');
                    out.innerHTML += '\n> ❌ Network error while communicating with local installer.\n';
                    btn.innerHTML = '🪄 Generate Lineart';
                    btn.disabled = false;
                    setTimeout(() => { term.style.display = 'none'; out.innerHTML = ''; }, 3000);
                }
            }
            return;
        }

        targetActiveObj = canvas.getActiveObject();
        if (!targetActiveObj || targetActiveObj.type !== 'image') return;

        // Open modal
        document.getElementById('tuning-modal').style.display = 'flex';
        
        // Extract original image to OpenCV Mat
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = targetActiveObj.width;
        tempCanvas.height = targetActiveObj.height;
        const ctx = tempCanvas.getContext('2d');
        ctx.drawImage(targetActiveObj.getElement(), 0, 0, targetActiveObj.width, targetActiveObj.height);
        
        if (tuneSrcMat) tuneSrcMat.delete(); // Free memory if opened previously
        tuneSrcMat = cv.imread(tempCanvas);
        
        runTuningPipeline();
    };

    // The 4-Layer Refinement Pipeline
    function runTuningPipeline() {
        if (!tuneSrcMat) return;
        let dst = new cv.Mat();
        let colorMat = new cv.Mat();
        
        // Pre-Step: Strip Alpha (RGBA -> RGB) because Bilateral Filter requires 3 channels
        cv.cvtColor(tuneSrcMat, colorMat, cv.COLOR_RGBA2RGB, 0);
        
        // LAYER 1: Bilateral Filter (The Cel-Shader)
        // Flattens smooth lighting gradients into solid colors without blurring the armor edges
        let blurVal = parseInt(document.getElementById('tune-blur').value);
        if (blurVal > 1) {
            let temp = new cv.Mat();
            cv.bilateralFilter(colorMat, temp, blurVal, 75, 75, cv.BORDER_DEFAULT);
            temp.copyTo(colorMat);
            temp.delete();
        }
        
        // Convert the flattened color image to Grayscale for Canny
        cv.cvtColor(colorMat, dst, cv.COLOR_RGB2GRAY, 0);
        colorMat.delete(); // Free memory
        
        // LAYER 2: Canny Edge Detection
        let thresh = parseInt(document.getElementById('tune-thresh').value);
        cv.Canny(dst, dst, thresh, thresh * 2, 3, false);
        
        // LAYER 3: Morphological Closing (Line Healing)
        // Canny produces white lines on a black background. 
        // This bridges any gaps and reconnects dotted/broken lines together.
        let healVal = parseInt(document.getElementById('tune-heal').value);
        if (healVal > 0) {
            let MHeal = cv.Mat.ones(healVal, healVal, cv.CV_8U);
            cv.morphologyEx(dst, dst, cv.MORPH_CLOSE, MHeal);
            MHeal.delete();
        }
        
        // Pre-Layer 4: Invert Colors (We want black ink on white background)
        cv.bitwise_not(dst, dst);
        
        // LAYER 4: Ink Thickness
        // Because the ink is now black on a white canvas, we use EROSION to eat away the white background, which thickens the black lines!
        let thickVal = parseInt(document.getElementById('tune-thick').value);
        if (thickVal > 1) {
            let MThick = cv.Mat.ones(thickVal, thickVal, cv.CV_8U);
            cv.erode(dst, dst, MThick, new cv.Point(-1, -1), 1, cv.BORDER_CONSTANT, cv.morphologyDefaultBorderValue());
            MThick.delete();
        }
        
        // Push result to the live preview canvas
        cv.imshow('tuning-canvas', dst);
        dst.delete(); // Free memory
    }

    // Bind the Sliders
    ['blur', 'thresh', 'heal', 'thick'].forEach(id => {
        document.getElementById('tune-' + id).addEventListener('input', function() {
            document.getElementById('val-' + id).innerText = this.value;
            runTuningPipeline();
        });
    });

    // Apply the Tuned Image to the Blueprint
    document.getElementById('btn-apply-tune').onclick = function() {
        const previewCanvas = document.getElementById('tuning-canvas');
        const dataURL = previewCanvas.toDataURL('image/png');
        
        fabric.Image.fromURL(dataURL, function(newImg) {
            newImg.set({
                left: targetActiveObj.left,
                top: targetActiveObj.top,
                scaleX: targetActiveObj.scaleX,
                scaleY: targetActiveObj.scaleY,
                angle: targetActiveObj.angle
            });
            
            canvas.remove(targetActiveObj);
            canvas.add(newImg);
            canvas.setActiveObject(newImg);
            saveHistory();
            
            document.getElementById('tuning-modal').style.display = 'none';
        });
    };

    // Show lineart button only for images
    canvas.on('selection:created', function(e) {
        const obj = e.selected[0];
        if (obj && obj.type === 'image') {
            document.getElementById('btn-lineart').style.display = 'inline-block';
            document.getElementById('group-transform').style.display = 'flex';
        }
        if (obj && (obj.type === 'i-text' || obj.type === 'textbox')) {
            document.getElementById('group-text-format').style.display = 'flex';
        } else {
            document.getElementById('group-text-format').style.display = 'none';
        }
    });

    canvas.on('selection:cleared', function() {
        document.getElementById('btn-lineart').style.display = 'none';
        document.getElementById('group-transform').style.display = 'none';
        document.getElementById('group-text-format').style.display = 'none';
    });

    canvas.on('selection:updated', function(e) {
        const obj = e.selected[0];
        if (obj && obj.type === 'image') {
            document.getElementById('btn-lineart').style.display = 'inline-block';
            document.getElementById('group-transform').style.display = 'flex';
        } else {
            document.getElementById('btn-lineart').style.display = 'none';
            document.getElementById('group-transform').style.display = 'none';
        }
        if (obj && (obj.type === 'i-text' || obj.type === 'textbox')) {
            document.getElementById('group-text-format').style.display = 'flex';
        } else {
            document.getElementById('group-text-format').style.display = 'none';
        }
    });

    // Text Formatting Logic
    function toggleTextStyle(prop, value, inactiveValue) {
        let obj = canvas.getActiveObject();
        if (!obj || (obj.type !== 'i-text' && obj.type !== 'textbox')) return;
        
        if (obj.selectionStart !== obj.selectionEnd) {
            let styleObj = {};
            // Check if first selected character has the property (simple toggle logic)
            let currentStyles = obj.getSelectionStyles();
            let isSet = currentStyles.length > 0 && currentStyles[0][prop] === value;
            styleObj[prop] = isSet ? inactiveValue : value;
            obj.setSelectionStyles(styleObj);
        } else {
            obj.set(prop, obj[prop] === value ? inactiveValue : value);
        }
        canvas.renderAll();
        saveHistory();
    }

    document.getElementById('btn-text-bold').onclick = () => toggleTextStyle('fontWeight', 'bold', 'normal');
    document.getElementById('btn-text-italic').onclick = () => toggleTextStyle('fontStyle', 'italic', 'normal');
    document.getElementById('btn-text-underline').onclick = () => toggleTextStyle('underline', true, false);
    document.getElementById('btn-text-strike').onclick = () => toggleTextStyle('linethrough', true, false);

    // The Flip Logic
    document.getElementById('btn-flip-x').onclick = () => {
        let obj = canvas.getActiveObject();
        if(obj) { obj.set('flipX', !obj.flipX); canvas.renderAll(); saveHistory(); }
    };
    
    document.getElementById('btn-flip-y').onclick = () => {
        let obj = canvas.getActiveObject();
        if(obj) { obj.set('flipY', !obj.flipY); canvas.renderAll(); saveHistory(); }
    };
    let isLassoMode = false;
    let lassoPoints = [];
    let lassoLines = [];
    let lassoCircles = [];
    let lassoTargetObj = null;

    document.getElementById('btn-lasso').onclick = (e) => {
        const activeObj = canvas.getActiveObject();
        if (!activeObj || activeObj.type !== 'image') {
            alert("Please select an Image to snip from first!");
            return;
        }
        
        lassoTargetObj = activeObj;
        isLassoMode = true;
        canvas.selection = false;
        canvas.isDrawingMode = false;
        
        // Lock all objects so they don't move while drawing
        canvas.getObjects().forEach(o => { o.set({ selectable: false, evented: false }); });
        
        // Reset drawing memory
        lassoPoints = [];
        lassoLines.forEach(l => canvas.remove(l));
        lassoCircles.forEach(c => canvas.remove(c));
        lassoLines = [];
        lassoCircles = [];
        
        document.getElementById('lasso-controls').style.display = 'flex';
        document.getElementById('btn-select').style.background = '#3b82f6';
        e.target.style.background = '#fbbf24';
    };

    document.getElementById('btn-lasso-cancel').onclick = () => {
        isLassoOperation = true;
        isLassoMode = false;
        lassoLines.forEach(l => canvas.remove(l));
        lassoCircles.forEach(c => canvas.remove(c));
        if (window.lassoClosingLine) { canvas.remove(window.lassoClosingLine); window.lassoClosingLine = null; }
        canvas.getObjects().forEach(o => { o.set({ selectable: true, evented: true }); });
        
        document.getElementById('lasso-controls').style.display = 'none';
        document.getElementById('btn-lasso').style.background = '#f97316';
        isLassoOperation = false;
        saveHistory();
        document.getElementById('btn-select').click();
    };

    document.getElementById('btn-lasso-finish').onclick = async () => {
        if (lassoPoints.length < 3) {
            alert("Please click at least 3 points to form a shape!");
            return;
        }
        
        if (window.lassoClosingLine) { canvas.remove(window.lassoClosingLine); window.lassoClosingLine = null; }
        canvas.getObjects().forEach(o => { o.set({ selectable: true, evented: true }); });
        
        let finishBtn = document.getElementById('btn-lasso-finish');
        let useAI = document.getElementById('lasso-ai-toggle').checked && localStorage.getItem('imgly_approved');
        finishBtn.innerHTML = useAI ? '⏳ AI Removing Background...' : '⏳ Extracting Snippet...';
        finishBtn.disabled = true;
        
        let minX = Math.min(...lassoPoints.map(p => p.x));
        let maxX = Math.max(...lassoPoints.map(p => p.x));
        let minY = Math.min(...lassoPoints.map(p => p.y));
        let maxY = Math.max(...lassoPoints.map(p => p.y));
        
        let polygon = new fabric.Polygon(lassoPoints, { absolutePositioned: true });
        
        lassoTargetObj.clone(async function(clone) {
            let width = maxX - minX;
            let height = maxY - minY;
            
            // 1. RECTANGULAR CROP (For AI) - No clipPath!
            let staticCanvasRect = new fabric.StaticCanvas(null, { width: width, height: height });
            clone.set({ left: clone.left - minX, top: clone.top - minY });
            staticCanvasRect.add(clone);
            staticCanvasRect.renderAll();
            let rectDataUrl = staticCanvasRect.toDataURL('image/png');
            
            // 2. POLYGONAL CROP (For Fallback)
            let staticCanvasPoly = new fabric.StaticCanvas(null, { width: width, height: height });
            clone.set({ clipPath: polygon });
            polygon.set({ left: polygon.left - minX, top: polygon.top - minY });
            staticCanvasPoly.add(clone);
            staticCanvasPoly.renderAll();
            let polyDataUrl = staticCanvasPoly.toDataURL('image/png');
            
            // AI Opt-In Check
            let userApprovedAI = localStorage.getItem('imgly_approved');
            if (!userApprovedAI) {
                const wantsAI = confirm("✨ First Time Setup ✨\n\nWould you like to use the AI Background Removal model to perfectly extract your snippet? \n\nThis requires a one-time download of a 40MB AI model that runs locally in your browser (no data is sent to the cloud!).\n\nClick OK to download and run the AI, or Cancel to just place the raw polygon shape.");
                if (wantsAI) {
                    localStorage.setItem('imgly_approved', 'true');
                    userApprovedAI = true;
                }
            }

            if (userApprovedAI && document.getElementById('lasso-ai-toggle').checked) {
                try {
                    // Dynamically import imgly ESM module via esm.sh to resolve lodash CommonJS dependencies correctly
                    const imglyModule = await import('https://esm.sh/@imgly/background-removal@1.4.3');
                    const imglyRemoveBackground = imglyModule.default || imglyModule.removeBackground;

                    // FEED THE RECTANGULAR CROP TO IMGLY ONNX AI
                    let aiConfig = {
                        publicPath: 'https://unpkg.com/@imgly/background-removal-data@1.4.3/dist/'
                    };
                    let imageBlob = await imglyRemoveBackground(rectDataUrl, aiConfig);
                    let aiDataUrl = URL.createObjectURL(imageBlob);
                    
                    fabric.Image.fromURL(aiDataUrl, function(aiImg) {
                        // BAKE THE POLYGON CLIP ONTO THE AI RESULT
                        let staticCanvasFinal = new fabric.StaticCanvas(null, { width: width, height: height });
                        aiImg.set({ clipPath: polygon });
                        staticCanvasFinal.add(aiImg);
                        staticCanvasFinal.renderAll();
                        let finalBakeUrl = staticCanvasFinal.toDataURL('image/png');
                        
                        fabric.Image.fromURL(finalBakeUrl, function(finalImg) {
                            finalImg.set({ left: minX + 50, top: minY + 50 });
                            canvas.add(finalImg);
                            canvas.setActiveObject(finalImg);
                            saveHistory();
                            
                            finishBtn.innerHTML = '✅ Finish Snip';
                            finishBtn.disabled = false;
                            document.getElementById('btn-lasso-cancel').click();
                        });
                    });
                } catch (err) {
                    console.error("AI Background Removal Failed:", err);
                    alert("AI Background Removal failed: " + (err.message || err) + "\n\n(this usually happens if the first download was interrupted). Placing raw snippet instead.");
                    
                    // Fallback to the raw snippet
                    fabric.Image.fromURL(polyDataUrl, function(img) {
                        img.set({ left: minX + 50, top: minY + 50 });
                        canvas.add(img);
                        canvas.setActiveObject(img);
                        saveHistory();
                        
                        finishBtn.innerHTML = '✅ Finish Snip';
                        finishBtn.disabled = false;
                        document.getElementById('btn-lasso-cancel').click();
                    });
                }
            } else {
                // User opted out, use raw snippet directly
                fabric.Image.fromURL(polyDataUrl, function(img) {
                    img.set({ left: minX + 50, top: minY + 50 });
                    canvas.add(img);
                    canvas.setActiveObject(img);
                    
                    finishBtn.innerHTML = '✅ Finish Snip';
                    finishBtn.disabled = false;
                    document.getElementById('btn-lasso-cancel').click();
                });
            }
        });
    };


    let isDrawingArrow = false;
    let arrowLine, arrowHead;

    document.getElementById('btn-arrow').onclick = (e) => {
        canvas.isDrawingMode = false;
        canvas.selection = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        document.getElementById('btn-select').style.background = '#3b82f6';
        e.target.style.background = '#fbbf24';
        isDrawingArrow = true;
    };

    // Zooming (Mouse Wheel)
    canvas.on('mouse:wheel', function(opt) {
        var delta = opt.e.deltaY;
        var zoom = canvas.getZoom();
        zoom *= 0.999 ** delta;
        if (zoom > 20) zoom = 20;
        if (zoom < 0.05) zoom = 0.05;
        canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
        opt.e.preventDefault();
        opt.e.stopPropagation();
        syncGridToCamera();
    });

    // Panning and Arrow Logic
    canvas.on('mouse:down', function(opt) {
        var evt = opt.e;
        if (isDrawingArrow) {
            isHistoryProcessing = true;
            const pointer = canvas.getPointer(evt);
            const points = [pointer.x, pointer.y, pointer.x, pointer.y];
            const color = document.getElementById('brush-color').value;
            const thickness = parseInt(document.getElementById('brush-size').value, 10) || 4;
            
            arrowLine = new fabric.Line(points, {
                strokeWidth: thickness, fill: color, stroke: color, originX: 'center', originY: 'center', selectable: false
            });
            arrowHead = new fabric.Triangle({
                width: thickness * 4, height: thickness * 4, fill: color, left: pointer.x, top: pointer.y, originX: 'center', originY: 'center', selectable: false, angle: 90
            });
            canvas.add(arrowLine, arrowHead);
            return;
        }

        // --- NEW LASSO LOGIC ---
        if (isLassoMode) {
            const pointer = canvas.getPointer(evt);
            
            // Check for poly snap close
            if (!document.getElementById('lasso-freehand-toggle').checked && lassoPoints.length >= 3) {
                let dx = pointer.x - lassoPoints[0].x;
                let dy = pointer.y - lassoPoints[0].y;
                if (Math.sqrt(dx*dx + dy*dy) < 15) {
                    let p1 = lassoPoints[lassoPoints.length - 1];
                    let p2 = lassoPoints[0];
                    let line = new fabric.Line([p1.x, p1.y, p2.x, p2.y], {
                        stroke: 'red', strokeWidth: 2, selectable: false, evented: false, excludeFromExport: true
                    });
                    lassoLines.push(line);
                    canvas.add(line);
                    if (window.lassoClosingLine) {
                        canvas.remove(window.lassoClosingLine);
                        window.lassoClosingLine = null;
                    }
                    canvas.renderAll();
                    document.getElementById('btn-lasso-finish').click();
                    return;
                }
            }

            if (document.getElementById('lasso-freehand-toggle').checked) {
                window.isLassoDrawing = true;
            }
            lassoPoints.push({x: pointer.x, y: pointer.y});
            
            // Draw a dot
            let circle = new fabric.Circle({
                radius: 3, fill: 'red', left: pointer.x, top: pointer.y,
                originX: 'center', originY: 'center', selectable: false, evented: false, excludeFromExport: true
            });
            lassoCircles.push(circle);
            canvas.add(circle);
            
            // Draw a line to the previous point
            if (lassoPoints.length > 1) {
                let p1 = lassoPoints[lassoPoints.length - 2];
                let p2 = lassoPoints[lassoPoints.length - 1];
                let line = new fabric.Line([p1.x, p1.y, p2.x, p2.y], {
                    stroke: 'red', strokeWidth: 2, selectable: false, evented: false, excludeFromExport: true
                });
                lassoLines.push(line);
                canvas.add(line);
            }
            return; // prevent panning/selecting
        }
        // --- END NEW LASSO LOGIC ---

        if (evt.altKey === true || evt.button === 1) {
            this.isDragging = true;
            this.selection = false;
            this.lastPosX = evt.clientX;
            this.lastPosY = evt.clientY;
            evt.preventDefault();
        }
    });
    
    canvas.on('mouse:move', function(opt) {
        if (isLassoMode) {
            const pointer = canvas.getPointer(opt.e);
            
            // Draw visual closing line back to start
            if (lassoPoints.length > 0) {
                if (!window.lassoClosingLine) {
                    window.lassoClosingLine = new fabric.Line([pointer.x, pointer.y, lassoPoints[0].x, lassoPoints[0].y], {
                        stroke: 'rgba(255,0,0,0.5)', strokeWidth: 1, strokeDashArray: [5, 5], selectable: false, evented: false, excludeFromExport: true
                    });
                    canvas.add(window.lassoClosingLine);
                } else {
                    window.lassoClosingLine.set({ x1: pointer.x, y1: pointer.y, x2: lassoPoints[0].x, y2: lassoPoints[0].y });
                }
            }
            
            // Freehand mode tracing
            if (window.isLassoDrawing) {
                lassoPoints.push({x: pointer.x, y: pointer.y});
                let p1 = lassoPoints[lassoPoints.length - 2];
                let p2 = lassoPoints[lassoPoints.length - 1];
                let line = new fabric.Line([p1.x, p1.y, p2.x, p2.y], {
                    stroke: 'red', strokeWidth: 2, selectable: false, evented: false, excludeFromExport: true
                });
                lassoLines.push(line);
                canvas.add(line);
            }
            canvas.renderAll();
            return;
        }

        if (isDrawingArrow && arrowLine) {
            const pointer = canvas.getPointer(opt.e);
            arrowLine.set({ x2: pointer.x, y2: pointer.y });
            arrowHead.set({ left: pointer.x, top: pointer.y });
            
            const dx = pointer.x - arrowLine.x1;
            const dy = pointer.y - arrowLine.y1;
            let angle = Math.atan2(dy, dx) * 180 / Math.PI;
            arrowHead.set({ angle: angle + 90 });
            canvas.renderAll();
            return;
        }

        if (this.isDragging) {
            var e = opt.e;
            var vpt = this.viewportTransform;
            vpt[4] += e.clientX - this.lastPosX;
            vpt[5] += e.clientY - this.lastPosY;
            this.requestRenderAll();
            this.lastPosX = e.clientX;
            this.lastPosY = e.clientY;
            syncGridToCamera();
        }
    });
    
    canvas.on('mouse:up', function(opt) {
        if (isLassoMode && window.isLassoDrawing) {
            window.isLassoDrawing = false;
            
            // Snappy point: automatically close the loop
            if (lassoPoints.length > 2) {
                let p1 = lassoPoints[lassoPoints.length - 1];
                let p2 = lassoPoints[0];
                let line = new fabric.Line([p1.x, p1.y, p2.x, p2.y], {
                    stroke: 'red', strokeWidth: 2, selectable: false, evented: false, excludeFromExport: true
                });
                lassoLines.push(line);
                canvas.add(line);
            }
            if (window.lassoClosingLine) {
                canvas.remove(window.lassoClosingLine);
                window.lassoClosingLine = null;
            }
            canvas.renderAll();
            
            return;
        }

        if (isDrawingArrow && arrowLine && arrowHead) {
            if (arrowLine.x1 !== arrowLine.x2 || arrowLine.y1 !== arrowLine.y2) {
                arrowLine.set({selectable: true});
                arrowHead.set({selectable: true});
                const group = new fabric.Group([arrowLine, arrowHead], { selectable: true });
                canvas.remove(arrowLine, arrowHead);
                canvas.add(group);
                canvas.setActiveObject(group);
                isHistoryProcessing = false;
                saveHistory();
            } else {
                canvas.remove(arrowLine, arrowHead);
                isHistoryProcessing = false;
            }
            arrowLine = null;
            arrowHead = null;
            
            isDrawingArrow = false;
            document.getElementById('btn-arrow').style.background = '#3b82f6';
            document.getElementById('btn-select').click();
            return;
        }

        this.setViewportTransform(this.viewportTransform);
        this.isDragging = false;
        this.selection = true;
    });

    // Save Logic
    document.getElementById('btn-save').onclick = async (e) => {
        const btn = e.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '💾 Saving...';
        btn.disabled = true;

        const jsonData = JSON.stringify(canvas.toJSON());
        const base64Image = canvas.toDataURL('png');

        try {
            const res = await fetch('/api/save_blueprint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    backlogid: <?= $backlogid ?>,
                    canvas_data: jsonData,
                    image: base64Image
                })
            });
            const result = await res.json();
            if (result.success) {
                btn.innerHTML = '✅ Saved!';
                lastSavedHistoryIndex = historyIndex;
                checkUnsavedChanges();
                setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 2000);
            } else {
                alert('Failed to save!');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        } catch (err) {
            alert('Error communicating with server.');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    };
    // Toggle Sidebar Visibility
    document.getElementById('btn-paint-plan').onclick = () => {
        const sidebar = document.getElementById('paint-sidebar');
        sidebar.style.display = sidebar.style.display === 'none' ? 'flex' : 'none';
    };

    document.getElementById('btn-close-paints').onclick = () => {
        document.getElementById('paint-sidebar').style.display = 'none';
    };

    // Add Paint Label to Canvas
    function addPaintLabelToCanvas(text, bgColor, imagepath, finish) {
        canvas.isDrawingMode = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        document.getElementById('btn-select').style.background = '#fbbf24';

        let hex = bgColor || '#1f2937';
        let cardWidth = 180;
        let imgHeight = 180;

        let titleText = new fabric.Textbox(text, {
            width: cardWidth - 24,
            fontSize: 14,
            fontFamily: 'sans-serif',
            fontWeight: 'bold',
            fill: '#111111',
            textAlign: 'left',
            originX: 'center', originY: 'top',
            left: 0,
            top: imgHeight + 12,
            splitByGrapheme: false
        });

        let subText = new fabric.Text((finish ? finish + ' | ' : '') + hex.toUpperCase(), {
            fontSize: 11,
            fontFamily: 'sans-serif',
            fill: '#666666',
            originX: 'left', originY: 'top',
            left: -cardWidth / 2 + 12,
            top: titleText.top + titleText.height + 6
        });

        let textTotalHeight = titleText.height + subText.height + 30;
        let cardHeight = imgHeight + textTotalHeight;

        titleText.set('top', titleText.top - cardHeight / 2);
        subText.set('top', subText.top - cardHeight / 2);

        let shadowRect = new fabric.Rect({
            width: cardWidth, height: cardHeight,
            fill: '#ffffff',
            rx: 12, ry: 12,
            originX: 'center', originY: 'center',
            shadow: new fabric.Shadow({
                color: 'rgba(0,0,0,0.15)',
                blur: 12, offsetX: 0, offsetY: 6
            })
        });

        let topColor = new fabric.Rect({
            width: cardWidth, height: imgHeight,
            fill: hex,
            originX: 'center', originY: 'top',
            top: -cardHeight / 2
        });

        let clipRect = new fabric.Rect({
            width: cardWidth, height: cardHeight,
            rx: 12, ry: 12,
            originX: 'center', originY: 'center'
        });

        function addGroupToCanvas(items) {
            let contentGroup = new fabric.Group(items, {
                originX: 'center', originY: 'center',
                clipPath: clipRect
            });

            let finalGroup = new fabric.Group([shadowRect, contentGroup], {
                left: canvas.width / 2 - cardWidth / 2,
                top: canvas.height / 2 - cardHeight / 2
            });

            canvas.add(finalGroup);
            canvas.setActiveObject(finalGroup);
            saveHistory();
        }

        if (imagepath) {
            fabric.Image.fromURL(imagepath, function(img, isError) {
                if (!isError && img) {
                    let scale = Math.max(cardWidth / img.width, imgHeight / img.height);
                    img.set({
                        originX: 'center', originY: 'top',
                        left: 0, top: -cardHeight / 2,
                        scaleX: scale, scaleY: scale
                    });
                    addGroupToCanvas([topColor, img, titleText, subText]);
                } else {
                    addGroupToCanvas([topColor, titleText, subText]);
                }
            });
        } else {
            addGroupToCanvas([topColor, titleText, subText]);
        }
    }

    // Smart Match Logic
    document.getElementById('btn-smart-match').addEventListener('click', () => {
        let activeObj = canvas.getActiveObject();
        if (!activeObj) {
            alert('Please select an object on the canvas first to pick a color.');
            return;
        }
        
        let targetR, targetG, targetB;

        if (activeObj.type === 'image') {
            let imgElement = activeObj.getElement();
            if (!imgElement) {
                alert("Cannot extract color from this image.");
                return;
            }
            let tempCanvas = document.createElement('canvas');
            let ctx = tempCanvas.getContext('2d', { willReadFrequently: true });
            tempCanvas.width = imgElement.naturalWidth || imgElement.width || activeObj.width;
            tempCanvas.height = imgElement.naturalHeight || imgElement.height || activeObj.height;
            ctx.drawImage(imgElement, 0, 0, tempCanvas.width, tempCanvas.height);
            
            try {
                let imgData = ctx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
                let pixels = imgData.data;
                let sumR = 0, sumG = 0, sumB = 0, count = 0;
                
                for (let i = 0; i < pixels.length; i += 4) {
                    if (pixels[i + 3] > 10) { // Ignore transparent or almost-transparent pixels
                        sumR += pixels[i];
                        sumG += pixels[i + 1];
                        sumB += pixels[i + 2];
                        count++;
                    }
                }
                
                if (count > 0) {
                    targetR = Math.round(sumR / count);
                    targetG = Math.round(sumG / count);
                    targetB = Math.round(sumB / count);
                } else {
                    alert("Image is completely transparent. Cannot extract color.");
                    return;
                }
            } catch (e) {
                alert("Could not extract pixel data. The image might be cross-origin tainted.");
                return;
            }
        } else {
            let targetColor = null;
            if (activeObj.fill && typeof activeObj.fill === 'string' && activeObj.fill.startsWith('#') && activeObj.fill !== '#00000000' && activeObj.fill !== 'transparent') {
                targetColor = activeObj.fill;
            } else if (activeObj.stroke && typeof activeObj.stroke === 'string' && activeObj.stroke.startsWith('#')) {
                targetColor = activeObj.stroke;
            } else {
                alert("Selected object doesn't have a solid hex fill or stroke color, nor is it an image.");
                return;
            }

            targetR = parseInt(targetColor.slice(1, 3), 16);
            targetG = parseInt(targetColor.slice(3, 5), 16);
            targetB = parseInt(targetColor.slice(5, 7), 16);
        }

        let bestMatch = null;
        let bestDist = Infinity;

        ALL_PAINTS.forEach(paint => {
            if (paint.color_hex) {
                let r = parseInt(paint.color_hex.slice(1, 3), 16);
                let g = parseInt(paint.color_hex.slice(3, 5), 16);
                let b = parseInt(paint.color_hex.slice(5, 7), 16);

                let dist = Math.sqrt(Math.pow(r - targetR, 2) + Math.pow(g - targetG, 2) + Math.pow(b - targetB, 2));
                if (dist < bestDist) {
                    bestDist = dist;
                    bestMatch = paint;
                }
            }
        });

        if (bestMatch) {
            document.getElementById('paint-search').value = bestMatch.name;
            const event = new Event('input');
            document.getElementById('paint-search').dispatchEvent(event);
        } else {
            alert("No paints with assigned color_hex found in inventory.");
        }
    });

    canvas.on('selection:created', toggleSmartMatch);
    canvas.on('selection:updated', toggleSmartMatch);
    canvas.on('selection:cleared', toggleSmartMatch);

    function toggleSmartMatch() {
        let activeObj = canvas.getActiveObject();
        let btn = document.getElementById('btn-smart-match');
        let hasColor = false;
        
        if (activeObj) {
            if (activeObj.type === 'image') {
                hasColor = true;
            } else if (activeObj.fill && typeof activeObj.fill === 'string' && activeObj.fill.startsWith('#') && activeObj.fill !== '#00000000' && activeObj.fill !== 'transparent') {
                hasColor = true;
            } else if (activeObj.stroke && typeof activeObj.stroke === 'string' && activeObj.stroke.startsWith('#')) {
                hasColor = true;
            }
        }

        if (hasColor) {
            btn.style.display = 'block';
        } else {
            btn.style.display = 'none';
        }
    }

    // Paint Search Filter
    document.getElementById('paint-search').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        const chips = document.querySelectorAll('.paint-chip');
        chips.forEach(chip => {
            const text = chip.innerText.toLowerCase();
            if (text.includes(query)) {
                chip.style.display = 'block';
            } else {
                chip.style.display = 'none';
            }
        });
    });
</script>

</body>
</html>
