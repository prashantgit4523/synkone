@php
$global_setting = \App\Models\GlobalSettings\GlobalSetting::first();

if ($control_document->is_generated) {
    $creation_date = $control_document->project_created_at;
    $last_update = Carbon\Carbon::now()->format('d/m/Y');
} else {
    $creation_date = $control_document->template->versions()->first();

    if ($creation_date !== null) {
        $creation_date = $creation_date->created_at->format('d/m/Y h:i:s A');
    }

    if ($control_document->created_at) {
        $last_update = $control_document->created_at->format('d/m/Y h:i:s A');
    }
}

$documents = $control_document->template
    ->versions()
    ->when(request()->filled('version'), fn($q) => $q->where('created_at', '<=', $control_document->created_at))
    ->orderByDesc('id')
    ->get();
@endphp
<style>
    body {
        padding: 0;
        margin: 0;
        font-family: sans-serif;
    }

    .underline {
        text-decoration: underline;
    }

    .bg-primary {
        background-color: {{ $global_setting->secondary_color }};
    }

    .text-primary {
        color: {{ $global_setting->secondary_color }};
    }

    table,
    th,
    td {
        border: .1px solid black;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 10px;
        text-align: left;
    }

    .page {
        padding: 60px;
        display: flex;
        flex-direction: column;
    }

    .break-page {
        page-break-after: always;
        page-break-inside: avoid;
    }

    .align-center {
        text-align: center;
    }

    .w-full {
        width: 100%;
    }

</style>

<body>
    <div class="page break-page align-center">
        <span><img src="{{ $control_document->getCompanyLogo() }}" height="400" /></span>
        <h1 class="underline text-primary">{{ $control_document->title }}</h1>
        <table class="w-full">
            <tr class="bg-primary">
                <th>Owner</th>
                <td>
                    @if ($control_document->is_generated)
                        System Generated
                    @else
                        {{ $control_document->admin?->full_name }}
                    @endif
                </td>
            </tr>
            <tr>
                <th>Version</th>
                <td>{{ $control_document->version }}</td>
            </tr>
            <tr>
                <th>Creation Date</th>
                <td>
                    {{ $creation_date }}
                </td>
            </tr>
            <tr>
                <th>Last Update</th>
                <td>{{$last_update??null}}</td>
            </tr>
            <tr>
                <th>Review Period</th>
                <td>Yearly</td>
            </tr>
            <tr>
                <th>Classification</th>
                <td>Internal</td>
            </tr>
        </table>
    </div>
    @if (!$control_document->is_generated)
        <div class="page break-page">
            <h2>Revision & Approval History</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-primary">
                        <th>Version</th>
                        <th>Updated by</th>
                        <th>Date</th>
                        <th>Description of Change</th>
                        <th>Approved by</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td>{{ $document->version }}</td>
                            <td>{{ $document->admin?->full_name }}</td>
                            <td>{{ $document->created_at->format('d/m/Y h:i:s A') }}</td>
                            <td>{{ $document->description }}</td>
                            <td>{{ $document->admin?->full_name }}</td>
                            <td>{{ $document->created_at->format('d/m/Y h:i:s A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <h2>Distribution list</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-primary">
                        <th>Version</th>
                        <th>Entity</th>
                        <th>Name/Designation</th>
                        <th>Date</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td>{{ $document->version }}</td>
                            <td>Internal</td>
                            <td>All affected staff</td>
                            <td>{{ $document->created_at->format('d/m/Y h:i:s A') }}</td>
                            <td>For compliance</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="page">
        {!! $control_document->body !!}
    </div>

</body>
