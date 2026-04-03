<?php
/**
 * Bethesda Mod Editor v2.0 - Complete Web-Based Mod Toolkit
 * Supports: ZIP/JAR archives, folder drag & drop
 * Parses: .esp/.esm/.esl, .pex, .psc, .nif, .dds, .bsa, .ba2, .seq
 * 100% client-side mod handling - PHP just serves the page
 */
$dirs = ['uploads', 'temp', 'cache'];
foreach ($dirs as $d) { if (!is_dir(__DIR__."/$d")) @mkdir(__DIR__."/$d", 0755, true); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Bethesda Mod Editor v2.0</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/monokai.css" rel="stylesheet">
<style>
:root {
    --bg-primary: #0d1117;
    --bg-secondary: #161b22;
    --bg-tertiary: #1c2333;
    --bg-hover: #21262d;
    --border-color: #30363d;
    --text-primary: #e6edf3;
    --text-secondary: #8b949e;
    --accent-gold: #d4a843;
    --accent-blue: #58a6ff;
    --accent-green: #3fb950;
    --accent-red: #f85149;
    --accent-purple: #bc8cff;
    --accent-orange: #d29922;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { background:var(--bg-primary); color:var(--text-primary); font-family:'Segoe UI',system-ui,-apple-system,sans-serif; height:100vh; overflow:hidden; display:flex; flex-direction:column; }
::-webkit-scrollbar { width:8px; height:8px; }
::-webkit-scrollbar-track { background:var(--bg-secondary); }
::-webkit-scrollbar-thumb { background:#444c56; border-radius:4px; }
::-webkit-scrollbar-thumb:hover { background:#555d66; }

/* Navbar */
#navbar { background:var(--bg-secondary); border-bottom:1px solid var(--border-color); padding:8px 16px; display:flex; align-items:center; gap:12px; flex-shrink:0; z-index:100; }
#navbar .brand { display:flex; align-items:center; gap:8px; font-weight:600; font-size:15px; color:var(--accent-gold); }
#navbar .brand i { font-size:18px; }
#navbar .mod-name { color:var(--text-secondary); font-size:13px; margin-left:8px; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#navbar .actions { margin-left:auto; display:flex; gap:6px; }
.btn-nav { background:var(--bg-tertiary); border:1px solid var(--border-color); color:var(--text-primary); padding:5px 12px; border-radius:6px; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:5px; transition:all 0.2s; }
.btn-nav:hover { background:var(--bg-hover); border-color:#555; }
.btn-nav.primary { background:#1f6feb; border-color:#1f6feb; }
.btn-nav.primary:hover { background:#388bfd; }
.btn-nav.success { background:#238636; border-color:#238636; }
.btn-nav.success:hover { background:#2ea043; }
.btn-nav.danger { background:#da3633; border-color:#da3633; }
.btn-nav.danger:hover { background:#f85149; }

/* Main layout */
#main-container { display:flex; flex:1; overflow:hidden; }

/* Welcome screen */
#welcome-screen { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; padding:40px; }
#drop-zone { border:2px dashed var(--border-color); border-radius:16px; padding:60px 80px; text-align:center; transition:all 0.3s; cursor:pointer; background:var(--bg-secondary); max-width:600px; width:100%; }
#drop-zone.drag-over { border-color:var(--accent-gold); background:rgba(212,168,67,0.05); transform:scale(1.02); }
#drop-zone .icon { font-size:64px; color:var(--accent-gold); margin-bottom:16px; }
#drop-zone h3 { margin-bottom:8px; }
#drop-zone p { color:var(--text-secondary); font-size:14px; margin-bottom:16px; }
#drop-zone .formats { color:var(--text-secondary); font-size:12px; display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
#drop-zone .formats span { background:var(--bg-tertiary); padding:2px 8px; border-radius:4px; border:1px solid var(--border-color); }

/* Editor layout */
#editor-layout { display:none; flex:1; overflow:hidden; }

/* Sidebar file tree */
#sidebar { width:280px; min-width:200px; max-width:500px; background:var(--bg-secondary); border-right:1px solid var(--border-color); display:flex; flex-direction:column; flex-shrink:0; overflow:hidden; }
#sidebar .header { padding:10px 12px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600; text-transform:uppercase; color:var(--text-secondary); letter-spacing:0.5px; }
#tree-search { background:var(--bg-tertiary); border:1px solid var(--border-color); color:var(--text-primary); padding:5px 10px; border-radius:4px; font-size:12px; width:100%; margin:8px 10px; box-sizing:border-box; width:calc(100% - 20px); }
#tree-search:focus { outline:none; border-color:var(--accent-blue); }
#file-tree { flex:1; overflow-y:auto; padding:4px 0; }

/* Tree items */
.tree-folder, .tree-file { display:flex; align-items:center; padding:3px 8px; padding-left:var(--indent, 12px); cursor:pointer; font-size:13px; user-select:none; transition:background 0.1s; white-space:nowrap; }
.tree-folder:hover, .tree-file:hover { background:var(--bg-hover); }
.tree-file.active { background:rgba(88,166,255,0.1); color:var(--accent-blue); }
.tree-file.modified::after { content:'●'; color:var(--accent-orange); margin-left:auto; font-size:10px; padding-right:8px; }
.tree-folder .arrow { width:16px; text-align:center; font-size:10px; color:var(--text-secondary); flex-shrink:0; transition:transform 0.15s; }
.tree-folder.collapsed .arrow { transform:rotate(-90deg); }
.tree-folder .folder-icon { margin:0 6px; color:var(--accent-gold); font-size:13px; flex-shrink:0; }
.tree-file .file-icon { margin:0 6px; margin-left:22px; font-size:13px; flex-shrink:0; }
.tree-folder .name, .tree-file .name { overflow:hidden; text-overflow:ellipsis; }
.tree-folder .count { margin-left:auto; color:var(--text-secondary); font-size:11px; padding-right:8px; }
.tree-children { overflow:hidden; }
.tree-folder.collapsed + .tree-children { display:none; }

/* File type colors */
.ft-esp { color:var(--accent-gold); }
.ft-esm { color:var(--accent-orange); }
.ft-esl { color:#e0a040; }
.ft-pex { color:var(--accent-green); }
.ft-psc { color:#7ee787; }
.ft-nif { color:var(--accent-blue); }
.ft-dds { color:var(--accent-purple); }
.ft-bsa, .ft-ba2 { color:var(--accent-orange); }
.ft-seq { color:var(--text-secondary); }
.ft-txt, .ft-cfg, .ft-ini, .ft-json, .ft-xml { color:var(--text-primary); }
.ft-swf { color:var(--accent-red); }
.ft-default { color:var(--text-secondary); }

/* Content area */
#content-area { flex:1; display:flex; flex-direction:column; overflow:hidden; }
#tab-bar { display:flex; background:var(--bg-secondary); border-bottom:1px solid var(--border-color); padding:0 8px; flex-shrink:0; overflow-x:auto; }
.tab-item { padding:8px 16px; font-size:12px; color:var(--text-secondary); cursor:pointer; border-bottom:2px solid transparent; display:flex; align-items:center; gap:6px; white-space:nowrap; transition:all 0.15s; }
.tab-item:hover { color:var(--text-primary); background:var(--bg-hover); }
.tab-item.active { color:var(--text-primary); border-bottom-color:var(--accent-blue); }
#content-panels { flex:1; overflow:auto; }
.panel { display:none; height:100%; overflow:auto; }
.panel.active { display:flex; flex-direction:column; }

/* Overview panel */
#overview-panel { padding:24px; }
.overview-header { display:flex; align-items:center; gap:16px; margin-bottom:24px; }
.overview-header .mod-icon { width:64px; height:64px; background:linear-gradient(135deg, #d4a843, #8b6914); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:28px; }
.overview-header h2 { font-size:20px; margin-bottom:4px; }
.overview-header .sub { color:var(--text-secondary); font-size:13px; }
.overview-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:24px; }
.stat-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:8px; padding:16px; }
.stat-card .label { font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; }
.stat-card .value { font-size:22px; font-weight:600; }
.stat-card .detail { font-size:12px; color:var(--text-secondary); margin-top:2px; }
.section-title { font-size:14px; font-weight:600; margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid var(--border-color); }
.masters-list { list-style:none; padding:0; }
.masters-list li { padding:6px 12px; background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:6px; margin-bottom:4px; font-size:13px; display:flex; align-items:center; gap:8px; }
.masters-list li i { color:var(--accent-gold); }
.records-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:6px; }
.record-badge { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:6px; padding:8px 10px; font-size:12px; display:flex; justify-content:space-between; }
.record-badge .type { font-weight:600; font-family:monospace; }
.record-badge .cnt { color:var(--text-secondary); }
.file-breakdown { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:8px; }
.breakdown-item { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:6px; }
.breakdown-item .bi-icon { font-size:20px; width:32px; text-align:center; }
.breakdown-item .bi-info { font-size:12px; }
.breakdown-item .bi-info .bi-count { font-weight:600; font-size:14px; }

/* Editor panel */
#editor-panel { display:flex; flex-direction:column; }
#editor-panel .editor-toolbar { padding:8px 12px; background:var(--bg-secondary); border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:8px; font-size:12px; flex-shrink:0; }
#editor-panel .editor-toolbar .filepath { color:var(--text-secondary); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#editor-panel .CodeMirror { flex:1; height:auto !important; font-size:13px; }

/* Hex panel */
#hex-panel { font-family:'Consolas','Courier New',monospace; font-size:13px; }
.hex-toolbar { padding:8px 12px; background:var(--bg-secondary); border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:8px; font-size:12px; flex-shrink:0; }
.hex-container { flex:1; overflow:auto; padding:8px; }
.hex-row { display:flex; line-height:1.6; }
.hex-offset { color:var(--accent-blue); width:80px; flex-shrink:0; }
.hex-bytes { flex:1; color:var(--text-primary); word-spacing:4px; }
.hex-ascii { color:var(--accent-green); width:180px; flex-shrink:0; margin-left:16px; white-space:pre; }
.hex-highlight { background:rgba(212,168,67,0.2); color:var(--accent-gold); }

/* Preview panel */
#preview-panel { display:flex; flex-direction:column; align-items:center; justify-content:center; }
#preview-panel canvas { max-width:100%; border:1px solid var(--border-color); border-radius:8px; }
.preview-info { padding:16px; text-align:center; color:var(--text-secondary); font-size:13px; }
.preview-info table { margin:12px auto; text-align:left; font-size:12px; }
.preview-info table td { padding:3px 12px; }
.preview-info table td:first-child { color:var(--text-secondary); }
.preview-info table td:last-child { color:var(--text-primary); font-family:monospace; }

/* Properties panel */
#props-panel { padding:16px; }
.prop-group { margin-bottom:16px; }
.prop-group h6 { font-size:12px; font-weight:600; text-transform:uppercase; color:var(--text-secondary); letter-spacing:0.5px; margin-bottom:8px; padding-bottom:4px; border-bottom:1px solid var(--border-color); }
.prop-row { display:flex; padding:4px 0; font-size:13px; }
.prop-row .prop-key { width:140px; color:var(--text-secondary); flex-shrink:0; }
.prop-row .prop-val { color:var(--text-primary); word-break:break-all; }

/* Status bar */
#statusbar { background:var(--bg-secondary); border-top:1px solid var(--border-color); padding:3px 12px; display:flex; align-items:center; gap:16px; font-size:11px; color:var(--text-secondary); flex-shrink:0; }
#statusbar .sep { width:1px; height:12px; background:var(--border-color); }

/* Loading overlay */
#loading { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:9999; flex-direction:column; align-items:center; justify-content:center; }
#loading.show { display:flex; }
#loading .spinner { width:40px; height:40px; border:3px solid var(--border-color); border-top-color:var(--accent-gold); border-radius:50%; animation:spin 0.8s linear infinite; margin-bottom:16px; }
@keyframes spin { to { transform:rotate(360deg); } }
#loading .msg { color:var(--text-primary); font-size:14px; }
#loading .sub-msg { color:var(--text-secondary); font-size:12px; margin-top:4px; }

/* Toast */
.toast-msg { position:fixed; bottom:20px; right:20px; background:var(--bg-tertiary); border:1px solid var(--border-color); color:var(--text-primary); padding:10px 16px; border-radius:8px; font-size:13px; z-index:10000; animation:toastIn 0.3s ease; display:flex; align-items:center; gap:8px; max-width:400px; }
@keyframes toastIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

/* Resize handle */
#resize-handle { width:4px; cursor:col-resize; background:transparent; transition:background 0.2s; flex-shrink:0; }
#resize-handle:hover, #resize-handle.dragging { background:var(--accent-blue); }

/* Options Popup */
.options-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:5000; backdrop-filter:blur(4px); }
.options-overlay.show { display:flex; align-items:center; justify-content:center; }
.options-popup { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:32px; max-width:520px; width:90%; box-shadow:0 24px 48px rgba(0,0,0,0.5); }
.options-popup h3 { margin-bottom:24px; font-size:18px; display:flex; align-items:center; gap:10px; }
.options-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; }
.option-card { background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:12px; padding:20px 12px; text-align:center; cursor:pointer; transition:all 0.25s; }
.option-card:hover { border-color:var(--accent-blue); background:var(--bg-hover); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.3); }
.option-card svg { width:36px; height:36px; margin-bottom:10px; }
.option-card .opt-label { font-size:12px; color:var(--text-secondary); font-weight:500; }
.option-card.active { border-color:var(--accent-green); }

/* AI Panel */
.ai-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:6000; backdrop-filter:blur(4px); }
.ai-overlay.show { display:flex; align-items:center; justify-content:center; }
.ai-panel { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; width:90%; max-width:700px; max-height:85vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 48px rgba(0,0,0,0.5); }
.ai-panel-header { padding:16px 20px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:12px; flex-shrink:0; }
.ai-panel-header h4 { flex:1; margin:0; font-size:16px; display:flex; align-items:center; gap:8px; }
.ai-panel-header .close-ai { background:none; border:none; color:var(--text-secondary); cursor:pointer; font-size:18px; padding:4px 8px; border-radius:6px; transition:all 0.2s; }
.ai-panel-header .close-ai:hover { color:var(--text-primary); background:var(--bg-hover); }
.ai-panel-body { flex:1; overflow-y:auto; padding:20px; }
.ai-section { margin-bottom:20px; }
.ai-section h6 { font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-secondary); margin-bottom:10px; }
.ai-input-group { display:flex; gap:8px; align-items:center; }
.ai-input { background:var(--bg-tertiary); border:1px solid var(--border-color); color:var(--text-primary); padding:8px 12px; border-radius:8px; font-size:13px; flex:1; }
.ai-input:focus { outline:none; border-color:var(--accent-blue); }
.ai-select { background:var(--bg-tertiary); border:1px solid var(--border-color); color:var(--text-primary); padding:8px 12px; border-radius:8px; font-size:13px; width:100%; }
.ai-select:focus { outline:none; border-color:var(--accent-blue); }
.ai-btn { padding:8px 16px; border-radius:8px; border:1px solid var(--border-color); cursor:pointer; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
.ai-btn-primary { background:#1f6feb; border-color:#1f6feb; color:#fff; }
.ai-btn-primary:hover { background:#388bfd; }
.ai-btn-success { background:#238636; border-color:#238636; color:#fff; }
.ai-btn-success:hover { background:#2ea043; }
.ai-btn-outline { background:transparent; border-color:var(--border-color); color:var(--text-primary); }
.ai-btn-outline:hover { background:var(--bg-hover); }
.ai-status { display:inline-flex; align-items:center; gap:6px; font-size:12px; padding:4px 10px; border-radius:20px; }
.ai-status.connected { background:rgba(63,185,80,0.15); color:var(--accent-green); }
.ai-status.disconnected { background:rgba(248,81,73,0.15); color:var(--accent-red); }
.ai-chat { margin-top:16px; }
.ai-chat-messages { max-height:400px; overflow-y:auto; padding:12px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:10px; margin-bottom:12px; }
.ai-msg { margin-bottom:12px; padding:10px 14px; border-radius:10px; font-size:13px; line-height:1.6; }
.ai-msg.user { background:rgba(31,111,235,0.15); border:1px solid rgba(31,111,235,0.2); margin-left:40px; }
.ai-msg.assistant { background:var(--bg-tertiary); border:1px solid var(--border-color); margin-right:20px; }
.ai-msg .msg-role { font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-secondary); margin-bottom:4px; }
.ai-msg pre { background:var(--bg-primary); padding:10px; border-radius:6px; overflow-x:auto; margin:8px 0; font-size:12px; border:1px solid var(--border-color); }
.ai-msg code { font-family:'Consolas','Courier New',monospace; font-size:12px; }
.ai-msg p { margin:4px 0; }
.ai-chat-input { display:flex; gap:8px; }
.ai-chat-input input { flex:1; }
.ai-key-hidden { display:none; }
.ai-key-hidden.show { display:block; margin-top:10px; }

/* Green Blinking Notification */
.ai-notif { position:fixed; bottom:70px; right:20px; z-index:4000; width:44px; height:44px; border-radius:50%; background:var(--accent-green); border:none; cursor:pointer; display:none; align-items:center; justify-content:center; box-shadow:0 4px 16px rgba(63,185,80,0.4); animation:aiBlink 1.5s ease-in-out infinite; transition:transform 0.2s; }
.ai-notif:hover { transform:scale(1.1); }
.ai-notif.show { display:flex; }
.ai-notif svg { width:22px; height:22px; fill:#fff; }
@keyframes aiBlink { 0%,100% { box-shadow:0 4px 16px rgba(63,185,80,0.4); } 50% { box-shadow:0 4px 24px rgba(63,185,80,0.8), 0 0 40px rgba(63,185,80,0.3); } }

/* AI Suggestions Popup */
.ai-suggestions-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:7000; backdrop-filter:blur(3px); }
.ai-suggestions-overlay.show { display:flex; align-items:center; justify-content:center; }
.ai-suggestions-panel { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; width:90%; max-width:650px; max-height:80vh; overflow-y:auto; padding:24px; box-shadow:0 24px 48px rgba(0,0,0,0.5); }
.ai-suggestions-panel h4 { margin-bottom:16px; display:flex; align-items:center; gap:10px; }
.ai-suggestion-card { background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:10px; padding:14px; margin-bottom:10px; cursor:pointer; transition:all 0.2s; }
.ai-suggestion-card:hover { border-color:var(--accent-blue); background:var(--bg-hover); }
.ai-suggestion-card .sug-title { font-weight:600; font-size:13px; margin-bottom:4px; }
.ai-suggestion-card .sug-desc { font-size:12px; color:var(--text-secondary); line-height:1.5; }
.ai-suggestion-card pre { background:var(--bg-primary); padding:8px; border-radius:6px; margin-top:8px; font-size:11px; overflow-x:auto; border:1px solid var(--border-color); }

/* Preview iframe */
#preview-iframe { width:100%; height:100%; border:none; border-radius:0; }

/* Responsive */
@media (max-width:768px) { #sidebar { width:200px; min-width:150px; } .options-grid { grid-template-columns:repeat(2, 1fr); } }

/* Script info panel */
.script-info { padding:16px; }
.script-info .header { display:flex; align-items:center; gap:12px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-color); }
.script-info .header i { font-size:24px; color:var(--accent-green); }
.script-info .header h5 { margin:0; font-size:16px; }
.script-info .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:16px; }
.script-info .info-card { background:var(--bg-tertiary); border:1px solid var(--border-color); border-radius:6px; padding:10px; }
.script-info .info-card .lbl { font-size:11px; color:var(--text-secondary); text-transform:uppercase; }
.script-info .info-card .val { font-size:13px; margin-top:2px; }
.string-table { max-height:300px; overflow-y:auto; }
.string-table table { width:100%; font-size:12px; }
.string-table table td { padding:3px 8px; border-bottom:1px solid var(--border-color); }
.string-table table td:first-child { color:var(--text-secondary); width:40px; }
</style>
</head>
<body>

<!-- Loading overlay -->
<div id="loading"><div class="spinner"></div><div class="msg">Loading...</div><div class="sub-msg"></div></div>

<!-- Navbar -->
<div id="navbar">
    <div class="brand"><i class="fas fa-dragon"></i> Bethesda Mod Editor <span style="font-size:10px;color:var(--text-secondary);margin-left:4px;">v2.0</span></div>
    <span id="nav-mod-name" class="mod-name"></span>
    <div class="actions">
        <button class="btn-nav" onclick="triggerImport()" title="Import ZIP ou dossier"><i class="fas fa-file-import"></i> Import</button>
        <button class="btn-nav" onclick="addFiles()" title="Ajouter des fichiers"><i class="fas fa-plus"></i> Add Files</button>
        <button class="btn-nav success" onclick="exportZip()" title="Exporter en ZIP"><i class="fas fa-download"></i> Export ZIP</button>
        <button class="btn-nav" onclick="showModInfo()" title="Infos du mod"><i class="fas fa-info-circle"></i></button>
        <button class="btn-nav" onclick="openOptions()" title="Options"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg> Options</button>
        <button class="btn-nav danger" onclick="closeMod()" title="Fermer le mod"><i class="fas fa-times"></i></button>
    </div>
</div>

<!-- Main container -->
<div id="main-container">
    <!-- Welcome / Drop zone -->
    <div id="welcome-screen">
        <div id="drop-zone">
            <div class="icon"><i class="fas fa-dragon"></i></div>
            <h3>Drop your mod here</h3>
            <p>Import a ZIP/JAR archive or drag a folder containing your mod files</p>
            <button class="btn-nav primary" style="font-size:14px;padding:8px 24px;margin-bottom:16px;" onclick="triggerImport()">
                <i class="fas fa-folder-open"></i> Browse Files
            </button>
            <div class="formats">
                <span>.esp</span><span>.esm</span><span>.esl</span><span>.bsa</span><span>.ba2</span>
                <span>.nif</span><span>.dds</span><span>.pex</span><span>.psc</span><span>.seq</span>
                <span>.zip</span><span>.jar</span><span>.rar</span><span>.7z</span><span>folders</span>
            </div>
        </div>
        <input type="file" id="import-input" style="display:none" multiple accept=".zip,.jar,.rar,.7z,.esp,.esm,.esl,.bsa,.ba2,.nif,.dds,.pex,.psc,.seq,.swf,.txt,.cfg,.ini,.json,.xml" webkitdirectory>
        <input type="file" id="import-zip-input" style="display:none" accept=".zip,.jar,.rar,.7z">
        <input type="file" id="add-files-input" style="display:none" multiple>
    </div>

    <!-- Editor layout (hidden until mod loaded) -->
    <div id="editor-layout">
        <!-- Sidebar -->
        <div id="sidebar">
            <div class="header"><i class="fas fa-folder-tree"></i> Explorer</div>
            <input type="text" id="tree-search" placeholder="Search files..." oninput="filterTree(this.value)">
            <div id="file-tree"></div>
        </div>
        <div id="resize-handle"></div>

        <!-- Content area -->
        <div id="content-area">
            <div id="tab-bar">
                <div class="tab-item active" data-panel="overview-panel" onclick="switchTab(this)"><i class="fas fa-home"></i> Overview</div>
                <div class="tab-item" data-panel="editor-panel" onclick="switchTab(this)"><i class="fas fa-code"></i> Editor</div>
                <div class="tab-item" data-panel="hex-panel" onclick="switchTab(this)"><i class="fas fa-memory"></i> Hex</div>
                <div class="tab-item" data-panel="preview-panel" onclick="switchTab(this)"><i class="fas fa-eye"></i> Preview</div>
                <div class="tab-item" data-panel="props-panel" onclick="switchTab(this)"><i class="fas fa-list"></i> Properties</div>
            </div>
            <div id="content-panels">
                <div id="overview-panel" class="panel active"></div>
                <div id="editor-panel" class="panel"></div>
                <div id="hex-panel" class="panel"></div>
                <div id="preview-panel" class="panel">
                    <iframe id="preview-iframe" src="https://pdf.celephe.com/3D.php" allow="fullscreen; autoplay"></iframe>
                </div>
                <div id="props-panel" class="panel"></div>
            </div>
        </div>
    </div>
</div>

<!-- Status bar -->
<div id="statusbar">
    <span id="sb-status">Ready</span>
    <span class="sep"></span>
    <span id="sb-files">0 files</span>
    <span class="sep"></span>
    <span id="sb-modified">0 modified</span>
    <span class="sep"></span>
    <span id="sb-size">0 B</span>
    <span class="sep"></span>
    <span id="sb-selected"></span>
</div>

<!-- Options Popup -->
<div class="options-overlay" id="options-overlay" onclick="if(event.target===this)closeOptions()">
    <div class="options-popup">
        <h3><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-gold)" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg> Options</h3>
        <div class="options-grid">
            <div class="option-card" onclick="closeOptions();openAIPanel()">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-blue)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 014 4v1a1 1 0 001 1h1a4 4 0 010 8h-1a1 1 0 00-1 1v1a4 4 0 01-8 0v-1a1 1 0 00-1-1H6a4 4 0 010-8h1a1 1 0 001-1V6a4 4 0 014-4z"/><circle cx="12" cy="12" r="2"/></svg>
                <div class="opt-label">Intelligence IA</div>
            </div>
            <div class="option-card" onclick="closeOptions();toast('Thème — bientôt disponible','info')">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-purple)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/><path d="M2 12h20"/></svg>
                <div class="opt-label">Thèmes</div>
            </div>
            <div class="option-card" onclick="closeOptions();toast('Raccourcis — bientôt disponible','info')">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h4M6 12h4M14 12h.01M18 12h.01M8 16h8"/></svg>
                <div class="opt-label">Raccourcis</div>
            </div>
            <div class="option-card" onclick="closeOptions();toast('Plugins — bientôt disponible','info')">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-orange)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                <div class="opt-label">Plugins</div>
            </div>
            <div class="option-card" onclick="closeOptions();toast('Export avancé — bientôt disponible','info')">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-red)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <div class="opt-label">Export Avancé</div>
            </div>
            <div class="option-card" onclick="closeOptions();toast('Paramètres — bientôt disponible','info')">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                <div class="opt-label">Paramètres</div>
            </div>
        </div>
    </div>
</div>

<!-- AI Panel -->
<div class="ai-overlay" id="ai-overlay" onclick="if(event.target===this)closeAIPanel()">
    <div class="ai-panel">
        <div class="ai-panel-header">
            <h4>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-blue)" stroke-width="2"><path d="M12 2a4 4 0 014 4v1a1 1 0 001 1h1a4 4 0 010 8h-1a1 1 0 00-1 1v1a4 4 0 01-8 0v-1a1 1 0 00-1-1H6a4 4 0 010-8h1a1 1 0 001-1V6a4 4 0 014-4z"/><circle cx="12" cy="12" r="2"/></svg>
                Intelligence IA — Groq
            </h4>
            <span class="ai-status disconnected" id="ai-status-badge">
                <svg width="10" height="10" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5" fill="currentColor"/></svg>
                <span id="ai-status-text">Désactivé</span>
            </span>
            <button class="close-ai" onclick="closeAIPanel()">&times;</button>
        </div>
        <div class="ai-panel-body">
            <div class="ai-section">
                <h6>Activation</h6>
                <button class="ai-btn ai-btn-primary" id="ai-activate-btn" onclick="toggleAIActivation()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    <span id="ai-activate-text">Activer IA</span>
                </button>
                <div class="ai-key-hidden" id="ai-key-section">
                    <h6 style="margin-top:16px">Clé API Groq</h6>
                    <div class="ai-input-group">
                        <input type="password" class="ai-input" id="ai-api-key" placeholder="Entrez votre clé API Groq (gsk_...)">
                        <button class="ai-btn ai-btn-outline" onclick="toggleKeyVisibility()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="ai-section">
                <h6>Modèle de Langage IA</h6>
                <select class="ai-select" id="ai-model-select">
                    <option value="qwen/qwen3-32b">Qwen3 32B</option>
                    <option value="meta-llama/llama-4-scout-17b-16e-instruct">Llama 4 Scout 17B</option>
                    <option value="llama-3.3-70b-versatile" selected>Llama 3.3 70B Versatile</option>
                    <option value="llama-3.1-8b-instant">Llama 3.1 8B Instant</option>
                    <option value="openai/gpt-oss-120b">GPT-OSS 120B</option>
                    <option value="openai/gpt-oss-20b">GPT-OSS 20B</option>
                    <option value="openai/gpt-oss-safeguard-20b">GPT-OSS Safeguard 20B</option>
                    <option value="moonshotai/kimi-k2-instruct">Kimi K2 Instruct</option>
                    <option value="moonshotai/kimi-k2-instruct-0905">Kimi K2 Instruct 0905</option>
                    <option value="groq/compound">Groq Compound</option>
                    <option value="groq/compound-mini">Groq Compound Mini</option>
                    <option value="canopylabs/orpheus-arabic-saudi">Orpheus Arabic Saudi</option>
                    <option value="canopylabs/orpheus-v1-english">Orpheus V1 English</option>
                    <option value="meta-llama/llama-prompt-guard-2-22m">Llama Prompt Guard 22M</option>
                    <option value="meta-llama/llama-prompt-guard-2-86m">Llama Prompt Guard 86M</option>
                    <option value="whisper-large-v3">Whisper Large V3</option>
                    <option value="whisper-large-v3-turbo">Whisper Large V3 Turbo</option>
                </select>
            </div>
            <div class="ai-section ai-chat">
                <h6>Assistant IA — Analyse &amp; Aide</h6>
                <div class="ai-chat-messages" id="ai-chat-messages">
                    <div class="ai-msg assistant">
                        <div class="msg-role">Assistant IA</div>
                        <p>Bienvenue ! Activez l'IA et sélectionnez un fichier dans l'éditeur pour que je puisse l'analyser. Je peux expliquer le code, proposer des modifications et décrire l'impact in-game de chaque changement.</p>
                    </div>
                </div>
                <div class="ai-chat-input">
                    <input type="text" class="ai-input" id="ai-chat-input" placeholder="Posez une question sur le code..." onkeydown="if(event.key==='Enter')sendAIChat()">
                    <button class="ai-btn ai-btn-success" onclick="sendAIChat()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI Notification Button -->
<button class="ai-notif" id="ai-notif-btn" onclick="showAISuggestions()" title="Suggestions IA disponibles">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a4 4 0 014 4v1a1 1 0 001 1h1a4 4 0 010 8h-1a1 1 0 00-1 1v1a4 4 0 01-8 0v-1a1 1 0 00-1-1H6a4 4 0 010-8h1a1 1 0 001-1V6a4 4 0 014-4zm0 4a2 2 0 100 4 2 2 0 000-4z"/></svg>
</button>

<!-- AI Suggestions Overlay -->
<div class="ai-suggestions-overlay" id="ai-suggestions-overlay" onclick="if(event.target===this)closeAISuggestions()">
    <div class="ai-suggestions-panel">
        <h4>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            Suggestions IA
            <button class="close-ai" style="margin-left:auto" onclick="closeAISuggestions()">&times;</button>
        </h4>
        <div id="ai-suggestions-list"></div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/javascript/javascript.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/xml/xml.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/clike/clike.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/properties/properties.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@xenova/transformers@2.17.1/dist/transformers.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/libarchive.js@1.3.0/dist/libarchive.js" defer></script>

<script>
// ===========================================================================
// STATE
// ===========================================================================
const state = {
    files: new Map(),       // path → { data: Uint8Array, modified: false }
    modName: '',
    masterFile: null,       // path to ESP/ESM/ESL
    espInfo: null,          // parsed ESP data
    selectedFile: null,
    editor: null,           // CodeMirror instance
    editorDirty: false,
    totalSize: 0,
    modifiedCount: 0
};

// ===========================================================================
// INIT
// ===========================================================================
document.addEventListener('DOMContentLoaded', () => {
    initDropZone();
    initResizeHandle();
    initImportInputs();
    registerPapyrusMode();
});

function initDropZone() {
    const dz = document.getElementById('drop-zone');
    const body = document.body;
    ['dragenter','dragover'].forEach(e => {
        body.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); });
        dz.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); dz.classList.add('drag-over'); });
    });
    ['dragleave','dragend'].forEach(e => {
        dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('drag-over'); });
    });
    body.addEventListener('drop', async ev => {
        ev.preventDefault(); ev.stopPropagation();
        dz.classList.remove('drag-over');
        const items = ev.dataTransfer.items;
        if (!items || items.length === 0) return;

        // Check if it's a folder drop (webkitGetAsEntry)
        const entries = [];
        for (let i = 0; i < items.length; i++) {
            const entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
            if (entry) entries.push(entry);
        }

        if (entries.length > 0 && entries.some(e => e.isDirectory)) {
            await importFromEntries(entries);
        } else {
            // File drop - check for ZIP
            const files = ev.dataTransfer.files;
            await handleDroppedFiles(files);
        }
    });
    dz.addEventListener('click', () => triggerImport());
}

