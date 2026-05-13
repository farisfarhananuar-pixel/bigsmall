<?php
require_once 'config.php';

// ============================================================
//  AJAX ENDPOINTS (dipanggil oleh JavaScript)
// ============================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $db = getDB();
    $act = $_GET['ajax'];

    // Simpan AI result + flashcards dari browser
    if ($act === 'save_ai' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $nid      = (int)($body['note_id'] ?? 0);
        $ai       = $body['ai_content'] ?? '';
        $fcs      = $body['flashcards'] ?? [];

        if (!$nid || !$ai) { echo json_encode(['ok'=>false,'err'=>'Data kosong']); exit; }

        // Ambil subject_id untuk nota ini
        $stmt = $db->prepare("SELECT subject_id FROM notes WHERE id=?");
        $stmt->execute([$nid]);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['ok'=>false,'err'=>'Nota tidak dijumpai']); exit; }
        $sid = $row['subject_id'];

        // Update nota
        $db->prepare("UPDATE notes SET ai_content=?, status='done' WHERE id=?")->execute([$ai, $nid]);

        // Simpan flashcards
        if (!empty($fcs)) {
            $fcStmt = $db->prepare("INSERT INTO flashcards (note_id, subject_id, soalan, jawapan) VALUES (?,?,?,?)");
            foreach ($fcs as $fc) {
                $soalan  = trim($fc['soalan'] ?? '');
                $jawapan = trim($fc['jawapan'] ?? '');
                if ($soalan && $jawapan) $fcStmt->execute([$nid, $sid, $soalan, $jawapan]);
            }
        }
        echo json_encode(['ok'=>true]);
        exit;
    }

    // Mark nota gagal
    if ($act === 'fail_note' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $nid = (int)($body['note_id'] ?? 0);
        if ($nid) $db->prepare("UPDATE notes SET status='failed' WHERE id=?")->execute([$nid]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    echo json_encode(['ok'=>false,'err'=>'Unknown action']);
    exit;
}

// ============================================================
//  ROUTING
// ============================================================
$db = getDB();
$page      = $_GET['page'] ?? 'dashboard';
$subjectId = (int)($_GET['sid'] ?? 0);
$noteId    = (int)($_GET['nid'] ?? 0);
$msg       = '';
$error     = '';

// ============================================================
//  PROSES POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- Tambah subjek ---
    if ($action === 'add_subject') {
        $code  = trim($_POST['code'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#16a34a';
        if ($code && $name) {
            $db->prepare("INSERT INTO subjects (code, name, color) VALUES (?,?,?)")->execute([$code, $name, $color]);
            header('Location: index.php?page=dashboard&msg=subject_added'); exit;
        }
    }

    if ($action === 'delete_subject') {
        $db->prepare("DELETE FROM subjects WHERE id=?")->execute([(int)$_POST['sid']]);
        header('Location: index.php?page=dashboard'); exit;
    }

    if ($action === 'update_progress') {
        $sid  = (int)$_POST['sid'];
        $prog = min(100, max(0, (int)$_POST['progress']));
        $db->prepare("UPDATE subjects SET progress=? WHERE id=?")->execute([$prog, $sid]);
        header('Location: index.php?page=subject&sid='.$sid.'&msg=progress_updated'); exit;
    }

    // --- Upload nota: PHP extract teks sahaja, AI buat dalam JS ---
    if ($action === 'upload_note') {
        $sid      = (int)$_POST['sid'];
        $language = $_POST['language'] ?? 'malay';
        $mode     = $_POST['mode'] ?? 'ringkasan';

        if (!empty($_FILES['dokumen']['name'][0])) {
            $files     = $_FILES['dokumen'];
            $count     = count($files['name']);
            $processed = 0;
            $pendingIds = [];

            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf','ppt','pptx'])) continue;
                if ($files['size'][$i] > MAX_FILE_SIZE) continue;

                $tmpName = UPLOAD_DIR . uniqid('note_', true) . '.' . $ext;
                if (!move_uploaded_file($files['tmp_name'][$i], $tmpName)) continue;

                // Extract teks
                $teks = ($ext === 'pdf') ? extractPDF($tmpName) : extractPPTX($tmpName);
                @unlink($tmpName);

                if (empty(trim($teks))) continue;

                $teks = mb_substr($teks, 0, 12000);

                // Simpan ke DB dengan status 'pending' — AI akan buat dalam JS
                $stmt = $db->prepare("INSERT INTO notes (subject_id, filename, original_name, raw_text, mode, language, status) VALUES (?,?,?,?,?,?,'pending')");
                $stmt->execute([$sid, $files['name'][$i], $files['name'][$i], $teks, $mode, $language]);
                $pendingIds[] = $db->lastInsertId();
                $processed++;
            }

            if ($processed > 0) {
                // Redirect ke subject page dengan pending IDs — JS akan proses AI
                $idsParam = implode(',', $pendingIds);
                header('Location: index.php?page=subject&sid='.$sid.'&processing='.$idsParam.'&lang='.$language.'&mode='.$mode);
                exit;
            }
        }
        $error = "Tiada fail yang berjaya diekstrak. Pastikan PDF/PPTX mengandungi teks.";
    }

    if ($action === 'delete_note') {
        $nid = (int)$_POST['nid'];
        $sid = (int)$_POST['sid'];
        $db->prepare("DELETE FROM notes WHERE id=?")->execute([$nid]);
        header('Location: index.php?page=subject&sid='.$sid); exit;
    }
}

// ============================================================
//  AMBIL DATA
// ============================================================
$subjects = $db->query("SELECT * FROM subjects ORDER BY created_at ASC")->fetchAll();

$subject = null;
if ($subjectId) {
    $s = $db->prepare("SELECT * FROM subjects WHERE id=?");
    $s->execute([$subjectId]);
    $subject = $s->fetch();
}

$notes = [];
if ($subjectId) {
    $s = $db->prepare("SELECT * FROM notes WHERE subject_id=? ORDER BY created_at DESC");
    $s->execute([$subjectId]);
    $notes = $s->fetchAll();
}

$note = null;
$flashcards = [];
if ($noteId) {
    $s = $db->prepare("SELECT n.*, s.name as subject_name, s.code as subject_code FROM notes n JOIN subjects s ON n.subject_id=s.id WHERE n.id=?");
    $s->execute([$noteId]);
    $note = $s->fetch();
    $s2 = $db->prepare("SELECT * FROM flashcards WHERE note_id=? ORDER BY id ASC");
    $s2->execute([$noteId]);
    $flashcards = $s2->fetchAll();
}

$totalNotes  = $db->query("SELECT COUNT(*) FROM notes WHERE status='done'")->fetchColumn();
$totalFC     = $db->query("SELECT COUNT(*) FROM flashcards")->fetchColumn();
$avgProgress = $db->query("SELECT COALESCE(AVG(progress),0) FROM subjects")->fetchColumn();

// Pending notes untuk proses AI (dari redirect selepas upload)
$processingIds  = [];
$processingLang = $_GET['lang'] ?? 'malay';
$processingMode = $_GET['mode'] ?? 'ringkasan';
if (!empty($_GET['processing'])) {
    foreach (explode(',', $_GET['processing']) as $id) {
        $id = (int)$id;
        if ($id > 0) $processingIds[] = $id;
    }
}

// Ambil raw_text untuk pending notes
$pendingData = [];
if (!empty($processingIds)) {
    $placeholders = implode(',', array_fill(0, count($processingIds), '?'));
    $s = $db->prepare("SELECT id, original_name, raw_text, mode, language FROM notes WHERE id IN ($placeholders) AND status='pending'");
    $s->execute($processingIds);
    $pendingData = $s->fetchAll();
}

$colorOptions = ['#16a34a','#15803d','#059669','#0d9488','#0891b2','#7c3aed','#db2777','#ea580c','#ca8a04'];
if (isset($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudyMate Pro — <?= $subject ? htmlspecialchars($subject['code']) : 'Dashboard' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --bg:        #050f07;
  --bg2:       #081209;
  --surface:   #0d1f10;
  --surface2:  #122615;
  --border:    #1a3a1e;
  --border2:   #234d28;
  --green:     #16a34a;
  --green-l:   #22c55e;
  --green-d:   #15803d;
  --green-glow:#16a34a33;
  --mint:      #4ade80;
  --text:      #dcfce7;
  --text2:     #86efac;
  --muted:     #4b7a55;
  --danger:    #ef4444;
  --warn:      #f59e0b;
  --white:     #f0fdf4;
}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;
  background-image:radial-gradient(ellipse at 10% 0%,rgba(22,163,74,.12) 0%,transparent 50%),radial-gradient(ellipse at 90% 100%,rgba(5,150,105,.07) 0%,transparent 50%);}
::-webkit-scrollbar{width:5px;height:5px;}
::-webkit-scrollbar-track{background:var(--bg2);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px;}
.wrapper{display:flex;min-height:100vh;}
.sidebar{width:240px;min-width:240px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto;}
.sidebar-logo{padding:1.4rem 1.2rem 1rem;border-bottom:1px solid var(--border);}
.logo-row{display:flex;align-items:center;gap:.7rem;margin-bottom:.2rem;}
.logo-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--green),#059669);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 0 16px var(--green-glow);flex-shrink:0;}
.logo-name{font-size:1.1rem;font-weight:800;color:var(--mint);letter-spacing:-.3px;}
.logo-ver{font-size:.68rem;color:var(--muted);font-weight:400;}
.sidebar-section{padding:.8rem .8rem .3rem;font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);}
.nav-item{display:flex;align-items:center;gap:.6rem;padding:.55rem .9rem;margin:.1rem .5rem;border-radius:9px;text-decoration:none;color:var(--text2);font-size:.82rem;font-weight:500;transition:all .2s;cursor:pointer;}
.nav-item:hover,.nav-item.active{background:var(--surface2);color:var(--mint);box-shadow:inset 0 0 0 1px var(--border2);}
.nav-item .ni{font-size:1rem;flex-shrink:0;}
.nav-badge{margin-left:auto;background:var(--green);color:#fff;font-size:.6rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;min-width:18px;text-align:center;}
.subj-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.sidebar-progress{margin:.2rem .9rem;margin-top:0;height:3px;background:var(--border);border-radius:2px;overflow:hidden;}
.sidebar-progress-bar{height:100%;background:linear-gradient(90deg,var(--green),var(--mint));border-radius:2px;transition:width .4s;}
.sidebar-footer{margin-top:auto;padding:1rem;border-top:1px solid var(--border);font-size:.72rem;color:var(--muted);text-align:center;}
.main{flex:1;overflow-x:hidden;padding:2rem;}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
.page-title{font-size:1.5rem;font-weight:800;color:var(--white);letter-spacing:-.5px;}
.page-sub{font-size:.82rem;color:var(--muted);margin-top:.15rem;}
.topbar-right{display:flex;align-items:center;gap:.7rem;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem;box-shadow:0 4px 24px rgba(0,0,0,.3);}
.card-sm{padding:1.1rem 1.2rem;}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;}
.stat-card{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.3rem;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:-20px;right:-20px;width:70px;height:70px;border-radius:50%;background:var(--green-glow);}
.stat-num{font-size:2rem;font-weight:800;color:var(--mint);line-height:1;}
.stat-label{font-size:.72rem;color:var(--muted);margin-top:.3rem;font-weight:500;}
.subj-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;}
.subj-card{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:1.2rem;text-decoration:none;color:inherit;transition:all .25s;display:block;position:relative;overflow:hidden;cursor:pointer;}
.subj-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--c,var(--green));border-radius:14px 14px 0 0;}
.subj-card:hover{border-color:var(--border2);transform:translateY(-3px);box-shadow:0 8px 30px rgba(0,0,0,.4);}
.subj-code{font-size:.68rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--c,var(--green));margin-bottom:.4rem;font-family:'JetBrains Mono',monospace;}
.subj-name{font-size:.88rem;font-weight:700;color:var(--white);line-height:1.4;margin-bottom:.8rem;}
.subj-prog-wrap{background:var(--border);border-radius:4px;height:5px;overflow:hidden;margin-bottom:.5rem;}
.subj-prog{height:100%;background:var(--c,var(--green));border-radius:4px;transition:width .4s;}
.subj-meta{display:flex;justify-content:space-between;font-size:.7rem;color:var(--muted);}
.add-subj-card{background:transparent;border:1.5px dashed var(--border);border-radius:14px;padding:1.2rem;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;min-height:130px;cursor:pointer;transition:all .2s;color:var(--muted);font-size:.82rem;font-weight:600;text-decoration:none;}
.add-subj-card:hover{border-color:var(--green);color:var(--green-l);}
.btn{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem 1.1rem;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
.btn-green{background:var(--green);color:#fff;}
.btn-green:hover{background:var(--green-l);box-shadow:0 4px 16px var(--green-glow);}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text2);}
.btn-outline:hover{border-color:var(--green);color:var(--mint);}
.btn-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;}
.btn-danger:hover{background:rgba(239,68,68,.2);}
.btn-sm{padding:.35rem .7rem;font-size:.75rem;}
.btn-lg{padding:.8rem 1.6rem;font-size:.9rem;}
.btn-block{width:100%;justify-content:center;}
.form-group{margin-bottom:1rem;}
.form-label{display:block;font-size:.72rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:.45rem;}
.form-control{width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:.65rem .9rem;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s;}
.form-control:focus{border-color:var(--green);box-shadow:0 0 0 3px var(--green-glow);}
select.form-control{-webkit-appearance:none;appearance:none;cursor:pointer;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.dropzone{border:2px dashed var(--border2);border-radius:12px;padding:2rem 1rem;text-align:center;background:var(--surface2);cursor:pointer;transition:all .3s;position:relative;}
.dropzone:hover,.dropzone.drag{border-color:var(--green);background:rgba(22,163,74,.04);}
.dropzone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.drop-icon{font-size:2rem;margin-bottom:.5rem;}
.drop-label{font-size:.9rem;font-weight:600;color:var(--text);}
.drop-sub{font-size:.75rem;color:var(--muted);margin-top:.3rem;}
.file-list{margin-top:.8rem;font-size:.78rem;color:var(--mint);font-family:'JetBrains Mono',monospace;display:none;}
.mode-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;margin-bottom:1rem;}
.mode-card{background:var(--surface2);border:1.5px solid var(--border);border-radius:10px;padding:.8rem;cursor:pointer;transition:all .2s;position:relative;text-align:center;}
.mode-card input{position:absolute;opacity:0;pointer-events:none;}
.mode-card:hover{border-color:var(--green-d);}
.mode-card.sel{border-color:var(--green);background:rgba(22,163,74,.08);box-shadow:0 0 0 2px var(--green-glow);}
.mode-card.sel .mc-name{color:var(--mint);}
.mc-icon{font-size:1.3rem;margin-bottom:.3rem;}
.mc-name{font-size:.75rem;font-weight:700;color:var(--text2);}
.mc-desc{font-size:.65rem;color:var(--muted);margin-top:.15rem;line-height:1.3;}
.notes-list{display:flex;flex-direction:column;gap:.8rem;}
.note-item{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:1rem 1.2rem;display:flex;align-items:center;gap:1rem;transition:all .2s;}
.note-item:hover{border-color:var(--border2);}
.note-icon{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,var(--green-d),var(--green));display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.note-info{flex:1;min-width:0;}
.note-name{font-size:.85rem;font-weight:700;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.note-meta{font-size:.72rem;color:var(--muted);margin-top:.2rem;}
.note-actions{display:flex;gap:.5rem;flex-shrink:0;}
.mode-pill{display:inline-flex;align-items:center;gap:.25rem;background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:.2rem .6rem;font-size:.68rem;color:var(--text2);font-weight:500;}
.note-content{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:1.8rem;line-height:1.85;font-size:.88rem;}
.note-content h1{font-size:1.2rem;font-weight:800;color:var(--mint);margin:1rem 0 .5rem;padding-bottom:.4rem;border-bottom:1px solid var(--border);}
.note-content h2{font-size:1rem;font-weight:700;color:var(--green-l);margin:.9rem 0 .4rem;}
.note-content h3{font-size:.9rem;font-weight:700;color:var(--text2);margin:.7rem 0 .3rem;}
.note-content ul{padding-left:1.3rem;margin:.4rem 0;}
.note-content li{margin-bottom:.3rem;color:#bbf7d0;}
.note-content strong{color:var(--white);}
.note-content em{color:var(--muted);}
.fc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;}
.fc{background:var(--surface2);border:1px solid var(--border);border-radius:12px;min-height:140px;cursor:pointer;perspective:600px;position:relative;}
.fc-inner{width:100%;height:100%;min-height:140px;transform-style:preserve-3d;transition:transform .5s;position:relative;}
.fc.flipped .fc-inner{transform:rotateY(180deg);}
.fc-front,.fc-back{position:absolute;inset:0;min-height:140px;backface-visibility:hidden;-webkit-backface-visibility:hidden;border-radius:12px;padding:1.1rem 1.2rem;display:flex;flex-direction:column;justify-content:space-between;}
.fc-front{background:var(--surface2);}
.fc-back{background:linear-gradient(135deg,var(--surface),rgba(22,163,74,.08));transform:rotateY(180deg);}
.fc-q{font-size:.82rem;font-weight:600;color:var(--text);line-height:1.5;}
.fc-a{font-size:.8rem;color:var(--mint);line-height:1.5;}
.fc-hint{font-size:.65rem;color:var(--muted);margin-top:.5rem;}
.fc-num{font-size:.65rem;font-family:'JetBrains Mono',monospace;color:var(--muted);}
.prog-bar-wrap{background:var(--border);border-radius:8px;height:10px;overflow:hidden;margin:1rem 0;}
.prog-bar{height:100%;background:linear-gradient(90deg,var(--green),var(--mint));border-radius:8px;transition:width .5s;}
.prog-pct{font-size:1.8rem;font-weight:800;color:var(--mint);}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:100;align-items:center;justify-content:center;padding:1rem;}
.modal-bg.open{display:flex;}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:18px;width:100%;max-width:480px;padding:1.8rem;animation:fadeUp .3s ease;max-height:90vh;overflow-y:auto;}
.modal-title{font-size:1rem;font-weight:800;color:var(--white);margin-bottom:1.3rem;display:flex;align-items:center;gap:.5rem;}
.alert{border-radius:10px;padding:.9rem 1.1rem;font-size:.82rem;display:flex;align-items:center;gap:.6rem;margin-bottom:1.2rem;}
.alert-ok{background:rgba(22,163,74,.1);border:1px solid rgba(22,163,74,.3);color:#86efac;}
.alert-err{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#fca5a5;}
.alert-warn{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);color:#fcd34d;}
.color-grid{display:flex;gap:.5rem;flex-wrap:wrap;}
.color-opt{width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all .2s;}
.color-opt:hover,.color-opt.sel{border-color:var(--white);transform:scale(1.15);}
.tabs{display:flex;gap:.3rem;border-bottom:1px solid var(--border);margin-bottom:1.5rem;}
.tab{padding:.6rem 1rem;font-size:.82rem;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .2s;}
.tab.active{color:var(--mint);border-bottom-color:var(--green);}
.tab-content{display:none;}
.tab-content.active{display:block;}
.spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
.empty{text-align:center;padding:3rem 1rem;color:var(--muted);font-size:.85rem;}
.empty-icon{font-size:3rem;margin-bottom:1rem;opacity:.5;}

/* Processing banner */
.processing-banner{
  background:linear-gradient(135deg,rgba(22,163,74,.12),rgba(5,150,105,.08));
  border:1px solid rgba(22,163,74,.3);
  border-radius:14px;padding:1.3rem 1.5rem;margin-bottom:1.5rem;
}
.pb-title{font-size:.9rem;font-weight:700;color:var(--mint);margin-bottom:.8rem;display:flex;align-items:center;gap:.5rem;}
.pb-item{display:flex;align-items:center;gap:.7rem;padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.82rem;}
.pb-item:last-child{border-bottom:none;}
.pb-name{flex:1;color:var(--text2);}
.pb-status{font-size:.75rem;font-weight:600;min-width:100px;text-align:right;}
.pb-status.wait{color:var(--muted);}
.pb-status.running{color:var(--warn);}
.pb-status.done{color:var(--mint);}
.pb-status.failed{color:var(--danger);}
.pb-bar{width:100%;height:4px;background:var(--border);border-radius:2px;margin-top:.3rem;overflow:hidden;}
.pb-fill{height:100%;background:linear-gradient(90deg,var(--green),var(--mint));border-radius:2px;width:0%;transition:width .5s;}

@media print{.sidebar,.topbar,.btn,.tabs,.modal-bg{display:none!important;}body{background:white;color:black;}.note-content{border:none;padding:0;}.note-content h1,.note-content h2,.note-content h3{color:black;}.note-content li{color:black;}}
@media(max-width:768px){.sidebar{display:none;}.main{padding:1rem;}.stats-grid{grid-template-columns:1fr 1fr;}.mode-grid{grid-template-columns:1fr 1fr;}.form-row{grid-template-columns:1fr;}.fc-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="wrapper">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-row">
      <div class="logo-icon">📗</div>
      <div>
        <div class="logo-name">StudyMate</div>
        <div class="logo-ver">Pro · UUM Edition</div>
      </div>
    </div>
  </div>
  <div class="sidebar-section">Menu</div>
  <a href="index.php?page=dashboard" class="nav-item <?= $page==='dashboard'?'active':'' ?>">
    <span class="ni">🏠</span> Dashboard
  </a>
  <a href="index.php?page=flashcards_all" class="nav-item <?= $page==='flashcards_all'?'active':'' ?>">
    <span class="ni">🃏</span> Semua Flashcard
    <?php if($totalFC): ?><span class="nav-badge"><?=$totalFC?></span><?php endif; ?>
  </a>
  <div class="sidebar-section" style="margin-top:.5rem;">Subjek</div>
  <?php foreach($subjects as $s): ?>
  <a href="index.php?page=subject&sid=<?=$s['id']?>" class="nav-item <?= ($page==='subject'&&$subjectId==$s['id'])?'active':'' ?>">
    <span class="subj-dot" style="background:<?=htmlspecialchars($s['color'])?>"></span>
    <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=htmlspecialchars($s['code'])?></span>
  </a>
  <div class="sidebar-progress">
    <div class="sidebar-progress-bar" style="width:<?=(int)$s['progress']?>%"></div>
  </div>
  <?php endforeach; ?>
  <div style="padding:.5rem .8rem;">
    <button onclick="openModal('modalAddSubj')" class="btn btn-outline btn-sm btn-block" style="margin-top:.5rem;">＋ Tambah Subjek</button>
  </div>
  <div class="sidebar-footer">📚 <?=count($subjects)?> subjek · <?=$totalNotes?> nota</div>
</aside>

<!-- MAIN -->
<main class="main">

<?php if($msg): ?>
<div class="alert alert-ok">✅
<?php
if(str_starts_with($msg,'uploaded_')) echo (int)substr($msg,9)." fail berjaya diproses & flashcard dijana!";
elseif($msg==='subject_added') echo "Subjek berjaya ditambah!";
elseif($msg==='progress_updated') echo "Progress berjaya dikemaskini!";
else echo htmlspecialchars($msg);
?>
</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-err">⚠️ <?=htmlspecialchars($error)?></div>
<?php endif; ?>

<!-- =================== DASHBOARD =================== -->
<?php if($page === 'dashboard'): ?>
<div class="topbar">
  <div>
    <div class="page-title">📊 Dashboard</div>
    <div class="page-sub">Selamat datang! Pilih subjek untuk mula belajar.</div>
  </div>
  <button onclick="openModal('modalAddSubj')" class="btn btn-green">＋ Tambah Subjek</button>
</div>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-num"><?=count($subjects)?></div><div class="stat-label">📚 Subjek</div></div>
  <div class="stat-card"><div class="stat-num"><?=$totalNotes?></div><div class="stat-label">📄 Nota Diproses</div></div>
  <div class="stat-card"><div class="stat-num"><?=$totalFC?></div><div class="stat-label">🃏 Flashcard</div></div>
</div>
<?php if(count($subjects)): ?>
<div class="card" style="margin-bottom:1.5rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;">
    <div style="font-size:.8rem;font-weight:700;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;">Progress Keseluruhan</div>
    <div class="prog-pct"><?=round($avgProgress)?>%</div>
  </div>
  <div class="prog-bar-wrap"><div class="prog-bar" style="width:<?=round($avgProgress)?>%"></div></div>
</div>
<?php endif; ?>
<div class="subj-grid">
  <?php foreach($subjects as $s):
    $cnt = $db->prepare("SELECT COUNT(*) FROM notes WHERE subject_id=? AND status='done'");
    $cnt->execute([$s['id']]);
    $nc = $cnt->fetchColumn();
  ?>
  <a href="index.php?page=subject&sid=<?=$s['id']?>" class="subj-card" style="--c:<?=htmlspecialchars($s['color'])?>">
    <div class="subj-code"><?=htmlspecialchars($s['code'])?></div>
    <div class="subj-name"><?=htmlspecialchars($s['name'])?></div>
    <div class="subj-prog-wrap"><div class="subj-prog" style="width:<?=(int)$s['progress']?>%"></div></div>
    <div class="subj-meta"><span><?=(int)$s['progress']?>% selesai</span><span><?=$nc?> nota</span></div>
  </a>
  <?php endforeach; ?>
  <div class="add-subj-card" onclick="openModal('modalAddSubj')">
    <span style="font-size:1.5rem;">＋</span><span>Tambah Subjek</span>
  </div>
</div>

<!-- =================== SUBJECT PAGE =================== -->
<?php elseif($page === 'subject' && $subject): ?>
<div class="topbar">
  <div>
    <div class="page-title" style="color:<?=htmlspecialchars($subject['color'])?>"><?=htmlspecialchars($subject['code'])?></div>
    <div class="page-sub"><?=htmlspecialchars($subject['name'])?></div>
  </div>
  <div class="topbar-right">
    <button onclick="openModal('modalUpload')" class="btn btn-green">＋ Upload Nota</button>
    <button onclick="openModal('modalProgress')" class="btn btn-outline">📈 Progress</button>
    <button onclick="confirmDelete(<?=$subject['id']?>,'<?=addslashes($subject['code'])?>')" class="btn btn-danger btn-sm">🗑</button>
  </div>
</div>
<div class="card card-sm" style="margin-bottom:1.3rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
    <span style="font-size:.75rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.8px;">Progress Belajar</span>
    <span style="font-size:1.3rem;font-weight:800;color:var(--mint);"><?=(int)$subject['progress']?>%</span>
  </div>
  <div class="prog-bar-wrap" style="margin:0;"><div class="prog-bar" style="width:<?=(int)$subject['progress']?>%;background:linear-gradient(90deg,<?=htmlspecialchars($subject['color'])?>,var(--mint))"></div></div>
</div>

<?php if(!empty($pendingData)): ?>
<!-- Processing banner — JS akan isi ini -->
<div class="processing-banner" id="processingBanner">
  <div class="pb-title"><span class="spinner"></span> &nbsp;Jana AI untuk <?=count($pendingData)?> fail...</div>
  <div id="pbItems">
    <?php foreach($pendingData as $pd): ?>
    <div class="pb-item" id="pb-<?=$pd['id']?>">
      <span class="pb-name">📄 <?=htmlspecialchars($pd['original_name'])?></span>
      <span class="pb-status wait" id="pbs-<?=$pd['id']?>">Menunggu...</span>
    </div>
    <div class="pb-bar"><div class="pb-fill" id="pbf-<?=$pd['id']?>"></div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="tabs">
  <div class="tab active" onclick="showTab('tab-notes',this)">📄 Nota (<?=count(array_filter($notes,fn($n)=>$n['status']==='done'))?>)</div>
  <div class="tab" onclick="showTab('tab-fc',this)">🃏 Flashcard</div>
</div>

<!-- TAB: Notes -->
<div id="tab-notes" class="tab-content active">
  <?php $doneNotes = array_filter($notes, fn($n)=>$n['status']==='done'); ?>
  <?php if(empty($doneNotes) && empty($pendingData)): ?>
  <div class="empty">
    <div class="empty-icon">📂</div>
    <div>Belum ada nota untuk subjek ini.</div>
    <button onclick="openModal('modalUpload')" class="btn btn-green" style="margin-top:1rem;">＋ Upload Pertama</button>
  </div>
  <?php else: ?>
  <div class="notes-list" id="notesList">
    <?php foreach($doneNotes as $n): ?>
    <div class="note-item">
      <div class="note-icon"><?= $n['language']==='malay'?'🇲🇾':'🇬🇧' ?></div>
      <div class="note-info">
        <div class="note-name"><?=htmlspecialchars($n['original_name'])?></div>
        <div class="note-meta">
          <span class="mode-pill">
            <?php $icons=['ringkasan'=>'📋','penerangan'=>'🎓','soaljawab'=>'❓','notapendek'=>'⚡'];
            echo ($icons[$n['mode']]??'📄').' '.ucfirst($n['mode']); ?>
          </span>
          &nbsp;· <?=date('d M Y',strtotime($n['created_at']))?>
        </div>
      </div>
      <div class="note-actions">
        <a href="index.php?page=note&nid=<?=$n['id']?>&sid=<?=$subject['id']?>" class="btn btn-outline btn-sm">👁 Baca</a>
        <button onclick="printNote(<?=$n['id']?>)" class="btn btn-outline btn-sm">🖨 PDF</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Padam nota ini?')">
          <input type="hidden" name="action" value="delete_note">
          <input type="hidden" name="nid" value="<?=$n['id']?>">
          <input type="hidden" name="sid" value="<?=$subject['id']?>">
          <button type="submit" class="btn btn-danger btn-sm">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- TAB: Flashcards -->
<div id="tab-fc" class="tab-content">
  <?php
  $fcStmt = $db->prepare("SELECT * FROM flashcards WHERE subject_id=? ORDER BY id ASC");
  $fcStmt->execute([$subject['id']]);
  $allFC = $fcStmt->fetchAll();
  ?>
  <?php if(empty($allFC)): ?>
  <div class="empty">
    <div class="empty-icon">🃏</div>
    <div>Flashcard akan dijana secara automatik apabila awak upload nota.</div>
  </div>
  <?php else: ?>
  <div style="font-size:.78rem;color:var(--muted);margin-bottom:1rem;">💡 Klik kad untuk lihat jawapan · <?=count($allFC)?> kad</div>
  <div class="fc-grid" id="fcGrid">
    <?php foreach($allFC as $k=>$fc): ?>
    <div class="fc" onclick="this.classList.toggle('flipped')">
      <div class="fc-inner">
        <div class="fc-front"><div><div class="fc-num">Soalan <?=$k+1?></div><div class="fc-q" style="margin-top:.5rem;"><?=htmlspecialchars($fc['soalan'])?></div></div><div class="fc-hint">👆 Klik untuk jawapan</div></div>
        <div class="fc-back"><div><div class="fc-num">Jawapan</div><div class="fc-a" style="margin-top:.5rem;"><?=htmlspecialchars($fc['jawapan'])?></div></div><div class="fc-hint">👆 Klik untuk soalan</div></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- =================== NOTE VIEW =================== -->
<?php elseif($page === 'note' && $note): ?>
<div class="topbar">
  <div>
    <div class="page-title" style="font-size:1.1rem;">📄 <?=htmlspecialchars($note['original_name'])?></div>
    <div class="page-sub"><?=htmlspecialchars($note['subject_code'].' · '.$note['subject_name'])?></div>
  </div>
  <div class="topbar-right">
    <a href="index.php?page=subject&sid=<?=$note['subject_id']?>" class="btn btn-outline btn-sm">← Balik</a>
    <button onclick="window.print()" class="btn btn-green btn-sm">🖨 Export PDF</button>
    <button onclick="salin()" class="btn btn-outline btn-sm">📋 Salin</button>
  </div>
</div>
<div class="note-content" id="noteBody">
  <?=formatMD($note['ai_content'] ?? '')?>
</div>
<?php if(!empty($flashcards)): ?>
<div style="margin-top:2rem;">
  <div style="font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:1rem;">🃏 Flashcard untuk nota ini (<?=count($flashcards)?> kad)</div>
  <div class="fc-grid">
    <?php foreach($flashcards as $k=>$fc): ?>
    <div class="fc" onclick="this.classList.toggle('flipped')">
      <div class="fc-inner">
        <div class="fc-front"><div><div class="fc-num">Soalan <?=$k+1?></div><div class="fc-q" style="margin-top:.5rem;"><?=htmlspecialchars($fc['soalan'])?></div></div><div class="fc-hint">👆 Klik untuk jawapan</div></div>
        <div class="fc-back"><div><div class="fc-num">Jawapan</div><div class="fc-a" style="margin-top:.5rem;"><?=htmlspecialchars($fc['jawapan'])?></div></div><div class="fc-hint">👆 Klik untuk soalan</div></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- =================== ALL FLASHCARDS =================== -->
<?php elseif($page === 'flashcards_all'): ?>
<div class="topbar">
  <div>
    <div class="page-title">🃏 Semua Flashcard</div>
    <div class="page-sub">Ulangkaji semua subjek dalam satu tempat · <?=$totalFC?> kad</div>
  </div>
</div>
<?php foreach($subjects as $s):
  $fcS = $db->prepare("SELECT * FROM flashcards WHERE subject_id=? ORDER BY RAND() LIMIT 20");
  $fcS->execute([$s['id']]);
  $fcList = $fcS->fetchAll();
  if(empty($fcList)) continue;
?>
<div style="margin-bottom:2rem;">
  <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem;">
    <span style="width:10px;height:10px;border-radius:50%;background:<?=htmlspecialchars($s['color'])?>;display:inline-block;"></span>
    <span style="font-size:.8rem;font-weight:700;color:var(--text2);"><?=htmlspecialchars($s['code'].' · '.$s['name'])?></span>
    <span style="font-size:.7rem;color:var(--muted);">(<?=count($fcList)?> kad)</span>
  </div>
  <div class="fc-grid">
    <?php foreach($fcList as $k=>$fc): ?>
    <div class="fc" onclick="this.classList.toggle('flipped')">
      <div class="fc-inner">
        <div class="fc-front"><div><div class="fc-num"><?=htmlspecialchars($s['code'])?> · #<?=$k+1?></div><div class="fc-q" style="margin-top:.5rem;"><?=htmlspecialchars($fc['soalan'])?></div></div><div class="fc-hint">👆 Klik untuk jawapan</div></div>
        <div class="fc-back"><div><div class="fc-num">Jawapan</div><div class="fc-a" style="margin-top:.5rem;"><?=htmlspecialchars($fc['jawapan'])?></div></div><div class="fc-hint">👆 Klik untuk soalan</div></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if($totalFC == 0): ?>
<div class="empty"><div class="empty-icon">🃏</div><div>Belum ada flashcard. Upload nota dahulu.</div></div>
<?php endif; ?>

<?php endif; ?>
</main>
</div>

<!-- MODAL: ADD SUBJECT -->
<div class="modal-bg" id="modalAddSubj">
  <div class="modal">
    <div class="modal-title">📚 Tambah Subjek Baru</div>
    <form method="POST">
      <input type="hidden" name="action" value="add_subject">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Kod Subjek</label>
          <input name="code" class="form-control" placeholder="cth: BKAZK3993" required maxlength="20">
        </div>
        <div class="form-group">
          <label class="form-label">Nama Subjek</label>
          <input name="name" class="form-control" placeholder="cth: Academic Project" required maxlength="200">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Warna</label>
        <div class="color-grid" id="colorGrid">
          <?php foreach($colorOptions as $k=>$c): ?>
          <div class="color-opt <?=$k===0?'sel':''?>" style="background:<?=$c?>" onclick="pickColor('<?=$c?>',this)" title="<?=$c?>"></div>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="color" id="colorInput" value="<?=$colorOptions[0]?>">
      </div>
      <div style="display:flex;gap:.7rem;margin-top:1.2rem;">
        <button type="submit" class="btn btn-green btn-lg" style="flex:1">✓ Tambah</button>
        <button type="button" onclick="closeModal('modalAddSubj')" class="btn btn-outline btn-lg">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: UPLOAD NOTE -->
<?php if($subject): ?>
<div class="modal-bg" id="modalUpload">
  <div class="modal">
    <div class="modal-title">⬆️ Upload Nota — <?=htmlspecialchars($subject['code'])?></div>
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <input type="hidden" name="action" value="upload_note">
      <input type="hidden" name="sid" value="<?=$subject['id']?>">
      <div class="form-group">
        <label class="form-label">Fail (boleh pilih berbilang)</label>
        <div class="dropzone" id="dz">
          <input type="file" name="dokumen[]" id="fi" accept=".pdf,.ppt,.pptx" multiple required>
          <div class="drop-icon">📎</div>
          <div class="drop-label">Seret atau klik untuk pilih</div>
          <div class="drop-sub">PDF, PPT, PPTX · Maks 20MB setiap fail</div>
          <div class="file-list" id="fl"></div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Bahasa Output</label>
          <select name="language" class="form-control">
            <option value="malay">🇲🇾 Bahasa Melayu</option>
            <option value="english">🇬🇧 English</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Mod AI</label>
        <div class="mode-grid">
          <label class="mode-card sel" onclick="selMode(this)"><input type="radio" name="mode" value="ringkasan" checked><div class="mc-icon">📋</div><div class="mc-name">Ringkasan</div><div class="mc-desc">Poin & tajuk</div></label>
          <label class="mode-card" onclick="selMode(this)"><input type="radio" name="mode" value="penerangan"><div class="mc-icon">🎓</div><div class="mc-name">Guru</div><div class="mc-desc">Terang & contoh</div></label>
          <label class="mode-card" onclick="selMode(this)"><input type="radio" name="mode" value="soaljawab"><div class="mc-icon">❓</div><div class="mc-name">Soal Jawab</div><div class="mc-desc">10 soalan exam</div></label>
          <label class="mode-card" onclick="selMode(this)"><input type="radio" name="mode" value="notapendek"><div class="mc-icon">⚡</div><div class="mc-name">Nota Cepat</div><div class="mc-desc">Bullet ringkas</div></label>
        </div>
      </div>
      <button type="submit" class="btn btn-green btn-lg btn-block" id="uploadBtn">
        <span id="uploadSpinner" style="display:none;" class="spinner"></span>
        <span id="uploadLabel">✨ Muat & Ekstrak Teks</span>
      </button>
      <button type="button" onclick="closeModal('modalUpload')" class="btn btn-outline btn-block" style="margin-top:.6rem;">Batal</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MODAL: PROGRESS -->
<?php if($subject): ?>
<div class="modal-bg" id="modalProgress">
  <div class="modal">
    <div class="modal-title">📈 Kemaskini Progress — <?=htmlspecialchars($subject['code'])?></div>
    <form method="POST">
      <input type="hidden" name="action" value="update_progress">
      <input type="hidden" name="sid" value="<?=$subject['id']?>">
      <div class="form-group">
        <label class="form-label">Progress (%): <span id="progVal"><?=(int)$subject['progress']?></span>%</label>
        <input type="range" name="progress" min="0" max="100" value="<?=(int)$subject['progress']?>"
          oninput="document.getElementById('progVal').textContent=this.value"
          style="width:100%;accent-color:var(--green);margin-top:.5rem;cursor:pointer;">
      </div>
      <div class="prog-bar-wrap"><div class="prog-bar" id="previewBar" style="width:<?=(int)$subject['progress']?>%"></div></div>
      <div style="display:flex;gap:.7rem;margin-top:1.2rem;">
        <button type="submit" class="btn btn-green btn-lg" style="flex:1">✓ Simpan</button>
        <button type="button" onclick="closeModal('modalProgress')" class="btn btn-outline btn-lg">Batal</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<form id="deleteSubjForm" method="POST" style="display:none">
  <input type="hidden" name="action" value="delete_subject">
  <input type="hidden" name="sid" id="deleteSubjId">
</form>

<!-- ============================================================
     GEMINI API KEY (dari PHP → JS)
     ============================================================ -->
<script>
const GEMINI_KEY = <?= json_encode(GEMINI_API_KEY) ?>;
const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

// Pending notes untuk proses AI
const PENDING_NOTES = <?= json_encode($pendingData) ?>;
const CURRENT_SID   = <?= json_encode($subjectId) ?>;
</script>

<script>
// ============================================================
//  UI helpers
// ============================================================
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-bg').forEach(m=>{
  m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');});
});
function showTab(id,el){
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  el.classList.add('active');
}
function selMode(el){
  document.querySelectorAll('.mode-card').forEach(c=>c.classList.remove('sel'));
  el.classList.add('sel');
}
function pickColor(c,el){
  document.querySelectorAll('.color-opt').forEach(o=>o.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById('colorInput').value=c;
}
const progInput=document.querySelector('input[type=range]');
if(progInput){
  progInput.addEventListener('input',function(){
    const bar=document.getElementById('previewBar');
    if(bar)bar.style.width=this.value+'%';
  });
}
const fi=document.getElementById('fi');
const fl=document.getElementById('fl');
if(fi){
  fi.addEventListener('change',function(){
    if(this.files.length){fl.style.display='block';fl.innerHTML=Array.from(this.files).map(f=>'✓ '+f.name).join('<br>');}
  });
}
const dz=document.getElementById('dz');
if(dz){
  dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('drag');});
  dz.addEventListener('dragleave',()=>dz.classList.remove('drag'));
  dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('drag');fi.files=e.dataTransfer.files;fi.dispatchEvent(new Event('change'));});
}
const uploadForm=document.getElementById('uploadForm');
if(uploadForm){
  uploadForm.addEventListener('submit',function(){
    document.getElementById('uploadBtn').disabled=true;
    document.getElementById('uploadSpinner').style.display='inline-block';
    document.getElementById('uploadLabel').textContent='Mengekstrak teks fail...';
  });
}
function confirmDelete(sid,code){
  if(confirm('Padam subjek '+code+'? Semua nota & flashcard akan turut dipadam.')){
    document.getElementById('deleteSubjId').value=sid;
    document.getElementById('deleteSubjForm').submit();
  }
}
function printNote(nid){window.open('index.php?page=note&nid='+nid+'&print=1','_blank');}
function salin(){
  const b=document.getElementById('noteBody');
  if(!b)return;
  navigator.clipboard.writeText(b.innerText).then(()=>{
    const btn=document.querySelector('[onclick="salin()"]');
    if(btn){btn.textContent='✅ Disalin!';setTimeout(()=>btn.textContent='📋 Salin',2000);}
  });
}
if(new URLSearchParams(location.search).get('print')==='1')window.print();

