<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
initDb(); sessionStart(); requireAdmin();

$pageTitle = 'Import automat';

ob_start();
?>
<div class="max-w-3xl mx-auto" x-data="importer()" x-init="checkStatus()">

  <div class="flex items-center gap-3 mb-6">
    <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
      </svg>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Import automat asociații</h1>
      <p class="text-sm text-gray-500">Registrul Național ONG – Ministerul Justiției (115.553 înregistrări)</p>
    </div>
  </div>

  <!-- STATUS CARD -->
  <div class="bg-white rounded-2xl shadow-sm p-6 mb-5">
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="text-center">
        <p class="text-3xl font-bold text-indigo-600" x-text="fmt(dbCount)">–</p>
        <p class="text-xs text-gray-500 mt-1">Asociații în DB</p>
      </div>
      <div class="text-center">
        <p class="text-3xl font-bold text-gray-800" x-text="fmt(totalRecords)">–</p>
        <p class="text-xs text-gray-500 mt-1">Total disponibile</p>
      </div>
      <div class="text-center">
        <p class="text-3xl font-bold" :class="errorCount > 0 ? 'text-red-500' : 'text-green-500'" x-text="fmt(errorCount)">0</p>
        <p class="text-xs text-gray-500 mt-1">Erori</p>
      </div>
    </div>

    <!-- PROGRESS BAR -->
    <div class="mb-4">
      <div class="flex justify-between text-xs text-gray-500 mb-1">
        <span x-text="progressLabel">Pregătire…</span>
        <span x-text="pct + '%'">0%</span>
      </div>
      <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
        <div class="h-3 rounded-full transition-all duration-300"
             :class="state === 'done' ? 'bg-green-500' : (state === 'error' ? 'bg-red-400' : 'bg-indigo-500')"
             :style="'width:' + pct + '%'"></div>
      </div>
    </div>

    <!-- LOG -->
    <div x-show="log.length > 0"
         class="bg-gray-50 rounded-xl p-3 text-xs font-mono text-gray-600 max-h-36 overflow-y-auto space-y-0.5 mb-4">
      <template x-for="line in log.slice().reverse()" :key="line">
        <div x-text="line"></div>
      </template>
    </div>

    <!-- BUTOANE -->
    <div class="flex gap-3 flex-wrap">

      <!-- Dacă ZIP-ul nu e extras -->
      <template x-if="!extracted && state !== 'extracting'">
        <button @click="setup()"
          class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-5 py-2.5 rounded-xl transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          Pregătește datele (extrage ZIP)
        </button>
      </template>

      <template x-if="extracted && state === 'idle'">
        <button @click="startImport()"
          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-xl transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Pornește importul
        </button>
      </template>

      <template x-if="state === 'importing'">
        <button @click="stopImport()"
          class="flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white text-sm font-medium px-5 py-2.5 rounded-xl transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"/>
          </svg>
          Oprește
        </button>
      </template>

      <template x-if="state === 'done'">
        <button @click="cleanup()"
          class="flex items-center gap-2 border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-xl transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
          Șterge fișierele temporare
        </button>
      </template>

      <!-- Buton resetare progres (disponibil oricând când nu rulează) -->
      <template x-if="state !== 'importing' && state !== 'extracting' && currentChunk > 0 && !done">
        <button @click="resetProgress()"
          class="flex items-center gap-2 border border-gray-200 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-sm font-medium px-4 py-2.5 rounded-xl transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
          </svg>
          Restart de la 0
        </button>
      </template>

      <template x-if="state === 'paused'">
        <button @click="startImport()"
          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-xl transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
          </svg>
          Continuă importul
        </button>
      </template>

      <a href="/admin/associations.php"
         class="flex items-center gap-2 border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-xl transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
        </svg>
        Vezi asociații
      </a>
    </div>
  </div>

  <!-- INFO CARD -->
  <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5 text-sm text-blue-700">
    <p class="font-semibold mb-2">Cum funcționează importul automat</p>
    <ol class="space-y-1 list-decimal list-inside text-blue-600">
      <li><strong>Extrage ZIP</strong> – datele sunt pregătite în 116 fișiere de câte ~1.000 înregistrări fiecare</li>
      <li><strong>Pornește importul</strong> – JavaScript trimite automat cereri AJAX pentru fiecare grup</li>
      <li><strong>Urmărești progresul</strong> în timp real prin bara de progres de mai sus</li>
      <li>Poți <strong>opri și relua</strong> oricând – importul continuă de unde a rămas</li>
      <li>La final, șterge fișierele temporare pentru a elibera spațiu</li>
    </ol>
    <p class="mt-3 text-blue-500 text-xs">Sursa datelor: Registrul Național al Asociațiilor și Fundațiilor – Ministerul Justiției / data.gov.ro</p>
  </div>

</div>

