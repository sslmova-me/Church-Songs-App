<?php
// ---------- PHP BACKEND: Handle JSON storage ----------
$dataFile = __DIR__ . '/data.json';

function loadData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return ['songs' => [], 'tags' => [], 'songTags' => [], 'mixes' => [], 'mixSongs' => []];
    }
    $content = file_get_contents($dataFile);
    $data = json_decode($content, true);
    return $data ?: ['songs' => [], 'tags' => [], 'songTags' => [], 'mixes' => [], 'mixSongs' => []];
}

function saveData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'load') {
        echo json_encode(loadData());
        exit;
    }
    if ($_GET['action'] === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $newData = json_decode($input, true);
        if ($newData) {
            saveData($newData);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
        }
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>🎵 OrinTag · Server Edition</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; }
    body { background: #f4f7fb; min-height: 100vh; }
    #app { max-width: 1200px; margin: 0 auto; position: relative; min-height: 100vh; background: #f4f7fb; }
    .top-bar { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 1.5rem; background: transparent; }
    .menu-btn { background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #1e293b; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s; }
    .menu-btn:hover { background: rgba(0,0,0,0.05); }
    .app-title { font-size: 1.4rem; font-weight: 700; background: linear-gradient(135deg, #1e293b, #2c3e50); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .top-bar-right { width: 44px; }
    .drawer-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); backdrop-filter: blur(2px); z-index: 2000; opacity: 0; visibility: hidden; transition: 0.2s; }
    .drawer { position: fixed; top: 0; left: 0; width: 280px; height: 100%; background: white; box-shadow: 4px 0 20px rgba(0,0,0,0.1); z-index: 2100; transform: translateX(-100%); transition: transform 0.25s ease; display: flex; flex-direction: column; }
    .drawer.open { transform: translateX(0); }
    .drawer-overlay.open { opacity: 1; visibility: visible; }
    .drawer-header { padding: 1.5rem 1.2rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
    .drawer-header h3 { font-size: 1.3rem; color: #1e293b; }
    .drawer-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
    .drawer-nav { flex: 1; padding: 1rem 0.5rem; }
    .drawer-item { display: flex; align-items: center; gap: 14px; padding: 0.9rem 1rem; margin: 4px 0; border-radius: 16px; cursor: pointer; font-size: 1.1rem; font-weight: 500; color: #334155; transition: background 0.15s; }
    .drawer-item:hover { background: #f1f5f9; }
    .drawer-item.active { background: #eef2ff; color: #1e40af; }
    .drawer-badge { margin-left: auto; background: #e2e8f0; padding: 2px 10px; border-radius: 40px; font-size: 0.8rem; font-weight: 500; color: #1e293b; }
    .drawer-footer { padding: 1rem; border-top: 1px solid #e2e8f0; }
    .panel-container { padding: 0 1.5rem 1rem; }
    .panel { display: none; }
    .panel.active { display: block; }
    .panel-header-row { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 10px; }
    .panel-title { display: flex; align-items: center; gap: 8px; }
    .panel-title h2 { font-size: 1.6rem; font-weight: 700; color: #0f172a; }
    .sort-select { padding: 0.3rem 0.8rem; border-radius: 40px; border: 1px solid #cbd5e1; background: white; font-size: 0.85rem; }
    .search-full { width: 100%; padding: 10px 16px; border-radius: 60px; border: 1px solid #cbd5e1; background: white; font-size: 0.95rem; margin-bottom: 0.4rem; }
    .filter-badge { margin-bottom: 0.4rem; min-height: 24px; }
    .songs-list, .tags-list, .mixes-list { display: flex; flex-direction: column; gap: 6px; max-height: calc(100vh - 200px); overflow-y: auto; padding-right: 2px; }
    .song-card, .mix-card { background: white; border-radius: 18px; padding: 0.5rem 0.8rem; box-shadow: 0 4px 10px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.02); transition: all 0.15s; }
    .song-card:hover, .mix-card:hover { box-shadow: 0 8px 16px -6px rgba(0,0,0,0.1); }
    .card-header { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
    .card-icon { font-size: 1.3rem; width: 28px; text-align: center; }
    .card-title { flex: 1; font-weight: 700; font-size: 1rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mix-badge { background: #e2e8f0; padding: 2px 8px; border-radius: 40px; font-size: 0.7rem; font-weight: 500; margin-left: 6px; }
    .expand-toggle { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s, transform 0.2s; }
    .expand-toggle:hover { background: #f1f5f9; }
    .song-card.expanded .expand-toggle, .mix-card.expanded .expand-toggle { transform: rotate(90deg); }
    .card-details { display: none; margin-top: 8px; padding-top: 6px; border-top: 1px dashed #e2e8f0; }
    .song-card.expanded .card-details, .mix-card.expanded .card-details { display: block; }
    .lyrics-preview { font-size: 0.75rem; color: #475569; margin-bottom: 8px; font-style: italic; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tag-chips, .keyphrase-chips { display: flex; flex-wrap: wrap; gap: 5px; margin: 6px 0; }
    .chip { background: #eef2ff; padding: 2px 8px; border-radius: 40px; font-size: 0.65rem; color: #1e40af; cursor: pointer; }
    .chip.keyphrase { background: #fef3c7; color: #92400e; }
    .chip-remove { margin-left: 4px; font-weight: bold; }
    .card-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 6px; }
    .icon-btn { background: none; border: none; font-size: 0.9rem; cursor: pointer; color: #64748b; padding: 3px 6px; border-radius: 40px; }
    .icon-btn:hover { background: #f1f5f9; color: #1e293b; }
    .tag-item, .mix-item { background: white; border-radius: 18px; padding: 0.6rem 0.8rem; box-shadow: 0 4px 10px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
    .tag-left, .mix-left { flex: 1; }
    .tag-name, .mix-name { font-weight: 600; background: #f1f5f9; padding: 3px 10px; border-radius: 40px; display: inline-block; margin-right: 8px; font-size: 0.9rem; }
    .tag-description, .mix-description { font-size: 0.7rem; color: #64748b; margin-top: 2px; }
    .fab-container { position: fixed; bottom: 30px; right: 30px; display: flex; flex-direction: column; gap: 16px; z-index: 1500; }
    .fab { width: 56px; height: 56px; border-radius: 28px; background: #2c3e50; color: white; border: none; box-shadow: 0 10px 20px rgba(44,62,80,0.3); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; transition: all 0.2s; }
    .fab.small { width: 48px; height: 48px; font-size: 1.4rem; background: #475569; }
    .fab:hover { transform: scale(1.05); background: #1e2b38; }
    .bottom-drawer { position: fixed; bottom: 0; left: 50%; width: 100%; max-width: 600px; background: white; border-radius: 32px 32px 0 0; box-shadow: 0 -10px 30px rgba(0,0,0,0.1); z-index: 2500; transform: translateX(-50%) translateY(100%); transition: transform 0.3s ease; padding: 1.5rem; }
    .bottom-drawer.open { transform: translateX(-50%) translateY(0); }
    .drawer-overlay-bottom { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 2400; opacity: 0; visibility: hidden; transition: 0.2s; }
    .drawer-overlay-bottom.open { opacity: 1; visibility: visible; }
    .drawer-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .drawer-options { display: flex; flex-direction: column; gap: 12px; }
    .drawer-option { padding: 16px; background: #f8fafc; border-radius: 20px; text-align: center; font-weight: 500; cursor: pointer; transition: background 0.2s; }
    .drawer-option:hover { background: #eef2ff; }
    .cancel-btn { background: #fee2e2; color: #b91c1c; border: none; padding: 14px; border-radius: 60px; font-weight: 600; cursor: pointer; margin-top: 16px; }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 3000; }
    .modal-content { background: white; width: 90%; max-width: 600px; border-radius: 36px; padding: 1.5rem; max-height: 90vh; overflow-y: auto; animation: fadeUp 0.2s ease; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 1.3rem; font-weight: 700; }
    .modal-header-right { display: flex; align-items: center; gap: 12px; }
    .modal-count-chip { background: #e2e8f0; padding: 0.2rem 0.6rem; border-radius: 40px; font-size: 0.75rem; }
    .modal-tabs { display: flex; gap: 8px; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; }
    .modal-tab { background: none; border: none; padding: 0.4rem 1rem; border-radius: 40px; cursor: pointer; font-weight: 500; }
    .modal-tab.active { background: #2c3e50; color: white; }
    .modal-search, .modal-textarea { margin: 1rem 0; width: 100%; padding: 10px; border-radius: 24px; border: 1px solid #cbd5e1; font-size: 0.9rem; }
    .modal-textarea { min-height: 120px; resize: vertical; }
    .check-list { max-height: 250px; overflow-y: auto; margin: 1rem 0; }
    .check-item { display: flex; align-items: center; gap: 12px; padding: 8px; border-bottom: 1px solid #edf2f7; }
    .check-item label { flex: 1; cursor: pointer; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 1rem; }
    .btn { border: none; background: white; padding: 0.6rem 1.2rem; border-radius: 60px; font-weight: 500; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .btn-primary { background: #2c3e50; color: white; }
    .btn-outline { border: 1px solid #cbd5e1; background: white; }
    .btn-danger { background: #fee2e2; color: #b91c1c; }
    .info-hint { font-size: 0.7rem; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 40px; display: inline-block; }
    .toggle-switch { display: flex; align-items: center; gap: 12px; background: #f1f5f9; padding: 8px 12px; border-radius: 60px; width: fit-content; }
    .toggle-btn { background: white; border: 1px solid #cbd5e1; padding: 4px 12px; border-radius: 40px; cursor: pointer; }
    .toggle-btn.active { background: #2c3e50; color: white; border-color: #2c3e50; }
    .filter-section { margin: 1rem 0; padding: 1rem 0; border-top: 1px solid #e2e8f0; }
    .filter-section h4 { font-size: 0.9rem; margin-bottom: 0.5rem; color: #1e293b; }
    .simple-song-list { max-height: 300px; overflow-y: auto; }
    .simple-song-item { padding: 8px; border-bottom: 1px solid #e2e8f0; }
    .settings-section { background: white; border-radius: 24px; padding: 1.5rem; margin-top: 1rem; }
    .danger-btn { background: #fee2e2; color: #b91c1c; border: none; padding: 12px 20px; border-radius: 60px; font-weight: 600; cursor: pointer; }
    .count-badge { background: #e2e8f0; padding: 0.15rem 0.6rem; border-radius: 40px; font-size: 0.85rem; font-weight: 500; color: #1e293b; }
    .progress-bar-container { width: 100%; background-color: #e2e8f0; border-radius: 20px; margin: 1rem 0; }
    .progress-bar { width: 0%; height: 12px; background-color: #2c3e50; border-radius: 20px; transition: width 0.2s; }
    .similarity-list { max-height: 200px; overflow-y: auto; margin: 1rem 0; background: #f8fafc; border-radius: 20px; padding: 0.5rem; }
    .similarity-item { padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
    .selected-count { font-size: 0.8rem; margin-top: 8px; text-align: right; color: #2c3e50; }
    .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .filter-summary { background: #f1f5f9; padding: 8px 12px; border-radius: 40px; margin-bottom: 1rem; font-size: 0.85rem; color: #1e293b; }
    .assign-song-title { font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.8rem; padding-left: 4px; }
    .btn-outline.active-quick { background: #2c3e50; color: white; border-color: #2c3e50; }
    .arrange-list { display: flex; flex-direction: column; gap: 6px; max-height: 300px; overflow-y: auto; }
    .arrange-item { display: flex; align-items: center; gap: 8px; background: white; padding: 8px 12px; border-radius: 40px; border: 1px solid #e2e8f0; }
    .arrange-item span { flex: 1; }
    .arrange-actions { display: flex; gap: 4px; }
    .arrange-actions .icon-btn { font-size: 1.2rem; padding: 2px 6px; }
    .filtered-badge { background: #fee2e2; color: #b91c1c; }
    @media (max-width: 600px) { .panel-container { padding: 0 0.8rem 1rem; } .fab-container { bottom: 20px; right: 20px; } .panel-title h2 { font-size: 1.4rem; } }
  </style>
</head>
<body>
<div id="app">
  <!-- Top Bar -->
  <div class="top-bar">
    <button class="menu-btn" id="menuToggle">☰</button>
    <div class="app-title">🎵 OrinTag</div>
    <div class="top-bar-right"></div>
  </div>

  <!-- Drawer Overlay & Drawer -->
  <div class="drawer-overlay" id="drawerOverlay"></div>
  <div class="drawer" id="drawer">
    <div class="drawer-header">
      <h3>Menu</h3>
      <button class="drawer-close" id="drawerClose">✖</button>
    </div>
    <div class="drawer-nav">
      <div class="drawer-item active" data-panel="songs"><span>🎵</span> Songs <span class="drawer-badge" id="drawerSongsCount">0</span></div>
      <div class="drawer-item" data-panel="tags"><span>🏷️</span> Tags <span class="drawer-badge" id="drawerTagsCount">0</span></div>
      <div class="drawer-item" data-panel="mixes"><span>🎚️</span> Mixes <span class="drawer-badge" id="drawerMixesCount">0</span></div>
      <div class="drawer-item" data-panel="settings"><span>⚙️</span> Settings</div>
    </div>
    <div class="drawer-footer">
      <button class="btn btn-outline" id="exportBtnDrawer" style="width:100%;">📥 Export</button>
      <button class="btn btn-outline" id="importBtnDrawer" style="width:100%; margin-top:8px;">📤 Import</button>
    </div>
  </div>

  <!-- Main Panels Container -->
  <div class="panel-container">
    <!-- SONGS PANEL -->
    <div class="panel active" id="panel-songs">
      <div class="panel-header-row">
        <div class="panel-title"><h2>🎵 Songs</h2> <span class="count-badge" id="songCountBadge">0</span><span id="filteredCountBadge" class="count-badge filtered-badge">Filtered: 0</span></div>
        <select id="sortSongs" class="sort-select">
          <option value="az">A → Z</option>
          <option value="za">Z → A</option>
          <option value="recent">Most recent</option>
        </select>
      </div>
      <input type="text" id="songSearch" class="search-full" placeholder="🔍 Search songs (title or lyrics)..." autocomplete="off">
      <div id="activeFilterBadge" class="filter-badge info-hint"></div>
      <div id="songsContainer" class="songs-list"></div>
    </div>

    <!-- TAGS PANEL -->
    <div class="panel" id="panel-tags">
      <div class="panel-header-row">
        <div class="panel-title"><h2>🏷️ Tags</h2> <span class="count-badge" id="tagCountBadge">0</span><span id="filteredTagCountBadge" class="count-badge filtered-badge">Filtered: 0</span></div>
        <select id="sortTags" class="sort-select">
          <option value="az">A → Z</option>
          <option value="za">Z → A</option>
          <option value="mostSongs">Most Songs</option>
        </select>
      </div>
      <input type="text" id="tagSearch" class="search-full" placeholder="🔍 Filter tags...">
      <div id="tagsContainer" class="tags-list"></div>
    </div>

    <!-- MIXES PANEL -->
    <div class="panel" id="panel-mixes">
      <div class="panel-header-row">
        <div class="panel-title"><h2>🎚️ Mixes</h2> <span class="count-badge" id="mixCountBadge">0</span><span id="filteredMixCountBadge" class="count-badge filtered-badge">Filtered: 0</span></div>
        <select id="sortMixes" class="sort-select">
          <option value="az">A → Z</option>
          <option value="za">Z → A</option>
          <option value="recent">Most recent</option>
        </select>
      </div>
      <input type="text" id="mixSearch" class="search-full" placeholder="🔍 Filter mixes...">
      <div id="mixesContainer" class="mixes-list"></div>
    </div>

    <!-- SETTINGS PANEL -->
    <div class="panel" id="panel-settings">
      <div class="panel-header-row"><h2>⚙️ Settings</h2></div>
      <div class="settings-section">
        <h3>Data Management</h3>
        <p style="margin: 1rem 0; color: #475569;">Export or import your entire library.</p>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <button class="btn btn-primary" id="exportBtnSettings">📥 Export JSON</button>
          <button class="btn btn-outline" id="importBtnSettings">📤 Import JSON</button>
        </div>
        <hr style="margin: 2rem 0; border: none; border-top: 1px solid #e2e8f0;">
        <h3>Reset</h3>
        <p style="margin: 1rem 0; color: #b91c1c;">Clear all songs, tags, and mixes.</p>
        <button class="danger-btn" id="resetAllDataBtn">⚠️ Reset All Data</button>
      </div>
    </div>
  </div>

  <!-- FABs Container -->
  <div class="fab-container" id="fabContainer"></div>

  <!-- Bottom Drawer for Song Add Actions -->
  <div class="drawer-overlay-bottom" id="bottomDrawerOverlay"></div>
  <div class="bottom-drawer" id="bottomDrawer">
    <div class="drawer-header-row">
      <h3>Add Songs</h3>
      <button class="drawer-close" id="closeBottomDrawer">✖</button>
    </div>
    <div class="drawer-options">
      <div class="drawer-option" id="addSingleSongOption">🎵 Add a Song</div>
      <div class="drawer-option" id="bulkTitlesOption">📚 Bulk Titles</div>
      <div class="drawer-option" id="bulkLyricsOption">📝 Bulk + Lyrics</div>
    </div>
    <button class="cancel-btn" id="cancelBottomDrawer">Cancel</button>
  </div>
</div>

<!-- ======================== MODALS ======================== -->
<!-- Add/Edit Song Modal -->
<div id="songModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span id="songModalTitle">Add Song</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    <input type="text" id="songTitleInput" placeholder="Song title (leave empty to auto‑generate from lyrics)" class="modal-search">
    <textarea id="songLyricsInput" class="modal-textarea" placeholder="📝 Full lyrics (Yoruba/English)..."></textarea>
    <div class="modal-footer">
      <button class="btn btn-outline close-modal">Cancel</button>
      <button id="saveSongBtn" class="btn btn-primary">Save</button>
    </div>
  </div>
</div>

<!-- Song Details Modal (Eye icon) -->
<div id="songDetailsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span>🔍 Song Details</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    <div style="margin-bottom: 1rem;"><strong>Title:</strong> <span id="detailsSongTitle"></span></div>
    <div><strong>Lyrics:</strong></div>
    <div id="detailsSongLyrics" style="white-space: pre-wrap; line-height: 1.5; max-height: 50vh; overflow-y: auto; background: #f8fafc; padding: 1rem; border-radius: 20px; margin-top: 0.5rem;"></div>
    <div class="modal-footer"><button class="btn btn-primary close-modal">Close</button></div>
  </div>
</div>

<!-- Lyrics View Modal -->
<div id="lyricsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span>📄 Full Lyrics</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    <div id="lyricsContent" style="white-space: pre-wrap; line-height: 1.5; max-height: 60vh; overflow-y: auto;"></div>
    <div class="modal-footer"><button class="btn btn-outline close-modal">Close</button></div>
  </div>
</div>

<!-- Add Tag Modal -->
<div id="addTagModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span>Add Tag</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    <input type="text" id="addTagNameInput" placeholder="Tag name (e.g. 'Afrobeat', 'Worship')" class="modal-search">
    <textarea id="addTagDescInput" placeholder="Short description (optional)" class="modal-textarea" rows="2"></textarea>
    <div class="modal-footer">
      <button class="btn btn-outline close-modal">Cancel</button>
      <button id="confirmAddTagBtn" class="btn btn-primary">Save</button>
    </div>
  </div>
</div>

<!-- Edit Tag Modal -->
<div id="editTagModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span id="editTagModalTitle">Edit Tag</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    <div class="modal-tabs">
      <button id="editTagDetailsTab" class="modal-tab active">Edit Details</button>
      <button id="editTagSongsTab" class="modal-tab">Manage Songs</button>
    </div>
    <div id="editTagDetailsPanel">
      <input type="text" id="editTagNameInput" placeholder="Tag name" class="modal-search">
      <textarea id="editTagDescInput" placeholder="Short description (optional)" class="modal-textarea" rows="2"></textarea>
    </div>
    <div id="editTagSongsPanel" style="display: none;">
      <input type="text" id="editTagSongsSearch" placeholder="🔎 Search songs in this tag..." class="modal-search">
      <div id="editTagSongsList" class="check-list"></div>
      <div class="selected-count" id="editTagSongsSelectedCount">0 songs will remain (0 to remove)</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline close-modal">Cancel</button>
      <button id="saveEditTagBtn" class="btn btn-primary">Save Changes</button>
    </div>
  </div>
</div>

<!-- Assign Tags to Song Modal -->
<div id="assignTagsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <span>Assign Tags to Song</span>
      <div class="modal-header-right"><span id="assignTagsCount" class="modal-count-chip">0 tags</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    </div>
    <input type="text" id="assignTagSearch" placeholder="🔎 Search tags..." class="modal-search">
    <div id="assignTagsList" class="check-list"></div>
    <div class="modal-footer">
      <button class="btn btn-outline close-modal">Cancel</button>
      <button id="confirmAssignTagsBtn" class="btn btn-primary">Save Tags</button>
    </div>
  </div>
</div>

<!-- Bulk Add Titles Modal -->
<div id="bulkModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">Bulk Add Song Titles <span class="close-modal" style="cursor:pointer">✖</span></div>
    <p style="font-size:0.8rem;">📌 <strong>Tip:</strong> Start a line with <code>#</code> to mark the beginning of a multi‑line title. Otherwise each line = one title.</p>
    <textarea id="bulkSongsTextarea" rows="10" placeholder="#Glory be to God in the Highest - Amen. For His mercies endureth forever - Amen

#Ta lo da biire, laiye at'orun, ko si o, ko si o

#The Son of God is lifted up

#J E S U S *2, oruko yen o lagbara
Jesu ti Nazareti *2, o ti ran mi lowo"></textarea>
    <div class="modal-footer">
      <button id="bulkAddToTagBtn" class="btn btn-primary">🏷️ Add to tag</button>
      <button id="confirmBulkBtn" class="btn btn-outline">Add All (no tag)</button>
      <button class="btn btn-outline close-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- Bulk Add Lyrics Modal -->
<div id="bulkLyricsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">Bulk Import Songs with Lyrics <span class="close-modal" style="cursor:pointer">✖</span></div>
    <p style="font-size:0.8rem;">📌 Format: <code>#Title</code> on its own line, then <code>---</code> on next line, then lyrics (any lines). Next song starts with <code>#</code> again.</p>
    <textarea id="bulkLyricsTextarea" rows="12" placeholder="#Glory be to God
---
Full lyrics here...
Line 2 of lyrics

#Ta lo da biire
---
More lyrics...
Second line"></textarea>
    <div class="modal-footer">
      <button id="bulkLyricsAddToTagBtn" class="btn btn-primary">🏷️ Add to tag</button>
      <button id="confirmBulkLyricsBtn" class="btn btn-outline">Add All (no tag)</button>
      <button class="btn btn-outline close-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- Bulk Tag Selection Modal -->
<div id="bulkTagSelectModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span>Select tags for imported songs</span><div class="modal-header-right"><span id="bulkTagSelectCount" class="modal-count-chip">0 tags</span><span class="close-modal" style="cursor:pointer">✖</span></div></div>
    <input type="text" id="bulkTagSearch" placeholder="🔎 Search tags..." class="modal-search">
    <div id="bulkTagList" class="check-list"></div>
    <div class="selected-count" id="bulkTagSelectedCount">0 tags selected</div>
    <div class="modal-footer">
      <button id="bulkTagConfirmBtn" class="btn btn-primary">Confirm & Import</button>
      <button class="btn btn-outline close-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- Progress Modal -->
<div id="progressModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">Bulk Import Progress <span class="close-modal" style="cursor:pointer">✖</span></div>
    <div id="progressStatus" style="margin: 0.5rem 0;">Preparing...</div>
    <div class="progress-bar-container"><div id="progressBarFill" class="progress-bar"></div></div>
    <div id="progressDetail" style="font-size:0.85rem; margin:0.5rem 0;"></div>
    <div id="progressSummary" style="margin-top: 1rem; display: none;"></div>
    <div class="modal-footer"><button id="progressCloseBtn" class="btn btn-outline" disabled>Close</button></div>
  </div>
</div>

<!-- Duplicate Modal -->
<div id="duplicateModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span>⚠️ Similar Song Detected</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    <div><strong>New title:</strong> <span id="dupNewTitle"></span></div>
    <div style="margin: 0.8rem 0;"><strong>Existing similar songs:</strong></div>
    <div id="dupSimilarList" class="similarity-list"></div>
    <div class="modal-footer">
      <button id="dupAddBtn" class="btn btn-primary">Add anyway</button>
      <button id="dupSkipBtn" class="btn btn-outline">Skip this song</button>
    </div>
  </div>
</div>

<!-- Tag Filter Modal (with Inclusion and Exclusion) -->
<div id="tagFilterModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span>Filter by Tags</span><div class="modal-header-right"><span id="filterTagsCount" class="modal-count-chip">0 tags</span><span class="close-modal" style="cursor:pointer">✖</span></div></div>
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 1rem;">
      <div class="toggle-switch" style="margin-bottom: 0;">
        <span>Match type:</span>
        <button id="toggleOrBtn" class="toggle-btn active">OR</button>
        <button id="toggleAndBtn" class="toggle-btn">AND</button>
      </div>
      <button id="untaggedFilterBtn" class="btn btn-danger" style="padding: 0.3rem 1rem;">🚫 Untagged</button>
    </div>
    <div class="filter-section">
      <h4>✅ Include songs with these tags:</h4>
      <input type="text" id="filterTagSearch" placeholder="🔎 Search tags..." class="modal-search">
      <div id="filterTagsList" class="check-list" style="max-height: 180px;"></div>
      <div class="filter-chips" id="selectedFilterChips"></div>
    </div>
    <div class="filter-section">
      <h4>🚫 Exclude songs with these tags:</h4>
      <input type="text" id="excludeTagSearch" placeholder="🔎 Search tags to exclude..." class="modal-search">
      <div id="excludeTagsList" class="check-list" style="max-height: 180px;"></div>
      <div class="filter-chips" id="selectedExcludeChips"></div>
    </div>
    <div class="modal-footer">
      <button id="applyFilterBtn" class="btn btn-primary">Apply</button>
      <button id="showFilterPreviewBtn" class="btn btn-outline">Show</button>
      <button class="btn btn-outline close-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- Preview Filter Results Modal -->
<div id="previewFilterModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span>Filtered Songs Preview</span><div class="modal-header-right"><span id="previewCountChip" class="modal-count-chip">0 songs</span><span class="close-modal" style="cursor:pointer">✖</span></div></div>
    <div id="previewFilterSummary" class="filter-summary"></div>
    <input type="text" id="previewSearch" placeholder="🔎 Search within results..." class="modal-search">
    <div id="previewSongsList" class="simple-song-list"></div>
    <div class="modal-footer"><button class="btn btn-primary close-modal">Close</button></div>
  </div>
</div>

<!-- View Songs in Tag Modal -->
<div id="viewTagSongsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span id="viewTagSongsTitle">Songs in Tag</span><div class="modal-header-right"><span id="viewTagSongsCount" class="modal-count-chip">0 songs</span><span class="close-modal" style="cursor:pointer">✖</span></div></div>
    <input type="text" id="viewTagSongsSearch" placeholder="🔎 Search within tag..." class="modal-search">
    <div id="viewTagSongsList" class="simple-song-list"></div>
    <div class="modal-footer"><button class="btn btn-primary close-modal">Close</button></div>
  </div>
</div>

<!-- Add Songs to Tag Modal -->
<div id="addSongsToTagModal" class="modal">
  <div class="modal-content">
    <div class="modal-header" id="addSongsToTagTitle">Add songs to tag <div class="modal-header-right"><span id="addSongsToTagCountChip" class="modal-count-chip">0 songs</span><span class="close-modal" style="cursor:pointer">✖</span></div></div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
      <input type="text" id="addSongToTagSearch" placeholder="🔎 Search songs..." class="modal-search" style="margin:0; flex:1;">
      <button class="btn btn-outline" id="addSongsToTagQuickAddToggle" style="margin-left:8px; padding:0.3rem 1rem;" title="Enable Quick Add">➕ Quick Add</button>
    </div>
    <div style="margin-bottom: 0.5rem;">
      <button class="btn btn-outline" id="addSongsToTagFilterToggle" style="padding: 0.3rem 1rem;">🏷️ Filter by tags</button>
      <span id="addSongsToTagFilterCount" class="modal-count-chip" style="margin-left: 8px;">0</span>
    </div>
    <div id="addSongsToTagFilterPanel" style="display: none; background: #f8fafc; border-radius: 20px; padding: 1rem; margin-bottom: 1rem;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
        <div class="toggle-switch" style="margin: 0;">
          <button id="addSongsToTagOrBtn" class="toggle-btn active">OR</button>
          <button id="addSongsToTagAndBtn" class="toggle-btn">AND</button>
        </div>
        <button id="addSongsToTagClearFilterBtn" class="btn btn-outline" style="padding: 0.2rem 0.8rem;">Clear</button>
      </div>
      <div class="filter-section" style="margin-top: 0; padding-top: 0; border-top: none;">
        <h4>✅ Include</h4>
        <input type="text" id="addSongsToTagIncludeSearch" placeholder="Search tags..." class="modal-search" style="margin: 0.3rem 0;">
        <div id="addSongsToTagIncludeList" class="check-list" style="max-height: 150px;"></div>
      </div>
      <div class="filter-section">
        <h4>🚫 Exclude</h4>
        <input type="text" id="addSongsToTagExcludeSearch" placeholder="Search tags..." class="modal-search" style="margin: 0.3rem 0;">
        <div id="addSongsToTagExcludeList" class="check-list" style="max-height: 150px;"></div>
      </div>
      <div class="filter-chips" id="addSongsToTagFilterChips"></div>
    </div>
    <div id="addSongsToTagList" class="check-list"></div>
    <div class="selected-count" id="addSongsToTagSelectedCount">0 songs selected</div>
    <div class="modal-footer">
      <button id="confirmAddSongsToTagBtn" class="btn btn-primary">Add to tag</button>
      <button class="btn btn-outline close-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- Manage Songs in Mix Modal -->
<div id="manageMixSongsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span id="manageMixSongsTitle">Manage Songs in Mix</span><div class="modal-header-right"><span id="manageMixSongsCount" class="modal-count-chip">0 songs</span><span class="close-modal" style="cursor:pointer">✖</span></div></div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
      <input type="text" id="manageMixSongsSearch" placeholder="🔎 Search songs..." class="modal-search" style="margin:0; flex:1;">
      <button class="btn btn-outline" id="manageMixQuickAddToggle" style="margin-left:8px; padding:0.3rem 1rem;" title="Enable Quick Add">➕ Quick Add</button>
    </div>
    <div style="margin-bottom: 0.5rem;">
      <button class="btn btn-outline" id="manageMixSongsFilterToggle" style="padding: 0.3rem 1rem;">🏷️ Filter by tags</button>
      <span id="manageMixSongsFilterCount" class="modal-count-chip" style="margin-left: 8px;">0</span>
    </div>
    <div id="manageMixSongsFilterPanel" style="display: none; background: #f8fafc; border-radius: 20px; padding: 1rem; margin-bottom: 1rem;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
        <div class="toggle-switch" style="margin: 0;">
          <button id="manageMixOrBtn" class="toggle-btn active">OR</button>
          <button id="manageMixAndBtn" class="toggle-btn">AND</button>
        </div>
        <button id="manageMixClearFilterBtn" class="btn btn-outline" style="padding: 0.2rem 0.8rem;">Clear</button>
      </div>
      <div class="filter-section" style="margin-top: 0; padding-top: 0; border-top: none;">
        <h4>✅ Include</h4>
        <input type="text" id="manageMixIncludeSearch" placeholder="Search tags..." class="modal-search" style="margin: 0.3rem 0;">
        <div id="manageMixIncludeList" class="check-list" style="max-height: 150px;"></div>
      </div>
      <div class="filter-section">
        <h4>🚫 Exclude</h4>
        <input type="text" id="manageMixExcludeSearch" placeholder="Search tags..." class="modal-search" style="margin: 0.3rem 0;">
        <div id="manageMixExcludeList" class="check-list" style="max-height: 150px;"></div>
      </div>
      <div class="filter-chips" id="manageMixFilterChips"></div>
    </div>
    <div id="manageMixSongsList" class="check-list"></div>
    <div class="selected-count" id="manageMixSongsSelectedCount">0 songs selected</div>
    <div class="modal-footer">
      <button id="saveMixSongsBtn" class="btn btn-primary">Save Changes</button>
      <button class="btn btn-outline close-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- Clone Mix Modal -->
<div id="cloneMixModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">Clone Mix <span class="close-modal" style="cursor:pointer">✖</span></div>
    <input type="text" id="cloneMixTitleInput" placeholder="New mix title" class="modal-search">
    <textarea id="cloneMixDescInput" placeholder="Short description (optional)" class="modal-textarea" rows="2"></textarea>
    <input type="text" id="cloneMixKeyphrasesInput" placeholder="Keyphrases (comma separated)" class="modal-search">
    <div class="check-item" style="margin: 1rem 0;">
      <input type="checkbox" id="cloneMixCopySongsCheck" checked>
      <label for="cloneMixCopySongsCheck">Copy songs from original mix</label>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline close-modal">Cancel</button>
      <button id="confirmCloneMixBtn" class="btn btn-primary">Create Clone</button>
    </div>
  </div>
</div>

<!-- View Songs in Mix Modal (with Arrange Mode) -->
<div id="viewMixSongsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span id="viewMixSongsTitle">Songs in Mix</span><div class="modal-header-right"><span id="viewMixSongsCountChip" class="modal-count-chip">0 songs</span><span class="close-modal" style="cursor:pointer">✖</span></div></div>
    <div style="display: flex; gap: 8px; margin-bottom: 0.8rem;">
      <input type="text" id="viewMixSongsSearch" placeholder="🔎 Search within mix..." class="modal-search" style="margin:0;">
      <div style="display: flex; gap: 6px;">
        <button class="btn btn-outline" id="viewMixNormalModeBtn" style="padding:0.3rem 1rem;">👁️ Normal</button>
        <button class="btn btn-outline" id="viewMixArrangeModeBtn" style="padding:0.3rem 1rem;">☰ Arrange</button>
      </div>
    </div>
    <div id="viewMixSongsListNormal" class="simple-song-list"></div>
    <div id="viewMixSongsListArrange" style="display:none;">
      <div style="display: flex; justify-content: flex-end; margin-bottom: 8px;">
        <button class="btn btn-outline" id="shuffleMixSongsBtn" style="padding:0.3rem 1rem;">🔀 Shuffle</button>
      </div>
      <div id="arrangeSongList" class="arrange-list"></div>
      <div class="modal-footer" style="margin-top:1rem;">
        <button id="saveMixOrderBtn" class="btn btn-primary">Save Order</button>
      </div>
    </div>
    <div class="modal-footer" id="viewMixNormalFooter">
      <button class="btn btn-primary close-modal">Close</button>
    </div>
  </div>
</div>

<!-- Quick Add Mini-Modal (Multi-select Tags & Mixes) -->
<div id="quickAddModal" class="modal">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header"><span>➕ Quick Add</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    <p style="font-size:0.8rem; margin-bottom:0.5rem;">Song: <strong id="quickAddSongTitle"></strong></p>
    <div class="modal-tabs" style="margin-bottom:0.5rem;">
      <button id="quickAddTagsTab" class="modal-tab active">🏷️ Tags</button>
      <button id="quickAddMixesTab" class="modal-tab">🎚️ Mixes</button>
    </div>
    <div id="quickAddTagsPanel">
      <input type="text" id="quickAddTagSearch" placeholder="🔎 Search tags..." class="modal-search" style="margin-top:0;">
      <div id="quickAddTagsList" class="check-list" style="max-height:200px;"></div>
    </div>
    <div id="quickAddMixesPanel" style="display:none;">
      <input type="text" id="quickAddMixSearch" placeholder="🔎 Search mixes..." class="modal-search" style="margin-top:0;">
      <div id="quickAddMixesList" class="check-list" style="max-height:200px;"></div>
    </div>
    <div class="selected-count" id="quickAddSelectedCount">0 selected</div>
    <div class="modal-footer">
      <button id="confirmQuickAddBtn" class="btn btn-primary">Add Selected</button>
      <button class="btn btn-outline close-modal">Cancel</button>
    </div>
  </div>
</div>

<!-- Summary Modal -->
<div id="summaryModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">Summary <span class="close-modal" style="cursor:pointer">✖</span></div>
    <div id="summaryMessage" style="margin: 1rem 0;"></div>
    <div class="modal-footer"><button class="btn btn-primary close-modal">OK</button></div>
  </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">Confirm <span class="close-modal" style="cursor:pointer">✖</span></div>
    <div id="confirmationMessage" style="margin: 1rem 0;"></div>
    <div class="modal-footer">
      <button id="confirmNoBtn" class="btn btn-outline">No</button>
      <button id="confirmYesBtn" class="btn btn-primary">Yes</button>
    </div>
  </div>
</div>

<!-- Stats Modal -->
<div id="statsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">Statistics <span class="close-modal" style="cursor:pointer">✖</span></div>
    <div id="statsContent" style="margin: 1rem 0; line-height: 1.8;"></div>
    <div class="modal-footer"><button class="btn btn-primary close-modal">Close</button></div>
  </div>
</div>

<!-- Add/Edit Mix Modal -->
<div id="mixModal" class="modal">
  <div class="modal-content">
    <div class="modal-header"><span id="mixModalTitle">Add Mix</span><span class="close-modal" style="cursor:pointer">✖</span></div>
    <input type="text" id="mixTitleInput" placeholder="Mix title" class="modal-search">
    <textarea id="mixDescInput" placeholder="Short description (optional)" class="modal-textarea" rows="2"></textarea>
    <input type="text" id="mixKeyphrasesInput" placeholder="Keyphrases (comma separated)" class="modal-search">
    <div class="modal-footer">
      <button class="btn btn-outline close-modal">Cancel</button>
      <button id="saveMixBtn" class="btn btn-primary">Save</button>
    </div>
  </div>
</div>

<input type="file" id="importFileInput" style="display: none;" accept=".json">

<!-- ==================== JAVASCRIPT PART 1 ==================== -->
<script>
(function() {
  "use strict";

  // ---------- DATA ----------
  let songs = [];
  let tags = [];
  let songTags = [];
  let mixes = [];
  let mixSongs = [];

  let currentFilterTags = [];
  let currentExcludeTags = [];
  let currentFilterMode = "OR";
  let filterUntagged = false;

  let currentResolveDuplicate = null;
  let confirmResolve = null;
  let editingSongId = null;
  let editingTagId = null;
  let tagSongsToRemove = new Set();
  let editingMixId = null;
  let currentBulkType = null;
  let currentBulkText = null;
  let currentAssignSongId = null;

  let quickAddSongId = null;
  let quickAddSelectedTags = new Set();
  let quickAddSelectedMixes = new Set();
  let quickAddActiveTab = 'tags';
  let currentArrangeMixId = null;
  let arrangeSongList = [];

  const STORAGE_KEY = "SongTagAppData";
  const FUZZY_THRESHOLD = 0.75;

  // ---------- SERVER SYNC ----------
  const SERVER_LOAD_URL = '?action=load';
  const SERVER_SAVE_URL = '?action=save';

  async function loadFromServer() {
    try {
      const res = await fetch(SERVER_LOAD_URL);
      const data = await res.json();
      songs = data.songs || [];
      tags = data.tags || [];
      songTags = data.songTags || [];
      mixes = data.mixes || [];
      mixSongs = data.mixSongs || [];
      songs = songs.map(s => ({ ...s, lyrics: s.lyrics || '', createdAt: s.createdAt || Date.now() }));
      tags = tags.map(t => ({ ...t, description: t.description || '' }));
      mixes = mixes.map(m => ({ ...m, description: m.description || '', keyphrases: m.keyphrases || '', createdAt: m.createdAt || Date.now() }));
    } catch (e) {
      console.warn('Load failed, using defaults');
      songs = []; tags = []; songTags = []; mixes = []; mixSongs = [];
    }
    updateCounts();
    updateDrawerCounts();
  }

  async function saveToServer() {
    const data = { songs, tags, songTags, mixes, mixSongs };
    try {
      await fetch(SERVER_SAVE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      localStorage.setItem('SongTagAppData_cache', JSON.stringify(data));
    } catch (e) {
      console.error('Save failed:', e);
      showSummary('⚠️ Could not save to server.');
    }
  }

  function saveData() { saveToServer(); updateCounts(); updateDrawerCounts(); }

  // ---------- UTILS ----------
  function genId() { return Date.now() + '-' + Math.random().toString(36).substr(2, 6); }
  function escapeHtml(str) { return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m] || m); }
  function normalize(str) { return str.toLowerCase().replace(/[',()]/g, '').replace(/[^\w\s]/g, ' ').replace(/\s+/g, ' ').trim(); }
  function levenshtein(a, b) {
    if (a.length === 0) return b.length;
    if (b.length === 0) return a.length;
    const matrix = Array(b.length + 1).fill(null).map(() => Array(a.length + 1).fill(0));
    for (let i = 0; i <= a.length; i++) matrix[0][i] = i;
    for (let j = 0; j <= b.length; j++) matrix[j][0] = j;
    for (let j = 1; j <= b.length; j++) {
      for (let i = 1; i <= a.length; i++) {
        const cost = a[i - 1] === b[j - 1] ? 0 : 1;
        matrix[j][i] = Math.min(matrix[j][i - 1] + 1, matrix[j - 1][i] + 1, matrix[j - 1][i - 1] + cost);
      }
    }
    return matrix[b.length][a.length];
  }
  function fuzzyMatch(text, searchTerm, threshold = FUZZY_THRESHOLD) {
    if (!searchTerm) return true;
    const normText = normalize(text);
    const normSearch = normalize(searchTerm);
    if (normText.includes(normSearch)) return true;
    const maxLen = Math.max(normText.length, normSearch.length);
    if (maxLen === 0) return true;
    const dist = levenshtein(normText, normSearch);
    const similarity = 1 - dist / maxLen;
    return similarity >= threshold;
  }
  function similarity(s1, s2) {
    let a = s1.toLowerCase().replace(/\s+/g, ' ').trim();
    let b = s2.toLowerCase().replace(/\s+/g, ' ').trim();
    if (a === b) return 1;
    let longer = a.length > b.length ? a : b;
    let shorter = a.length > b.length ? b : a;
    if (longer.length === 0) return 1;
    const dist = levenshtein(a, b);
    return 1 - dist / longer.length;
  }
  function findSimilarSongs(newTitle, threshold=0.85) {
    return songs.filter(s => similarity(s.title, newTitle) >= threshold).map(s => ({ title: s.title, sim: similarity(s.title, newTitle) }));
  }
  function autoTitleFromLyrics(lyrics) {
    let lines = lyrics.split(/\r?\n/).filter(l=>l.trim());
    return lines.slice(0,2).join(" ").trim().substring(0,60);
  }

  function updateCounts() {
    document.getElementById('songCountBadge').innerText = songs.length;
    document.getElementById('tagCountBadge').innerText = tags.length;
    document.getElementById('mixCountBadge').innerText = mixes.length;
  }
  function updateDrawerCounts() {
    document.getElementById('drawerSongsCount').innerText = songs.length;
    document.getElementById('drawerTagsCount').innerText = tags.length;
    document.getElementById('drawerMixesCount').innerText = mixes.length;
  }
  function refreshUI() { renderSongs(); renderTags(); renderMixes(); updateCounts(); updateDrawerCounts(); }

  // ---------- DRAWER & PANELS ----------
  const drawer = document.getElementById('drawer');
  const drawerOverlay = document.getElementById('drawerOverlay');
  function openDrawer() { drawer.classList.add('open'); drawerOverlay.classList.add('open'); }
  function closeDrawer() { drawer.classList.remove('open'); drawerOverlay.classList.remove('open'); }
  function switchPanel(panelId) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.getElementById(`panel-${panelId}`).classList.add('active');
    document.querySelectorAll('.drawer-item').forEach(item => { item.classList.remove('active'); if (item.dataset.panel === panelId) item.classList.add('active'); });
    closeDrawer();
    updateFABs(panelId);
  }
  const fabContainer = document.getElementById('fabContainer');
  function updateFABs(panel) {
    if (panel === 'songs') {
      fabContainer.innerHTML = `<button class="fab small" id="filterFab">🏷️</button><button class="fab" id="addSongFab">➕</button>`;
      document.getElementById('filterFab').onclick = () => openTagFilterModal();
      document.getElementById('addSongFab').onclick = openBottomDrawer;
    } else if (panel === 'tags') {
      fabContainer.innerHTML = `<button class="fab" id="addTagFab">➕</button>`;
      document.getElementById('addTagFab').onclick = () => { document.getElementById('addTagNameInput').value = ''; document.getElementById('addTagDescInput').value = ''; document.getElementById('addTagModal').style.display = 'flex'; };
    } else if (panel === 'mixes') {
      fabContainer.innerHTML = `<button class="fab" id="addMixFab">➕</button>`;
      document.getElementById('addMixFab').onclick = openAddMixModal;
    } else { fabContainer.innerHTML = ''; }
  }

  const bottomDrawer = document.getElementById('bottomDrawer');
  const bottomOverlay = document.getElementById('bottomDrawerOverlay');
  function openBottomDrawer() { bottomDrawer.classList.add('open'); bottomOverlay.classList.add('open'); }
  function closeBottomDrawer() { bottomDrawer.classList.remove('open'); bottomOverlay.classList.remove('open'); }

  function exportData() {
    const data = JSON.stringify({ songs, tags, songTags, mixes, mixSongs }, null, 2);
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([data]));
    a.download = `orinTag_${Date.now()}.json`;
    a.click();
  }
  function importData(file) {
    const reader = new FileReader();
    reader.onload = e => {
      try {
        const d = JSON.parse(e.target.result);
        songs = (d.songs || []).map(s=>({...s, lyrics:s.lyrics||'', createdAt: s.createdAt || Date.now()}));
        tags = (d.tags || []).map(t=>({...t, description:t.description||''}));
        songTags = d.songTags || [];
        mixes = (d.mixes || []).map(m=>({...m, description:m.description||'', keyphrases:m.keyphrases||'', createdAt:m.createdAt||Date.now()}));
        mixSongs = d.mixSongs || [];
        saveData(); refreshUI(); showSummary('Import successful!');
      } catch (ex) { showSummary('Invalid JSON'); }
    };
    reader.readAsText(file);
  }

  function confirmAction(msg) {
    return new Promise(resolve => {
      document.getElementById('confirmationMessage').innerText = msg;
      confirmResolve = resolve;
      document.getElementById('confirmationModal').style.display = 'flex';
    });
  }
  function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.style.display = 'none';
    modal.querySelectorAll('input[type="text"]').forEach(input => { if (input.id.includes('Search') || input.id.includes('search')) input.value = ''; });
    const qaToggle = modal.querySelector('.btn-outline[id$="QuickAddToggle"]');
    if (qaToggle) { qaToggle.classList.remove('active-quick'); qaToggle.innerHTML = '➕ Quick Add'; }
  }
  function closeAllModals() { document.querySelectorAll('.modal').forEach(m => { m.style.display = 'none'; m.querySelectorAll('input[type="text"]').forEach(input => { if (input.id.includes('Search') || input.id.includes('search')) input.value = ''; }); }); }
  function showSummary(msg) { document.getElementById('summaryMessage').innerHTML = msg; document.getElementById('summaryModal').style.display = 'flex'; }

  function confirmAddWithDuplicateCheck(newTitle) {
    return new Promise(resolve => {
      const similar = findSimilarSongs(newTitle, 0.85);
      if (!similar.length) { resolve(true); return; }
      document.getElementById('dupNewTitle').innerText = newTitle;
      document.getElementById('dupSimilarList').innerHTML = similar.map(s => `<div class="similarity-item">📌 ${escapeHtml(s.title)} (${Math.round(s.sim*100)}% similar)</div>`).join('');
      currentResolveDuplicate = resolve;
      document.getElementById('duplicateModal').style.display = 'flex';
    });
  }

  let currentProgressActive = false;
  function openProgressModal(total) {
    document.getElementById('progressModal').style.display = 'flex';
    document.getElementById('progressSummary').style.display = 'none';
    document.getElementById('progressCloseBtn').disabled = true;
    document.getElementById('progressBarFill').style.width = '0%';
    document.getElementById('progressStatus').innerHTML = `0 / ${total} songs`;
    currentProgressActive = true;
  }
  function updateProgress(current, total, currentTitle) {
    if (!currentProgressActive) return;
    const pct = (current/total)*100;
    document.getElementById('progressBarFill').style.width = pct+'%';
    document.getElementById('progressStatus').innerHTML = `${current} / ${total} songs`;
    document.getElementById('progressDetail').innerHTML = `Adding: ${escapeHtml(currentTitle)}`;
  }
  function finishProgress(summary) {
    if (!currentProgressActive) return;
    document.getElementById('progressStatus').innerHTML = '✅ Complete';
    document.getElementById('progressDetail').innerHTML = '';
    document.getElementById('progressSummary').style.display = 'block';
    document.getElementById('progressSummary').innerHTML = summary;
    document.getElementById('progressCloseBtn').disabled = false;
    currentProgressActive = false;
  }
  async function runBulkWithProgress(items, addFn, tagIds = []) {
    const total = items.length;
    openProgressModal(total);
    let added=0, skipped=0, dups=0;
    const addedIds = [];
    for (let i=0; i<total; i++) {
      if (!currentProgressActive && i>0) break;
      updateProgress(i+1, total, items[i].title || items[i]);
      const ok = await confirmAddWithDuplicateCheck(items[i].title || items[i]);
      if (ok) {
        const newSong = addFn(items[i]);
        if (newSong) { added++; addedIds.push(newSong.id); }
        else { skipped++; dups++; }
      } else { skipped++; }
    }
    if (tagIds.length && addedIds.length) {
      for (const sid of addedIds) for (const tid of tagIds) {
        if (!songTags.some(st => st.songId===sid && st.tagId===tid)) songTags.push({songId:sid, tagId:tid});
      }
      saveData();
    }
    refreshUI();
    finishProgress(`✅ Added: ${added}<br>⏭️ Skipped: ${skipped}<br>⚠️ Duplicates: ${dups}`);
  }

  function parseTitlesBulk(text) {
    const lines = text.split(/\r?\n/);
    if (!lines.some(l=>l.trim().startsWith('#'))) return lines.filter(l=>l.trim()).map(l=>l.trim());
    const titles = []; let cur = null;
    for (const line of lines) {
      if (line.trim().startsWith('#')) { if (cur) titles.push(cur.trim()); cur = line.replace(/^#\s*/,'').trim(); }
      else if (cur && line.trim()) cur += ' '+line.trim();
    }
    if (cur) titles.push(cur.trim());
    return titles;
  }
  function addSingleTitle(item) {
    if (songs.some(s => s.title.toLowerCase() === item.title.toLowerCase())) return null;
    const s = { id: genId(), title: item.title, lyrics: '', createdAt: Date.now() };
    songs.push(s); saveData(); refreshUI(); return s;
  }
  function parseLyricsBulk(text) {
    const lines = text.split(/\r?\n/);
    const result = [];
    for (let i=0; i<lines.length; i++) {
      if (lines[i].trim().startsWith('#')) {
        const title = lines[i].replace(/^#\s*/,'').trim();
        i++;
        while (i<lines.length && lines[i].trim()==='') i++;
        if (i>=lines.length || lines[i].trim()!=='---') continue;
        i++;
        const lyr = [];
        while (i<lines.length && !lines[i].trim().startsWith('#')) { lyr.push(lines[i]); i++; }
        if (title) result.push({title, lyrics: lyr.join('\n').trim()});
        i--;
      }
    }
    return result;
  }
  function addSingleLyricsItem(item) {
    let title = item.title || autoTitleFromLyrics(item.lyrics);
    if (!title || songs.some(s => s.title.toLowerCase() === title.toLowerCase())) return null;
    const s = { id: genId(), title, lyrics: item.lyrics, createdAt: Date.now() };
    songs.push(s); saveData(); refreshUI(); return s;
  }

  function openBulkTagSelectModal(type, text) {
    currentBulkType = type; currentBulkText = text;
    const cont = document.getElementById('bulkTagList');
    const search = document.getElementById('bulkTagSearch');
    const selectedIds = new Set();
    function render() {
      const q = search.value.toLowerCase();
      const filtered = tags.filter(t=>t.name.toLowerCase().includes(q));
      cont.innerHTML = filtered.map(t=>`<div class="check-item"><input type="checkbox" value="${t.id}" id="btag_${t.id}" ${selectedIds.has(t.id)?'checked':''}><label for="btag_${t.id}">🏷️ ${escapeHtml(t.name)}</label></div>`).join('');
      document.getElementById('bulkTagSelectCount').innerText = filtered.length;
      updateCount();
    }
    function updateCount() { document.getElementById('bulkTagSelectedCount').innerText = `${selectedIds.size} tag(s) selected`; }
    cont.addEventListener('change', e => { if(e.target.type==='checkbox'){ if(e.target.checked) selectedIds.add(e.target.value); else selectedIds.delete(e.target.value); updateCount(); } });
    search.oninput = render;
    render();
    document.getElementById('bulkTagConfirmBtn').onclick = () => {
      closeModal('bulkTagSelectModal');
      const ids = Array.from(selectedIds);
      if (currentBulkType==='titles') runBulkWithProgress(parseTitlesBulk(currentBulkText).map(t=>({title:t})), addSingleTitle, ids);
      else runBulkWithProgress(parseLyricsBulk(currentBulkText), addSingleLyricsItem, ids);
    };
    document.getElementById('bulkTagSelectModal').style.display = 'flex';
  }

  function openTagFilterModal() {
    const modal = document.getElementById('tagFilterModal');
    const includeCont = document.getElementById('filterTagsList');
    const excludeCont = document.getElementById('excludeTagsList');
    const includeSearch = document.getElementById('filterTagSearch');
    const excludeSearch = document.getElementById('excludeTagSearch');
    function renderInclude() {
      const q = includeSearch.value.toLowerCase();
      const filtered = tags.filter(t=>t.name.toLowerCase().includes(q));
      includeCont.innerHTML = filtered.map(t=>`<div class="check-item"><input type="checkbox" value="${t.id}" id="ftag_${t.id}" ${currentFilterTags.includes(t.id)?'checked':''}><label for="ftag_${t.id}">🏷️ ${escapeHtml(t.name)}</label></div>`).join('');
      updateIncludeChips();
    }
    function renderExclude() {
      const q = excludeSearch.value.toLowerCase();
      const filtered = tags.filter(t=>t.name.toLowerCase().includes(q));
      excludeCont.innerHTML = filtered.map(t=>`<div class="check-item"><input type="checkbox" value="${t.id}" id="etag_${t.id}" ${currentExcludeTags.includes(t.id)?'checked':''}><label for="etag_${t.id}">🚫 ${escapeHtml(t.name)}</label></div>`).join('');
      updateExcludeChips();
    }
    function updateIncludeChips() {
      const chipDiv = document.getElementById('selectedFilterChips');
      const ids = Array.from(document.querySelectorAll('#filterTagsList input:checked')).map(cb=>cb.value);
      chipDiv.innerHTML = ids.map(id=>{ const t=tags.find(t=>t.id===id); return t?`<span class="chip">🏷️ ${escapeHtml(t.name)}</span>`:''; }).join('');
    }
    function updateExcludeChips() {
      const chipDiv = document.getElementById('selectedExcludeChips');
      const ids = Array.from(document.querySelectorAll('#excludeTagsList input:checked')).map(cb=>cb.value);
      chipDiv.innerHTML = ids.map(id=>{ const t=tags.find(t=>t.id===id); return t?`<span class="chip" style="background:#fee2e2;color:#b91c1c;">🚫 ${escapeHtml(t.name)}</span>`:''; }).join('');
    }
    renderInclude(); renderExclude();
    document.getElementById('filterTagsCount').innerText = tags.length;
    includeCont.addEventListener('change', updateIncludeChips);
    excludeCont.addEventListener('change', updateExcludeChips);
    includeSearch.oninput = renderInclude;
    excludeSearch.oninput = renderExclude;
    document.getElementById('toggleOrBtn').onclick = ()=>{ currentFilterMode='OR'; document.getElementById('toggleOrBtn').classList.add('active'); document.getElementById('toggleAndBtn').classList.remove('active'); };
    document.getElementById('toggleAndBtn').onclick = ()=>{ currentFilterMode='AND'; document.getElementById('toggleAndBtn').classList.add('active'); document.getElementById('toggleOrBtn').classList.remove('active'); };
    document.getElementById('untaggedFilterBtn').onclick = ()=>{ filterUntagged=true; currentFilterTags=[]; currentExcludeTags=[]; renderSongs(); updateFilterBadge(); closeModal('tagFilterModal'); };
    document.getElementById('applyFilterBtn').onclick = ()=>{
      filterUntagged=false;
      currentFilterTags = Array.from(document.querySelectorAll('#filterTagsList input:checked')).map(cb=>cb.value);
      currentExcludeTags = Array.from(document.querySelectorAll('#excludeTagsList input:checked')).map(cb=>cb.value);
      renderSongs(); updateFilterBadge(); closeModal('tagFilterModal');
    };
    document.getElementById('showFilterPreviewBtn').onclick = ()=>{
      const incIds = Array.from(document.querySelectorAll('#filterTagsList input:checked')).map(cb=>cb.value);
      const excIds = Array.from(document.querySelectorAll('#excludeTagsList input:checked')).map(cb=>cb.value);
      const mode = document.getElementById('toggleOrBtn').classList.contains('active')?'OR':'AND';
      const filtered = songs.filter(s=>{
        const ids = songTags.filter(st=>st.songId===s.id).map(st=>st.tagId);
        const inc = incIds.length===0?true:(mode==='OR'?incIds.some(tid=>ids.includes(tid)):incIds.every(tid=>ids.includes(tid)));
        const exc = excIds.length===0?true:!excIds.some(tid=>ids.includes(tid));
        return inc && exc;
      });
      const incNames = incIds.map(id=>tags.find(t=>t.id===id)?.name).filter(Boolean).join(', ');
      const excNames = excIds.map(id=>tags.find(t=>t.id===id)?.name).filter(Boolean).join(', ');
      openPreviewFilterModal(filtered, `Include: ${incNames||'none'} (${mode}) | Exclude: ${excNames||'none'}`);
    };
    modal.style.display='flex';
  }

  function openPreviewFilterModal(filteredSongs, summary) {
    document.getElementById('previewFilterSummary').innerText = summary;
    const search = document.getElementById('previewSearch');
    const cont = document.getElementById('previewSongsList');
    const countSpan = document.getElementById('previewCountChip');
    function render() {
      const q = search.value.toLowerCase();
      const f = filteredSongs.filter(s=>s.title.toLowerCase().includes(q));
      cont.innerHTML = f.map(s=>`<div class="simple-song-item">🎵 ${escapeHtml(s.title)}</div>`).join('');
      countSpan.innerText = f.length;
    }
    render();
    search.oninput = render;
    document.getElementById('previewFilterModal').style.display='flex';
  }

  function updateFilterBadge() {
    const badge = document.getElementById('activeFilterBadge');
    if (filterUntagged) {
      badge.innerHTML = `🚫 Filter: Untagged <span style="cursor:pointer;" id="clearFilterBtn">✖ Clear</span>`;
      document.getElementById('clearFilterBtn').onclick = ()=>{ filterUntagged=false; renderSongs(); updateFilterBadge(); };
    } else if (currentFilterTags.length || currentExcludeTags.length) {
      const inc = currentFilterTags.map(id=>tags.find(t=>t.id===id)?.name).filter(Boolean).join(', ');
      const exc = currentExcludeTags.map(id=>tags.find(t=>t.id===id)?.name).filter(Boolean).join(', ');
      let text = '';
      if (inc) text += `🏷️ Include: ${inc} (${currentFilterMode}) `;
      if (exc) text += `🚫 Exclude: ${exc}`;
      badge.innerHTML = `${text} <span style="cursor:pointer;" id="clearFilterBtn">✖ Clear</span>`;
      document.getElementById('clearFilterBtn').onclick = ()=>{ currentFilterTags=[]; currentExcludeTags=[]; renderSongs(); updateFilterBadge(); };
    } else { badge.innerHTML = ''; }
  }

  function getTagsForSong(songId) { return tags.filter(t=>songTags.some(st=>st.songId===songId&&st.tagId===t.id)); }

  function renderSongs() {
    const rawSearch = document.getElementById('songSearch').value;
    let filtered = songs.filter(song => fuzzyMatch(song.title, rawSearch) || fuzzyMatch(song.lyrics || '', rawSearch));
    if (filterUntagged) filtered = filtered.filter(s=>songTags.filter(st=>st.songId===s.id).length===0);
    else {
      if (currentFilterTags.length) {
        filtered = filtered.filter(s=>{
          const ids = songTags.filter(st=>st.songId===s.id).map(st=>st.tagId);
          return currentFilterMode==='OR' ? currentFilterTags.some(tid=>ids.includes(tid)) : currentFilterTags.every(tid=>ids.includes(tid));
        });
      }
      if (currentExcludeTags.length) {
        filtered = filtered.filter(s=>!currentExcludeTags.some(tid=>songTags.filter(st=>st.songId===s.id).map(st=>st.tagId).includes(tid)));
      }
    }
    const sort = document.getElementById('sortSongs').value;
    if (sort==='az') filtered.sort((a,b)=>a.title.localeCompare(b.title));
    else if (sort==='za') filtered.sort((a,b)=>b.title.localeCompare(a.title));
    else if (sort==='recent') filtered.sort((a,b)=>(b.createdAt||0) - (a.createdAt||0));
    const cont = document.getElementById('songsContainer');
    if (!filtered.length) { cont.innerHTML='<div style="text-align:center;padding:2rem;">✨ No songs match</div>'; }
    else {
      cont.innerHTML = filtered.map(song=>{
        const tagChips = getTagsForSong(song.id).map(t=>`<span class="chip" data-tagid="${t.id}" data-songid="${song.id}">🏷️ ${escapeHtml(t.name)} <span class="chip-remove" data-tagid="${t.id}" data-songid="${song.id}">✖</span></span>`).join('');
        const preview = song.lyrics ? song.lyrics.substring(0,50)+(song.lyrics.length>50?'…':'') : 'No lyrics';
        return `<div class="song-card collapsed" data-song-id="${song.id}">
          <div class="card-header"><span class="card-icon">🎵</span><span class="card-title">${escapeHtml(song.title)}</span><button class="expand-toggle">▶</button></div>
          <div class="card-details">
            <div class="lyrics-preview">📄 ${escapeHtml(preview)}</div>
            <div class="tag-chips">${tagChips||'<span style="font-size:0.7rem;">no tags</span>'}</div>
            <div class="card-actions">
              <button class="icon-btn view-details-btn" data-id="${song.id}" title="View details">👁️</button>
              <button class="icon-btn add-tag-btn" data-id="${song.id}" title="Assign tags">🏷️</button>
              <button class="icon-btn edit-song-btn" data-id="${song.id}" title="Edit">✏️</button>
              <button class="icon-btn delete-song-btn" data-id="${song.id}" title="Delete">🗑️</button>
            </div>
          </div>
        </div>`;
      }).join('');
      attachSongEvents();
      attachCollapsibleEvents('song-card');
    }
    document.getElementById('filteredCountBadge').innerText = `Filtered: ${filtered.length}`;
  }

  function renderTags() {
    const search = document.getElementById('tagSearch').value.toLowerCase();
    const sort = document.getElementById('sortTags').value;
    let filtered = tags.filter(t => t.name.toLowerCase().includes(search));
    if (sort === 'az') filtered.sort((a, b) => a.name.localeCompare(b.name));
    else if (sort === 'za') filtered.sort((a, b) => b.name.localeCompare(a.name));
    else if (sort === 'mostSongs') {
      filtered.sort((a, b) => {
        const countA = songTags.filter(st => st.tagId === a.id).length;
        const countB = songTags.filter(st => st.tagId === b.id).length;
        return countB - countA || a.name.localeCompare(b.name);
      });
    }
    const container = document.getElementById('tagsContainer');
    if (!filtered.length) { container.innerHTML = '<div style="padding:1rem;">No tags found</div>'; }
    else {
      container.innerHTML = filtered.map(tag => {
        const count = songTags.filter(st => st.tagId === tag.id).length;
        const descHtml = tag.description ? `<div class="tag-description">${escapeHtml(tag.description)}</div>` : '';
        return `<div class="tag-item" data-tag-id="${tag.id}">
          <div class="tag-left">
            <div class="tag-name-row">
              <span class="tag-name">🏷️ ${escapeHtml(tag.name)}</span>
              <span class="tag-count-chip">${count} song${count !== 1 ? 's' : ''}</span>
            </div>
            ${descHtml}
          </div>
          <div class="tag-actions">
            <button class="icon-btn add-songs-to-tag-btn" data-id="${tag.id}" title="Add songs">➕</button>
            <button class="icon-btn edit-tag-btn" data-id="${tag.id}" title="Edit">✏️</button>
            <button class="icon-btn delete-tag-btn" data-id="${tag.id}" title="Delete">🗑️</button>
          </div>
        </div>`;
      }).join('');
      document.querySelectorAll('.tag-left').forEach(l => l.addEventListener('click', (e) => { e.stopPropagation(); const id = l.closest('.tag-item').dataset.tagId; openViewTagSongsModal(tags.find(t => t.id === id)); }));
      document.querySelectorAll('.add-songs-to-tag-btn').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); openAddSongsToTagModal(b.dataset.id); }));
      document.querySelectorAll('.edit-tag-btn').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); openEditTagModal(b.dataset.id); }));
      document.querySelectorAll('.delete-tag-btn').forEach(b => b.addEventListener('click', async (e) => { e.stopPropagation(); if (await confirmAction('Delete tag?')) deleteTagById(b.dataset.id); }));
    }
    document.getElementById('filteredTagCountBadge').innerText = `Filtered: ${filtered.length}`;
    updateCounts();
  }

  function renderMixes() {
    const search = document.getElementById('mixSearch').value.toLowerCase();
    const sort = document.getElementById('sortMixes').value;
    let filtered = mixes.filter(m=>m.title.toLowerCase().includes(search)||(m.keyphrases||'').toLowerCase().includes(search));
    if (sort==='az') filtered.sort((a,b)=>a.title.localeCompare(b.title));
    else if (sort==='za') filtered.sort((a,b)=>b.title.localeCompare(a.title));
    else if (sort==='recent') filtered.sort((a,b)=>b.createdAt - a.createdAt);
    const cont = document.getElementById('mixesContainer');
    if (!filtered.length) { cont.innerHTML='<div>No mixes</div>'; }
    else {
      cont.innerHTML = filtered.map(mix=>{
        const count = mixSongs.filter(ms=>ms.mixId===mix.id).length;
        const chips = (mix.keyphrases||'').split(',').map(k=>k.trim()).filter(k=>k).map(k=>`<span class="chip keyphrase">🔑 ${escapeHtml(k)}</span>`).join('');
        const desc = mix.description ? mix.description.substring(0,40)+(mix.description.length>40?'…':'') : '';
        return `<div class="mix-card collapsed" data-mix-id="${mix.id}">
          <div class="card-header"><span class="card-icon">🎚️</span><span class="card-title">${escapeHtml(mix.title)} <span class="mix-badge">${count}</span></span><button class="expand-toggle">▶</button></div>
          <div class="card-details">
            ${desc?`<div class="mix-description">${escapeHtml(desc)}</div>`:''}
            <div class="keyphrase-chips">${chips}</div>
            <div class="card-actions">
              <button class="icon-btn view-mix-songs-btn" data-id="${mix.id}">👁️</button>
              <button class="icon-btn manage-mix-songs-btn" data-id="${mix.id}">✏️🎵</button>
              <button class="icon-btn edit-mix-btn" data-id="${mix.id}">✏️</button>
              <button class="icon-btn clone-mix-btn" data-id="${mix.id}">📋</button>
              <button class="icon-btn delete-mix-btn" data-id="${mix.id}">🗑️</button>
            </div>
          </div>
        </div>`;
      }).join('');
      attachMixEvents();
      attachCollapsibleEvents('mix-card');
    }
    document.getElementById('filteredMixCountBadge').innerText = `Filtered: ${filtered.length}`;
  }

  function attachCollapsibleEvents(cardClass) {
    document.querySelectorAll(`.${cardClass}`).forEach(card=>{
      const header = card.querySelector('.card-header');
      const toggle = card.querySelector('.expand-toggle');
      const toggleFn = (e) => { e.stopPropagation(); card.classList.toggle('expanded'); card.classList.toggle('collapsed'); toggle.textContent = card.classList.contains('expanded') ? '▼' : '▶'; };
      header.addEventListener('click', (e) => { if (!e.target.closest('.expand-toggle')) toggleFn(e); });
      toggle.addEventListener('click', toggleFn);
    });
  }

  function attachSongEvents() {
    document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', (e) => { if(e.target.classList.contains('chip-remove')) return; const tid=c.dataset.tagid; if(tid){ filterUntagged=false; currentFilterTags=[tid]; currentExcludeTags=[]; renderSongs(); updateFilterBadge(); } }));
    document.querySelectorAll('.chip-remove').forEach(rm => rm.addEventListener('click', async (e) => { e.stopPropagation(); const tagId=rm.dataset.tagid, songId=rm.dataset.songid; if(await confirmAction('Remove tag?')){ songTags=songTags.filter(st=>!(st.songId===songId&&st.tagId===tagId)); saveData(); renderSongs(); renderTags(); } }));
    document.querySelectorAll('.view-details-btn').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); const s = songs.find(s => s.id === b.dataset.id); if(s){ document.getElementById('detailsSongTitle').innerText = s.title; document.getElementById('detailsSongLyrics').innerText = s.lyrics||'(No lyrics)'; document.getElementById('songDetailsModal').style.display='flex'; } }));
    document.querySelectorAll('.add-tag-btn').forEach(b => b.addEventListener('click', () => openAssignTagsModal(b.dataset.id)));
    document.querySelectorAll('.edit-song-btn').forEach(b => b.addEventListener('click', () => openEditSongModal(b.dataset.id)));
    document.querySelectorAll('.delete-song-btn').forEach(b => b.addEventListener('click', async () => { if(await confirmAction('Delete song?')) deleteSongById(b.dataset.id); }));
  }

  function attachMixEvents() {
    document.querySelectorAll('.view-mix-songs-btn').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); openViewMixSongsModal(mixes.find(m=>m.id===b.dataset.id)); }));
    document.querySelectorAll('.manage-mix-songs-btn').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); openManageMixSongsModal(b.dataset.id); }));
    document.querySelectorAll('.edit-mix-btn').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); openEditMixModal(b.dataset.id); }));
    document.querySelectorAll('.clone-mix-btn').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); openCloneMixModal(b.dataset.id); }));
    document.querySelectorAll('.delete-mix-btn').forEach(b => b.addEventListener('click', async (e) => { e.stopPropagation(); if(await confirmAction('Delete mix?')) deleteMixById(b.dataset.id); }));
  }

  function deleteSongById(id) { songs = songs.filter(s => s.id !== id); songTags = songTags.filter(st => st.songId !== id); mixSongs = mixSongs.filter(ms => ms.songId !== id); saveData(); refreshUI(); }
  function deleteTagById(id) { tags = tags.filter(t => t.id !== id); songTags = songTags.filter(st => st.tagId !== id); saveData(); renderSongs(); renderTags(); }
  function deleteMixById(id) { mixes = mixes.filter(m => m.id !== id); mixSongs = mixSongs.filter(ms => ms.mixId !== id); saveData(); renderMixes(); }

  function filterSongsByTagFilter(songList, includeIds, excludeIds, mode) {
    return songList.filter(song => {
      const songTagIds = songTags.filter(st=>st.songId===song.id).map(st=>st.tagId);
      const includePass = includeIds.length === 0 ? true : (mode === 'OR' ? includeIds.some(tid => songTagIds.includes(tid)) : includeIds.every(tid => songTagIds.includes(tid)));
      const excludePass = excludeIds.length === 0 ? true : !excludeIds.some(tid => songTagIds.includes(tid));
      return includePass && excludePass;
    });
  }

  // ========== ADD SONGS TO TAG MODAL ==========
  function openAddSongsToTagModal(tagId) {
    const tag = tags.find(t => t.id === tagId); if(!tag) return;
    document.getElementById('addSongsToTagTitle').innerHTML = `Add songs to “${escapeHtml(tag.name)}” <div class="modal-header-right"><span id="addSongsToTagCountChip" class="modal-count-chip">0</span><span class="close-modal" style="cursor:pointer">✖</span></div>`;
    const cont = document.getElementById('addSongsToTagList');
    const search = document.getElementById('addSongToTagSearch');
    const selectedIds = new Set(songTags.filter(st=>st.tagId===tagId).map(st=>st.songId));

    let filterInclude = [], filterExclude = [], filterMode = 'OR';
    let filterPanelVisible = false;
    const availableTags = tags.filter(t => t.id !== tagId);
    const includeCont = document.getElementById('addSongsToTagIncludeList');
    const excludeCont = document.getElementById('addSongsToTagExcludeList');
    const includeSearch = document.getElementById('addSongsToTagIncludeSearch');
    const excludeSearch = document.getElementById('addSongsToTagExcludeSearch');
    const orBtn = document.getElementById('addSongsToTagOrBtn');
    const andBtn = document.getElementById('addSongsToTagAndBtn');
    const clearBtn = document.getElementById('addSongsToTagClearFilterBtn');
    const chipsDiv = document.getElementById('addSongsToTagFilterChips');
    const filterCountSpan = document.getElementById('addSongsToTagFilterCount');

    const qaToggle = document.getElementById('addSongsToTagQuickAddToggle');
    let quickAddEnabled = false;
    qaToggle.onclick = () => { quickAddEnabled = !quickAddEnabled; qaToggle.classList.toggle('active-quick', quickAddEnabled); qaToggle.innerHTML = quickAddEnabled ? '✅ Quick Add ON' : '➕ Quick Add'; renderSongList(); };

    function updateFilterCount() { filterCountSpan.innerText = filterInclude.length + filterExclude.length; }
    function renderIncludeList() { const q = includeSearch.value.toLowerCase(); const filtered = availableTags.filter(t=>t.name.toLowerCase().includes(q)); includeCont.innerHTML = filtered.map(t=>`<div class="check-item"><input type="checkbox" value="${t.id}" id="addtag_inc_${t.id}" ${filterInclude.includes(t.id)?'checked':''}><label for="addtag_inc_${t.id}">🏷️ ${escapeHtml(t.name)}</label></div>`).join(''); }
    function renderExcludeList() { const q = excludeSearch.value.toLowerCase(); const filtered = availableTags.filter(t=>t.name.toLowerCase().includes(q)); excludeCont.innerHTML = filtered.map(t=>`<div class="check-item"><input type="checkbox" value="${t.id}" id="addtag_exc_${t.id}" ${filterExclude.includes(t.id)?'checked':''}><label for="addtag_exc_${t.id}">🚫 ${escapeHtml(t.name)}</label></div>`).join(''); }
    function updateChips() { const incChips = filterInclude.map(id=>{ const t=availableTags.find(t=>t.id===id); return t?`<span class="chip">🏷️ ${escapeHtml(t.name)}</span>`:''; }).join(''); const excChips = filterExclude.map(id=>{ const t=availableTags.find(t=>t.id===id); return t?`<span class="chip" style="background:#fee2e2;color:#b91c1c;">🚫 ${escapeHtml(t.name)}</span>`:''; }).join(''); chipsDiv.innerHTML = incChips + excChips; updateFilterCount(); }
    function collectChecked() { filterInclude = Array.from(document.querySelectorAll('#addSongsToTagIncludeList input:checked')).map(cb=>cb.value); filterExclude = Array.from(document.querySelectorAll('#addSongsToTagExcludeList input:checked')).map(cb=>cb.value); }
    includeCont.addEventListener('change', ()=>{ collectChecked(); updateChips(); renderSongList(); });
    excludeCont.addEventListener('change', ()=>{ collectChecked(); updateChips(); renderSongList(); });
    includeSearch.oninput = renderIncludeList;
    excludeSearch.oninput = renderExcludeList;
    orBtn.onclick = ()=>{ filterMode='OR'; orBtn.classList.add('active'); andBtn.classList.remove('active'); renderSongList(); };
    andBtn.onclick = ()=>{ filterMode='AND'; andBtn.classList.add('active'); orBtn.classList.remove('active'); renderSongList(); };
    clearBtn.onclick = ()=>{ filterInclude=[]; filterExclude=[]; renderIncludeList(); renderExcludeList(); updateChips(); renderSongList(); };

    function getFilteredSongs() {
      let baseSongs = songs.filter(s => !selectedIds.has(s.id));
      const searchTerm = search.value;
      let textFiltered = baseSongs.filter(s => fuzzyMatch(s.title, searchTerm) || fuzzyMatch(s.lyrics || '', searchTerm));
      return filterSongsByTagFilter(textFiltered, filterInclude, filterExclude, filterMode);
    }
    function renderSongList() {
      const filtered = getFilteredSongs();
      let html = '';
      filtered.forEach(song => { const checked = selectedIds.has(song.id) ? 'checked' : ''; html += `<div class="check-item">`; if (quickAddEnabled) { html += `<button class="icon-btn quick-add-song-btn" data-song-id="${song.id}" title="Quick Add to Tag/Mix" style="margin-right:4px;">➕</button>`; } html += `<input type="checkbox" value="${song.id}" id="asong_${song.id}" ${checked}><label for="asong_${song.id}">🎵 ${escapeHtml(song.title)}</label></div>`; });
      cont.innerHTML = html;
      document.getElementById('addSongsToTagCountChip').innerText = filtered.length;
      updateSelectedCount();
      if (quickAddEnabled) { cont.querySelectorAll('.quick-add-song-btn').forEach(btn => { btn.addEventListener('click', (e) => { e.stopPropagation(); openQuickAddModal(btn.dataset.songId); }); }); }
    }
    function updateSelectedCount() { const sel = document.querySelectorAll('#addSongsToTagList input:checked').length; document.getElementById('addSongsToTagSelectedCount').innerText = `${sel} song(s) selected`; }

    document.getElementById('addSongsToTagFilterToggle').onclick = () => { filterPanelVisible = !filterPanelVisible; document.getElementById('addSongsToTagFilterPanel').style.display = filterPanelVisible ? 'block' : 'none'; if (filterPanelVisible) { renderIncludeList(); renderExcludeList(); updateChips(); } };
    cont.addEventListener('change', e => { if (e.target.type === 'checkbox') { if (e.target.checked) selectedIds.add(e.target.value); else selectedIds.delete(e.target.value); updateSelectedCount(); } });
    search.oninput = renderSongList;
    renderSongList();

    document.getElementById('confirmAddSongsToTagBtn').onclick = () => { const sel = Array.from(document.querySelectorAll('#addSongsToTagList input:checked')).map(cb=>cb.value); let added = 0; sel.forEach(sid => { if (!songTags.some(st=>st.songId===sid&&st.tagId===tagId)) { songTags.push({songId:sid, tagId}); added++; } }); if (added) { saveData(); renderSongs(); renderTags(); } closeModal('addSongsToTagModal'); showSummary(`Added ${added} song(s).`); };
    document.getElementById('addSongsToTagModal').style.display = 'flex';
  }

  // ========== MANAGE MIX SONGS MODAL ==========
  function openManageMixSongsModal(mixId) {
    const mix = mixes.find(m => m.id === mixId); if (!mix) return;
    document.getElementById('manageMixSongsTitle').innerHTML = `Manage songs in “${escapeHtml(mix.title)}”`;
    const cont = document.getElementById('manageMixSongsList');
    const search = document.getElementById('manageMixSongsSearch');
    const selectedIds = new Set(mixSongs.filter(ms=>ms.mixId===mixId).map(ms=>ms.songId));

    let filterInclude = [], filterExclude = [], filterMode = 'OR';
    let filterPanelVisible = false;
    const includeCont = document.getElementById('manageMixIncludeList');
    const excludeCont = document.getElementById('manageMixExcludeList');
    const includeSearch = document.getElementById('manageMixIncludeSearch');
    const excludeSearch = document.getElementById('manageMixExcludeSearch');
    const orBtn = document.getElementById('manageMixOrBtn');
    const andBtn = document.getElementById('manageMixAndBtn');
    const clearBtn = document.getElementById('manageMixClearFilterBtn');
    const chipsDiv = document.getElementById('manageMixFilterChips');
    const filterCountSpan = document.getElementById('manageMixSongsFilterCount');

    const qaToggle = document.getElementById('manageMixQuickAddToggle');
    let quickAddEnabled = false;
    qaToggle.onclick = () => { quickAddEnabled = !quickAddEnabled; qaToggle.classList.toggle('active-quick', quickAddEnabled); qaToggle.innerHTML = quickAddEnabled ? '✅ Quick Add ON' : '➕ Quick Add'; renderSongList(); };

    function updateFilterCount() { filterCountSpan.innerText = filterInclude.length + filterExclude.length; }
    function renderIncludeList() { const q = includeSearch.value.toLowerCase(); const filtered = tags.filter(t=>t.name.toLowerCase().includes(q)); includeCont.innerHTML = filtered.map(t=>`<div class="check-item"><input type="checkbox" value="${t.id}" id="mix_inc_${t.id}" ${filterInclude.includes(t.id)?'checked':''}><label for="mix_inc_${t.id}">🏷️ ${escapeHtml(t.name)}</label></div>`).join(''); }
    function renderExcludeList() { const q = excludeSearch.value.toLowerCase(); const filtered = tags.filter(t=>t.name.toLowerCase().includes(q)); excludeCont.innerHTML = filtered.map(t=>`<div class="check-item"><input type="checkbox" value="${t.id}" id="mix_exc_${t.id}" ${filterExclude.includes(t.id)?'checked':''}><label for="mix_exc_${t.id}">🚫 ${escapeHtml(t.name)}</label></div>`).join(''); }
    function updateChips() { const incChips = filterInclude.map(id=>{ const t=tags.find(t=>t.id===id); return t?`<span class="chip">🏷️ ${escapeHtml(t.name)}</span>`:''; }).join(''); const excChips = filterExclude.map(id=>{ const t=tags.find(t=>t.id===id); return t?`<span class="chip" style="background:#fee2e2;color:#b91c1c;">🚫 ${escapeHtml(t.name)}</span>`:''; }).join(''); chipsDiv.innerHTML = incChips + excChips; updateFilterCount(); }
    function collectChecked() { filterInclude = Array.from(document.querySelectorAll('#manageMixIncludeList input:checked')).map(cb=>cb.value); filterExclude = Array.from(document.querySelectorAll('#manageMixExcludeList input:checked')).map(cb=>cb.value); }
    includeCont.addEventListener('change', ()=>{ collectChecked(); updateChips(); renderSongList(); });
    excludeCont.addEventListener('change', ()=>{ collectChecked(); updateChips(); renderSongList(); });
    includeSearch.oninput = renderIncludeList;
    excludeSearch.oninput = renderExcludeList;
    orBtn.onclick = ()=>{ filterMode='OR'; orBtn.classList.add('active'); andBtn.classList.remove('active'); renderSongList(); };
    andBtn.onclick = ()=>{ filterMode='AND'; andBtn.classList.add('active'); orBtn.classList.remove('active'); renderSongList(); };
    clearBtn.onclick = ()=>{ filterInclude=[]; filterExclude=[]; renderIncludeList(); renderExcludeList(); updateChips(); renderSongList(); };

    function getFilteredSongs() {
      const searchTerm = search.value;
      let textFiltered = songs.filter(s => fuzzyMatch(s.title, searchTerm) || fuzzyMatch(s.lyrics || '', searchTerm));
      return filterSongsByTagFilter(textFiltered, filterInclude, filterExclude, filterMode);
    }
    function renderSongList() {
      const filtered = getFilteredSongs();
      let html = '';
      filtered.forEach(song => { const checked = selectedIds.has(song.id) ? 'checked' : ''; html += `<div class="check-item">`; if (quickAddEnabled) { html += `<button class="icon-btn quick-add-song-btn" data-song-id="${song.id}" title="Quick Add to Tag/Mix" style="margin-right:4px;">➕</button>`; } html += `<input type="checkbox" value="${song.id}" id="msong_${song.id}" ${checked}><label for="msong_${song.id}">🎵 ${escapeHtml(song.title)}</label></div>`; });
      cont.innerHTML = html;
      document.getElementById('manageMixSongsCount').innerText = filtered.length;
      updateSelectedCount();
      if (quickAddEnabled) { cont.querySelectorAll('.quick-add-song-btn').forEach(btn => { btn.addEventListener('click', (e) => { e.stopPropagation(); openQuickAddModal(btn.dataset.songId); }); }); }
    }
    function updateSelectedCount() { const sel = document.querySelectorAll('#manageMixSongsList input:checked').length; document.getElementById('manageMixSongsSelectedCount').innerText = `${sel} song(s) selected`; }

    document.getElementById('manageMixSongsFilterToggle').onclick = () => { filterPanelVisible = !filterPanelVisible; document.getElementById('manageMixSongsFilterPanel').style.display = filterPanelVisible ? 'block' : 'none'; if (filterPanelVisible) { renderIncludeList(); renderExcludeList(); updateChips(); } };
    cont.addEventListener('change', e => { if (e.target.type === 'checkbox') { if (e.target.checked) selectedIds.add(e.target.value); else selectedIds.delete(e.target.value); updateSelectedCount(); } });
    search.oninput = renderSongList;
    renderSongList();

    document.getElementById('saveMixSongsBtn').onclick = () => { mixSongs = mixSongs.filter(ms => ms.mixId !== mixId); for (const sid of selectedIds) mixSongs.push({ mixId, songId: sid }); saveData(); renderMixes(); closeModal('manageMixSongsModal'); showSummary(`Mix updated with ${selectedIds.size} songs.`); };
    document.getElementById('manageMixSongsModal').style.display = 'flex';
  }

  // ========== VIEW MIX SONGS MODAL (Arrange) ==========
  function openViewMixSongsModal(mix) {
    currentArrangeMixId = mix.id;
    document.getElementById('viewMixSongsTitle').innerHTML = `Songs in “${escapeHtml(mix.title)}”`;
    const search = document.getElementById('viewMixSongsSearch');
    const normalCont = document.getElementById('viewMixSongsListNormal');
    const arrangeCont = document.getElementById('arrangeSongList');
    const countSpan = document.getElementById('viewMixSongsCountChip');
    const normalModeBtn = document.getElementById('viewMixNormalModeBtn');
    const arrangeModeBtn = document.getElementById('viewMixArrangeModeBtn');
    const normalFooter = document.getElementById('viewMixNormalFooter');
    const arrangePanel = document.getElementById('viewMixSongsListArrange');

    let mode = 'normal';
    let currentOrder = mixSongs.filter(ms=>ms.mixId===mix.id).map(ms=>ms.songId);

    function renderNormal() { const q = search.value.toLowerCase(); const orderedSongs = currentOrder.map(id => songs.find(s=>s.id===id)).filter(s=>s && s.title.toLowerCase().includes(q)); normalCont.innerHTML = orderedSongs.map(s => `<div class="simple-song-item">🎵 ${escapeHtml(s.title)}</div>`).join(''); countSpan.innerText = orderedSongs.length; }
    function renderArrange() { const q = search.value.toLowerCase(); const orderedSongs = currentOrder.map(id => songs.find(s=>s.id===id)).filter(s=>s && s.title.toLowerCase().includes(q)); arrangeSongList = orderedSongs.map(s => s.id); let html = ''; orderedSongs.forEach((song, index) => { html += `<div class="arrange-item" data-song-id="${song.id}"><span>🎵 ${escapeHtml(song.title)}</span><div class="arrange-actions"><button class="icon-btn move-up-btn" ${index === 0 ? 'disabled' : ''} data-index="${index}">⬆️</button><button class="icon-btn move-down-btn" ${index === orderedSongs.length-1 ? 'disabled' : ''} data-index="${index}">⬇️</button></div></div>`; }); arrangeCont.innerHTML = html; countSpan.innerText = orderedSongs.length; arrangeCont.querySelectorAll('.move-up-btn').forEach(btn => { btn.addEventListener('click', () => moveArrangeItem(parseInt(btn.dataset.index), -1)); }); arrangeCont.querySelectorAll('.move-down-btn').forEach(btn => { btn.addEventListener('click', () => moveArrangeItem(parseInt(btn.dataset.index), 1)); }); }
    function moveArrangeItem(index, direction) { const newIndex = index + direction; if (newIndex < 0 || newIndex >= arrangeSongList.length) return; [arrangeSongList[index], arrangeSongList[newIndex]] = [arrangeSongList[newIndex], arrangeSongList[index]]; currentOrder = [...arrangeSongList]; renderArrange(); }
    function switchMode(newMode) { mode = newMode; if (mode === 'normal') { normalCont.style.display = 'block'; arrangePanel.style.display = 'none'; normalFooter.style.display = 'flex'; normalModeBtn.classList.add('active'); arrangeModeBtn.classList.remove('active'); renderNormal(); } else { normalCont.style.display = 'none'; arrangePanel.style.display = 'block'; normalFooter.style.display = 'none'; arrangeModeBtn.classList.add('active'); normalModeBtn.classList.remove('active'); renderArrange(); } }
    normalModeBtn.onclick = () => switchMode('normal');
    arrangeModeBtn.onclick = () => switchMode('arrange');
    document.getElementById('shuffleMixSongsBtn').onclick = () => { for (let i = arrangeSongList.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [arrangeSongList[i], arrangeSongList[j]] = [arrangeSongList[j], arrangeSongList[i]]; } currentOrder = [...arrangeSongList]; renderArrange(); };
    document.getElementById('saveMixOrderBtn').onclick = () => { mixSongs = mixSongs.filter(ms => ms.mixId !== mix.id); currentOrder.forEach(songId => mixSongs.push({ mixId: mix.id, songId })); saveData(); renderMixes(); closeModal('viewMixSongsModal'); showSummary('Order saved.'); };
    search.oninput = () => { if (mode === 'normal') renderNormal(); else renderArrange(); };
    switchMode('normal');
    document.getElementById('viewMixSongsModal').style.display = 'flex';
  }

  // ========== QUICK ADD ==========
  function openQuickAddModal(songId) {
    const song = songs.find(s => s.id === songId); if (!song) return;
    quickAddSongId = songId; quickAddSelectedTags.clear(); quickAddSelectedMixes.clear(); quickAddActiveTab = 'tags';
    document.getElementById('quickAddSongTitle').innerText = song.title;
    document.getElementById('quickAddTagsTab').classList.add('active'); document.getElementById('quickAddMixesTab').classList.remove('active');
    document.getElementById('quickAddTagsPanel').style.display = 'block'; document.getElementById('quickAddMixesPanel').style.display = 'none';
    renderQuickAddTags(); renderQuickAddMixes(); updateQuickAddSelectedCount();
    document.getElementById('quickAddTagsTab').onclick = () => { quickAddActiveTab = 'tags'; document.getElementById('quickAddTagsTab').classList.add('active'); document.getElementById('quickAddMixesTab').classList.remove('active'); document.getElementById('quickAddTagsPanel').style.display = 'block'; document.getElementById('quickAddMixesPanel').style.display = 'none'; };
    document.getElementById('quickAddMixesTab').onclick = () => { quickAddActiveTab = 'mixes'; document.getElementById('quickAddMixesTab').classList.add('active'); document.getElementById('quickAddTagsTab').classList.remove('active'); document.getElementById('quickAddMixesPanel').style.display = 'block'; document.getElementById('quickAddTagsPanel').style.display = 'none'; };
    document.getElementById('quickAddTagSearch').oninput = renderQuickAddTags;
    document.getElementById('quickAddMixSearch').oninput = renderQuickAddMixes;
    document.getElementById('confirmQuickAddBtn').onclick = () => { let added = 0; for (const tagId of quickAddSelectedTags) { if (!songTags.some(st => st.songId === quickAddSongId && st.tagId === tagId)) { songTags.push({ songId: quickAddSongId, tagId }); added++; } } for (const mixId of quickAddSelectedMixes) { if (!mixSongs.some(ms => ms.mixId === mixId && ms.songId === quickAddSongId)) { mixSongs.push({ mixId, songId: quickAddSongId }); added++; } } if (added) { saveData(); refreshUI(); } closeModal('quickAddModal'); showSummary(`Added to ${added} items.`); };
    document.getElementById('quickAddModal').style.display = 'flex';
  }
  function renderQuickAddTags() { const search = document.getElementById('quickAddTagSearch').value; const cont = document.getElementById('quickAddTagsList'); const availableTags = tags.filter(t => !songTags.some(st => st.songId === quickAddSongId && st.tagId === t.id)); const filtered = availableTags.filter(t => fuzzyMatch(t.name, search)); cont.innerHTML = filtered.map(t => { const checked = quickAddSelectedTags.has(t.id) ? 'checked' : ''; return `<div class="check-item"><input type="checkbox" value="${t.id}" id="qtag_${t.id}" ${checked}><label for="qtag_${t.id}">🏷️ ${escapeHtml(t.name)}</label></div>`; }).join(''); cont.querySelectorAll('input').forEach(cb => cb.addEventListener('change', () => { if (cb.checked) quickAddSelectedTags.add(cb.value); else quickAddSelectedTags.delete(cb.value); updateQuickAddSelectedCount(); })); }
  function renderQuickAddMixes() { const search = document.getElementById('quickAddMixSearch').value; const cont = document.getElementById('quickAddMixesList'); const availableMixes = mixes.filter(m => !mixSongs.some(ms => ms.mixId === m.id && ms.songId === quickAddSongId)); const filtered = availableMixes.filter(m => fuzzyMatch(m.title, search) || fuzzyMatch(m.keyphrases||'', search)); cont.innerHTML = filtered.map(m => { const checked = quickAddSelectedMixes.has(m.id) ? 'checked' : ''; return `<div class="check-item"><input type="checkbox" value="${m.id}" id="qmix_${m.id}" ${checked}><label for="qmix_${m.id}">🎚️ ${escapeHtml(m.title)}</label></div>`; }).join(''); cont.querySelectorAll('input').forEach(cb => cb.addEventListener('change', () => { if (cb.checked) quickAddSelectedMixes.add(cb.value); else quickAddSelectedMixes.delete(cb.value); updateQuickAddSelectedCount(); })); }
  function updateQuickAddSelectedCount() { const total = quickAddSelectedTags.size + quickAddSelectedMixes.size; document.getElementById('quickAddSelectedCount').innerText = `${total} selected`; }

  // ========== OTHER MODALS ==========
  function openViewTagSongsModal(tag) { document.getElementById('viewTagSongsTitle').innerHTML = `Songs in “${escapeHtml(tag.name)}”`; const search = document.getElementById('viewTagSongsSearch'); const cont = document.getElementById('viewTagSongsList'); const countSpan = document.getElementById('viewTagSongsCount'); function render() { const q = search.value; const ids = songTags.filter(st=>st.tagId===tag.id).map(st=>st.songId); const filtered = songs.filter(s=>ids.includes(s.id) && (fuzzyMatch(s.title, q) || fuzzyMatch(s.lyrics||'', q))); cont.innerHTML = filtered.map(s=>`<div class="simple-song-item">🎵 ${escapeHtml(s.title)}</div>`).join(''); countSpan.innerText = filtered.length; } render(); search.oninput = render; document.getElementById('viewTagSongsModal').style.display='flex'; }
  function openEditTagModal(id) { editingTagId = id; const tag = tags.find(t=>t.id===id); if(tag){ document.getElementById('editTagNameInput').value = tag.name; document.getElementById('editTagDescInput').value = tag.description||''; document.getElementById('editTagModalTitle').innerText = `Edit Tag – ${escapeHtml(tag.name)}`; } tagSongsToRemove.clear(); document.getElementById('editTagDetailsPanel').style.display='block'; document.getElementById('editTagSongsPanel').style.display='none'; document.getElementById('editTagDetailsTab').classList.add('active'); document.getElementById('editTagSongsTab').classList.remove('active'); document.getElementById('editTagModal').style.display='flex'; renderEditTagSongsList(); }
  function renderEditTagSongsList() { const search = document.getElementById('editTagSongsSearch').value; const songIdsInTag = songTags.filter(st=>st.tagId===editingTagId).map(st=>st.songId); const songsInTag = songs.filter(s=>songIdsInTag.includes(s.id) && (fuzzyMatch(s.title, search) || fuzzyMatch(s.lyrics||'', search))); const cont = document.getElementById('editTagSongsList'); cont.innerHTML = songsInTag.map(s=>`<div class="check-item"><input type="checkbox" value="${s.id}" id="etsong_${s.id}" ${tagSongsToRemove.has(s.id)?'':'checked'}><label for="etsong_${s.id}">🎵 ${escapeHtml(s.title)}</label></div>`).join(''); const remaining = songsInTag.filter(s=>!tagSongsToRemove.has(s.id)).length; document.getElementById('editTagSongsSelectedCount').innerText = `${remaining} songs will remain (${tagSongsToRemove.size} to remove)`; cont.querySelectorAll('input').forEach(cb=>cb.addEventListener('change', ()=>{ if(!cb.checked) tagSongsToRemove.add(cb.value); else tagSongsToRemove.delete(cb.value); const newRem = songsInTag.filter(s=>!tagSongsToRemove.has(s.id)).length; document.getElementById('editTagSongsSelectedCount').innerText = `${newRem} songs will remain (${tagSongsToRemove.size} to remove)`; })); }
  function openAddSongModal() { editingSongId=null; document.getElementById('songTitleInput').value=''; document.getElementById('songLyricsInput').value=''; document.getElementById('songModalTitle').innerText='Add Song'; document.getElementById('songModal').style.display='flex'; }
  function openEditSongModal(id) { editingSongId=id; const s=songs.find(s=>s.id===id); if(s){ document.getElementById('songTitleInput').value=s.title; document.getElementById('songLyricsInput').value=s.lyrics||''; } document.getElementById('songModalTitle').innerText='Edit Song'; document.getElementById('songModal').style.display='flex'; }
  function openAssignTagsModal(songId) { currentAssignSongId=songId; const song = songs.find(s=>s.id===songId); const container=document.getElementById('assignTagsList'); const searchInput=document.getElementById('assignTagSearch'); const countSpan=document.getElementById('assignTagsCount'); const titleElement = document.createElement('div'); titleElement.className = 'assign-song-title'; titleElement.id = 'assignModalSongTitle'; titleElement.innerHTML = `📌 Song: ${escapeHtml(song?.title || 'Unknown')}`; const modalContent = document.querySelector('#assignTagsModal .modal-content'); const existingTitle = document.getElementById('assignModalSongTitle'); if (existingTitle) existingTitle.remove(); modalContent.insertBefore(titleElement, document.getElementById('assignTagSearch')); const selectedIds = new Set(songTags.filter(st => st.songId === songId).map(st => st.tagId)); function render() { const search = searchInput.value; const filteredTags = tags.filter(t => fuzzyMatch(t.name, search)); container.innerHTML = filteredTags.map(tag => { const checked = selectedIds.has(tag.id) ? 'checked' : ''; return `<div class="check-item"><input type="checkbox" value="${tag.id}" id="atag_${tag.id}" ${checked}><label for="atag_${tag.id}">🏷️ ${escapeHtml(tag.name)}</label></div>`; }).join(''); countSpan.innerText = `${filteredTags.length} tag${filteredTags.length !== 1 ? 's' : ''}`; } container.addEventListener('change', (e) => { if (e.target.type === 'checkbox') { const tagId = e.target.value; if (e.target.checked) selectedIds.add(tagId); else selectedIds.delete(tagId); } }); searchInput.oninput = render; render(); document.getElementById('confirmAssignTagsBtn').onclick = () => { songTags = songTags.filter(st => st.songId !== currentAssignSongId); for (const tagId of selectedIds) songTags.push({ songId: currentAssignSongId, tagId }); saveData(); renderSongs(); renderTags(); closeAllModals(); }; document.getElementById('assignTagsModal').style.display = 'flex'; }
  function openAddMixModal() { editingMixId=null; document.getElementById('mixModalTitle').innerText='Add Mix'; document.getElementById('mixTitleInput').value=''; document.getElementById('mixDescInput').value=''; document.getElementById('mixKeyphrasesInput').value=''; document.getElementById('mixModal').style.display='flex'; }
  function openEditMixModal(id) { const mix=mixes.find(m=>m.id===id); if(!mix) return; editingMixId=id; document.getElementById('mixModalTitle').innerText='Edit Mix'; document.getElementById('mixTitleInput').value=mix.title; document.getElementById('mixDescInput').value=mix.description||''; document.getElementById('mixKeyphrasesInput').value=mix.keyphrases||''; document.getElementById('mixModal').style.display='flex'; }
  function openCloneMixModal(id) { const orig=mixes.find(m=>m.id===id); if(!orig) return; document.getElementById('cloneMixTitleInput').value=orig.title+' (copy)'; document.getElementById('cloneMixDescInput').value=orig.description||''; document.getElementById('cloneMixKeyphrasesInput').value=orig.keyphrases||''; document.getElementById('cloneMixCopySongsCheck').checked=true; document.getElementById('confirmCloneMixBtn').onclick=()=>{ const title=document.getElementById('cloneMixTitleInput').value.trim(); if(!title){ showSummary('Title required'); return; } const newMix={ id:genId(), title, description:document.getElementById('cloneMixDescInput').value, keyphrases:document.getElementById('cloneMixKeyphrasesInput').value, createdAt:Date.now() }; mixes.push(newMix); if(document.getElementById('cloneMixCopySongsCheck').checked){ const ids=mixSongs.filter(ms=>ms.mixId===id).map(ms=>ms.songId); ids.forEach(sid=>mixSongs.push({mixId:newMix.id, songId:sid})); } saveData(); renderMixes(); closeModal('cloneMixModal'); showSummary(`Mix cloned.`); }; document.getElementById('cloneMixModal').style.display='flex'; }
  function showStats() { const total=songs.length; const tagged=new Set(songTags.map(st=>st.songId)).size; document.getElementById('statsContent').innerHTML=`📊 <strong>Total Songs:</strong> ${total}<br>🏷️ <strong>Tagged Songs:</strong> ${tagged}<br>🚫 <strong>Untagged Songs:</strong> ${total-tagged}<br>🏷️ <strong>Total Tags:</strong> ${tags.length}<br>🎚️ <strong>Total Mixes:</strong> ${mixes.length}`; document.getElementById('statsModal').style.display='flex'; }

  // Bind events
  function bindEvents() {
    document.getElementById('menuToggle').onclick = openDrawer;
    document.getElementById('drawerClose').onclick = closeDrawer;
    drawerOverlay.onclick = closeDrawer;
    document.querySelectorAll('.drawer-item[data-panel]').forEach(item=>item.addEventListener('click',()=>switchPanel(item.dataset.panel)));
    document.getElementById('closeBottomDrawer').onclick = closeBottomDrawer;
    document.getElementById('cancelBottomDrawer').onclick = closeBottomDrawer;
    bottomOverlay.onclick = closeBottomDrawer;
    document.getElementById('addSingleSongOption').onclick = ()=>{ closeBottomDrawer(); openAddSongModal(); };
    document.getElementById('bulkTitlesOption').onclick = ()=>{ closeBottomDrawer(); document.getElementById('bulkModal').style.display='flex'; };
    document.getElementById('bulkLyricsOption').onclick = ()=>{ closeBottomDrawer(); document.getElementById('bulkLyricsModal').style.display='flex'; };
    document.getElementById('exportBtnDrawer').onclick = exportData;
    document.getElementById('exportBtnSettings').onclick = exportData;
    document.getElementById('importBtnDrawer').onclick = ()=>document.getElementById('importFileInput').click();
    document.getElementById('importBtnSettings').onclick = ()=>document.getElementById('importFileInput').click();
    document.getElementById('resetAllDataBtn').onclick = async ()=>{ if (await confirmAction('Reset all data?')) { songs=[]; tags=[]; songTags=[]; mixes=[]; mixSongs=[]; saveData(); refreshUI(); showSummary('Data reset.'); } };
    document.getElementById('songSearch').addEventListener('input', renderSongs);
    document.getElementById('sortSongs').addEventListener('change', renderSongs);
    document.getElementById('tagSearch').addEventListener('input', renderTags);
    document.getElementById('sortTags').addEventListener('change', renderTags);
    document.getElementById('mixSearch').addEventListener('input', renderMixes);
    document.getElementById('sortMixes').addEventListener('change', renderMixes);
    document.getElementById('confirmBulkBtn').onclick = ()=>{ const text = document.getElementById('bulkSongsTextarea').value; if (text.trim()) runBulkWithProgress(parseTitlesBulk(text).map(t=>({title:t})), addSingleTitle, []); closeModal('bulkModal'); };
    document.getElementById('confirmBulkLyricsBtn').onclick = ()=>{ const text = document.getElementById('bulkLyricsTextarea').value; if (text.trim()) runBulkWithProgress(parseLyricsBulk(text), addSingleLyricsItem, []); closeModal('bulkLyricsModal'); };
    document.getElementById('bulkAddToTagBtn').onclick = ()=>{ const text = document.getElementById('bulkSongsTextarea').value; if (text.trim()) openBulkTagSelectModal('titles', text); else showSummary('No songs'); };
    document.getElementById('bulkLyricsAddToTagBtn').onclick = ()=>{ const text = document.getElementById('bulkLyricsTextarea').value; if (text.trim()) openBulkTagSelectModal('lyrics', text); else showSummary('No songs'); };
    document.getElementById('saveSongBtn').onclick = async ()=>{ let title = document.getElementById('songTitleInput').value.trim(); const lyrics = document.getElementById('songLyricsInput').value; if (!title && lyrics) title = autoTitleFromLyrics(lyrics); if (!title) { showSummary('Title required'); return; } if (!editingSongId) { if (!await confirmAddWithDuplicateCheck(title)) { closeAllModals(); return; } songs.push({ id: genId(), title, lyrics, createdAt: Date.now() }); } else { const s = songs.find(s=>s.id===editingSongId); if (s) { s.title=title; s.lyrics=lyrics; } } saveData(); refreshUI(); closeAllModals(); };
    document.getElementById('confirmAddTagBtn').onclick = ()=>{ const name = document.getElementById('addTagNameInput').value.trim(); if (!name) { showSummary('Tag name required'); return; } tags.push({ id: genId(), name, description: document.getElementById('addTagDescInput').value }); saveData(); renderSongs(); renderTags(); closeModal('addTagModal'); };
    document.getElementById('saveMixBtn').onclick = ()=>{ const title = document.getElementById('mixTitleInput').value.trim(); if (!title) { showSummary('Title required'); return; } const data = { title, description: document.getElementById('mixDescInput').value, keyphrases: document.getElementById('mixKeyphrasesInput').value, createdAt: editingMixId ? mixes.find(m=>m.id===editingMixId)?.createdAt : Date.now() }; if (editingMixId) Object.assign(mixes.find(m=>m.id===editingMixId), data); else mixes.push({ id: genId(), ...data }); saveData(); renderMixes(); closeModal('mixModal'); };
    document.getElementById('saveEditTagBtn').onclick = ()=>{ const name = document.getElementById('editTagNameInput').value.trim(); if (!name) { showSummary('Tag name required'); return; } const tag = tags.find(t=>t.id===editingTagId); if (tag) { tag.name=name; tag.description=document.getElementById('editTagDescInput').value; } for (const sid of tagSongsToRemove) songTags = songTags.filter(st=>!(st.songId===sid && st.tagId===editingTagId)); saveData(); renderSongs(); renderTags(); closeModal('editTagModal'); };
    document.getElementById('editTagDetailsTab').onclick = ()=>{ document.getElementById('editTagDetailsPanel').style.display='block'; document.getElementById('editTagSongsPanel').style.display='none'; document.getElementById('editTagDetailsTab').classList.add('active'); document.getElementById('editTagSongsTab').classList.remove('active'); };
    document.getElementById('editTagSongsTab').onclick = ()=>{ document.getElementById('editTagDetailsPanel').style.display='none'; document.getElementById('editTagSongsPanel').style.display='block'; document.getElementById('editTagDetailsTab').classList.remove('active'); document.getElementById('editTagSongsTab').classList.add('active'); renderEditTagSongsList(); };
    document.getElementById('editTagSongsSearch').addEventListener('input', renderEditTagSongsList);
    document.getElementById('dupAddBtn').onclick = ()=>{ if(currentResolveDuplicate){ currentResolveDuplicate(true); closeModal('duplicateModal'); currentResolveDuplicate=null; } };
    document.getElementById('dupSkipBtn').onclick = ()=>{ if(currentResolveDuplicate){ currentResolveDuplicate(false); closeModal('duplicateModal'); currentResolveDuplicate=null; } };
    document.getElementById('progressCloseBtn').onclick = ()=>closeModal('progressModal');
    document.getElementById('confirmYesBtn').onclick = ()=>{ if(confirmResolve){ confirmResolve(true); closeModal('confirmationModal'); confirmResolve=null; } };
    document.getElementById('confirmNoBtn').onclick = ()=>{ if(confirmResolve){ confirmResolve(false); closeModal('confirmationModal'); confirmResolve=null; } };
    document.querySelectorAll('.close-modal').forEach(el=>el.addEventListener('click', closeAllModals));
    window.onclick = e => { if (e.target.classList.contains('modal')) closeAllModals(); };
    document.getElementById('importFileInput').onchange = e => { if (e.target.files[0]) importData(e.target.files[0]); };
    document.getElementById('statsBtn')?.addEventListener('click', showStats);
  }

  async function init() { await loadFromServer(); bindEvents(); renderSongs(); renderTags(); renderMixes(); updateFABs('songs'); updateFilterBadge(); }
  init();
})();
</script>
</body>
</html>