function initImportInputs() {
    document.getElementById('import-zip-input').addEventListener('change', async function(e) {
        if (this.files.length > 0) await handleDroppedFiles(this.files);
        this.value = '';
    });
    document.getElementById('add-files-input').addEventListener('change', async function(e) {
        if (this.files.length > 0) {
            for (const f of this.files) {
                const data = new Uint8Array(await f.arrayBuffer());
                state.files.set(f.name, { data, modified: false });
                state.totalSize += data.byteLength;
            }
            detectMasterFile();
            refreshUI();
            toast('Added ' + this.files.length + ' file(s)', 'success');
        }
        this.value = '';
    });
}

function triggerImport() {
    // Show a choice: ZIP file or folder
    const input = document.getElementById('import-zip-input');
    input.setAttribute('accept', '.zip,.jar,.rar,.7z,.esp,.esm,.esl,.bsa,.ba2,.nif,.dds,.pex,.psc,.seq,.swf,.txt,.cfg,.ini,.json,.xml');
    input.removeAttribute('webkitdirectory');
    input.setAttribute('multiple', '');
    input.click();
}

function addFiles() {
    const input = document.getElementById('add-files-input');
    input.click();
}

// ===========================================================================
// FILE IMPORT
// ===========================================================================
async function handleDroppedFiles(fileList) {
    showLoading('Processing files...');
    try {
        const files = Array.from(fileList);
        const zipFiles = files.filter(f => /\.(zip|jar)$/i.test(f.name));
        const compressedFiles = files.filter(f => /\.(rar|7z)$/i.test(f.name));
        const looseFiles = files.filter(f => !/\.(zip|jar|rar|7z)$/i.test(f.name));

        // Process ZIP files
        for (const zf of zipFiles) {
            await importZip(zf);
        }

        // Process RAR/7z files
        for (const cf of compressedFiles) {
            await handleCompressedFile(cf);
        }

        // Process loose files
        for (const f of looseFiles) {
            const data = new Uint8Array(await f.arrayBuffer());
            // Try to preserve relative path if available
            const path = f.webkitRelativePath || f.name;
            state.files.set(cleanPath(path), { data, modified: false });
            state.totalSize += data.byteLength;
        }

        if (state.files.size > 0) {
            detectMasterFile();
            showEditorLayout();
            refreshUI();
            toast('Imported ' + state.files.size + ' files', 'success');
        } else {
            toast('No supported files found', 'warning');
        }
    } catch (err) {
        console.error(err);
        toast('Import error: ' + err.message, 'error');
    }
    hideLoading();
}

