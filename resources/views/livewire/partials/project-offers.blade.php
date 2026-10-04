<div data-project-section class="pt-4 border-t border-slate-200/80 space-y-3">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Verknüpfte Angebote & LV-Positionen</h4>
                            <button wire:click="openParseOffer" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition cursor-pointer">+ LV Angebot hochladen (PDF)</button>
                        </div>
                        <div class="space-y-2">
                            @forelse ($proj->offers as $offer)
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex justify-between items-center text-xs">
                                    <div>
                                        <p class="font-bold text-slate-900">{{ $offer->offer_number ?: 'Angebot' }} • {{ $offer->title }}</p>
                                        <p class="text-[10px] text-slate-500 font-medium">{{ $offer->sections->count() }} Abschnitte • {{ date('d.m.Y', strtotime($offer->created_at)) }}</p>
                                    </div>
                                    <p class="font-bold text-slate-900">{{ number_format($offer->total_net, 2, ',', '.') }} € netto</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500 italic">Keine verknüpften Angebote vorhanden.</p>
                            @endforelse
                        </div>
                    </div>
