<div class="min-h-screen bg-slate-50 text-slate-900 py-8 px-4 sm:px-6 lg:px-8 font-sans">
    <div class="max-w-3xl mx-auto space-y-6">

        <!-- CI Header Banner with Official BT Bautechnik Logo -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="p-2 bg-slate-50 border border-slate-200 rounded-xl">
                    <x-application-logo class="h-12 w-auto object-contain" />
                </div>
                <div>
                    <h1 class="text-lg font-black text-slate-900 tracking-tight">Bautagebuch Freigabe & Prüfbestätigung</h1>
                    <p class="text-xs text-slate-500 font-medium">{{ $settings->company_name ?: 'BT Bautechnik UG (haftungsbeschränkt)' }} • VOB Bautagebuch</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-extrabold bg-blue-50 text-blue-700 border border-blue-200">
                <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                Digitales Signaturverfahren
            </span>
        </div>

        @if($share->status === 'approved' || $isSubmitted)
            <!-- Success State with Audit Trail -->
            <div class="bg-white border border-emerald-200 rounded-2xl p-8 shadow-sm space-y-6">
                <div class="text-center space-y-3">
                    <div class="w-16 h-16 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-full flex items-center justify-center mx-auto text-2xl font-bold">
                        ✓
                    </div>
                    <h2 class="text-2xl font-black text-slate-900">Bautagebuch rechtssicher freigegeben!</h2>
                    <p class="text-slate-600 text-sm max-w-md mx-auto">
                        Vielen Dank! Die digitale Bestätigung von <strong class="text-slate-900 font-bold">{{ $share->approver_name }}</strong> wurde protokolliert.
                    </p>
                </div>

                <!-- Audit Trail Certificate Box -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 space-y-4 text-xs font-mono">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <span class="font-bold text-slate-900 text-sm flex items-center gap-1.5">
                            <span>🛡️</span> AUDIT-TRAIL REGISTRIERUNG
                        </span>
                        <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-extrabold border border-emerald-200">VERIFIZIERT</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-slate-700">
                        <div>
                            <span class="text-slate-500 block font-sans text-[11px]">Prüfer / Freigebender:</span>
                            <strong class="text-slate-900">{{ $share->approver_name }}</strong> ({{ $share->approver_role }})
                        </div>
                        <div>
                            <span class="text-slate-500 block font-sans text-[11px]">E-Mail Verifizierung:</span>
                            <span class="text-emerald-700 font-bold">✓ 6-Stelliger Sicherheitscode verifiziert</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block font-sans text-[11px]">Zeitstempel (UTC):</span>
                            <span class="text-slate-900 font-bold">{{ $share->approved_at?->format('d.m.Y H:i:s') }} Uhr</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block font-sans text-[11px]">Client IP-Adresse:</span>
                            <span class="text-slate-900 font-bold">{{ $share->client_ip ?: 'Erfasst' }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-200 space-y-1">
                        <span class="text-slate-500 block font-sans text-[11px]">Kryptografischer Dokumenten-Hash (SHA-256):</span>
                        <div class="bg-white p-2.5 rounded text-slate-700 break-all select-all font-mono text-[11px] border border-slate-200">
                            {{ $share->sha256_hash ?: hash('sha256', $share->share_token) }}
                        </div>
                    </div>
                </div>
            </div>
        @elseif($share->status === 'rejected' || $isRejected)
            <!-- Rejected State -->
            <div class="bg-white border border-red-200 rounded-2xl p-8 text-center space-y-4 shadow-sm">
                <div class="w-16 h-16 bg-red-50 text-red-600 border border-red-200 rounded-full flex items-center justify-center mx-auto text-2xl font-bold">
                    ✕
                </div>
                <h2 class="text-2xl font-black text-slate-900">Freigabe abgelehnt</h2>
                <p class="text-slate-600 text-sm max-w-md mx-auto">
                    Die Ablehnung durch <strong class="text-slate-900">{{ $share->approver_name }}</strong> wurde protokolliert.
                </p>
                <div class="bg-slate-50 p-4 rounded-xl text-left border border-slate-200 text-sm text-slate-800">
                    <span class="text-xs text-slate-500 block mb-1">Grund der Ablehnung:</span>
                    {{ $share->rejection_reason }}
                </div>
            </div>
        @else
            <!-- Bautagebuch Details Card in Official CI Design -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-6">

                <!-- Meta Details -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200/80 text-sm">
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase block">Baustelle</span>
                        <strong class="text-slate-900 font-extrabold">{{ $project->name ?? 'Baustelle' }}</strong>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase block">Datum</span>
                        <strong class="text-blue-700 font-extrabold">{{ \Carbon\Carbon::parse($log->date)->format('d.m.Y') }}</strong>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase block">Witterung / Temp.</span>
                        <span class="text-slate-800 font-semibold">{{ $log->weather ?: 'Normal' }} @if($log->temperature)({{ $log->temperature }}°C)@endif</span>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase block">Personal</span>
                        <span class="text-slate-800 font-semibold">{{ $log->workers_count ?: 0 }} Mitarbeiter</span>
                    </div>
                </div>

                <!-- Ausgeführte Arbeiten -->
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Ausgeführte Arbeiten & Gewerk</h3>
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-slate-800 text-sm leading-relaxed whitespace-pre-line">
                        {{ $log->work_performed ?: 'Keine Einträge.' }}
                    </div>
                </div>

                @if($log->special_occurrences)
                <!-- Besondere Vorkommnisse -->
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-amber-700">Besondere Vorkommnisse / Störungen</h3>
                    <div class="p-4 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-sm leading-relaxed whitespace-pre-line">
                        {{ $log->special_occurrences }}
                    </div>
                </div>
                @endif

                <!-- Digital Approval & 2FA Form -->
                <div class="border-t border-slate-200 pt-6 space-y-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>🛡️</span> Digitales Signaturverfahren
                        </h3>
                        <span class="text-xs text-slate-500 font-medium">Rolle: <strong class="text-blue-700 font-bold">{{ $share->approver_role }}</strong></span>
                    </div>

                    @if(!$isPinVerified)
                        <!-- STEP 1: E-Mail PIN Verification -->
                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-4">
                            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <span>1.</span> 📩 E-Mail-Sicherheitscode anfordern
                            </h4>
                            <p class="text-xs text-slate-600">
                                Geben Sie Ihren Namen und Ihre E-Mail-Adresse ein. Sie erhalten umgehend einen 6-stelligen Sicherheitscode zur Bestätigung Ihrer Identität.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ihr Vor- & Nachname *</label>
                                    <input type="text" wire:model="approverName" placeholder="z.B. Dipl.-Ing. Julia Weber" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-slate-900 text-xs focus:border-blue-600">
                                    @error('approverName') <span class="text-xs text-red-600 block mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ihre E-Mail-Adresse *</label>
                                    <input type="email" wire:model="approverEmail" placeholder="name@architekturbuero.de" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-slate-900 text-xs focus:border-blue-600">
                                    @error('approverEmail') <span class="text-xs text-red-600 block mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            @if(!$pinSent)
                                <button type="button" wire:click="sendSecurityPin" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/10 transition flex items-center justify-center gap-2">
                                    <span>📩</span> Sicherheitscode jetzt per E-Mail anfordern
                                </button>
                            @else
                                <div class="bg-blue-50 border border-blue-200 p-4 rounded-xl space-y-3">
                                    <span class="text-xs text-blue-900 font-bold block">{{ $pinMessage }}</span>
                                    
                                    <div class="flex items-center gap-2">
                                        <input type="text" wire:model="inputPin" placeholder="6-stelliger Code (z.B. 849201)" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-slate-900 text-xs font-mono text-center tracking-widest focus:border-blue-600">
                                        <button type="button" wire:click="verifySecurityPin" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl whitespace-nowrap shadow-sm">
                                            Code prüfen ✓
                                        </button>
                                    </div>
                                    @error('inputPin') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>
                    @else
                        <!-- STEP 2: Digital Touch Signature Pad -->
                        <div class="bg-slate-50 p-5 rounded-2xl border border-emerald-300 space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-bold text-emerald-900 flex items-center gap-2">
                                    <span>✓ 2. E-Mail verifiziert</span> • Digitale Touch-Unterschrift
                                </h4>
                                <span class="text-xs text-emerald-800 font-mono font-bold">2FA VERIFIZIERT</span>
                            </div>

                            <!-- Canvas Signature Pad -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-bold text-slate-700 uppercase">Unterschrift (mit Finger oder Maus zeichnen) *</label>
                                    <button type="button" onclick="clearSignature()" class="text-xs text-slate-500 hover:text-slate-900 underline">
                                        Zurücksetzen
                                    </button>
                                </div>
                                <div class="bg-white border-2 border-dashed border-slate-300 rounded-xl overflow-hidden relative shadow-inner">
                                    <canvas id="signatureCanvas" class="w-full h-40 touch-none cursor-crosshair"></canvas>
                                    <div id="signaturePlaceholder" class="absolute inset-0 flex items-center justify-center pointer-events-none text-slate-400 text-xs font-medium">
                                        Hier unterschreiben...
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
                                <button type="button" onclick="submitApproval()" class="w-full sm:flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-6 rounded-xl shadow-md shadow-emerald-500/10 transition flex items-center justify-center gap-2 text-sm">
                                    <span>🛡️</span> Bautagebuch jetzt freigeben
                                </button>
                                
                                <button type="button" x-data="{ showReject: false }" @click="showReject = !showReject" class="w-full sm:w-auto bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-3 px-4 rounded-xl text-xs transition">
                                    Ablehnen...
                                </button>
                            </div>
                        </div>
                    @endif
                </div>

            </div>
        @endif

    </div>

    <!-- Interactive Canvas Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('signatureCanvas');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const placeholder = document.getElementById('signaturePlaceholder');
            let isDrawing = false;
            let hasDrawn = false;

            function resizeCanvas() {
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width;
                canvas.height = rect.height;
                ctx.strokeStyle = '#2563eb'; // BT Bautechnik Corporate Brand Blue
                ctx.lineWidth = 3;
                ctx.lineCap = 'round';
            }

            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            function startDraw(e) {
                isDrawing = true;
                hasDrawn = true;
                if (placeholder) placeholder.style.display = 'none';
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
            }

            function draw(e) {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
            }

            function stopDraw() {
                isDrawing = false;
            }

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDraw);
            canvas.addEventListener('mouseleave', stopDraw);

            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', stopDraw);

            window.clearSignature = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
                if (placeholder) placeholder.style.display = 'flex';
            };

            window.submitApproval = function() {
                if (!hasDrawn) {
                    alert('Bitte zeichnen Sie Ihre Unterschrift vor der Freigabe.');
                    return;
                }
                const signatureBase64 = canvas.toDataURL('image/png');
                @this.call('approveWithSignature', signatureBase64);
            };
        });
    </script>
</div>