async function importZip(file) {
    showLoading('Extracting ' + file.name + '...', 'Reading archive...');
    const zip = await JSZip.loadAsync(file);
    const entries = Object.keys(zip.files);

    // Detect common root folder
    let commonRoot = detectCommonRoot(entries.filter(e => !zip.files[e].dir));

    let processed = 0;
    const total = entries.filter(e => !zip.files[e].dir).length;

    for (const path of entries) {
        const entry = zip.files[path];
        if (entry.dir) continue;

        let cleanedPath = path;
        if (commonRoot) {
            cleanedPath = path.substring(commonRoot.length);
        }
        cleanedPath = cleanPath(cleanedPath);
        if (!cleanedPath) continue;

        showLoading('Extracting ' + file.name + '...', `${++processed}/${total} files`);
        const data = new Uint8Array(await entry.async('uint8array'));
        state.files.set(cleanedPath, { data, modified: false });
        state.totalSize += data.byteLength;
    }

    if (!state.modName) {
        state.modName = file.name.replace(/\.(zip|jar)$/i, '');
    }
}

async function importFromEntries(entries) {
    showLoading('Reading folder...');
    try {
        const fileEntries = [];
        for (const entry of entries) {
            await collectEntries(entry, '', fileEntries);
        }

        // Detect common root
        const paths = fileEntries.map(fe => fe.path);
        const commonRoot = detectCommonRoot(paths);

        let processed = 0;
        for (const fe of fileEntries) {
            showLoading('Importing files...', `${++processed}/${fileEntries.length}`);
            let path = fe.path;
            if (commonRoot) path = path.substring(commonRoot.length);
            path = cleanPath(path);
            if (!path) continue;

            const data = new Uint8Array(await readFileEntry(fe.entry));
            state.files.set(path, { data, modified: false });
            state.totalSize += data.byteLength;
        }

        if (!state.modName && entries.length === 1 && entries[0].isDirectory) {
            state.modName = entries[0].name;
        }

        if (state.files.size > 0) {
            detectMasterFile();
            showEditorLayout();
            refreshUI();
            toast('Imported ' + state.files.size + ' files from folder', 'success');
        }
    } catch (err) {
        console.error(err);
        toast('Import error: ' + err.message, 'error');
    }
    hideLoading();
}

async function collectEntries(entry, basePath, result) {
    if (entry.isFile) {
        result.push({ path: basePath + entry.name, entry });
    } else if (entry.isDirectory) {
        const reader = entry.createReader();
        const entries = await new Promise((resolve, reject) => {
            const all = [];
            function readBatch() {
                reader.readEntries(batch => {
                    if (batch.length === 0) resolve(all);
                    else { all.push(...batch); readBatch(); }
                }, reject);
            }
            readBatch();
        });
        for (const child of entries) {
            await collectEntries(child, basePath + entry.name + '/', result);
        }
    }
}

function readFileEntry(entry) {
    return new Promise((resolve, reject) => {
        entry.file(file => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsArrayBuffer(file);
        }, reject);
    });
}

// ===========================================================================
// MASTER FILE DETECTION & ESP PARSER
// ===========================================================================
function detectMasterFile() {
    const pluginExts = ['.esp', '.esm', '.esl'];
    for (const [path, file] of state.files) {
        const ext = getExt(path);
        if (pluginExts.includes(ext)) {
            state.masterFile = path;
            if (!state.modName) state.modName = getFileName(path).replace(/\.[^.]+$/, '');
            state.espInfo = parseESP(file.data);
            break;
        }
    }
    if (!state.modName && state.files.size > 0) {
        state.modName = 'Untitled Mod';
    }
    document.getElementById('nav-mod-name').textContent = state.modName;
}

function parseESP(buffer) {
    const view = new DataView(buffer.buffer, buffer.byteOffset, buffer.byteLength);
    const info = {
        type: '', version: 0, numRecords: 0, nextObjectId: 0,
        author: '', description: '', masters: [], flags: 0,
        internalVersion: 0, groups: [], recordCounts: {}
    };

    try {
        // Read TES4 record header
        const type = readString(buffer, 0, 4);
        if (type !== 'TES4') {
            info.type = 'Unknown (not TES4)';
            return info;
        }
        info.type = 'TES4';

        const dataSize = view.getUint32(4, true);
        info.flags = view.getUint32(8, true);
        info.internalVersion = view.getUint16(20, true);

        // Parse TES4 subrecords
        let offset = 24;
        const endOffset = 24 + dataSize;
        let currentMaster = null;

        while (offset < endOffset && offset < buffer.byteLength - 6) {
            const subType = readString(buffer, offset, 4);
            const subSize = view.getUint16(offset + 4, true);
            offset += 6;

            if (offset + subSize > buffer.byteLength) break;

            switch (subType) {
                case 'HEDR':
                    if (subSize >= 12) {
                        info.version = view.getFloat32(offset, true);
                        info.numRecords = view.getInt32(offset + 4, true);
                        info.nextObjectId = view.getUint32(offset + 8, true);
                    }
                    break;
                case 'CNAM':
                    info.author = readZString(buffer, offset, subSize);
                    break;
                case 'SNAM':
                    info.description = readZString(buffer, offset, subSize);
                    break;
                case 'MAST':
                    currentMaster = readZString(buffer, offset, subSize);
                    break;
                case 'DATA':
                    if (currentMaster) {
                        info.masters.push(currentMaster);
                        currentMaster = null;
                    }
                    break;
            }
            offset += subSize;
        }
        if (currentMaster) info.masters.push(currentMaster);

        // Scan GRUP records for record type counts
        offset = 24 + dataSize;
        let grupCount = 0;
        while (offset < buffer.byteLength - 24 && grupCount < 500) {
            const grpType = readString(buffer, offset, 4);
            if (grpType !== 'GRUP') break;

            const groupSize = view.getUint32(offset + 4, true);
            if (groupSize < 24 || offset + groupSize > buffer.byteLength) break;

            const groupType = view.getInt32(offset + 12, true);
            if (groupType === 0) {
                const label = readString(buffer, offset + 8, 4);
                // Count records inside this group
                const count = countRecordsInGroup(buffer, view, offset + 24, offset + groupSize, label);
                if (count > 0) {
                    info.recordCounts[label] = count;
                }
                info.groups.push({ type: label, size: groupSize, count });
            }

            offset += groupSize;
            grupCount++;
        }

    } catch (e) {
        console.error('ESP parse error:', e);
    }

    return info;
}

