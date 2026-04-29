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
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

use App\Http\Controllers\Staff\ReportController;
use App\Http\Requests\Staff\UpdateReportRequest;
use App\Models\Group;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('show reports returns an ok response', function (): void {
    $reports = Report::factory()->times(3)->create();
    $group = Group::factory()->create([
        'is_modo' => 1,
    ]);
    $user = User::factory()->create([
        'group_id' => $group->id,
    ]);

    $response = $this->actingAs($user)->get(route('staff.reports.index'));

    $response->assertOk();
    $response->assertViewIs('Staff.report.index');
});

test('show a report returns an ok response', function (): void {
    $report = Report::factory()->create();
    $group = Group::factory()->create([
        'is_modo' => 1,
    ]);
    $user = User::factory()->create([
        'group_id' => $group->id,
    ]);

    $response = $this->actingAs($user)->get(route('staff.reports.show', [$report]));

    $response->assertOk();
    $response->assertViewIs('Staff.report.show');
    $response->assertViewHas('report', $report);
});

test('update validates with a form request', function (): void {
    $this->assertActionUsesFormRequest(
        ReportController::class,
        'update',
        UpdateReportRequest::class
    );
});

test('update a report returns an ok response', function (): void {
    $report = Report::factory()->create([
        'verdict' => '',
    ]);
    $group = Group::factory()->create([
        'is_modo' => 1,
    ]);
    $user = User::factory()->create([
        'group_id' => $group->id,
    ]);

    $verdictMessage = 'This report has been resolved.';

    $response = $this->actingAs($user)->patch(route('staff.reports.update', ['report' => $report]), [
        'verdict' => $verdictMessage,
    ]);

    $response->assertRedirect(route('staff.reports.index'));
    $this->assertDatabaseHas('reports', [
        'id'        => $report->id,
        'verdict'   => $verdictMessage,
        'solved_by' => $user->id,
    ]);
});
