<?php
/**
* ╔══════════════════════════════════════════════════════════════════════╗
* ║  GENESIS-ULTRA v9.1 — INDEX.PHP                                      ║
* ║  Interface chercheur scientifique autonome • 8 sources • IA Mistral  ║
* ╚══════════════════════════════════════════════════════════════════════╝
*/
if(session_status() === PHP_SESSION_NONE) @session_start();
if(!isset($_SESSION['genesis_session'])) {
    $_SESSION['genesis_session'] = bin2hex(random_bytes(8));
}
$SESSION_ID = $_SESSION['genesis_session'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GENESIS-ULTRA v9.1 — Chercheur Scientifique Autonome</title>
<meta name="description" content="Moteur de découverte scientifique autonome IA — 8 sources • Mistral • Auto-research">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
/* =========================================================================
DESIGN SYSTEM — GENESIS-ULTRA v9.1
Esthétique: Biopunk · Cyberlaboratoire · Brutaliste scientifique
========================================================================= */
:root {
--bg:        #080c10;
--surface:   #0d1319;
--surface2:  #111820;
--surface3:  #161f2a;
--border:    rgba(0, 180, 255, 0.12);
--border2:   rgba(0, 180, 255, 0.25);
--accent:    #00c8ff;
--accent2:   #0affb0;
--accent3:   #ff3d6b;
--accent4:   #ffd700;
--text:      #c8dff0;
--text-dim:  #5a7a95;
--text-bright: #e8f4ff;
--log-h:     calc(100vh - 280px);
--glow:      0 0 20px rgba(0, 200, 255, 0.15);
--glow-green:0 0 20px rgba(10, 255, 176, 0.15);
--glow-red:  0 0 15px rgba(255, 61, 107, 0.2);
--mono:      'Space Mono', 'Courier New', monospace;
--display:   'Syne', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
height: 100%;
background: var(--bg);
color: var(--text);
font-family: var(--mono);
font-size: 12px;
overflow: hidden;
}
/* Grain texture */
body::before {
content: '';
position: fixed; inset: 0; z-index: 0; pointer-events: none;
background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
opacity: 0.6;
}
/* ── SCANLINES ── */
body::after {
content: '';
position: fixed; inset: 0; z-index: 0; pointer-events: none;
background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.03) 2px, rgba(0,0,0,0.03) 4px);
}
/* ── LAYOUT ROOT ── */
#root {
position: relative; z-index: 1;
display: grid;
grid-template-rows: auto auto 1fr auto;
height: 100vh;
overflow: hidden;
}
/* =========================================================================
HEADER
========================================================================= */
#header {
display: grid;
grid-template-columns: 1fr auto 1fr;
align-items: center;
padding: 14px 20px 12px;
border-bottom: 1px solid var(--border2);
background: linear-gradient(180deg, rgba(0,200,255,0.05) 0%, transparent 100%);
position: relative;
overflow: hidden;
}
#header::before {
content: '';
position: absolute; top: 0; left: 0; right: 0; height: 1px;
background: linear-gradient(90deg, transparent, var(--accent), transparent);
animation: scan-h 4s ease-in-out infinite;
}
@keyframes scan-h { 0%,100%{opacity:.3} 50%{opacity:1} }
.header-left {
display: flex; flex-direction: column; gap: 2px;
}
.header-brand {
font-family: var(--display);
font-size: 20px;
font-weight: 800;
letter-spacing: -0.5px;
color: var(--text-bright);
}
.header-brand span { color: var(--accent); }
.header-sub {
font-size: 9px;
color: var(--text-dim);
letter-spacing: 2px;
text-transform: uppercase;
}
.header-center {
display: flex; flex-direction: column; align-items: center; gap: 4px;
}
#status-pill {
display: flex; align-items: center; gap: 6px;
padding: 5px 14px;
border: 1px solid var(--border2);
border-radius: 20px;
font-size: 10px;
letter-spacing: 1px;
transition: all 0.3s;
}
#status-pill.running  { border-color: var(--accent2); color: var(--accent2); box-shadow: var(--glow-green); }
#status-pill.error    { border-color: var(--accent3); color: var(--accent3); }
#status-pill.paused   { border-color: var(--accent4); color: var(--accent4); }
#status-pill.init     { border-color: var(--text-dim); color: var(--text-dim); }
.pulse-dot {
width: 6px; height: 6px; border-radius: 50%; background: currentColor;
animation: pulse-dot 1.5s ease-in-out infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.4;transform:scale(0.6)} }
#timer-display {
font-size: 10px; color: var(--text-dim); letter-spacing: 1px;
}
.header-right {
display: flex; flex-direction: column; align-items: flex-end; gap: 6px;
}
.ctrl-row {
display: flex; gap: 6px;
}
/* =========================================================================
TOPBAR — Stats + Search + Controls
========================================================================= */
#topbar {
display: flex; align-items: center; gap: 10px;
padding: 8px 16px;
background: var(--surface);
border-bottom: 1px solid var(--border);
overflow: hidden;
}
.stat-chip {
display: flex; align-items: center; gap: 5px;
padding: 4px 10px;
background: var(--surface2);
border: 1px solid var(--border);
border-radius: 4px;
font-size: 10px;
white-space: nowrap;
color: var(--text-dim);
transition: all 0.2s;
}
.stat-chip .val { color: var(--text-bright); font-weight: 700; margin-left: 2px; }
.stat-chip .accent-val { color: var(--accent); }
.stat-chip .green-val { color: var(--accent2); }
.topbar-sep { width: 1px; height: 20px; background: var(--border); flex-shrink: 0; }
.search-wrap {
flex: 1; display: flex; gap: 6px; align-items: center;
min-width: 0;
}
#search-input {
flex: 1;
background: var(--surface2);
border: 1px solid var(--border);
border-radius: 4px;
padding: 5px 10px;
font-family: var(--mono);
font-size: 11px;
color: var(--text);
outline: none;
transition: all 0.2s;
min-width: 0;
}
#search-input:focus { border-color: var(--accent); box-shadow: var(--glow); }
#search-input::placeholder { color: var(--text-dim); }
.sort-select {
background: var(--surface2); border: 1px solid var(--border); border-radius: 4px;
padding: 5px 8px; font-family: var(--mono); font-size: 10px; color: var(--text);
outline: none; cursor: pointer;
}
/* =========================================================================
BUTTONS
========================================================================= */
.btn {
display: inline-flex; align-items: center; gap: 5px;
padding: 5px 12px;
border: 1px solid var(--border2);
border-radius: 4px;
background: transparent;
color: var(--text-dim);
font-family: var(--mono);
font-size: 10px;
letter-spacing: 0.5px;
cursor: pointer;
transition: all 0.2s;
white-space: nowrap;
text-decoration: none;
}
.btn:hover { background: var(--surface3); color: var(--text-bright); border-color: var(--accent); }
.btn.primary { border-color: var(--accent); color: var(--accent); }
.btn.primary:hover { background: rgba(0, 200, 255, 0.1); box-shadow: var(--glow); }
.btn.success { border-color: var(--accent2); color: var(--accent2); }
.btn.danger  { border-color: var(--accent3); color: var(--accent3); }
.btn.warning { border-color: var(--accent4); color: var(--accent4); }
.btn.sm { padding: 3px 8px; font-size: 9px; }
.btn.icon-only { padding: 5px 8px; }
.btn-group { display: flex; gap: 4px; }
/* Export dropdown */
.export-wrap { position: relative; }
#export-menu {
display: none;
position: absolute; top: calc(100% + 4px); right: 0;
background: var(--surface2);
border: 1px solid var(--border2);
border-radius: 6px;
padding: 4px;
z-index: 100;
min-width: 140px;
}
#export-menu.open { display: block; }
#export-menu a {
display: flex; align-items: center; gap: 6px;
padding: 6px 10px;
color: var(--text-dim);
text-decoration: none;
font-size: 10px;
border-radius: 4px;
transition: all 0.15s;
}
#export-menu a:hover { background: var(--surface3); color: var(--accent); }
/* =========================================================================
MAIN COLUMNS
========================================================================= */
#main {
display: grid;
grid-template-columns: 1fr 380px;
overflow: hidden;
height: 100%;
}
/* ── LEFT: Results ── */
#results-panel {
display: flex; flex-direction: column;
overflow: hidden;
border-right: 1px solid var(--border);
}
#results-header {
display: flex; align-items: center; justify-content: space-between;
padding: 8px 14px;
border-bottom: 1px solid var(--border);
background: var(--surface);
flex-shrink: 0;
}
.panel-title {
font-family: var(--display);
font-size: 11px;
font-weight: 700;
letter-spacing: 2px;
text-transform: uppercase;
color: var(--text-bright);
}
.panel-title span { color: var(--accent); margin-left: 6px; }
#results-grid {
flex: 1;
overflow-y: auto;
padding: 12px;
display: grid;
grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
gap: 10px;
align-content: start;
}
#results-grid::-webkit-scrollbar { width: 4px; }
#results-grid::-webkit-scrollbar-track { background: var(--surface); }
#results-grid::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }
/* ── HYPOTHESIS CARD ── */
.hypo-card {
background: var(--surface);
border: 1px solid var(--border);
border-radius: 8px;
padding: 14px;
cursor: pointer;
transition: all 0.2s;
position: relative;
overflow: hidden;
animation: card-in 0.4s ease both;
}
@keyframes card-in { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.hypo-card::before {
content: '';
position: absolute; top: 0; left: 0; right: 0; height: 2px;
background: linear-gradient(90deg, var(--accent), var(--accent2));
opacity: 0;
transition: opacity 0.2s;
}
.hypo-card:hover { border-color: var(--border2); box-shadow: var(--glow); transform: translateY(-1px); }
.hypo-card:hover::before { opacity: 1; }
.hypo-card.high-novelty { border-color: rgba(10, 255, 176, 0.3); }
.hypo-card.high-novelty::before { opacity: 1; background: linear-gradient(90deg, var(--accent2), var(--accent4)); }
.card-top {
display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;
margin-bottom: 8px;
}
.card-title {
font-family: var(--display);
font-size: 12px;
font-weight: 600;
color: var(--text-bright);
line-height: 1.4;
flex: 1;
}
.novelty-ring {
width: 36px; height: 36px; flex-shrink: 0;
position: relative;
}
.novelty-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.novelty-ring .track { stroke: var(--surface3); stroke-width: 3; fill: none; }
.novelty-ring .fill { stroke-width: 3; fill: none; stroke-linecap: round; transition: stroke-dashoffset 1s; }
.novelty-val {
position: absolute; inset: 0;
display: flex; align-items: center; justify-content: center;
font-size: 8px; font-weight: 700; color: var(--text-bright);
}
.card-meta {
display: flex; flex-wrap: wrap; gap: 4px;
margin-bottom: 8px;
}
.meta-tag {
display: inline-flex; align-items: center; gap: 3px;
padding: 2px 6px;
background: var(--surface2);
border: 1px solid var(--border);
border-radius: 3px;
font-size: 9px;
color: var(--text-dim);
}
.meta-tag.domain { border-color: rgba(0,200,255,0.2); color: var(--accent); }
.meta-tag.sources { border-color: rgba(10,255,176,0.2); color: var(--accent2); }
.meta-tag.evidence { border-color: rgba(255,215,0,0.2); color: var(--accent4); }
.card-vulgar {
font-size: 10px;
color: var(--text-dim);
line-height: 1.5;
margin-bottom: 8px;
display: -webkit-box;
-webkit-line-clamp: 2;
-webkit-box-orient: vertical;
overflow: hidden;
}
.card-sources {
display: flex; flex-wrap: wrap; gap: 3px;
margin-bottom: 8px;
}
.src-pip {
display: inline-flex; align-items: center;
padding: 1px 5px;
border-radius: 2px;
font-size: 8px;
font-weight: 700;
letter-spacing: 0.5px;
text-decoration: none;
transition: opacity 0.15s;
}
.src-pip:hover { opacity: 0.75; }
.src-pubmed      { background: rgba(0,102,204,0.3);  color: #6eb8ff; border: 1px solid rgba(0,102,204,0.4); }
.src-uniprot     { background: rgba(0,170,85,0.3);   color: #6effa8; border: 1px solid rgba(0,170,85,0.4); }
.src-clinvar     { background: rgba(204,34,0,0.3);   color: #ffaa88; border: 1px solid rgba(204,34,0,0.4); }
.src-arxiv       { background: rgba(255,102,0,0.3);  color: #ffc480; border: 1px solid rgba(255,102,0,0.4); }
.src-europepmc   { background: rgba(0,119,187,0.3);  color: #80d0ff; border: 1px solid rgba(0,119,187,0.4); }
.src-openalex    { background: rgba(139,92,246,0.3); color: #d4a8ff; border: 1px solid rgba(139,92,246,0.4); }
.src-chembl      { background: rgba(217,119,6,0.3);  color: #fcd34d; border: 1px solid rgba(217,119,6,0.4); }
.src-wikidata    { background: rgba(100,100,100,0.3);color: #aaa;    border: 1px solid rgba(100,100,100,0.4); }
.card-footer {
display: flex; align-items: center; justify-content: space-between;
padding-top: 8px;
border-top: 1px solid var(--border);
}
.card-date { font-size: 9px; color: var(--text-dim); }
.card-actions { display: flex; gap: 4px; }
/* Pagination */
#pagination {
display: flex; align-items: center; justify-content: center; gap: 6px;
padding: 10px;
border-top: 1px solid var(--border);
background: var(--surface);
flex-shrink: 0;
}
.page-btn {
width: 28px; height: 28px;
display: flex; align-items: center; justify-content: center;
border: 1px solid var(--border);
border-radius: 4px;
background: var(--surface2);
color: var(--text-dim);
font-family: var(--mono);
font-size: 10px;
cursor: pointer;
transition: all 0.15s;
}
.page-btn:hover { border-color: var(--border2); color: var(--text-bright); }
.page-btn.active { border-color: var(--accent); color: var(--accent); background: rgba(0,200,255,0.08); }
.page-info { font-size: 10px; color: var(--text-dim); }
/* Empty state */
.empty-state {
grid-column: 1 / -1;
display: flex; flex-direction: column; align-items: center; justify-content: center;
padding: 60px 20px;
gap: 12px;
color: var(--text-dim);
}
.empty-state .icon { font-size: 40px; opacity: 0.4; }
.empty-state p { font-size: 11px; text-align: center; }
/* ── RIGHT: Logs ── */
#logs-panel {
display: flex; flex-direction: column;
overflow: hidden;
background: var(--surface);
}
#logs-header {
display: flex; align-items: center; justify-content: space-between;
padding: 8px 14px;
border-bottom: 1px solid var(--border);
flex-shrink: 0;
}
#logs-container {
flex: 1;
overflow-y: auto;
padding: 8px;
}
#logs-container::-webkit-scrollbar { width: 3px; }
#logs-container::-webkit-scrollbar-thumb { background: var(--border2); }
.log-entry {
display: grid;
grid-template-columns: 52px 1fr;
gap: 6px;
padding: 6px 8px;
margin-bottom: 4px;
border-radius: 4px;
border-left: 2px solid transparent;
background: var(--surface2);
animation: log-in 0.3s ease both;
position: relative;
}
@keyframes log-in { from{opacity:0;transform:translateX(8px)} to{opacity:1;transform:translateX(0)} }
.log-entry.success { border-left-color: var(--accent2); }
.log-entry.error   { border-left-color: var(--accent3); background: rgba(255,61,107,0.05); }
.log-entry.warning { border-left-color: var(--accent4); background: rgba(255,215,0,0.05); }
.log-entry.info    { border-left-color: var(--accent); }
.log-entry.waiting { border-left-color: var(--text-dim); }
.log-time { font-size: 8px; color: var(--text-dim); padding-top: 1px; letter-spacing: 0.3px; }
.log-body { flex: 1; min-width: 0; }
.log-msg  { font-size: 10px; color: var(--text); line-height: 1.4; word-break: break-word; }
.log-detail {
font-size: 9px;
color: var(--text-dim);
margin-top: 3px;
padding: 4px 6px;
background: var(--surface3);
border-radius: 3px;
white-space: pre-wrap;
word-break: break-word;
line-height: 1.4;
}
/* Phase badge */
.phase-badge {
display: inline-block;
padding: 0 4px;
font-size: 7px;
border-radius: 2px;
background: var(--surface3);
color: var(--text-dim);
margin-bottom: 2px;
text-transform: uppercase;
letter-spacing: 0.5px;
}
/* Progress steps */
#phase-bar {
padding: 6px 8px;
border-bottom: 1px solid var(--border);
flex-shrink: 0;
}
.steps-track {
display: flex; gap: 3px; align-items: center;
}
.step-item {
flex: 1;
height: 3px;
border-radius: 2px;
background: var(--surface3);
transition: all 0.3s;
position: relative;
}
.step-item.done     { background: var(--accent2); }
.step-item.active   { background: var(--accent); animation: step-pulse 1s ease-in-out infinite; }
.step-item.pending  { background: var(--surface3); }
@keyframes step-pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
.step-labels {
display: flex; justify-content: space-between;
font-size: 7px; color: var(--text-dim); margin-top: 3px;
letter-spacing: 0.3px;
}
/* =========================================================================
FOOTER — Bug report + status
========================================================================= */
#footer {
display: flex; align-items: center; gap: 10px;
padding: 6px 14px;
border-top: 1px solid var(--border);
background: var(--surface);
flex-shrink: 0;
}
.footer-info { font-size: 9px; color: var(--text-dim); flex: 1; }
.footer-info strong { color: var(--accent); }
#bug-area { display: flex; align-items: center; gap: 6px; }
#bug-txt { display: none; }
/* =========================================================================
MODAL — Article complet
========================================================================= */
.modal-overlay {
display: none;
position: fixed; inset: 0; z-index: 500;
background: rgba(0,0,0,0.88);
backdrop-filter: blur(4px);
animation: modal-fade 0.2s ease;
}
.modal-overlay.open { display: flex; align-items: center; justify-content: center; padding: 20px; }
@keyframes modal-fade { from{opacity:0} to{opacity:1} }
.modal-box {
background: var(--surface);
border: 1px solid var(--border2);
border-radius: 12px;
width: 100%;
max-width: 860px;
max-height: 90vh;
display: flex; flex-direction: column;
box-shadow: 0 0 60px rgba(0,200,255,0.15);
animation: modal-in 0.3s cubic-bezier(.34,1.56,.64,1) both;
overflow: hidden;
}
@keyframes modal-in { from{transform:scale(0.92);opacity:0} to{transform:scale(1);opacity:1} }
.modal-head {
display: flex; align-items: center; justify-content: space-between;
padding: 16px 20px;
border-bottom: 1px solid var(--border);
flex-shrink: 0;
}
.modal-head h2 {
font-family: var(--display);
font-size: 16px;
font-weight: 700;
color: var(--text-bright);
}
.modal-close {
width: 28px; height: 28px;
display: flex; align-items: center; justify-content: center;
border: 1px solid var(--border2);
border-radius: 4px;
background: transparent;
color: var(--text-dim);
cursor: pointer;
transition: all 0.15s;
font-size: 14px;
}
.modal-close:hover { border-color: var(--accent3); color: var(--accent3); }
.modal-body {
flex: 1; overflow-y: auto; padding: 20px;
}
.modal-body::-webkit-scrollbar { width: 4px; }
.modal-body::-webkit-scrollbar-thumb { background: var(--border2); }
.modal-footer {
padding: 14px 20px;
border-top: 1px solid var(--border);
display: flex; justify-content: space-between; align-items: center;
flex-shrink: 0;
}
/* Article sections */
.art-section {
margin-bottom: 16px;
border: 1px solid var(--border);
border-radius: 8px;
overflow: hidden;
}
.art-section-head {
display: flex; align-items: center; gap: 8px;
padding: 10px 14px;
background: var(--surface2);
border-bottom: 1px solid var(--border);
font-family: var(--display);
font-size: 11px;
font-weight: 700;
color: var(--accent);
letter-spacing: 1px;
text-transform: uppercase;
}
.art-section-body {
padding: 14px;
font-size: 11px;
line-height: 1.7;
color: var(--text);
}
.art-section.vulgar .art-section-head { color: var(--accent2); }
.art-section.critique .art-section-head { color: var(--accent4); }
.art-section.method .art-section-head { color: #c084fc; }
.score-bars { display: flex; flex-direction: column; gap: 6px; }
.score-row { display: grid; grid-template-columns: 120px 1fr 32px; align-items: center; gap: 8px; }
.score-label { font-size: 9px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; }
.score-bar { height: 4px; border-radius: 2px; background: var(--surface3); }
.score-bar-fill { height: 100%; border-radius: 2px; background: var(--accent); transition: width 1s; }
.score-val { font-size: 9px; color: var(--text-bright); text-align: right; }
.tag-cloud { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.kw-tag { padding: 2px 7px; border-radius: 3px; background: var(--surface2); border: 1px solid var(--border); font-size: 9px; color: var(--accent); }
/* Deep research result */
.deep-result { padding: 14px; }
.deep-status {
display: inline-flex; align-items: center; gap: 6px;
padding: 6px 12px;
border-radius: 4px;
font-size: 11px;
margin-bottom: 12px;
}
.deep-status.confirmed { background: rgba(10,255,176,0.1); color: var(--accent2); border: 1px solid rgba(10,255,176,0.3); }
.deep-status.partial   { background: rgba(255,215,0,0.1);  color: var(--accent4); border: 1px solid rgba(255,215,0,0.3); }
.deep-status.refuted   { background: rgba(255,61,107,0.1); color: var(--accent3); border: 1px solid rgba(255,61,107,0.3); }
.deep-status.inconclusive { background: var(--surface2); color: var(--text-dim); border: 1px solid var(--border); }
/* Loading state */
.modal-loading {
display: flex; flex-direction: column; align-items: center; justify-content: center;
padding: 60px 20px; gap: 16px;
}
.dna-spinner {
width: 48px; height: 48px;
border: 2px solid var(--border);
border-top-color: var(--accent);
border-radius: 50%;
animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-msg { font-size: 11px; color: var(--text-dim); }
/* =========================================================================
NOTIFICATIONS TOAST
========================================================================= */
#toast-area {
position: fixed; top: 20px; right: 20px; z-index: 999;
display: flex; flex-direction: column; gap: 8px;
}
.toast {
padding: 10px 16px;
border-radius: 6px;
border: 1px solid;
font-size: 11px;
animation: toast-in 0.3s cubic-bezier(.34,1.56,.64,1) both;
max-width: 300px;
}
@keyframes toast-in { from{opacity:0;transform:translateX(20px)} to{opacity:1;transform:translateX(0)} }
.toast.success { background: rgba(10,255,176,0.1); border-color: var(--accent2); color: var(--accent2); }
.toast.error   { background: rgba(255,61,107,0.1); border-color: var(--accent3); color: var(--accent3); }
.toast.info    { background: rgba(0,200,255,0.1);  border-color: var(--accent);  color: var(--accent); }
/* =========================================================================
RESPONSIVE
========================================================================= */
@media (max-width: 900px) {
#main { grid-template-columns: 1fr; }
#logs-panel { display: none; }
html, body { overflow: auto; }
#root { height: auto; }
#results-grid { grid-template-columns: 1fr; }
}
/* =========================================================================
UTILITIES
========================================================================= */
.hidden { display: none !important; }
.mono { font-family: var(--mono); }
.ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
</head>
<body>
<div id="root">
<!-- ═══════════════════════════════════════════
HEADER
═══════════════════════════════════════════ -->
<header id="header">
<div class="header-left">
<div class="header-brand">GENESIS<span>·</span>ULTRA</div>
<div class="header-sub">v9.1 NEURON · Chercheur Autonome · 8 Sources</div>
</div>
<div class="header-center">
<div id="status-pill" class="init">
<span class="pulse-dot"></span>
<span id="phase-label">INITIALISATION</span>
</div>
<div id="timer-display">00:00:00</div>
</div>
<div class="header-right">
<div class="ctrl-row">
<button class="btn warning" id="btn-pause" onclick="togglePause()">⏸ Pause</button>
<div class="export-wrap">
<button class="btn primary" onclick="toggleExportMenu()">↓ Exporter ▾</button>
<div id="export-menu">
<a href="#" onclick="exportData('csv');return false">📊 CSV (Excel, R, pandas)</a>
<a href="#" onclick="exportData('json');return false">🔵 JSON complet</a>
<a href="#" onclick="exportData('bibtex');return false">📄 BibTeX (Zotero)</a>
<a href="#" onclick="exportData('ris');return false">📄 RIS (EndNote)</a>
<a href="#" onclick="exportData('jsonld');return false">🌐 JSON-LD (Web sémantique)</a>
</div>
</div>
</div>
<div class="ctrl-row">
<div id="target-chip" class="stat-chip">🎯 <span class="val" id="current-target">—</span></div>
</div>
</div>
</header>
<!-- ═══════════════════════════════════════════
TOPBAR — Stats + Search
═══════════════════════════════════════════ -->
<div id="topbar">
<div class="stat-chip">🧬 <span class="val accent-val" id="total-hypo">0</span> découvertes</div>
<div class="stat-chip">📚 Articles <span class="val" id="total-articles">0</span></div>
<div class="stat-chip">🔬 Deep <span class="val" id="total-deep">0</span></div>
<div class="stat-chip">📡 Sources <span class="val green-val" id="sources-ok">0/8</span></div>
<div class="stat-chip">⚠️ Erreurs <span class="val" id="error-count">0</span></div>
<div class="topbar-sep"></div>
<div class="search-wrap">
<input type="text" id="search-input" placeholder="🔍 Rechercher dans les découvertes..." oninput="onSearch(this.value)">
<select class="sort-select" id="sort-select" onchange="loadHypotheses(1)">
<option value="date">Date ↓</option>
<option value="novelty">Novelty ↓</option>
<option value="confidence">Confiance ↓</option>
</select>
<button class="btn sm" onclick="loadHypotheses(1)">↻</button>
</div>
</div>
<!-- ═══════════════════════════════════════════
MAIN CONTENT
═══════════════════════════════════════════ -->
<div id="main">
<!-- ── Results Panel ── -->
<div id="results-panel">
<div id="results-header">
<div class="panel-title">Base de Découvertes <span id="count-label">—</span></div>
<div class="btn-group">
<button class="btn sm" onclick="loadHypotheses(currentPage)">↻ Rafraîchir</button>
</div>
</div>
<div id="results-grid">
<div class="empty-state">
<div class="icon">🧬</div>
<p>L'IA explore les bases scientifiques...<br>Les découvertes apparaîtront ici.</p>
</div>
</div>
<div id="pagination"></div>
</div>
<!-- ── Logs Panel ── -->
<div id="logs-panel">
<div id="logs-header">
<div class="panel-title">Conscience IA</div>
<button class="btn sm icon-only" onclick="clearLogs()" title="Effacer logs">✕</button>
</div>
<div id="phase-bar">
<div class="steps-track" id="steps-track">
<!-- Injected by JS -->
</div>
<div class="step-labels">
<span>Cible</span>
<span>PubMed</span>
<span>UniProt</span>
<span>ClinVar</span>
<span>ArXiv</span>
<span>EuroPMC</span>
<span>OpenAlex</span>
<span>ChEMBL</span>
<span>Wiki</span>
<span>Synthèse</span>
</div>
</div>
<div id="logs-container">
<div class="log-entry info">
<div class="log-time">--:--:--</div>
<div class="log-body"><div class="log-msg">⏳ Initialisation du moteur...</div></div>
</div>
</div>
</div>
</div>
<!-- ═══════════════════════════════════════════
FOOTER
═══════════════════════════════════════════ -->
<footer id="footer">
<div class="footer-info">
Session: <strong><?=$SESSION_ID?></strong> · Version: <strong>9.1-NEURON-FIXED</strong> ·
Uptime: <strong id="uptime-str">--</strong>
</div>
<div id="bug-area">
<button class="btn sm danger" onclick="copyBugReport()">🐛 Rapport Bug</button>
<textarea id="bug-txt" readonly></textarea>
<span id="copy-ok" style="font-size:9px;color:var(--accent2);display:none">✅ Copié!</span>
</div>
</footer>
</div><!-- #root -->
<!-- ═══════════════════════════════════════════
MODAL ARTICLE
═══════════════════════════════════════════ -->
<div id="modal" class="modal-overlay">
<div class="modal-box">
<div class="modal-head">
<h2 id="modal-title">📄 Article Scientifique</h2>
<button class="modal-close" onclick="closeModal()">✕</button>
</div>
<div class="modal-body" id="modal-body">
<div class="modal-loading">
<div class="dna-spinner"></div>
<div class="loading-msg">Génération de l'article en cours...</div>
</div>
</div>
<div class="modal-footer" id="modal-footer" style="display:none">
<div class="btn-group" id="modal-actions"></div>
<button class="btn" onclick="closeModal()">Fermer</button>
</div>
</div>
</div>
<!-- ═══════════════════════════════════════════
TOAST AREA
═══════════════════════════════════════════ -->
<div id="toast-area"></div>
<!-- ═══════════════════════════════════════════
JAVASCRIPT PRINCIPAL
═══════════════════════════════════════════ -->
<script>
'use strict';
// ============================================================
// STATE
// ============================================================
const SESSION = '<?=$SESSION_ID?>';
let startTime     = Date.now();
let currentPage   = 1;
let totalPages    = 1;
let totalCount    = 0;
let isPaused      = false;
let pollTimer     = null;
let searchTimer   = null;
let lastLogs      = [];
let errorCount    = 0;
let currentHypoId = null;
// Step progression (0=target, 1-8=sources, 9=synthesis) — 8 sources (Semantic Scholar retiré)
const STEP_LABELS = ['Cible','PubMed','UniProt','ClinVar','ArXiv','EuroPMC','OpenAlex','ChEMBL','Wiki','Synthèse'];
// ============================================================
// TIMER
// ============================================================
function fmt(n) { return String(n).padStart(2,'0'); }
setInterval(() => {
const s = Math.floor((Date.now()-startTime)/1000);
document.getElementById('timer-display').textContent = fmt(Math.floor(s/3600))+':'+fmt(Math.floor((s%3600)/60))+':'+fmt(s%60);
}, 1000);
// ============================================================
// PHASE BAR
// ============================================================
function initStepsTrack() {
const el = document.getElementById('steps-track');
el.innerHTML = STEP_LABELS.map((_,i) => `<div class="step-item pending" id="step-${i}"></div>`).join('');
}
function updateStepsTrack(step) {
STEP_LABELS.forEach((_,i) => {
const el = document.getElementById('step-'+i);
if(!el) return;
el.className = 'step-item ' + (i < step ? 'done' : i === step ? 'active' : 'pending');
});
}
// ============================================================
// API UTILS
// ============================================================
async function api(action, params={}) {
const q = new URLSearchParams({action, session: SESSION, ...params});
try {
const r = await fetch('agent.php?' + q.toString());
const text = await r.text();
if(!text.trim().startsWith('{')) throw new Error('Not JSON: ' + text.substring(0,120));
return JSON.parse(text);
} catch(e) {
console.warn('API error', action, e.message);
return {error: e.message};
}
}
// ============================================================
// INIT
// ============================================================
async function initEngine() {
setStatus('init','INITIALISATION');
const data = await api('init');
if(data.ok) {
setStatus('running','ACTIF');
toast('🚀 GENESIS-ULTRA v9.1 NEURON démarré', 'success');
loadHypotheses(1);
loadStats();
startPolling();
} else {
setStatus('error','ERREUR INIT');
toast('❌ ' + (data.error || 'Initialisation échouée'), 'error');
}
}
// ============================================================
// POLLING
// ============================================================
function startPolling() {
if(pollTimer) clearInterval(pollTimer);
pollTimer = setInterval(pollServer, 3500);
pollServer(); // immediate
}
async function pollServer() {
if(isPaused) return;
const data = await api('observe');
if(data.error) { errorCount++; updateErrorCount(); return; }
errorCount = 0;
updateErrorCount();
// Phase & Status
const phase = data.phase || 'unknown';
const status= data.status || 'running';
const step  = data.step  ?? 0;
setStatus(status, phaseLabel(phase, data.target));
updateStepsTrack(step);
if(data.target) document.getElementById('current-target').textContent = data.target;
if(data.sources_ok != null) document.getElementById('sources-ok').textContent = data.sources_ok+'/8';
if(data.total != null) document.getElementById('total-hypo').textContent = data.total;
// Logs
if(data.logs && data.logs.length > 0) {
lastLogs = data.logs;
renderLogs(data.logs);
updateBugReport();
}
// Nouvelle hypothèse → reload
if(data.new_hypothesis) {
loadHypotheses(currentPage === 1 ? 1 : currentPage);
loadStats();
toast('✨ Nouvelle découverte archivée !', 'success');
}
}
function phaseLabel(phase, target) {
const map = {
'init': 'INITIALISATION',
'target_selection': '🎯 SÉLECTION CIBLE',
'data_harvest': '📡 COLLECTE DONNÉES',
'synthesis': '🧠 SYNTHÈSE IA',
'paused': '⏸ EN PAUSE',
'error': '❌ ERREUR',
'halted': '🛑 ARRÊTÉ',
};
const label = map[phase] || phase.toUpperCase();
return target ? label + ' · ' + target : label;
}
// ============================================================
// STATUS
// ============================================================
function setStatus(status, label) {
const pill = document.getElementById('status-pill');
pill.className = 'running'; // default
if(status === 'error') pill.className = 'error';
else if(status === 'paused') pill.className = 'paused';
else if(status === 'init') pill.className = 'init';
document.getElementById('phase-label').textContent = label;
}
// ============================================================
// LOGS
// ============================================================
function renderLogs(logs) {
const container = document.getElementById('logs-container');
const wasBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 40;
container.innerHTML = logs.slice(-80).map(l => {
const cls   = l.type || 'info';
const phase = l.phase ? `<div class="phase-badge">${l.phase}</div>` : '';
const det   = l.detail ? `<div class="log-detail">${escHtml(String(l.detail))}</div>` : '';
return `<div class="log-entry ${cls}">
<div class="log-time">${l.time||'--:--:--'}</div>
<div class="log-body">${phase}<div class="log-msg">${escHtml(l.msg||'')}</div>${det}</div>
</div>`;
}).join('');
if(wasBottom) container.scrollTop = container.scrollHeight;
}
function clearLogs() {
document.getElementById('logs-container').innerHTML = '';
lastLogs = [];
}
// ============================================================
// HYPOTHESES
// ============================================================
async function loadHypotheses(page=1) {
currentPage = page;
const sort   = document.getElementById('sort-select')?.value || 'date';
const search = document.getElementById('search-input')?.value || '';
const params = {page, limit: 12, sort};
if(search) params.search = search;
const data = await api('load_hypotheses', params);
if(data.error) return;
const hypos = data.hypotheses || [];
const pag   = data.pagination || {};
totalPages  = pag.total_pages || 1;
totalCount  = pag.total_count || hypos.length;
document.getElementById('count-label').textContent = totalCount + ' résultat' + (totalCount !== 1 ? 's' : '');
document.getElementById('total-hypo').textContent  = totalCount;
renderHypotheses(hypos);
renderPagination(page, totalPages);
}
function renderHypotheses(hypos) {
const grid = document.getElementById('results-grid');
if(!hypos.length) {
grid.innerHTML = `<div class="empty-state"><div class="icon">🔬</div><p>Aucune découverte encore archivée.<br>L'IA est en train d'explorer...</p></div>`;
return;
}
grid.innerHTML = hypos.map(h => hypoCard(h)).join('');
}
function hypoCard(h) {
const novelty = h.novelty || h.confidence || 0.5;
const pct     = Math.round(novelty * 100);
const hi      = novelty >= 0.7 ? 'high-novelty' : '';
// Source pills
const sources = [...new Set(h.sources || [])];
const srcHtml = sources.map(s => {
const cls  = 'src-' + s.toLowerCase().replace(/\s/g,'').replace('scholar','').replace('semantics','semanticscholar');
const term = encodeURIComponent(h.target||'');
const urls = {
'PubMed':          'https://pubmed.ncbi.nlm.nih.gov/?term='+term,
'UniProt':         'https://www.uniprot.org/uniprot/?query='+term,
'ClinVar':         'https://www.ncbi.nlm.nih.gov/clinvar/?term='+term,
'ArXiv':           'https://arxiv.org/search/?query='+term,
'EuropePMC':       'https://europepmc.org/search?query='+term,
'OpenAlex':        'https://openalex.org/works?search='+term,
'ChEMBL':          'https://www.ebi.ac.uk/chembl/compound_report_card/search/?q='+term,
'Wikidata':        'https://www.wikidata.org/w/index.php?search='+term,
};
return `<a href="${urls[s]||'#'}" target="_blank" class="src-pip ${cls}" onclick="event.stopPropagation()">${s}</a>`;
}).join('');
// Ring SVG
const r = 15; const circ = 2 * Math.PI * r;
const fill  = circ - (novelty * circ);
const color = novelty > 0.7 ? '#0affb0' : novelty > 0.4 ? '#00c8ff' : '#5a7a95';
const title = h.title || h.hypothesis || 'Hypothèse ' + (h.target||'');
const shortTitle = title.length > 90 ? title.substring(0,87)+'...' : title;
const evidence = h.evidence_strength || '';
const evidBadge = evidence ? `<span class="meta-tag evidence">⚡ ${evidence}</span>` : '';
return `<div class="hypo-card ${hi}" onclick="openArticle('${escAttr(h.id)}')">
<div class="card-top">
<div class="card-title">${escHtml(shortTitle)}</div>
<div class="novelty-ring">
<svg viewBox="0 0 36 36"><circle class="track" cx="18" cy="18" r="${r}"/><circle class="fill" cx="18" cy="18" r="${r}" stroke="${color}" stroke-dasharray="${circ}" stroke-dashoffset="${fill}"/></svg>
<div class="novelty-val">${pct}%</div>
</div>
</div>
<div class="card-meta">
${h.domain ? `<span class="meta-tag domain">🏷 ${escHtml(h.domain)}</span>` : ''}
<span class="meta-tag sources">📡 ${sources.length} src</span>
${evidBadge}
</div>
${h.vulgarized ? `<div class="card-vulgar">${escHtml(h.vulgarized)}</div>` : ''}
<div class="card-sources">${srcHtml}</div>
<div class="card-footer">
<div class="card-date">${h.saved_at ? fmtDate(h.saved_at) : '—'}</div>
<div class="card-actions">
<button class="btn sm success" onclick="event.stopPropagation();openDeepResearch('${escAttr(h.id)}')">🔬 Deep</button>
<button class="btn sm danger" onclick="event.stopPropagation();deleteHypo('${escAttr(h.id)}')">✕</button>
</div>
</div>
</div>`;
}
function fmtDate(ts) {
return new Date(ts*1000).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
}
// ============================================================
// PAGINATION
// ============================================================
function renderPagination(current, total) {
const el = document.getElementById('pagination');
if(total <= 1) { el.innerHTML = ''; return; }
let html = `<div class="page-info">Page ${current} / ${total}</div>`;
if(current > 1)     html += `<button class="page-btn" onclick="loadHypotheses(${current-1})">‹</button>`;
if(current > 2)     html += `<button class="page-btn" onclick="loadHypotheses(1)">1</button>`;
if(current > 3)     html += `<span class="page-info">…</span>`;
for(let i=Math.max(1,current-1);i<=Math.min(total,current+1);i++) {
html += `<button class="page-btn${i===current?' active':''}" onclick="loadHypotheses(${i})">${i}</button>`;
}
if(current < total-2) html += `<span class="page-info">…</span>`;
if(current < total-1) html += `<button class="page-btn" onclick="loadHypotheses(${total})">${total}</button>`;
if(current < total)   html += `<button class="page-btn" onclick="loadHypotheses(${current+1})">›</button>`;
el.innerHTML = html;
}
// ============================================================
// MODAL — ARTICLE COMPLET
// ============================================================
async function openArticle(hypoId) {
currentHypoId = hypoId;
openModal('📄 Article Scientifique — Chargement...');
const data = await api('generate_article', {id: hypoId});
if(data.error) {
document.getElementById('modal-body').innerHTML = `<div class="log-entry error"><div class="log-time">!</div><div class="log-body"><div class="log-msg">❌ ${escHtml(data.error)}</div></div></div>`;
return;
}
document.getElementById('modal-title').textContent = '📄 Article Scientifique Complet';
const vs = data.validation_score || 0.5;
const is = data.impact_score || data.validation_score || 0.5;
const ns = data.novelty || 0.65;
const kwHtml = (data.keywords || []).map(k => `<span class="kw-tag">${escHtml(k)}</span>`).join('');
const colabHtml = (data.collaborations_needed || []).map(c => `<span class="kw-tag">${escHtml(c)}</span>`).join('');
document.getElementById('modal-body').innerHTML = `
<div class="art-section">
<div class="art-section-head">📊 Scores de Qualité</div>
<div class="art-section-body">
<div class="score-bars">
${scorebar('Validation', vs)}
${scorebar('Impact', is)}
${scorebar('Nouveauté', ns)}
</div>
${data.estimated_timeline ? `<p style="margin-top:10px;font-size:10px;color:var(--text-dim)">⏳ Timeline estimée: <strong style="color:var(--accent)">${data.estimated_timeline}</strong></p>` : ''}
</div>
</div>
<div class="art-section">
<div class="art-section-head">🔬 Résumé Scientifique</div>
<div class="art-section-body">${escHtml(data.scientific_summary || '—')}</div>
</div>
<div class="art-section vulgar">
<div class="art-section-head">🌍 Vulgarisation (Grand Public)</div>
<div class="art-section-body">${escHtml(data.vulgarized || '—')}</div>
</div>
<div class="art-section method">
<div class="art-section-head">⚗️ Méthodologie & Protocole</div>
<div class="art-section-body">${escHtml(data.methodology || '—')}</div>
</div>
<div class="art-section">
<div class="art-section-head">🎯 Actions Recommandées</div>
<div class="art-section-body">${escHtml(data.actionable || '—')}</div>
</div>
${data.clinical_implications ? `<div class="art-section">
<div class="art-section-head">🏥 Implications Cliniques</div>
<div class="art-section-body">${escHtml(data.clinical_implications)}</div>
</div>` : ''}
<div class="art-section critique">
<div class="art-section-head">🔍 Auto-Critique & Limites</div>
<div class="art-section-body">${escHtml(data.self_critique || '—')}</div>
</div>
${data.future_directions ? `<div class="art-section">
<div class="art-section-head">🚀 Directions Futures</div>
<div class="art-section-body">${escHtml(data.future_directions)}</div>
</div>` : ''}
${kwHtml ? `<div class="art-section">
<div class="art-section-head">🏷 Mots-Clés</div>
<div class="art-section-body"><div class="tag-cloud">${kwHtml}</div></div>
</div>` : ''}
${colabHtml ? `<div class="art-section">
<div class="art-section-head">🤝 Collaborations Nécessaires</div>
<div class="art-section-body"><div class="tag-cloud">${colabHtml}</div></div>
</div>` : ''}
<div class="art-section">
<div class="art-section-head">📡 Sources Croisées</div>
<div class="art-section-body">${(data.sources||[]).join(' · ') || '—'}</div>
</div>
`;
document.getElementById('modal-footer').style.display = 'flex';
document.getElementById('modal-actions').innerHTML = `
<button class="btn success" onclick="openDeepResearch('${escAttr(hypoId)}')">🔬 Recherche Approfondie</button>
`;
}
function scorebar(label, val) {
const pct = Math.round(val * 100);
const color = val >= 0.7 ? 'var(--accent2)' : val >= 0.4 ? 'var(--accent)' : 'var(--accent3)';
return `<div class="score-row">
<div class="score-label">${label}</div>
<div class="score-bar"><div class="score-bar-fill" style="width:${pct}%;background:${color}"></div></div>
<div class="score-val">${pct}%</div>
</div>`;
}
// ============================================================
// DEEP RESEARCH
// ============================================================
async function openDeepResearch(hypoId) {
currentHypoId = hypoId;
openModal('🔬 Recherche Approfondie — Analyse...');
const data = await api('deep_research', {id: hypoId});
if(data.error) {
document.getElementById('modal-body').innerHTML = `<div class="log-entry error"><div class="log-time">!</div><div class="log-body"><div class="log-msg">❌ ${escHtml(data.error)}</div></div></div>`;
return;
}
document.getElementById('modal-title').textContent = '🔬 Rapport de Recherche Approfondie';
const status    = data.validation_status || 'inconclusive';
const statusMap = {
'confirmed':    ['✅ CONFIRMÉE', 'confirmed'],
'partial':      ['⚡ PARTIELLEMENT CONFIRMÉE', 'partial'],
'refuted':      ['❌ RÉFUTÉE', 'refuted'],
'inconclusive': ['❓ INCONCLUSIVE', 'inconclusive'],
};
const [sLabel, sCls] = statusMap[status] || ['❓ INCONNUE', 'inconclusive'];
document.getElementById('modal-body').innerHTML = `
<div class="deep-result">
<div class="deep-status ${sCls}">${sLabel}</div>
<div class="art-section">
<div class="art-section-head">📊 Résultats</div>
<div class="art-section-body">
${scorebar('Nouvelle confiance', data.updated_confidence || 0.5)}
<p style="margin-top:10px;font-size:10px;color:var(--text-dim)">Sources analysées: <strong style="color:var(--accent)">${data.new_sources || '—'}</strong></p>
<p style="font-size:10px;color:var(--text-dim)">Bases interrogées: <strong style="color:var(--accent2)">${data.new_sources_queried || 0}</strong></p>
</div>
</div>
${data.new_insights ? `<div class="art-section">
<div class="art-section-head">💡 Nouveaux Insights</div>
<div class="art-section-body">${escHtml(data.new_insights)}</div>
</div>` : ''}
<div class="art-section">
<div class="art-section-head">🎯 Conclusion</div>
<div class="art-section-body">${escHtml(data.conclusion || '—')}</div>
</div>
${data.recommendation ? `<div class="art-section">
<div class="art-section-head">➡️ Recommandation</div>
<div class="art-section-body">${escHtml(data.recommendation)}</div>
</div>` : ''}
${data.revised_mechanism ? `<div class="art-section">
<div class="art-section-head">🔄 Mécanisme Révisé</div>
<div class="art-section-body">${escHtml(data.revised_mechanism)}</div>
</div>` : ''}
</div>
`;
document.getElementById('modal-footer').style.display = 'flex';
document.getElementById('modal-actions').innerHTML = `
<button class="btn primary" onclick="openArticle('${escAttr(hypoId)}')">📄 Voir l'article mis à jour</button>
`;
loadHypotheses(currentPage);
loadStats();
toast('🔬 Recherche approfondie terminée', 'info');
}
// ============================================================
// MODAL UTILS
// ============================================================
function openModal(title) {
document.getElementById('modal-title').textContent = title;
document.getElementById('modal-body').innerHTML = `<div class="modal-loading"><div class="dna-spinner"></div><div class="loading-msg">Analyse en cours...</div></div>`;
document.getElementById('modal-footer').style.display = 'none';
document.getElementById('modal').classList.add('open');
}
function closeModal() {
document.getElementById('modal').classList.remove('open');
currentHypoId = null;
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });
document.getElementById('modal').addEventListener('click', e => { if(e.target.id === 'modal') closeModal(); });
// ============================================================
// STATS
// ============================================================
async function loadStats() {
const data = await api('stats');
if(data.error) return;
if(data.articles != null) document.getElementById('total-articles').textContent = data.articles;
if(data.deep_research != null) document.getElementById('total-deep').textContent = data.deep_research;
if(data.hypotheses != null) document.getElementById('total-hypo').textContent = data.hypotheses;
if(data.uptime_seconds != null) {
const s = data.uptime_seconds;
document.getElementById('uptime-str').textContent = fmt(Math.floor(s/3600))+':'+fmt(Math.floor((s%3600)/60))+':'+fmt(s%60);
}
}
// ============================================================
// SEARCH
// ============================================================
function onSearch(val) {
clearTimeout(searchTimer);
searchTimer = setTimeout(() => loadHypotheses(1), 400);
}
// ============================================================
// DELETE
// ============================================================
async function deleteHypo(id) {
if(!confirm('Supprimer définitivement cette découverte ?')) return;
const data = await api('delete', {id});
if(data.deleted) {
toast('🗑 Découverte supprimée', 'info');
loadHypotheses(currentPage);
} else {
toast('❌ Suppression échouée: ' + (data.error||''), 'error');
}
}
// ============================================================
// PAUSE/RESUME
// ============================================================
async function togglePause() {
const btn = document.getElementById('btn-pause');
if(isPaused) {
isPaused = false;
const data = await api('resume');
setStatus('running','REPRISE');
btn.textContent = '⏸ Pause';
toast('▶️ Recherche reprise', 'success');
} else {
isPaused = true;
const data = await api('pause');
setStatus('paused','EN PAUSE');
btn.textContent = '▶ Reprendre';
toast('⏸ Recherche en pause', 'info');
}
}
// ============================================================
// EXPORT
// ============================================================
function toggleExportMenu() {
document.getElementById('export-menu').classList.toggle('open');
}
document.addEventListener('click', e => {
if(!e.target.closest('.export-wrap')) {
document.getElementById('export-menu').classList.remove('open');
}
});
function exportData(fmt) {
window.open('agent.php?action=export&format='+fmt, '_blank');
document.getElementById('export-menu').classList.remove('open');
toast('⬇️ Export ' + fmt.toUpperCase() + ' lancé', 'info');
}
// ============================================================
// BUG REPORT
// ============================================================
function updateBugReport() {
const txt = document.getElementById('bug-txt');
txt.value = `=== GENESIS-ULTRA v9.1 BUG REPORT ===
Session:  ${SESSION}
Time:     ${new Date().toISOString()}
Status:   ${document.getElementById('phase-label').textContent}
Errors:   ${errorCount}
Logs:
${lastLogs.slice(-15).map(l=>`  [${l.time||'?'}][${l.type||'?'}] ${l.msg||''}`).join('\n')}
===================================`;
}
function copyBugReport() {
updateBugReport();
const txt = document.getElementById('bug-txt');
txt.select();
try { document.execCommand('copy'); } catch(e) { navigator.clipboard?.writeText(txt.value); }
const ok = document.getElementById('copy-ok');
ok.style.display = 'inline';
setTimeout(() => ok.style.display='none', 2000);
}
function updateErrorCount() {
document.getElementById('error-count').textContent = errorCount;
}
// ============================================================
// TOASTS
// ============================================================
function toast(msg, type='info', duration=3000) {
const area = document.getElementById('toast-area');
const el = document.createElement('div');
el.className = 'toast ' + type;
el.textContent = msg;
area.appendChild(el);
setTimeout(() => {
el.style.opacity = '0';
el.style.transform = 'translateX(20px)';
el.style.transition = 'all 0.3s';
setTimeout(() => el.remove(), 300);
}, duration);
}
// ============================================================
// HTML UTILS
// ============================================================
function escHtml(s) {
return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) { return String(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
// ============================================================
// INIT
// ============================================================
window.addEventListener('DOMContentLoaded', () => {
initStepsTrack();
initEngine();
});
</script>
</body>
</html>