function countRecordsInGroup(buffer, view, start, end, expectedType) {
    let count = 0;
    let offset = start;
    while (offset < end - 24 && offset < buffer.byteLength - 24) {
        const type = readString(buffer, offset, 4);
        if (type === 'GRUP') {
            const gs = view.getUint32(offset + 4, true);
            if (gs < 24) break;
            offset += gs;
        } else {
            count++;
            const dataSize = view.getUint32(offset + 4, true);
            offset += 24 + dataSize;
        }
        if (count > 50000) break; // safety limit
    }
    return count;
}

// ===========================================================================
// PEX PARSER (Papyrus Compiled Script)
// ===========================================================================
function parsePEX(buffer) {
    const view = new DataView(buffer.buffer, buffer.byteOffset, buffer.byteLength);
    const info = {
        valid: false, magic: 0, majorVersion: 0, minorVersion: 0,
        gameId: 0, gameName: '', compilationTime: '', sourceFile: '',
        userName: '', machineName: '', stringTable: []
    };

    try {
        if (buffer.byteLength < 12) return info;

        // PEX uses BIG-endian for magic
        info.magic = view.getUint32(0, false);
        if (info.magic !== 0xFA57C0DE) return info;

        info.valid = true;
        info.majorVersion = view.getUint8(4);
        info.minorVersion = view.getUint8(5);
        info.gameId = view.getUint16(6, false);

        switch (info.gameId) {
            case 1: info.gameName = 'Skyrim'; break;
            case 2: info.gameName = 'Fallout 4'; break;
            case 3: info.gameName = 'Fallout 76'; break;
            case 4: info.gameName = 'Starfield'; break;
            default: info.gameName = 'Unknown (' + info.gameId + ')';
        }

        // Compilation time (uint64 big-endian, but let's read as seconds)
        const compTimeHigh = view.getUint32(8, false);
        const compTimeLow = view.getUint32(12, false);
        const compTime = compTimeHigh * 0x100000000 + compTimeLow;
        if (compTime > 0 && compTime < 4294967296) {
            info.compilationTime = new Date(compTime * 1000).toLocaleString();
        } else {
            info.compilationTime = 'N/A';
        }

        let offset = 16;

        // Read wstring (uint16 length + chars)
        function readWString() {
            if (offset + 2 > buffer.byteLength) return '';
            const len = view.getUint16(offset, false);
            offset += 2;
            if (offset + len > buffer.byteLength) return '';
            let s = '';
            for (let i = 0; i < len; i++) s += String.fromCharCode(buffer[offset + i]);
            offset += len;
            return s;
        }

        info.sourceFile = readWString();
        info.userName = readWString();
        info.machineName = readWString();

        // String table
        if (offset + 2 <= buffer.byteLength) {
            const strCount = view.getUint16(offset, false);
            offset += 2;
            for (let i = 0; i < Math.min(strCount, 500); i++) {
                info.stringTable.push(readWString());
            }
        }

    } catch (e) {
        console.error('PEX parse error:', e);
    }

    return info;
}

// ===========================================================================
// DDS PARSER
// ===========================================================================
function parseDDS(buffer) {
    const view = new DataView(buffer.buffer, buffer.byteOffset, buffer.byteLength);
    const info = {
        valid: false, width: 0, height: 0, mipMapCount: 0,
        format: '', fourCC: '', bitCount: 0, compressed: false,
        depth: 0, linearSize: 0
    };

    try {
        if (buffer.byteLength < 128) return info;

        const magic = view.getUint32(0, true);
        if (magic !== 0x20534444) return info; // "DDS "

        info.valid = true;
        const flags = view.getUint32(8, true);
        info.height = view.getUint32(12, true);
        info.width = view.getUint32(16, true);
        info.linearSize = view.getUint32(20, true);
        info.depth = view.getUint32(24, true);
        info.mipMapCount = view.getUint32(28, true);

        // Pixel format at offset 76
        const pfFlags = view.getUint32(80, true);
        info.fourCC = readString(buffer, 84, 4);
        info.bitCount = view.getUint32(88, true);

        if (pfFlags & 0x4) { // DDPF_FOURCC
            info.compressed = true;
            info.format = info.fourCC;
            if (info.fourCC === 'DX10') {
                if (buffer.byteLength >= 148) {
                    const dxgiFormat = view.getUint32(128, true);
                    info.format = 'DXGI Format ' + dxgiFormat;
                    info.format += ' (' + getDXGIFormatName(dxgiFormat) + ')';
                }
            }
        } else if (pfFlags & 0x40) { // DDPF_RGB
            info.format = 'Uncompressed RGB' + (info.bitCount || '');
            if (pfFlags & 0x1) info.format += '+Alpha';
        } else if (pfFlags & 0x20000) { // DDPF_LUMINANCE
            info.format = 'Luminance';
        } else {
            info.format = 'Unknown (flags: 0x' + pfFlags.toString(16) + ')';
        }

    } catch (e) {
        console.error('DDS parse error:', e);
    }

    return info;
}

function getDXGIFormatName(fmt) {
    const names = {
        71: 'BC1_UNORM (DXT1)', 72: 'BC1_UNORM_SRGB',
        74: 'BC2_UNORM (DXT3)', 75: 'BC2_UNORM_SRGB',
        77: 'BC3_UNORM (DXT5)', 78: 'BC3_UNORM_SRGB',
        80: 'BC4_UNORM (ATI1)', 83: 'BC5_UNORM (ATI2)',
        87: 'B8G8R8A8_UNORM', 95: 'BC6H_UF16', 98: 'BC7_UNORM', 99: 'BC7_UNORM_SRGB'
    };
    return names[fmt] || 'Format ' + fmt;
}

// ===========================================================================
// NIF PARSER (Basic header)
// ===========================================================================
function parseNIF(buffer) {
    const info = {
        valid: false, headerString: '', version: '', userVersion: 0,
        numBlocks: 0, blockTypes: []
    };

    try {
        // Read header string until newline (max 80 chars)
        let headerEnd = 0;
        for (let i = 0; i < Math.min(buffer.byteLength, 80); i++) {
            if (buffer[i] === 0x0A) { headerEnd = i; break; }
        }
        if (headerEnd === 0) return info;

        info.headerString = readString(buffer, 0, headerEnd).trim();
        info.valid = info.headerString.includes('Gamebryo') || info.headerString.includes('NetImmerse');

        // Extract version from header string
        const vMatch = info.headerString.match(/Version\s+([\d.]+)/);
        if (vMatch) info.version = vMatch[1];

        // After header string + newline, read binary version
        let offset = headerEnd + 1;
        if (offset + 4 > buffer.byteLength) return info;

        const view = new DataView(buffer.buffer, buffer.byteOffset, buffer.byteLength);

        // Binary version (4 bytes)
        offset += 4;

        // Endian type
        if (offset + 1 > buffer.byteLength) return info;
        offset += 1; // endianType

        // User version
        if (offset + 4 > buffer.byteLength) return info;
        info.userVersion = view.getUint32(offset, true);
        offset += 4;

        // Num blocks
        if (offset + 4 > buffer.byteLength) return info;
        info.numBlocks = view.getUint32(offset, true);
        offset += 4;

        // Read block type names (simplified)
        // Skip some fields to get to block types
        // This varies by NIF version, so we'll do a best-effort parse
        // Skip BSStreamHeader for Skyrim NIFs
        if (info.userVersion >= 12) {
            // Skip BS header (author, process script, export script)
            for (let i = 0; i < 3; i++) {
                if (offset + 4 > buffer.byteLength) return info;
                const sLen = view.getUint32(offset, true);
                offset += 4 + sLen;
            }
        }

        // Number of block types
        if (offset + 2 > buffer.byteLength) return info;
        const numTypes = view.getUint16(offset, true);
        offset += 2;

        for (let i = 0; i < Math.min(numTypes, 100); i++) {
            if (offset + 4 > buffer.byteLength) break;
            const tLen = view.getUint32(offset, true);
            offset += 4;
            if (offset + tLen > buffer.byteLength) break;
            let name = readString(buffer, offset, tLen);
            offset += tLen;
            info.blockTypes.push(name);
        }

    } catch (e) {
        console.error('NIF parse error:', e);
    }

    return info;
}

// ===========================================================================
// FILE TREE UI
// ===========================================================================
function buildFileTree() {
    const tree = {};
    for (const path of state.files.keys()) {
        const parts = path.split('/');
        let node = tree;
        for (let i = 0; i < parts.length - 1; i++) {
            if (!node[parts[i]]) node[parts[i]] = {};
            node = node[parts[i]];
        }
        node[parts[parts.length - 1]] = null; // leaf = file
    }
    return tree;
}

function renderFileTree() {
    const tree = buildFileTree();
    const container = document.getElementById('file-tree');
    container.innerHTML = '';
    renderTreeNode(tree, container, '', 0);
}

function renderTreeNode(node, container, basePath, depth) {
    // Separate folders and files
    const entries = Object.entries(node);
    const folders = entries.filter(([, v]) => v !== null).sort((a, b) => a[0].localeCompare(b[0]));
    const files = entries.filter(([, v]) => v === null).sort((a, b) => a[0].localeCompare(b[0]));

    for (const [name, children] of folders) {
        const fullPath = basePath ? basePath + '/' + name : name;
        const fileCount = countFilesInTree(children);

        const folderEl = document.createElement('div');
        folderEl.className = 'tree-folder';
        folderEl.style.setProperty('--indent', (12 + depth * 16) + 'px');
        folderEl.innerHTML = `<span class="arrow"><i class="fas fa-chevron-down"></i></span>
            <span class="folder-icon"><i class="fas fa-folder"></i></span>
            <span class="name">${esc(name)}</span>
            <span class="count">${fileCount}</span>`;
        folderEl.onclick = (e) => {
            e.stopPropagation();
            folderEl.classList.toggle('collapsed');
            const icon = folderEl.querySelector('.folder-icon i');
            icon.className = folderEl.classList.contains('collapsed') ? 'fas fa-folder' : 'fas fa-folder-open';
        };
        container.appendChild(folderEl);

        const childContainer = document.createElement('div');
        childContainer.className = 'tree-children';
        renderTreeNode(children, childContainer, fullPath, depth + 1);
        container.appendChild(childContainer);
    }

    for (const [name] of files) {
        const fullPath = basePath ? basePath + '/' + name : name;
        const ext = getExt(name);
        const fileEl = document.createElement('div');
        fileEl.className = 'tree-file';
        fileEl.dataset.path = fullPath;
        fileEl.style.setProperty('--indent', (12 + depth * 16) + 'px');

        const iconClass = getFileIconClass(ext);
        const colorClass = getFileColorClass(ext);

        fileEl.innerHTML = `<span class="file-icon ${colorClass}"><i class="${iconClass}"></i></span>
            <span class="name">${esc(name)}</span>`;

        if (state.files.get(fullPath)?.modified) {
            fileEl.classList.add('modified');
        }

        fileEl.onclick = () => selectFile(fullPath);
        container.appendChild(fileEl);
    }
}

function countFilesInTree(node) {
    if (node === null) return 1;
    let count = 0;
    for (const v of Object.values(node)) {
        count += countFilesInTree(v);
    }
    return count;
}

function filterTree(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('#file-tree .tree-file').forEach(el => {
        const path = el.dataset.path.toLowerCase();
        const match = !q || path.includes(q);
        el.style.display = match ? '' : 'none';
    });
    // Show parent folders of visible files
    document.querySelectorAll('#file-tree .tree-folder').forEach(el => {
        const children = el.nextElementSibling;
        if (children) {
            const hasVisibleChildren = children.querySelector('.tree-file:not([style*="display: none"])') ||
                                       children.querySelector('.tree-folder');
            el.style.display = (!q || hasVisibleChildren) ? '' : 'none';
        }
    });
}

// ===========================================================================
// FILE SELECTION & VIEWING
// ===========================================================================
function selectFile(path) {
    // Save current editor content if dirty
    saveEditorContent();

    state.selectedFile = path;

    // Highlight in tree
    document.querySelectorAll('#file-tree .tree-file').forEach(el => el.classList.remove('active'));
    const el = document.querySelector(`.tree-file[data-path="${CSS.escape(path)}"]`);
    if (el) el.classList.add('active');

    // Update status bar
    document.getElementById('sb-selected').textContent = path;

    const file = state.files.get(path);
    if (!file) return;

    const ext = getExt(path);
    updatePropertiesPanel(path, file);

    // Decide which tab to show
    if (isTextFile(ext)) {
        showEditorForFile(path, file);
        switchTabByName('editor-panel');
    } else if (ext === '.esp' || ext === '.esm' || ext === '.esl') {
        showPluginView(path, file);
        switchTabByName('props-panel');
    } else if (ext === '.pex') {
        showPEXView(path, file);
        switchTabByName('props-panel');
    } else if (ext === '.dds') {
        showDDSPreview(path, file);
        switchTabByName('preview-panel');
        sendFileTo3DPreview(path, file.data);
    } else if (ext === '.nif') {
        showNIFView(path, file);
        switchTabByName('preview-panel');
        sendFileTo3DPreview(path, file.data);
    } else {
        showHexView(path, file);
        switchTabByName('hex-panel');
    }
}

function isTextFile(ext) {
    return ['.psc','.txt','.cfg','.ini','.json','.xml','.log','.md','.csv','.html','.htm','.js','.css','.bat','.ps1','.sh','.py'].includes(ext);
}

