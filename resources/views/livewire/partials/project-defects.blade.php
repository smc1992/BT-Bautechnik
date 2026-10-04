<div data-project-section class="pt-4 border-t border-slate-200/80 space-y-3">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider"><x-ui-icon name="warning" class="inline-block align-middle" /> Mängel & Restarbeiten dieser Baustelle</h4>
                                @if($proj->defects->count() > 0)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300">
                                        {{ $proj->defects->where('status', '!=', 'abgenommen')->count() }} Offen / {{ $proj->defects->count() }} Gesamt
                                    </span>
                                @endif
                            </div>
                            <button wire:click="openCreateDefectModal('{{ $proj->id }}')" class="text-xs font-bold text-amber-600 hover:text-amber-800 transition cursor-pointer flex items-center gap-1">
                                <span><x-ui-icon name="warning" class="inline-block align-middle" /></span> + Mangel erfassen
                            </button>
                        </div>

                        <div class="space-y-2">
                            @forelse ($proj->defects as $defect)
                                <div class="bg-amber-50/50 p-3 rounded-xl border border-amber-200/80 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 text-xs">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-extrabold text-slate-900">{{ $defect->title }}</span>
                                            @if($defect->status === 'abgenommen')
                                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[9px] font-bold">Abgenommen</span>
                                            @elseif($defect->status === 'behoben')
                                                <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-[9px] font-bold">Behoben</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-800 text-[9px] font-bold">Offen</span>
                                            @endif
                                            <span class="text-[10px] text-slate-500 font-medium"><x-ui-icon name="folder" class="inline-block align-middle" /> {{ $defect->location ?: 'Baustelle' }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600">{{ $defect->description }}</p>
                                        @if($defect->assignedContact)
                                            <p class="text-[10px] text-blue-700 font-bold"><x-ui-icon name="users" class="inline-block align-middle" /> Subunternehmer: {{ $defect->assignedContact->display_name }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-[10px] text-slate-500 block">Frist: {{ $defect->deadline ? date('d.m.Y', strtotime($defect->deadline)) : 'Keine Frist' }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="p-3 bg-emerald-50/60 border border-emerald-200/80 rounded-xl text-xs text-emerald-900 font-medium flex justify-between items-center">
                                    <span><x-ui-icon name="grid" class="inline-block align-middle" /> Für diese Baustelle sind aktuell keine Mängel registriert.</span>
                                    <button wire:click="openCreateDefectModal('{{ $proj->id }}')" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] transition shadow-2xs cursor-pointer">
                                        + Mangel eintragen
                                    </button>
                                </div>
                            @endforelse
                        </div>
                    </div>