<script>
function importer() {
  return {
    state:        'idle',   // idle | extracting | importing | paused | done | error
    extracted:    false,
    totalChunks:  116,
    totalRecords: 115553,
    currentChunk: 0,
    dbCount:      0,
    importedSession: 0,
    errorCount:   0,
    log:          [],
    running:      false,

    get pct() {
      if (this.totalChunks === 0) return 0;
      return Math.min(100, Math.round(this.currentChunk / this.totalChunks * 100));
    },

    get progressLabel() {
      if (this.state === 'idle')       return 'Așteptare…';
      if (this.state === 'extracting') return 'Se extrage ZIP-ul…';
      if (this.state === 'done')       return '✓ Import finalizat!';
      if (this.state === 'error')      return '✗ Eroare – verifică log-ul';
      if (this.state === 'paused')     return `Oprit la chunk ${this.currentChunk}/${this.totalChunks}`;
      return `Chunk ${this.currentChunk} / ${this.totalChunks} – ${this.importedSession.toLocaleString()} importate în sesiunea curentă`;
    },

    fmt(n) { return Number(n).toLocaleString('ro-RO'); },

    addLog(msg) {
      const ts = new Date().toLocaleTimeString('ro-RO');
      this.log.push(`[${ts}] ${msg}`);
      if (this.log.length > 200) this.log.shift();
    },

    async checkStatus() {
      try {
        const r = await fetch('/admin/import_batch.php?action=status');
        const d = await r.json();
        this.dbCount      = d.db_count      || 0;
        this.extracted    = d.extracted     || false;
        this.totalChunks  = d.total_chunks  || 116;
        this.totalRecords = d.total_records || 115553;

        // Restaurăm progresul din sesiunea anterioară
        if (d.last_chunk > 0) {
          this.currentChunk = d.last_chunk;
        }
        if (d.done) {
          this.state = 'done';
          this.addLog(`✓ Import finalizat anterior. ${this.fmt(this.dbCount)} înregistrări în DB.`);
        } else if (d.last_chunk > 0 && this.extracted) {
          this.state = 'paused';
          this.addLog(`↩ Importul anterior s-a oprit la chunk ${d.last_chunk}/${this.totalChunks}. Apasă "Continuă importul".`);
        } else if (this.extracted) {
          this.addLog(`Date extrase. ${this.fmt(this.dbCount)} înregistrări în DB. Apasă "Pornește importul".`);
        } else {
          this.addLog('ZIP-ul nu este extras încă. Apasă "Pregătește datele".');
        }
      } catch(e) {
        this.addLog('Eroare verificare status: ' + e.message);
      }
    },

    async setup() {
      this.state = 'extracting';
      this.addLog('Se extrage ZIP-ul asociatii_chunks.zip…');
      try {
        const r = await fetch('/admin/import_batch.php?action=setup');
        const d = await r.json();
        if (d.success) {
          this.extracted    = true;
          this.totalChunks  = d.total_chunks;
          this.totalRecords = d.total_records;
          this.state = 'idle';
          this.addLog('✓ ' + d.message);
        } else {
          this.state = 'error';
          this.addLog('✗ ' + d.message);
        }
      } catch(e) {
        this.state = 'error';
        this.addLog('Eroare extragere: ' + e.message);
      }
    },

    async startImport() {
      this.running = true;
      this.state   = 'importing';
      this.addLog(`▶ Pornesc de la chunk ${this.currentChunk}…`);
      this.runBatch();
    },

    stopImport() {
      this.running = false;
      this.state   = 'paused';
      this.addLog(`⏸ Oprit la chunk ${this.currentChunk}.`);
    },

    async runBatch() {
      if (!this.running) return;

      try {
        const url = `/admin/import_batch.php?action=import&chunk=${this.currentChunk}`;
        const r   = await fetch(url);
        const d   = await r.json();

        if (!d.success) {
          this.state   = 'error';
          this.running = false;
          this.addLog('✗ Chunk ' + this.currentChunk + ': ' + (d.message || 'Eroare necunoscută'));
          return;
        }

        this.importedSession += (d.imported || 0);
        this.errorCount      += (d.errors   || 0);

        // Afișăm detalii erori dacă există
        if (d.errors > 0 && d.error_msgs && d.error_msgs.length > 0) {
          d.error_msgs.forEach(msg => this.addLog('⚠ EROARE: ' + msg));
        }

        // Actualizăm contorul din DB la fiecare 10 chunk-uri
        if (this.currentChunk % 10 === 0) {
          fetch('/admin/import_batch.php?action=status')
            .then(x => x.json())
            .then(s => { this.dbCount = s.db_count || this.dbCount; });
        }

        if (d.done) {
          this.currentChunk = this.totalChunks;
          this.state        = 'done';
          this.running      = false;
          // Refresh final count
          const s = await (await fetch('/admin/import_batch.php?action=status')).json();
          this.dbCount = s.db_count;
          this.addLog(`✓ Import complet! ${this.fmt(this.dbCount)} asociații în baza de date.`);
          return;
        }

        this.currentChunk = d.next_chunk;
        this.addLog(`Chunk ${d.chunk + 1}/${this.totalChunks}: ${d.imported} importate, ${d.skipped} sărite`);

        // Mică pauză pentru a nu supraîncărca serverul
        setTimeout(() => this.runBatch(), 300);

      } catch(e) {
        // Retry automat la erori de rețea (max 3 ori)
        this._retries = (this._retries || 0) + 1;
        if (this._retries <= 3) {
          this.addLog(`⚠ Retry ${this._retries}/3 pentru chunk ${this.currentChunk}…`);
          setTimeout(() => this.runBatch(), 2000);
        } else {
          this._retries = 0;
          this.state    = 'error';
          this.running  = false;
          this.addLog('✗ Eroare de rețea: ' + e.message);
        }
      }
    },

    async cleanup() {
      try {
        const r = await fetch('/admin/import_batch.php?action=cleanup');
        const d = await r.json();
        this.extracted = false;
        this.addLog('🗑 ' + (d.message || 'Curățat.'));
      } catch(e) {
        this.addLog('Eroare cleanup: ' + e.message);
      }
    },

    async resetProgress() {
      if (!confirm('Resetezi progresul și importul va reîncepe de la chunk 0. Înregistrările existente nu se șterg. Continui?')) return;
      try {
        await fetch('/admin/import_batch.php?action=reset_progress');
        this.currentChunk    = 0;
        this.importedSession = 0;
        this.errorCount      = 0;
        this.state           = 'idle';
        this.addLog('↺ Progresul a fost resetat. Importul va reîncepe de la chunk 0.');
      } catch(e) {
        this.addLog('Eroare reset: ' + e.message);
      }
    }
  };
}
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/_layout.php';
