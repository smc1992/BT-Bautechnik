<div wire:click="loadChat('{{ $chatItem->id }}'); if (window.innerWidth < 1024) mobileSidebar = false;"
     class="group p-2.5 rounded-xl border transition-all duration-150 cursor-pointer relative flex flex-col gap-1 {{ $isActive ? 'bg-blue-600/15 text-white border-blue-500/60 shadow-xs' : 'bg-slate-900/50 text-slate-300 border-slate-800/80 hover:bg-slate-900 hover:border-slate-700 hover:text-white' }}">
    
    <div class="flex items-center justify-between gap-1 pr-12">
        <p class="font-bold text-xs truncate leading-snug flex-1 {{ $isActive ? 'text-cyan-300' : 'text-slate-200' }}">
            {{ $chatItem->title }}
        </p>

        <!-- Hover Actions: Rename & Delete -->
        <div class="absolute right-2 top-2 flex items-center gap-1 opacity-80 lg:opacity-0 group-hover:opacity-100 transition">
            <!-- Rename Button -->
            <button wire:click.stop="openRenameModal('{{ $chatItem->id }}')" 
                    title="Unterhaltung umbenennen"
                    class="p-1 text-slate-400 hover:text-cyan-300 rounded hover:bg-slate-800 transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
            </button>

            <!-- Delete Button -->
            <button wire:click.stop="deleteChat('{{ $chatItem->id }}')" 
                    title="Unterhaltung löschen"
                    class="p-1 text-slate-400 hover:text-rose-400 rounded hover:bg-slate-800 transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="flex items-center justify-between text-[10px] {{ $isActive ? 'text-blue-300' : 'text-slate-500' }}">
        <span>{{ $chatItem->updated_at->format('d.m. H:i') }} Uhr</span>
        <span class="font-semibold px-1.5 py-0.2 rounded {{ $isActive ? 'bg-blue-500/20 text-cyan-200 border border-blue-500/30' : 'bg-slate-800 text-slate-400' }}">
            {{ $chatItem->messages_count }} Msg
        </span>
    </div>
</div>