// ===========================================================================
// EDITOR (CodeMirror)
// ===========================================================================
function showEditorForFile(path, file) {
    const panel = document.getElementById('editor-panel');
    const ext = getExt(path);

    if (!state.editor) {
        panel.innerHTML = `<div class="editor-toolbar">
            <i class="fas fa-code" style="color:var(--accent-green)"></i>
            <span class="filepath" id="editor-filepath"></span>
            <button class="btn-nav" onclick="saveEditorContent()" style="margin-left:auto"><i class="fas fa-save"></i> Save</button>
        </div>
        <div id="editor-container"></div>`;

        state.editor = CodeMirror(document.getElementById('editor-container'), {
            value: '',
            lineNumbers: true,
            theme: 'monokai',
            matchBrackets: true,
            indentUnit: 4,
            tabSize: 4,
            lineWrapping: true,
            mode: 'text/plain'
        });

        state.editor.on('change', () => { state.editorDirty = true; });
    }

    // Set content
    const text = new TextDecoder('utf-8', { fatal: false }).decode(file.data);
    state.editor.setValue(text);
    state.editorDirty = false;

    // Set mode based on extension
    let mode = 'text/plain';
    if (ext === '.psc') mode = 'text/x-papyrus';
    else if (ext === '.json') mode = 'application/json';
    else if (ext === '.xml' || ext === '.html' || ext === '.htm') mode = 'text/html';
    else if (ext === '.js') mode = 'text/javascript';
    else if (ext === '.css') mode = 'text/css';
    else if (ext === '.ini' || ext === '.cfg') mode = 'text/x-properties';

    state.editor.setOption('mode', mode);

    document.getElementById('editor-filepath').textContent = path;
    setTimeout(() => state.editor.refresh(), 50);
}

function saveEditorContent() {
    if (!state.editor || !state.editorDirty || !state.selectedFile) return;
    const file = state.files.get(state.selectedFile);
    if (!file) return;

    const text = state.editor.getValue();
    file.data = new TextEncoder().encode(text);
    file.modified = true;
    state.editorDirty = false;
    updateModifiedCount();
    refreshTreeModified();
    toast('Saved: ' + getFileName(state.selectedFile), 'success');
}

// ===========================================================================
// HEX VIEWER
// ===========================================================================
function showHexView(path, file, maxBytes = 8192) {
    const panel = document.getElementById('hex-panel');
    const data = file.data;
    const len = Math.min(data.byteLength, maxBytes);

    let html = `<div class="hex-toolbar">
        <i class="fas fa-memory" style="color:var(--accent-blue)"></i>
        <span>${esc(path)}</span>
        <span style="margin-left:auto;color:var(--text-secondary)">${formatSize(data.byteLength)} total${data.byteLength > maxBytes ? ' (showing first ' + formatSize(maxBytes) + ')' : ''}</span>
    </div><div class="hex-container">`;

    for (let i = 0; i < len; i += 16) {
        const offsetStr = i.toString(16).toUpperCase().padStart(8, '0');
        let hexParts = [];
        let asciiParts = [];

        for (let j = 0; j < 16; j++) {
            if (i + j < len) {
                hexParts.push(data[i + j].toString(16).toUpperCase().padStart(2, '0'));
                const ch = data[i + j];
                asciiParts.push(ch >= 32 && ch <= 126 ? String.fromCharCode(ch) : '.');
            } else {
                hexParts.push('  ');
                asciiParts.push(' ');
            }
        }

        html += `<div class="hex-row">
            <span class="hex-offset">${offsetStr}</span>
            <span class="hex-bytes">${hexParts.join(' ')}</span>
            <span class="hex-ascii">${esc(asciiParts.join(''))}</span>
        </div>`;
    }

    html += '</div>';
    panel.innerHTML = html;
}

// ===========================================================================
// PLUGIN VIEW (ESP/ESM/ESL)
// ===========================================================================
function showPluginView(path, file) {
    const info = parseESP(file.data);
    const panel = document.getElementById('props-panel');

    const flagDescriptions = [];
    if (info.flags & 0x01) flagDescriptions.push('Master (ESM)');
    if (info.flags & 0x80) flagDescriptions.push('Localized');
    if (info.flags & 0x200) flagDescriptions.push('Light (ESL)');

    let html = `<div class="script-info">
        <div class="header">
            <i class="fas fa-gem" style="color:var(--accent-gold)"></i>
            <div>
                <h5>${esc(getFileName(path))}</h5>
                <small style="color:var(--text-secondary)">Bethesda Plugin File</small>
            </div>
        </div>
        <div class="info-grid">
            <div class="info-card"><div class="lbl">Version</div><div class="val">${info.version ? info.version.toFixed(2) : 'N/A'}</div></div>
            <div class="info-card"><div class="lbl">Records</div><div class="val">${info.numRecords.toLocaleString()}</div></div>
            <div class="info-card"><div class="lbl">Author</div><div class="val">${esc(info.author) || '<i>None</i>'}</div></div>
            <div class="info-card"><div class="lbl">Internal Version</div><div class="val">${info.internalVersion}</div></div>
            <div class="info-card"><div class="lbl">Flags</div><div class="val">${flagDescriptions.join(', ') || 'None'} (0x${info.flags.toString(16).toUpperCase()})</div></div>
            <div class="info-card"><div class="lbl">Next Object ID</div><div class="val">0x${info.nextObjectId.toString(16).toUpperCase()}</div></div>
        </div>`;

    if (info.description) {
        html += `<div class="prop-group"><h6>Description</h6><p style="font-size:13px;color:var(--text-primary);white-space:pre-wrap">${esc(info.description)}</p></div>`;
    }

    if (info.masters.length > 0) {
        html += `<div class="prop-group"><h6>Master Dependencies (${info.masters.length})</h6><ul class="masters-list">`;
        for (const m of info.masters) {
            html += `<li><i class="fas fa-link"></i> ${esc(m)}</li>`;
        }
        html += '</ul></div>';
    }

    if (info.groups.length > 0) {
        html += `<div class="prop-group"><h6>Record Groups (${info.groups.length})</h6><div class="records-grid">`;
        const recordTypeNames = {
            'GMST':'Game Settings','KYWD':'Keywords','TXST':'Texture Sets','GLOB':'Globals',
            'CLAS':'Classes','FACT':'Factions','HDPT':'Head Parts','RACE':'Races',
            'MGEF':'Magic Effects','ENCH':'Enchantments','SPEL':'Spells','ACTI':'Activators',
            'ARMO':'Armor','BOOK':'Books','CONT':'Containers','DOOR':'Doors',
            'INGR':'Ingredients','LIGH':'Lights','MISC':'Misc Items','STAT':'Statics',
            'WEAP':'Weapons','NPC_':'NPCs','CELL':'Cells','WRLD':'Worldspaces',
            'DIAL':'Dialog Topics','QUST':'Quests','IDLE':'Idles','PACK':'Packages',
            'PERK':'Perks','SCRL':'Scrolls','ALCH':'Potions','LVLI':'Leveled Items',
            'LVLN':'Leveled NPCs','LVSP':'Leveled Spells','SNDR':'Sound Descriptors',
            'DLBR':'Dialog Branches','DLVW':'Dialog Views','SMQN':'SM Quest Nodes',
            'FLST':'Form Lists','HAZD':'Hazards','EXPL':'Explosions','PROJ':'Projectiles',
            'IMAD':'Image Space Adapters','EFSH':'Effect Shaders','DUAL':'Dual Cast Data',
            'EQUP':'Equip Types','COBJ':'Constructible Objects','OTFT':'Outfits',
            'ARMA':'Armor Addons','VTYP':'Voice Types','MATT':'Materials','IPDS':'Impact Data Sets',
            'AVIF':'Actor Values','LCTN':'Locations','MESG':'Messages','RFCT':'Visual Effects',
            'MUST':'Music Tracks','MUSC':'Music Types','CPTH':'Camera Paths','ANIO':'Anim Objects',
            'WATR':'Water Types','WOOP':'Words of Power','SHOU':'Shouts','LSCR':'Load Screens',
            'TREE':'Trees','FLOR':'Flora','FURN':'Furniture','AMMO':'Ammo','CLMT':'Climate',
            'REGN':'Regions','NAVI':'Navigation','NAVM':'Navmesh','IMGS':'Image Spaces',
            'APPA':'Apparatus','ASTP':'Association Types','BPTD':'Body Part Data',
            'CLFM':'Colors','CMPO':'Components','CSTY':'Combat Styles','DEBR':'Debris',
            'DOBJ':'Default Objects','ECZN':'Encounter Zones','EYES':'Eyes',
            'GDRY':'God Rays','GRAS':'Grass','HAIR':'Hair','IPCT':'Impacts',
            'KEYM':'Keys','KSSM':'Sound Keywords','LENS':'Lens Flares',
            'LGTM':'Lighting Templates','LTEX':'Land Textures','MATO':'Materials (Obj)',
            'MOVT':'Movement Types','MSTT':'Moveable Statics','PKIN':'Pack-In',
            'REVB':'Reverb Parameters','SCEN':'Scenes','SLGM':'Soul Gems',
            'SMBN':'SM Branch Nodes','SMEN':'SM Event Nodes','SNCT':'Sound Categories',
            'SOPM':'Sound Output Models','SPGD':'Shader Particles','TACT':'Talking Activators',
            'TXST':'Texture Sets','WTHR':'Weather'
        };

        for (const g of info.groups.sort((a,b) => b.count - a.count)) {
            const name = recordTypeNames[g.type] || g.type;
            html += `<div class="record-badge"><span class="type">${g.type}</span><span title="${esc(name)}" class="cnt">${g.count} ${esc(name)}</span></div>`;
        }
        html += '</div></div>';
    }

    html += '</div>';
    panel.innerHTML = html;

    // Also show hex
    showHexView(path, file);
}

// ===========================================================================
// PEX VIEW
// ===========================================================================
function showPEXView(path, file) {
    const info = parsePEX(file.data);
    const panel = document.getElementById('props-panel');

    if (!info.valid) {
        panel.innerHTML = `<div class="script-info"><p>Could not parse PEX file (invalid magic number)</p></div>`;
        showHexView(path, file);
        return;
    }

    let html = `<div class="script-info">
        <div class="header">
            <i class="fas fa-scroll" style="color:var(--accent-green)"></i>
            <div>
                <h5>${esc(getFileName(path))}</h5>
                <small style="color:var(--text-secondary)">Compiled Papyrus Script</small>
            </div>
        </div>
        <div class="info-grid">
            <div class="info-card"><div class="lbl">Game</div><div class="val">${esc(info.gameName)}</div></div>
            <div class="info-card"><div class="lbl">Version</div><div class="val">${info.majorVersion}.${info.minorVersion}</div></div>
            <div class="info-card"><div class="lbl">Compiled</div><div class="val">${esc(info.compilationTime)}</div></div>
            <div class="info-card"><div class="lbl">Source File</div><div class="val">${esc(info.sourceFile)}</div></div>
            <div class="info-card"><div class="lbl">User</div><div class="val">${esc(info.userName) || 'N/A'}</div></div>
            <div class="info-card"><div class="lbl">Machine</div><div class="val">${esc(info.machineName) || 'N/A'}</div></div>
        </div>`;

    // Check if we have a matching .psc source file
    const pscPath = findMatchingSource(path);
    if (pscPath) {
        html += `<div class="prop-group"><h6>Source Available</h6>
            <p style="font-size:13px"><a href="#" onclick="selectFile('${esc(pscPath)}');return false" style="color:var(--accent-blue)">${esc(pscPath)}</a></p>
        </div>`;
    }

    if (info.stringTable.length > 0) {
        html += `<div class="prop-group"><h6>String Table (${info.stringTable.length} entries)</h6>
            <div class="string-table"><table>`;
        for (let i = 0; i < Math.min(info.stringTable.length, 200); i++) {
            html += `<tr><td>${i}</td><td>${esc(info.stringTable[i])}</td></tr>`;
        }
        if (info.stringTable.length > 200) {
            html += `<tr><td colspan="2" style="color:var(--text-secondary)">... and ${info.stringTable.length - 200} more</td></tr>`;
        }
        html += '</table></div></div>';
    }

    html += '</div>';
    panel.innerHTML = html;
    showHexView(path, file);
}

function findMatchingSource(pexPath) {
    const name = getFileName(pexPath).replace(/\.pex$/i, '.psc');
    for (const p of state.files.keys()) {
        if (getFileName(p).toLowerCase() === name.toLowerCase()) return p;
    }
    return null;
}

// ===========================================================================
// DDS PREVIEW
// ===========================================================================
function showDDSPreview(path, file) {
    const info = parseDDS(file.data);
    const panel = document.getElementById('preview-panel');

    if (!info.valid) {
        panel.innerHTML = `<div class="preview-info"><p>Could not parse DDS file</p></div>`;
        return;
    }

    let html = `<div class="preview-info" style="width:100%">
        <div style="display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:16px">
            <i class="fas fa-image fa-2x" style="color:var(--accent-purple)"></i>
            <div><h5 style="margin:0">${esc(getFileName(path))}</h5><small>DirectDraw Surface Texture</small></div>
        </div>
        <canvas id="dds-canvas" width="${Math.min(info.width, 1024)}" height="${Math.min(info.height, 1024)}" 
            style="max-width:90%;image-rendering:pixelated;background:repeating-conic-gradient(#333 0% 25%, #444 0% 50%) 50%/16px 16px;margin:0 auto;display:block"></canvas>
        <table>
            <tr><td>Dimensions</td><td>${info.width} × ${info.height}</td></tr>
            <tr><td>Format</td><td>${esc(info.format)}</td></tr>
            <tr><td>Mipmaps</td><td>${info.mipMapCount}</td></tr>
            <tr><td>Compressed</td><td>${info.compressed ? 'Yes (' + esc(info.fourCC) + ')' : 'No'}</td></tr>
            <tr><td>File Size</td><td>${formatSize(file.data.byteLength)}</td></tr>
        </table>
    </div>`;

    panel.innerHTML = html;

    // Try basic DDS decode for preview
    try {
        decodeDDSToCanvas(file.data, info, document.getElementById('dds-canvas'));
    } catch (e) {
        console.warn('DDS decode failed:', e);
        // Draw placeholder
        const canvas = document.getElementById('dds-canvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#2a2a3e';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#888';
            ctx.font = '16px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Preview not available for ' + info.format, canvas.width/2, canvas.height/2);
        }
    }

    // Show hex too
    showHexView(path, file, 4096);
}

