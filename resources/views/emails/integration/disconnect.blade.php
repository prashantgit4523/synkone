@component('mail::layout')
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
        >
            <p style="margin-bottom: 20px;">Hi {{decodeHTMLEntity($admin->full_name)}},</p>
            <p>Integration with <strong>{{ $integration->name }}</strong> was disconnected.</p>
            <p>Because of this the following project(s) no longer
                has/have technical automated control implementation and they require manual implementation.</p>
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block" style="text-align: center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;">
            <ul>
                @foreach($admin->projects as $project)
                    <li>
                        <p>Title: {{ $project->name }}</p>
                        <p>Standard: {{ $project->standard }}</p>
                        <p>Description: {{ $project->description }}</p>
                        <a class="btn btn-small btn-primary pull-right"
                           style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 13px; color: #FFF; text-decoration: none; line-height: 1.5em; font-weight: bold; text-align: center; cursor: pointer; display: inline-block; text-transform: capitalize; background-color: #6658dd; margin: 0; border-color: #6658dd; border-style: solid; border-width: 8px 16px;margin-bottom: 16px;"
                           href="{{ route('compliance-project-show', [$project->id, 'controls']) }}">
                            Go to project
                        </a>
                    </li>
                @endforeach
            </ul>
        </td>
    </tr>
@endcomponent