// ============================================================
//  GEMINI API CALL (dari browser)
// ============================================================
async function tanyaGemini(prompt) {
  const resp = await fetch(GEMINI_URL + '?key=' + GEMINI_KEY, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      contents:[{parts:[{text:prompt}]}],
      generationConfig:{temperature:0.7,maxOutputTokens:4096}
    })
  });
  if(!resp.ok) throw new Error('Gemini HTTP ' + resp.status);
  const data = await resp.json();
  const text = data?.candidates?.[0]?.content?.parts?.[0]?.text;
  if(!text) throw new Error('Respons AI kosong');
  return text;
}

// Parse flashcards dari teks AI
function parseFlashcards(text) {
  const fcs = [];
  const regex = /SOALAN:\s*(.+?)\nJAWAPAN:\s*(.+?)(?=\nSOALAN:|\s*$)/gs;
  let m;
  while((m=regex.exec(text))!==null){
    const soalan = m[1].trim();
    const jawapan = m[2].trim();
    if(soalan && jawapan) fcs.push({soalan,jawapan});
  }
  return fcs;
}

// Simpan ke DB via PHP AJAX
async function simpanKeDB(noteId, aiContent, flashcards) {
  const resp = await fetch('index.php?ajax=save_ai', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({note_id:noteId, ai_content:aiContent, flashcards})
  });
  const data = await resp.json();
  if(!data.ok) throw new Error(data.err || 'Gagal simpan');
}

