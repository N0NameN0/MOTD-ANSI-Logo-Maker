<?php /* index.php - ANSI / TheDraw .TDF web renderer (canvas compositing). */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MOTD ANSI Logo Maker</title>
<meta name="description" content="Generate colourful ANSI logos from classic TheDraw .TDF fonts - perfect for your SSH /etc/motd banner. Browse 1197 fonts, preview live, export PNG/ANSI or copy a one-line curl command.">
<link rel="canonical" href="https://malm.santo.fr/">
<meta name="theme-color" content="#11131a">
<!-- favicon -->
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" href="/favicon.png">
<!-- Open Graph (Facebook, Messenger, LinkedIn, Google...) -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="MOTD ANSI Logo Maker">
<meta property="og:title" content="MOTD ANSI Logo Maker">
<meta property="og:description" content="Generate colourful ANSI logos from classic TheDraw .TDF fonts - perfect for your SSH /etc/motd banner.">
<meta property="og:url" content="https://malm.santo.fr/">
<meta property="og:image" content="https://malm.santo.fr/og-image.png">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="MOTD ANSI Logo Maker - ANSI logo of FAIRLIGHT">
<!-- Twitter / X -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="MOTD ANSI Logo Maker">
<meta name="twitter:description" content="Generate colourful ANSI logos from TheDraw .TDF fonts for your SSH /etc/motd banner.">
<meta name="twitter:image" content="https://malm.santo.fr/og-image.png">
<style>
  :root { --bg:#11131a; --panel:#1b1e27; --line:#2c3140; --fg:#cdd3e0; --accent:#5ac8fa; }
  * { box-sizing: border-box; }
  html,body { margin:0; height:100%; background:var(--bg); color:var(--fg);
              font:13px/1.4 ui-monospace,Menlo,Consolas,monospace; }
  .app { display:grid; grid-template-columns: 300px 1fr; grid-template-rows:auto auto 1fr; height:100%; }
  #logobar { grid-column:1/3; display:flex; justify-content:center; align-items:center;
             background:var(--panel); border-bottom:1px solid var(--line); padding:8px 12px; }
  #logobar img { max-height:88px; max-width:100%; height:auto; display:block; }
  header { grid-column:1/3; display:flex; flex-wrap:wrap; gap:10px; align-items:center;
           justify-content:center; padding:8px 12px; background:var(--panel);
           border-bottom:1px solid var(--line); }
  header label { display:flex; align-items:center; gap:5px; white-space:nowrap; }
  input,select,button { background:#0e0f15; color:var(--fg); border:1px solid var(--line);
                        border-radius:5px; padding:4px 6px; font:inherit; }
  input[type=range]{ padding:0; }
  button { cursor:pointer; }
  button:hover { border-color:var(--accent); }
  select:disabled,input:disabled { cursor:not-allowed; opacity:.5; }
  #toast { position:fixed; top:0; left:0; background:#000e; color:#fff;
           border:1px solid var(--accent); border-radius:7px; padding:5px 10px;
           font-size:12px; opacity:0; pointer-events:none; z-index:50;
           transform:translateY(-4px); transition:opacity .3s ease, transform .3s ease;
           box-shadow:0 4px 16px #0009; white-space:nowrap; }
  #toast.show { opacity:1; transform:translateY(0); }
  #infobtn { width:30px; height:30px; border-radius:50%; padding:0;
             font-size:16px; line-height:1; }
  .overlay { position:fixed; inset:0; background:#000a; backdrop-filter:blur(2px);
             display:flex; align-items:center; justify-content:center; z-index:100; }
  .overlay[hidden] { display:none; }
  .dialog { position:relative; background:var(--panel); border:1px solid var(--line);
            border-radius:12px; padding:26px 30px; max-width:440px; text-align:center;
            box-shadow:0 20px 60px #000c; }
  .dialog h2 { color:var(--accent); margin:.1em 0 .5em; }
  .dialog p { margin:.5em 0; }
  .dialog .credit { font-size:15px; }
  .dialog code { background:#0e0f15; padding:1px 5px; border-radius:4px; }
  .dialog a { color:var(--accent); text-decoration:none; font-weight:bold; }
  .dialog a:hover { text-decoration:underline; }
  .dialog .close { position:absolute; top:8px; right:10px; background:none; border:none;
                   color:var(--fg); font-size:22px; line-height:1; cursor:pointer; }
  .dialog .close:hover { color:var(--accent); }
  #word { width:220px; }
  aside { background:var(--panel); border-right:1px solid var(--line); padding:10px;
          display:flex; flex-direction:column; gap:8px; min-height:0; }
  aside .row { display:flex; gap:6px; }
  aside .row > * { flex:1; min-width:0; }
  #list { flex:1; min-height:120px; }
  #vars { height:150px; }
  select[multiple],select[size] { overflow:auto; }
  main { overflow:auto; padding:18px; background:
         repeating-conic-gradient(#0a0b10 0% 25%, #0d0e14 0% 50%) 50%/24px 24px; }
  #stage { display:inline-block; background:#000; padding:10px; border:1px solid var(--line);
           box-shadow:0 6px 30px rgba(0,0,0,.5); }
  #canvas { image-rendering:pixelated; display:block; }
  .meta { color:#8b93a7; font-size:12px; }
</style>
</head>
<body>
<div class="app">
  <div id="logobar"><img src="logo.png" alt="MOTD ANSI Logo Maker"></div>
  <header>
    <label>Text <input id="word" value="HELLO" autocomplete="off"></label>
    <label>Space <input id="space" type="number" min="0" max="40" value="6" style="width:54px"></label>
    <label>Gap <input id="gap" type="number" min="0" max="40" value="1" style="width:54px"></label>
    <label>Zoom <input id="zoom" type="range" min="1" max="8" step="1" value="3"><span id="zoomv">3&times;</span></label>
    <label class="nc">fg <select id="deffg"></select></label>
    <button id="export">PNG</button>
    <button id="ansi">ANSI</button>
    <button id="curlbtn">cURL</button>
    <button id="wgetbtn">wget</button>
    <span id="status" class="meta"></span>
    <button id="infobtn" title="About" aria-label="About">&#9432;</button>
  </header>

  <aside>
    <input id="search" placeholder="filter fonts&hellip;" autocomplete="off">
    <div class="row">
      <select id="sort">
        <option value="name">sort: name</option>
        <option value="height">sort: height</option>
        <option value="variations">sort: variations</option>
        <option value="type">sort: type</option>
      </select>
      <button id="sortdir" title="reverse">&uarr;</button>
    </div>
    <select id="list" size="20"></select>
    <div class="meta">Variation (&larr; &rarr; or click):</div>
    <select id="vars" size="6"></select>
    <div id="finfo" class="meta"></div>
  </aside>

  <main>
    <div id="stage"><canvas id="canvas" width="16" height="16"></canvas></div>
  </main>
</div>
<div id="toast"></div>

<div id="modal" class="overlay" hidden>
  <div class="dialog" role="dialog" aria-modal="true">
    <button class="close" id="modalClose" aria-label="Close">&times;</button>
    <h2>MOTD ANSI Logo Maker</h2>
    <p>Generate ANSI logos from TheDraw <code>.TDF</code> fonts<br>for your SSH <code>/etc/motd</code>.</p>
    <p class="credit">Coded by <strong>Antoine Santo</strong> <span class="meta">aka</span> <strong>N0NameN0</strong></p>
    <p><a id="ghlink" href="https://github.com/N0NameN0/MOTD-ANSI-Logo-Maker" target="_blank" rel="noopener">github.com/N0NameN0/MOTD-ANSI-Logo-Maker &rarr;</a></p>
  </div>
</div>

<script>
const CW = 8, CH = 16;
const DOS_RGB = [
  '#000000','#0000aa','#00aa00','#00aaaa','#aa0000','#aa00aa','#aa5500','#aaaaaa',
  '#555555','#5555ff','#55ff55','#55ffff','#ff5555','#ff55ff','#ffff55','#ffffff'];
const DOS_NAMES = ['black','blue','green','cyan','red','magenta','brown','lt.gray',
  'dk.gray','lt.blue','lt.green','lt.cyan','lt.red','lt.magenta','yellow','white'];

const $ = s => document.querySelector(s);
const canvas = $('#canvas'), ctx = canvas.getContext('2d');

let bgImg, ftAtlas;
let fileList = [];
let metrics = null;
let sortDesc = false;
let fontData = null;
let varIdx = 0;
const fontCache = new Map();
let wantFile = null, wantVar = 0;   // pending selection to restore from localStorage

// ---- persistence (localStorage) -----------------------------------------
const LS_KEY = 'ansitdf.state';
function saveState(){
  try {
    localStorage.setItem(LS_KEY, JSON.stringify({
      word:$('#word').value, space:$('#space').value, gap:$('#gap').value,
      zoom:$('#zoom').value, fg:$('#deffg').value, sort:$('#sort').value,
      desc:sortDesc, filter:$('#search').value,
      file: fontData ? fontData.file : null, var: varIdx
    }));
  } catch(e){}
}
function loadState(){
  try { return JSON.parse(localStorage.getItem(LS_KEY)); } catch(e){ return null; }
}
function applyState(s){
  if (!s) return;
  if (s.word  != null) $('#word').value  = s.word;
  if (s.space != null) $('#space').value = s.space;
  if (s.gap   != null) $('#gap').value   = s.gap;
  if (s.zoom  != null) $('#zoom').value  = s.zoom;
  if (s.fg    != null) $('#deffg').value = s.fg;
  if (s.sort  != null) $('#sort').value  = s.sort;
  if (s.filter!= null) $('#search').value= s.filter;
  if (s.desc){ sortDesc = true; $('#sortdir').textContent = '\u2193'; }
}

function setStatus(t){ $('#status').textContent = t || ''; }

function loadImage(src){
  return new Promise((res,rej)=>{ const im=new Image();
    im.onload=()=>res(im); im.onerror=()=>rej(new Error('load '+src)); im.src=src; });
}
function buildAtlas(img){
  const c=document.createElement('canvas'); c.width=img.width; c.height=img.height;
  const cx=c.getContext('2d'); cx.drawImage(img,0,0);
  const id=cx.getImageData(0,0,c.width,c.height), d=id.data;
  for(let i=0;i<d.length;i+=4){ if(d[i]===0&&d[i+1]===0&&d[i+2]===0) d[i+3]=0; }
  cx.putImageData(id,0,0); return c;
}

async function loadList(){
  fileList = await (await fetch('api.php?action=list')).json();
  if ($('#sort').value !== 'name') await ensureMetrics();   // restored metric sort
  renderList();
}
function currentFilter(){ return $('#search').value.trim().toLowerCase(); }
function sortedFiltered(){
  let files = fileList.slice();
  const f = currentFilter();
  if (f) files = files.filter(n => n.toLowerCase().includes(f));
  const mode = $('#sort').value;
  if (mode === 'name'){
    files.sort((a,b)=>a.toLowerCase().localeCompare(b.toLowerCase()));
  } else if (metrics){
    const col = {height:0,variations:2,type:3}[mode];
    files.sort((a,b)=>{
      const x=(metrics[a]||[0,0,0,0])[col], y=(metrics[b]||[0,0,0,0])[col];
      return x-y || a.toLowerCase().localeCompare(b.toLowerCase());
    });
  }
  if (sortDesc) files.reverse();
  return files;
}
const NBSP = '\u00a0';
function renderList(keep){
  const sel = keep ?? $('#list').value;
  const files = sortedFiltered();
  const mode = $('#sort').value;
  const col = {height:0,variations:2,type:3}[mode];
  const tag = {height:'h',variations:'v',type:'t'}[mode];
  const showMetric = mode!=='name' && metrics;
  const COLW = 26;                       // name column width (monospace, NBSP-padded)
  $('#list').innerHTML = '';
  for (const n of files){
    const o = document.createElement('option');
    if (showMetric && metrics[n]){
      const name = n.length > COLW ? n.slice(0, COLW-1)+'\u2026' : n + NBSP.repeat(COLW - n.length);
      o.textContent = name + tag + metrics[n][col];   // aligned 2nd column
    } else {
      o.textContent = n;
    }
    o.value = n;
    $('#list').appendChild(o);
  }
  if (wantFile && files.includes(wantFile)){
    const wf = wantFile, wv = wantVar; wantFile = null;
    $('#list').value = wf; loadFont(wf, wv);
    // defer so the listbox is laid out, then center the selected row
    requestAnimationFrame(scrollListToSelected);
    setTimeout(scrollListToSelected, 60);
  } else if (files.includes(sel)) $('#list').value = sel;
  else if (files.length) { $('#list').value = files[0]; loadFont(files[0]); }
}
function scrollListToSelected(){
  const list = $('#list');
  const i = list.selectedIndex;
  if (i < 0 || !list.options.length) return;
  const itemH = list.scrollHeight / list.options.length;       // ~row height
  list.scrollTop = Math.max(0, i*itemH - list.clientHeight/2 + itemH/2);
}

async function ensureMetrics(){
  if (metrics) return;                       // in-memory for this session
  // The metrics are cached SERVER-SIDE (api.php -> metrics.json); this just
  // fetches that (fast) cache.
  setStatus('loading metrics\u2026');
  metrics = await (await fetch('api.php?action=metrics')).json();
  setStatus('');
}

async function loadFont(name, restoreVar){
  if (!name) return;
  let data = fontCache.get(name);
  if (!data){
    setStatus('loading '+name+'\u2026');
    data = await (await fetch('api.php?action=font&file='+encodeURIComponent(name))).json();
    fontCache.set(name, data); setStatus('');
  }
  fontData = data;
  varIdx = Math.max(0, Math.min(restoreVar||0, data.fonts.length-1));
  renderVars();
  render();
  saveState();
}
function curFont(){ return fontData && fontData.fonts[varIdx]; }
function renderVars(){
  const vs = $('#vars'); vs.innerHTML='';
  if (!fontData) return;
  fontData.fonts.forEach((f,i)=>{
    const o=document.createElement('option');
    o.value=i; o.textContent=(i+1)+'. '+f.name+'  ['+f.typeName+']';
    vs.appendChild(o);
  });
  vs.value=varIdx;
  const f=curFont();
  $('#finfo').textContent = f
    ? fontData.file+' \u00b7 '+fontData.fonts.length+' variation(s) \u00b7 type '+f.typeName+' \u00b7 spacing '+f.spacing+' \u00b7 '+Object.keys(f.glyphs).length+' glyphs'
    : '';
  const isColor = !!(f && f.type === 2);
  $('#deffg').disabled = isColor;                       // can't be opened when color
  document.querySelectorAll('.nc').forEach(e=> e.style.opacity = isColor ? 0.4 : 1 );
}

function buildGrid(text, font, spaceWidth, gap){
  const items = [];
  for (const ch of text){
    if (ch === ' '){ items.push({w:spaceWidth, rows:[]}); continue; }
    const code = ch.charCodeAt(0);
    const g = font.glyphs[code];
    if (!g){ items.push({w:spaceWidth, rows:[]}); continue; }
    items.push({w:g.w, rows:g.rows});
  }
  if (!items.length) return [];
  let lineH = 1;
  for (const it of items) lineH = Math.max(lineH, it.rows.length);
  const grid = [];
  for (let r=0;r<lineH;r++){
    const line = [];
    items.forEach((it,idx)=>{
      const row = r < it.rows.length ? it.rows[r] : [];
      for (let c=0;c<it.w;c++) line.push(c < row.length ? row[c] : null);
      if (gap>0 && idx < items.length-1) for (let k=0;k<gap;k++) line.push(null);
    });
    grid.push(line);
  }
  return grid;
}

function render(){
  const font = curFont();
  if (!font || !ftAtlas) return;
  const text = $('#word').value;
  const spaceWidth = Math.max(0, parseInt($('#space').value)||0);
  const gap = Math.max(0, parseInt($('#gap').value)||0);
  const defFg = parseInt($('#deffg').value);
  const grid = buildGrid(text, font, spaceWidth, gap);
  const rows = grid.length;
  const cols = rows ? Math.max(...grid.map(l=>l.length)) : 0;
  const z = parseInt($('#zoom').value)||1;
  $('#zoomv').textContent = z+'\u00d7';
  // The zoom is baked into the canvas BITMAP (not applied via CSS) and the
  // element is then mapped 1 canvas pixel = 1 physical pixel, so the on-screen
  // result is pixel-perfect whatever the devicePixelRatio / browser zoom is.
  const baseW = Math.max(1, cols*CW), baseH = Math.max(1, rows*CH);
  canvas.width  = baseW*z;
  canvas.height = baseH*z;
  ctx.imageSmoothingEnabled = false;
  ctx.fillStyle = '#000';
  ctx.fillRect(0,0,canvas.width,canvas.height);
  for (let r=0;r<rows;r++){
    const line = grid[r];
    for (let c=0;c<line.length;c++){
      const cell = line[c];
      if (!cell) continue;
      const code = cell[0];
      let fg, bg;
      if (cell[1] === null){ fg = defFg; bg = 0; }
      else { fg = cell[1] & 0x0F; bg = (cell[1] >> 4) & 0x07; }
      const x = c*CW*z, y = r*CH*z;
      ctx.drawImage(bgImg, bg*CW,0,CW,CH, x,y,CW*z,CH*z);
      ctx.drawImage(ftAtlas, code*CW, fg*CH, CW,CH, x,y,CW*z,CH*z);
    }
  }
  applyZoom();
  saveState();
}
// Size the DOM element so one backing-store pixel == one physical pixel.
function applyZoom(){
  const dpr = window.devicePixelRatio || 1;
  canvas.style.width  = (canvas.width  / dpr) + 'px';
  canvas.style.height = (canvas.height / dpr) + 'px';
}

function fillFgSelect(){
  const s=$('#deffg');
  DOS_RGB.forEach((rgb,i)=>{ const o=document.createElement('option');
    o.value=i; o.textContent=i+' '+DOS_NAMES[i]; s.appendChild(o); });
  s.value=7;
}
function bump(delta){
  if (!fontData) return;
  varIdx = Math.max(0, Math.min(varIdx+delta, fontData.fonts.length-1));
  $('#vars').value = varIdx; renderVars(); render();
}

['word','space','gap','deffg'].forEach(id=>{
  $('#'+id).addEventListener('input', render);
});
$('#zoom').addEventListener('input', render);
window.addEventListener('resize', applyZoom);
$('#search').addEventListener('input', ()=>{ renderList(); saveState(); });
$('#sort').addEventListener('change', async ()=>{
  if ($('#sort').value!=='name') await ensureMetrics();
  renderList(); saveState();
});
$('#sortdir').addEventListener('click', ()=>{
  sortDesc=!sortDesc; $('#sortdir').textContent = sortDesc?'\u2193':'\u2191'; renderList(); saveState();
});
$('#list').addEventListener('change', e=> loadFont(e.target.value));
$('#vars').addEventListener('change', e=>{ varIdx=parseInt(e.target.value)||0; renderVars(); render(); });
document.addEventListener('keydown', e=>{
  if (e.key==='Escape' && !$('#modal').hidden){ closeModal(); return; }
  if (!$('#modal').hidden) return;                 // modal open -> ignore nav keys
  if (e.target.tagName==='INPUT' && e.target.id==='word') return;
  if (e.key==='ArrowLeft'){ bump(-1); e.preventDefault(); }
  else if (e.key==='ArrowRight'){ bump(1); e.preventDefault(); }
});
$('#export').addEventListener('click', ()=>{
  // The canvas bitmap already includes the zoom factor, so export it directly.
  const z = parseInt($('#zoom').value) || 1;
  const a = document.createElement('a');
  a.download = baseName()+'_'+z+'x.png';
  a.href = canvas.toDataURL('image/png'); a.click();
});

// ---- ANSI / cURL / wget --------------------------------------------------
function baseName(){
  const f = curFont();
  return ((f&&f.name)||'ansi').replace(/[^\w]+/g,'_')+'_'+$('#word').value.replace(/[^\w]+/g,'_');
}
function ansiURL(){
  const u = new URL('api.php', location.href);
  u.search = new URLSearchParams({
    action:'ansi',
    file: fontData ? fontData.file : '',
    var: varIdx,
    text: $('#word').value,
    space: $('#space').value,
    gap: $('#gap').value,
    fg: $('#deffg').value,
    color: 1
  }).toString();
  return u.href;
}
function shellQuote(s){ return "'" + String(s).replace(/'/g, "'\\''") + "'"; }
let _toastTimer = null;
function toast(msg, anchor){
  const t = $('#toast');
  t.textContent = msg;
  // position just under the element that was clicked (kept on-screen)
  if (anchor){
    const r = anchor.getBoundingClientRect();
    t.style.visibility = 'hidden';
    t.classList.add('show');
    const tw = t.offsetWidth;
    t.style.visibility = '';
    let left = r.left;
    if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
    t.style.left = Math.max(8, Math.round(left)) + 'px';
    t.style.top  = Math.round(r.bottom + 6) + 'px';
  }
  t.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(()=> t.classList.remove('show'), 1500);
}
function copyText(text, ok){
  if (navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(text).then(ok, ()=>fallbackCopy(text, ok));
  } else {
    fallbackCopy(text, ok);
  }
}
function fallbackCopy(text, ok){
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position='fixed'; ta.style.top='-1000px'; ta.style.opacity='0';
  document.body.appendChild(ta); ta.focus(); ta.select();
  let done = false;
  try { done = document.execCommand('copy'); } catch(e){}
  document.body.removeChild(ta);
  if (done) ok();
}
function showCmd(kind, anchor){
  const url = ansiURL();
  const cmd = (kind==='curl')
    ? 'curl -s ' + shellQuote(url) + ' > /etc/motd'
    : 'wget -qO- ' + shellQuote(url) + ' > /etc/motd';
  copyText(cmd, ()=> toast(kind + ' command copied \u2713', anchor));
}
$('#ansi').addEventListener('click', async ()=>{
  try {
    const txt = await (await fetch(ansiURL())).text();
    const blob = new Blob([txt], {type:'text/plain;charset=utf-8'});
    const a = document.createElement('a');
    a.download = baseName()+'.ans';
    a.href = URL.createObjectURL(blob); a.click();
    setTimeout(()=>URL.revokeObjectURL(a.href), 3000);
  } catch(e){ setStatus('ANSI export failed: '+e.message); }
});
$('#curlbtn').addEventListener('click', e=>showCmd('curl', e.currentTarget));
$('#wgetbtn').addEventListener('click', e=>showCmd('wget', e.currentTarget));

// ---- About modal ---------------------------------------------------------
function openModal(){ $('#modal').hidden = false; }
function closeModal(){ $('#modal').hidden = true; }
$('#infobtn').addEventListener('click', openModal);
$('#modalClose').addEventListener('click', closeModal);
$('#modal').addEventListener('click', e=>{ if (e.target.id==='modal') closeModal(); });

(async function(){
  fillFgSelect();
  const st = loadState();
  applyState(st);
  if (st && st.file){ wantFile = st.file; wantVar = st.var || 0; }
  try {
    bgImg = await loadImage('bg.png');
    const ft = await loadImage('ft.png');
    ftAtlas = buildAtlas(ft);
  } catch(err){ setStatus('asset error: '+err.message); return; }
  await loadList();
  applyZoom();
})();
</script>
</body>
</html>