function decodeDDSToCanvas(buffer, info, canvas) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = Math.min(info.width, 1024);
    const h = Math.min(info.height, 1024);
    canvas.width = w;
    canvas.height = h;

    // DDS data starts after header (128 bytes, or 148 for DX10)
    let dataOffset = 128;
    if (info.fourCC === 'DX10') dataOffset = 148;

    if (!info.compressed && info.bitCount && buffer.byteLength > dataOffset) {
        // Uncompressed - try to decode BGRA or BGR
        const imgData = ctx.createImageData(w, h);
        const bpp = info.bitCount / 8;
        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                const srcIdx = dataOffset + (y * info.width + x) * bpp;
                const dstIdx = (y * w + x) * 4;
                if (srcIdx + bpp <= buffer.byteLength) {
                    if (bpp === 4) {
                        imgData.data[dstIdx] = buffer[srcIdx + 2];     // R
                        imgData.data[dstIdx + 1] = buffer[srcIdx + 1]; // G
                        imgData.data[dstIdx + 2] = buffer[srcIdx];     // B
                        imgData.data[dstIdx + 3] = buffer[srcIdx + 3]; // A
                    } else if (bpp === 3) {
                        imgData.data[dstIdx] = buffer[srcIdx + 2];
                        imgData.data[dstIdx + 1] = buffer[srcIdx + 1];
                        imgData.data[dstIdx + 2] = buffer[srcIdx];
                        imgData.data[dstIdx + 3] = 255;
                    }
                }
            }
        }
        ctx.putImageData(imgData, 0, 0);
    } else if (info.fourCC === 'DXT1' && buffer.byteLength > dataOffset) {
        decodeDXT1(buffer, dataOffset, w, h, ctx);
    } else if (info.fourCC === 'DXT5' && buffer.byteLength > dataOffset) {
        decodeDXT5(buffer, dataOffset, w, h, ctx);
    } else {
        // Show a pattern for unsupported formats
        ctx.fillStyle = '#1a1a2e';
        ctx.fillRect(0, 0, w, h);
        ctx.fillStyle = '#666';
        ctx.font = '14px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(`Format: ${info.format}`, w/2, h/2 - 10);
        ctx.fillText(`Decode not supported in browser`, w/2, h/2 + 10);
    }
}

function decodeDXT1(buffer, offset, width, height, ctx) {
    const imgData = ctx.createImageData(width, height);
    const blockW = Math.ceil(width / 4);
    const blockH = Math.ceil(height / 4);

    for (let by = 0; by < blockH; by++) {
        for (let bx = 0; bx < blockW; bx++) {
            const blockOffset = offset + (by * blockW + bx) * 8;
            if (blockOffset + 8 > buffer.byteLength) break;

            const c0 = buffer[blockOffset] | (buffer[blockOffset + 1] << 8);
            const c1 = buffer[blockOffset + 2] | (buffer[blockOffset + 3] << 8);

            const colors = [];
            colors[0] = rgb565(c0);
            colors[1] = rgb565(c1);

            if (c0 > c1) {
                colors[2] = [Math.round((2*colors[0][0]+colors[1][0])/3), Math.round((2*colors[0][1]+colors[1][1])/3), Math.round((2*colors[0][2]+colors[1][2])/3), 255];
                colors[3] = [Math.round((colors[0][0]+2*colors[1][0])/3), Math.round((colors[0][1]+2*colors[1][1])/3), Math.round((colors[0][2]+2*colors[1][2])/3), 255];
            } else {
                colors[2] = [Math.round((colors[0][0]+colors[1][0])/2), Math.round((colors[0][1]+colors[1][1])/2), Math.round((colors[0][2]+colors[1][2])/2), 255];
                colors[3] = [0, 0, 0, 0];
            }

            const lookup = buffer[blockOffset+4] | (buffer[blockOffset+5]<<8) | (buffer[blockOffset+6]<<16) | (buffer[blockOffset+7]<<24);

            for (let py = 0; py < 4; py++) {
                for (let px = 0; px < 4; px++) {
                    const x = bx * 4 + px;
                    const y = by * 4 + py;
                    if (x >= width || y >= height) continue;
                    const idx = (py * 4 + px) * 2;
                    const ci = (lookup >> idx) & 3;
                    const di = (y * width + x) * 4;
                    imgData.data[di] = colors[ci][0];
                    imgData.data[di+1] = colors[ci][1];
                    imgData.data[di+2] = colors[ci][2];
                    imgData.data[di+3] = colors[ci][3];
                }
            }
        }
    }
    ctx.putImageData(imgData, 0, 0);
}

function decodeDXT5(buffer, offset, width, height, ctx) {
    const imgData = ctx.createImageData(width, height);
    const blockW = Math.ceil(width / 4);
    const blockH = Math.ceil(height / 4);

    for (let by = 0; by < blockH; by++) {
        for (let bx = 0; bx < blockW; bx++) {
            const blockOffset = offset + (by * blockW + bx) * 16;
            if (blockOffset + 16 > buffer.byteLength) break;

            // Alpha block (8 bytes)
            const a0 = buffer[blockOffset];
            const a1 = buffer[blockOffset + 1];
            const alphas = [a0, a1];
            if (a0 > a1) {
                for (let i = 1; i <= 6; i++) alphas.push(Math.round(((7-i)*a0 + i*a1) / 7));
            } else {
                for (let i = 1; i <= 4; i++) alphas.push(Math.round(((5-i)*a0 + i*a1) / 5));
                alphas.push(0); alphas.push(255);
            }

            // 48-bit alpha lookup
            let alphaLookup = 0n;
            for (let i = 0; i < 6; i++) {
                alphaLookup |= BigInt(buffer[blockOffset + 2 + i]) << BigInt(i * 8);
            }

            // Color block (8 bytes at offset+8)
            const co = blockOffset + 8;
            const c0 = buffer[co] | (buffer[co+1] << 8);
            const c1 = buffer[co+2] | (buffer[co+3] << 8);
            const colors = [];
            colors[0] = rgb565(c0);
            colors[1] = rgb565(c1);
            colors[2] = [Math.round((2*colors[0][0]+colors[1][0])/3), Math.round((2*colors[0][1]+colors[1][1])/3), Math.round((2*colors[0][2]+colors[1][2])/3), 255];
            colors[3] = [Math.round((colors[0][0]+2*colors[1][0])/3), Math.round((colors[0][1]+2*colors[1][1])/3), Math.round((colors[0][2]+2*colors[1][2])/3), 255];

            const lookup = buffer[co+4] | (buffer[co+5]<<8) | (buffer[co+6]<<16) | (buffer[co+7]<<24);

            for (let py = 0; py < 4; py++) {
                for (let px = 0; px < 4; px++) {
                    const x = bx * 4 + px;
                    const y = by * 4 + py;
                    if (x >= width || y >= height) continue;
                    const ci = (lookup >> ((py*4+px)*2)) & 3;
                    const ai = Number((alphaLookup >> BigInt((py*4+px)*3)) & 7n);
                    const di = (y * width + x) * 4;
                    imgData.data[di] = colors[ci][0];
                    imgData.data[di+1] = colors[ci][1];
                    imgData.data[di+2] = colors[ci][2];
                    imgData.data[di+3] = alphas[ai];
                }
            }
        }
    }
    ctx.putImageData(imgData, 0, 0);
}

function rgb565(c) {
    const r = ((c >> 11) & 0x1F) * 255 / 31;
    const g = ((c >> 5) & 0x3F) * 255 / 63;
    const b = (c & 0x1F) * 255 / 31;
    return [Math.round(r), Math.round(g), Math.round(b), 255];
}

// ===========================================================================
// NIF VIEW
// ===========================================================================
function showNIFView(path, file) {
    const info = parseNIF(file.data);
    const panel = document.getElementById('props-panel');

    let html = `<div class="script-info">
        <div class="header">
            <i class="fas fa-cube" style="color:var(--accent-blue)"></i>
            <div>
                <h5>${esc(getFileName(path))}</h5>
                <small style="color:var(--text-secondary)">NetImmerse/Gamebryo Model</small>
            </div>
        </div>
        <div class="info-grid">
            <div class="info-card"><div class="lbl">Format</div><div class="val">${esc(info.headerString) || 'N/A'}</div></div>
            <div class="info-card"><div class="lbl">Version</div><div class="val">${esc(info.version) || 'N/A'}</div></div>
            <div class="info-card"><div class="lbl">Blocks</div><div class="val">${info.numBlocks}</div></div>
            <div class="info-card"><div class="lbl">User Version</div><div class="val">${info.userVersion}</div></div>
            <div class="info-card"><div class="lbl">File Size</div><div class="val">${formatSize(file.data.byteLength)}</div></div>
        </div>`;

    if (info.blockTypes.length > 0) {
        // Count block types
        const typeCounts = {};
        for (const t of info.blockTypes) {
            typeCounts[t] = (typeCounts[t] || 0) + 1;
        }
        html += `<div class="prop-group"><h6>Block Types (${info.blockTypes.length})</h6><div class="records-grid">`;
        for (const [type, count] of Object.entries(typeCounts).sort((a,b) => b[1]-a[1])) {
            html += `<div class="record-badge"><span class="type">${esc(type)}</span></div>`;
        }
        html += '</div></div>';
    }

    html += '</div>';
    panel.innerHTML = html;
    showHexView(path, file, 4096);
}

// ===========================================================================
// PROPERTIES PANEL
// ===========================================================================
function updatePropertiesPanel(path, file) {
    // Properties are shown per file type in the specific views above
    // This provides a fallback for files without specific views
    const ext = getExt(path);
    if (['.esp','.esm','.esl','.pex','.nif'].includes(ext)) return;

    const panel = document.getElementById('props-panel');
    let html = `<div class="prop-group"><h6>File Information</h6>`;
    html += propRow('Path', path);
    html += propRow('Name', getFileName(path));
    html += propRow('Extension', ext.toUpperCase());
    html += propRow('Size', formatSize(file.data.byteLength));
    html += propRow('Modified', file.modified ? 'Yes' : 'No');

    if (ext === '.dds') {
        const ddsInfo = parseDDS(file.data);
        if (ddsInfo.valid) {
            html += propRow('Dimensions', `${ddsInfo.width} × ${ddsInfo.height}`);
            html += propRow('Format', ddsInfo.format);
            html += propRow('Mipmaps', ddsInfo.mipMapCount);
        }
    }

    html += '</div>';
    panel.innerHTML = html;
}

function propRow(key, val) {
    return `<div class="prop-row"><span class="prop-key">${esc(key)}</span><span class="prop-val">${esc(String(val))}</span></div>`;
}

// ===========================================================================
// OVERVIEW PANEL
// ===========================================================================
function renderOverview() {
    const panel = document.getElementById('overview-panel');

    // Count file types
    const typeCounts = {};
    let meshCount = 0, textureCount = 0, scriptCount = 0, sourceCount = 0, otherCount = 0;
    for (const [path] of state.files) {
        const ext = getExt(path);
        typeCounts[ext] = (typeCounts[ext] || 0) + 1;
        if (ext === '.nif') meshCount++;
        else if (ext === '.dds') textureCount++;
        else if (ext === '.pex') scriptCount++;
        else if (ext === '.psc') sourceCount++;
        else otherCount++;
    }

    let html = `<div class="overview-header">
        <div class="mod-icon"><i class="fas fa-dragon"></i></div>
        <div>
            <h2>${esc(state.modName)}</h2>
            <div class="sub">${state.files.size} files • ${formatSize(state.totalSize)}</div>
        </div>
    </div>`;

    // Stats grid
    html += `<div class="overview-grid">
        <div class="stat-card"><div class="label">Total Files</div><div class="value">${state.files.size}</div></div>
        <div class="stat-card"><div class="label">Total Size</div><div class="value">${formatSize(state.totalSize)}</div></div>
        <div class="stat-card"><div class="label">Master File</div><div class="value" style="font-size:14px">${state.masterFile ? esc(getFileName(state.masterFile)) : 'None'}</div></div>
        <div class="stat-card"><div class="label">Modified</div><div class="value">${state.modifiedCount}</div></div>
    </div>`;

    // ESP info
    if (state.espInfo) {
        const ei = state.espInfo;
        html += `<div class="section-title"><i class="fas fa-gem" style="color:var(--accent-gold)"></i> Plugin Information</div>`;
        html += `<div class="overview-grid" style="margin-bottom:16px">
            <div class="stat-card"><div class="label">Version</div><div class="value">${ei.version ? ei.version.toFixed(2) : 'N/A'}</div></div>
            <div class="stat-card"><div class="label">Records</div><div class="value">${ei.numRecords.toLocaleString()}</div></div>
            <div class="stat-card"><div class="label">Author</div><div class="value" style="font-size:14px">${esc(ei.author) || 'N/A'}</div></div>
            <div class="stat-card"><div class="label">Record Groups</div><div class="value">${ei.groups.length}</div></div>
        </div>`;

        if (ei.masters.length > 0) {
            html += `<div class="section-title"><i class="fas fa-project-diagram" style="color:var(--accent-blue)"></i> Master Dependencies</div>
                <ul class="masters-list" style="margin-bottom:16px">`;
            for (const m of ei.masters) {
                html += `<li><i class="fas fa-link"></i> ${esc(m)}</li>`;
            }
            html += '</ul>';
        }

        if (ei.groups.length > 0) {
            html += `<div class="section-title"><i class="fas fa-cubes" style="color:var(--accent-purple)"></i> Record Types</div>
                <div class="records-grid" style="margin-bottom:16px">`;
            const recordTypeNames = {
                'GMST':'Game Settings','KYWD':'Keywords','GLOB':'Globals','FACT':'Factions',
                'MGEF':'Magic Effects','ENCH':'Enchantments','SPEL':'Spells','ARMO':'Armor',
                'WEAP':'Weapons','NPC_':'NPCs','QUST':'Quests','PERK':'Perks','ALCH':'Potions',
                'SCRL':'Scrolls','MISC':'Misc Items','FLST':'Form Lists','COBJ':'Constructibles',
                'HAZD':'Hazards','EXPL':'Explosions','PROJ':'Projectiles','EFSH':'Effect Shaders',
                'DUAL':'Dual Cast','LVLI':'Leveled Items','LVLN':'Leveled NPCs','LVSP':'Leveled Spells',
                'BOOK':'Books','STAT':'Statics','ACTI':'Activators','IDLE':'Idles','PACK':'Packages',
                'CELL':'Cells','WRLD':'Worldspaces','DIAL':'Dialogs','LIGH':'Lights','DOOR':'Doors',
                'CONT':'Containers','INGR':'Ingredients','SNDR':'Sounds','EQUP':'Equip Types','MESG':'Messages',
                'RACE':'Races','HDPT':'Head Parts','TXST':'Texture Sets','CLAS':'Classes',
                'ARMA':'Armor Addons','OTFT':'Outfits','MATT':'Materials','RFCT':'Visual Effects',
                'MUST':'Music','TREE':'Trees','FLOR':'Flora','FURN':'Furniture','AMMO':'Ammo',
                'AVIF':'Actor Values','LCTN':'Locations','SHOU':'Shouts','WOOP':'Words of Power',
                'VTYP':'Voice Types','CSTY':'Combat Styles','KEYM':'Keys','MOVT':'Movement Types',
                'MSTT':'Moveable Statics','SLGM':'Soul Gems','IPCT':'Impacts','LSCR':'Load Screens'
            };
            for (const g of ei.groups.sort((a,b) => b.count - a.count)) {
                const name = recordTypeNames[g.type] || '';
                html += `<div class="record-badge"><span class="type">${g.type}</span><span class="cnt" title="${esc(name)}">${g.count}${name ? ' ' + name : ''}</span></div>`;
            }
            html += '</div>';
        }
    }

    // File breakdown
    html += `<div class="section-title"><i class="fas fa-folder-tree" style="color:var(--accent-gold)"></i> File Breakdown</div>
        <div class="file-breakdown">`;

    const categories = [
        { icon: 'fas fa-gem', color: 'var(--accent-gold)', label: 'Plugins', ext: ['.esp','.esm','.esl'] },
        { icon: 'fas fa-cube', color: 'var(--accent-blue)', label: 'Meshes (.nif)', ext: ['.nif'] },
        { icon: 'fas fa-image', color: 'var(--accent-purple)', label: 'Textures (.dds)', ext: ['.dds'] },
        { icon: 'fas fa-scroll', color: 'var(--accent-green)', label: 'Scripts (.pex)', ext: ['.pex'] },
        { icon: 'fas fa-code', color: '#7ee787', label: 'Sources (.psc)', ext: ['.psc'] },
        { icon: 'fas fa-desktop', color: 'var(--accent-red)', label: 'Interface', ext: ['.swf'] },
        { icon: 'fas fa-archive', color: 'var(--accent-orange)', label: 'Archives', ext: ['.bsa','.ba2'] },
        { icon: 'fas fa-file', color: 'var(--text-secondary)', label: 'Other', ext: null }
    ];

    for (const cat of categories) {
        let count;
        if (cat.ext === null) {
            count = 0;
            for (const [path] of state.files) {
                const ext = getExt(path);
                if (!categories.slice(0, -1).some(c => c.ext.includes(ext))) count++;
            }
        } else {
            count = 0;
            for (const [path] of state.files) {
                if (cat.ext.includes(getExt(path))) count++;
            }
        }
        if (count > 0) {
            html += `<div class="breakdown-item">
                <div class="bi-icon" style="color:${cat.color}"><i class="${cat.icon}"></i></div>
                <div class="bi-info"><div class="bi-count">${count}</div><div>${cat.label}</div></div>
            </div>`;
        }
    }

    html += '</div>';
    panel.innerHTML = html;
}

