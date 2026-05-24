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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blueprint Editor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <style>
        body { margin: 0; overflow: hidden; background: #f3f4f6; font-family: sans-serif; }
        #desktop-ui { display: flex; flex-direction: column; height: 100vh; }
        #mobile-warning { display: none; text-align: center; padding: 50px; font-size: 1.5rem; color: #374151; }
        #canvas-container { flex-grow: 1; display: flex; justify-content: center; align-items: center; background: #e5e7eb; overflow: hidden; }
        .canvas-container { background: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .toolbar { padding: 10px; background: #1f2937; color: white; display: flex; gap: 10px; align-items: center; }
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
        <a href="/backlog" class="btn" style="background:#4b5563;">⬅ Back</a>
        <button class="btn" id="btn-draw">🖌️ Free Draw</button>
        <input type="color" id="brush-color" value="#000000" class="h-8 w-10 border-0 cursor-pointer" title="Brush Color">
        <input type="range" id="brush-size" min="1" max="50" value="5" class="w-24 cursor-pointer" title="Brush Size">
        <button class="btn" id="btn-text">📝 Add Text</button>
        <button class="btn" style="background:#eab308; color:black;" id="btn-sticky">🟨 Sticky Note</button>
        <button class="btn" id="btn-rect">🟦 Add Box</button>
        <button class="btn danger" id="btn-delete">🗑️ Delete</button>
        <button class="btn" id="btn-undo" title="Ctrl+Z">↩️ Undo</button>
        <button class="btn" id="btn-redo" title="Ctrl+Y">↪️ Redo</button>
        <button class="btn save" id="btn-save">💾 Save Blueprint</button>
    </div>
    
    <div id="canvas-container">
        <canvas id="c"></canvas>
    </div>
</div>

<script>
    // Initialize Canvas
    const container = document.getElementById('canvas-container');
    const canvas = new fabric.Canvas('c', {
        width: container.clientWidth - 40,
        height: container.clientHeight - 40,
        isDrawingMode: false,
        backgroundColor: 'white',
        fireMiddleClick: true
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

    function saveHistory() {
        if (isHistoryProcessing) return;
        if (historyIndex < history.length - 1) {
            history = history.slice(0, historyIndex + 1);
        }
        history.push(JSON.stringify(canvas));
        historyIndex++;
    }

    function undo() {
        if (historyIndex > 0) {
            isHistoryProcessing = true;
            historyIndex--;
            canvas.loadFromJSON(history[historyIndex], () => {
                canvas.renderAll();
                isHistoryProcessing = false;
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
            });
        }
    }

    document.getElementById('btn-undo').onclick = undo;
    document.getElementById('btn-redo').onclick = redo;

    canvas.on('object:added', saveHistory);
    canvas.on('object:modified', saveHistory);
    canvas.on('object:removed', saveHistory);

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undo();
        }
        if (e.ctrlKey && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            redo();
        }
    });

    // Load Existing Data
    const existingData = <?= $saved_data ?: 'null' ?>;
    if (existingData) {
        isHistoryProcessing = true;
        canvas.loadFromJSON(existingData, () => {
            canvas.renderAll();
            isHistoryProcessing = false;
            saveHistory();
        });
    } else {
        saveHistory();
    }

    // Tools
    document.getElementById('btn-draw').onclick = (e) => {
        canvas.isDrawingMode = !canvas.isDrawingMode;
        e.target.style.background = canvas.isDrawingMode ? '#fbbf24' : '#3b82f6';
    };

    document.getElementById('brush-color').addEventListener('change', function() {
        canvas.freeDrawingBrush.color = this.value;
    });
    document.getElementById('brush-size').addEventListener('input', function() {
        canvas.freeDrawingBrush.width = parseInt(this.value, 10);
    });
    
    // Set initial brush settings
    canvas.freeDrawingBrush.color = '#000000';
    canvas.freeDrawingBrush.width = 5;

    document.getElementById('btn-text').onclick = () => {
        canvas.isDrawingMode = false;
        document.getElementById('btn-draw').style.background = '#3b82f6';
        
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

    document.getElementById('btn-rect').onclick = () => {
        canvas.isDrawingMode = false;
        const rect = new fabric.Rect({ left: 100, top: 100, fill: 'transparent', stroke: 'red', strokeWidth: 2, width: 100, height: 100 });
        canvas.add(rect);
        canvas.setActiveObject(rect);
    };

    document.getElementById('btn-delete').onclick = () => {
        const active = canvas.getActiveObjects();
        if (active.length) {
            canvas.discardActiveObject();
            active.forEach((obj) => canvas.remove(obj));
        }
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
    });

    // Panning (Middle Click or Alt + Drag)
    canvas.on('mouse:down', function(opt) {
        var evt = opt.e;
        if (evt.altKey === true || evt.button === 1) {
            this.isDragging = true;
            this.selection = false;
            this.lastPosX = evt.clientX;
            this.lastPosY = evt.clientY;
            evt.preventDefault();
        }
    });
    
    canvas.on('mouse:move', function(opt) {
        if (this.isDragging) {
            var e = opt.e;
            var vpt = this.viewportTransform;
            vpt[4] += e.clientX - this.lastPosX;
            vpt[5] += e.clientY - this.lastPosY;
            this.requestRenderAll();
            this.lastPosX = e.clientX;
            this.lastPosY = e.clientY;
        }
    });
    
    canvas.on('mouse:up', function(opt) {
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
</script>

</body>
</html>