async function markFailed(noteId) {
  await fetch('index.php?ajax=fail_note', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({note_id:noteId})
  });
}

// Build prompt
function buildPrompt(note) {
  const lang = note.language === 'malay' ? 'Bahasa Melayu' : 'English';
  let arahan;
  switch(note.mode) {
    case 'ringkasan':
      arahan = note.language==='malay'
        ? 'Buat RINGKASAN LENGKAP dalam Bahasa Melayu dengan tajuk, subtajuk, dan poin penting. Gunakan emoji sesuai.'
        : 'Create a COMPREHENSIVE SUMMARY in English with headings, subheadings, and key points. Use appropriate emojis.';
      break;
    case 'penerangan':
      arahan = note.language==='malay'
        ? 'TERANGKAN kandungan ini dalam Bahasa Melayu seperti seorang pensyarah mengajar. Guna contoh mudah.'
        : 'EXPLAIN this content in English like a lecturer teaching. Use simple examples.';
      break;
    case 'soaljawab':
      arahan = note.language==='malay'
        ? 'Cipta 10 SOALAN PEPERIKSAAN beserta jawapan lengkap dalam Bahasa Melayu.'
        : 'Create 10 EXAM QUESTIONS with full answers in English.';
      break;
    case 'notapendek':
      arahan = note.language==='malay'
        ? 'Buat NOTA RINGKAS dalam Bahasa Melayu menggunakan bullet points. Untuk ulangkaji pantas.'
        : 'Create CONCISE NOTES in English using bullet points. For quick revision.';
      break;
    default: arahan = 'Summarise in '+lang+'.';
  }
  return arahan + '\n\n---FAIL: ' + note.original_name + '---\n\n' + note.raw_text;
}

