<div data-project-section class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                                    <span><x-ui-icon name="camera" class="inline-block align-middle" /> Baustellen-Fotos & Bestandsaufnahme</span>
                                    <span class="bg-blue-100 text-blue-800 text-[10px] px-2 py-0.5 rounded-full font-bold">
                                        Fotos wählbar (JPG, PNG)
                                    </span>
                                </h4>
                            </div>

                            <!-- Photo Upload Box -->
                            <div class="bg-slate-50 border border-dashed border-slate-300 rounded-2xl p-3.5 space-y-2.5 relative">
                                <div class="space-y-3">
                                    <div>
                                        <label for="project-photo-files" class="block text-sm font-semibold mb-2">Fotos auswählen</label>
                                        <input id="project-photo-files" type="file" wire:model="uploadPhotoFiles" accept="image/jpeg,image/png" multiple class="w-full text-sm bg-white border border-slate-300 rounded-lg p-2">
                                        @error('uploadPhotoFiles.*')<p class="text-sm text-rose-700 mt-1" role="alert">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="project-photo-category" class="block text-sm font-semibold mb-2">Foto-Kategorie</label>
                                        <select id="project-photo-category" wire:model="photoCategory" class="w-full bg-white border border-slate-300 rounded-lg p-2">
                                            <option value="bestandsaufnahme">Bestandsaufnahme</option>
                                            <option value="fortschritt">Baufortschritt</option>
                                            <option value="mangel">Mangel</option>
                                            <option value="abnahme">Abnahme</option>
                                        </select>
                                    </div>

                                    @if(!empty($uploadPhotoFiles) && is_array($uploadPhotoFiles) && count($uploadPhotoFiles) > 0)
                                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                                            <span class="text-xs font-bold text-slate-700"><x-ui-icon name="camera" class="inline-block align-middle" /> {{ count($uploadPhotoFiles) }} Datei(en) ausgewählt</span>
                                            <button wire:click="uploadPhotos" wire:loading.attr="disabled" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition shrink-0 cursor-pointer">
                                                <span wire:loading.remove wire:target="uploadPhotos">Hochladen</span>
                                                <span wire:loading wire:target="uploadPhotos">Speichere...</span>
                                            </button>
                                        </div>
                                    @endif
                            </div>

                            <!-- Photos Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-80 overflow-y-auto pr-1">
                                @forelse ($proj->photos as $photo)
                                    <div class="relative group bg-slate-100 rounded-xl overflow-hidden border border-slate-200 aspect-square shadow-2xs">
                                        <a href="{{ asset('storage/' . $photo->photo_path) }}" target="_blank">
                                            <img src="{{ asset('storage/' . $photo->photo_path) }}" alt="{{ $photo->caption }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                        </a>

                                        <!-- Category Badge -->
                                        <span class="absolute top-1.5 left-1.5 bg-slate-900/80 backdrop-blur-xs text-white text-[9px] font-bold px-1.5 py-0.5 rounded shadow-2xs">
                                            @if($photo->category === 'bestandsaufnahme') <x-ui-icon name="document" class="inline-block align-middle" /> Bestand
                                            @elseif($photo->category === 'fortschritt') <x-ui-icon name="chart" class="inline-block align-middle" /> Fortschritt
                                            @elseif($photo->category === 'mangel') <x-ui-icon name="warning" class="inline-block align-middle" /> Mangel
                                            @else <x-ui-icon name="grid" class="inline-block align-middle" /> Abnahme
                                            @endif
                                        </span>

                                        <!-- Delete Button -->
                                        <button aria-label="Foto löschen" wire:click="deletePhoto('{{ $photo->id }}')"
                                                wire:confirm="Soll dieses Foto wirklich gelöscht werden?"
                                                class="absolute top-1.5 right-1.5 bg-rose-600/90 hover:bg-rose-700 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition shadow-xs cursor-pointer">
                                            ✕
                                        </button>

                                        @if ($photo->caption)
                                            <div class="absolute bottom-0 inset-x-0 bg-slate-950/75 text-white text-[10px] p-1.5 truncate">
                                                {{ $photo->caption }}
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="col-span-full py-8 text-center text-xs text-slate-500 italic bg-slate-50/50 border border-slate-200/60 rounded-xl">
                                        Keine Baustellen-Fotos vorhanden. Laden Sie Fotos der Bestandsaufnahme hoch!
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
