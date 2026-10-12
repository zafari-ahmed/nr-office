<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Setting;
use App\Models\SiteVisit;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SiteVisitController extends Controller
{
    public function index(Request $request)
    {
        $start = $request->query('from') ? Carbon::parse($request->query('from')) : today()->subDays(6);
        $days = (int) $request->query('days', 21);
        $end = $start->copy()->addDays($days - 1);

        $staff = Employee::active()->where('is_site_staff', true)->orderByRaw('rotation_order is null, rotation_order')->orderBy('full_name')
            ->with(['siteVisits' => fn ($q) => $q->with('project')->where('status', '!=', 'cancelled')
                ->where('departure_date', '<=', $end)
                ->where(fn ($w) => $w->where('expected_return_date', '>=', $start)->orWhere('actual_return_date', '>=', $start)->orWhere('status', 'on_site'))])
            ->get();

        $onSite = SiteVisit::onSite()->with('employee', 'project')->orderBy('expected_return_date')->get();
        $remind = Setting::int('rotation.remind_days_before');

        return view('rotation.index', [
            'staff' => $staff,
            'start' => $start,
            'days' => $days,
            'returningSoon' => $onSite->filter(fn ($v) => $v->displayStatus() === 'returning_soon'),
            'overdue' => $onSite->filter(fn ($v) => $v->displayStatus() === 'overdue'),
            'upcoming' => SiteVisit::where('status', 'scheduled')->with('employee')->orderBy('departure_date')->limit(8)->get(),
            'projects' => Project::whereIn('status', ['active', 'on_hold', 'quoted'])->orderBy('name')->get(['id', 'name', 'site_address', 'city']),
            'employees' => Employee::active()->where('is_site_staff', true)->orderBy('full_name')->get(['id', 'full_name']),
            'suggested' => $this->suggestNext(),
            'defaultDays' => Setting::int('rotation.default_stay_days'),
            'remindDays' => $remind,
            'onSiteCount' => $onSite->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $departure = Carbon::parse($data['departure_date']);
        $visit = SiteVisit::create([
            ...$data,
            'site_name' => $data['site_name'] ?: Project::find($data['project_id'])?->name ?? 'Site',
            'expected_return_date' => SiteVisit::returnDateFor($departure, (int) $data['planned_days']),
            'status' => $departure->lte(today()) ? 'on_site' : 'scheduled',
            'created_by' => $request->user()->id,
        ]);
        $visit->load('employee');
        Activity::log('visit_scheduled', "{$visit->employee->full_name} scheduled for {$visit->site_name} until ".$visit->expected_return_date->format('d M'), $visit);

        return back()->with('success', 'Visit scheduled. Return date '.$visit->expected_return_date->format('d M Y').'.');
    }

    /**
     * Edit any visit that isn't cancelled: change who, where and the dates, or end it early by giving a return date.
     * Status follows the dates: a departure in the future means scheduled, today or earlier means on site.
     */
    public function update(Request $request, SiteVisit $visit)
    {
        abort_if($visit->status === 'cancelled', 422, 'Cancelled visits cannot be edited.');
        $data = $this->validated($request);
        $extra = $request->validate([
            'returned_on' => ['nullable', 'date', 'after_or_equal:departure_date', 'before_or_equal:today'],
        ]);
        $departure = Carbon::parse($data['departure_date']);
        $expected = SiteVisit::returnDateFor($departure, (int) $data['planned_days']);

        $status = $departure->gt(today()) ? 'scheduled' : 'on_site';
        $returnedOn = $extra['returned_on'] ?? null;
        if ($returnedOn) {
            $status = 'returned';
        }

        $visit->update([
            ...$data,
            'site_name' => $data['site_name'] ?: Project::find($data['project_id'])?->name ?? $visit->site_name,
            'expected_return_date' => $expected,
            'status' => $status,
            'actual_return_date' => $returnedOn,
            'reminder_sent_at' => $visit->expected_return_date->equalTo($expected) ? $visit->reminder_sent_at : null,
        ]);
        $visit->load('employee');
        Activity::log('visit_updated', $returnedOn
            ? "{$visit->employee->full_name} returned from {$visit->site_name} on ".Carbon::parse($returnedOn)->format('d M')
            : "{$visit->employee->full_name}'s visit to {$visit->site_name} changed to ".$departure->format('d M').' → '.$expected->format('d M'), $visit);

        return back()->with('success', $returnedOn ? 'Visit ended on '.Carbon::parse($returnedOn)->format('d M').'.' : 'Visit updated. Return date '.$expected->format('d M Y').'.');
    }

    public function depart(SiteVisit $visit)
    {
        $visit->update(['status' => 'on_site', 'departure_date' => min($visit->departure_date, today())]);
        Activity::log('visit_departed', "{$visit->employee->full_name} left for {$visit->site_name}", $visit);

        return back()->with('success', 'Marked as on site.');
    }

    public function markReturned(Request $request, SiteVisit $visit)
    {
        $on = $request->validate(['returned_on' => ['nullable', 'date', 'before_or_equal:today']])['returned_on'] ?? null;
        $on = $on ? max(Carbon::parse($on), $visit->departure_date) : today();
        $visit->update(['status' => 'returned', 'actual_return_date' => $on]);
        Activity::log('visit_returned', "{$visit->employee->full_name} returned from {$visit->site_name}", $visit);

        return back()->with('success', $visit->employee->full_name.' marked as returned.');
    }

    public function extend(Request $request, SiteVisit $visit)
    {
        $extra = (int) $request->validate(['days' => ['required', 'integer', 'min:1', 'max:60']])['days'];
        $visit->update([
            'planned_days' => $visit->planned_days + $extra,
            'expected_return_date' => $visit->expected_return_date->copy()->addDays($extra),
            'reminder_sent_at' => null,
        ]);
        Activity::log('visit_extended', "{$visit->employee->full_name}'s stay at {$visit->site_name} extended by {$extra} days", $visit);

        return back()->with('success', 'Stay extended to '.$visit->expected_return_date->format('d M').'.');
    }

    public function cancel(SiteVisit $visit)
    {
        $visit->update(['status' => 'cancelled']);

        return back()->with('success', 'Visit cancelled.');
    }

    /**
     * Next in rotation: site staff not currently on site / scheduled, who has waited longest since
     * their last return (never-visited first), tie-broken by rotation order.
     */
    private function suggestNext(): ?Employee
    {
        return Employee::active()->where('is_site_staff', true)
            ->whereDoesntHave('siteVisits', fn ($q) => $q->whereIn('status', ['on_site', 'scheduled']))
            ->withMax(['siteVisits as last_return' => fn ($q) => $q->where('status', 'returned')], 'actual_return_date')
            ->get()
            ->sortBy([fn ($a, $b) => strcmp((string) $a->last_return, (string) $b->last_return), fn ($a, $b) => ($a->rotation_order ?? 999) <=> ($b->rotation_order ?? 999)])
            ->first();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'departure_date' => ['required', 'date'],
            'planned_days' => ['required', 'integer', 'min:1', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['site_name'] ??= '';

        return $data;
    }
}
