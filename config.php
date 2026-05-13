<?php
// ============================================================
//  STUDYMATE v2 — KONFIGURASI
//  Edit bahagian ini sebelum upload ke InfinityFree
// ============================================================

// --- DATABASE (InfinityFree MySQL) ---
define('DB_HOST', 'sql200.infinityfree.com'); // tukar ikut host InfinityFree awak
define('DB_NAME', 'nama_database');            // tukar
define('DB_USER', 'nama_user');                // tukar
define('DB_PASS', 'kata_laluan');              // tukar

// --- GEMINI API KEY (digunakan oleh JavaScript browser, bukan PHP) ---
// PENTING: Protect key di Google Cloud Console:
// APIs & Services > Credentials > Edit API Key > API restrictions (pilih Generative Language API)
// + Application restrictions > HTTP referrers > tambah domain awak
define('GEMINI_API_KEY', 'AIzaSyAYVdfxgPT8PeFUSRdqAYQ2_PHl7VpQSw0');

// --- TETAPAN FAIL ---
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('UPLOAD_DIR', 'uploads/');

// ============================================================
//  SAMBUNGAN DATABASE
// ============================================================
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        die('<div style="font-family:sans-serif;padding:2rem;background:#0a1a0a;color:#ef4444;border:1px solid #ef4444;border-radius:10px;margin:2rem;">
            <strong>⚠️ Gagal sambung database.</strong><br><br>
            Sila semak tetapan dalam <code>config.php</code>:<br>
            &bull; DB_HOST, DB_NAME, DB_USER, DB_PASS<br><br>
            <small>' . htmlspecialchars($e->getMessage()) . '</small>
        </div>');
    }
    return $pdo;
}

// ============================================================
//  SETUP DATABASE
// ============================================================
function setupDatabase() {
    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL,
            name VARCHAR(200) NOT NULL,
            color VARCHAR(7) DEFAULT '#16a34a',
            progress INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            raw_text LONGTEXT,
            ai_content LONGTEXT,
            mode VARCHAR(30) DEFAULT 'ringkasan',
            language VARCHAR(10) DEFAULT 'malay',
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS flashcards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            note_id INT NOT NULL,
            subject_id INT NOT NULL,
            soalan TEXT NOT NULL,
            jawapan TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    // Upgrade dari versi lama — tambah kolum baru kalau belum ada
    try { $db->exec("ALTER TABLE notes ADD COLUMN raw_text LONGTEXT AFTER original_name"); } catch(Exception $e){}
    try { $db->exec("ALTER TABLE notes ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER language"); } catch(Exception $e){}
}
setupDatabase();

// ============================================================
//  HELPER: Extract PDF
// ============================================================
function extractPDF($path) {
    $out = @shell_exec("pdftotext " . escapeshellarg($path) . " - 2>/dev/null");
    if (!empty(trim($out ?? ''))) return trim($out);
    $content = @file_get_contents($path);
    if (!$content) return '';
    $teks = '';
    preg_match_all('/BT[\s\S]*?ET/', $content, $matches);
    foreach ($matches[0] as $block) {
        preg_match_all('/\(([^)]+)\)\s*T[jJ]/', $block, $strings);
        foreach ($strings[1] as $s) $teks .= ' ' . $s;
    }
    if (empty(trim($teks))) {
        preg_match_all('/\(([^\(\)]{3,})\)/', $content, $m);
        foreach ($m[1] as $s) if (preg_match('/[a-zA-Z]{2,}/', $s)) $teks .= ' ' . $s;
    }
    return trim(preg_replace('/\s+/', ' ', $teks));
}

// ============================================================
//  HELPER: Extract PPTX
// ============================================================
function extractPPTX($path) {
    if (!class_exists('ZipArchive')) return '';
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    $teks = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nama = $zip->getNameIndex($i);
        if (preg_match('/^ppt\/slides\/slide[0-9]+\.xml$/', $nama)) {
            $xml = $zip->getFromIndex($i);
            if ($xml) {
                $xml = preg_replace('/<a:rPr[^>]*>.*?<\/a:rPr>/s', '', $xml);
                $bersih = strip_tags(str_replace(['</a:t>','</a:p>'], [' ',"\n"], $xml));
                $teks .= $bersih . "\n";
            }
        }
    }
    $zip->close();
    return trim(preg_replace('/[ \t]+/', ' ', $teks));
}

// ============================================================
//  HELPER: Format Markdown → HTML (safe)
// ============================================================
function formatMD($t) {
    $t = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $t);
    $t = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $t);
    $t = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $t);
    $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
    $t = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $t);
    $t = preg_replace('/^[\*\-] (.+)$/m', '<li>$1</li>', $t);
    $t = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $t);
    $t = nl2br($t);
    return $t;
}

// Upload dir
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
