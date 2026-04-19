(function() {
  "use strict";

  // ---------- DATA ----------
  let songs = [];
  let tags = [];
  let songTags = [];
  let mixes = [];
  let mixSongs = [];

  // Filter state
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

  const STORAGE_KEY = "SongTagAppData";

  // ---------- UTILS ----------
  function genId() { return Date.now() + '-' + Math.random().toString(36).substr(2, 6); }
  function escapeHtml(str) {
    return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m] || m);
  }
  function similarity(s1, s2) {
    let a = s1.toLowerCase().replace(/\s+/g, ' ').trim();
    let b = s2.toLowerCase().replace(/\s+/g, ' ').trim();
    if (a === b) return 1;
    let longer = a.length > b.length ? a : b;
    let shorter = a.length > b.length ? b : a;
    if (longer.length === 0) return 1;
    const editDist = (s1,s2) => {
      let m = s1.length, n = s2.length;
      let dp = Array(m+1).fill().map(()=>Array(n+1).fill(0));
      for(let i=0;i<=m;i++) dp[i][0]=i;
      for(let j=0;j<=n;j++) dp[0][j]=j;
      for(let i=1;i<=m;i++) for(let j=1;j<=n;j++) dp[i][j]=Math.min(dp[i-1][j]+1, dp[i][j-1]+1, dp[i-1][j-1]+(s1[i-1]===s2[j-1]?0:1));
      return dp[m][n];
    };
    let dist = editDist(a, b);
    return 1 - dist / longer.length;
  }
  function findSimilarSongs(newTitle, threshold=0.85) {
    return songs.filter(s => similarity(s.title, newTitle) >= threshold).map(s => ({ title: s.title, sim: similarity(s.title, newTitle) }));
  }
  function autoTitleFromLyrics(lyrics) {
    let lines = lyrics.split(/\r?\n/).filter(l=>l.trim());
    return lines.slice(0,2).join(" ").trim().substring(0,60);
  }

  // ---------- STORAGE ----------
  function saveToLocalStorage() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ songs, tags, songTags, mixes, mixSongs }));
  }
  function loadFromLocalStorage() {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      try {
        const data = JSON.parse(raw);
        songs = data.songs || [];
        tags = data.tags || [];
        songTags = data.songTags || [];
        mixes = data.mixes || [];
        mixSongs = data.mixSongs || [];
        songs = songs.map(s => ({ ...s, lyrics: s.lyrics || "" }));
        tags = tags.map(t => ({ ...t, description: t.description || "" }));
        mixes = mixes.map(m => ({ ...m, description: m.description || "", keyphrases: m.keyphrases || "", createdAt: m.createdAt || Date.now() }));
      } catch(e) {}
    }
    if (!songs.length) {
      songs = [
        { id: genId(), title: "Aye", lyrics: "Aye aye aye\nOmo to shan..." },
        { id: genId(), title: "Ife", lyrics: "Ife mi\nMo fe e..." },
        { id: genId(), title: "Orente", lyrics: "Orente mi\nWahala..." },
        { id: genId(), title: "Fall", lyrics: "Fall in love again..." },
        { id: genId(), title: "Joro", lyrics: "Joro joro\nFire..." }
      ];
      tags = [
        { id: genId(), name: "Afrobeat", description: "" },
        { id: genId(), name: "Love", description: "" },
        { id: genId(), name: "Party", description: "" }
      ];
      songTags = [
        { songId: songs[0].id, tagId: tags[0].id },
        { songId: songs[1].id, tagId: tags[1].id },
        { songId: songs[2].id, tagId: tags[0].id }
      ];
    }
    if (!mixes.length) {
      mixes = [
        { id: genId(), title: "Sunday Worship", description: "Calm worship songs", keyphrases: "worship,slow,gospel", createdAt: Date.now() }
      ];
      mixSongs = [];
    }
    updateCounts();
    updateDrawerCounts();
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

  // ---------- UI: Drawer, Panels, FABs ----------
  const drawer = document.getElementById('drawer');
  const drawerOverlay = document.getElementById('drawerOverlay');
  function openDrawer() { drawer.classList.add('open'); drawerOverlay.classList.add('open'); }
  function closeDrawer() { drawer.classList.remove('open'); drawerOverlay.classList.remove('open'); }

  function switchPanel(panelId) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.getElementById(`panel-${panelId}`).classList.add('active');
    document.querySelectorAll('.drawer-item').forEach(item => {
      item.classList.remove('active');
      if (item.dataset.panel === panelId) item.classList.add('active');
    });
    closeDrawer();
    updateFABs(panelId);
  }

  const fabContainer = document.getElementById('fabContainer');
  function updateFABs(panel) {
    if (panel === 'songs') {
      fabContainer.innerHTML = `
        <button class="fab small" id="filterFab" title="Filter by tags">🏷️</button>
        <button class="fab" id="addSongFab" title="Add song">➕</button>
      `;
      document.getElementById('filterFab').onclick = () => openTagFilterModal();
      document.getElementById('addSongFab').onclick = openBottomDrawer;
    } else if (panel === 'tags') {
      fabContainer.innerHTML = `<button class="fab" id="addTagFab" title="Add tag">➕</button>`;
      document.getElementById('addTagFab').onclick = () => {
        document.getElementById('addTagNameInput').value = '';
        document.getElementById('addTagDescInput').value = '';
        document.getElementById('addTagModal').style.display = 'flex';
      };
    } else if (panel === 'mixes') {
      fabContainer.innerHTML = `<button class="fab" id="addMixFab" title="Add mix">➕</button>`;
      document.getElementById('addMixFab').onclick = openAddMixModal;
    } else {
      fabContainer.innerHTML = '';
    }
  }

  // Bottom drawer
  const bottomDrawer = document.getElementById('bottomDrawer');
  const bottomOverlay = document.getElementById('bottomDrawerOverlay');
  function openBottomDrawer() { bottomDrawer.classList.add('open'); bottomOverlay.classList.add('open'); }
  function closeBottomDrawer() { bottomDrawer.classList.remove('open'); bottomOverlay.classList.remove('open'); }

  // Settings export/import/reset
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
        songs = (d.songs || []).map(s=>({...s, lyrics:s.lyrics||""}));
        tags = (d.tags || []).map(t=>({...t, description:t.description||""}));
        songTags = d.songTags || [];
        mixes = (d.mixes || []).map(m=>({...m, description:m.description||"", keyphrases:m.keyphrases||"", createdAt:m.createdAt||Date.now()}));
        mixSongs = d.mixSongs || [];
        saveToLocalStorage();
        renderSongs(); renderTags(); renderMixes();
        updateDrawerCounts();
        showSummary('Import successful!');
      } catch (ex) { showSummary('Invalid JSON file.'); }
    };
    reader.readAsText(file);
  }

  // ---------- CONFIRMATION, MODALS ----------
  function confirmAction(msg) {
    return new Promise(resolve => {
      document.getElementById('confirmationMessage').innerText = msg;
      confirmResolve = resolve;
      document.getElementById('confirmationModal').style.display = 'flex';
    });
  }
  function closeModal(id) { document.getElementById(id).style.display = 'none'; }
  function closeAllModals() { document.querySelectorAll('.modal').forEach(m => m.style.display = 'none'); }
  function showSummary(msg) {
    document.getElementById('summaryMessage').innerHTML = msg;
    document.getElementById('summaryModal').style.display = 'flex';
  }

  // ---------- DUPLICATE HANDLING ----------
  function confirmAddWithDuplicateCheck(newTitle) {
    return new Promise(resolve => {
      const similar = findSimilarSongs(newTitle, 0.85);
      if (!similar.length) { resolve(true); return; }
      document.getElementById('dupNewTitle').innerText = newTitle;
      document.getElementById('dupSimilarList').innerHTML = similar.map(s => 
        `<div class="similarity-item">📌 ${escapeHtml(s.title)} (${Math.round(s.sim*100)}% similar)</div>`
      ).join('');
      currentResolveDuplicate = resolve;
      document.getElementById('duplicateModal').style.display = 'flex';
    });
  }

  // ---------- PROGRESS MODAL ----------
  let currentProgressActive = false;
  function openProgressModal(total) {
    const modal = document.getElementById('progressModal');
    modal.style.display = 'flex';
    document.getElementById('progressSummary').style.display = 'none';
    document.getElementById('progressCloseBtn').disabled = true;
    document.getElementById('progressBarFill').style.width = '0%';
    document.getElementById('progressDetail').innerHTML = '';
    document.getElementById('progressStatus').innerHTML = `0 / ${total} songs`;
    currentProgressActive = true;
  }
  function updateProgress(current, total, currentTitle) {
    if (!currentProgressActive) return;
    const percent = (current/total)*100;
    document.getElementById('progressBarFill').style.width = percent+'%';
    document.getElementById('progressStatus').innerHTML = `${current} / ${total} songs`;
    document.getElementById('progressDetail').innerHTML = `Now adding: ${escapeHtml(currentTitle)}`;
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
  async function runBulkWithProgress(items, addFunction, tagIdsToAssign = []) {
    const total = items.length;
    openProgressModal(total);
    let added = 0, skipped = 0, duplicates = 0;
    const addedSongIds = [];
    for (let i = 0; i < total; i++) {
      if (!currentProgressActive && i > 0) break;
      updateProgress(i+1, total, items[i].title || items[i]);
      const shouldAdd = await confirmAddWithDuplicateCheck(items[i].title || items[i]);
      if (shouldAdd) {
        const newSong = addFunction(items[i]);
        if (newSong) {
          added++;
          addedSongIds.push(newSong.id);
        } else { skipped++; duplicates++; }
      } else { skipped++; }
    }
    if (tagIdsToAssign.length && addedSongIds.length) {
      for (const songId of addedSongIds) {
        for (const tagId of tagIdsToAssign) {
          if (!songTags.some(st => st.songId === songId && st.tagId === tagId)) {
            songTags.push({ songId, tagId });
          }
        }
      }
      saveToLocalStorage();
      renderSongs();
      renderTags();
    }
    finishProgress(`✅ Added: ${added}<br>⏭️ Skipped: ${skipped}<br>⚠️ Duplicates avoided: ${duplicates}`);
  }

  // ---------- BULK PARSING ----------
  function parseTitlesBulk(text) {
    const lines = text.split(/\r?\n/);
    const usesDelimiter = lines.some(l => l.trim().startsWith('#'));
    if (!usesDelimiter) return lines.filter(l => l.trim()).map(l => l.trim());
    const titles = [];
    let cur = null;
    for (const line of lines) {
      if (line.trim().startsWith('#')) {
        if (cur) titles.push(cur.trim());
        cur = line.replace(/^#\s*/, '').trim();
      } else if (cur && line.trim()) {
        cur += ' ' + line.trim();
      }
    }
    if (cur) titles.push(cur.trim());
    return titles;
  }
  function addSingleTitle(item) {
    if (songs.some(s => s.title.toLowerCase() === item.title.toLowerCase())) return null;
    const newSong = { id: genId(), title: item.title, lyrics: "" };
    songs.push(newSong);
    saveToLocalStorage(); renderSongs(); renderTags(); renderMixes();
    return newSong;
  }
  function parseLyricsBulk(text) {
    const lines = text.split(/\r?\n/);
    const result = [];
    for (let i = 0; i < lines.length; i++) {
      const l = lines[i].trim();
      if (l.startsWith('#')) {
        const title = l.replace(/^#\s*/, '').trim();
        i++;
        while (i < lines.length && lines[i].trim() === '') i++;
        if (i >= lines.length || lines[i].trim() !== '---') continue;
        i++;
        const lyricsLines = [];
        while (i < lines.length && !lines[i].trim().startsWith('#')) {
          lyricsLines.push(lines[i]);
          i++;
        }
        const lyrics = lyricsLines.join('\n').trim();
        if (title) result.push({ title, lyrics });
        i--;
      }
    }
    return result;
  }
  function addSingleLyricsItem(item) {
    let finalTitle = item.title;
    if (!finalTitle && item.lyrics) finalTitle = autoTitleFromLyrics(item.lyrics);
    if (!finalTitle) return null;
    if (songs.some(s => s.title.toLowerCase() === finalTitle.toLowerCase())) return null;
    const newSong = { id: genId(), title: finalTitle, lyrics: item.lyrics };
    songs.push(newSong);
    saveToLocalStorage(); renderSongs(); renderTags(); renderMixes();
    return newSong;
  }

  // ---------- BULK TAG SELECTION ----------
  function openBulkTagSelectModal(type, text) {
    currentBulkType = type;
    currentBulkText = text;
    const container = document.getElementById('bulkTagList');
    const searchInput = document.getElementById('bulkTagSearch');
    const countSpan = document.getElementById('bulkTagSelectCount');
    function renderTagList() {
      const search = searchInput.value.toLowerCase();
      const filtered = tags.filter(t => t.name.toLowerCase().includes(search));
      container.innerHTML = filtered.map(tag => 
        `<div class="check-item"><input type="checkbox" value="${tag.id}" id="btag_${tag.id}"><label for="btag_${tag.id}">🏷️ ${escapeHtml(tag.name)}</label></div>`
      ).join('');
      countSpan.innerText = `${filtered.length} tag${filtered.length !== 1 ? 's' : ''}`;
      updateSelectedCount();
    }
    function updateSelectedCount() {
      const selected = document.querySelectorAll('#bulkTagList input:checked').length;
      document.getElementById('bulkTagSelectedCount').innerText = `${selected} tag(s) selected`;
    }
    renderTagList();
    container.addEventListener('change', updateSelectedCount);
    searchInput.oninput = renderTagList;
    document.getElementById('bulkTagConfirmBtn').onclick = () => {
      const selectedIds = Array.from(document.querySelectorAll('#bulkTagList input:checked')).map(cb => cb.value);
      closeModal('bulkTagSelectModal');
      if (currentBulkType === 'titles') {
        const items = parseTitlesBulk(currentBulkText).map(t => ({ title: t }));
        runBulkWithProgress(items, addSingleTitle, selectedIds);
      } else {
        const items = parseLyricsBulk(currentBulkText);
        runBulkWithProgress(items, addSingleLyricsItem, selectedIds);
      }
      currentBulkType = null;
      currentBulkText = null;
    };
    document.getElementById('bulkTagSelectModal').style.display = 'flex';
  }

  // ---------- TAG FILTER MODAL (with exclusion) ----------
  function openTagFilterModal() {
    const modal = document.getElementById('tagFilterModal');
    const includeContainer = document.getElementById('filterTagsList');
    const excludeContainer = document.getElementById('excludeTagsList');
    const includeSearch = document.getElementById('filterTagSearch');
    const excludeSearch = document.getElementById('excludeTagSearch');
    const countSpan = document.getElementById('filterTagsCount');

    function renderIncludeList() {
      const search = includeSearch.value.toLowerCase();
      const filtered = tags.filter(t => t.name.toLowerCase().includes(search));
      includeContainer.innerHTML = filtered.map(tag => 
        `<div class="check-item"><input type="checkbox" value="${tag.id}" id="ftag_${tag.id}" ${currentFilterTags.includes(tag.id) ? 'checked' : ''}><label for="ftag_${tag.id}">🏷️ ${escapeHtml(tag.name)}</label></div>`
      ).join('');
      updateIncludeChips();
    }
    function renderExcludeList() {
      const search = excludeSearch.value.toLowerCase();
      const filtered = tags.filter(t => t.name.toLowerCase().includes(search));
      excludeContainer.innerHTML = filtered.map(tag => 
        `<div class="check-item"><input type="checkbox" value="${tag.id}" id="etag_${tag.id}" ${currentExcludeTags.includes(tag.id) ? 'checked' : ''}><label for="etag_${tag.id}">🚫 ${escapeHtml(tag.name)}</label></div>`
      ).join('');
      updateExcludeChips();
    }
    function updateIncludeChips() {
      const chipDiv = document.getElementById('selectedFilterChips');
      const selectedIds = Array.from(document.querySelectorAll('#filterTagsList input:checked')).map(cb => cb.value);
      chipDiv.innerHTML = selectedIds.map(id => {
        const t = tags.find(t => t.id === id);
        return t ? `<span class="chip">🏷️ ${escapeHtml(t.name)}</span>` : '';
      }).join('');
    }
    function updateExcludeChips() {
      const chipDiv = document.getElementById('selectedExcludeChips');
      const selectedIds = Array.from(document.querySelectorAll('#excludeTagsList input:checked')).map(cb => cb.value);
      chipDiv.innerHTML = selectedIds.map(id => {
        const t = tags.find(t => t.id === id);
        return t ? `<span class="chip" style="background:#fee2e2;color:#b91c1c;">🚫 ${escapeHtml(t.name)}</span>` : '';
      }).join('');
    }
    function updateTotalCount() {
      countSpan.innerText = `${tags.length} tag${tags.length !== 1 ? 's' : ''}`;
    }

    renderIncludeList();
    renderExcludeList();
    updateTotalCount();
    includeContainer.addEventListener('change', updateIncludeChips);
    excludeContainer.addEventListener('change', updateExcludeChips);
    includeSearch.oninput = renderIncludeList;
    excludeSearch.oninput = renderExcludeList;

    document.getElementById('toggleOrBtn').onclick = () => {
      currentFilterMode = "OR";
      document.getElementById('toggleOrBtn').classList.add('active');
      document.getElementById('toggleAndBtn').classList.remove('active');
    };
    document.getElementById('toggleAndBtn').onclick = () => {
      currentFilterMode = "AND";
      document.getElementById('toggleAndBtn').classList.add('active');
      document.getElementById('toggleOrBtn').classList.remove('active');
    };
    document.getElementById('untaggedFilterBtn').onclick = () => {
      filterUntagged = true;
      currentFilterTags = [];
      currentExcludeTags = [];
      renderSongs();
      updateFilterBadge();
      closeModal('tagFilterModal');
    };
    document.getElementById('applyFilterBtn').onclick = () => {
      filterUntagged = false;
      currentFilterTags = Array.from(document.querySelectorAll('#filterTagsList input:checked')).map(cb => cb.value);
      currentExcludeTags = Array.from(document.querySelectorAll('#excludeTagsList input:checked')).map(cb => cb.value);
      renderSongs();
      updateFilterBadge();
      closeModal('tagFilterModal');
    };
    document.getElementById('showFilterPreviewBtn').onclick = () => {
      const includeIds = Array.from(document.querySelectorAll('#filterTagsList input:checked')).map(cb => cb.value);
      const excludeIds = Array.from(document.querySelectorAll('#excludeTagsList input:checked')).map(cb => cb.value);
      const mode = document.getElementById('toggleOrBtn').classList.contains('active') ? "OR" : "AND";
      const filteredSongs = songs.filter(song => {
        const songTagIds = songTags.filter(st => st.songId === song.id).map(st => st.tagId);
        const includePass = includeIds.length === 0 ? true :
          (mode === "OR" ? includeIds.some(tid => songTagIds.includes(tid)) : includeIds.every(tid => songTagIds.includes(tid)));
        const excludePass = excludeIds.length === 0 ? true : !excludeIds.some(tid => songTagIds.includes(tid));
        return includePass && excludePass;
      });
      const includeNames = includeIds.map(id => tags.find(t => t.id === id)?.name).filter(Boolean).join(', ');
      const excludeNames = excludeIds.map(id => tags.find(t => t.id === id)?.name).filter(Boolean).join(', ');
      const summary = `Include: ${includeNames || 'none'} (${mode}) | Exclude: ${excludeNames || 'none'}`;
      openPreviewFilterModal(filteredSongs, summary);
    };
    modal.style.display = 'flex';
  }

  function openPreviewFilterModal(filteredSongs, summaryText) {
    document.getElementById('previewFilterSummary').innerText = summaryText;
    const searchInput = document.getElementById('previewSearch');
    const container = document.getElementById('previewSongsList');
    const countSpan = document.getElementById('previewCountChip');
    function renderPreview() {
      const search = searchInput.value.toLowerCase();
      const filtered = filteredSongs.filter(s => s.title.toLowerCase().includes(search));
      container.innerHTML = filtered.map(song => `<div class="simple-song-item">🎵 ${escapeHtml(song.title)}</div>`).join('');
      countSpan.innerText = `${filtered.length} song${filtered.length !== 1 ? 's' : ''}`;
    }
    renderPreview();
    searchInput.oninput = renderPreview;
    document.getElementById('previewFilterModal').style.display = 'flex';
  }

  function updateFilterBadge() {
    const badge = document.getElementById('activeFilterBadge');
    if (filterUntagged) {
      badge.innerHTML = `🚫 Filter: Untagged <span style="cursor:pointer; margin-left:6px;" id="clearFilterBtn">✖ Clear</span>`;
      document.getElementById('clearFilterBtn')?.addEventListener('click', () => {
        filterUntagged = false;
        renderSongs();
        updateFilterBadge();
      });
    } else if (currentFilterTags.length || currentExcludeTags.length) {
      const incNames = currentFilterTags.map(id => tags.find(t => t.id === id)?.name).filter(Boolean).join(', ');
      const excNames = currentExcludeTags.map(id => tags.find(t => t.id === id)?.name).filter(Boolean).join(', ');
      let text = '';
      if (incNames) text += `🏷️ Include: ${incNames} (${currentFilterMode}) `;
      if (excNames) text += `🚫 Exclude: ${excNames}`;
      badge.innerHTML = `${text} <span style="cursor:pointer; margin-left:6px;" id="clearFilterBtn">✖ Clear</span>`;
      document.getElementById('clearFilterBtn')?.addEventListener('click', () => {
        currentFilterTags = [];
        currentExcludeTags = [];
        renderSongs();
        updateFilterBadge();
      });
    } else {
      badge.innerHTML = '';
    }
  }

  // ---------- RENDER SONGS ----------
  function getTagsForSong(songId) {
    return tags.filter(t => songTags.some(st => st.songId === songId && st.tagId === t.id));
  }
  function renderSongs() {
  const searchTerm = document.getElementById('songSearch').value.toLowerCase();
  let filtered = songs.filter(s => s.title.toLowerCase().includes(searchTerm));
  if (filterUntagged) {
    filtered = filtered.filter(song => songTags.filter(st => st.songId === song.id).length === 0);
  } else {
    if (currentFilterTags.length) {
      filtered = filtered.filter(song => {
        const ids = songTags.filter(st => st.songId === song.id).map(st => st.tagId);
        return currentFilterMode === "OR"
          ? currentFilterTags.some(tid => ids.includes(tid))
          : currentFilterTags.every(tid => ids.includes(tid));
      });
    }
    if (currentExcludeTags.length) {
      filtered = filtered.filter(song => {
        const ids = songTags.filter(st => st.songId === song.id).map(st => st.tagId);
        return !currentExcludeTags.some(tid => ids.includes(tid));
      });
    }
  }
  const sort = document.getElementById('sortSongs').value;
  if (sort === 'az') filtered.sort((a,b) => a.title.localeCompare(b.title));
  else if (sort === 'za') filtered.sort((a,b) => b.title.localeCompare(a.title));
  else if (sort === 'recent') filtered.sort((a,b) => b.id.localeCompare(a.id));

  const container = document.getElementById('songsContainer');
  if (!filtered.length) {
    container.innerHTML = '<div style="text-align:center;padding:2rem;">✨ No songs match</div>';
    return;
  }

  container.innerHTML = filtered.map(song => {
    const tagList = getTagsForSong(song.id);
    const chips = tagList.map(t => 
      `<span class="chip" data-tagid="${t.id}" data-songid="${song.id}">🏷️ ${escapeHtml(t.name)} <span class="chip-remove" data-tagid="${t.id}" data-songid="${song.id}">✖</span></span>`
    ).join('');
    const preview = song.lyrics ? (song.lyrics.substring(0,50) + (song.lyrics.length > 50 ? "…" : "")) : "No lyrics";
    return `
      <div class="song-card collapsed" data-song-id="${song.id}">
        <div class="card-header">
          <span class="card-icon">🎵</span>
          <span class="card-title">${escapeHtml(song.title)}</span>
          <button class="expand-toggle" aria-label="Expand">▶</button>
        </div>
        <div class="card-details">
          <div class="lyrics-preview">📄 ${escapeHtml(preview)}</div>
          <div class="tag-chips">${chips || '<span style="font-size:0.7rem;">no tags</span>'}</div>
          <div class="card-actions">
            <button class="icon-btn view-lyrics-btn" data-id="${song.id}">📄</button>
            <button class="icon-btn add-tag-btn" data-id="${song.id}">🏷️</button>
            <button class="icon-btn edit-song-btn" data-id="${song.id}">✏️</button>
            <button class="icon-btn delete-song-btn" data-id="${song.id}">🗑️</button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  attachSongEvents();
  attachCollapsibleEvents('song-card');
}

  function attachSongEvents() {
    document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', (e) => {
      if (e.target.classList.contains('chip-remove')) return;
      const tid = c.dataset.tagid;
      if (tid) {
        filterUntagged = false;
        currentFilterTags = [tid];
        currentExcludeTags = [];
        renderSongs();
        updateFilterBadge();
      }
    }));
    document.querySelectorAll('.chip-remove').forEach(rm => rm.addEventListener('click', async (e) => {
      e.stopPropagation();
      const tagId = rm.dataset.tagid, songId = rm.dataset.songid;
      if (await confirmAction('Remove tag?')) {
        songTags = songTags.filter(st => !(st.songId === songId && st.tagId === tagId));
        saveToLocalStorage();
        renderSongs();
        renderTags();
      }
    }));
    document.querySelectorAll('.view-lyrics-btn').forEach(b => b.addEventListener('click', () => {
      const s = songs.find(s => s.id === b.dataset.id);
      if (s) {
        document.getElementById('lyricsContent').innerText = s.lyrics || "";
        document.getElementById('lyricsModal').style.display = 'flex';
      }
    }));
    document.querySelectorAll('.add-tag-btn').forEach(b => b.addEventListener('click', () => openAssignTagsModal(b.dataset.id)));
    document.querySelectorAll('.edit-song-btn').forEach(b => b.addEventListener('click', () => openEditSongModal(b.dataset.id)));
    document.querySelectorAll('.delete-song-btn').forEach(b => b.addEventListener('click', async () => {
      if (await confirmAction('Delete song?')) deleteSongById(b.dataset.id);
    }));
  }

  function deleteSongById(id) {
    songs = songs.filter(s => s.id !== id);
    songTags = songTags.filter(st => st.songId !== id);
    mixSongs = mixSongs.filter(ms => ms.songId !== id);
    saveToLocalStorage();
    renderSongs(); renderTags(); renderMixes();
  }

  // ---------- RENDER TAGS ----------
  function renderTags() {
    const search = document.getElementById('tagSearch').value.toLowerCase();
    const sort = document.getElementById('sortTags').value;
    let filtered = tags.filter(t => t.name.toLowerCase().includes(search));
    if (sort === 'az') filtered.sort((a,b) => a.name.localeCompare(b.name));
    else filtered.sort((a,b) => b.name.localeCompare(a.name));
    const container = document.getElementById('tagsContainer');
    if (!filtered.length) {
      container.innerHTML = '<div style="padding:1rem;">No tags found</div>';
      return;
    }
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
    document.querySelectorAll('.tag-left').forEach(l => l.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = l.closest('.tag-item').dataset.tagId;
      openViewTagSongsModal(tags.find(t => t.id === id));
    }));
    document.querySelectorAll('.add-songs-to-tag-btn').forEach(b => b.addEventListener('click', (e) => {
      e.stopPropagation();
      openAddSongsToTagModal(b.dataset.id);
    }));
    document.querySelectorAll('.edit-tag-btn').forEach(b => b.addEventListener('click', (e) => {
      e.stopPropagation();
      openEditTagModal(b.dataset.id);
    }));
    document.querySelectorAll('.delete-tag-btn').forEach(b => b.addEventListener('click', async (e) => {
      e.stopPropagation();
      if (await confirmAction('Delete tag?')) deleteTagById(b.dataset.id);
    }));
    updateCounts();
  }

  function deleteTagById(id) {
    tags = tags.filter(t => t.id !== id);
    songTags = songTags.filter(st => st.tagId !== id);
    saveToLocalStorage();
    renderSongs(); renderTags();
  }

  function openViewTagSongsModal(tag) {
    document.getElementById('viewTagSongsTitle').innerHTML = `Songs in “${escapeHtml(tag.name)}”`;
    const searchInput = document.getElementById('viewTagSongsSearch');
    const container = document.getElementById('viewTagSongsList');
    const countSpan = document.getElementById('viewTagSongsCount');
    function render() {
      const search = searchInput.value.toLowerCase();
      const songIds = songTags.filter(st => st.tagId === tag.id).map(st => st.songId);
      const songsInTag = songs.filter(s => songIds.includes(s.id) && s.title.toLowerCase().includes(search));
      container.innerHTML = songsInTag.map(s => `<div class="simple-song-item">🎵 ${escapeHtml(s.title)}</div>`).join('');
      countSpan.innerText = `${songsInTag.length} song${songsInTag.length !== 1 ? 's' : ''}`;
    }
    render();
    searchInput.oninput = render;
    document.getElementById('viewTagSongsModal').style.display = 'flex';
  }

  // ---------- RENDER MIXES ----------
  function renderMixes() {
  const search = document.getElementById('mixSearch').value.toLowerCase();
  const sort = document.getElementById('sortMixes').value;
  let filtered = mixes.filter(m => m.title.toLowerCase().includes(search) || (m.keyphrases||'').toLowerCase().includes(search));
  if (sort === 'az') filtered.sort((a,b) => a.title.localeCompare(b.title));
  else if (sort === 'za') filtered.sort((a,b) => b.title.localeCompare(a.title));
  else if (sort === 'recent') filtered.sort((a,b) => b.createdAt - a.createdAt);

  const container = document.getElementById('mixesContainer');
  if (!filtered.length) {
    container.innerHTML = '<div style="padding:1rem;">No mixes found</div>';
    return;
  }

  container.innerHTML = filtered.map(mix => {
    const count = mixSongs.filter(ms => ms.mixId === mix.id).length;
    const chips = (mix.keyphrases || '').split(',').map(k => k.trim()).filter(k => k).map(k => `<span class="chip keyphrase">🔑 ${escapeHtml(k)}</span>`).join('');
    const descPreview = mix.description ? (mix.description.substring(0,40) + (mix.description.length > 40 ? '…' : '')) : '';
    return `
      <div class="mix-card collapsed" data-mix-id="${mix.id}">
        <div class="card-header">
          <span class="card-icon">🎚️</span>
          <span class="card-title">${escapeHtml(mix.title)} <span class="mix-badge">${count}</span></span>
          <button class="expand-toggle" aria-label="Expand">▶</button>
        </div>
        <div class="card-details">
          ${descPreview ? `<div class="mix-description" style="margin-bottom:6px;">${escapeHtml(descPreview)}</div>` : ''}
          <div class="keyphrase-chips">${chips}</div>
          <div class="card-actions">
            <button class="icon-btn view-mix-songs-btn" data-id="${mix.id}" title="View songs">👁️</button>
            <button class="icon-btn manage-mix-songs-btn" data-id="${mix.id}" title="Manage songs">✏️🎵</button>
            <button class="icon-btn edit-mix-btn" data-id="${mix.id}" title="Edit">✏️</button>
            <button class="icon-btn clone-mix-btn" data-id="${mix.id}" title="Clone">📋</button>
            <button class="icon-btn delete-mix-btn" data-id="${mix.id}" title="Delete">🗑️</button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  attachMixEvents();
  attachCollapsibleEvents('mix-card');
}

  function attachMixEvents() {
    document.querySelectorAll('.mix-left').forEach(l => l.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = l.closest('.mix-item').dataset.mixId;
      openViewMixSongsModal(mixes.find(m => m.id === id));
    }));
    document.querySelectorAll('.view-mix-songs-btn').forEach(b => b.addEventListener('click', (e) => {
      e.stopPropagation();
      openViewMixSongsModal(mixes.find(m => m.id === b.dataset.id));
    }));
    document.querySelectorAll('.manage-mix-songs-btn').forEach(b => b.addEventListener('click', (e) => {
      e.stopPropagation();
      openManageMixSongsModal(b.dataset.id);
    }));
    document.querySelectorAll('.edit-mix-btn').forEach(b => b.addEventListener('click', (e) => {
      e.stopPropagation();
      openEditMixModal(b.dataset.id);
    }));
    document.querySelectorAll('.clone-mix-btn').forEach(b => b.addEventListener('click', (e) => {
      e.stopPropagation();
      openCloneMixModal(b.dataset.id);
    }));
    document.querySelectorAll('.delete-mix-btn').forEach(b => b.addEventListener('click', async (e) => {
      e.stopPropagation();
      if (await confirmAction('Delete mix?')) deleteMixById(b.dataset.id);
    }));
  }

  function attachCollapsibleEvents(cardClass) {
  document.querySelectorAll(`.${cardClass}`).forEach(card => {
    const header = card.querySelector('.card-header');
    const toggle = card.querySelector('.expand-toggle');
    
    const toggleExpand = (e) => {
      e.stopPropagation();
      card.classList.toggle('expanded');
      card.classList.toggle('collapsed');
      toggle.textContent = card.classList.contains('expanded') ? '▼' : '▶';
    };
    
    // Click on header toggles (but not if clicking action buttons inside details)
    header.addEventListener('click', (e) => {
      // Don't toggle if clicking the toggle button itself (handled separately)
      if (e.target.closest('.expand-toggle')) return;
      toggleExpand(e);
    });
    
    // Toggle button click
    toggle.addEventListener('click', toggleExpand);
  });
}

  function deleteMixById(id) {
    mixes = mixes.filter(m => m.id !== id);
    mixSongs = mixSongs.filter(ms => ms.mixId !== id);
    saveToLocalStorage();
    renderMixes();
  }

  function openViewMixSongsModal(mix) {
    document.getElementById('viewMixSongsTitle').innerHTML = `Songs in “${escapeHtml(mix.title)}”`;
    const searchInput = document.getElementById('viewMixSongsSearch');
    const container = document.getElementById('viewMixSongsList');
    const countSpan = document.getElementById('viewMixSongsCountChip');
    function render() {
      const search = searchInput.value.toLowerCase();
      const songIds = mixSongs.filter(ms => ms.mixId === mix.id).map(ms => ms.songId);
      const songsInMix = songs.filter(s => songIds.includes(s.id) && s.title.toLowerCase().includes(search));
      container.innerHTML = songsInMix.map(s => `<div class="simple-song-item">🎵 ${escapeHtml(s.title)}</div>`).join('');
      countSpan.innerText = `${songsInMix.length} song${songsInMix.length !== 1 ? 's' : ''}`;
    }
    render();
    searchInput.oninput = render;
    document.getElementById('viewMixSongsModal').style.display = 'flex';
  }

  // ---------- MIX CRUD ----------
  function openAddMixModal() {
    editingMixId = null;
    document.getElementById('mixModalTitle').innerText = 'Add Mix';
    document.getElementById('mixTitleInput').value = '';
    document.getElementById('mixDescInput').value = '';
    document.getElementById('mixKeyphrasesInput').value = '';
    document.getElementById('mixModal').style.display = 'flex';
  }
  function openEditMixModal(id) {
    const mix = mixes.find(m => m.id === id);
    if (!mix) return;
    editingMixId = id;
    document.getElementById('mixModalTitle').innerText = 'Edit Mix';
    document.getElementById('mixTitleInput').value = mix.title;
    document.getElementById('mixDescInput').value = mix.description || '';
    document.getElementById('mixKeyphrasesInput').value = mix.keyphrases || '';
    document.getElementById('mixModal').style.display = 'flex';
  }
  function openCloneMixModal(id) {
    const original = mixes.find(m => m.id === id);
    if (!original) return;
    document.getElementById('cloneMixTitleInput').value = original.title + ' (copy)';
    document.getElementById('cloneMixDescInput').value = original.description || '';
    document.getElementById('cloneMixKeyphrasesInput').value = original.keyphrases || '';
    document.getElementById('cloneMixCopySongsCheck').checked = true;
    document.getElementById('confirmCloneMixBtn').onclick = () => {
      const title = document.getElementById('cloneMixTitleInput').value.trim();
      if (!title) { showSummary('Title required'); return; }
      const newMix = {
        id: genId(),
        title,
        description: document.getElementById('cloneMixDescInput').value,
        keyphrases: document.getElementById('cloneMixKeyphrasesInput').value,
        createdAt: Date.now()
      };
      mixes.push(newMix);
      if (document.getElementById('cloneMixCopySongsCheck').checked) {
        const songIds = mixSongs.filter(ms => ms.mixId === id).map(ms => ms.songId);
        songIds.forEach(songId => mixSongs.push({ mixId: newMix.id, songId }));
      }
      saveToLocalStorage();
      renderMixes();
      closeModal('cloneMixModal');
      showSummary(`Mix "${escapeHtml(title)}" cloned.`);
    };
    document.getElementById('cloneMixModal').style.display = 'flex';
  }
  function openManageMixSongsModal(mixId) {
    const mix = mixes.find(m => m.id === mixId);
    if (!mix) return;
    document.getElementById('manageMixSongsTitle').innerHTML = `Manage songs in “${escapeHtml(mix.title)}”`;
    const container = document.getElementById('manageMixSongsList');
    const searchInput = document.getElementById('manageMixSongsSearch');
    const countSpan = document.getElementById('manageMixSongsCount');
    function render() {
      const search = searchInput.value.toLowerCase();
      const currentIds = new Set(mixSongs.filter(ms => ms.mixId === mixId).map(ms => ms.songId));
      const filteredSongs = songs.filter(s => s.title.toLowerCase().includes(search));
      container.innerHTML = filteredSongs.map(song => {
        const checked = currentIds.has(song.id) ? 'checked' : '';
        return `<div class="check-item"><input type="checkbox" value="${song.id}" id="msong_${song.id}" ${checked}><label for="msong_${song.id}">🎵 ${escapeHtml(song.title)}</label></div>`;
      }).join('');
      countSpan.innerText = `${filteredSongs.length} song${filteredSongs.length !== 1 ? 's' : ''}`;
      updateSelected();
    }
    function updateSelected() {
      const selected = document.querySelectorAll('#manageMixSongsList input:checked').length;
      document.getElementById('manageMixSongsSelectedCount').innerText = `${selected} song(s) selected`;
    }
    render();
    container.addEventListener('change', updateSelected);
    searchInput.oninput = render;
    document.getElementById('saveMixSongsBtn').onclick = () => {
      const selectedIds = Array.from(document.querySelectorAll('#manageMixSongsList input:checked')).map(cb => cb.value);
      mixSongs = mixSongs.filter(ms => ms.mixId !== mixId);
      selectedIds.forEach(songId => mixSongs.push({ mixId, songId }));
      saveToLocalStorage();
      renderMixes();
      closeModal('manageMixSongsModal');
      showSummary(`Mix updated with ${selectedIds.length} songs.`);
    };
    document.getElementById('manageMixSongsModal').style.display = 'flex';
  }

  // ---------- SONG CRUD ----------
  function openAddSongModal() {
    editingSongId = null;
    document.getElementById('songTitleInput').value = '';
    document.getElementById('songLyricsInput').value = '';
    document.getElementById('songModalTitle').innerText = 'Add Song';
    document.getElementById('songModal').style.display = 'flex';
  }
  function openEditSongModal(id) {
    editingSongId = id;
    const s = songs.find(s => s.id === id);
    if (s) {
      document.getElementById('songTitleInput').value = s.title;
      document.getElementById('songLyricsInput').value = s.lyrics || '';
    }
    document.getElementById('songModalTitle').innerText = 'Edit Song';
    document.getElementById('songModal').style.display = 'flex';
  }

  // ---------- ASSIGN TAGS ----------
  function openAssignTagsModal(songId) {
    currentAssignSongId = songId;
    const container = document.getElementById('assignTagsList');
    const searchInput = document.getElementById('assignTagSearch');
    const countSpan = document.getElementById('assignTagsCount');
    function render() {
      const filter = searchInput.value.toLowerCase();
      const filtered = tags.filter(t => t.name.toLowerCase().includes(filter));
      const current = songTags.filter(st => st.songId === currentAssignSongId).map(st => st.tagId);
      container.innerHTML = filtered.map(tag => 
        `<div class="check-item"><input type="checkbox" value="${tag.id}" id="atag_${tag.id}" ${current.includes(tag.id) ? 'checked' : ''}><label for="atag_${tag.id}">🏷️ ${escapeHtml(tag.name)}</label></div>`
      ).join('');
      countSpan.innerText = `${filtered.length} tag${filtered.length !== 1 ? 's' : ''}`;
    }
    render();
    searchInput.oninput = render;
    document.getElementById('confirmAssignTagsBtn').onclick = () => {
      const checks = document.querySelectorAll('#assignTagsList input:checked');
      const newIds = Array.from(checks).map(cb => cb.value);
      songTags = songTags.filter(st => st.songId !== currentAssignSongId);
      newIds.forEach(tid => songTags.push({ songId: currentAssignSongId, tagId: tid }));
      saveToLocalStorage();
      renderSongs(); renderTags();
      closeAllModals();
    };
    document.getElementById('assignTagsModal').style.display = 'flex';
  }

  function openAddSongsToTagModal(tagId) {
    const tag = tags.find(t => t.id === tagId);
    if (!tag) return;
    const container = document.getElementById('addSongsToTagList');
    const searchInput = document.getElementById('addSongToTagSearch');
    document.getElementById('addSongsToTagTitle').innerHTML = `Add songs to “${escapeHtml(tag.name)}” <div class="modal-header-right"><span id="addSongsToTagCountChip" class="modal-count-chip">0</span><span class="close-modal" style="cursor:pointer">✖</span></div>`;
    function render() {
      const search = searchInput.value.toLowerCase();
      const already = new Set(songTags.filter(st => st.tagId === tagId).map(st => st.songId));
      const available = songs.filter(s => !already.has(s.id) && s.title.toLowerCase().includes(search));
      container.innerHTML = available.map(song => 
        `<div class="check-item"><input type="checkbox" value="${song.id}" id="asong_${song.id}"><label for="asong_${song.id}">🎵 ${escapeHtml(song.title)}</label></div>`
      ).join('');
      document.getElementById('addSongsToTagCountChip').innerText = available.length;
      updateSelected();
    }
    function updateSelected() {
      const sel = document.querySelectorAll('#addSongsToTagList input:checked').length;
      document.getElementById('addSongsToTagSelectedCount').innerText = `${sel} song(s) selected`;
    }
    render();
    container.addEventListener('change', updateSelected);
    searchInput.oninput = render;
    document.getElementById('confirmAddSongsToTagBtn').onclick = () => {
      const selected = Array.from(document.querySelectorAll('#addSongsToTagList input:checked')).map(cb => cb.value);
      let added = 0;
      selected.forEach(songId => {
        if (!songTags.some(st => st.songId === songId && st.tagId === tagId)) {
          songTags.push({ songId, tagId });
          added++;
        }
      });
      if (added) { saveToLocalStorage(); renderSongs(); renderTags(); }
      closeModal('addSongsToTagModal');
      showSummary(`Added ${added} song(s) to tag.`);
    };
    document.getElementById('addSongsToTagModal').style.display = 'flex';
  }

  // ---------- EDIT TAG ----------
  function openEditTagModal(id) {
    editingTagId = id;
    const tag = tags.find(t => t.id === id);
    if (tag) {
      document.getElementById('editTagNameInput').value = tag.name;
      document.getElementById('editTagDescInput').value = tag.description || '';
      document.getElementById('editTagModalTitle').innerText = `Edit Tag – ${escapeHtml(tag.name)}`;
    }
    tagSongsToRemove.clear();
    document.getElementById('editTagDetailsPanel').style.display = 'block';
    document.getElementById('editTagSongsPanel').style.display = 'none';
    document.getElementById('editTagDetailsTab').classList.add('active');
    document.getElementById('editTagSongsTab').classList.remove('active');
    document.getElementById('editTagModal').style.display = 'flex';
    renderEditTagSongsList();
  }
  function renderEditTagSongsList() {
    const search = document.getElementById('editTagSongsSearch').value.toLowerCase();
    const songIdsInTag = songTags.filter(st => st.tagId === editingTagId).map(st => st.songId);
    const songsInTag = songs.filter(s => songIdsInTag.includes(s.id) && s.title.toLowerCase().includes(search));
    const container = document.getElementById('editTagSongsList');
    container.innerHTML = songsInTag.map(song => 
      `<div class="check-item"><input type="checkbox" value="${song.id}" id="etsong_${song.id}" ${tagSongsToRemove.has(song.id) ? '' : 'checked'}><label for="etsong_${song.id}">🎵 ${escapeHtml(song.title)}</label></div>`
    ).join('');
    const remaining = songsInTag.filter(s => !tagSongsToRemove.has(s.id)).length;
    document.getElementById('editTagSongsSelectedCount').innerText = `${remaining} songs will remain (${tagSongsToRemove.size} to remove)`;
    container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', () => {
        if (!cb.checked) tagSongsToRemove.add(cb.value);
        else tagSongsToRemove.delete(cb.value);
        const newRemaining = songsInTag.filter(s => !tagSongsToRemove.has(s.id)).length;
        document.getElementById('editTagSongsSelectedCount').innerText = `${newRemaining} songs will remain (${tagSongsToRemove.size} to remove)`;
      });
    });
  }

  // ---------- STATS ----------
  function showStats() {
    const totalSongs = songs.length;
    const taggedSongs = new Set(songTags.map(st => st.songId)).size;
    const untagged = totalSongs - taggedSongs;
    document.getElementById('statsContent').innerHTML = `
      📊 <strong>Total Songs:</strong> ${totalSongs}<br>
      🏷️ <strong>Tagged Songs:</strong> ${taggedSongs}<br>
      🚫 <strong>Untagged Songs:</strong> ${untagged}<br>
      🏷️ <strong>Total Tags:</strong> ${tags.length}<br>
      🎚️ <strong>Total Mixes:</strong> ${mixes.length}
    `;
    document.getElementById('statsModal').style.display = 'flex';
  }

  // ---------- BIND EVENTS & INIT ----------
  function bindEvents() {
    document.getElementById('menuToggle').onclick = openDrawer;
    document.getElementById('drawerClose').onclick = closeDrawer;
    drawerOverlay.onclick = closeDrawer;
    document.querySelectorAll('.drawer-item[data-panel]').forEach(item => item.addEventListener('click', () => switchPanel(item.dataset.panel)));

    document.getElementById('closeBottomDrawer').onclick = closeBottomDrawer;
    document.getElementById('cancelBottomDrawer').onclick = closeBottomDrawer;
    bottomOverlay.onclick = closeBottomDrawer;
    document.getElementById('addSingleSongOption').onclick = () => { closeBottomDrawer(); openAddSongModal(); };
    document.getElementById('bulkTitlesOption').onclick = () => { closeBottomDrawer(); document.getElementById('bulkModal').style.display = 'flex'; };
    document.getElementById('bulkLyricsOption').onclick = () => { closeBottomDrawer(); document.getElementById('bulkLyricsModal').style.display = 'flex'; };

    document.getElementById('exportBtnDrawer').onclick = exportData;
    document.getElementById('exportBtnSettings').onclick = exportData;
    document.getElementById('importBtnDrawer').onclick = () => document.getElementById('importFileInput').click();
    document.getElementById('importBtnSettings').onclick = () => document.getElementById('importFileInput').click();
    document.getElementById('resetAllDataBtn').onclick = async () => {
      if (await confirmAction('Reset all data?')) {
        localStorage.removeItem(STORAGE_KEY);
        location.reload();
      }
    };

    document.getElementById('songSearch').addEventListener('input', renderSongs);
    document.getElementById('sortSongs').addEventListener('change', renderSongs);
    document.getElementById('tagSearch').addEventListener('input', renderTags);
    document.getElementById('sortTags').addEventListener('change', renderTags);
    document.getElementById('mixSearch').addEventListener('input', renderMixes);
    document.getElementById('sortMixes').addEventListener('change', renderMixes);

    document.getElementById('confirmBulkBtn').onclick = () => {
      const text = document.getElementById('bulkSongsTextarea').value;
      if (text.trim()) {
        const items = parseTitlesBulk(text).map(t => ({ title: t }));
        runBulkWithProgress(items, addSingleTitle, []);
      }
      closeModal('bulkModal');
      document.getElementById('bulkSongsTextarea').value = '';
    };
    document.getElementById('confirmBulkLyricsBtn').onclick = () => {
      const text = document.getElementById('bulkLyricsTextarea').value;
      if (text.trim()) {
        const items = parseLyricsBulk(text);
        runBulkWithProgress(items, addSingleLyricsItem, []);
      }
      closeModal('bulkLyricsModal');
      document.getElementById('bulkLyricsTextarea').value = '';
    };
    document.getElementById('bulkAddToTagBtn').onclick = () => {
      const text = document.getElementById('bulkSongsTextarea').value;
      if (text.trim()) openBulkTagSelectModal('titles', text);
      else showSummary('No songs to import');
    };
    document.getElementById('bulkLyricsAddToTagBtn').onclick = () => {
      const text = document.getElementById('bulkLyricsTextarea').value;
      if (text.trim()) openBulkTagSelectModal('lyrics', text);
      else showSummary('No songs to import');
    };

    document.getElementById('saveSongBtn').onclick = async () => {
      let title = document.getElementById('songTitleInput').value.trim();
      const lyrics = document.getElementById('songLyricsInput').value;
      if (!title && lyrics) title = autoTitleFromLyrics(lyrics);
      if (!title) { showSummary('Title required'); return; }
      if (!editingSongId) {
        const ok = await confirmAddWithDuplicateCheck(title);
        if (!ok) { closeAllModals(); return; }
        songs.push({ id: genId(), title, lyrics });
      } else {
        const s = songs.find(s => s.id === editingSongId);
        if (s) { s.title = title; s.lyrics = lyrics; }
      }
      saveToLocalStorage();
      renderSongs(); renderTags(); renderMixes();
      closeAllModals();
    };

    document.getElementById('confirmAddTagBtn').onclick = () => {
      const name = document.getElementById('addTagNameInput').value.trim();
      if (!name) { showSummary('Tag name required'); return; }
      tags.push({ id: genId(), name, description: document.getElementById('addTagDescInput').value });
      saveToLocalStorage();
      renderSongs(); renderTags();
      closeModal('addTagModal');
    };

    document.getElementById('saveMixBtn').onclick = () => {
      const title = document.getElementById('mixTitleInput').value.trim();
      if (!title) { showSummary('Title required'); return; }
      const data = {
        title,
        description: document.getElementById('mixDescInput').value,
        keyphrases: document.getElementById('mixKeyphrasesInput').value,
        createdAt: editingMixId ? mixes.find(m => m.id === editingMixId)?.createdAt : Date.now()
      };
      if (editingMixId) {
        const mix = mixes.find(m => m.id === editingMixId);
        if (mix) Object.assign(mix, data);
      } else {
        mixes.push({ id: genId(), ...data });
      }
      saveToLocalStorage();
      renderMixes();
      closeModal('mixModal');
    };

    document.getElementById('saveEditTagBtn').onclick = () => {
      const name = document.getElementById('editTagNameInput').value.trim();
      if (!name) { showSummary('Tag name required'); return; }
      const tag = tags.find(t => t.id === editingTagId);
      if (tag) {
        tag.name = name;
        tag.description = document.getElementById('editTagDescInput').value;
      }
      for (const songId of tagSongsToRemove) {
        songTags = songTags.filter(st => !(st.songId === songId && st.tagId === editingTagId));
      }
      saveToLocalStorage();
      renderSongs(); renderTags();
      closeModal('editTagModal');
    };

    document.getElementById('editTagDetailsTab').onclick = () => {
      document.getElementById('editTagDetailsPanel').style.display = 'block';
      document.getElementById('editTagSongsPanel').style.display = 'none';
      document.getElementById('editTagDetailsTab').classList.add('active');
      document.getElementById('editTagSongsTab').classList.remove('active');
    };
    document.getElementById('editTagSongsTab').onclick = () => {
      document.getElementById('editTagDetailsPanel').style.display = 'none';
      document.getElementById('editTagSongsPanel').style.display = 'block';
      document.getElementById('editTagDetailsTab').classList.remove('active');
      document.getElementById('editTagSongsTab').classList.add('active');
      renderEditTagSongsList();
    };
    document.getElementById('editTagSongsSearch').addEventListener('input', renderEditTagSongsList);

    document.getElementById('dupAddBtn').onclick = () => {
      if (currentResolveDuplicate) { currentResolveDuplicate(true); closeModal('duplicateModal'); currentResolveDuplicate = null; }
    };
    document.getElementById('dupSkipBtn').onclick = () => {
      if (currentResolveDuplicate) { currentResolveDuplicate(false); closeModal('duplicateModal'); currentResolveDuplicate = null; }
    };
    document.getElementById('progressCloseBtn').onclick = () => closeModal('progressModal');

    document.getElementById('confirmYesBtn').onclick = () => {
      if (confirmResolve) { confirmResolve(true); closeModal('confirmationModal'); confirmResolve = null; }
    };
    document.getElementById('confirmNoBtn').onclick = () => {
      if (confirmResolve) { confirmResolve(false); closeModal('confirmationModal'); confirmResolve = null; }
    };

    document.querySelectorAll('.close-modal').forEach(el => el.addEventListener('click', closeAllModals));
    window.onclick = e => { if (e.target.classList.contains('modal')) closeAllModals(); };

    document.getElementById('importFileInput').onchange = e => { if (e.target.files[0]) importData(e.target.files[0]); };
    document.getElementById('statsBtn')?.addEventListener('click', showStats); // optional if you add a stats button
  }

  function init() {
    loadFromLocalStorage();
    bindEvents();
    renderSongs();
    renderTags();
    renderMixes();
    updateFABs('songs');
    updateFilterBadge();
  }

  init();
})();