// ===========================================================================
// EXPORT
// ===========================================================================
async function exportZip() {
    if (state.files.size === 0) {
        toast('No files to export', 'warning');
        return;
    }

    saveEditorContent();
    showLoading('Building ZIP...', 'Compressing files...');

    try {
        const zip = new JSZip();
        let processed = 0;

        for (const [path, file] of state.files) {
            zip.file(path, file.data);
            processed++;
            if (processed % 20 === 0) {
                showLoading('Building ZIP...', `${processed}/${state.files.size} files`);
                await new Promise(r => setTimeout(r, 0)); // yield
            }
        }

        showLoading('Generating archive...', 'This may take a moment');
        const blob = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } });

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = (state.modName || 'mod') + '.zip';
        a.click();
        URL.revokeObjectURL(url);

        toast('Exported: ' + a.download + ' (' + formatSize(blob.size) + ')', 'success');
    } catch (err) {
        console.error(err);
        toast('Export error: ' + err.message, 'error');
    }
    hideLoading();
}

// ===========================================================================
// UI HELPERS
// ===========================================================================
function showEditorLayout() {
    document.getElementById('welcome-screen').style.display = 'none';
    document.getElementById('editor-layout').style.display = 'flex';
}

function closeMod() {
    if (state.modifiedCount > 0 && !confirm('You have unsaved changes. Close anyway?')) return;
    state.files.clear();
    state.modName = '';
    state.masterFile = null;
    state.espInfo = null;
    state.selectedFile = null;
    state.totalSize = 0;
    state.modifiedCount = 0;
    if (state.editor) { state.editor.setValue(''); state.editorDirty = false; }
    document.getElementById('welcome-screen').style.display = '';
    document.getElementById('editor-layout').style.display = 'none';
    document.getElementById('nav-mod-name').textContent = '';
    updateStatusBar();
}

function switchTab(tabEl) {
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    tabEl.classList.add('active');
    const panelId = tabEl.dataset.panel;
    document.getElementById(panelId).classList.add('active');
    if (panelId === 'editor-panel' && state.editor) {
        setTimeout(() => state.editor.refresh(), 50);
    }
}

function switchTabByName(panelId) {
    const tabEl = document.querySelector(`.tab-item[data-panel="${panelId}"]`);
    if (tabEl) switchTab(tabEl);
}

function refreshUI() {
    renderFileTree();
    renderOverview();
    updateStatusBar();
    updateModifiedCount();
}

function updateStatusBar() {
    document.getElementById('sb-files').textContent = state.files.size + ' files';
    document.getElementById('sb-size').textContent = formatSize(state.totalSize);
    document.getElementById('sb-modified').textContent = state.modifiedCount + ' modified';
    document.getElementById('sb-status').textContent = state.masterFile ? 'Master: ' + getFileName(state.masterFile) : 'Ready';
}

function updateModifiedCount() {
    let count = 0;
    for (const [, f] of state.files) { if (f.modified) count++; }
    state.modifiedCount = count;
    document.getElementById('sb-modified').textContent = count + ' modified';
}

function refreshTreeModified() {
    document.querySelectorAll('#file-tree .tree-file').forEach(el => {
        const path = el.dataset.path;
        const file = state.files.get(path);
        if (file?.modified) el.classList.add('modified');
        else el.classList.remove('modified');
    });
}

function showModInfo() {
    switchTabByName('overview-panel');
}

function showLoading(msg, sub) {
    const el = document.getElementById('loading');
    el.classList.add('show');
    el.querySelector('.msg').textContent = msg || 'Loading...';
    el.querySelector('.sub-msg').textContent = sub || '';
}

function hideLoading() {
    document.getElementById('loading').classList.remove('show');
}

function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = 'toast-msg';
    const colors = { success: 'var(--accent-green)', error: 'var(--accent-red)', warning: 'var(--accent-orange)', info: 'var(--accent-blue)' };
    const icons = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    el.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}" style="color:${colors[type] || colors.info}"></i> ${esc(msg)}`;
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateY(20px)'; el.style.transition = 'all 0.3s'; setTimeout(() => el.remove(), 300); }, 3000);
}

// ===========================================================================
// RESIZE HANDLE
// ===========================================================================
function initResizeHandle() {
    const handle = document.getElementById('resize-handle');
    const sidebar = document.getElementById('sidebar');
    let startX, startWidth;

    handle.addEventListener('mousedown', (e) => {
        startX = e.clientX;
        startWidth = sidebar.offsetWidth;
        handle.classList.add('dragging');
        document.addEventListener('mousemove', onResize);
        document.addEventListener('mouseup', stopResize);
        e.preventDefault();
    });

    function onResize(e) {
        const newWidth = startWidth + (e.clientX - startX);
        sidebar.style.width = Math.max(150, Math.min(600, newWidth)) + 'px';
    }

    function stopResize() {
        handle.classList.remove('dragging');
        document.removeEventListener('mousemove', onResize);
        document.removeEventListener('mouseup', stopResize);
        if (state.editor) state.editor.refresh();
    }
}

// ===========================================================================
// PAPYRUS SYNTAX MODE FOR CODEMIRROR
// ===========================================================================
function registerPapyrusMode() {
    if (typeof CodeMirror === 'undefined') return;

    CodeMirror.defineMode('papyrus', function() {
        const keywords = new Set([
            'scriptname','extends','property','endproperty','function','endfunction','event','endevent',
            'state','endstate','if','elseif','else','endif','while','endwhile','return','import',
            'auto','autoreadonly','hidden','conditional','native','global','new','length',
            'as','is','true','false','none','self','parent'
        ]);
        const types = new Set([
            'int','float','bool','string','objectreference','actor','form','quest','spell',
            'activator','weapon','armor','potion','ingredient','book','container','door',
            'enchantment','explosion','faction','flora','furniture','hazard','idle','key',
            'keyword','leveleditem','light','location','magiceffect','message','miscobject',
            'musictype','package','perk','projectile','race','scroll','shout','soulgem',
            'sound','static','textureset','topic','voicetype','weather','wordofpower','cell',
            'effectshader','imagespacemodifier','impactdataset','visualeffect','alias',
            'referencealias','locationalias','activemagiceffect','constructibleobject',
            'formlist','globalvariable','leveledactor','leveledspell','outfit','scene',
            'worldspace','ammo','associationtype','combatstytle','encounterzone',
            'equpslot','headpart','movementtype','talkingactivator','utility','debug',
            'game','math','input','ui','stringutil','storageutil'
        ]);

        return {
            startState: () => ({ inComment: false }),
            token: function(stream, st) {
                // Block comments
                if (st.inComment) {
                    if (stream.match(/.*?\;\//) || stream.skipToEnd()) { st.inComment = false; }
                    return 'comment';
                }
                if (stream.match(/\/\;/)) { st.inComment = true; return 'comment'; }

                // Line comments
                if (stream.match(';')) { stream.skipToEnd(); return 'comment'; }

                // Strings
                if (stream.match(/"[^"]*"/)) return 'string';

                // Numbers
                if (stream.match(/0x[0-9a-fA-F]+/) || stream.match(/-?\d+\.?\d*/)) return 'number';

                // Words
                if (stream.match(/[a-zA-Z_]\w*/)) {
                    const word = stream.current().toLowerCase();
                    if (keywords.has(word)) return 'keyword';
                    if (types.has(word)) return 'type';
                    return 'variable';
                }

                // Operators
                if (stream.match(/[+\-*/%=<>!&|]+/)) return 'operator';

                stream.next();
                return null;
            }
        };
    });
    CodeMirror.defineMIME('text/x-papyrus', 'papyrus');
}

// ===========================================================================
// UTILITIES
// ===========================================================================
function readString(buffer, offset, length) {
    let s = '';
    for (let i = 0; i < length && offset + i < buffer.byteLength; i++) {
        s += String.fromCharCode(buffer[offset + i]);
    }
    return s;
}

function readZString(buffer, offset, maxLen) {
    let s = '';
    for (let i = 0; i < maxLen && offset + i < buffer.byteLength; i++) {
        if (buffer[offset + i] === 0) break;
        s += String.fromCharCode(buffer[offset + i]);
    }
    return s;
}

function getExt(path) {
    const dot = path.lastIndexOf('.');
    return dot >= 0 ? path.substring(dot).toLowerCase() : '';
}

function getFileName(path) {
    const slash = Math.max(path.lastIndexOf('/'), path.lastIndexOf('\\'));
    return slash >= 0 ? path.substring(slash + 1) : path;
}

function cleanPath(path) {
    return path.replace(/\\/g, '/').replace(/^\/+/, '').replace(/\/+$/, '');
}

function detectCommonRoot(paths) {
    if (paths.length === 0) return '';
    const parts = paths[0].replace(/\\/g, '/').split('/');
    let common = '';
    for (let i = 0; i < parts.length - 1; i++) {
        const test = parts.slice(0, i + 1).join('/') + '/';
        if (paths.every(p => p.replace(/\\/g, '/').startsWith(test))) {
            common = test;
        } else {
            break;
        }
    }
    return common;
}

function formatSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getFileIconClass(ext) {
    const icons = {
        '.esp':'fas fa-gem', '.esm':'fas fa-gem', '.esl':'fas fa-gem',
        '.pex':'fas fa-scroll', '.psc':'fas fa-code',
        '.nif':'fas fa-cube', '.dds':'fas fa-image',
        '.bsa':'fas fa-archive', '.ba2':'fas fa-archive',
        '.seq':'fas fa-list-ol', '.swf':'fas fa-desktop',
        '.txt':'fas fa-file-alt', '.cfg':'fas fa-cog', '.ini':'fas fa-cog',
        '.json':'fas fa-brackets-curly', '.xml':'fas fa-file-code',
        '.wav':'fas fa-music', '.xwm':'fas fa-music', '.fuz':'fas fa-music',
        '.lip':'fas fa-comment', '.hkx':'fas fa-running',
        '.bik':'fas fa-video', '.btt':'fas fa-tree', '.lst':'fas fa-list'
    };
    return icons[ext] || 'fas fa-file';
}

function getFileColorClass(ext) {
    const colors = {
        '.esp':'ft-esp', '.esm':'ft-esm', '.esl':'ft-esl',
        '.pex':'ft-pex', '.psc':'ft-psc',
        '.nif':'ft-nif', '.dds':'ft-dds',
        '.bsa':'ft-bsa', '.ba2':'ft-ba2', '.seq':'ft-seq',
        '.swf':'ft-swf', '.txt':'ft-txt', '.cfg':'ft-cfg', '.ini':'ft-ini',
        '.json':'ft-json', '.xml':'ft-xml'
    };
    return colors[ext] || 'ft-default';
}

function esc(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===========================================================================
// KEYBOARD SHORTCUTS
// ===========================================================================
document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        saveEditorContent();
    }
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportZip();
    }
});

