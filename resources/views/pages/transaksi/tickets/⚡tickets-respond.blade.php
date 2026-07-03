<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Services\TicketLog;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?int $ticketId = null;

    public string $ticketNo    = '';
    public string $ticketTitle = '';
    public string $statusName  = '';
    public string $statusColor = '#6b7280';
    public string $handlerName     = '';  // yang mengerjakan (technician)
    public string $firstResponder  = '';  // yang merespon pertama
    public string $firstRespondAt  = '';

    public string $newComment = '';

    // Panel penanganan (khusus petugas): update status & solusi.
    public string $updStatus     = '';
    public string $updResolution = '';

    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public function mount(): void { $this->registerAreas(['modal']); }

    public function isPetugas(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'manager', 'teknisi']);
    }

    #[Computed]
    public function statusOptions(): array
    {
        return DB::table('ticket_statuses')->where('is_active', 1)->orderBy('sort_order')->pluck('name', 'id')->all();
    }

    /** Muat ulang info header tiket (status, pengerjaan, respon pertama). */
    private function loadHeader(): void
    {
        $t = DB::table('tickets as t')
            ->leftJoin('ticket_statuses as s', 's.id', '=', 't.status_id')
            ->leftJoin('users as u', 'u.id', '=', 't.technician_id')
            ->leftJoin('users as fr', 'fr.id', '=', 't.first_responded_by')
            ->where('t.id', $this->ticketId)
            ->select('t.ticket_no', 't.title', 't.status_id', 't.resolution',
                's.name as status', 's.color as status_color',
                'u.name as handler', 'fr.name as first_resp',
                DB::raw("TO_CHAR(t.first_responded_at, 'dd/mm/yyyy HH24:MI') as first_resp_at"))
            ->first();

        if (!$t) return;

        $this->ticketNo       = (string) $t->ticket_no;
        $this->ticketTitle    = (string) $t->title;
        $this->statusName     = (string) ($t->status ?? '-');
        $this->statusColor    = (string) ($t->status_color ?? '#6b7280');
        $this->handlerName    = (string) ($t->handler ?? '');
        $this->firstResponder = (string) ($t->first_resp ?? '');
        $this->firstRespondAt = (string) ($t->first_resp_at ?? '');
        $this->updStatus      = $t->status_id ? (string) $t->status_id : '';
        $this->updResolution  = (string) ($t->resolution ?? '');
    }

    #[On('tickets.openRespond')]
    public function openRespond(int $id): void
    {
        if (!DB::table('tickets')->where('id', $id)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Tiket tidak ditemukan.');
            return;
        }

        $this->ticketId   = $id;
        $this->newComment = '';
        $this->loadHeader();
        $this->resetValidation();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'tickets-respond');
        $this->dispatch('focus-comment');
    }

    /** Format durasi singkat (mnt/jam/hari). */
    private function humanDur(int $s): string
    {
        if ($s < 60)   return $s . ' dtk';
        $m = intdiv($s, 60);
        if ($m < 60)   return $m . ' mnt';
        $h = intdiv($m, 60); $mm = $m % 60;
        if ($h < 24)   return $h . 'j' . ($mm ? ' ' . $mm . 'm' : '');
        $d = intdiv($h, 24); $hh = $h % 24;
        return $d . 'h' . ($hh ? ' ' . $hh . 'j' : '');
    }

    /** Ringkasan waktu: dibuat, respon pertama, penyelesaian. */
    #[Computed]
    public function stats(): array
    {
        if (!$this->ticketId) return [];
        $t = DB::table('tickets')->where('id', $this->ticketId)->first();
        $created  = $t && $t->reported_at ? Carbon::parse($t->reported_at) : null;
        $resolved = $t && $t->resolved_at ? Carbon::parse($t->resolved_at) : null;
        $firstC   = DB::table('ticket_comments')->where('ticket_id', $this->ticketId)->orderBy('created_at')->orderBy('id')->value('created_at');
        $firstC   = $firstC ? Carbon::parse($firstC) : null;

        return [
            'created'      => $created?->format('d/m/Y H:i') ?? '-',
            'first'        => $firstC?->format('d/m/Y H:i'),
            'first_delta'  => ($created && $firstC) ? $this->humanDur((int) $created->diffInSeconds($firstC)) : null,
            'resolved'     => $resolved?->format('d/m/Y H:i'),
            'res_delta'    => ($created && $resolved) ? $this->humanDur((int) $created->diffInSeconds($resolved)) : null,
        ];
    }

    /** Timeline gabungan: aktivitas (status/assign) + balasan, urut waktu. */
    #[Computed]
    public function timeline(): array
    {
        if (!$this->ticketId) return [];

        $statusColors = DB::table('ticket_statuses')->pluck('color', 'name')->all();

        $rows = [];

        $acts = DB::table('ticket_activities as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.ticket_id', $this->ticketId)
            ->select('a.action', 'a.from_value', 'a.to_value', 'a.note', 'a.user_id',
                'u.name as user_name', DB::raw("TO_CHAR(a.created_at, 'YYYY-MM-DD HH24:MI:SS') as at"))
            ->get();
        foreach ($acts as $a) {
            $rows[] = ['kind' => 'act', 'action' => $a->action, 'from' => $a->from_value, 'to' => $a->to_value,
                'from_color' => $statusColors[$a->from_value] ?? '#6b7280',
                'to_color'   => $statusColors[$a->to_value] ?? '#6b7280',
                'note' => $a->note, 'user' => $a->user_name, 'at' => $a->at];
        }

        $cmts = DB::table('ticket_comments as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.ticket_id', $this->ticketId)
            ->select('c.id', 'c.comment', 'c.user_id', 'u.name as user_name',
                DB::raw("TO_CHAR(c.created_at, 'YYYY-MM-DD HH24:MI:SS') as at"))
            ->get();
        foreach ($cmts as $c) {
            $rows[] = ['kind' => 'comment', 'id' => $c->id, 'content' => $c->comment,
                'user_id' => $c->user_id, 'user' => $c->user_name, 'at' => $c->at];
        }

        usort($rows, fn($x, $y) => strcmp((string) $x['at'], (string) $y['at']));

        $prev = null;
        foreach ($rows as &$r) {
            $c = Carbon::parse($r['at']);
            $r['at_fmt'] = $c->format('d/m/Y H:i');
            $r['delta']  = $prev ? '+' . $this->humanDur((int) $prev->diffInSeconds($c)) : null;
            $prev = $c;
        }

        return $rows;
    }

    public function addComment(): void
    {
        $this->validate(
            ['newComment' => 'required|string|max:2000'],
            ['newComment.required' => 'Respon tidak boleh kosong.'],
            ['newComment' => 'Respon'],
        );

        if (!$this->ticketId) return;

        $uid = auth()->id();
        $ticket = DB::table('tickets')->where('id', $this->ticketId)->first();

        // 1. Simpan respon — siapa yang merespon tercatat di ticket_comments.user_id.
        DB::table('ticket_comments')->insert([
            'ticket_id'  => $this->ticketId,
            'user_id'    => $uid,
            'comment'    => trim($this->newComment),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Catat respon pertama + auto-assign pengerjaan ke teknisi yang merespon.
        $updates = [];

        if (empty($ticket->first_responded_at)) {
            $updates['first_responded_by'] = $uid;
            $updates['first_responded_at'] = now();
        }

        $isPetugas = auth()->user()?->hasAnyRole(['admin', 'manager', 'teknisi']);
        if ($isPetugas && empty($ticket->technician_id)) {
            $updates['technician_id'] = $uid;
            TicketLog::log($this->ticketId, 'assigned', null, auth()->user()->name, 'Mulai mengerjakan (via respon)');
        }

        if (!empty($updates)) {
            $updates['updated_at'] = now();
            DB::table('tickets')->where('id', $this->ticketId)->update($updates);
            $this->dispatch('tickets.saved');   // refresh kolom Teknisi di list
        }

        $this->newComment = '';
        $this->loadHeader();
        unset($this->timeline, $this->stats);
        $this->dispatch('toast', type: 'success', message: 'Respon ditambahkan.');
        $this->dispatch('focus-comment');
    }

    /** Petugas: update status + solusi/tindakan langsung dari modal Respon. */
    public function savePenanganan(): void
    {
        if (!$this->isPetugas()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya petugas yang bisa update status/solusi.');
            return;
        }

        $this->validate(
            ['updStatus' => 'required|integer|exists:ticket_statuses,id', 'updResolution' => 'nullable|string|max:2000'],
            ['updStatus.required' => 'Status wajib dipilih.'],
            ['updStatus' => 'Status', 'updResolution' => 'Solusi/Tindakan'],
        );

        if (!$this->ticketId) return;

        $ticket    = DB::table('tickets')->where('id', $this->ticketId)->first();
        $newStatus = (int) $this->updStatus;
        $isClosed  = (int) DB::table('ticket_statuses')->where('id', $newStatus)->value('is_closed');

        $updates = [
            'resolution' => $this->updResolution !== '' ? trim($this->updResolution) : null,
            'updated_at' => now(),
        ];

        if ((int) $ticket->status_id !== $newStatus) {
            $updates['status_id']   = $newStatus;
            $updates['resolved_at'] = $isClosed ? ($ticket->resolved_at ?: now()) : null;
            TicketLog::log(
                $this->ticketId, 'status_changed',
                DB::table('ticket_statuses')->where('id', $ticket->status_id)->value('name'),
                DB::table('ticket_statuses')->where('id', $newStatus)->value('name'),
            );
        }

        // Petugas yang menangani jadi pengerja kalau belum ada.
        if (empty($ticket->technician_id)) {
            $updates['technician_id'] = auth()->id();
            TicketLog::log($this->ticketId, 'assigned', null, auth()->user()->name, 'Mulai mengerjakan (via penanganan)');
        }

        DB::table('tickets')->where('id', $this->ticketId)->update($updates);

        $this->loadHeader();
        unset($this->timeline, $this->stats);
        $this->dispatch('tickets.saved');
        $this->dispatch('toast', type: 'success', message: 'Penanganan diperbarui.');
    }

    public function deleteComment(int $commentId): void
    {
        $c = DB::table('ticket_comments')->where('id', $commentId)->first();
        if (!$c) return;

        if ($c->user_id !== auth()->id() && !auth()->user()?->hasRole('admin')) {
            $this->dispatch('toast', type: 'error', message: 'Tidak boleh menghapus respon orang lain.');
            return;
        }

        DB::table('ticket_comments')->where('id', $commentId)->delete();
        unset($this->timeline, $this->stats);
        $this->dispatch('toast', type: 'success', message: 'Respon dihapus.');
    }

    public function closeModal(): void
    {
        $this->reset(['ticketId', 'ticketNo', 'ticketTitle', 'statusName', 'newComment', 'handlerName', 'firstResponder', 'firstRespondAt', 'updStatus', 'updResolution']);
        $this->statusColor = '#6b7280';
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'tickets-respond');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="tickets-respond" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$ticketId ?? 'none']) }}">

            {{-- HEADER --}}
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-semibold text-brand-green dark:text-brand-lime">{{ $ticketNo }}</span>
                            @if ($statusName)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-white"
                                      style="background: {{ $statusColor }}">{{ $statusName }}</span>
                            @endif
                        </div>
                        <h2 class="mt-1 text-lg font-semibold text-ink dark:text-gray-100">{{ $ticketTitle }}</h2>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-xs">
                            <span class="text-muted dark:text-gray-400">
                                🔧 Dikerjakan:
                                <span class="font-semibold text-ink dark:text-gray-100">{{ $handlerName ?: 'Belum ditugaskan' }}</span>
                            </span>
                            <span class="text-muted dark:text-gray-400">
                                💬 Respon pertama:
                                <span class="font-semibold text-ink dark:text-gray-100">{{ $firstResponder ?: '—' }}</span>
                                @if ($firstRespondAt) <span class="text-muted-soft">({{ $firstRespondAt }})</span> @endif
                            </span>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>

                {{-- RINGKASAN WAKTU --}}
                @php $s = $this->stats; @endphp
                <div class="grid grid-cols-3 gap-2 mt-3">
                    <div class="px-3 py-2 rounded-lg bg-surface-soft dark:bg-gray-800">
                        <p class="text-[10px] uppercase tracking-wide text-muted-soft">Dibuat</p>
                        <p class="text-xs font-semibold text-body dark:text-gray-200">{{ $s['created'] ?? '-' }}</p>
                    </div>
                    <div class="px-3 py-2 rounded-lg bg-surface-soft dark:bg-gray-800">
                        <p class="text-[10px] uppercase tracking-wide text-muted-soft">Respon Pertama</p>
                        <p class="text-xs font-semibold text-body dark:text-gray-200">
                            {{ $s['first'] ?? '—' }}
                            @if (!empty($s['first_delta'])) <span class="text-brand-green dark:text-brand-lime">(+{{ $s['first_delta'] }})</span> @endif
                        </p>
                    </div>
                    <div class="px-3 py-2 rounded-lg bg-surface-soft dark:bg-gray-800">
                        <p class="text-[10px] uppercase tracking-wide text-muted-soft">Selesai</p>
                        <p class="text-xs font-semibold text-body dark:text-gray-200">
                            {{ $s['resolved'] ?? '—' }}
                            @if (!empty($s['res_delta'])) <span class="text-emerald-600 dark:text-emerald-400">(total {{ $s['res_delta'] }})</span> @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- BODY: 2 kolom — KIRI Progress & Status, KANAN Chat & Respon --}}
            <div class="flex-1 flex flex-col lg:flex-row min-h-0">

                {{-- ╔═══ KIRI: PROGRESS & STATUS ═══╗ --}}
                <div class="flex flex-col min-h-0 lg:w-1/2 border-b lg:border-b-0 lg:border-r border-hairline dark:border-gray-700">
                    <div class="px-6 py-2.5 border-b border-hairline bg-surface-soft dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-ink dark:text-gray-100">Progress & Status</h3>
                    </div>

                    {{-- list kejadian sistem --}}
                    <div class="flex-1 px-6 py-4 space-y-3 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                        @php $acts = array_values(array_filter($this->timeline, fn($e) => $e['kind'] === 'act')); @endphp
                        @forelse ($acts as $e)
                            @php
                                $meta = match ($e['action']) {
                                    'created'        => ['chip' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',       'tag' => 'DIBUAT'],
                                    'status_changed' => ['chip' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',   'tag' => 'STATUS'],
                                    'assigned'       => ['chip' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300', 'tag' => 'ASSIGN'],
                                    default          => ['chip' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',       'tag' => 'INFO'],
                                };
                            @endphp
                            <div class="p-3 rounded-xl border border-dashed border-hairline dark:border-gray-700" wire:key="tl-a-{{ $e['at'] }}-{{ $loop->index }}">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide {{ $meta['chip'] }}">{{ $meta['tag'] }}</span>
                                    @if ($e['action'] === 'status_changed')
                                        @if ($e['from'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-white" style="background: {{ $e['from_color'] }}">{{ $e['from'] }}</span>
                                        @endif
                                        <svg class="w-4 h-4 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold text-white ring-2 ring-offset-1 ring-amber-300" style="background: {{ $e['to_color'] }}">{{ $e['to'] }}</span>
                                    @elseif ($e['action'] === 'assigned')
                                        <span class="text-sm text-body dark:text-gray-300">{{ $e['to'] ? 'Ditugaskan ke' : 'Penugasan dilepas' }}</span>
                                        @if ($e['to'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-200">{{ $e['to'] }}</span>
                                        @endif
                                    @else
                                        <span class="text-sm text-body dark:text-gray-300">Tiket dibuat</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t border-hairline-soft dark:border-gray-800">
                                    <span class="text-[11px] text-muted-soft">{{ $e['at_fmt'] }} @if ($e['delta']) · {{ $e['delta'] }} @endif</span>
                                    @if ($e['user']) <span class="text-[11px] text-muted-soft truncate">oleh {{ $e['user'] }}</span> @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-8 text-sm text-center text-muted dark:text-gray-400">Belum ada perubahan status.</p>
                        @endforelse
                    </div>

                    {{-- PANEL PENANGANAN (khusus petugas) --}}
                    @if ($this->isPetugas())
                        <div class="px-6 py-4 border-t border-hairline bg-amber-50/60 dark:bg-amber-900/10">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">PENANGANAN</span>
                                <span class="text-xs text-muted dark:text-gray-400">Update status & solusi</span>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <x-input-label value="Status" class="mb-1" />
                                    <x-select-input wire:model="updStatus" class="w-full" :error="$errors->has('updStatus')">
                                        <option value="">— pilih —</option>
                                        @foreach ($this->statusOptions as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('updStatus')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Solusi / Tindakan" class="mb-1" />
                                    <textarea wire:model="updResolution" rows="2" maxlength="2000"
                                        class="w-full rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                                        placeholder="Tindakan teknis yang dilakukan..."></textarea>
                                    <x-input-error :messages="$errors->get('updResolution')" class="mt-1" />
                                </div>
                            </div>
                            <div class="flex justify-end mt-3">
                                <x-primary-button type="button" wire:click="savePenanganan" wire:loading.attr="disabled" wire:target="savePenanganan">
                                    <span wire:loading.remove wire:target="savePenanganan">Simpan Penanganan</span>
                                    <span wire:loading wire:target="savePenanganan">Menyimpan...</span>
                                </x-primary-button>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ╔═══ KANAN: CHAT & RESPON ═══╗ --}}
                <div class="flex flex-col min-h-0 lg:w-1/2">
                    <div class="px-6 py-2.5 border-b border-hairline bg-surface-soft dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-ink dark:text-gray-100">Chat & Respon</h3>
                    </div>

                    {{-- bubble chat --}}
                    <div class="flex-1 px-6 py-4 space-y-3 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                        @php $chats = array_values(array_filter($this->timeline, fn($e) => $e['kind'] === 'comment')); @endphp
                        @forelse ($chats as $e)
                            @php $own = $e['user_id'] === auth()->id(); @endphp
                            <div class="flex {{ $own ? 'justify-end' : 'justify-start' }}" wire:key="tl-c-{{ $e['id'] }}">
                                <div class="max-w-[85%]">
                                    <div class="flex items-center gap-2 mb-0.5 {{ $own ? 'justify-end' : '' }}">
                                        <span class="text-xs font-semibold text-ink dark:text-gray-100">{{ $e['user'] ?? 'User' }}</span>
                                        <span class="text-[10px] text-muted-soft">{{ $e['at_fmt'] }}</span>
                                    </div>
                                    <div class="p-3 rounded-2xl {{ $own
                                        ? 'bg-brand-green text-white rounded-tr-sm dark:bg-brand-lime dark:text-gray-900'
                                        : 'bg-canvas border border-hairline text-body rounded-tl-sm dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300' }}">
                                        <p class="text-sm whitespace-pre-line">{{ $e['content'] }}</p>
                                    </div>
                                    @if ($e['user_id'] === auth()->id() || auth()->user()?->hasRole('admin'))
                                        <div class="mt-0.5 {{ $own ? 'text-right' : '' }}">
                                            <button type="button" wire:click="deleteComment({{ $e['id'] }})" wire:confirm="Hapus respon ini?"
                                                class="text-[10px] text-muted-soft hover:text-red-600 transition">Hapus</button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-8 text-sm text-center text-muted dark:text-gray-400">Belum ada respon. Mulai percakapan.</p>
                        @endforelse
                    </div>

                    {{-- input respon --}}
                    <div class="px-6 py-4 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700"
                        x-data x-on:focus-comment.window="$nextTick(() => setTimeout(() => $refs.cmt?.focus(), 150))">
                        <textarea wire:model="newComment" x-ref="cmt" rows="2" maxlength="2000"
                            wire:keydown.ctrl.enter="addComment"
                            class="w-full rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                            placeholder="Tulis balasan... (Ctrl+Enter untuk kirim)"></textarea>
                        <x-input-error :messages="$errors->get('newComment')" class="mt-1" />
                        <div class="flex justify-end gap-2 mt-2">
                            <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                            <x-primary-button type="button" wire:click="addComment" wire:loading.attr="disabled" wire:target="addComment">
                                <span wire:loading.remove wire:target="addComment">Kirim Respon</span>
                                <span wire:loading wire:target="addComment">Mengirim...</span>
                            </x-primary-button>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </x-modal>
</div>