function buildFlashcardPrompt(aiContent, language) {
  const lang = language==='malay' ? 'Bahasa Melayu' : 'English';
  return 'Berdasarkan nota berikut, cipta 8 FLASHCARD dalam '+lang+'.\nFormat MESTI begini (satu baris tiap satu):\nSOALAN: [soalan]\nJAWAPAN: [jawapan]\n\nJangan tambah apa-apa lain selain format tu.\n\n'+aiContent;
}

// ============================================================
//  MAIN PROCESSING LOOP
// ============================================================
async function prosesSemuaNota() {
  if(!PENDING_NOTES || PENDING_NOTES.length===0) return;

  let doneCount = 0;

  for(const note of PENDING_NOTES) {
    const statusEl = document.getElementById('pbs-'+note.id);
    const fillEl   = document.getElementById('pbf-'+note.id);

    try {
      // Step 1: Jana AI
      if(statusEl) { statusEl.textContent='Jana AI...'; statusEl.className='pb-status running'; }
      if(fillEl)   fillEl.style.width='30%';

      const aiResult = await tanyaGemini(buildPrompt(note));

      if(fillEl) fillEl.style.width='65%';

      // Step 2: Jana flashcards
      if(statusEl) statusEl.textContent='Jana flashcard...';
      let flashcards = [];
      try {
        const fcResult = await tanyaGemini(buildFlashcardPrompt(aiResult, note.language));
        flashcards = parseFlashcards(fcResult);
      } catch(e) { /* flashcard optional, teruskan je */ }

      if(fillEl) fillEl.style.width='90%';

      // Step 3: Simpan ke DB
      if(statusEl) statusEl.textContent='Menyimpan...';
      await simpanKeDB(note.id, aiResult, flashcards);

      if(fillEl) fillEl.style.width='100%';
      if(statusEl) { statusEl.textContent='✅ Selesai ('+flashcards.length+' kad)'; statusEl.className='pb-status done'; }
      doneCount++;

    } catch(err) {
      console.error('Error nota '+note.id+':', err);
      await markFailed(note.id).catch(()=>{});
      if(statusEl) { statusEl.textContent='❌ Gagal: '+err.message; statusEl.className='pb-status failed'; }
      if(fillEl) { fillEl.style.width='100%'; fillEl.style.background='var(--danger)'; }
    }
  }

  // Semua selesai — reload halaman
  const banner = document.getElementById('processingBanner');
  if(banner) {
    const title = banner.querySelector('.pb-title');
    if(title) title.innerHTML = '✅ Selesai! ' + doneCount + '/' + PENDING_NOTES.length + ' fail berjaya. Memuatkan semula...';
  }
  setTimeout(()=>{
    window.location.href = 'index.php?page=subject&sid='+CURRENT_SID+'&msg=uploaded_'+doneCount;
  }, 2000);
}

// Jalankan bila ada pending notes
if(PENDING_NOTES && PENDING_NOTES.length > 0) {
  prosesSemuaNota();
}
</script>
</body>
</html>
