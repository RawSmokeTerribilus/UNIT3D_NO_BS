<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     Roardom <roardom@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Http\Livewire;

use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportesDescartadosEnBloque;
use App\Traits\LivewireSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ReportSearch extends Component
{
    use LivewireSort;
    use WithPagination;

    #TODO: Update URL attributes once Livewire 3 fixes upstream bug. See: https://github.com/livewire/livewire/discussions/7746

    #[Url(history: true)]
    public ?string $reporter = null;

    #[Url(history: true)]
    public ?string $reported = null;

    #[Url(history: true)]
    public ?string $staff = null;

    #[Url(history: true)]
    public ?string $judge = null;

    #[Url(history: true)]
    public ?string $title = null;

    #[Url(history: true)]
    public ?string $message = null;

    #[Url(history: true)]
    public ?string $verdict = null;

    #[Url(history: true)]
    public ?string $type = null;

    #[Url(history: true)]
    public ?string $status = 'open';

    #[Url(history: true)]
    public string $sortField = 'created_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    #[Url(history: true)]
    public int $perPage = 25;

    /*
     * NOBS: boton nuke. Cierra reportes en bloque con un veredicto fijo y avisa
     * una vez a cada reportero. Dos interruptores, tres modos: los marcados,
     * los del reportero del filtro, o los dos a la vez = todos los abiertos.
     * No toca nada mas que los reportes. Solo admin y superiores.
     */

    /**
     * @var list<int|string>
     */
    public array $marcados = [];

    public bool $modoMarcados = false;

    public bool $modoUsuario = false;

    private const string VEREDICTO_NUKE = 'Descartado en bloque por motivos tecnicos (nuke de reportes). '
        .'No se ha tomado ninguna medida sobre lo reportado. Si crees que merecia atencion, contacta con un admin.';

    final public function puedeNukear(): bool
    {
        return (bool) auth()->user()?->group?->is_admin;
    }

    /**
     * El filtro «Reporter» es un LIKE: «pepe» casaria con varios. Para el modo
     * usuario hace falta el nombre EXACTO de una sola cuenta.
     */
    private function reporteroExacto(): ?int
    {
        if ($this->reporter === null || trim($this->reporter) === '') {
            return null;
        }

        $id = User::where('username', '=', trim($this->reporter))->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return Builder<Report>|null
     */
    private function objetivoNuke(): ?Builder
    {
        $abiertos = Report::query()->whereNull('solved_by');

        if ($this->modoMarcados && $this->modoUsuario) {
            return $abiertos;
        }

        if ($this->modoMarcados) {
            $ids = array_values(array_filter(array_map('intval', $this->marcados)));

            return $ids === [] ? null : $abiertos->whereIntegerInRaw('id', $ids);
        }

        if ($this->modoUsuario) {
            $reportero = $this->reporteroExacto();

            return $reportero === null ? null : $abiertos->where('reporter_id', '=', $reportero);
        }

        return null;
    }

    final protected int $nukeCuenta {
        get => $this->puedeNukear() ? ($this->objetivoNuke()?->count() ?? 0) : 0;
    }

    final protected string $nukeModo {
        get => match (true) {
            $this->modoMarcados && $this->modoUsuario => 'todos',
            $this->modoMarcados                        => 'marcados',
            $this->modoUsuario                         => $this->reporteroExacto() === null ? 'usuario-sin-nombre' : 'usuario',
            default                                    => 'ninguno',
        };
    }

    final public function nuke(): void
    {
        abort_unless($this->puedeNukear(), 403);

        $objetivo = $this->objetivoNuke();

        if ($objetivo === null) {
            $this->dispatch('nuke-hecho', cerrados: 0);

            return;
        }

        $staff = auth()->user();
        $reportes = $objetivo->get(['id', 'reporter_id', 'title']);

        DB::transaction(function () use ($reportes, $staff): void {
            Report::query()
                ->whereIntegerInRaw('id', $reportes->pluck('id')->all())
                ->whereNull('solved_by')
                ->update([
                    'solved_by' => $staff->id,
                    'solved_at' => now(),
                    'verdict'   => self::VEREDICTO_NUKE,
                ]);
        });

        foreach ($reportes->groupBy('reporter_id') as $reporterId => $suyos) {
            $reportero = User::find($reporterId);

            if ($reportero === null || $reportero->id === User::SYSTEM_USER_ID) {
                continue;
            }

            $reportero->notify(new ReportesDescartadosEnBloque($suyos->pluck('title')->map(fn ($t) => (string) $t)->values()->all()));
        }

        $this->marcados = [];
        $this->modoMarcados = false;
        $this->modoUsuario = false;

        $this->dispatch('nuke-hecho', cerrados: $reportes->count());
    }

    /**
     * @var \Illuminate\Pagination\LengthAwarePaginator<int, Report>
     */
    final protected \Illuminate\Pagination\LengthAwarePaginator $reports {
        get => Report::query()
            ->with('reported.group', 'reporter.group', 'assignee.group')
            ->when($this->type !== null, fn ($query) => $query->where('type', '=', $this->type))
            ->when($this->reporter !== null, fn ($query) => $query->whereRelation('reporter', 'username', 'LIKE', '%'.$this->reporter.'%'))
            ->when($this->reported !== null, fn ($query) => $query->whereRelation('reported', 'username', 'LIKE', '%'.$this->reported.'%'))
            ->when($this->staff !== null, fn ($query) => $query->whereRelation('assignee', 'username', 'LIKE', '%'.$this->staff.'%'))
            ->when($this->judge !== null, fn ($query) => $query->whereRelation('judge', 'username', 'LIKE', '%'.$this->judge.'%'))
            ->when($this->title !== null, fn ($query) => $query->where('title', 'LIKE', '%'.str_replace(' ', '%', '%'.$this->title.'%')))
            ->when($this->message !== null, fn ($query) => $query->where('message', 'LIKE', '%'.str_replace(' ', '%', '%'.$this->message.'%')))
            ->when($this->verdict !== null, fn ($query) => $query->where('verdict', 'LIKE', '%'.str_replace(' ', '%', '%'.$this->verdict.'%')))
            ->when($this->status === 'open', fn ($query) => $query->whereNull('solved_by')->where(fn ($query) => $query->whereNull('snoozed_until')->orWhere('snoozed_until', '<', now())))
            ->when($this->status === 'snoozed', fn ($query) => $query->whereNull('solved_by')->where('snoozed_until', '>', now()))
            ->when($this->status === 'closed', fn ($query) => $query->whereNotNull('solved_by'))
            ->when($this->status === 'all_open', fn ($query) => $query->whereNull('solved_by'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    final public function render(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.report-search', [
            'reports'     => $this->reports,
            'puedeNukear' => $this->puedeNukear(),
            'nukeCuenta'  => $this->nukeCuenta,
            'nukeModo'    => $this->nukeModo,
        ]);
    }
}