// Prevent accidental page close with unsaved changes
window.addEventListener('beforeunload', (e) => {
    if (state.modifiedCount > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// ===========================================================================
// OPTIONS POPUP
// ===========================================================================
function openOptions() {
    document.getElementById('options-overlay').classList.add('show');
}
function closeOptions() {
    document.getElementById('options-overlay').classList.remove('show');
}

// ===========================================================================
// AI PANEL & GROQ INTEGRATION
// ===========================================================================
const aiState = {
    active: false,
    apiKey: '',
    model: 'llama-3.3-70b-versatile',
    chatHistory: [],
    lastAnalysis: null,
    suggestions: [],
    analysisTimer: null,
    analysisCount: 0
};

function openAIPanel() {
    document.getElementById('ai-overlay').classList.add('show');
}
function closeAIPanel() {
    document.getElementById('ai-overlay').classList.remove('show');
}

function toggleAIActivation() {
    const keySection = document.getElementById('ai-key-section');
    const btn = document.getElementById('ai-activate-btn');
    const btnText = document.getElementById('ai-activate-text');
    const badge = document.getElementById('ai-status-badge');
    const badgeText = document.getElementById('ai-status-text');

    if (!aiState.active) {
        keySection.classList.add('show');
        const key = document.getElementById('ai-api-key').value.trim();
        if (key && key.startsWith('gsk_')) {
            aiState.apiKey = key;
            aiState.active = true;
            aiState.model = document.getElementById('ai-model-select').value;
            btnText.textContent = 'Désactiver IA';
            btn.classList.remove('ai-btn-primary');
            btn.classList.add('ai-btn-success');
            badge.classList.remove('disconnected');
            badge.classList.add('connected');
            badgeText.textContent = 'Connecté';
            toast('IA activée avec succès !', 'success');
            startCodeAnalysisLoop();
        } else if (!key) {
            toast('Entrez votre clé API Groq pour activer l\'IA', 'warning');
        } else {
            toast('Clé API invalide — doit commencer par gsk_', 'error');
        }
    } else {
        aiState.active = false;
        aiState.apiKey = '';
        btnText.textContent = 'Activer IA';
        btn.classList.remove('ai-btn-success');
        btn.classList.add('ai-btn-primary');
        badge.classList.remove('connected');
        badge.classList.add('disconnected');
        badgeText.textContent = 'Désactivé';
        document.getElementById('ai-notif-btn').classList.remove('show');
        if (aiState.analysisTimer) clearTimeout(aiState.analysisTimer);
        toast('IA désactivée', 'info');
    }
}

function toggleKeyVisibility() {
    const input = document.getElementById('ai-api-key');
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Groq API call
async function callGroqAPI(messages, temperature = 0.7) {
    if (!aiState.active || !aiState.apiKey) {
        toast('Activez l\'IA d\'abord', 'warning');
        return null;
    }
    try {
        const resp = await fetch('https://api.groq.com/openai/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + aiState.apiKey,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                model: document.getElementById('ai-model-select').value,
                messages: messages,
                temperature: temperature,
                max_tokens: 4096
            })
        });
        if (!resp.ok) {
            const err = await resp.json().catch(() => ({}));
            throw new Error(err.error?.message || 'Erreur API: ' + resp.status);
        }
        const data = await resp.json();
        return data.choices?.[0]?.message?.content || '';
    } catch (err) {
        console.error('Groq API error:', err);
        toast('Erreur Groq: ' + err.message, 'error');
        return null;
    }
}

// Chat
async function sendAIChat() {
    const input = document.getElementById('ai-chat-input');
    const msg = input.value.trim();
    if (!msg) return;
    if (!aiState.active) { toast('Activez l\'IA d\'abord', 'warning'); return; }

    input.value = '';
    appendChatMessage('user', msg);

    // Build context from current file
    let systemPrompt = `Tu es un assistant expert en modding Bethesda (Skyrim, Fallout 4, Starfield). Tu analyses le code des mods et expliques en détail les effets in-game de chaque modification. Tu proposes des améliorations concrètes avec le code complet et les propriétés détaillées. Tu écris directement les codes possibles avec leurs propriétés. Tu prends des initiatives en termes de propositions. Réponds en français.`;

    if (state.selectedFile && state.editor) {
        const code = state.editor.getValue();
        const path = state.selectedFile;
        systemPrompt += `\n\nFichier actuellement ouvert: ${path}\nContenu du fichier:\n\`\`\`\n${code.substring(0, 6000)}\n\`\`\``;
    }

    const messages = [
        { role: 'system', content: systemPrompt },
        ...aiState.chatHistory.slice(-10),
        { role: 'user', content: msg }
    ];

    appendChatMessage('assistant', '⏳ Analyse en cours...');

    const response = await callGroqAPI(messages);
    // Remove loading message
    const messagesEl = document.getElementById('ai-chat-messages');
    if (messagesEl.lastChild) messagesEl.removeChild(messagesEl.lastChild);

    if (response) {
        aiState.chatHistory.push({ role: 'user', content: msg });
        aiState.chatHistory.push({ role: 'assistant', content: response });
        appendChatMessage('assistant', response);
    } else {
        appendChatMessage('assistant', 'Erreur lors de la communication avec l\'IA. Vérifiez votre clé API et le modèle sélectionné.');
    }
}

function appendChatMessage(role, content) {
    const container = document.getElementById('ai-chat-messages');
    const div = document.createElement('div');
    div.className = 'ai-msg ' + role;
    const roleLabel = role === 'user' ? 'Vous' : 'Assistant IA';

    let rendered = content;
    if (typeof marked !== 'undefined' && role === 'assistant') {
        try { rendered = marked.parse(content); } catch(e) { rendered = esc(content).replace(/\n/g, '<br>'); }
    } else {
        rendered = esc(content).replace(/\n/g, '<br>');
    }

    div.innerHTML = `<div class="msg-role">${roleLabel}</div>${rendered}`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// ===========================================================================
// REAL-TIME CODE ANALYSIS
// ===========================================================================
let lastAnalyzedCode = '';

function startCodeAnalysisLoop() {
    if (!aiState.active) return;
    if (aiState.analysisTimer) clearTimeout(aiState.analysisTimer);

    aiState.analysisTimer = setInterval(() => {
        if (!aiState.active || !state.editor || !state.selectedFile) return;
        const currentCode = state.editor.getValue();
        if (currentCode === lastAnalyzedCode || currentCode.length < 10) return;

        // Only analyze if code changed
        lastAnalyzedCode = currentCode;
        runCodeAnalysis(currentCode, state.selectedFile);
    }, 5000);
}

async function runCodeAnalysis(code, filePath) {
    if (!aiState.active) return;

    const ext = getExt(filePath);
    const systemMsg = `Tu es un expert en modding Bethesda. Analyse ce fichier de mod et retourne un JSON valide (pas de markdown, juste le JSON brut) avec cette structure exacte:
{
  "summary": "Résumé court de ce que fait ce fichier",
  "gameEffects": "Description des effets in-game de ce code",
  "suggestions": [
    {"title": "Titre de la suggestion", "description": "Description détaillée de l'amélioration proposée et son impact in-game", "code": "code complet de la modification proposée"}
  ],
  "warnings": ["avertissements éventuels"]
}
Propose entre 2 et 5 suggestions concrètes de modifications possibles avec le code détaillé et les propriétés. Explique l'impact in-game de chaque suggestion. Fichier: ${filePath}`;

    const messages = [
        { role: 'system', content: systemMsg },
        { role: 'user', content: `Analyse ce code:\n\`\`\`\n${code.substring(0, 6000)}\n\`\`\`` }
    ];

    const response = await callGroqAPI(messages, 0.3);
    if (!response) return;

    try {
        // Try to parse JSON from response (may be wrapped in markdown)
        let json = response;
        const jsonMatch = response.match(/\{[\s\S]*\}/);
        if (jsonMatch) json = jsonMatch[0];
        const analysis = JSON.parse(json);

        aiState.lastAnalysis = analysis;
        aiState.suggestions = analysis.suggestions || [];
        aiState.analysisCount++;

        // Show green notification
        if (aiState.suggestions.length > 0) {
            document.getElementById('ai-notif-btn').classList.add('show');
        }
    } catch (e) {
        console.warn('AI analysis parse error:', e);
        // Even if JSON parse fails, store raw response as suggestion
        aiState.lastAnalysis = { summary: response.substring(0, 200), suggestions: [{ title: 'Analyse IA', description: response, code: '' }] };
        aiState.suggestions = aiState.lastAnalysis.suggestions;
        document.getElementById('ai-notif-btn').classList.add('show');
    }
}

function showAISuggestions() {
    document.getElementById('ai-notif-btn').classList.remove('show');
    const overlay = document.getElementById('ai-suggestions-overlay');
    const list = document.getElementById('ai-suggestions-list');

    if (!aiState.lastAnalysis) {
        list.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px">Aucune suggestion disponible. Sélectionnez un fichier dans l\'éditeur.</p>';
        overlay.classList.add('show');
        return;
    }

    const a = aiState.lastAnalysis;
    let html = '';

    if (a.summary) {
        html += `<div class="ai-suggestion-card" style="border-left:3px solid var(--accent-blue)">
            <div class="sug-title">📋 Résumé</div>
            <div class="sug-desc">${esc(a.summary)}</div>
        </div>`;
    }
    if (a.gameEffects) {
        html += `<div class="ai-suggestion-card" style="border-left:3px solid var(--accent-gold)">
            <div class="sug-title">🎮 Effets In-Game</div>
            <div class="sug-desc">${esc(a.gameEffects)}</div>
        </div>`;
    }
    if (a.warnings && a.warnings.length > 0) {
        html += `<div class="ai-suggestion-card" style="border-left:3px solid var(--accent-red)">
            <div class="sug-title">⚠️ Avertissements</div>
            <div class="sug-desc">${a.warnings.map(w => esc(w)).join('<br>')}</div>
        </div>`;
    }

    if (a.suggestions && a.suggestions.length > 0) {
        for (const s of a.suggestions) {
            html += `<div class="ai-suggestion-card" onclick="applyAISuggestion(this)" data-code="${esc(s.code || '')}">
                <div class="sug-title" style="color:var(--accent-green)">✨ ${esc(s.title)}</div>
                <div class="sug-desc">${esc(s.description)}</div>
                ${s.code ? `<pre><code>${esc(s.code)}</code></pre>` : ''}
            </div>`;
        }
    }

    if (!html) html = '<p style="color:var(--text-secondary);text-align:center;padding:20px">Aucune suggestion pour le moment.</p>';

    list.innerHTML = html;
    overlay.classList.add('show');
}

function closeAISuggestions() {
    document.getElementById('ai-suggestions-overlay').classList.remove('show');
}

function applyAISuggestion(el) {
    const code = el.dataset.code;
    if (!code || !state.editor) return;
    // Insert suggestion as comment + code at cursor position
    const cursor = state.editor.getCursor();
    state.editor.replaceRange('\n// --- Suggestion IA ---\n' + code + '\n// --- Fin Suggestion ---\n', cursor);
    closeAISuggestions();
    toast('Suggestion insérée dans l\'éditeur', 'success');
}

// ===========================================================================
// COMPRESSED FILE SUPPORT (.rar, .7z)
// ===========================================================================
async function handleCompressedFile(file) {
    // Use libarchive.js for .rar and .7z
    if (typeof Archive === 'undefined') {
        toast('Bibliothèque de décompression en cours de chargement...', 'info');
        // Wait for libarchive to load
        await new Promise(r => setTimeout(r, 2000));
        if (typeof Archive === 'undefined') {
            toast('Impossible de charger la bibliothèque de décompression', 'error');
            return false;
        }
    }

    showLoading('Décompression de ' + file.name + '...', 'Lecture de l\'archive...');
    try {
        const archive = await Archive.open(file);
        const extractedFiles = await archive.extractFiles();

        let processed = 0;
        for (const [path, fileData] of Object.entries(extractedFiles)) {
            if (!fileData || fileData.size === 0) continue;
            const buffer = await fileData.arrayBuffer();
            const data = new Uint8Array(buffer);
            const cleanedPath = cleanPath(path);
            if (!cleanedPath) continue;
            state.files.set(cleanedPath, { data, modified: false });
            state.totalSize += data.byteLength;
            processed++;
        }

        if (!state.modName) {
            state.modName = file.name.replace(/\.(rar|7z)$/i, '');
        }

        toast('Extrait ' + processed + ' fichier(s) de ' + file.name, 'success');
        return true;
    } catch (err) {
        console.error('Archive extraction error:', err);
        // Fallback: try JSZip for some formats
        try {
            await importZip(file);
            return true;
        } catch (e2) {
            toast('Format d\'archive non supporté: ' + file.name + '. Essayez un fichier .zip.', 'error');
            return false;
        }
    }
}

// ===========================================================================
// 3D PREVIEW IFRAME COMMUNICATION
// ===========================================================================
function sendFileTo3DPreview(path, data) {
    const iframe = document.getElementById('preview-iframe');
    if (!iframe || !iframe.contentWindow) return;

    try {
        // Send file data to 3D.php via postMessage
        const ext = getExt(path);
        const base64 = btoa(String.fromCharCode(...data.slice(0, Math.min(data.length, 5242880))));
        iframe.contentWindow.postMessage({
            type: 'loadFile',
            fileName: getFileName(path),
            fileExt: ext,
            fileData: base64,
            filePath: path
        }, '*');
    } catch (e) {
        console.warn('Could not send file to 3D preview:', e);
    }
}

// Override preview for visual files
function show3DPreview(path, file) {
    switchTabByName('preview-panel');
    sendFileTo3DPreview(path, file.data);
}
</script>
</body>
</html>
